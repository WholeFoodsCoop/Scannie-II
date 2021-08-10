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
class AuditReport extends PageLayoutA
{

    public function preprocess()
    {
        $this->displayFunction = $this->postView();
        $this->__routes[] = 'post<test>';
        $this->__routes[] = 'post<notes>';
        $this->__routes[] = 'post<fetch>';
        $this->__routes[] = 'post<clear>';
        $this->__routes[] = 'post<upcs>';
        $this->__routes[] = 'post<deleteRow>';
        $this->__routes[] = 'post<rowCount>';
        $this->__routes[] = 'post<setSku>';
        $this->__routes[] = 'post<setBrand>';
        $this->__routes[] = 'post<setDescription>';
        $this->__routes[] = 'post<setDept>';
        $this->__routes[] = 'post<setCost>';
        $this->__routes[] = 'post<setNotes>';
        $this->__routes[] = 'post<checked>';
        $this->__routes[] = 'post<review>';
        $this->__routes[] = 'post<columnSet>';
        $this->__routes[] = 'post<saveAs>';
        $this->__routes[] = 'post<loadList>';
        $this->__routes[] = 'post<deleteList>';

        return parent::preprocess();
    }

    private function getUpcList($username, $storeID)
    {
        $upcs = array();
        $dbc = ScanLib::getConObj();

        $args = array($username, $storeID);
        $prep = $dbc->prepare("SELECT upc FROM woodshed_no_replicate.AuditScan
            WHERE username = ? AND storeID = ? AND savedAs = 'default'");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $upcs[$row['upc']] = $row['upc'];
            //echo $row['upc'];
        }
        //var_dump($dbc);
        echo $dbc->error();

        return $upcs;
    }

