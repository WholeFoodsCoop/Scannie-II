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
class NewAuditReport extends PageLayoutA
{

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();
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

        return parent::preprocess();
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


        return header("location: NewAuditReport.php$suff");
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

        return header('location: NewAuditReport.php');
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
                v.units
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

        $pth = "
        <tr id=\"filter-tr\">
            <td data-column=\"upc\"class=\"upc column-filter\"></td>
            <td data-column=\"sku\"class=\"sku column-filter\"></td>
            <td data-column=\"brand\"class=\"brand column-filter\"></td>
            <td data-column=\"sign-brand\"class=\"sign-brand hidden column-filter\"></td>
            <td data-column=\"description\"class=\"column-filter\"></td>
            <td data-column=\"sign-description\"class=\"sign-description hidden column-filter\"></td>
            <td data-column=\"size\"class=\"size column-filter\"></td>
            <td data-column=\"cost\"class=\"cost column-filter\"></td>
            <td data-column=\"price\"class=\"price column-filter\"></td>
            <td data-column=\"sale\"class=\"sale column-filter\"></td>
            <td data-column=\"\"class=\"margin_target_diff column-filter\"></td>
            <td data-column=\"srp\"class=\"srp column-filter\"></td>
            <td data-column=\"rsrp\"class=\"rsrp column-filter\"></td>
            <td data-column=\"prid\"class=\"prid column-filter\"></td>
            <td data-column=\"dept\"class=\"dept column-filter\"></td>
            <td data-column=\"vendor\"class=\"vendor column-filter\"></td>
            <td data-column=\"\"class=\"column-filter\"></td>
            <td data-column=\"notes\"class=\"notes column-filter\"></td>
            <td data-column=\"\"class=\"column-filter\"></td>
            <td><input type=\"checkbox\" id=\"check-all\"/></td>
        </tr>
        ";
        $th = "
        <tr>
            <th class=\"upc\">upc</th>
            <th class=\"sku\">sku</th>
            <th class=\"brand\">brand</th>
            <th class=\"sign-brand hidden\">sign-brand</th>
            <th class=\"description\">description</th>
            <th class=\"sign-description hidden\">sign-description</th>
            <th class=\"size\">size</th>
            <th class=\"units\">units</th>
            <th class=\"cost\">cost</th>
            <th class=\"price\">price</th>
            <th class=\"sale\">sale</th>
            <th class=\"margin_target_diff\">margin / target (diff)</th>
            <th class=\"srp\">srp</th>
            <th class=\"rsrp\">round srp</th>
            <th class=\"prid\">prid</th>
            <th class=\"dept\">dept</th>
            <th class=\"vendor\">vendor</th>
            <th class=\"last_sold\">last_sold</th>
            <th class=\"reviewed\">reviewed</th>
            <th class=\"notes\">notes</th>
            <th class=\"\"></th>
            <th class=\"check\"></th>
        </tr>
        ";
        $result = $dbc->execute($prep, $args);
        while ($row = $dbc->fetch_row($result)) {
            $upc = $row['upc'];
            $data = $this->getMovement($dbc, $upc);
            $lastSold = '';
            foreach ($data as $storeID => $bRow) {
                $inUse = ($bRow['inUse'] != 1) ? 'alert-danger' : 'alert-success';
                $ls = ($bRow['last_sold'] == null) ? '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' : $bRow['last_sold'];
                $lastSold .= '('.$storeID.') <span class="'.$inUse.'">'.$ls.'</span> ';
            }
            $uLink = '<a class="upc" href="../../../../git/fannie/item/ItemEditorPage.php?searchupc='.$upc.
                '&ntype=UPC&searchBtn=" target="_blank">'.$upc.'</a>';
            $sku = $row['sku'];
            $brand = $row['brand'];
            $signBrand = $row['signBrand'];
            $description = $row['description'];
            $signDescription = $row['signDescription'];
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
                    echo $margin; // this is incorrect
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
            $td .= "<tr class=\"prod-row\" id=\"$rowID\">";
            $td .= "<td class=\"upc\" data-upc=\"$upc\">$uLink</td>";
            $td .= "<td class=\"sku editable editable-sku\">$sku</td>";
            $td .= "<td class=\"brand editable editable-brand\" data-table=\"products\">$brand</td>";
            $td .= "<td class=\"sign-brand editable editable-brand hidden\" data-table=\"productUser\">$signBrand</td>";
            $td .= "<td class=\"description editable editable-description\" data-table=\"products\">$description</td>";
            $td .= "<td class=\"sign-description editable editable-description hidden\" data-table=\"productUser\">$signDescription</td>";
            $td .= "<td class=\"size\">$size</td>";
            $td .= "<td class=\"units\">$units</td>";
            $td .= "<td class=\"cost\" $ogCost>$cost</td>";
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
            $td .= "<td class=\"notes editable editable-notes\">$notes</td>";
            $td .= "<td class=\"last_sold\">$lastSold</td>";
            $td .= "<td class=\"reviewed\">$reviewed</td>";
            $td .= "<td><span class=\"scanicon scanicon-trash scanicon-sm \"></span></td></td>";
            $td .= "<td class=\"check\"><input type=\"checkbox\" name=\"check\" class=\"row-check\" $checked/></td>";
            $td .= "</tr>";
            $textarea .= "$upc\r\n";
        }
        $textarea .= "</textarea></div>";
        $rows = $dbc->numRows($result);

