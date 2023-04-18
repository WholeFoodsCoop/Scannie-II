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

    public $columns = array('check', 'upc', 'sku', 'alias', 'brand', 'sign-brand', 'description', 'sign-description', 'size', 'units', 'netcost', 'cost', 'recentPurchase',
        'price', 'sale', 'autoPar', 'margin_target_diff', 'rsrp', 'srp', 'prid', 'dept', 'subdept', 'local', 'flags', 'vendor', 'last_sold', 'scaleItem', 
        'scalePLU', 'mnote', 'notes', 'reviewed', 'costChange', 'floorSections', 'comment', 'PRN', 'caseCost');

    public function preprocess()
    {
        $this->displayFunction = $this->postView();
        $this->__routes[] = 'post<test>';
        $this->__routes[] = 'post<scrollMode>';
        $this->__routes[] = 'post<reviewList>';
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
        $this->__routes[] = 'post<vendCat>';
        $this->__routes[] = 'post<brandList>';
        $this->__routes[] = 'post<setStoreID>';
        $this->__routes[] = 'get<exportExcel>';

        return parent::preprocess();
    }

    public function getExportExcelHandler()
    {
        echo $this->postView('true');

        return false;
    }

    public function postSetStoreIDHandler()
    {
        $storeID = FormLib::get('setStoreID', false);
        $_SESSION['AuditReportStoreID'] = $storeID;

        return false;
    }

    public function postScrollModeHandler()
    {
        $scrollMode = FormLib::get('scrollMode');
        $_SESSION['scrollMode'] = $scrollMode;

        return true;
    }

    public function postBrandListHandler()
    {
        $dbc = ScanLib::getConObj();
        $brand = FormLib::get('brandList');
        $username = FormLib::get('username');
        $storeID = FormLib::get('storeID');

        $args = array($brand);
        $prep = $dbc->prepare("SELECT upc FROM products WHERE TRIM(brand) = TRIM(?)");
        $res = $dbc->execute($prep, $args);
        $items = array();
        while ($row = $dbc->fetchRow($res)) {
            $items[] = $row['upc'];
        }

        $this->loadVendorCatalogHandler($items, $username, $storeID);

        return header("location: AuditReport.php");
    }

    public function postVendCatHandler()
    {
        $dbc = ScanLib::getConObj();
        $vid = FormLib::get('vendCat');
        $username = FormLib::get('username');
        $storeID = FormLib::get('storeID');
        $full = FormLib::get('loadFullCat');
        $inUse = ($full == 1) ? '' : ' AND inUse = 1 ';

        $args = array($vid);
        $prep = $dbc->prepare("SELECT v.upc
            FROM products AS p
                LEFT JOIN vendorItems AS v ON v.upc=p.upc AND v.vendorID=p.default_vendor_id
                RIGHT JOIN MasterSuperDepts AS m ON m.dept_ID=p.department
            WHERE p.default_vendor_id = ?
                AND m.super_name != 'PRODUCE'
                $inUse
            GROUP BY p.upc;
        ");
        $res = $dbc->execute($prep, $args);
        $items = array();
        while ($row = $dbc->fetchRow($res)) {
            $items[] = $row['upc'];
        }

        $this->loadVendorCatalogHandler($items, $username, $storeID);

        return header("location: AuditReport.php?upc=$items[0]");
    }

    private function getUpcList($username, $storeID)
    {
        $upcs = array();
        $dbc = ScanLib::getConObj();

        $args = array($username);
        $prep = $dbc->prepare("SELECT upc FROM woodshed_no_replicate.AuditScan
            WHERE username = ? AND savedAs = 'default'");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $upcs[$row['upc']] = $row['upc'];
            //echo $row['upc'];
        }
        //var_dump($dbc);
        echo $dbc->error();

        return $upcs;
    }

    public function postDeleteListHandler($demo=false)
    {

        $dbc = ScanLib::getConObj('SCANALTDB');
        $delete = FormLib::get('deleteList');
        $delete = htmlspecialchars_decode($delete);
        $username = FormLib::get('username');

        $args = array($username, $delete);
        $prep = $dbc->prepare("DELETE FROM AuditScan WHERE username = ?
            AND savedAs = ?");
        $res = $dbc->execute($prep, $args);

        $args = array($username);
        $prep = $dbc->prepare("DELETE FROM AuditScan WHERE username = ?
            AND savedAs = 'default'");
        $res = $dbc->execute($prep, $args);

        return header("location: AuditReport.php");
    }

    private function loadVendorCatalogHandler($upcs, $username, $storeID)
    {
        $dbc = ScanLib::getConObj('SCANALTDB');

        $args = array($username);
        $prep = $dbc->prepare("DELETE FROM AuditScan WHERE username = ?
            AND savedAs = 'default'");
        $res = $dbc->execute($prep, $args);

        foreach ($upcs as $upc) {
            $args = array($upc, $username, $storeID);
            $prep = $dbc->prepare("INSERT INTO AuditScan (date, upc, username, storeID, savedAs, notes)
                VALUES (NOW(), ?, ?, ?, 'default', '' );
            ");
            $res = $dbc->execute($prep, $args);
        }

        return false;
    }

    public function postLoadListHandler()
    {

        $dbc = ScanLib::getConObj('SCANALTDB');
        $load = FormLib::get('loadList');
        $username = FormLib::get('username');
        $storeID = FormLib::get('storeID');

        $args = array($username);
        $prep = $dbc->prepare("DELETE FROM AuditScan WHERE username = ?
            AND savedAs = 'default'");
        $res = $dbc->execute($prep, $args);

        $args = array($username, $storeID, $load);
        $prep = $dbc->prepare("INSERT INTO AuditScan (date, upc, username, storeID, savedAs, notes)
            SELECT NOW(), upc, username, storeID, 'default', notes FROM AuditScan WHERE username = ?
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
        
        $notes = array();
        $args = array($username);
        $prep = $dbc->prepare("SELECT upc, notes FROM AuditScan 
            WHERE username = ? AND savedAs = 'default'");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $notes[$row['upc']] = $row['notes'];
        }

        foreach($upcs as $upc) {
            $note = '';
            $note = $notes[$upc];
            $args = array($upc, $username, $storeID, $saveAs, $note);
            $prep = $dbc->prepare("INSERT INTO AuditScan (date, upc, username, storeID, savedAs, notes)
                VALUES (NOW(), ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE date = NOW()");
            $res = $dbc->execute($prep, $args);
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
        $json = array();

        $dbc = ScanLib::getConObj();
        $args = array($checked, $username, $upc);
        $query = $dbc->prepare("UPDATE woodshed_no_replicate.AuditScan SET checked = ? WHERE username = ? AND upc = ? 
            AND savedAs = 'default'");
        $dbc->execute($query, $args);
        if ($er = $dbc->error())
            $json['error'] = $er;
        echo json_encode($json);

        return false;
    }

    public function postReviewListHandler()
    {
        $username = FormLib::get('username');
        $storeID = FormLib::get('storeID');
        $listName = '';
        $json = array();

        $dbc = ScanLib::getConObj('SCANALTDB');

        $args = array($username);
        $prep = $dbc->prepare("SELECT v.vendorName FROM AuditScan AS a 
            LEFT JOIN is4c_op.products AS p ON p.upc=a.upc
            LEFT JOIN is4c_op.vendors AS v ON v.vendorID=p.default_vendor_id
            WHERE username = ? AND savedAs = 'default' 
            LIMIT 1");
        $res = $dbc->execute($prep, $args);
        $row = $dbc->fetchRow($res);
        $listName = $row['vendorName'] . " REVIEW LIST";

        $args = array($username, $storeID, $listName, $username);
        $prep = $dbc->prepare("INSERT IGNORE INTO AuditScan (date, upc, username, storeID, notes, checked, savedAs) 
            SELECT date, upc, ?, ?, notes, checked, ? 
            FROM AuditScan where savedAs = 'default' AND username = ? AND notes != ''
            ");
        $res = $dbc->execute($prep, $args);

        $json['saved'] = 1;
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
        $vendorID = FormLib::get('vendorID');
        $json = array();

        $dbc = ScanLib::getConObj();
        $mod = new DataModel($dbc);
        $json['saved'] = $mod->setCost($upc, $cost, $vendorID);
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
        $username = FormLib::get('username');
        $upc = FormLib::get('upc');
        $json = array();
        $json['test'] = 'test';

        $dbc = ScanLib::getConObj();
        $args = array($upc, $username);
        $prep = $dbc->prepare('DELETE FROM woodshed_no_replicate.AuditScan WHERE upc = ? AND username = ? AND savedAs = "default"');
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
        $username = FormLib::get('username');
        $args = array($username);
        $query = $dbc->prepare("DELETE FROM woodshed_no_replicate.AuditScan WHERE username = ? AND savedAs = 'default'");
        $dbc->execute($query, $args);

        $json = array('test'=>'successful');
        echo json_encode($json);

        return false;
    }


    public function postNotesHandler()
    {
        $dbc = ScanLib::getConObj();
        $username = FormLib::get('username');
        $args = array($username);
        $query = $dbc->prepare("UPDATE woodshed_no_replicate.AuditScan SET notes = '' WHERE username = ? AND savedAs = 'default'");
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
        $deleteList = FormLib::get('add-delete-list', false);

        $upcs = FormLib::get('upcs');
        $plus = array();
        $chunks = explode("\r\n", $upcs);
        foreach ($chunks as $key => $str) {
            $str = scanLib::upcParse($str);
            $str = scanLib::upcPreparse($str);
            $plus[] = $str;
        }

        if ($deleteList == false) {
            foreach ($plus as $upc) {
                if ($upc != 0) {
                    $args = array($upc, $username, $storeID);
                    $prep = $dbc->prepare("INSERT IGNORE INTO woodshed_no_replicate.AuditScan (upc, username, storeID, date, savedAs)
                        VALUES (?, ?, ?, NOW(), 'default');");
                    $res = $dbc->execute($prep, $args);
                }
            }
        } else {
            foreach ($plus as $upc) {
                if ($upc != 0) {
                    $args = array($upc, $username);
                    $prep = $dbc->prepare("DELETE FROM woodshed_no_replicate.AuditScan WHERE upc = ? AND username = ? AND savedAs = 'default'");
                    $res = $dbc->execute($prep, $args);
                }
            }
        }

        return header('location: AuditReport.php');
    }

    public function postRowCountHandler()
    {
        $dbc = ScanLib::getConObj();

        $json = array('count' => null);
        $username = ($un = scanLib::getUser()) ? $un : "Generic User";
        $args = array($username);
        $query = $dbc->prepare("
            SELECT upc
            FROM woodshed_no_replicate.AuditScan
            WHERE username = ?
        ");
        $result = $dbc->execute($query, $args);
        $json['count'] = $dbc->numRows($result);
        echo json_encode($json);

        return false;
    }

    public function postFetchHandler($demo=false)
    {
        $dbc = ScanLib::getConObj();
        $username = ($un = scanLib::getUser()) ? $un : "Generic User";
        $storeID = (isset($_SESSION['AuditReportStoreID'])) ? $_SESSION['AuditReportStoreID'] : scanLib::getStoreID();
        $rounder = new PriceRounder();

        $upcs = array();

        $args = array($username, $storeID);
        $prep = $dbc->prepare("
            SELECT
                pf.flags,
                p.store_id,
                p.upc,
                v.sku,
                va.sku AS alias,
                va.isPrimary,
                p.brand,
                u.brand AS signBrand,
                p.description AS description,
                u.description AS signDescription,
                p.cost,
                p.auto_par,
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
                a.PRN, 
                ROUND(p.cost * v.units, 2) AS caseCost,
                CASE
                    WHEN vd.margin > 0.01 THEN p.cost / (1 - vd.margin) ELSE p.cost / (1 - dm.margin)
                END AS rsrp,
                v.srp AS vsrp,
                a.checked,
                p.last_sold,
                pr.reviewed,
                CASE
                    WHEN p.size <> 0 THEN CONCAT(p.size, ' ', p.unitofmeasure) ELSE CONCAT(v.size, ' ', p.unitofmeasure)
                END AS size,
                v.units,
                c.previousCost,
                c.newCost,
                c.difference AS costChange,
                c.date AS costChangeDate,
                subdepts.subdept_name AS subdept,
                p.price_rule_id,
                CASE 
                    WHEN p.local = 0 THEN ''
                    WHEN p.local = 1 THEN 'SC'
                    WHEN p.local = 2 THEN 'MN/WI'
                END AS local,
                fslv.sections AS floorSections,
                pr.comment
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
                LEFT JOIN subdepts ON subdepts.subdept_no=p.subdept AND subdepts.dept_ID=p.department
                LEFT JOIN prodFlagsListView AS pf ON pf.upc=p.upc AND pf.storeID=p.store_id
                LEFT JOIN FloorSectionsListView AS fslv ON fslv.upc=p.upc AND fslv.storeID=p.store_id
                LEFT JOIN VendorAliases AS va ON va.vendorID=p.default_vendor_id AND va.upc=p.upc
            WHERE p.upc != '0000000000000'
                AND a.username = ?
                AND p.store_id = ?
                AND a.savedAS = 'default'
            GROUP BY a.upc
            ORDER BY a.date DESC
        ");

        // get autopar for all stores
        $pars = array();
        $parA = array($username);
        $parP = $dbc->prepare("
            SELECT p.upc,
                ROUND(auto_par*7,1) AS autoPar,
                p.store_id,
                CASE 
                    WHEN p.last_sold IS NULL THEN DATEDIFF(NOW(), p.created)
                    ELSE
                    CASE 
                        WHEN DATEDIFF(NOW(), p.last_sold) > 0 THEN DATEDIFF(NOW(), p.last_sold)
                        ELSE 9999
                    END
                END AS daysWOsale,
                CASE
                    WHEN p.last_sold IS NULL THEN 'created'
                    ELSE 'last_sold'
                END AS daysWOtype 
            FROM products AS p
                RIGHT JOIN woodshed_no_replicate.AuditScan AS a ON a.upc=p.upc
            WHERE p.upc != '0000000000000'
                AND a.username = ? 
                AND a.savedAS = 'default'
            ORDER BY p.upc, p.store_id
        ");
        $parR = $dbc->execute($parP, $parA);
        while ($row = $dbc->fetchRow($parR)) {
            $pars[$row['upc']][$row['store_id']] = $row['autoPar'];
            $woSales[$row['upc']][$row['store_id']] = $row['daysWOsale'];
            $woType[$row['upc']][$row['store_id']] = $row['daysWOtype'];
        }

        $td = "";
        $csv = "UPC, SKU, ALIAS, BRAND, SIGNBRAND, DESC, SIGNDESC, SIZE, UNITS, NETCOST, COST, RECENT PURCHASE, PRICE, CUR SALE, AUTOPAR, CUR MARGIN, TARGET MARGIN, DIFF, RAW SRP, SRP, PRICE RULE, DEPT, SUBDEPT, LOCAL, FLAGS, VENDOR, LAST TIME SOLD, SCALE, SCALE PLU, LAST REVIEWED, FLOOR SECTIONS, REVIEW COMMENTS, PRN, CASE COST, NOTES\r\n";

            //$prepCsv = strip_tags("\"$upc\", \"$sku\", \"$brand\", \"$signBrand\", \"$description\", \"$signDesecription\", $size, $units, $netCost, $cost, $recentPurchase, $price, $sale, $autoPar, $curMargin, $margin, $diff, $rsrp, $srp, $prid, $dept, $subdept, $local, \"$flags\", \"$vendor\", $lastSold, $bycount, \"$scalePLU\", \"$reviewed\", \"$floorSections\", \"$reviewComments\", \"$prn\", $caseCost, \"$notes");
        $textarea = "<div style=\"position: relative\">
            <span class=\"status-popup\">Copied!</span>
            <textarea class=\"copy-text\" id=\"list\" name=\"list\" rows=3 cols=10>";

        // this is the second thead row (filters)
        $pth = "
        <tr id=\"filter-tr\">
            <td title=\"upc\" data-column=\"upc\"class=\"upc column-filter\"upc</td>
            <td title=\"sku\" data-column=\"sku\"class=\"sku column-filter\"></td>
            <td title=\"alias\" data-column=\"alias\"class=\"alias column-filter\"></td>
            <td title=\"band\" data-column=\"brand\"class=\"brand column-filter\"></td>
            <td title=\"sign-brand\" data-column=\"sign-brand\"class=\"sign-brand column-filter\"></td>
            <td title=\"description\" data-column=\"description\"class=\"description column-filter\"></td>
            <td title=\"sign-description\" data-column=\"sign-description\"class=\"sign-description column-filter\"></td>
            <td title=\"size\" data-column=\"size\"class=\"size column-filter\"></td>
            <td title=\"units\" data-column=\"units\"class=\"units column-filter\"></td>
            <td title=\"netCost\" data-column=\"netCost\"class=\"netCost column-filter\"></td>
            <td title=\"cost\" data-column=\"cost\"class=\"cost column-filter\"></td>
            <td title=\"recentPurchases\" data-column=\"recentPurchase\"class=\"recentPurchase column-filter\"></td>
            <td title=\"price\" data-column=\"price\"class=\"price column-filter\"></td>
            <td title=\"sale\" data-column=\"sale\"class=\"sale column-filter\"></td>
            <td title=\"autoPar\" data-column=\"autoPar\"class=\"autoPar column-filter\"></td>
            <td title=\"margin_target_diff\" data-column=\"margin_target_diff\"class=\"margin_target_diff column-filter\"></td>
            <td title=\"srp\" data-column=\"srp\"class=\"srp column-filter\"></td>
            <td title=\"rsrp\" data-column=\"rsrp\"class=\"rsrp column-filter\"></td>
            <td title=\"prid\" data-column=\"prid\"class=\"prid column-filter\"></td>
            <td title=\"dept\" data-column=\"dept\"class=\"dept column-filter\"></td>
            <td title=\"subdebt\" data-column=\"subdept\"class=\"subdept column-filter\"></td>
            <td title=\"local\" data-column=\"local\"class=\"local column-filter\"></td>
            <td title=\"flags\" data-column=\"flags\"class=\"flags column-filter\"></td>
            <td title=\"vendor\" data-column=\"vendor\"class=\"vendor column-filter\"></td>
            <td title=\"last_sold\" data-column=\"last_sold\"class=\"last_sold column-filter\"></td>
            <td title=\"scaleItem\" data-column=\"scaleItem\"class=\"scaleItem column-filter\"></td>
            <td title=\"scalePLU\" data-column=\"scalePLU\"class=\"scalePLU column-filter\"></td>
            <td title=\"reviewed\" data-column=\"reviewed\"class=\"reviewed column-filter\"></td>
            <td title=\"costChange\" data-column=\"costChange\"class=\"costChange column-filter\"></td>
            <td title=\"floorSections\" data-column=\"floorSections\"class=\"floorSections column-filter\"></td>
            <td title=\"comment\" data-column=\"comment\"class=\"comment column-filter\"></td>
            <td title=\"PRN\" data-column=\"PRN\"class=\"PRN column-filter\"></td>
            <td title=\"caseCost\" data-column=\"caseCost\"class=\"caseCost column-filter\"></td>
            <td title=\"mnote\" data-column=\"mnote\"class=\"mnote column-filter\"></td>
            <td title=\"notes\" data-column=\"notes\"class=\"notes column-filter\"></td>
            <td title=\"check\" data-column=\"check\" class=\"check column-filter\"></td>
            <td title=\"trash-icon\" data-column=\"trash-icon\" class=\"trash-icon column-filter\"></td> <!-- you cannot filter this column -->
        </tr>
        ";


        // this is the first thead row (column sorting)
        $th = "
        <tr>
            <th class=\"upc\">upc</th>
            <th class=\"sku\">sku</th>
            <th class=\"alias\">alias</th>
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
            <th class=\"autoPar\">autoPar(*7)</th>
            <th class=\"margin_target_diff\">margin, target, diff</th>
            <th class=\"rsrp\">raw srp</th>
            <th class=\"srp\">srp</th>
            <th class=\"prid\">prid</th>
            <th class=\"dept\">dept</th>
            <th class=\"subdept\">subdept</th>
            <th class=\"local\">local</th>
            <th class=\"flags\">flags</th>
            <th class=\"vendor\">vendor</th>
            <th class=\"last_sold\">last_sold</th>
            <th class=\"scaleItem\">scale</th>
            <th class=\"scalePLU\">scalePLU</th>
            <th class=\"reviewed\">reviewed</th>
            <th class=\"costChange\">last cost change</th>
            <th class=\"floorSections\">floor sections</th>
            <th class=\"comment\">comment</th>
            <th class=\"PRN\">PRN</th>
            <th class=\"caseCost\">caseCost</th>
            <th class=\"mnote\">mnote</th>
            <th class=\"notes\">notes</th>
            <th class=\"trash\"></th>
            <th class=\"check\"></th>
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
            $alias = $row['alias'];
            $isPrimary = $row['isPrimary'];
            if ($isPrimary == 1) {
                $alias = "<span style=\"background-color: #CBF6FF\">$alias [P]</span>";
            }
            list($recentPurchase, $received) = $this->getRecentPurchase($dbc,$upc);
            $brand = $row['brand'];
            //$autoPar = '';
            $autoPar = '<div style="height: 12px;"><table class="table table-small small" style="margin-top: -4.5px;
                background-color: rgba(0,0,0,0); border: 0px solid transparent;">';
            $csvAutoPar = '';
            foreach ($pars[$upc] as $storeID => $par) {
                $woSalesText = '';
                if ($woSales[$upc][$storeID] < 20) {
                    $woSalesText = 'lightgreen';
                }
                if ($woSales[$upc][$storeID] > 19) {
                    $woSalesText = 'orange';
                }
                if ($woSales[$upc][$storeID] > 29) {
                    $woSalesText = 'tomato';
                }
                if ($woSales[$upc][$storeID] > 60) {
                    $woSalesText = 'darkred';
                }
                if ($woSales[$upc][$storeID] == 1 && $woType[$upc][$storeID] == 'last_sold') {
                    $woSalesText = 'lightblue';
                }
                if (strlen($par) == 3)
                    $par = "<span style=\"color: transparent\">_</span>".$par;
                //$autoPar .= "<span style=\"border: 1px solid $woSalesText;\"><span style=\"color: $woSalesText; \">&#9608;</span> $par</span> ";
                //$autoPar .= "<td style=\"width: 25px\"><span style=\"color: $woSalesText; \">&#9608;</span> $par</td>";
                $autoPar .= "<td style=\"width: 25px; border-left: 5px solid $woSalesText; \"> $par</td>";
                $csvAutoPar .= "[$storeID] $par ";
            }
            $autoPar .= "</table></div>";
            $signBrand = $row['signBrand'];
            $description = $row['description'];
            $signDescription = $row['signDescription'];
            $netCost = $row['cost'];
            $cost = $row['cost'];
            $ogCost = null;
            $adjcost = $row['adjcost'];
            $price = $row['price'];
            $badPrice = ($netCost > $price) ? ' style="color: tomato; font-weight: bold"; title="Price Below Cost" ' : '';
            $priceRuleID = $row['price_rule_id'];
            $sale = $row['sale'];
            if ($sale == '0.00') {
                $sale = '';
            } else if ($sale == $price) {
                $sale = 'BOGO';
            } else {
                $sale = "$$sale";
            }
            if ($priceRuleID != 0) {
                $price = "$price <span style=\"font-weight: bold; color: blue; \">*</span>";
            }
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
            //override srp with value in products
            $srp = $row['vsrp'];
            $prid = $row['priceRuleType'];
            $dept = $row['dept'];
            $subdept = $row['subdept'];
            $local = $row['local'];
            $storeID = $row['store_id'];
            //$flags = $flagData[$upc][$storeID];
            $flags = $row['flags'];
            $vendor = $row['vendor'];
            $notes = $row['notes'];
            $mnote = ($notes != '') ? "<button class=\"btn-mnote\"><b><</b></button>" : null;
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
            $floorSections = $row['floorSections'];
            $reviewComments = $row['comment'];
            $prn = $row['PRN'];
            $scalePLU = ($bycount == null) ? '' : substr($upc, 3, 4);
            $caseCost = $row['caseCost'];
            $ubid = uniqid();
            $td .= "<tr class=\"prod-row\" id=\"$rowID\">";
            $td .= "<td class=\"upc\" data-upc=\"$upc\">$uLink</td>";
            $td .= "<td class=\"sku\">$sku</td>";
            $td .= "<td class=\"alias\">$alias</td>";
            $td .= "<td class=\"brand editable editable-brand\" data-table=\"products\"
                style=\"text-transform:uppercase;\" id=\"b$ubid\">$brand</td>";
            $td .= "<td class=\"sign-brand editable editable-brand \" data-table=\"productUser\" id=\"sb$ubid\">$signBrand</td>";
            $td .= "<td class=\"description editable editable-description\" data-table=\"products\" 
                style=\"text-transform:uppercase;\" maxlength=\"30\" id=\"d$ubid\">$description</td>";
            $td .= "<td class=\"sign-description editable editable-description \" data-table=\"productUser\" spellcheck=\"true\" id=\"sd$ubid\">$signDescription</td>";
            $td .= "<td class=\"size\">$size</td>";
            $td .= "<td class=\"units\">$units</td>";
            $td .= "<td class=\"netCost editable-cost\" data-vid=\"$vendorID\">$netCost</td>";
            $td .= "<td class=\"cost\" $ogCost>$cost</td>";
            $td .= "<td class=\"recentPurchase\" title=\"$received\">$recentPurchase</td>";
            //$td .= "<td class=\"\" title=\"\">$received</td>";
            $td .= "<td class=\"price\" $badPrice>$price</td>";
            $td .= "<td class=\"sale\"><span style=\"color: darkgreen; font-weight: bold;\">$sale</span></td>";
            $td .= "<td class=\"autoPar\">$autoPar</td>";
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
            //$td .= "<td class=\"dept\">
            //    <span class=\"dept-text\">$dept</span>
            //    <span class=\"dept-select hidden\">$deptOpts</span>
            //    </td>";
            $td .= "<td class=\"dept\">$dept</td>";
            $td .= "<td class=\"subdept\">$subdept</td>";
            $td .= "<td class=\"local\">$local</td>";
            $td .= "<td class=\"flags\">$flags</td>";
            $td .= "<td class=\"vendor\" data-vendorID=\"$vendorID\">$vendor</td>";
            $td .= "<td class=\"last_sold\">$lastSold</td>";
            $td .= "<td class=\"scaleItem\">$bycount</td>";
            $td .= "<td class=\"scalePLU\">$scalePLU</td>";
            $td .= "<td class=\"reviewed\">$reviewed</td>";
            $oper = ($costChange > 0) ? '+' : '-';
            $td .= "<td class=\"costChange\">$oper$costChange - $costChangeDate</td>";
            $td .= "<td class=\"floorSections\">$floorSections</td>";
            $td .= "<td class=\"comment\">$reviewComments</td>";
            $td .= "<td class=\"PRN\">$prn</td>";
            $td .= "<td class=\"caseCost\">$caseCost</td>";
            $td .= "<td class=\"mnote\">$mnote</td>";
            $td .= "<td class=\"notes editable editable-notes\">$notes</td>";
            $td .= "<td><span class=\"scanicon scanicon-trash scanicon-sm \"></span></td></td>";
            $td .= "<td class=\"check\"><input type=\"checkbox\" name=\"check\" class=\"row-check\" $checked/></td>";
            $td .= "</tr>";
            $textarea .= "$upc\r\n";
        
            $brand = preg_replace("/[^A-Za-z0-9 ]/", '', $brand);
            //$brand = str_replace(',', '', $brand);
            $signBrand = preg_replace("/[^A-Za-z0-9 ]/", '', $signBrand);
            //$signBrand = str_replace(',', '', $signBrand);
            $description = preg_replace("/[^A-Za-z0-9 ]/", '', $description);
            //$description = str_replace(',', '', $description);
            $signDescription = preg_replace("/[^A-Za-z0-9 ]/", '', $signDescription);
            //$signDescription = str_replace(',', '', $signDescription);
            $vendor = preg_replace("/[^A-Za-z0-9 ]/", '', $vendor);
            //$vendor = str_replace(',', '', $vendor);
            $floorSections = preg_replace("/[^A-Za-z0-9 ]/", ' & ', $floorSections);
            $flags = str_replace(",", ' & ', $flags);
            $brand = str_replace(',', '', $brand);
            $autoPar = str_replace("&#9608;", " | ", $autoPar);

            $prepCsv = strip_tags("\"$upc\", \"$sku\", \"$alias\", \"$brand\", \"$signBrand\", \"$description\", \"$signDescription\", $size, $units, $netCost, $cost, $recentPurchase, $price, $sale, $csvAutoPar, $curMargin, $margin, $diff, $rsrp, $srp, $prid, $dept, $subdept, $local, \"$flags\", \"$vendor\", $lastSold, $bycount, \"$scalePLU\", \"$reviewed\", \"$floorSections\", \"$reviewComments\", \"$prn\", $caseCost, \"$mnote\", \"$notes");
            $prepCsv = str_replace("&nbsp;", "", $prepCsv);
            $prepCsv = str_replace("\"", "", $prepCsv);
            $csv .= "$prepCsv" . "\r\n";
        }
        $textarea .= "</textarea></div>";
        $rows = $dbc->numRows($result);

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

        if ($demo == true) {
            return $csv;
        } elseif (FormLib::get('fetch') == 'true') {
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
        $unitCost = (isset($row['unitCost'])) ? $row['unitCost'] : 0;
        $received = (isset($row['receivedDate'])) ? $row['receivedDate'] : 0;

        return array($unitCost, $received);
    }

    private function getNotesOpts($dbc,$username)
    {
        $args = array($username);
        $query = $dbc->prepare("SELECT notes FROM woodshed_no_replicate.AuditScan WHERE username = ? 
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

    public function StoreSelector($storeID='storeID',$onChange='')
    {
        $select = "<select class=\"form-control\" id=\"storeSelector-$storeID\" name=\"$storeID\" onChange=\"$onChange\">";
        $dbc = scanLib::getConObj();
        $current = (isset($_SESSION['AuditReportStoreID'])) ? $_SESSION['AuditReportStoreID'] : scanLib::getStoreID();

        $prep = $dbc->prepare("SELECT storeID, description FROM Stores");
        $res = $dbc->execute($prep); 
        while ($row = $dbc->fetchRow($res)) {
            $id = $row['storeID'];
            $d = $row['description'];
            $selected = ($current == $id) ? ' selected ' : '';
            $select .= "<option value=\"$id\" $selected>$d</option>";
        }
        $select .= "</select>";

        return $select;
    }

    public function postView($demo=false)
    {
        $dbc = scanLib::getConObj();
        $FANNIE_ROOTDIR = $this->config->vars['FANNIE_ROOTDIR'];
        $username = ($un = scanLib::getUser()) ? $un : "Generic User";
        $storeID = (isset($_SESSION['AuditReportStoreID'])) ? $_SESSION['AuditReportStoreID'] : scanLib::getStoreID();
        $loaded = FormLib::get('loaded');
        $loadedHTMLSpec = htmlspecialchars($loaded);
        $scrollMode = 'on';
        if (isset($_SESSION['scrollMode'])) {
            $scrollMode = ($_SESSION['scrollMode'] == 0) ? 'on' : 'off';
        }

        if (!isset($_SESSION['columnBitSet'])) {
            // define default columns to show
            $_SESSION['columnBitSet'] = 0;
            $x = 0;
            $x |= 1 << 0;
            $x |= 1 << 1;
            $x |= 1 << 2;
            $x |= 1 << 4;
            $x |= 1 << 6;
            $x |= 1 << 8;
            $x |= 1 << 9;
            $x |= 1 << 10;
            $x |= 1 << 13;
            $x |= 1 << 14;
            $x |= 1 << 15;
            $x |= 1 << 20;
            $x |= 1 << 29;
            $x |= 1 << 30;
            $_SESSION['columnBitSet'] = $x;
        }

        $args = array($username);
        $prep = $dbc->prepare("SELECT upc FROM woodshed_no_replicate.AuditScan
            WHERE username = ? AND savedAs = 'default'");
        $res = $dbc->execute($prep, $args);
        $list = '<textarea name="list" style="display: none;">';
        while ($row = $dbc->fetchRow($res)) {
            $list .= $row['upc'] . "\r\n";
        }
        $list .= '</textarea>';

        $args = array($username);
        $prep = $dbc->prepare("SELECT savedAs, DATE(date) AS date FROM woodshed_no_replicate.AuditScan
            WHERE username = ? AND savedAs != 'default' GROUP BY savedAs ORDER BY date DESC");
        $res = $dbc->execute($prep, $args);
        $savedLists = "";
        $datalist = "<datalist id=\"savedLists\">";
        while ($row = $dbc->fetchRow($res)) {
            $date = $row['date'];
            $saved = $row['savedAs'];
            $sel = ($saved == $loaded) ? ' selected ' : '';
            $style = (strpos(strtolower($saved), 'review') !== false) ? "style=\"background-color: #FFFFCC; border: 1px solid grey;\"" : "";
            $savedLists .= "<option value=\"$saved\" $style  $sel>[$date] $saved</option>";
            $datalist .= "<option value=\"$saved\">";
        }
        $datalist .= "</datalist>";

        $vselect = '<option value="">Load Vendor Catalog</option>';
        $curVendor = FormLib::get('vendor');
        $prep = $dbc->prepare("SELECT vendorName, vendorID FROM vendors 
            WHERE vendorID NOT IN (-2,-1,1,2)
            ORDER BY vendorName ASC;");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
             $vid = $row['vendorID'];
             $vname = $row['vendorName'];
             $vselect .= "<option value='$vid'>$vname</option>";
         }

        $bselect = '<option value="">Load All By Brand</option>';
        $prep = $dbc->prepare("
            SELECT brand FROM products AS p
                INNER JOIN MasterSuperDepts AS m ON m.dept_ID=p.department
            WHERE m.super_name NOT IN ('PRODUCE')
                AND p.last_sold > NOW() - INTERVAL 30 DAY
            GROUP BY brand
        ");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            $brand = trim($row['brand']);
            $bselect .= "<option value=\"$brand\">$brand</option>";
         }


        $prep = $dbc->prepare("SELECT * FROM woodshed_no_replicate.temp");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            //echo "<div>{$row['upc']}</div>";
        }
        $tempInputVal = '';
        $countTemp = $dbc->numRows($res);
        $tempBtn = ""; $tempClass = '';
        $saveReviewBtn = '';
        $vncBtn = '';
        $checkPriceBtn = '';
        $tempBtnID = "prevent-default";
        $reviewForm = '';
        //$exportExcelForm = '';
        if ($_COOKIE['user_type'] == 2) {
            // user is admin 
            $tempClass = "btn-secondary";
            $vncBtn = '
        <div class="form-group dummy-form">
            <button class="btn btn-default btn-sm small text-secondary" id="validate-notes-cost">VNC</button> |
            <button class="btn btn-default btn-sm small text-secondary" id="hide-validated">Hide VNC\'d</button>
        </div>';
            $checkPriceBtn = '
        <!--
            <div class="form-group dummy-form">
            <button class="btn btn-default btn-sm small" id="check-prices">Check Prices</button>
            </div> -->
                ';

            // choose verbiage for review button
            if ($countTemp > 0) {
                $tempBtn = 'Close Review';
                $tempInputVal = 'close';
                $tempClass = 'btn-danger';
            } else {
                $tempBtn = 'Open Review';
                $tempInputVal = 'open';
            }
            $tempBtnID = "temp-btn";
            $reviewBtn = "<button class=\"btn $tempClass btn-sm page-control\" id=\"$tempBtnID\">$tempBtn</button>";
            $saveReviewBtn = '<button id="saveReviewList" class="btn btn-secondary btn-sm page-control">Save Review List</button>';

            $reviewForm = '
<div class="form-group dummy-form">
    <form method="post" action="AuditReport.php">
        '.$reviewBtn.'
        <input type="hidden" name="review" value="'.$tempInputVal.'"/>
        <input type="hidden" name="username" value="'.$username.'"/>
    </form>
</div>';
//        $exportColumns = '<div style=\"float: left; padding: 25px\">';
//        $colSize = round(count($this->columns) / 3);
//        $i = 0;
//        foreach ($this->columns as $col) {
//            if ($i % $colSize == 0) {
//                $exportColumns .= "</div><div style=\"float: left; padding: 24px;\">";
//            }
//            $exportColumns .= <<<HTML
//                <div>
//                    <input type="checkbox" name="export-$col" id="export-$col"/>
//                    <label for="export-$col">$col</label>
//                </div>
//HTML;
//            $i++;
//        }
//        $exportColumns .= "</div>";


//        $exportExcelForm = <<<HTML
//<div id="export-window" style="position: fixed; top: 0px; left: 0px; width: 100vw; height: 100vh; background-color: white; z-index: 999; ">
//    <div class="row">
//        <div class="col-lg-2">
//        </div>
//        <div class="col-lg-8">
//            <h4>Select Columns To Export</h4>
//                <div style="border: 1px solid grey; border-radius: 3px; height: 500px;">$exportColumns</div>
//                <div class="form-group"><input type="submit" class="btn btn-default" /></div>
//                <span title="close" style="cursor: pointer;" onClick="$('#export-window').hide();">Go Back</span>
//            <form action="AuditReport.php" method="post">
//        </div>
//        <div class="col-lg-2">
//            </form>
//        </div>
//    </div>
//</div> 
//HTML;


        } else {
            // user is not csather
        }

        $options = $this->getNotesOpts($dbc,$username);
        $noteStr = "";
        $noteStr .= "<select id=\"notes\" style=\"font-size: 10px; font-weight: normal; margin-left: 5px; border: 1px solid lightgrey\">";
        $noteStr .= "<option value=\"viewall\">View All</option>";
        foreach ($options as $k => $option) {
            $noteStr .= "<option value=\"".$k."\">".$option."</option>";
        }
        $noteStr .= "</select>";
        $nFilter = "<div style=\"font-size: 12px;\"><b>Note Filter</b>:$noteStr</div>";

        $columns = $this->columns;
        $columnCheckboxes = "<div style=\"font-size: 12px; padding: 10px;\"><b>Show/Hide Columns: </b>";
        $i = count($columns) - 1;
        foreach ($columns as $column) {
            $columnCheckboxes .= "<span class=\"column-checkbox\"><label for=\"check-$column\">$column</label> <input type=\"checkbox\" name=\"column-checkboxes\" id=\"check-$column\" data-colnum=\"$i\" value=\"$column\" class=\"column-checkbox\" checked></span>";
            $i--;
        }
        $columnCheckboxes .= "</div>";

        $adminModal = '';
        if ($_COOKIE['user_type'] == 2) {
            $adminModal = "
                            <div align=\"center\">
                                <h4 style=\"text-align: left;\">Sync Items To SMS</h4>
                                <form method=\"post\" class=\"\" action=\"http://$FANNIE_ROOTDIR/modules/plugins2.0/SMS/scan/SmsProdSyncList.php\" name=\"SmsSync\">
                                    <div class=\"form-group\">
                                        <textarea class=\"form-control\" name=\"SyncUpcs\" id=\"SyncUpcs\" rows=\"10\"></textarea>
                                    </div>
                                    <div class=\"form-group\">
                                        <button type=\"submit\" class=\"btn btn-default btn-xs\">Sync</button>
                                    </div>
                                </form>
                            </div>";
        }
        // we don't need SMS sync in 2 places right now
        $adminModal = '';

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
                                    <div class=\"form-group\" align=\"right\">
                                        <label for=\"add-delete-list\"><span style=\"font-weight: bold; color: tomato\">Delete</span> Instead of Add</label>
                                        <input type=\"checkbox\" id=\"add-delete-list\" name=\"add-delete-list\" value=1 />
                                    </div>
                                    <input type=\"hidden\" name=\"storeID\" value=\"$storeID\" />
                                    <input type=\"hidden\" name=\"username\" value=\"$username\" />
                                </form>
                            </div>
                            <div  style=\"background: repeating-linear-gradient( -45deg, white 0px, white 5px, lightgrey 6px, lightgrey 11px, white 12px);
                                padding: 10px; border-radius: 5px; \">
                                $adminModal
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


        if ($demo == true) {
            $uniqueid = uniqid();
            $file="AuditReport_$uniqueid.csv";
            //header("Content-type: application/vnd.ms-excel");
            header("Content-type: text/csv");
            header("Content-Disposition: attachment; filename=$file");
        }

        if ($demo == true) {
            echo $this->postFetchHandler($demo);
        } else {
            return <<<HTML
<div class="container-fluid">
$modal
<input type="hidden" name="keydown" id="keydown"/>
<form id="page-info" style="display: none">
    <input type="hidden" id="storeID" value="$storeID" />
    <input type="hidden" id="username" value="$username" />
</form>

<!--
<div class="form-group dummy-form">
    <button id="clearNotesInputB" class="btn btn-secondary btn-sm page-control">Clear Notes</button>
</div>
-->
<div class="form-group dummy-form">
    <button id="clearAllInputB" class="btn btn-secondary btn-sm page-control">Clear Queue</button>
</div>
<div class="form-group dummy-form">
    <button class="btn btn-secondary btn-sm page-control" data-toggle="modal" data-target="#upcs_modal">Add Items</button>
</div>
<div class="form-group dummy-form">
    $saveReviewBtn
</div>
$reviewForm
<div class="form-group dummy-form">
    <a class="btn btn-info btn-sm page-control" href="ProductScanner.php ">Scanner</a>
</div>
<div class="form-group dummy-form" style="float: right;">
    {$this->StoreSelector('storeID')}
</div>
<div style="font-family: consolas; float: right; margin: 5px; padding: 5px" id="ajax-response"></div>
<div></div>

<div class="gui-group">
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
        </div>
        $deleteList
        $datalist
    </form>
</div>

<form name="deleteListForm" method="post" action="AuditReport.php" style="display: inline-block">
    <input name="username" type="hidden" value="$username" />
    <input name="storeID" type="hidden" value="$storeID" />
    <input name="deleteList" type="hidden" value="$loadedHTMLSpec" />
</form>

<div class="gui-group">
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
</div>

<div class="gui-group">
    <form name="loadVendCat" id="loadVendCat" method="post" action="AuditReport.php" style="display: inline-block">
        <input name="username" type="hidden" value="$username" />
        <input name="storeID" type="hidden" value="$storeID" />
        <div class="form-group dummy-form">
            <select name="vendCat" class="form-control form-control-sm" placeholder="Select a Vendor Catalog">
                $vselect
            </select>
        </div>
        <div class="form-group dummy-form">
        <label for="loadFullCat" style="font-size: 14px">Load All</label>
            <input type="checkbox" name="loadFullCat" id="loadFullCat" value="1" />&nbsp;
            <button class="btn btn-default btn-sm" type="submit" id="loadCatBtn">Load</button>
        </div>
    </form>
</div>

<div class="gui-group">
    <form name="loadBrandList" id="loadBrandList" method="post" action="AuditReport.php" style="display: inline-block">
        <input name="username" type="hidden" value="$username" />
        <input name="storeID" type="hidden" value="$storeID" />
        <div class="form-group dummy-form">
            <select name="brandList" class="form-control form-control-sm" placeholder="Select a Brand">
                $bselect
            </select>
        </div>
        <div class="form-group dummy-form">
            <button class="btn btn-default btn-sm" type="submit" id="loadBrandBtn">Load</button>
        </div>
    </form>
</div>
<div class="row">
    <div class="col-lg-2">
        <div style="font-size: 12px;">
            <li><a href="AuditReport.php?exportExcel=1" download>Export to Excel (CSV)</a></li>
        </div>
    </div>
    <div class="col-lg-2">
        <div style="font-size: 12px;">
            <label for="check-pos-descript"><b>Switch POS/SIGN Descriptors</b>:&nbsp;</label>
            <input type="checkbox" name="check-pos-descript" id="check-pos-descript" class="" checked>
        </div>
    </div>
    <div class="col-lg-2">
    </div>
    <div class="col-lg-2">
    </div>
    <div class="col-lg-2">
    </div>
    <div class="col-lg-2">
        $nFilter
    </div>
</div>

$columnCheckboxes
<div class="row">
    <div class="col-lg-6">
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
                <button class="btn btn-default btn-sm small text-secondary" id="invert-show">Invert View</button>
            </div>
            $checkPriceBtn
            $vncBtn
        </div>
    </div>
    <div class="col-lg-3" >
        <div class="card" style="margin: 5px; box-shadow: 1px 1px lightgrey;">
            <div class="card-body" style="background-color: rgba(211,211,211,0.2);">
                <h6 class="card-title">Average Calculator</h6>
                    <div class="form-group">
                        <textarea rows=1 id="avgCalc" name="avgCalc" style="font-size: 12px" class="form-control small" ></textarea>
                    </div>
                    <div>
                        <p id="avgAnswer" style="font-size: 12px;"></p>
                        <p id="stdevAnswer" style="font-size: 12px;"></p>
                    </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="card" style="margin: 5px; box-shadow: 1px 1px lightgrey;" id="simpleInputCalc">
            <div class="card-body" style="background-color: rgba(211,211,211,0.2);">
                <h6 class="card-title">Simple Input Calculator 
                    <span id="hide-SIC" style="padding: 5px; padding-right: 10px; padding-left: 10px;border: 1px solid grey; font-size: 12px;
                        cursor: pointer;">
                        lock:$scrollMode</span></h6>
                <div class="row">
                    <div class="col-lg-9">
                        <input type="text" id="calculator" name="calculator" style="font-size: 12px" class="form-control small">
                    </div>
                    <div class="col-lg-3">
                        <div class="form-group">
                            <button id="clear" class="btn btn-default btn-sm small form-control" style="font-size: 10px">CL</button>
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
        $scrollMode = (isset($_SESSION['scrollMode'])) ? $_SESSION['scrollMode'] : 0;

        return <<<JAVASCRIPT
var startup = 1;
var columnSet = $config;
var tableRows = $('#table-rows').val();
var storeID = $('#storeID').val();
var username = $('#username').val();
var scrollMode = $scrollMode;
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

$("#mytable").bind('sortEnd', function(){
    stripeTable();
});

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
$('#saveReviewList').click(function() {
    var c = confirm("Save notated rows as review list?");
    if (c == true) {
        $.ajax({
            type: 'post',
            data: 'storeID='+storeID+'&username='+username+'&reviewList=true',
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

var lastCost = null;
$('.editable-cost').click(function(){
    lastCost = $(this).text();
    $(this).attr('contentEditable', 'true');
    $(this).css('font-weight', 'bold');
});
$('.editable-cost').focusout(function(){
    var cost = $(this).text();
    var upc = $(this).parent().find('.upc').attr('data-upc');
    var vendorID = $(this).attr('data-vid'); 
    var element = $(this);
    if (lastCost != cost) {
        $.ajax({
            type: 'post',
            data: 'setCost=true&upc='+upc+'&cost='+cost+'&vendorID='+vendorID,
            dataType: 'json',
            url: 'AuditReport.php',
            success: function(response)
            {
                console.log('Saved: '+response);
                if (response.saved == true) {
                    /*
                        success!
                    */
                    element.css('background-color', 'lightgreen');
                    setTimeout(function(){
                        element.css('background-color', 'transparent');
                    }, 1000);
                    // check the associated checkbox 
                    let checkbox = element.parent().find('input[type=checkbox]');
                    console.log(checkbox.attr('name'));
                    //checkbox.prop('checked', true);
                    checkbox.trigger('click');
                } else {
                    /*
                        failure
                    */
                    element.css('background-color', 'tomato');
                    setTimeout(function(){
                        element.css('background-color', 'transparent');
                    }, 1000);
                }
            },
        });
    }
    $(this).attr('contentEditable', 'false');
    $(this).css('font-weight', 'normal');
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

$('.editable-notes').each(function(){
    $(this).attr('contentEditable', true);
    $(this).attr('spellCheck', false);
});
$('.editable-description').each(function(){
    $(this).attr('contentEditable', true);
    //$(this).attr('spellCheck', false);
});
$('.editable-description.sign-description').each(function(){
    $(this).attr('contentEditable', true);
});
$('.editable-brand').each(function(){
    $(this).attr('contentEditable', true);
    //$(this).attr('spellCheck', false);
});
$('.editable-brand.sign-brand').each(function(){
    $(this).attr('contentEditable', true);
    $(this).attr('spellCheck', false);
});
var lastBrand = null;
$('.editable-brand').click(function(){
    lastBrand = $(this).text();
});
$('.editable-brand').focus(function(){
    lastBrand = $(this).text();
});
$('.editable-brand').focusout(function(){
    var table = $(this).attr('data-table');
    var upc = $(this).parent().find('td.upc').attr('data-upc');
    var brand = $(this).text();
    var elemID = $(this).attr('id');
    console.log(elemID);
    if (brand != lastBrand) {
        brand = encodeURIComponent(brand);
        $.ajax({
            type: 'post',
            data: 'setBrand=true&upc='+upc+'&brand='+brand+'&table='+table,
            dataType: 'json',
            url: 'AuditReport.php',
            success: function(response)
            {
                if (response.saved == 'true') {
                    $('#ajax-response').show();
                    $('#ajax-response').text("Save Error").css('background-color', '#FF6347').fadeOut(1500);
                } else {
                    $('#ajax-response').show();
                    $('#ajax-response').text("Save Success").css('background-color', '#AFE1AF').fadeOut(1500);
                }
            },
        });
    }
});
var lastDescription = null;
$('.editable-description').click(function(){
    lastDescription = $(this).text();
});
$('.editable-description').focus(function(){
    lastDescription = $(this).text();
});
$('.editable-description').focusout(function(){
    var table = $(this).attr('data-table');
    var upc = $(this).parent().find('td.upc').attr('data-upc');
    var description = $(this).text();
    var elemID = $(this).attr('id');
    if (description != lastDescription) {
        console.log(lastDescription+','+description);
        description = encodeURIComponent($(this).text());
        $.ajax({
            type: 'post',
            data: 'setDescription=true&upc='+upc+'&description='+description+'&table='+table,
            dataType: 'json',
            url: 'AuditReport.php',
            success: function(response)
            {
                if (response.saved == 'true') {
                    $('#ajax-response').show();
                    $('#ajax-response').text("Save Error").css('background-color', '#FF6347').fadeOut(1500);
                } else {
                    $('#ajax-response').show();
                    $('#ajax-response').text("Save Success").css('background-color', '#AFE1AF').fadeOut(1500);
                }
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
        strpercent += '<span style="color: lightgreen; border: 1px solid grey;">&#9608;</span>';
    }
    for (i; i < 100; i += 10) {
        strpercent += '<span style="color: grey; border: 1px solid grey;">&#9608;</span>';
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

$('#invert-show').click(function(){
    $('tr.prod-row').each(function(){
        var visible = $(this).is(':visible');
        if (visible) {
            $(this).hide();
        } else {
            $(this).show();
        }
    });
});

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
    var arr = $('#calculator').val();
    arr = arr.replace('$', '');
    arr = arr.replace('CS', '');
    arr = arr.replace(/\s+/g,'');
    arr = arr.split(" ");
    if (e.keyCode == 13) {
        // Enter key pressed
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
    // find average
    let text = $(this).val();
    let args = text.split('\\n');
    let total = 0;
    for (let i=0; i < args.length; i++) {
        total += parseFloat(args[i], 10);
    }
    let answer = total / args.length;
    let mean = answer;

    // find stdev
    let xi = 0;
    for (let i=0; i < args.length; i++) {
        let x = parseFloat(args[i]); 
        let p = x - mean;
        p = Math.pow(p, 2);
        xi += p;
    }
    let xi_mean = xi / (args.length - 1);
    let stddev = Math.sqrt(xi_mean)

    answer = 'AVERG: ' + answer; 
    stddev = "STDEV: " + stddev;
    if (answer) {
        $('#avgAnswer').text(answer);
        $('#stdevAnswer').text(stddev);
    } else {
        $('#avgAnswer').text('');
        $('#stdevAnswer').text('');
    }

});

$('#prevent-default').click(function(e) {
    e.preventDefault();
});

$('#loadCatBtn').on('click', function(){
    c = confirm("Are you sure you would like to load this catalog? This will replace the current list.");
    return c;
});
$('#loadBrandBtn').on('click', function(){
    c = confirm("Are you sure you would like to load all from this brand? This will replace the current list.");
    return c;
});

//var scrollMode = 0;
$(window).scroll(function () {
    var scrollTop = $(this).scrollTop();
    if (scrollMode == 0) {
        if (scrollTop > 400) {
            $('#simpleInputCalc')
                .css('position', 'fixed')
                .css('top', '0px')
                .css('right', '0px')
                .css('background-color', 'rgba(255,255,255,1)')
                .css('width', '309px');
        } else {
            $('#simpleInputCalc')
                .css('position', 'relative')
                .css('background-color', 'rgba(255,255,255,1)')
                .css('width', '309px');
        }
    }
});

$('#hide-SIC').click(function(){
    if (scrollMode == 0) {
        scrollMode = 1;
        $(this).text('lock:off');
        $('#simpleInputCalc')
            .css('position', 'relative')
            .css('background-color', 'rgba(255,255,255,1)');
    } else {
        scrollMode = 0;
        $(this).text('lock:on');
    }
    $.ajax({
        type: 'post',
        data: 'scrollMode='+scrollMode,
        url: 'AuditReport.php',
        success: function(response) {
            console.log('set scrollMode success');
        },
        error: function(response) {
            console.log('set scrollMode error');
        }
    });
});

$('#validate-notes-cost').click(function(){
    $('tr').each(function(){
        if ($(this).hasClass('prod-row')) {
            var col1 = $(this).find('td.netCost').text();
            col1 = parseFloat(col1);
            var col2 = $(this).find('td.notes').text();
            col2 = parseFloat(col2);
            if (col1 == col2) {
                $(this).css('background-color', 'tomato')
                    .addClass('validated');
            }
            //console.log('col1: '+col1+', col2: '+col2);
        }
    });
});
$('#hide-validated').click(function(){
    $('tr.validated').each(function(){
        $(this).hide();
    });
});

$( function() {
    $('#simpleInputCalc').draggable();
});

$('#storeSelector-storeID').css('border', '1px solid brown');
$('#storeSelector-storeID').change(function(){
    var id = $(this).find(':selected').val();
    $.ajax({
        type: 'post',
        data: 'setStoreID='+id,
        url: 'AuditReport.php',
        success: function(re) {
            console.log(re);
            location.reload();
        },
        error: function(re) {
            console.log('AJAX ERROR: '+response)
        },
    });
});

$('.btn-mnote').click(function(){
    let newval = $(this).closest('td').next().text();
    let newvalElm = $(this).closest('td').next();
    let oldval = $(this).parent().parent().find('td.netCost').text();
    let oldvalElm = $(this).parent().parent().find('td.netCost');

    oldvalElm.focus();
    oldvalElm.text(newval);
    oldvalElm.focusout();
    newvalElm.focus();
    newvalElm.text('');
    newvalElm.focusout();
});
JAVASCRIPT;
    }

    public function cssContent()
    {
        return <<<HTML
div.gui-group {
    background-color: #F2F2F2;
    display: inline-block;
    height: 42px;
    border-radius: 3px;
    margin: 5px;
}
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

    <li>Head Buttons (Top Grey & Teal Buttons)</li>
    <ul>
        <li><strong>Clear Queue</strong> Removes all items from the table.</li>
        <li><strong>Add Items</strong> Opens a module to paste a list of UPCs to load (or remove) to (or from) list.</li>
        <li><strong>Scanner (Teal Button)</strong> Navigates user to the Scanner module (leaves this page).</li>
        <li><strong>[Admin Only] Save Review List</strong> Will save a list (to Saved Lists) of all current items that
            have <b>notes</b> entered, along with those notes.</li>
        <li><strong>[Admin Only] Open Review</strong> Takes a snapshot of the costs of items loaded. Once initiated, 
            the button will change to <strong>Close Review</strong> which will process the changes in cost and record 
            them in the operational database cost change table (productCostChanges).
            <b>Only one user can use the Review function at a time</b></li>
    </ul>
    <li>Definition of Columns</li>
    <p>Each checkbox in the <strong>Show/Hide Columns</strong> correlates with a column to show or hide in the Audit Report table.</p>
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
        <li><strong>Sale</strong> Current sale price of item, if any. <b>Note </b>that this column will show only the sale price
            for the selected store. </li>
        <li><strong>autoPar</strong> Automated PAR (average of sales over 90 days), multiplied by 7 (average of item(s) sold in one week). 
            <ul> 
                <li><u>Borders:</u></li>
                <li><b><span style="color: lightblue">Blue</span></b> item has sales as of yesterday</li>
                <li><b><span style="color: lightgreen">Green</span></b> item as sold in past 20 days</li>
                <li><b><span style="color: orange">Yellow</span></b> item has not sold in less than 20 days</li>
                <li><b><span style="color: tomato">Red</span></b> item last sold > 30 days</li>
                <li><b><span style="color: darkred">Dark Red</span></b> it has been 60+ days since this item has sold</li>
            </ul>
        </li>
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
        <li><strong>*</strong> Columns with an asterisk in this list are editable fields.</li>
    </ul>
    <li>Forms & Functions (Grey Boxes)</li>
    <ul>
        <li><strong>Saved Lists</strong> Load a previously saved list of items. If a list is already loaded, there will also 
            be an option to remove this list from Saved Lists.</li>
        <li><strong>Save List As</strong> Save the current list of items.</li>
        <li><strong>Load Vendor Catalog</strong> Loads an entire vendor catalog. By default, only items in use (by at least one store)
            will load. If the <b>Load All</b> checkbox is checked, the entire catalog will be loaded including out-of-use items.</li>
        <li><strong>Load All By Brand</strong> Loads all items with the selected brand name, regardless of in-use status.</li>
    </ul>
    <li>Button Filters</li>
        <ul>
            <li><strong>View Unchecked</strong> Will show only column in table that are not checked. ("checked" refers to the status
                of the checkboxes at the end of each row).</li>
            <li><strong>View Checked</strong> Shows only checked rows.</li>
            <li><strong>View All</strong> Shows all rows, regardless of checkboxes.</li>
            <li><strong>Invert View</strong> inverts shown & hidden rows.</li>
            <li><strong>[Admin Only] VNC</strong> compares values entered in <b>notes</b> column and <b>netCost</b>.
                Every row with a match will be highlighted.</li>
            <li><strong>[Admin Only] Hide VNC</strong> Hides VNC highlighted rows.</li>
        </ul>
    <li>Calculators</li>
    <ul>
        <li><strong>Average Calculator</strong> Paste a list of numbers here (one number per line) to get the average  
            and standard deviation. There must be no blank lines for this calculator to function.</li>
        <li><strong>Simple Input Calculator</strong> Enter data as <number> <function> <number> and hit enter to calculate.
            Pressing the <b>CL</b> button will clear shown calculations.  eg (enter as) 1.23 * 4.56 <enter></li>
    </ul>
    <li>Column Filters</li>
    <p>Underneath the column header row is a row of blank cells. Enter search criteria in these cells to filter the corresponding
        column by the string entered.</p>
</ul>
HTML;
    }

}
WebDispatch::conditionalExec();