    public function postDeleteListHandler()
    {

        $dbc = ScanLib::getConObj('SCANALTDB');
        $delete = FormLib::get('deleteList');
        $username = FormLib::get('username');
        $storeID = FormLib::get('storeID');

        $args = array($username, $storeID, $delete);
        $prep = $dbc->prepare("DELETE FROM AuditScan WHERE username = ?
            AND storeID = ? AND savedAs = ? OR savedAs = 'default'");
        $res = $dbc->execute($prep, $args);

        return header("location: AuditReport.php");
    }

    public function postLoadListHandler()
    {

        $dbc = ScanLib::getConObj('SCANALTDB');
        $load = FormLib::get('loadList');
        $username = FormLib::get('username');
        $storeID = FormLib::get('storeID');

        $args = array($username, $storeID);
        $prep = $dbc->prepare("DELETE FROM AuditScan WHERE username = ?
            AND storeID = ? AND savedAs = 'default'");
        $res = $dbc->execute($prep, $args);

        $args = array($username, $storeID, $load);
        $prep = $dbc->prepare("INSERT INTO AuditScan (date, upc, username, storeID, savedAs)
            SELECT NOW(), upc, username, storeID, 'default' FROM AuditScan WHERE username = ?
            AND storeID = ? AND savedAs = ?");
        $res = $dbc->execute($prep, $args);

        return header("location: AuditReport.php?loaded=$load");
    }

    public function postSaveAsHandler()
    {

        $dbc = ScanLib::getConObj('SCANALTDB');
        $saveAs = FormLib::get('saveAs');
        $username = FormLib::get('username');
        $storeID = FormLib::get('storeID');
        $list = FormLib::get('list');
        $upcs = explode("\r\n", $list);
        $f = fopen('test.txt', 'w');
        foreach($upcs as $upc) {
            $args = array($upc, $username, $storeID, $saveAs);
            $prep = $dbc->prepare("INSERT INTO AuditScan (date, upc, username, storeID, savedAs)
                VALUES (NOW(), ?, ?, ?, ?)");
            $res = $dbc->execute($prep, $args);
            //file_put_contents('test.txt', $upc);
            fwrite($f, $upc, 1000);
        }
        $er = $dbc->error();

        return header('location: AuditReport.php');
    }

    public function postColumnSetHandler()
    {
        $bitSet = $_SESSION['columnBitSet']; // is the INT value of columnBitSet 
        $column = FormLib::get('columnSet'); // the column to be changed
        $numCols = FormLib::get('numCols'); // the number of columns/checkboxes that exist
        $column = $numCols - $column - 1;
        $set = FormLib::get('set');

        if ($set == "true") {
            $_SESSION['columnBitSet'] = $bitSet | (1 << $column);
        } else {
            $_SESSION['columnBitSet'] = $bitSet & ~(1 << $column);
        }

        $json = array();
        $json['test'] = 'true';
        $json['val'] = $bitSet;

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
            $prep = $dbc->prepare("INSERT INTO woodshed_no_replicate.temp (upc,cost) SELECT upc, cost FROM products WHERE UPC in (SELECT upc FROM woodshed_no_replicate.AuditScan WHERE username = ? AND savedAs = 'default') GROUP BY upc;");
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

    private function getProdFlagsListView($dbc, $upcs)
    {
        $str = "";
        $data = "";

        list($inStr, $args) = $dbc->safeInClause($upcs);
        $query = "SELECT upc, flags, storeID FROM prodFlagsListView WHERE upc IN ($inStr)";
        $prep = $dbc->prepare($query);
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            //$str .= "<div>" . $row['storeID'] . ": " . $row['flags'] . "</div>";
            $upc = $row['upc']; $flags = $row['flags']; $storeID = $row['storeID'];
            $data[$upc][$storeID] = $flags;
        }
        echo $dbc->error();

        return $data;
    }

    private function getScaleData($dbc, $upc)
    {
        $bycount = null;
        $args = array($upc);
//                WHEN bycount = 0 THEN 'Random'
//                WHEN bycount = 1 THEN 'Fixed'
        $prep = $dbc->prepare("SELECT
            CASE
                WHEN weight = 0 THEN 'Random'
                WHEN weight = 1 THEN 'Fixed'
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
        $prep = $dbc->prepare('DELETE FROM woodshed_no_replicate.AuditScan WHERE upc = ? AND storeID = ? AND username = ? AND savedAs = "default"');
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
        $query = $dbc->prepare("DELETE FROM woodshed_no_replicate.AuditScan WHERE storeID = ? AND username = ? AND savedAs = 'default'");
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
                $prep = $dbc->prepare("INSERT IGNORE INTO woodshed_no_replicate.AuditScan (upc, username, storeID, date, savedAs)
                    VALUES (?, ?, ?, NOW(), 'default');");
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

        $upcs = array();
        //$upcs = $this->getUpcList($username, $storeID);
        // flags[upc][storeID] = flags string.
        //$flagData = $this->getProdFlagsListView($dbc, $upcs);

        $args = array($username, $storeID);
        $prep = $dbc->prepare("
            SELECT
                pf.flags,
                p.store_id,
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
                c.date AS costChangeDate,
                subdepts.subdept_name AS subdept,
                CASE 
                    WHEN p.local = 0 THEN ''
                    WHEN p.local = 1 THEN 'SC'
                    WHEN p.local = 2 THEN 'MN/WI'
                END AS local
            FROM products AS p
                LEFT JOIN vendorItems AS v ON p.default_vendor_id=v.vendorID AND p.upc=v.upc
                LEFT JOIN productUser AS u ON p.upc=u.upc
                LEFT JOIN PriceRules AS r ON p.price_rule_id=r.PriceRuleID
                LEFT JOIN PriceRuleTypes AS t ON r.priceRuleTypeID=t.priceRuleTypeID
                LEFT JOIN departments AS d ON p.department=d.dept_no
                LEFT JOIN vendors AS e ON p.default_vendor_id=e.vendorID
                RIGHT JOIN woodshed_no_replicate.AuditScan AS a ON p.upc=a.upc AND p.store_id=a.storeID
                LEFT JOIN deptMargin AS dm ON p.department=dm.dept_ID
                LEFT JOIN vendorDepartments AS vd
                    ON vd.vendorID = p.default_vendor_id AND vd.posDeptID = p.department
                LEFT JOIN prodReview AS pr ON p.upc=pr.upc
                LEFT JOIN productCostChanges AS c ON p.upc=c.upc
                LEFT JOIN subdepts ON subdepts.subdept_no=p.subdept AND subdepts.dept_ID=p.department
                LEFT JOIN prodFlagsListView AS pf ON pf.upc=p.upc AND pf.storeID=p.store_id
            WHERE p.upc != '0000000000000'
                AND a.username = ?
                AND p.store_id = ?
                AND a.savedAS = 'default'
            GROUP BY a.upc
            ORDER BY a.date DESC
        ");

        $td = "";
        $textarea = "<div style=\"position: relative\">
            <span class=\"status-popup\">Copied!</span>
            <textarea class=\"copy-text\" id=\"list\" name=\"list\" rows=3 cols=10>";

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
            <td title=\"subdebt\" data-column=\"subdepts\"class=\"subdept column-filter\"></td>
            <td title=\"local\" data-column=\"local\"class=\"local column-filter\"></td>
            <td title=\"flags\" data-column=\"flags\"class=\"flags column-filter\"></td>
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
            <th class=\"margin_target_diff\">margin, target, diff</th>
            <th class=\"srp\">srp</th>
            <th class=\"rsrp\">round srp</th>
            <th class=\"prid\">prid</th>
            <th class=\"dept\">dept</th>
            <th class=\"subdept\">subdept</th>
            <th class=\"local\">local</th>
            <th class=\"flags\">flags</th>
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


        while ($row = $dbc->fetch_row($result)) {
            $upc = $row['upc'];
            //$upcs[$upc] = $upc;
            $data = $this->getMovement($dbc, $upc);
            $bycount = null;
            $bycount = $this->getScaleData($dbc, $upc);
            $lastSold = '';
            foreach ($data as $storeID => $bRow) {
                $inUse = ($bRow['inUse'] != 1) ? 'alert-danger' : 'alert-success';
                $ls = ($bRow['last_sold'] == null) ? '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' : $bRow['last_sold'];
                $lastSold .= '('.$storeID.') <span class="'.$inUse.'">'.$ls.'</span> ';
            }
            $uLink = '<a class="upc" href="../../../../git/fannie/item/ItemEditorPage.php?searchupc='.$upc.
                '&ntype=UPC&searchBtn=" target="_blank">'.$upc.'</a>';
            $sku = $row['sku'];
            list($recentPurchase, $received) = $this->getRecentPurchase($dbc,$upc);
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
            $subdept = $row['subdept'];
            $local = $row['local'];
            $storeID = $row['store_id'];
            //$flags = $flagData[$upc][$storeID];
            $flags = $row['flags'];
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
            $td .= "<td class=\"upc\" data-upc=\"$upc\">$uLink</td>";
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
            //$td .= "<td class=\"\" title=\"\">$received</td>";
            $td .= "<td class=\"price\">$price</td>";
            $td .= "<td class=\"sale\">$sale</td>";
            $diff = round($curMargin - $margin, 1);
            $curMargin = round($curMargin, 1);
            $td .= "<td class=\"margin_target_diff\">
                <span class=\"margin-container\">$curMargin</span>
                <span class=\"margin-container\">$margin</span>
                <span class=\"margin-container\">$diff</span>
            </td>";
            $td .= "<td class=\"rsrp\">$rsrp</td>";
            $td .= "<td class=\"srp\">$srp</td>";
            $td .= "<td class=\"prid\">$prid</td>";
            $td .= "<td class=\"dept\">
                <span class=\"dept-text\">$dept</span>
                <span class=\"dept-select hidden\">$deptOpts</span>
                </td>";
            $td .= "<td class=\"subdept\">$subdept</td>";
            $td .= "<td class=\"local\">$local</td>";
            $td .= "<td class=\"flags\">$flags</td>";
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

        $args = array($username, $storeID);
        $prep = $dbc->prepare("SELECT upc FROM woodshed_no_replicate.AuditScan
            WHERE username = ? AND storeID = ? AND savedAs = 'default'");
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
<input type="hidden" id="table-rows" val(ue)="$rows" />
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

    private function getRecentPurchase($dbc,$upc)
    {
        $args = array($upc);
        $prep = $dbc->prepare("SELECT
            sku, internalUPC, brand, description, DATE(receivedDate) AS receivedDate,
            caseSize, receivedTotalCost AS cost,
            unitCost, ROUND(receivedTotalCost/caseSize,3) AS mpcost
            FROM PurchaseOrderItems WHERE internalUPC = ?
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
        $query = $dbc->prepare("SELECT notes FROM woodshed_no_replicate.AuditScan WHERE storeID = ? AND username = ? 
            and savedAs = 'default' GROUP BY notes;");
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

    public function postView()
    {
        $dbc = scanLib::getConObj();
        $username = ($un = scanLib::getUser()) ? $un : "Generic User";
        $storeID = scanLib::getStoreID();
        $loaded = FormLib::get('loaded');
        $test = new DataModel($dbc);

        if (!isset($_SESSION['columnBitSet'])) {
            $_SESSION['columnBitSet'] = 1708975;
        }

        $args = array($username, $storeID);
        $prep = $dbc->prepare("SELECT upc FROM woodshed_no_replicate.AuditScan
            WHERE username = ? AND storeID = ? AND savedAs = 'default'");
        $res = $dbc->execute($prep, $args);
        $list = '<textarea name="list" style="display: none;">';
        while ($row = $dbc->fetchRow($res)) {
            $list .= $row['upc'] . "\r\n";
        }
        $list .= '</textarea>';

        $args = array($username, $storeID);
        $prep = $dbc->prepare("SELECT savedAs FROM woodshed_no_replicate.AuditScan
            WHERE username = ? AND storeID = ? AND savedAs != 'default' GROUP BY savedAs");
        $res = $dbc->execute($prep, $args);
        $savedLists = "";
        $datalist = "<datalist id=\"savedLists\">";
        while ($row = $dbc->fetchRow($res)) {
            $saved = $row['savedAs'];
            $sel = ($saved == $loaded) ? ' selected ' : '';
            $savedLists .= "<option value=\"$saved\" $sel>$saved</option>";
            $datalist .= "<option value=\"$saved\">";
        }
        $datalist .= "</datalist>";

        $prep = $dbc->prepare("SELECT * FROM woodshed_no_replicate.temp");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            //echo "<div>{$row['upc']}</div>";
        }
        $countTemp = $dbc->numRows($res);
        $tempBtn = "";
        $tempBtnID = "prevent-default";
        if ($_COOKIE['user_type'] == 2) {
            $tempClass = "btn-secondary";
            if ($countTemp > 0) {
                $tempBtn = 'Close Review';
                $tempInputVal = 'close';
                $tempClass = 'btn-danger';
            } else {
                $tempBtn = 'Open Review';
                $tempInputVal = 'open';
            }
            $tempBtnID = "temp-btn";
        }

        $options = $this->getNotesOpts($dbc,$storeID,$username);
        $noteStr = "";
        $noteStr .= "<select id=\"notes\" style=\"font-size: 10px; font-weight: normal; margin-left: 5px; border: 1px solid lightgrey\">";
        $noteStr .= "<option value=\"viewall\">View All</option>";
        foreach ($options as $k => $option) {
            $noteStr .= "<option value=\"".$k."\">".$option."</option>";
        }
        $noteStr .= "</select>";
        $nFilter = "<div style=\"font-size: 12px; padding: 10px;\"><b>Note Filter</b>:$noteStr</div>";

        $columns = array('check', 'upc', 'sku', 'brand', 'sign-brand', 'description', 'sign-description', 'size', 'units', 'netcost', 'cost', 'recentPurchase',
            'price', 'sale', 'margin_target_diff', 'rsrp', 'srp', 'prid', 'dept', 'subdept', 'local', 'flags', 'vendor', 'last_sold', 'scaleItem', 'notes', 'reviewed',
            'costChange');
        $columnCheckboxes = "<div style=\"font-size: 12px; padding: 10px;\"><b>Show/Hide Columns: </b>";
        $i = count($columns) - 1;
        foreach ($columns as $column) {
            $columnCheckboxes .= "<span class=\"column-checkbox\"><label for=\"check-$column\">$column</label> <input type=\"checkbox\" name=\"column-checkboxes\" id=\"check-$column\" data-colnum=\"$i\" value=\"$column\" class=\"column-checkbox\" checked></span>";
            $i--;
        }
        $columnCheckboxes .= "</div>";

        $modal = "
            <div id=\"upcs_modal\" class=\"modal\">
                <div class=\"modal-dialog\" role=\"document\">
                    <div class=\"modal-content\">
                      <div class=\"modal-header\">
                        <h3 class=\"modal-title\" style=\"color: #8c7b70\">Enter a list of Barcodes</h3>
                        <button type=\"button\" class=\"close\" data-dismiss=\"modal\" aria-label=\"Close\"
                                style=\"position: absolute; top:20; right: 20\">
                              <span aria-hidden=\"true\">&times;</span>
                            </button>
                          </div>
                          <div class=\"modal-body\">
                            <div align=\"center\">
                                <form method=\"post\" class=\"\">
                                    <div class=\"form-group\">
                                        <textarea class=\"form-control\" name=\"upcs\" rows=\"10\"></textarea>
                                    </div>
                                    <div class=\"form-group\">
                                        <button type=\"submit\" class=\"btn btn-default btn-xs\">Submit</button>
                                    </div>
                                    <input type=\"hidden\" name=\"storeID\" value=\"$storeID\" />
                                    <input type=\"hidden\" name=\"username\" value=\"$username\" />
                                </form>
                            </div>
                          </div>
                        </div>
                    </div>
                </div>
        ";

        $deleteList = '';
        if (strlen($loaded) > 0) {
            // show the delete button IF a list was recently selected
            $deleteList = "
                <div class=\"form-group dummy-form\">
                    <span class=\"btn btn-danger btn-sm\"
                        onclick=\"var c = confirm('Delete list?'); if (c == true) { document.forms['deleteListForm'].submit(); }\">Delete</span>
                </div> |
            ";
        }

        $this->addScript('../../../common/javascript/tablesorter/js/jquery.tablesorter.min.js');
        $this->addScript('../../../common/javascript/tablesorter/js/jquery.metadata.js');
        $this->addOnloadCommand("$('#mytable').tablesorter();");

        return <<<HTML
<div class="container-fluid">
$modal
<input type="hidden" name="keydown" id="keydown"/>
<form id="page-info" style="display: none">
    <input type="hidden" id="storeID" value="$storeID" />
    <input type="hidden" id="username" value="$username" />
</form>

<div class="form-group dummy-form">
    <button id="clearNotesInputB" class="btn btn-secondary btn-sm page-control">Clear Notes</button>
</div>
<div class="form-group dummy-form">
    <button id="clearAllInputB" class="btn btn-secondary btn-sm page-control">Clear Queue</button>
</div>
<div class="form-group dummy-form">
    <button class="btn btn-secondary btn-sm page-control" data-toggle="modal" data-target="#upcs_modal">Add Items</button>
</div>
<div class="form-group dummy-form">
    <form method="post" action="AuditReport.php">
        <button class="btn $tempClass btn-sm page-control" id="$tempBtnID">$tempBtn</button>
        <input type="hidden" name="review" value="$tempInputVal"/>
        <input type="hidden" name="username" value="$username"/>
    </form>
</div>
<div class="form-group dummy-form">
    <a class="btn btn-info btn-sm page-control" href="ProductScanner.php ">Scanner</a>
</div>
<div></div>
<form name="load" id="loadList" method="post" action="AuditReport.php" style="display: inline-block">
    <input name="username" type="hidden" value="$username" />
    <input name="storeID" type="hidden" value="$storeID" />
    <div class="form-group dummy-form">
        <select name="loadList" class="form-control form-control-sm">
            <option val=0>Saved Lists</option>
            $savedLists
        </select>
    </div>
    <div class="form-group dummy-form">
        <button class="btn btn-default btn-sm" type="submit">Load</button>
    </div> |
    $deleteList
    $datalist
</form>
<form name="deleteListForm" method="post" action="AuditReport.php" style="display: inline-block">
    <input name="username" type="hidden" value="$username" />
    <input name="storeID" type="hidden" value="$storeID" />
    <input name="deleteList" type="hidden" value="$loaded" />
</form>
<form name="save" id="saveList" method="post" action="AuditReport.php" style="display: inline-block">
    <input name="username" type="hidden" value="$username" />
    <input name="storeID" type="hidden" value="$storeID" />
    $list
    <div class="form-group dummy-form">
        <input name="saveAs" class="form-control form-control-sm" list="savedLists" placeholder="Save List As" autocomplete="off"/>
    </div>
    <div class="form-group dummy-form">
        <button class="btn btn-default btn-sm" type="submit">Save</button>
    </div>
</form>
$nFilter
$columnCheckboxes

<div class="row">
    <div class="col-lg-6">
        <div style="font-size: 12px; padding: 10px;">
            <label for="check-pos-descript"><b>Switch POS/SIGN Descriptors</b>:&nbsp;</label><input type="checkbox" name="check-pos-descript" id="check-pos-descript" class="" checked>
        </div>
        <div id="countDisplay" style="font-size: 12px; padding: 10px; display: none;">
            <span id="checkedCount"></span> <b>/
            <span id="itemCount"></span></b> ->
            <span id="percentComplete"></span>
        </div>
        <div style="font-size: 12px; padding: 10px;">
            <div class="form-group dummy-form">
                <button class="btn btn-default btn-sm small" id="view-unchecked">View UnChecked</button>
            </div>
            <div class="form-group dummy-form">
                <button class="btn btn-default btn-sm small" id="view-checked">View Checked</button>
            </div>
            <div class="form-group dummy-form">
                <button class="btn btn-default btn-sm small" id="view-all">View All</button>
            </div>
            <div class="form-group dummy-form">
                <button class="btn btn-default btn-sm small" id="check-prices">Check Prices</button>
            </div>
        </div>
    </div>
    <div class="col-lg-3" >
        <div class="card" style="margin: 5px; box-shadow: 1px 1px lightgrey;">
            <div class="card-body">
                <h6 class="card-title" title="JK">Average Calculator</h6>
                    <div class="form-group">
                        <textarea rows=1 id="avgCalc" name="avgCalc" style="font-size: 12px" class="form-control small" ></textarea>
                    </div>
                    <div>
                        <p id="avgAnswer" style="font-size: 12px;"></p>
                    </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="card" style="margin: 5px; box-shadow: 1px 1px lightgrey;">
            <div class="card-body">
                <h6 class="card-title" title="JK">Simple Input Calculator &trade;</h6>
                <div class="row">
                    <div class="col-lg-9">
                        <input type="text" id="calculator" name="calculator" style="font-size: 12px" class="form-control small" autofocus>
                    </div>
                    <div class="col-lg-3">
                        <div class="form-group">
                            <button id="clear" class="btn btn-default btn-sm small form-control">CL</button>
                        </div>
                    </div>
                </div>
                <div>
                    <p id="output" style="font-size: 12px; padding-top: 10px;"></p>
                </div>
            </div>
        </div>
    </div>
</div>


<div id="mytable-container">
    {$this->postFetchHandler()}
</div>

</div>
HTML;
    }

    public function formContent()
    {
    }

    public function javascriptContent()
    {
        $dbc = ScanLib::getConObj();
        $mod = new DataModel($dbc);
        $config = $mod->getAuditReportOpt(session_id());

        $config = $_SESSION['columnBitSet'];
        $columnBitSet = $_SESSION['columnBitSet'];

        return <<<JAVASCRIPT
var startup = 1;
var columnSet = $config;
var tableRows = $('#table-rows').val();
var storeID = $('#storeID').val();
var username = $('#username').val();
var stripeTable = function(){
    $('tr.prod-row').each(function(){
        $(this).removeClass('stripe');
    });
    $('tr.prod-row').each(function(i = 0){
        if ($(this).is(':visible')) {;
            if (i % 2 == 0) {
                $(this).addClass('stripe');
            } else {
                $(this).removeClass('stripe');
            }
        i++;
        }
    });

    return false;
};
stripeTable();
//setInterval('stripeTable()', 1000);
$('#clearNotesInputB').click(function() {
    var c = confirm("Are you sure?");
    if (c == true) {
        $.ajax({
            type: 'post',
            data: 'storeID='+storeID+'&username='+username+'&notes=true',
            dataType: 'json',
            url: 'AuditReport.php',
            success: function(response) {
                location.reload();
            },
            error: function(response) {
            },
        });
    }
});
$('#clearAllInputB').click(function() {
    var c = confirm("Are you sure?");
    if (c == true) {
        $.ajax({
            type: 'post',
            data: 'storeID='+storeID+'&username='+username+'&clear=true',
            dataType: 'json',
            url: 'AuditReport.php',
            success: function(response) {
                location.href = 'AuditReport.php';
            },
            error: function(response) {
                alert('error');
            },
        });
    };
});

$("#notes").change( function() {
    var noteKey = $("#notes").val();
    var note = $("#notes").find(":selected").text();
    $("#mytable").each(function() {
        $(this).find("tr").each(function() {
            $(this).show();
        });
    });
    $("#mytable").each(function() {
        $(this).find("tr").each(function() {
            if (!$(this).parent('thead').is('thead')) {
                var notecell = $(this).find(".notes").text();
                if (note != notecell) {
                    $(this).closest("tr").hide();
                }
                if (noteKey == "viewall") {
                    $(this).show();
                }
                $(".blankrow").show();
            }
        });
    });
    stripeTable();
});

$('.copy-text').focus(function(){
    $(this).select();
    var status = document.execCommand('copy');
    if (status == true) {
        $(this).parent().find('.status-popup').show()
            .delay(400).fadeOut(400);
    }
});

$('.scanicon-trash').click( function(event) {
    var upc = $(this).parent().parent().find('.upc').attr('data-upc');
    var rowclicked = $(this).parent().parent().closest('tr').attr('id');
    var r = confirm('Remove '+upc+' from Queue?');
    if (r == true) {
        $.ajax({
            url: 'AuditReport.php',
            type: 'post',
            dataType: 'json',
            data: 'storeID='+storeID+'&upc='+upc+'&username='+username+'&deleteRow=true',
            success: function(response)
            {
                console.log(response);
                location.reload();
            },
            error: function(response)
            {
                console.log(response);
            },
        });
    }
});


var lastNotes = null
$('.editable-notes').click(function(){
    lastNotes = $(this).text();
});
$('.editable-notes').focusout(function(){
    var notes= $(this).text();
    var upc = $(this).parent().find('.upc').attr('data-upc');
    if (lastNotes != notes) {
        $.ajax({
            type: 'post',
            data: 'setNotes=true&upc='+upc+'&storeID='+storeID+'&username='+username+'&notes='+notes,
            dataType: 'json',
            url: 'AuditReport.php',
            success: function(response)
            {
                console.log(response);
                if (response.saved != true) {
                    // alert user of error
                } else {
                }
            },
        });
    }

});

//var lastSku = null
//$('.editable').each(function(){
//    $(this).attr('contentEditable', true);
//    $(this).attr('spellCheck', false);
//});
$('.editable-notes').each(function(){
    $(this).attr('contentEditable', true);
    $(this).attr('spellCheck', false);
});
$('.editable-description').each(function(){
    $(this).attr('contentEditable', true);
    $(this).attr('spellCheck', false);
});
$('.editable-description.sign-description').each(function(){
    $(this).attr('contentEditable', true);
    $(this).attr('spellCheck', false);
});
$('.editable-brand').each(function(){
    $(this).attr('contentEditable', true);
    $(this).attr('spellCheck', false);
});
$('.editable-brand.sign-brand').each(function(){
    $(this).attr('contentEditable', true);
    $(this).attr('spellCheck', false);
});
var lastBrand = null;
$('.editable-brand').click(function(){
    lastBrand = $(this).text();
});
$('.editable-brand').focusout(function(){
    var table = $(this).attr('data-table');
    var upc = $(this).parent().find('td.upc').attr('data-upc');
    var brand = $(this).text();
    brand = encodeURIComponent(brand);
    if (brand != lastBrand) {
        $.ajax({
            type: 'post',
            data: 'setBrand=true&upc='+upc+'&brand='+brand+'&table='+table,
            dataType: 'json',
            url: 'AuditReport.php',
            success: function(response)
            {
                console.log(response);
                if (response.saved != true) {
                    // alert user of error
                }
            },
        });
    }
});
var lastDescription = null;
$('.editable-description').click(function(){
    lastDescription = $(this).text();
});
$('.editable-description').focusout(function(){
    var table = $(this).attr('data-table');
    var upc = $(this).parent().find('td.upc').attr('data-upc');
    var description = encodeURIComponent($(this).text());
    if (description != lastDescription) {
        $.ajax({
            type: 'post',
            data: 'setDescription=true&upc='+upc+'&description='+description+'&table='+table,
            dataType: 'json',
            url: 'AuditReport.php',
            success: function(response)
            {
                console.log(response);
                if (response.saved != true) {
                    // alert user of error
                }
                var test = $(this).parent();
            },
        });
    }
});

$(document).keydown(function(e){
    var key = e.keyCode;
    $('#keydown').val(key);
});
$(document).keyup(function(e){
    var key = e.keyCode;
    $('#keydown').val(0);
});
$(document).mousedown(function(e){
    if (e.which == 1 && $('#keydown').val() == 16) {
        e.preventDefault();
        // SHIFT + LEFT CLICK
        var target = $(e.target);
        if (target.closest('tr').hasClass('highlight')) {
            target.closest('tr').removeClass('highlight');
        } else {
            $('tr').each(function(){
                if ($(this).hasClass('highlight')) {
                    $(this).removeClass('highlight');
                };
            });
            target.closest('tr').addClass('highlight');
        }
        $('#keydown').val(0);
    }
});

$('.row-check').click(function(){
    if (!$('#countDisplay').is(':visible')) {
        $('#countDisplay').show();
    }
    var rows = 0;
    var count = 0;
    $('.row-check').each(function(){
        rows++;
        if ($(this).prop('checked') == true) {
            count++;
        }
    });
    $('#itemCount').text(rows);
    $('#checkedCount').text(count);
    var percent = 100 * (count / rows);
    var strpercent = '';
    var i = 0
    for (i; i < percent; i += 10) {
        strpercent += '<span style="color: lightgreen;">&#9608;</span>';
    }
    for (i; i < 100; i += 10) {
        strpercent += '<span style="color: grey;">&#9608;</span>';
    }
    $('#percentComplete').html(Math.round(percent, 4) + '% Complete ' + strpercent);
});

$('.column-checkbox').change(function(){

    var numCols = 0;
    $('input.column-checkbox').each(function(){
        numCols++;  
    });

    var checked = $(this).is(':checked');
    var set = checked;
    var column = $(this).val();
    let columnName = "."+column;
    if (columnName == ".")
        return false;
    var colnum = $(this).attr('data-colnum');
    if (checked == true) {
        // show column
        $(columnName).each(function(){
            $(this).show();
        });
    } else {
        // hide column
        $(columnName).each(function(){
            $(this).hide();
        });
    }
    if (startup == 0) {
        // do not request ajax if mode = startup (initial column hide/show on pageload)
        $.ajax({
            type: 'post',
            data: 'columnSet='+colnum+'&set='+set+'&bitSet='+$columnBitSet+'&numCols='+numCols,
            dataType: 'json',
            url: 'AuditReport.php',
            success: function(response)
            {
                if (response.error) {
                    console.log(response);
                }
                console.log(response.test);
                console.log("SUCCESS!");
                console.log("VALUE: " + response.val);
            },
            error: function(response, errorThrown)
            {
                console.log(errorThrown);
                console.log("there was an error with your ajax request!");
            },
        });
    }
});

$('#check-pos-descript').click(function(){
    $('#check-brand').trigger('click');
    $('#check-sign-brand').trigger('click');
    $('#check-description').trigger('click');
    $('#check-sign-description').trigger('click');
});

// check for new rows, replace table if new scans found
var fetchNewRows = function()
{
    $.ajax({
        type: 'post',
        data: 'rowCount=true',
        dataType: 'json',
        url: 'AuditReport.php',
        success: function(response)
        {
            var newCount = response.count;
            if (newCount > tableRows) {
                tableRows = newCount;
            }
        },
    });
}
//setInterval('fetchNewRows()', 1000);

$('[id]').each(function(){
    var ids = $('[id="'+this.id+'"]');
    if(ids.length>1 && ids[0]==this)
        console.warn('Multiple IDs #'+this.id);
});

var styleChecked = function() {
    $('.row-check').each(function(){
        var checked = $(this).is(':checked');
        if (checked == true) {
            $(this).closest('tr').addClass('highlight-checked');
        } else {
            $(this).closest('tr').removeClass('highlight-checked');
        }
    });
};
$('.row-check').click(function(){
    var checked = $(this).is(':checked');
    var upc = $(this).closest('tr').find('td.upc').attr('data-upc');
    var storeID = $('#storeID').val();
    var username = $('#username').val();
    $.ajax({
        type: 'post',
        data: 'checked='+checked+'&upc='+upc+'&username='+username+'&storeID='+storeID,
        dataType: 'json',
        url: 'AuditReport.php',
        success: function(response)
        {
            console.log(response);
            styleChecked();
        },
    });
});
styleChecked();

$('.column-filter').each(function(){
    $(this).attr('contentEditable', true);
});
$('.column-filter').focusin(function(){
    $(this).select();
});
$('.column-filter').focusout(function(){
    $(this).text('');
});
$('.column-filter').keyup(function(){
    $('tr').each(function(){
        $(this).show();
    });
    var text = $(this).text().toUpperCase();
    var column = $(this).attr('data-column');
    $('td.'+column).each(function(){
        if ($(this).closest('tr').attr('id') != 'filter-tr') {
            var contents = $(this).text();
            contents = contents.toUpperCase();
            console.log(text+','+column+','+contents);
            console.log(contents.includes(text));
            if (contents.includes(text)) {
                $(this).closest('tr').show();
            } else {
                $(this).closest('tr').hide();
            }
        }
    });
});


$('#view-all').click(function(){
    $('#mytablebody tr').each(function(){
        $(this).show();
    });
});
$('#view-checked').click(function(){
    $('#mytablebody tr').each(function(){
        $(this).show();
    });
    $('#mytablebody tr').each(function(){
        var checked = $(this).find('.row-check').is(':checked');
        if (checked == true) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
});
$('#view-unchecked').click(function(){
    $('#mytablebody tr').each(function(){
        $(this).show();
    });
    $('#mytablebody tr').each(function(){
        var checked = $(this).find('.row-check').is(':checked');
        if (checked == false) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
});
$('#check-prices').click(function(){
    $('#mytablebody tr').each(function(){
        var srp = parseFloat($(this).find('.srp').text());
        var price = parseFloat($(this).find('.price').text());
        if (price < srp) {
            $(this).find('.srp').css('color', 'red')
                .css('font-weight', 'bold');
            $(this).find('.price').css('color', 'red');
        } else if (price > srp) {
            $(this).find('.srp').css('color', 'blue')
                .css('font-weight', 'bold');
            $(this).find('.price').css('color', 'blue');
        }
    });
});

$('.edit-department').change(function(){
    var upc = $(this).parent().parent().parent().find('td.upc').attr('data-upc');
    var dept = $(this).val();
    console.log(upc+', '+dept);
    $.ajax({
        type: 'post',
        data: 'setDept=true&upc='+upc+'&department='+dept,
        dataType: 'json',
        url: 'AuditReport.php',
        success: function(response)
        {
            console.log(response);
        },
    });
});

$('.dept-text').click(function(){
    $(this).parent().find('.dept-select').show();
    $(this).parent().find('.dept-select').trigger('click');;
    $(this).hide();
});
//$('.dept-select').change(function(){
//    setTimeout(function(){location.reload();
//    }, 500);
//});
//$('.dept-select').focusout(function(){
//    setTimeout(function(){location.reload();
//    }, 500);
//});

$('#temp').click(function(){
    c = confirm('Save costs to temp table?');
    if (c === true) {
        alert('well foo');
    }
});

var resizes = 0;
$('#calculator').keydown(function(e){
    if (e.keyCode == 13) {
        // Enter key pressed
        var arr = $('#calculator').val();
        arr = arr.replace('$', '');
        arr = arr.replace('CS', '');
        arr = arr.split(" ");
        if (arr.length == 3) {
            var val_1 = parseFloat(arr[0], 10);
            var oper = arr[1];
            var val_2 = parseFloat(arr[2], 10);
        } else {
            arr = arr[0];
            if (arr.indexOf('/') !== -1) {
                var oper =  '/';
                arr = arr.split('/');
            } else if (arr.indexOf('*') !== -1) {
                var oper =  '*';
                arr = arr.split('*');
            } else if (arr.indexOf('+') !== -1) {
                var oper =  '+';
                arr = arr.split('+');
            } else if (arr.indexOf('-') !== -1) {
                var oper =  '-';
                arr = arr.split('-');
            }
            var val_1 = arr[0];
            var val_2 = arr[1];
        }

        var ans = '';
        switch (oper) {
            case '+':
                ans = parseFloat(val_1) + parseFloat(val_2);
                break;
            case '-':
                ans = val_1 - val_2;
                break;
            case '*':
                ans = val_1 * val_2;
                break;
            case '/':
                ans = val_1 / val_2;
                break;
        }
        var val = $('#calculator').val(ans.toFixed(3));
        var html = $('#output').text();
        $('#output').prepend("<div>"+val_1+' '+oper+' '+val_2+" = "+ans.toFixed(3)+"</div>");
        if (resizes == 0) {
            window.resizeBy(0, 30);
        } else {
            window.resizeBy(0, 18);
        }
        resizes += 1;
    }
    if (e.keyCode == 8) {
    }
});

$('#clear').click(function(){
    $('#output').html("");
    window.resizeTo(215,120);
    $('#calculator').focus().val(null);
    resizes = 0;
});

$('#calculator').click(function(){
    $(this).select();
});

$('#temp-btn').click(function(){
    var text = $(this).text();
    if (text == 'Close Review') {
        c = confirm('Are you sure?');
        if (c == true) {
            return true;
        } else {
            return false;
        }
    }
});

$('#check-all').click(function(){
    var checked = $(this).is(':checked');
    if (checked == true) {
        $('.row-check').each(function(){
            if (!$(this).is(':checked')) {
                $(this).trigger('click');
            }
        });
    } else {
        $('.row-check').each(function(){
            if ($(this).is(':checked')) {
                $(this).trigger('click');
            }
        });
    }
});

// uncheck columns by session_id settings
$(window).load(function(){
    var numCols = 0;
    $('input.column-checkbox').each(function(){
        numCols++;  
    });
    //let bin = (columnSet >>> 0).toString(2);
    //let bin = 63;
    let bin = $columnBitSet;
    bin = bin.toString(2);
    bin = bin.padStart(numCols, '0');
    for (let i = bin.length; i >= 0; i--) {
        if (bin.charAt(i) == 0) {
            $('.column-checkbox[data-colnum='+i+']').trigger('click');
        }
    }
});
window.onload = function() {startup = 0;};

$('#avgCalc').focusout(function(){
    let text = $(this).val();
    let args = text.split('\\n');
    let total = 0;
    for (let i=0; i < args.length; i++) {
        total += parseFloat(args[i], 10);
    }
    let answer = total / args.length;
    console.log(total);
    console.log(answer);
    if (answer) {
        $('#avgAnswer').text(answer);
    } else {
        $('#avgAnswer').text('');
    }
});

$('#prevent-default').click(function(e) {
    e.preventDefault();
});
JAVASCRIPT;
    }

    public function cssContent()
    {
        return <<<HTML
span.margin-container {
    width: 38px;
    display: inline-block;
    border: 1px solid lightgrey;
    text-align: right;
}
.btn {
    cursor: pointer;
}
.dept-text {
    cursor: pointer;
}
select {
    border:none;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    -ms-appearance: none; /* get rid of default appearance for IE8, 9 and 10*/
    background-color: rgba(0,0,0,0);
    cursor: pointer;
}
td.column-filter, tr.filter-tr {
    height: 28px;
    background: lightblue;
    background: linear-gradient(#F5F5F5, white, #F5F5F5);
}
input[type=checkbox]:checked {
    color: red;
    border: 1px solid red;
}
th, .editable {
    cursor: pointer;
}
.hidden {
    display: none;
}
span.column-checkbox {
    padding: 5px;
}
tr, td {
    //position: relative;
}
tr.highlight {
    background-color: plum;
    background: linear-gradient(#FFCCE5, #FF99CC);
}
.currentEdit {
    color: purple;
    font-weight: bold;
}
.stripe {
    background: #FFFFCC;
}
thead {
    background-color: lightgrey;
    background: linear-gradient(lightgrey, #DEDEDE);
    //text-shadow: 1px 1px white;
}
.dummy-form {
    display: inline-block;
    padding: 5px;
}
.page-control {
    width: 140px;
}
.status-popup {
    display: none;
    position: absolute;
    top: 0px;
    right: 0px;
    background: white;
    padding: 5px;
    font-weight: bold;
    border-top-left-radius: 5px;
    border-top-right-radius: 5px;
    border-bottom-right-radius: 5px;
    border-style: solid solid solid solid;
    border-color: grey;
    border-width: 1px;
    box-shadow: 1px 1px slategrey;
}
.highlight-checked {
    background: grey;
    background-color: grey;
    color: white;
}
HTML;
    }

    public function helpContent()
    {
        return <<<HTML
<ul>
    <li>Definition of Columns</li>
    <ul>
        <li><strong>Check</strong> Show checkboxes for each row.</li>
        <li><strong>UPC</strong> Numerical barcode for each item.</li>
        <li><strong>SKU</strong> Current SKU for each item in respect to the default vendor ID.</li>
        <li><strong>Brand*</strong> POS brand that shows up on shelf tags.</li>
        <li><strong>Sign-Brand*</strong> Brand that shows up on Sale/special signage.</li>
        <li><strong>Description*</strong> POS description on shelf tags.</li>
        <li><strong>Sign-Description*</strong> Special sign description.</li>
        <li><strong>Size</strong> Size of 1 unit of products.</li>
        <li><strong>Units</strong> Case size from vendor.</li>
        <li><strong>NetCost</strong> POS cost before adjustments for shipping or discounts.</li>
        <li><strong>Cost</strong> POS cost <i>after</i> adjustments.</li>
        <li><strong>Recent Purchase / PO-Cost</strong> Most recent cost found in Purchase Order Items.</li>
        <li><strong>Price</strong> Current normal price in POS.</li>
        <li><strong>Sale</strong> Corrent sale price of item, if any.</li>
        <li><strong>Margin Target Diff</strong> Lists current margin, then target margin based on vendor and department, then the difference between the two.</li>
        <li><strong>RSRP</strong> Our WFC calculated SRP before applying rounding rules.</li>
        <li><strong>SRP</strong> SRP after rounding.</li>
        <li><strong>PRID</strong> Price rule ID.</li>
        <li><strong>Dept</strong> Department.</li>
        <li><strong>Vendor</strong> Default vendor.</li>
        <li><strong>Last Sold</strong> Show the date each item was last sold at each store.</li>
        <li><strong>Scale Item</strong> Scale item type.</li>
        <li><strong>Notes*</strong> Notes can be entered for each product from the Audit Scanner, or from this page.</li>
        <li><strong>Reviewed</strong> Shows the last time each product was reviewed, in respect to Fannie Product Review.</li>
        <li><strong>Cost Change</strong> Most recent cost change, taken only from when the <i>Review</i> button option is used.</li>
        <li><strong>*<strong> Columns with an asterisk in this list are editable fields.</li>
    </ul>
</ul>
HTML;
    }

}
WebDispatch::conditionalExec();