        $ret = <<<HTML
<input type="hidden" id="table-rows" value="$rows" />
<table class="table table-bordered table-sm small items" id="mytable">
<thead>$th</thead>
$pth
<tbody id="mytablebody">
    $td
    <tr><td>$textarea</td></tr>
</tbody>
</table>
HTML;

        if (FormLib::get('fetch') == 'true') {
            echo $ret;
            return false;
        } else {
            return $ret;
        }

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

    public function pageContent()
    {
        $dbc = scanLib::getConObj();
        $username = ($un = scanLib::getUser()) ? $un : "Generic User";
        $storeID = scanLib::getStoreID();
        $test = new DataModel($dbc);

        $prep = $dbc->prepare("SELECT * FROM woodshed_no_replicate.temp");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            //echo "<div>{$row['upc']}</div>";
        }
        $countTemp = $dbc->numRows($res);
        $tempClass = "btn-secondary";
        if ($countTemp > 0) {
            $tempBtn = 'Close Review';
            $tempInputVal = 'close';
            $tempClass = 'btn-danger';
        } else {
            $tempBtn = 'Open Review';
            $tempInputVal = 'open';
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

        $columns = array('check', 'upc', 'sku', 'brand', 'sign-brand', 'description', 'sign-description', 'size', 'units', 'cost', 'price',
            'sale', 'margin_target_diff', 'rsrp', 'srp', 'prid', 'dept', 'vendor', 'last_sold', 'notes', 'reviewed');
        $columnCheckboxes = "<div style=\"font-size: 12px; padding: 10px;\"><b>Show/Hide Columns: </b>";
        foreach ($columns as $column) {
            $columnCheckboxes .= "<span class=\"column-checkbox\"><label for=\"check-$column\">$column</label> <input type=\"checkbox\" name=\"column-checkboxes\" id=\"check-$column\" value=\"$column\" class=\"column-checkbox\" checked></span>";
        }
        $columnCheckboxes .= "</div>";

        $modal = "
            <div id=\"upcs_modal\" class=\"modal\">
                <div class=\"modal-dialog\" role=\"document\">
                    <div class=\"modal-content\">
                      <div class=\"modal-header\">
                        <h3 class=\"modal-title\" style=\"color: #8c7b70\">Upload a list of UPCs to scan</h3>
                        <button type=\"button\" class=\"close\" data-dismiss=\"modal\" aria-label=\"Close\"
                                style=\"position: absolute; top:20; right: 20\">
                              <span aria-hidden=\"true\">&times;</span>
                            </button>
                          </div>
                          <div class=\"modal-body\">
                            <div align=\"center\">
                                <form method=\"post\" class=\"form-inline\">
                                    <div class=\"form-group\">
                                        <textarea class=\"form-control\" name=\"upcs\" rows=\"10\" cols=\"50\"></textarea>
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

        $this->addScript('../../../common/javascript/tablesorter/js/jquery.tablesorter.min.js');
        $this->addScript('../../../common/javascript/tablesorter/js/jquery.metadata.js');
        $this->addOnloadCommand("$('#mytable').tablesorter();");

        return <<<HTML
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
    <button class="btn btn-secondary btn-sm page-control" data-toggle="modal" data-target="#upcs_modal">Upload a List</button>
</div>
<div class="form-group dummy-form">
    <form method="post" action="NewAuditReport.php">
        <button class="btn $tempClass btn-sm page-control" id="temp-btn">$tempBtn</button>
        <input type="hidden" name="review" value="$tempInputVal"/>
        <input type="hidden" name="username" value="$username"/>
    </form>
</div>
<div class="form-group dummy-form">
    <a class="btn btn-info btn-sm page-control" href="AuditScanner.php ">Scanner</a>
</div>
$nFilter
$columnCheckboxes

<div class="row">
    <div class="col-lg-8">
        <div style="font-size: 12px; padding: 10px;">
            <label for="check-pos-descript"><b>Switch POS/SIGN Descriptors</b>:&nbsp;</label><input type="checkbox" name="check-pos-descript" id="check-pos-descript" class="column-checkbox" checked>
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
    <div class="col-lg-4">
        <div class="card" style="margin: 5px">
            <div class="card-body">
                <h6 class="card-title">Simple Input Calculator &trade;</h6>
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
HTML;
    }

