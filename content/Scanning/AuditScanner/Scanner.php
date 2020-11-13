<?php
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../../common/sqlconnect/SQLManager.php');
}
if (!class_exists('PriceRounder')) {
    include(__DIR__.'/../../../common/lib/PriceRounder.php');
}
class Scanner extends PageLayoutA
{
    protected $ui = false;
    protected $enable_linea = true;
    protected $must_authenticate = TRUE;

    protected $columns = array('par', 'inUse', 'posBrand', 'posDesc', 'signBrand', 
        'signDesc', 'narrow', 'cost', 'adj', 'PO', 'price', 'vendor', 'dept', 'size', 
        'units', 'curMargin', 'prid', 'location', 'note');

    public function preprocess()
    {
        $this->displayFunction = $this->getView();
        $this->__routes[] = 'post<test>';
        $this->__routes[] = 'post<setcolumn>';
        $this->__routes[] = 'post<note>';

        return parent::preprocess();
    }

    public function postNoteHandler()
    {
        $dbc = ScanLib::getConObj('SCANALTDB');

        $upc = FormLib::get('upc');
        $note = FormLib::get('note');
        $username = FormLib::get('username');

        $args = array($note, $upc, $username);
        $prep = $dbc->prepare("UPDATE AuditScan
            SET notes = ? WHERE upc = ? AND username = ?;");
        $res = $dbc->execute($prep, $args);

        return false;
    }

    public function postSetcolumnHandler()
    {
        $active = FormLib::get('active');
        $col = FormLib::get('col');
        $_SESSION['view'][$col] = $active;

        //echo $col . ', ' . $active;

        return false;
        //return header('location: ' . $_SERVER['REQUEST_URI']);
    }

    public function postColumnSetHandler()
    {
        $dbc = ScanLib::getConObj();
        $column = FormLib::get('columnSet');
        $column = 23 - $column;
        $set = FormLib::get('set');
        $change = ($set == "true") ? " | (1 << $column)" : " & ~(1 << $column)";

        $args = array(session_id());
        $query = "UPDATE woodshed_no_replicate.ScannieConfig SET auditReportOpt = auditReportOpt $change  WHERE session_id = ?";
        $prep = $dbc->prepare($query);
        $res = $dbc->execute($prep, $args);
        $json = array();
        if ($err = $dbc->error())
            $json['error'] = $er;
        echo json_encode($json);

        return false;
    }

    public function postReviewView()
    {
        $dbc = ScanLib::getConObj();
        $review = FormLib::get('review');
        $username = FormLib::get('username');
        $json = array();

        if ($review == 'open') {
            $prep = $dbc->prepare("INSERT INTO woodshed_no_replicate.temp (upc,cost) SELECT upc, cost FROM products WHERE UPC in (SELECT upc FROM woodshed_no_replicate.AuditScan WHERE username = ?) GROUP BY upc;");
            $res = $dbc->execute($prep, array($username));
        } elseif ($review == 'close') {
            $prep = $dbc->prepare("INSERT INTO productCostChanges (upc, previousCost, newCost, difference, date) 
                SELECT 
                t.upc AS upc, 
                t.cost as previousCost, 
                p.cost as newCost, 
                (p.cost - t.cost) AS difference, 
                DATE(NOW()) AS date 
                FROM woodshed_no_replicate.temp AS t 
                LEFT JOIN products AS p ON t.upc = p.upc 
                RIGHT JOIN woodshed_no_replicate.AuditScan AS a ON p.upc=a.upc
                WHERE (p.cost - t.cost) <> 0 
                AND a.username = ?
                GROUP BY p.upc 
                ON DUPLICATE KEY UPDATE previousCost=VALUES(previousCost), newCost=VALUES(newCost), difference=VALUES(difference), date=VALUES(date);
            ");
            $res = $dbc->execute($prep, array($username));
            if (!$er = $dbc->error()) {
                $prep = $dbc->prepare("DELETE FROM woodshed_no_replicate.temp");
                $res = $dbc->execute($prep);
            }
        }
        $suff = '';
        if ($er = $dbc->error())
            $suff = "?$er"; 


        return header("location: AuditReport.php$suff");
    }

    private function getScaleItem($dbc, $upc)
    {
        $data = array();

        return $data;
    }

    private function getScaleData($dbc, $upc)
    {
        $args = array($upc);
        $prep = $dbc->prepare("SELECT 
            CASE 
                WHEN bycount = 0 THEN 'Random'
                WHEN bycount = 1 THEN 'Fixed'
                ELSE 'not in scale'
            END AS bycount
            FROM scaleItems 
            WHERE plu = ?");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $value = $row['bycount'];
            $bycount = ($value > -1) ? $value : 5;
        }
        echo $dbc->error();

        return $bycount;
    }

    private function getMovement($dbc, $upc)
    {
        $data = array();
        $args = array($upc);
        $prep = $dbc->prepare("SELECT DATE(last_sold) AS last_sold, inUse, store_id FROM products WHERE upc = ?
            ORDER BY upc, store_id;");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $data[$row['store_id']]['last_sold'] = $row['last_sold'];
            $data[$row['store_id']]['inUse'] = $row['inUse'];
        }

        return $data;
    }

    public function postTestHandler()
    {
        $json = array('test'=>'successful');
        echo json_encode($json);

        return false;
    }

    private function getDeptOptions($dbc, $dept)
    {
        $args = array();
        $prep = $dbc->prepare("SELECT dept_no, dept_name FROM departments;");
        $res = $dbc->execute($prep);
        $departments = "<select class=\"edit-department\">";
        while ($row = $dbc->fetchRow($res)) {
            $num = $row['dept_no'];
            $name = $row['dept_name'];
            $sel = ($dept == $num) ? 'selected' : '';
            $departments .= "<option value=\"$num\" $sel>$num - $name</option>";
        }
        $departments .= "</select>";
         
        return $departments;
    }

    public function postCheckedHandler()
    {
        $upc = FormLib::get('upc');
        $checked = FormLib::get('checked');
        $checked = ($checked == 'false') ? 0 : 1;
        $username = FormLib::get('username');
        $storeID = FormLib::get('storeID');
        $json = array();

        $dbc = ScanLib::getConObj();
        $args = array($checked, $storeID, $username, $upc);
        $query = $dbc->prepare("UPDATE woodshed_no_replicate.AuditScan SET checked = ? WHERE storeID = ? AND username = ? AND upc = ?");
        $dbc->execute($query, $args);
        if ($er = $dbc->error())
            $json['error'] = $er;
        echo json_encode($json);

        return false;
    }

    public function postSetNotesHandler()
    {
        $upc = FormLib::get('upc');
        $notes = FormLib::get('notes');
        $username = FormLib::get('username');
        $storeID = FormLib::get('storeID');
        $json = array();

        $dbc = ScanLib::getConObj('SCANALTDB');
        $mod = new DataModel($dbc);
        $json['saved'] = $mod->setNotes($upc, $storeID, $notes, $username);
        echo json_encode($json);

        return false;
    }

    public function postSetCostHandler()
    {
        $upc = FormLib::get('upc');
        $cost = FormLib::get('cost');
        $json = array();

        $dbc = ScanLib::getConObj();
        $mod = new DataModel($dbc);
        $json['saved'] = $mod->setCost($upc, $cost);
        echo json_encode($json);

        return false;
    }

    public function postSetDeptHandler()
    {
        $upc = FormLib::get('upc');
        $dept = FormLib::get('department');
        $table = FormLib::get('table');
        $json = array();

        $dbc = ScanLib::getConObj();
        $mod = new DataModel($dbc);
        $json['saved'] = $mod->setDept($upc, $dept);
        echo json_encode($json);

        return false;
    }
     
    public function postSetDescriptionHandler()
    {
        $upc = FormLib::get('upc');
        $description = FormLib::get('description');
        $table = FormLib::get('table');
        $json = array();

        $dbc = ScanLib::getConObj();
        $mod = new DataModel($dbc);
        $json['saved'] = $mod->setDescription($upc, $description, $table);
        echo json_encode($json);

        return false;
    }

    public function postSetBrandHandler()
    {
        $upc = FormLib::get('upc');
        $brand = FormLib::get('brand');
        $table = FormLib::get('table');
        $json = array();

        $dbc = ScanLib::getConObj();
        $mod = new DataModel($dbc);
        $json['saved'] = $mod->setBrand($upc, $brand, $table);
        echo json_encode($json);

        return false;
    }

    public function postSetSkuHandler()
    {
        $upc = FormLib::get('upc');
        $sku = FormLib::get('sku');
        $lastSku = FormLib::get('lastSku');
        $vendorID = FormLib::get('vendorID');
        $json = array();

        $dbc = ScanLib::getConObj();
        $mod = new DataModel($dbc);
        $json['saved'] = $mod->setSku($vendorID, $lastSku, $upc, $sku);
        echo json_encode($json);

        return false;
    }

    public function postDeleteRowHandler()
    {
        $storeID = FormLib::get('storeID');
        $username = FormLib::get('username');
        $upc = FormLib::get('upc');
        $json = array();
        $json['test'] = 'test';

        $dbc = ScanLib::getConObj();
        $args = array($upc, $storeID, $username);
        $prep = $dbc->prepare('DELETE FROM woodshed_no_replicate.AuditScan WHERE upc = ? AND storeID = ? AND username = ?');
        $dbc->execute($prep, $args);
        if ($er = $dbc->error()) {
            $json['dbc-error'] = $er;
        }
        echo json_encode($json);

        return false;
    }

    public function postClearHandler()
    {
        $dbc = ScanLib::getConObj();
        $storeID = FormLib::get('storeID');
        $username = FormLib::get('username');
        $args = array($storeID, $username);
        $query = $dbc->prepare("DELETE FROM woodshed_no_replicate.AuditScan WHERE storeID = ? AND username = ?");
        $dbc->execute($query, $args);

        $json = array('test'=>'successful');
        echo json_encode($json);

        return false;
    }


    public function postNotesHandler()
    {
        $dbc = ScanLib::getConObj();
        $storeID = FormLib::get('storeID');
        $username = FormLib::get('username');
        $args = array($storeID, $username);
        $query = $dbc->prepare("UPDATE woodshed_no_replicate.AuditScan SET notes = '' WHERE storeID = ? AND username = ?");
        $dbc->execute($query, $args);

        $json = array('test'=>'successful');
        echo json_encode($json);

        return false;
    }

    public function postUpcsHandler()
    {
        $dbc = ScanLib::getConObj();
        $storeID = FormLib::get('storeID');
        $username = FormLib::get('username');

        $upcs = FormLib::get('upcs');
        $plus = array();
        $chunks = explode("\r\n", $upcs);
        foreach ($chunks as $key => $str) {
            $str = scanLib::upcParse($str);
            $str = scanLib::upcPreparse($str);
            $plus[] = $str;
        }
        foreach ($plus as $upc) {
            if ($upc != 0) {
                $args = array($upc, $username, $storeID);
                $prep = $dbc->prepare("INSERT IGNORE INTO woodshed_no_replicate.AuditScan (upc, username, storeID, date)
                    VALUES (?, ?, ?, NOW());");
                $res = $dbc->execute($prep, $args);
            }
        }

        return header('location: AuditReport.php');
    }

    public function postRowCountHandler()
    {
        $dbc = ScanLib::getConObj();

        $json = array('count' => null);
        $username = ($un = scanLib::getUser()) ? $un : "Generic User";
        $storeID = scanLib::getStoreID();
        $args = array($username, $storeID);
        $query = $dbc->prepare("
            SELECT upc
            FROM woodshed_no_replicate.AuditScan
            WHERE username = ?
                AND storeID = ?
        ");
        $result = $dbc->execute($query, $args);
        $json['count'] = $dbc->numRows($result);
        echo json_encode($json);

        return false;
    }

    public function postFetchHandler()
    {
        $dbc = ScanLib::getConObj();
        $username = ($un = scanLib::getUser()) ? $un : "Generic User";
        $storeID = scanLib::getStoreID();
        $rounder = new PriceRounder();

        $args = array($username, $storeID);
        $prep = $dbc->prepare("
            SELECT 
                p.upc,
                v.sku, 
                p.brand, 
                u.brand AS signBrand,
                p.description AS description,
                u.description AS signDescription,
                p.cost,
                CASE 
                    WHEN e.shippingMarkup > 0 THEN p.cost + (p.cost * e.shippingMarkup) ELSE p.cost
                END AS adjcost,
                p.normal_price AS price, 
                p.special_price AS sale,
                t.description AS priceRuleType, 
                CONCAT(p.department, ' - ', d.dept_name) AS dept,
                d.dept_no, 
                d.dept_name, 
                e.vendorID, 
                CONCAT(e.vendorID, ' - ', e.vendorName) AS vendor,
                e.vendorID AS vendorID,
                a.date,
                a.username,
                100 * (p.normal_price - p.cost) / p.normal_price AS curMargin,
                100 * ROUND(CASE
                    WHEN vd.margin > 0.01 THEN vd.margin ELSE d.margin 
                END, 4) AS margin,
                a.notes,
                CASE
                    WHEN vd.margin > 0.01 THEN p.cost / (1 - vd.margin) ELSE p.cost / (1 - dm.margin)
                END AS rsrp,
                a.checked,
                p.last_sold,
                pr.reviewed,
                CASE
                    WHEN p.size <> 0 THEN p.size ELSE v.size
                END AS size,
                v.units,
                c.previousCost,
                c.newCost,
                c.difference AS costChange,
                c.date AS costChangeDate
            FROM products AS p
                LEFT JOIN vendorItems AS v ON p.default_vendor_id=v.vendorID AND p.upc=v.upc
                LEFT JOIN productUser AS u ON p.upc=u.upc
                LEFT JOIN PriceRules AS r ON p.price_rule_id=r.PriceRuleID
                LEFT JOIN PriceRuleTypes AS t ON r.priceRuleTypeID=t.priceRuleTypeID
                LEFT JOIN departments AS d ON p.department=d.dept_no
                LEFT JOIN vendors AS e ON p.default_vendor_id=e.vendorID
                RIGHT JOIN woodshed_no_replicate.AuditScan AS a ON p.upc=a.upc
                LEFT JOIN deptMargin AS dm ON p.department=dm.dept_ID
                LEFT JOIN vendorDepartments AS vd
                    ON vd.vendorID = p.default_vendor_id AND vd.posDeptID = p.department 
                LEFT JOIN prodReview AS pr ON p.upc=pr.upc
                LEFT JOIN productCostChanges AS c ON p.upc=c.upc
            WHERE p.upc != '0000000000000'
                AND a.username = ?
                AND a.storeiD = ?
            GROUP BY a.upc
            ORDER BY a.date DESC
        ");

        $td = "";
        $textarea = "<div style=\"position: relative\">
            <span class=\"status-popup\">Copied!</span>
            <textarea class=\"copy-text\" rows=3 cols=10>";

        // this is the second thead row (filters)
        $pth = "
        <tr id=\"filter-tr\">
            <td title=\"upc\" data-column=\"upc\"class=\"upc column-filter\"upc</td>
            <td title=\"sku\" data-column=\"sku\"class=\"sku column-filter\"></td>
            <td title=\"band\" data-column=\"brand\"class=\"brand column-filter\"></td>
            <td title=\"sign-brand\" data-column=\"sign-brand\"class=\"sign-brand column-filter\"></td>
            <td title=\"description\" data-column=\"description\"class=\"column-filter\"></td>
            <td title=\"sign-description\" data-column=\"sign-description\"class=\"sign-description column-filter\"></td>
            <td title=\"size\" data-column=\"size\"class=\"size column-filter\"></td>
            <td title=\"units\" data-column=\"units\"class=\"units column-filter\"></td>
            <td title=\"netCost\" data-column=\"netCost\"class=\"netCost column-filter\"></td>
            <td title=\"cost\" data-column=\"cost\"class=\"cost column-filter\"></td>
            <td title=\"recentPurchases\" data-column=\"recentPurchase\"class=\"recentPurchase column-filter\"></td>
            <td title=\"price\" data-column=\"price\"class=\"price column-filter\"></td>
            <td title=\"sale\" data-column=\"sale\"class=\"sale column-filter\"></td>
            <td title=\"margin_target_diff\" data-column=\"margin_target_diff\"class=\"margin_target_diff column-filter\"></td>
            <td title=\"srp\" data-column=\"srp\"class=\"srp column-filter\"></td>
            <td title=\"rsrp\" data-column=\"rsrp\"class=\"rsrp column-filter\"></td>
            <td title=\"prid\" data-column=\"prid\"class=\"prid column-filter\"></td>
            <td title=\"dept\" data-column=\"dept\"class=\"dept column-filter\"></td>
            <td title=\"vendor\" data-column=\"vendor\"class=\"vendor column-filter\"></td>
            <td title=\"last_sold\" data-column=\"last_sold\"class=\"last_sold column-filter\"></td>
            <td title=\"scaleItem\" data-column=\"scaleItem\"class=\"scaleItem column-filter\"></td>
            <td title=\"reviewed\" data-column=\"reviewed\"class=\"reviewed column-filter\"></td>
            <td title=\"costChange\" data-column=\"costChange\"class=\"costChange column-filter\"></td>
            <td title=\"notes\" data-column=\"notes\"class=\"notes column-filter\"></td>
            <td title=\"check\" data-column=\"check\" class=\"check column-filter\"></td>
            <td title=\"unknown\" data-column=\"unknown\" class=\"unknown column-filter\"></td>
        </tr>
        ";

        // this is the first thead row (column sorting)
        $th = "
        <tr>
            <th class=\"upc\">upc</th>
            <th class=\"sku\">sku</th>
            <th class=\"brand\">brand</th>
            <th class=\"sign-brand \">sign-brand</th>
            <th class=\"description\">description</th>
            <th class=\"sign-description \">sign-description</th>
            <th class=\"size\">size</th>
            <th class=\"units\">units</th>
            <th class=\"netCost\">netCost</th>
            <th class=\"cost\">cost</th>
            <th class=\"recentPurchase\">PO-unit</th>
            <th class=\"price\">price</th>
            <th class=\"sale\">sale</th>
            <th class=\"margin_target_diff\">margin / target (diff)</th>
            <th class=\"srp\">srp</th>
            <th class=\"rsrp\">round srp</th>
            <th class=\"prid\">prid</th>
            <th class=\"dept\">dept</th>
            <th class=\"vendor\">vendor</th>
            <th class=\"last_sold\">last_sold</th>
            <th class=\"scaleItem\">scale</th>
            <th class=\"reviewed\">reviewed</th>
            <th class=\"costChange\">last cost change</th>
            <th class=\"notes\">notes</th>
            <th class=\"check\"></th>
            <th class=\"\"></th>
        </tr>
        ";
        $result = $dbc->execute($prep, $args);
        $upcs = array();
        while ($row = $dbc->fetch_row($result)) {
            $upc = $row['upc'];
            $upcs[$upc] = $upc;
            $data = $this->getMovement($dbc, $upc);
            $bycount = null;
            $bycount = $this->getScaleData($dbc, $upc);
            $lastSold = '';
            foreach ($data as $storeID => $bRow) {
                $inUse = ($bRow['inUse'] != 1) ? 'alert-danger' : 'alert-success';
                $ls = ($bRow['last_sold'] == null) ? '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' : $bRow['last_sold'];
                $lastSold .= '('.$storeID.') <span class="'.$inUse.'">'.$ls.'</span> ';
            }
            $sku = $row['sku'];
            list($recentPurchase, $received) = $this->getRecentPurchase($dbc,$sku);
            $brand = $row['brand'];
            $signBrand = $row['signBrand'];
            $description = $row['description'];
            $signDescription = $row['signDescription'];
            $netCost = $row['cost'];
            $cost = $row['cost'];
            $ogCost = null;
            $adjcost = $row['adjcost'];
            $price = $row['price'];
            $sale = $row['sale'];
            $sale = ($sale == '0.00') ? '' : "$$sale";
            $margin = round($row['margin'], 2);
            $curMargin = round($row['curMargin'], 2);
            $rsrp = round($row['rsrp'], 2);
            $srp = $rounder->round($rsrp);
            if ($adjcost != $cost) {
                $ogCost = "title=\"Cost before adjustments: $cost\"";
                $cost = round($adjcost, 3);
                $curMargin = round(100 * ($price - $cost) / $price, 3);
                $rsrp = round($cost / (1 - ($margin/100)), 2);
                $srp = $rounder->round($rsrp);
                if ($upc == '0024238000000') {
                    //echo $margin; // this is incorrect
                }
            }
            $prid = $row['priceRuleType'];
            $dept = $row['dept'];
            $vendor = $row['vendor'];
            $notes = $row['notes'];
            $vendorID = $row['vendorID'];
            $checked = $row['checked'];
            $checked = ($checked == 1) ? 'checked' : '';
            $rowID = uniqid();
            $deptOpts = $this->getDeptOptions($dbc, $row['dept_no']);
            $reviewed = $row['reviewed'];
            $size = $row['size'];
            $units = $row['units'];
            $costChangeDate = $row['costChangeDate'];
            $costChange = $row['costChange'];
            $td .= "<tr class=\"prod-row\" id=\"$rowID\">";
            $td .= "<td class=\"sku editable editable-sku\">$sku</td>";
            $td .= "<td class=\"brand editable editable-brand\" data-table=\"products\">$brand</td>";
            $td .= "<td class=\"sign-brand editable editable-brand \" data-table=\"productUser\">$signBrand</td>";
            $td .= "<td class=\"description editable editable-description\" data-table=\"products\">$description</td>";
            $td .= "<td class=\"sign-description editable editable-description \" data-table=\"productUser\">$signDescription</td>";
            $td .= "<td class=\"size\">$size</td>";
            $td .= "<td class=\"units\">$units</td>";
            $td .= "<td class=\"netCost\">$netCost</td>";
            $td .= "<td class=\"cost\" $ogCost>$cost</td>";
            $td .= "<td class=\"recentPurchase\" title=\"$received\">$recentPurchase</td>";
            $td .= "<td class=\"price\">$price</td>";
            $td .= "<td class=\"sale\">$sale</td>";
            $diff = round($curMargin - $margin, 1);
            $td .= "<td class=\"margin_target_diff\">$curMargin / $margin ($diff)</td>";
            $td .= "<td class=\"rsrp\">$rsrp</td>";
            $td .= "<td class=\"srp\">$srp</td>";
            $td .= "<td class=\"prid\">$prid</td>";
            $td .= "<td class=\"dept\">
                <span class=\"dept-text\">$dept</span>
                <span class=\"dept-select hidden\">$deptOpts</span>
                </td>";
            $td .= "<td class=\"vendor\" data-vendorID=\"$vendorID\">$vendor</td>";
            $td .= "<td class=\"last_sold\">$lastSold</td>";
            $td .= "<td class=\"scaleItem\">$bycount</td>";
            $td .= "<td class=\"reviewed\">$reviewed</td>";
            $oper = ($costChange > 0) ? '+' : '-';
            $td .= "<td class=\"costChange\">$oper$costChange - $costChangeDate</td>";
            $td .= "<td class=\"notes editable editable-notes\">$notes</td>";
            $td .= "<td><span class=\"scanicon scanicon-trash scanicon-sm \"></span></td></td>";
            $td .= "<td class=\"check\"><input type=\"checkbox\" name=\"check\" class=\"row-check\" $checked/></td>";
            $td .= "</tr>";
            $textarea .= "$upc\r\n";
        }
        $textarea .= "</textarea></div>";
        $rows = $dbc->numRows($result);

        $prep = $dbc->prepare("SELECT upc FROM woodshed_no_replicate.AuditScan");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            $upc = $row['upc'];
            $uLink = '<a class="upc" href="../../../../git/fannie/item/ItemEditorPage.php?searchupc='.$upc.
                '&ntype=UPC&searchBtn=" target="_blank">'.$upc.'</a>';
            if (!in_array($upc, $upcs)) {
                $td .= "<tr class=\"prod-row\" id=\"$rowID\">";
                $td .= "<td class=\"upc\" data-upc=\"$upc\">$uLink</td>";
                $td .= "<td></td><td></td><td><i>Unknown PLU / Create New Product</i></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>";
                $td .= "<td><span class=\"scanicon scanicon-trash scanicon-sm \"></span></td><td></td>";
                $td .= "</tr>";
                $rows++;
            }
        }
        echo $dbc->error();

        $ret = <<<HTML
<input type="hidden" id="table-rows" value="$rows" />
<div class="table-responsive">
    <table class="table table-bordered table-sm small items" id="mytable">
    <thead>$th</thead>
    $pth
    <tbody id="mytablebody">
        $td
        <tr><td>$textarea</td></tr>
    </tbody>
    </table>
</div>
HTML;

        if (FormLib::get('fetch') == 'true') {
            echo $ret;
            return false;
        } else {
            return $ret;
        }

    }

    private function getRecentPurchase($dbc,$sku)
    {
        $args = array($sku);
        $prep = $dbc->prepare("SELECT
            sku, internalUPC, brand, description, DATE(receivedDate) AS receivedDate,
            caseSize, receivedTotalCost AS cost,
            unitCost, ROUND(receivedTotalCost/caseSize,3) AS mpcost
            FROM PurchaseOrderItems WHERE sku = ?
                AND unitCost > 0
            ORDER BY receivedDate DESC
            limit 1");
        $result = $dbc->execute($prep,$args);
        $options = array();
        $row = $dbc->fetch_row($result);
        $unitCost = $row['unitCost'];
        $received = $row['receivedDate'];

        return array($unitCost, $received);
    }

    private function getNotesOpts($dbc,$storeID,$username)
    {
        $args = array($storeID,$username);
        $query = $dbc->prepare("SELECT notes FROM woodshed_no_replicate.AuditScan WHERE storeID = ? AND username = ? GROUP BY notes;");
        $result = $dbc->execute($query,$args);
        $options = array();
        while ($row = $dbc->fetch_row($result)) {
            if ($row['notes'] != '') {
                $options[] = $row['notes'];
            }
        }
        echo $dbc->error();
        return $options;
    }

    private function recordData($upc, $username, $storeID)
    {
        $dbc = scanLib::getConObj('SCANALTDB');
        $args = array($upc, $username, $storeID);
        if ($upc == 0)
            return false;
        $prep = $dbc->prepare("INSERT IGNORE INTO AuditScan (upc, username, storeID, date)
            VALUES (?, ?, ?, NOW())");
        $res = $dbc->execute($prep, $args);
        if ($dbc->error()) {
            return '<div class="alert alert-danger">' . $dbc->error() . '</div>';
        } else {
            return false;
        }
    }

    private function getCount($dbc,$storeID,$username)
    {
        $args = array($username,$storeID);
        $prep = $dbc->prepare("SELECT count(*) FROM woodshed_no_replicate.AuditScan
            WHERE username = ? AND storeID = ?");
        $res = $dbc->execute($prep,$args);
        $count = $dbc->fetchRow($res);
        return $count[0];
    }

    public function getView()
    {
        // use session values to remember which columns to show
        if (!isset($_SESSION['view'])) {
            $_SESSION['view'] = array();
            foreach ($this->columns as $col) {
                $_SESSION['view'][$col] = 1;
            }
        }
        $_SESSION['view']['test'] = 'Successful';
        $hiddenColumnSelector = <<<HTML
<div id="hiddenColumnSelector">
    <div id="hiddenColumnSelectorClose" onclick="window.location.reload(); $('#hiddenColumnSelector').hide();"><</div>
HTML;
        foreach ($this->columns as $col) {
            $inUse = ($_SESSION['view'][$col] == 1) ? 'active' : '';
            $hiddenColumnSelector .= <<<HTML
    <div class="select-row col-select $inUse" data-column="$col" data-name="$col">$col</div>
HTML;
        }
$hiddenColumnSelector .= "</div>";

        $FANNIE_ROOTDIR = $this->config->vars['FANNIE_ROOTDIR'];

        $dbc = scanLib::getConObj();
        $username = ($un = scanLib::getUser()) ? $un : "Generic User";
        $storeID = scanLib::getStoreID();
        $rounder = new PriceRounder();
        $upc = scanLib::padUPC(FormLib::get('upc'));
        $autoParMulti = ($storeID == 1) ? 3 : 7;
        $rec = $this->recordData($upc, $username, $storeID);
        $count = $this->getCount($dbc, $storeID, $username);


        $dbcB = scanLib::getConObj('SCANALTDB');
        $p = $dbcB->prepare("SELECT * FROM ScannieConfig WHERE session_id = ?");
        $r = $dbcB->execute($p, session_id());
        $scannerConfig = array();
        /*
        $cols = array('scanBeep', 'auditPar', 'auditCost', 'auditSrp',
            'auditProdInfo', 'auditVendorInfo', 'auditSize', 'auditSignInfo',
            'auditSaleInfo', 'auditLocations', 'socketDevice');
        while ($row = $dbcB->fetchRow($r)) {
            foreach ($cols as $col) {
                $scannerConfig[$col] = $row[$col];
            }
        }
        */
        $beep = $scannerConfig['scanBeep'];
        if ($beep == true) {
            $this->addOnloadCommand("
                WebBarcode.Linea.emitTones(
                    [
                        { 'tone':100, 'duration':100 },
                    ] 
                );
            ");
        }

        /*
        $columns = array('upc', 'sku', 'brand', 'sign-brand', 'description', 'sign-description', 'size', 'units', 'netcost', 'cost', 'recentPurchase', 
            'price', 'sale', 'margin_target_diff', 'rsrp', 'srp', 'prid', 'dept', 'vendor', 'last_sold', 'scaleItem', 'notes', 'reviewed', 
            'costChange');
        */

        $args = array($upc, $storeID);
        $prep = $dbc->prepare("SELECT *, 
            (100 * (normal_price - cost) / normal_price) AS curMargin
            FROM products AS p
                LEFT JOIN departments AS d ON p.department=d.dept_no
            WHERE p.upc = ? AND p.store_id = ?");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $brand = strtoupper($row['brand']);
            $description = strtoupper($row['description']);
            $cost = $row['cost'];
            $normal_price = $row['normal_price'];
            $vendorID = $row['default_vendor_id'];
            $size = $row['size'];
            $curMargin = round($row['curMargin'], 2);
            $autoPar = round($row['auto_par'], 1);
            $par = round($autoPar * $autoParMulti, 1);
            $department = $row['department'];
            $deptName = $row['dept_name'];
            $inUse = $row['inUse'];
        }

        $args = array($upc);
        $prep = $dbc->prepare("SELECT brand, description, narrow FROM productUser WHERE upc = ?");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $signBrand = $row['brand'];
            $signDescription = $row['description'];
            $narrow = $row['narrow'];
        }

        $args = array($vendorID);
        $prep = $dbc->prepare("SELECT * FROM vendors WHERE vendorID = ?");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $vendorName = $row['vendorName'];
            $shipping = $row['shippingMarkup'];
            $discount = $row['discountRate'];
        }

        $args = array($upc, $vendorID);
        $prep = $dbc->prepare("SELECT * FROM vendorItems WHERE upc = ? AND vendorID = ?");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $sku = $row['sku'];
            $units = $row['units'];
            if (!$size > 0) $size = $row['size'];
        }
        $adjCost = ($shipping > 0) ? $cost + ($cost * $shipping) : $cost;
        $adjCost = round($adjCost, 3);

        list($recentPurchase, $received) = $this->getRecentPurchase($dbc,$sku);

        $args = array($upc);
        $prep = $dbc->prepare("SELECT * FROM batchList AS b LEFT JOIN batches AS ba ON b.batchID=ba.batchID WHERE upc = ? AND NOW() >= startDate AND NOW() <= endDate");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $salePrice = $row['salePrice'];
        }

        $args = array($upc);
        $prep = $dbc->prepare("SELECT t.description AS priceRuleType
                FROM products AS p
                    LEFT JOIN PriceRules AS r ON p.price_rule_id=r.PriceRuleID
                    LEFT JOIN PriceRuleTypes AS t ON r.priceRuleTypeID=t.priceRuleTypeID
                WHERE p.upc = ?
        ");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $priceRuleType = $row['priceRuleType'];
        }

        $args = array($upc, $storeID);
        $prep = $dbc->prepare("SELECT fslv.sections AS location
            FROM products AS p
                LEFT JOIN FloorSectionsListView AS fslv ON p.upc=fslv.upc AND p.store_id=fslv.storeID
            WHERE p.upc = ?
                AND p.store_id = ?
        ");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $location = $row['location'];
        }

        $args = array($username, $storeID, $upc);
        $prep = $dbc->prepare("SELECT notes FROM woodshed_no_replicate.AuditScan WHERE username = ? AND storeID = ? AND upc = ?");
        $res = $dbc->execute($prep,$args);
        $row = $dbc->fetchRow($res);
        $curNote = $row['notes'];

        $prep = $dbc->prepare("SELECT floorSectionID, name FROM FloorSections 
            WHERE storeID = ? ORDER BY name;");
        $res = $dbc->execute($prep, array($storeID));
        $sections_list = array();
        while ($row = $dbc->fetchRow($res)) {
            $fsID = $row['floorSectionID'];
            $name = $row['name'];
            $sections_list[$fsID] = $name;
        }

        $prep = $dbc->prepare("SELECT m.*, f.name, f.floorSectionID
            FROM FloorSectionProductMap AS m
                RIGHT JOIN FloorSections AS f ON m.floorSectionID=f.floorSectionID
                WHERE upc = ?
                    AND f.storeID = ?;");
        $res = $dbc->execute($prep, array($upc, $storeID));
        $sections = "<div style=\"color: black; font-weight: bold; text-shadow: 1px 1px lightgrey\">In Section(s)</div>";
        while ($row = $dbc->fetchRow($res)) {
            $fsID = $row['floorSectionID'];
            $mapID = $row['floorSectionProductMapID'];
            $name = $row['name'];
            $sections .= "<div id=\"sm_$mapID\" class=\"sections\">";
            $sections .= "<span data-mapID=\"$mapID\" class=\"scanicon scanicon-trash btn btn-default\">&nbsp;</span>";
            $sections .= $this->getFloorSectionSelect($sections_list, $fsID, $mapID, $upc);

            $sections .= "</div>";
        }
        $sections .= "<div style=\"color: black; font-weight: bold; text-shadow: 1px 1px lightgrey\">Add Section</div>";
        $sections .= "<div id=\"add-floor-section\">";
        $sections .= $this->getFloorSectionSelect($sections_list, $fsID, 'create_new_mapID', $upc);
        $sections .= "</div>";
        $inUse = ($inUse == 1) ? '1 (is in use)' : '0 (not in use)';
        $inUseAlert = ($inUse == 1) ? '' : 'danger';
        if ($narrow == 1) {
            $narrow = '1: (is narrow)';
            $narrowAlert = 'warning';
        } else {
            $narrow = '0: (is not narrow)';
            $narrowAlert = '';
        }

        $salePriceVis = (isset($salePrice)) ? '' : 'hidden';
        $recentPurchaseVis = (isset($recentPurchase)) ? '' : 'hidden';

        $sections .= $this->getFloorSectionSelect($sections_list, $fsID, 'create_new_mapID', $upc);

        $this->addScript('productScanner.js?unique='.$timestamp);

        // Enter Notes Chunk
        $args = array($username, $storeID);
        $prep = $dbc->prepare("SELECT notes FROM woodshed_no_replicate.AuditScan WHERE username = ? AND storeID = ?");
        $res = $dbc->execute($prep,$args);
        $notes = array();
        while ($row = $dbc->fetchRow($res)) {
            if (!in_array($row['notes'],$notes)) {
                $notes[] = $row['notes'];
            }
        }

        //  Commonly used NOTES.
        $hiddenB = '
            <div id="notepad" class="collapse" align="center">
                <div class="container-fluid">
                    <form method="post" name="notepad" class="" action="Scanner.php">
                        <input type="text" name="note" id="note" class="form-control" "><br />
                        <input type="hidden" name="upc" value="'.$upc.'">
                        <span class="btn btn-primary" id="submit-note">Submit Note</span>
                    </form>
        ';

        asort($notes);
        foreach ($notes as $note) {
            if ($note != NULL) {
                $hiddenB.= '<span class="qmBtn"  onClick="qm(\''.$note.'\'); return false; ">
                    <b>'.$note.'</b></span>';
            }
        }

        $hiddenB .= '
                </div>
            </div>';

         $ret = <<<HTML
$hiddenColumnSelector
$hiddenB
<div id="floor-section-edit" >
    <div id="floor-section-edit-close" onclick="$('#floor-section-edit').hide();"><</div>
    $select
    $sections
</div>

<div id="hidden-test">
    Well, hello!
    <div class="close" onclick="$('#hidden-test').hide();">CLOSE</></div>
</div>

<div align="center">
    <form method="post" class="" id="my-form" name="main_form">
        <div class="row">
            <div class="col-8" align="right">
                <span>$count</span>
                <span style="text-shadow: 1px 1px white">$username</span>
                <input class="input-sm info" name="upc" id="upc" value="$upc" autofocus
                    style="text-align: center;" pattern="\d*">
            </div>
            <div class="col-2" align="left">
                <div>&nbsp;</div>
                <button class="input-sm info btn btn-info btn-sm" type="submit"     
                    style="text-align: center;" pattern="\d*">Submit</button>
            </div>
        </div>
        <input type="hidden" id="sku" name="sku" />
        <input type="hidden" name="success" value="empty"/>
        <span id="auto_par" class="sm-label"></span><span id="par_val" class="norm-text"></span>
        <!-- <button type="submit" class="btn btn-xs"><span class="go-icon"></span></button> -->
    </form>
</div>
<input type="hidden" name="keydown" id="keydown"/>
<form id="page-info" style="display: none">
    <input type="hidden" id="storeID" value="$storeID" />
    <input type="hidden" id="username" value="$username" />
</form>
<div style="text-align: right;"><span class="info-label">sku:</span>$sku</div>
<div class="row">
    <div class="col-12">
        <div class="info-row inline-row" data-name="par" data-table="none" data-column="none">$par 
            <span class="small">avg sold in $autoParMulti days</span>
            ($autoPar/<span class="small">day</span>)
        </div>
    </div>
</div>
<div class="info-row $inUseAlert" data-name="inUse" data-table="products" data-column="inUse">$inUse</div>
<div class="info-row" data-name="posBrand" data-table="products" data-column="brand">$brand</div>
<div class="info-row" data-name="posDesc" data-table="products" data-column="description">$description</div>
<div class="info-row" data-name="signBrand" data-table="productUser" data-column="brand">$signBrand</div>
<div class="info-row" data-name="signDesc" data-table="productUser" data-column="description">$signDescription</div>
<div class="info-row $narrowAlert" data-name="narrow" data-table="productUser" data-column="narrow">$narrow</div>
<div class="row">
    <div class="col-4">
        <div class="info-row inline-row" data-name="cost" data-table="products" data-column="cost">$cost</div>
    </div>
    <div class="col-4">
        <div class="info-row inline-row" data-name="adj" data-table="none" data-column="none">$adjCost</div>
    </div>
    <div class="col-4">
        <div class="info-row $recentPurchaseVis" data-name="PO" data-table="none" data-column="PO"
            onclick="alert('Received on: '+'$received');">$recentPurchase</div>
    </div>
</div>
<div class="row">
    <div class="col-4">
        <div class="info-row" data-name="price" data-table="productUser" data-column="price">$normal_price</div>
    </div>
    <div class="col-4">
        <div class="info-row success $salePriceVis" data-name="salePrice" data-table="productUser" data-column="salePrice">$salePrice</div>
    </div>
</div>
<div class="info-row" data-name="vendor" data-table="vendors" data-column="vendor">$vendorID - $vendorName</div>
<div class="info-row" data-name="dept" data-table="products" data-column="department">$department - $deptName</div>
<div class="info-row" data-name="size" data-table="productUser" data-column="size">$size</div>
<div class="info-row" data-name="units" data-table="productUser" data-column="units">$units</div>
<div class="info-row" data-name="curMargin" data-table="none" data-column="none">$curMargin%</div>
<div class="info-row" data-name="prid" data-table="none" data-column="none">$priceRuleType</div>
<div class="info-row" data-name="location" data-table="none" data-column="none" onclick="$('#floor-section-edit').show();">$location</div>
<div class="info-row" data-name="note" data-table="none" data-column="none">$curNote</div>
<!--
last_sold
reviewed          

costChange        
scaleItem         
location module

-->
                    <div class="row">
                        <!-- <div class="col-4  clear btn btn-warning" onClick="queue('.$storeID.'); return false;">Print</div> -->
                        <div class="col-4 clear">
                            <form method="get" type="hidden">
                            <a href="../ScannerSettings.php" class="btn btn-info" style="width: 100%;">
                                <span class="scanicon scanicon-settings-white"></span> 
                            </a>
                            <input type="hidden" name="note" value="Print Tag" />
                            <input type="hidden" id="upc" name="upc" value="'.$upc.'" />
                        </div>
                        </form>
                        <div class="col-4  clear">
                            <button class="btn btn-pencil" style="" onclick="$('#notepad').show();" style="width: 100%;">
                                <span class="scanicon scanicon-pencil-white"></span>
                            </button></div>
                        <div class="col-4  clear "><a class="btn btn-success" style="width: 100%; height: 44px; font-weight: bold;" href="http://'.$MY_ROOTDIR.'/content/Scanning/BatchCheck/SCS.php">B.C.</a></div>
                    </div>
                    <div class="row">
                        <div class="col-4">
                            <a class="btn btn-primary" style="width: 100%; height: 44px; font-weight: bold;" href="AuditReport.php ">Report</a>
                        </div>
                        <div class="col-4">
                            <a class="btn btn-default" style="background: plum; color: white; height: 44px; width: 100%; font-weight: bold;" href="http://$FANNIE_ROOTDIR/modules/plugins2.0/ShelfAudit/SaMenuPage.php">Fannie</a>
                        </div>
                        <div class="col-4">
                            <a class="btn btn-default" style="background: orange; color: white; height: 44px; width: 100%; font-weight: bold;" href="#"
                                onclick="$('#hiddenColumnSelector').show();">Cols</a>
                        </div>
                    </div>
HTML;

        foreach ($_SESSION['view'] as $col => $active) {
            if ($active == 0) {
                $cmd = <<<JAVASCRIPT
$('.info-row[data-name="$col"]').hide();
JAVASCRIPT;
                $this->addOnloadCommand($cmd);
            }
        }

        return $ret;
    }

    private function getFloorSectionSelect($sections, $cur_section, $mapID, $upc)
    {
        $select = "<select data-mapID=\"$mapID\" data-upc=\"$upc\" class=\"update-section\">";
        $cur = '';
        foreach ($sections as $fsID => $name) {
            $cur = ($cur_section == $fsID) ? 'selected' : '';
            $select .= "<option value=\"$fsID\" $cur>$name</option>"; 
        }
        $select .= "</select>";

        return $select;
    }

    public function formContent()
    {
    }

    public function javascriptContent()
    {
        //$dbc = ScanLib::getConObj();

        return <<<JAVASCRIPT
$('#submit-note').click(function(){
    var note = $('#note').val(); 
    var upc = $('#upc').val();
    var username = $('#username').val();
    $.ajax({
        type: 'post',
        url: window.location.href,
        data: 'note='+note+'&upc='+upc+'&username='+username,
        success: function(response) {
            window.location.reload();
        }
    });
});
$('.info-row').each(function(){
    var name = $(this).attr('data-name');    
    $(this).prepend('<span class="info-label">'+name+':</span>');
});
$('.col-select').click(function(){
    var col = $(this).attr('data-column');
    var active = null;
    if ($(this).hasClass('active')) {
        $(this).removeClass('active');
        active = 0;
    } else {
        $(this).addClass('active');
        active = 1;
    }
    $.ajax({
        type: 'post',
        url: window.location.href,
        data: 'setcolumn=true&col='+col+'&active='+active,
        success: function(response) {
            console.log('success');
        }
    });
});
JAVASCRIPT;
    }

    public function cssContent()
    {
        return <<<HTML
html, body {
    background: linear-gradient(45deg, lightgrey, white, lightgrey);
    //height: 100%;
    overflow: auto;
    overflow-x: hidden;
    //font-size: 14px;
}
body {
    padding-top: 15px;
}
input {
    background: rgba(0, 0, 0, 0.05);
    border: none;
}
.info-row {
    padding: 4px;
    margin-bottom: 1px;
    background: rgba(0, 0, 0, 0.05);
}
.select-row {
    padding: 2px;
    margin-bottom: 1px;
    background: rgba(0, 0, 0, 0.05);
}
.inline-row {
    display: inline-block;
    width: 100%;
}
.info-label {
    font-size: 10px;
    font-weight: bold;
    width: 60px;
    display: inline-block;
    text-align: right;
    margin-right: 5px;
}
.success {
    background: lightgreen;
    background: linear-gradient(45deg, lightgreen, lawngreen);
}
.hidden {
    display: none;
}
.small {
    font-size: 10px;
}
#add-floor-section {
    //background: rgba(200, 247, 197, 0.8);
    color: black;
    margin: 10px;
    padding: 10px;
}
#floor-section-edit {
    position: fixed; 
    top:0px;
    left:0px;
    background: rgba(255,255,255,0.9);
    padding-top: 100px;
    padding: 25px;
    width: 100%;
    height: 100%;
    display: none;
    z-index: 1000;
}
#floor-section-edit-close {
    position: fixed;
    top:25px;
    right:25px;
    color: black;
    text-shadow: 1px 1px lightgrey;
    border-radius: 50%;
    border: 1px solid black;
    height: 50px;
    width: 50px;
    padding: 12.5px;
    padding-left: 19px;
    font-weight: bold;
}
#hiddenColumnSelectorClose {
    position: fixed;
    top:25px;
    right:25px;
    color: black;
    text-shadow: 1px 1px lightgrey;
    border-radius: 50%;
    border: 1px solid black;
    height: 50px;
    width: 50px;
    padding: 12.5px;
    padding-left: 19px;
    font-weight: bold;
    z-index: 1001;
    cursor: pointer;
}
#hidden-test {
    position: fixed;
    top: 0px;
    left: 0px;
    padding-top: 25px;
    background: red;
    height: 100%;
    width: 100%;
    z-index: 1000;
    display: none;
}
#hiddenColumnSelector {
    position: fixed;
    top: 0px;
    left: 0px;
    padding-top: 0px;
    background: white;
    height: 100%;
    width: 100%;
    z-index: 1000;
    display: none;
    cursor: pointer;
    overflow-y: auto;
}
#notepad {
    padding-top: 25px;
    position: fixed;
    top: 0px;
    left: 0px;
    background: red;
    background-color: rgba(255, 255, 255, 0.9);
    height: 100%;
    width: 100%;
    z-index: 1001;
    display: none;
    cursor: pointer;
    opacity: 1;
}
.col-select {
    cursor: pointer;   
}
.active {
    background: rgba(25, 100, 255, 0.3);
}
.qmBtn {
   background-clip: padding-box;
   padding: 5px;
   margin: 5px;
   padding-top: 10px;
   border-radius: 5px;
   background-color: white;
   background: linear-gradient(45deg, cyan, blue);
   color: white;
   border: 3px solid transparent;
   //height: auto;
   //min-height: 50px;
   width: 75px;
   height: 75px;
   float: left;
   font-size: 12px;
}
.btn-pencil {
    background: linear-gradient(45deg, crimson, tomato); width: 100%;
}
.btn-info {
    //background: linear-gradient(45deg,  #48c9b0,  #76d7c4); 
    //background: cyan;
}
.btn-success{
    //background: linear-gradient(45deg,     #138d75 ,   #45b39d ); 
    //background: blue;
}
.danger {
    background: linear-gradient(45deg, crimson, tomato);
}
.warning {
    background: linear-gradient(45deg, gold, orange);
}
HTML;
    }

    public function helpContent()
    {
        return <<<HTML
HTML;
    }

}
WebDispatch::conditionalExec();