    public function formContent()
    {
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
var tableRows = $('#table-rows').val();
var storeID = $('#storeID').val();
var username = $('#username').val();
var stripeTable = function(){
    $('tr.prod-row').each(function(){
        $(this).removeClass('stripe');
    });
    $('tr.prod-row').each(function(i = 0){
        if ($(this).is(':visible')) {
            if (i % 2 == 0) {
                $(this).addClass('stripe');
            } else {
                $(this).removeClass('stripe');
            }
        }
        i++;
    });

    return false;
};
$.ajax({
    type: 'post',
    data: 'test=true',
    dataType: 'json',
    url: 'NewAuditReport.php',
    success: function(response)
    {
        console.log("ajax test: "+response.test);
    },
});
stripeTable();
setInterval('stripeTable()', 1000);
//$(document).mouseup(function(){
//    stripeTable();
//});
//$(document).mousedown(function(){
//    stripeTable();
//});
$('#clearNotesInputB').click(function() {
    var c = confirm("Are you sure?");
    if (c == true) {
        $.ajax({
            type: 'post', 
            data: 'storeID='+storeID+'&username='+username+'&notes=true',
            dataType: 'json',
            url: 'NewAuditReport.php',
            success: function(response) {
                //fetchTable();
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
            url: 'NewAuditReport.php',
            success: function(response) {
                //fetchTable();
                location.reload();
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
            url: 'NewAuditReport.php',
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
    var upc = $(this).parent().parent().find('.upc').attr('data-upc');
    if (lastNotes != notes) {
        //alert(lastNotes+','+notes+','+upc+','+storeID+','+username);
        $.ajax({
            type: 'post',
            data: 'setNotes=true&upc='+upc+'&storeID='+storeID+'&username='+username+'&notes='+notes,
            dataType: 'json',
            url: 'NewAuditReport.php',
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
$('.editable-description.sign-description').each(function(){
    $(this).attr('contentEditable', true);
    $(this).attr('spellCheck', false);
});
$('.editable-brand.sign-brand').each(function(){
    $(this).attr('contentEditable', true);
    $(this).attr('spellCheck', false);
});
//$('.editable').click(function(){
//    $(this).addClass('currentEdit');
//});
//$('.editable').focusout(function(){
//    $(this).removeClass('currentEdit');
//});
//
//$('.editable-sku').click(function(){
//    lastSku = $(this).text();
//});
//$('.editable-sku').focusout(function(){
//    var sku = $(this).text();
//    var vendorID = $(this).parent().find('td.vendor').attr('data-vendorID');
//    var upc = $(this).parent().parent().find('.upc').attr('data-upc');
//    $.ajax({
//        type: 'post',
//        data: 'setSku=true&lastSku='+lastSku+'&sku='+sku+'&vendorID='+vendorID+'&upc='+upc,
//        dataType: 'json',
//        url: 'NewAuditReport.php',
//        success: function(response)
//        {
//            console.log(response);
//            if (response.saved != true) {
//                // alert user of error
//            } else {
//            }
//        },
//    });
//
//});
var lastBrand = null;
$('.editable-brand.sign-brand').click(function(){
    lastBrand = $(this).text();
});
$('.editable-brand.sign-brand').focusout(function(){
    var table = $(this).attr('data-table');
    var upc = $(this).parent().find('td.upc').attr('data-upc');
    var brand = $(this).text();
    if (brand != lastBrand) {
        $.ajax({
            type: 'post',
            data: 'setBrand=true&upc='+upc+'&brand='+brand+'&table='+table,
            dataType: 'json',
            url: 'NewAuditReport.php',
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
$('.editable-description.sign-description').click(function(){
    lastDescription = $(this).text();
});
$('.editable-description.sign-description').focusout(function(){
    var table = $(this).attr('data-table');
    var upc = $(this).parent().find('td.upc').attr('data-upc');
    var description = encodeURIComponent($(this).text());
    if (description != lastDescription) {
        $.ajax({
            type: 'post',
            data: 'setDescription=true&upc='+upc+'&description='+description+'&table='+table,
            dataType: 'json',
            url: 'NewAuditReport.php',
            success: function(response)
            {
                console.log(response);
                if (response.saved != true) {
                    // alert user of error
                }
                var test = $(this).parent();
                //var test = $(this);
                console.log(test);
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
        //console.log(e.target);
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
    var checked = $(this).is(':checked');
    var column = $(this).val();
    if (checked == true) {
        // show column
        $('.'+column).each(function(){
            $(this).show();
        }); 
    } else {
        // hide column
        $('.'+column).each(function(){
            $(this).hide();
        }); 
    }
});

$('.column-checkbox').each(function(){
    var column = $(this).val();
    if (column == 'sign-brand') {
        $(this).prop('checked', false);
    }
    if (column == 'sign-description') {
        $(this).prop('checked', false);
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
        url: 'NewAuditReport.php',
        success: function(response)
        {
            var newCount = response.count;
            //console.log(tableRows+', '+newCount);
            if (newCount > tableRows) {
                //fetchTable();
                tableRows = newCount;
                location.reload();
                //console.log(document);
            }
        },
    });
}
setInterval('fetchNewRows()', 1000);
//fetchTable();

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
        url: 'NewAuditReport.php',
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
        url: 'NewAuditReport.php',
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
$('.dept-select').change(function(){
    setTimeout(function(){location.reload(); 
    }, 500);
});
$('.dept-select').focusout(function(){
    setTimeout(function(){location.reload(); 
    }, 500);
});

$('#temp').click(function(){
    c = confirm('Save costs to temp table?');
    if (c === true) {
        alert('well foo');
    }
});

// uncheck column filter defaults
$('#check-prid').trigger('click');
$('#check-margin_target_diff').trigger('click');
$('#check-notes').trigger('click');
$('#check-sale').trigger('click');
$('#check-last_sold').trigger('click');
$('#check-reviewed').trigger('click');

var resizes = 0;
$('#calculator').keydown(function(e){
    if (e.keyCode == 13) {
        // Enter key pressed
        //alert('enter');
        var arr = $('#calculator').val();
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
        // "Backspace"
        //$('#calculator').val('');
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
JAVASCRIPT;
    }

    public function cssContent()
    {
        return <<<HTML
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
    position: relative;
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

//    public function helpContent()
//    {
//        return <<<HTML
//HTML;
//    }

}
WebDispatch::conditionalExec();