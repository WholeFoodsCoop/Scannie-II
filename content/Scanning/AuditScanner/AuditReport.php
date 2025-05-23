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
if (!class_exists('VendorPricingLib')) {
    include(__DIR__.'/../../../common/lib/VendorPricingLib.php');
}
class AuditReport extends PageLayoutA
{

    public $columns = array('check', 'upc', 'sku', 'alias', 'likeCode', 'brand', 'sign-brand', 'description', 
        'sign-description', 'size', 'uom', 'units', 'netcost', 'cost', 'vcost', 'recentPurchase',
        'price', 'sale', 'autoPar', 'margin_target_diff', 'rsrp', 'srp', 'prid', 'prt', 'tax', 'dept', 'subdept',
        'superdept', 
        'local', 'flags', 'vendor', 'last_sold', 'scaleItem', 'scalePLU', 'tare', 'mnote', 'notes', 'reviewed', 
        'costChange', 'floorSections', 'comment', 'PRN', 'caseCost'); 

    public $taxes = array('None', 'Regular', 'Deli', 'Cannabis');

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
        $this->__routes[] = 'post<setSize>';
        $this->__routes[] = 'post<setUom>';
        $this->__routes[] = 'post<setDescription>';
        $this->__routes[] = 'post<setDept>';
        $this->__routes[] = 'post<setCost>';
        $this->__routes[] = 'post<setVendorCost>';
        $this->__routes[] = 'post<setNotes>';
        $this->__routes[] = 'post<checked>';
        $this->__routes[] = 'post<review>';
        $this->__routes[] = 'post<columnSet>';
        $this->__routes[] = 'post<saveAs>';
        $this->__routes[] = 'get<loadList>';
        $this->__routes[] = 'post<deleteList>';
        $this->__routes[] = 'post<vendCat>';
        $this->__routes[] = 'post<brandList>';
        $this->__routes[] = 'post<setStoreID>';
        $this->__routes[] = 'get<exportExcel>';
        $this->__routes[] = 'get<exportCsv>';
        $this->__routes[] = 'post<setPRN>';
        $this->__routes[] = 'post<updatesrps>';
        $this->__routes[] = 'post<visrpnotes>';
        $this->__routes[] = 'post<viclearnotes>';
        $this->__routes[] = 'post<roundPriceNotes>';
        $this->__routes[] = 'post<genericupc>';
        $this->__routes[] = 'post<genericsku>';
        $this->__routes[] = 'post<genericnewsrp>';
        $this->__routes[] = 'post<updatefuturecosts>';
        $this->__routes[] = 'post<updateResetSrps>';
        $this->__routes[] = 'post<clearItemData>';
        $this->__routes[] = 'post<setPriceRuleDetails>';
        $this->__routes[] = 'post<setProductCosts>';
        $this->__routes[] = 'post<setVendorID>';
        $this->__routes[] = 'post<getFamilyItems>';
        $this->__routes[] = 'post<lpad>';
        $this->__routes[] = 'post<sessionNotepad>';
        $this->__routes[] = 'post<createdprn>';
        $this->__routes[] = 'post<udc>';
        $this->__routes[] = 'post<uncheckall>';
        $this->__routes[] = 'post<savenotes>';
        $this->__routes[] = 'post<lastKnownPrice>';
        $this->__routes[] = 'post<setprndiff>';

        return parent::preprocess();
    }

    public function postSetprndiffHandler()
    {
        $dbc = ScanLib::getConObj();
        $username = FormLib::get('username');

        $prep = $dbc->prepare("
            UPDATE woodshed_no_replicate.AuditScan AS a
                INNER JOIN products AS p ON p.upc=a.upc
            SET a.PRN = ROUND(a.notes - p.normal_price, 2)
            WHERE username = ? 
                AND savedAs = 'default'
        ");
        $res = $dbc->execute($prep, array($username));

        return false;
    }

    public function postLastKnownPriceHandler()
    {
        $dbc = ScanLib::getConObj();
        $username = FormLib::get('username');
        $json = array();
        $json['errors'] = '';
        $upcs = array();

        $prep = $dbc->prepare("
            SELECT upc
            FROM woodshed_no_replicate.AuditScan
            WHERE username = ? 
                AND savedAs = 'default'
        ");
        $res = $dbc->execute($prep, array($username));
        while ($row = $dbc->fetchRow($res)) {
            $upcs[$row['upc']] = 0; 
        }
        if ($dbc->error()) {
            $json['errors'] .= "Error: ".$dbc->error();
        }

        $prep = $dbc->prepare("SELECT upc, price
            FROM prodUpdate 
            WHERE upc = ?
                AND  price > 0 
            ORDER BY modified DESC
            LIMIT 1;");

        $updateP = $dbc->prepare("UPDATE woodshed_no_replicate.AuditScan
            SET notes = ?
            WHERE upc = ?
                AND username = ?
                AND savedAs = 'default'
            ");

        foreach ($upcs as $upc => $na) {
            $res = $dbc->execute($prep, array($upc));
            $row = $dbc->fetchRow($res);
            $price = $row['price'];

            $updateR = $dbc->execute($updateP, array($price, $upc, $username));
        }

        $json['test'] = "plew plew plew";
        echo json_encode($json);

        return false;
    }

    public function postSavenotesHandler()
    {
        $dbc = ScanLib::getConObj();
        $username = FormLib::get('username');
        $data = FormLib::get('notes');
        $data = json_decode($data);
        $json = array();
        $json['errors'] = '';

        $prep = $dbc->prepare("
            UPDATE woodshed_no_replicate.AuditScan
            SET notes = ? 
            WHERE upc = ?
                AND username = ?
                AND savedAs = 'default'
        ");

        $dbc->startTransaction();
        foreach ($data as $upc => $note) {
            $res = $dbc->execute($prep, array($note, $upc, $username));
            if ($dbc->error()) {
                $json['errors'][] = $dbc->error();
            }
        }
        $dbc->commitTransaction();

        $json['test'] = "$username";
        echo json_encode($json);

        return false;
    }

    public function postUncheckallHandler()
    {
        $dbc = ScanLib::getConObj();
        $username = FormLib::get('username');
        $json = array();
        $json['errors'] = '';

        $prep = $dbc->prepare("
            UPDATE woodshed_no_replicate.AuditScan
            SET checked = NULL
            WHERE username = ?
                AND savedAs = 'default'
        ");
        $res = $dbc->execute($prep, array($username));
        $json['errors'] .= $dbc->error();

        $json['test'] = "$username";
        echo json_encode($json);

        return false;
    }

    public function postUdcHandler()
    {
        $dbc = ScanLib::getConObj();
        $username = FormLib::get('username');
        $newCost = FormLib::get('newCost');
        $upc = FormLib::get('upc');
        $json = array();
        $json['errors'] = '';

        $mod = new DataModel($dbc);
        //$userID = $mod->name2id($username);
        $json['saved'] = $mod->setCost($upc, $newCost, $vendorID, $username);

        $args = array($upc, $username);
        $prep = $dbc->prepare("
            UPDATE woodshed_no_replicate.AuditScan
            SET notes = null, checked = 1
            WHERE upc = ?
                AND username = ?
                AND savedAs = 'default'
        ");
        $res = $dbc->execute($prep, $args);
        $json['errors'] .= $dbc->error();

        $json['test'] = "$username $newCost $upc";
        echo json_encode($json);

        return false;
    }

    public function postSessionNotepadHandler()
    {
        $text = FormLib::get('sessionNotepad', false);
        $username = FormLib::get('username');

        $_SESSION['notepad'.$username] = $text;

        return true;
    }

    public function postCreatedprnHandler()
    {
        $dbc = ScanLib::getConObj();
        $username = FormLib::get('username');

        $args = array($username);
        $prep = $dbc->prepare("
            UPDATE products p
                INNER JOIN woodshed_no_replicate.AuditScan a ON a.upc=p.upc
            SET a.PRN = DATE(p.created)
            WHERE a.username=?
                AND a.savedAs='default'
        ");
        $res = $dbc->execute($prep, $args);

        echo $dbc->error();

        return false;
    }

    public function postLpadHandler()
    {
        $dbc = ScanLib::getConObj();

        $prep = $dbc->prepare("UPDATE GenericUpload SET upc = LPAD(SUBSTR(upc,1,12),13,'0');");
        $res = $dbc->execute($prep);

        echo $dbc->error();

        return false;
    }

    public function postSetVendorIDHandler()
    {
        $vendorID = FormLib::get('vendorID');
        $_SESSION['currentVendor'] = $vendorID;

        return false;
    }

    public function postGetFamilyItemsHandler()
    {
        $dbc = ScanLib::getConObj();
        $upc = FormLib::get('upc');
        $family = substr($upc, 3, 5);

        $args = array($upc);
        $prep = $dbc->prepare("SELECT brand, size FROM products WHERE upc = ? GROUP BY upc");
        $res = $dbc->execute($prep, $args);
        $row = $dbc->fetchRow($res);

        $td = "<table class=\"table table-bordered table-sm small\">
            <thead style=\"background: linear-gradient(lightgrey, #DCDCDC, lightgrey)\"><th>UPC</th><th>Brand</th><th>Description</th><th>Size</th><th>Cost</th><th>Price</th></thead><tbody>";
        $args = array($row['brand'], $row['size']);
        $prep = $dbc->prepare("
            SELECT upc, brand, description, size, cost, normal_price
            FROM products
            WHERE brand = ? 
                AND upc LIKE '%$family%'
                AND size LIKE ? 
            GROUP BY upc;
        ");
        $res = $dbc->execute($prep, $args);
        $i=0;
        while ($r = $dbc->fetchRow($res)) {
            $stripe = ($i % 2 == 0) ? 'stripe2' : '';
            $td .= sprintf("<tr class=\"$stripe\"><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></td>",
                $r['upc'],
                $r['brand'],
                $r['description'],
                $r['size'],
                $r['cost'],
                $r['normal_price']
            );
            $i++;
        }
        $td .= "</tbody></table>";

        echo $dbc->error();
        echo $td;
        //echo "yeehaw!";

        return false;
    }

    public function postSetPriceRuleDetailsHandler()
    {
        $dbc = ScanLib::getConObj();
        $username = FormLib::get('username');

        $args = array($username);
        $prep = $dbc->prepare("
            UPDATE PriceRules r
                INNER JOIN products p ON p.price_rule_id=r.priceRuleID
                INNER JOIN woodshed_no_replicate.AuditScan a ON a.upc=p.upc
            SET r.details = a.notes
            WHERE a.username=?
                AND a.savedAs='default'
                AND LENGTH(a.notes) > 0
        ");
        $res = $dbc->execute($prep, $args);

        echo $dbc->error();

        return false;
    }

    public function postSetProductCostsHandler()
    {
        $dbc = ScanLib::getConObj();
        $username = FormLib::get('username');

        $args = array($username);
        $prep = $dbc->prepare("
            UPDATE products p
                INNER JOIN woodshed_no_replicate.AuditScan a ON a.upc=p.upc
            SET p.cost = a.notes
            WHERE a.username=?
                AND a.savedAs='default'
                AND notes > 0
        ");
        $res = $dbc->execute($prep, $args);

        echo $dbc->error();

        return false;
    }

    public function postClearItemDataHandler()
    {
        $items = array();
        $dbc = ScanLib::getConObj();
        $username = FormLib::get('username');
        $storeID = FormLib::get('storeID');

        $args = array($username, $storeID);
        $prep = $dbc->prepare("
            UPDATE products p 
                INNER JOIN woodshed_no_replicate.AuditScan a
                    ON a.upc=p.upc
            SET p.brand = NULL, p.description = NULL, p.cost = NULL, p.normal_price = NULL,
                p.default_vendor_id = NULL, p.department = NULL, p.subdept = NULL, p.tax = 0,
                p.numflag = 0, p.auto_par = 0, p.price_rule_id = 0
            WHERE a.username=?
                AND a.storeID=?
                AND a.savedAs='default'
        ");
        $res = $dbc->execute($prep, $args);

        $prep = $dbc->prepare("SELECT upc FROM woodshed_no_replicate.AuditScan
            WHERE username=? AND storeID=? AND savedAs='default'");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $items[] = $row['upc'];
        }

        list($args, $inStr) = $dbc->safeInClause($items);
        $prep = $dbc->prepare("DELETE FROM vendorItems WHERE upc IN ($inStr)");
        $res = $dbc->execute($prep, $args);

        $args = array($username, $storeID);
        $prep = $dbc->prepare("
            UPDATE productUser p 
                INNER JOIN woodshed_no_replicate.AuditScan a
                    ON a.upc=p.upc
            SET p.brand = NULL, p.description = NULL
            WHERE a.username=?
                AND a.storeID=?
                AND a.savedAs='default'
        ");
        $res = $dbc->execute($prep, $args);

        $args = array($username, $storeID);
        $prep = $dbc->prepare("
            UPDATE scaleItems p 
                INNER JOIN woodshed_no_replicate.AuditScan a
                    ON a.upc=p.plu
            SET p.itemdesc = NULL, p.exceptionprice = NULL, p.weight = NULL, p.bycount = NULL, p.tare = NULL,
                p.shelflife = NULL, p.netWeight = NULL, p.text = NULL, p.reportingClass = NULL, p.label = NULL,
                p.graphics = NULL, p.modified = NULL, p.linkedPLU = NULL, p.mosaStatement = NULL, p.originText = NULL,
                p.reheat = NULL
            WHERE a.username=?
                AND a.storeID=?
                AND a.savedAs='default'
        ");
        $res = $dbc->execute($prep, $args);

        $args = array($username, $storeID);
        $prep = $dbc->prepare("
            DELETE FROM VendorAliases va
            INNER JOIN woodshed_no_replicate.AuditScan a
                ON a.upc=va.upc
            WHERE a.username=?
                AND a.storeID=?
                AND a.savedAs='default'
        ");
        $res = $dbc->execute($prep, $args);

        echo $dbc->error();

        echo "the correct method is being called";

        return false;
    }

    public function postUpdateResetSrpsHandler()
    {
        $dbc = ScanLib::getConObj();
        $username = FormLib::get('username');
        $storeID = FormLib::get('storeID');
        $vendorID = FormLib::get('vendorID');

        $args = array($username, $storeID, $vendorID);
        $prep = $dbc->prepare("UPDATE vendorItems v
                INNER JOIN products p ON p.upc=v.upc
                INNER JOIN woodshed_no_replicate.AuditScan a ON a.upc=p.upc
            SET v.srp = p.normal_price
            WHERE a.username=?
                AND a.storeID=?
                AND a.savedAs='default'
                AND v.vendorID=?");
        $res = $dbc->execute($prep, $args);

        echo $dbc->error();

        echo "the correct method is being called";

        return false;
    }

    /*
        post update future costs handler
        notes > AFVI.futureCosts ON vendorID, startDate
        update futureReview
    */
    public function postUpdatefuturecostsHandler()
    {
        $dbc = ScanLib::getConObj();
        $username = FormLib::get('username');
        $vendorID = FormLib::get('vendorID');
        $startDate = FormLib::get('startDate');

        $args = array($vendorID, $startDate, $vendorID, $username);
        $prep = $dbc->prepare("INSERT INTO FutureVendorItems (upc, sku, vendorID, futureCost, startDate, srp) 
            SELECT c.upc, v.sku, ?, REPLACE(c.notes, \"$\", \"\"), ?, 0 
            FROM woodshed_no_replicate.AuditScan AS c 
                INNER JOIN products p ON p.upc=c.upc
                LEFT JOIN vendorItems v ON v.upc=p.upc
                    AND v.vendorID=?
            WHERE p.cost <> c.notes
                AND c.notes <> 0
                AND c.notes != ''
                AND c.username=?
                AND c.savedAs='default'
            GROUP BY c.upc;");
        $res = $dbc->execute($prep, $args);

        echo $dbc->error();

        $args = array($vendorID, $startDate, $username);
        $prep = $dbc->prepare("INSERT INTO woodshed_2.futureReview (upc, vendorID, reviewDate)
            SELECT upc, ?, ? FROM woodshed_no_replicate.AuditScan 
            WHERE username=?
                AND savedAs='default'
                AND notes IS NOT NULL
                AND notes != ''
                AND notes <> 0");
        $res = $dbc->execute($prep, $args);

        echo $dbc->error();

        return false;
    }

    public function postGenericnewsrpHandler()
    {
        $dbc = ScanLib::getConObj();
        $username = FormLib::get('username');

        $args = array($username);
        $prep = $dbc->prepare("UPDATE woodshed_no_replicate.AuditScan a INNER JOIN GenericUpload g ON g.upc=a.upc SET a.notes=REPLACE(g.NewSRP, '$', '') WHERE a.username=? AND a.savedAs='default';");
        $res = $dbc->execute($prep, $args);

        // catch items if not padded 
        $args = array($username);
        $prep = $dbc->prepare("UPDATE woodshed_no_replicate.AuditScan a INNER JOIN GenericUpload g ON LPAD(SUBSTR(g.upc,1,12),13,'0')=a.upc SET a.notes=REPLACE(g.NewSRP, '$', '') WHERE a.username=? AND a.savedAs='default';");
        $res = $dbc->execute($prep, $args);

        return false;
    }

    public function postGenericupcHandler()
    {
        $dbc = ScanLib::getConObj();
        $username = FormLib::get('username');

        $args = array($username);
        $prep = $dbc->prepare("UPDATE woodshed_no_replicate.AuditScan a INNER JOIN GenericUpload g ON g.upc=a.upc SET a.notes=REPLACE(g.cost, '$', '') WHERE a.username=? AND a.savedAs='default';");
        $res = $dbc->execute($prep, $args);

        // catch items if not padded 
        $args = array($username);
        $prep = $dbc->prepare("UPDATE woodshed_no_replicate.AuditScan a INNER JOIN GenericUpload g ON LPAD(SUBSTR(g.upc,1,12),13,'0')=a.upc SET a.notes=REPLACE(g.cost, '$', '') WHERE a.username=? AND a.savedAs='default';");
        $res = $dbc->execute($prep, $args);

        return false;
    }

    public function postGenericskuHandler()
    {
        $dbc = ScanLib::getConObj();
        $username = FormLib::get('username');
        $vendorID = FormLib::get('vendorID');

        $args = array($username, $vendorID);
        $prep = $dbc->prepare("UPDATE woodshed_no_replicate.AuditScan a
            INNER JOIN vendorItems v ON v.upc=a.upc
            INNER JOIN GenericUpload g ON g.sku=v.sku
            SET a.notes=REPLACE(g.cost, '$', '')
            WHERE a.username=?
                AND v.vendorID=?
                AND a.savedAs='default';");
        $res = $dbc->execute($prep, $args);

        $args = array($username, $vendorID);
        $prep = $dbc->prepare("UPDATE woodshed_no_replicate.AuditScan a
            INNER JOIN vendorItems v ON v.upc=a.upc
            INNER JOIN GenericUpload g ON CAST(g.sku AS UNSIGNED)=CAST(v.sku AS UNSIGNED) 
            SET a.notes=REPLACE(g.cost, '$', '')
            WHERE a.username=?
                AND v.vendorID=?
                AND a.savedAs='default'
                AND CAST(g.sku AS UNSIGNED) <> 0
                AND CAST(v.sku AS UNSIGNED) <> 0
            ");
        $res = $dbc->execute($prep, $args);

        return false;
    }

    public function postRoundPriceNotesHandler()
    {
        $items = array();
        $dbc = ScanLib::getConObj();
        $username = FormLib::get('username');
        $rounder = new PriceRounder();

        $args = array($username);
        $prep = $dbc->prepare("SELECT upc, notes FROM woodshed_no_replicate.AuditScan 
            WHERE username=? AND savedAs='default'");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $upc = $row['upc'];
            $raw = $row['notes'];
            $items[$upc] = $rounder->round($raw);
        }

        $updateP = $dbc->prepare("UPDATE woodshed_no_replicate.AuditScan 
            SET notes = ? WHERE upc = ? AND username = ? AND savedAs = 'default'");

        $dbc->startTransaction();
        foreach ($items as $upc => $srp) {
            $updateA = array($srp, $upc, $username);
            $dbc->execute($updateP, $updateA);
        }
        $dbc->commitTransaction();

        $er = ($dbc->error()) ? $dbc->error() : '';
        echo $er;

        return false;
    }

    public function getShippingMarkup($vendorID)
    {
        $dbc = ScanLib::getConObj();
        $prep = $dbc->prepare("SELECT shippingMarkup
            FROM vendors WHERE vendorID = ?");
        $res = $dbc->execute($prep, array($vendorID));
        $row = $dbc->fetchRow($res); 
        $markup = $row['shippingMarkup'];
        
        return $markup;
    }

    public function postUpdatesrpsHandler()
    {
        $username = FormLib::get('username');
        $vendorID = FormLib::get('vendorID');
        $storeID = FormLib::get('storeID');
        $includePR = FormLib::get('includePR', 0);
        $items = array();

        $rounder = new PriceRounder();
        $dbc = ScanLib::getConObj();
        
        $shippingMarkup = $this->getShippingMarkup($vendorID);
        $query = VendorPricingLib::recalcVendorSrpsQ($shippingMarkup, $includePR);
        //$query = VendorPricingLib::recalcVendorSrpsQ();

        $auditUpcs = array();
        $prep = $dbc->prepare("SELECT upc FROM woodshed_no_replicate.AuditScan WHERE username=?
            AND savedAs='default'");
        $res = $dbc->execute($prep, array($username));
        while ($row = $dbc->fetchRow($res)) {
            $auditUpcs[] = $row['upc'];
        }

        $args = array($vendorID);
        $prep = $dbc->prepare($query);
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $upc = $row['upc'];
            $cost = $row['cost'];
            $srp = $row['rawSRP'];
            //$srp = $row['NewSRP'];

            //echo 'upc: '.$upc.', rawSRP: '.$srp;
            if (($srp - substr($srp, 0, 4)) == 0) {
                $srp = substr($srp, 0, 4);
            }
            $srp = $rounder->round($srp);
            //echo 'roundedSRP: '.$srp;
             

            $normal_price = $row['normal_price'];
            if ($cost != 0 && in_array($upc, $auditUpcs)) {
                $items[$upc]['srp'] = $srp;
                $items[$upc]['normal_price'] = $normal_price;
            }
        }

        $ret .= $dbc->error();

        $auditP = $dbc->prepare("
            UPDATE woodshed_no_replicate.AuditScan AS a
            SET notes=?
            WHERE a.upc=?
                AND a.username=?
                AND a.storeID=?
                AND a.savedAs='default' 
        ");
        // Vendor Items srps should be updated when the price actually changes, not before
        //$vendorItemsP = $dbc->prepare("UPDATE vendorItems SET srp = ?, modified = NOW() WHERE upc = ? AND vendorID = ?");

        $dbc->startTransaction();
        foreach ($items as $upc => $row) {
            if (abs($row['normal_price'] - $row['srp']) > 0.01) {
                $auditA = array(
                    $row['srp'],
                    $upc,
                    $username,
                    $storeID
                );
                $dbc->execute($auditP, $auditA);
                $ret .= $dbc->error();
            }

            //$vendorItemsA = array(
            //    $row['srp'],
            //    $upc,
            //    $vendorID
            //);
            //$dbc->execute($vendorItemsP, $vendorItemsA);
            //$ret .= $dbc->error();

        }
        $dbc->commitTransaction();

        echo $ret . ' END';
        return false; 
    }

    public function postViclearnotesHandler()
    {
        $username = FormLib::get('username');
        $storeID = FormLib::get('storeID');
        $dbc = ScanLib::getConObj();

        $args = array($username, $storeID);
        $prep = $dbc->prepare("
            UPDATE woodshed_no_replicate.AuditScan AS a
            SET notes=null
            WHERE a.username=?
                AND a.storeID=?
                AND a.savedAs='default' ");
        $res = $dbc->execute($prep, $args);

        echo "Notes cleared";
        return false;
    }

    public function postVisrpnotesHandler()
    {
        $rounder = new PriceRounder();
        $username = FormLib::get('username');
        $storeID = FormLib::get('storeID');
        $dbc = ScanLib::getConObj();
        $items = array();

        $ret = '';

        $listA = array($username);
        $listP = $dbc->prepare("
            SELECT
                a.upc,
                ROUND(
                    CASE
                        WHEN c.margin IS NOT NULL THEN p.cost / (1 - c.margin) ELSE
                            CASE WHEN vd.margin IS NOT NULL AND vd.margin <> 0 THEN p.cost / (1 - vd.margin) ELSE
                                CASE WHEN b.margin IS NOT NULL THEN p.cost / (1 - b.margin) ELSE p.cost / (1 - 0.40) 
                            END
                        END
                    END, 3) AS srp,
                ROUND(
                    CASE
                        WHEN c.margin IS NOT NULL THEN (p.cost + (p.cost * v.shippingMarkup)) / (1 - c.margin) ELSE
                            CASE WHEN vd.margin IS NOT NULL AND vd.margin <> 0 THEN (p.cost + (p.cost * v.shippingMarkup)) / (1 - vd.margin) ELSE
                                CASE WHEN b.margin IS NOT NULL THEN (p.cost + (p.cost * v.shippingMarkup)) / (1 - b.margin) ELSE (p.cost + (p.cost * v.shippingMarkup)) / (1 - 0.40)
                            END
                        END
                    END, 3) AS srp2,
                v.shippingMarkup,
                v.vendorID
            FROM woodshed_no_replicate.AuditScan AS a
            INNER JOIN products AS p ON p.upc=a.upc
            LEFT JOIN departments AS b ON p.department=b.dept_no
            LEFT JOIN vendors AS v on v.vendorID=p.default_vendor_ID
            LEFT JOIN VendorSpecificMargins AS c ON c.vendorID=p.default_vendor_id AND p.department=c.deptID
            LEFT JOIN MasterSuperDepts AS m ON m.dept_ID=p.department
            LEFT JOIN batchList AS bl ON bl.upc=p.upc
            LEFT JOIN batchReviewLog AS brl ON brl.bid=bl.batchID AND brl.forced = '0000-00-00 00:00:00'
            LEFT JOIN prodReview AS pr ON pr.upc=p.upc AND pr.vendorID=p.default_vendor_id

            LEFT JOIN vendorItems AS vi ON vi.upc=p.upc AND vi.vendorID=p.default_vendor_id
            LEFT JOIN vendorDepartments AS vd ON vd.vendorID=p.default_vendor_id AND vd.deptID=vi.vendorDept

            WHERE a.username=?
                AND a.savedAs='default'
            GROUP BY p.upc
        ");
        $listR = $dbc->execute($listP, $listA);
        while ($row = $dbc->fetchRow($listR)) {
            $upc = $row['upc'];
            $vendorID = $row['vendorID'];
            $srp = $row['srp'];
            $srp2 = $row['srp2'];
            if ($srp2 > $srp)
                // if $srp2 > $srp, then a shipping markup exists
                $srp = $srp2;
            $srp = $rounder->round($srp);
            $items[$upc]['srp'] = $srp;
            $items[$upc]['vendorID'] = $vendorID;
        }

        $ret .= $dbc->error();

        $prep = $dbc->prepare("
            UPDATE woodshed_no_replicate.AuditScan AS a
            SET notes=?
            WHERE a.upc=?
                AND a.username=?
                AND a.storeID=?
                AND a.savedAs='default' 
        ");

        $dbc->startTransaction();
        foreach ($items as $upc => $row) {
            $args = array(
                $row['srp'],
                $upc,
                $username,
                $storeID
            );
            $dbc->execute($prep, $args);
            $ret .= $dbc->error();
        }
        $dbc->commitTransaction();


        echo $ret . ' END';
        return false; 
    }

    public function postSetPRNHandler()
    {
        $username = FormLib::get('username');
        $dbc = ScanLib::getConObj('SCANALTDB');

        $getvendorP = $dbc->prepare("select v.vendorName FROM AuditScan a inner join is4c_op.products p on p.upc=a.upc INNER JOIN is4c_op.vendors v ON v.vendorID=p.default_vendor_id WHERE a.username='csather' AND a.savedAs='default' GROUP BY p.default_vendor_id ORDER BY count(p.default_vendor_id) DESC LIMIT 1;");
        $vendorName = $dbc->getValue($getvendorP);
        $vendorName = str_replace("'", "", $vendorName);
        $reviewList = $vendorName . " REVIEW LIST";

        $args = array($username, $username, $reviewList);
        $prep = $dbc->prepare("UPDATE AuditScan a INNER JOIN AuditScan b ON b.upc=a.upc SET a.PRN=b.notes WHERE a.username=? AND a.savedAs='default' AND b.username=? AND b.savedAs=?;"); 
        $dbc->execute($prep, $args);

        echo $dbc->error();

        return false; 
    }

    public function getExportExcelHandler()
    {
        echo $this->postView('true');

        return false;
    }

    public function postExportCsvHandler()
    {
        $files = glob('./noauto/' . '*', GLOB_MARK);
        foreach ($files as $file) {
            if (is_dir($file)) {
                deleteDir($file);
            } else {
                unlink($file);
            }
        }

        $filename = 'AuditReport' . uniqid() . '.csv'; 

        $theadStr = FormLib::get('thead', false);
        $theadStr = json_decode($theadStr);

        $tdStr = FormLib::get('tableData', false);
        $tdStr = json_decode($tdStr);

        $data = array(
            $theadStr,
        );
        foreach ($tdStr as $arr) {
            $data[] = $arr;
        }

        $f = fopen("noauto/$filename", "w");
        foreach ($data as $arr) {
            fputcsv($f, $arr);
        }
        fclose($f);

        echo $filename;

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

        if ($brand == -1 || $brand == "") {
            // do nothing 
        } else {
            $args = array($brand);
            $prep = $dbc->prepare("SELECT upc FROM products WHERE TRIM(brand) = TRIM(?)");
            $res = $dbc->execute($prep, $args);
            $items = array();
            while ($row = $dbc->fetchRow($res)) {
                $items[] = $row['upc'];
            }

            $this->loadVendorCatalogHandler($items, $username, $storeID);
        }


        return header("location: AuditReport.php");
    }

    public function postVendCatHandler()
    {
        $dbc = ScanLib::getConObj();
        $vid = FormLib::get('vendCat');
        $username = FormLib::get('username');
        $storeID = FormLib::get('storeID');
        $full = FormLib::get('loadFullCat');
        $loadVendorItems = FormLib::get('loadFullCatV');
        $inUse = ($full == 1) ? '' : ' AND inUse = 1 ';
        $_SESSION['currentVendor'] = $vid;

        if ($vid <= 0) {
            // do nothing
        } else {
            $args = array($vid);
            if ($loadVendorItems == 1) {
                // load all from vendor items regardless of default vendor
                $prep = $dbc->prepare("SELECT upc FROM vendorItems WHERE vendorID = ? GROUP BY upc");
            } else {
                // load only items with default vendor set to selected vid
                $prep = $dbc->prepare("SELECT v.upc
                    FROM products AS p
                        LEFT JOIN vendorItems AS v ON v.upc=p.upc AND v.vendorID=p.default_vendor_id
                        RIGHT JOIN MasterSuperDepts AS m ON m.dept_ID=p.department
                    WHERE p.default_vendor_id = ?
                        AND m.super_name != 'PRODUCE'
                        $inUse
                    GROUP BY p.upc;
                ");
            }
            $res = $dbc->execute($prep, $args);
            $items = array();
            while ($row = $dbc->fetchRow($res)) {
                $items[] = $row['upc'];
            }

            $this->loadVendorCatalogHandler($items, $username, $storeID);
        }


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
        //echo $dbc->error();

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

    public function getLoadListHandler()
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

        $insertP = $dbc->prepare("INSERT INTO AuditScan (date, upc, username, storeID, savedAs, notes)
            VALUES (NOW(), ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE date = NOW(), notes=VALUES(notes)");
        $delP = $dbc->prepare("DELETE FROM AuditScan WHERE upc = ? AND username = ? AND savedAs = ?");
        foreach($upcs as $upc) {
            $note = '';
            $note = $notes[$upc];
            if (strtoupper($note) != 'DEL') {
                $insertA = array($upc, $username, $storeID, $saveAs, $note);
                $res = $dbc->execute($insertP, $insertA);
            } else {
                $delA = array($upc, $username, $saveAs);
                $res = $dbc->execute($delP, $delA);
            }
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

        // 1. Get majority default vendor name 
        $args = array($username);
        $prep = $dbc->prepare(" SELECT
                COUNT(v.vendorName) AS Count, v.vendorName
            FROM woodshed_no_replicate.AuditScan AS a
                LEFT JOIN is4c_op.products AS p ON p.upc=a.upc
                LEFT JOIN is4c_op.vendors AS v ON v.vendorID=p.default_vendor_id
            WHERE username = ? AND savedAs = 'default'
            GROUP BY v.vendorName
            ORDER BY count(*) DESC
            LIMIT 1
        ");
        $res = $dbc->execute($prep, $args);
        $row = $dbc->fetchRow($res);
        $listName = str_replace("'", "", $row['vendorName']);
        $listName = $listName . " REVIEW LIST";

        // 2. Remove DEL rows
        $delIDs = array();
        $delA = array($username, $username, $listName);
        $delP = $dbc->prepare("
            SELECT a.id FROM AuditScan a
                INNER JOIN AuditScan b
                    ON b.upc=a.upc
                        AND b.savedAs = 'default'
                        AND b.username=?
                        AND b.notes = 'DEL'
            WHERE a.username=?
                AND a.savedAs=?");
        $delR = $dbc->execute($delP, $delA);
        while ($row = $dbc->fetchRow($delR)) {
            $delIDs[] = $row['id'];
        }

        list($inStr, $rmA) = $dbc->safeInClause($delIDs);
        $rmP = $dbc->prepare("DELETE FROM AuditScan WHERE id IN ($inStr)");
        $rmR = $dbc->execute($rmP, $rmA);

        // 3. Insert / Update notes
        $args = array($username, $storeID, $listName, $username);
        $prep = $dbc->prepare("INSERT INTO AuditScan (date, upc, username, storeID, notes, checked, savedAs) 
            SELECT date, upc, ?, ?, notes, checked, ? 
                FROM AuditScan where savedAs = 'default' AND username = ? AND notes != '' AND notes != 'DEL' 
            ON DUPLICATE KEY UPDATE notes=VALUES(notes), date=VALUES(date)
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
        $username = FormLib::get('username');
        $json = array();

        $dbc = ScanLib::getConObj();

        $args = array($upc, $username);
        $prep = $dbc->prepare("
            UPDATE woodshed_no_replicate.AuditScan
            SET notes = null, checked = 1
            WHERE upc = ?
                AND username = ?
                AND savedAs = 'default'
        ");
        $res = $dbc->execute($prep, $args);
        $json['errors'] .= $dbc->error();

        $mod = new DataModel($dbc);
        $json['saved'] = $mod->setCost($upc, $cost, $vendorID);
        echo json_encode($json);

        return false;
    }

    public function postSetVendorCostHandler()
    {
        $upc = FormLib::get('upc');
        $cost = FormLib::get('cost');
        $vendorID = FormLib::get('vendorID');
        $json = array();

        $dbc = ScanLib::getConObj();
        $args = array($cost, $upc, $vendorID);
        $prep = $dbc->prepare("UPDATE vendorItems SET cost = ? WHERE upc = ? AND vendorID = ?");
        $res = $dbc->execute($prep, $args);

        $args = array($upc, $vendorID);
        $prep = $dbc->prepare("SELECT cost FROM vendorItems WHERE upc = ? AND vendorID = ? GROUP BY upc");
        $res = $dbc->execute($prep, $args);
        $row = $dbc->fetchRow($res);

        $logA = array($upc, $cost, $vendorID);
        $logP = $dbc->prepare("INSERT INTO woodshed_no_replicate.VendorItemsCosts (upc, cost, vendorID, modified) VALUES (?, ?, ?, NOW())");
        $logR = $dbc->execute($logP, $logA);

        $json['saved'] = '';
        if ($row['cost'] == $cost) {
            $json['saved'] = true;
        }
        $json['test'] = 'success';
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
        $description = urldecode($description);
        $description = trim($description);
        //$description = str_replace('Â', '', $description);
        $description = trim($description, 'Â');
        $description = trim($description, ' ');
        $description = trim($description);
        $table = FormLib::get('table');
        $json = array();

        $dbc = ScanLib::getConObj();
        $mod = new DataModel($dbc);
        $json['saved'] = $mod->setDescription($upc, $description, $table);
        $json['str'] = 'str: '.$description;
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

    public function postSetSizeHandler()
    {
        $upc = FormLib::get('upc');
        $size = FormLib::get('size');
        $table = FormLib::get('table');
        $json = array();

        $dbc = ScanLib::getConObj();
        $mod = new DataModel($dbc);
        $json['saved'] = $mod->setSize($upc, $size, $table);
        echo json_encode($json);

        return false;
    }

    public function postSetUomHandler()
    {
        $upc = FormLib::get('upc');
        $uom = FormLib::get('uom');
        $table = FormLib::get('table');
        $json = array();

        $dbc = ScanLib::getConObj();
        $mod = new DataModel($dbc);
        $json['saved'] = $mod->setUom($upc, $uom, $table);
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

        $costMode = 0; 
        if (isset($_SESSION['costMode'])) {
            $costMode = $_SESSION['costMode'];
        }

        $vendorCosts = array();
        if ($costMode == 0) {
            // cost mode = Products Table
        }
        if ($costMode == 1) {
            // cost mode = Vendor Items Table
            $args = array($username, $_SESSION['currentVendor']);
            $prep = $dbc->prepare("SELECT v.upc, v.cost, v.sku
                FROM vendorItems AS v
                RIGHT JOIN woodshed_no_replicate.AuditScan AS a ON a.upc=v.upc
            WHERE v.upc != '0000000000000'
                AND a.username = ?
                AND a.savedAS = 'default'
                AND v.vendorID = ?
            ");
            $res = $dbc->execute($prep, $args);
            while ($row = $dbc->fetchRow($res)) {
                $vendorCosts[$row['upc']]['cost'] = $row['cost'];
                $vendorCosts[$row['upc']]['sku'] = $row['sku'];
                //echo $row['upc'];
            }
        }
        $andReviewVendorID = ($_SESSION['currentVendor'] > 0) ? " AND pr.vendorID = {$_SESSION['currentVendor']} " : '';
        $vendorAliasJoinOn = ($costMode == 1) ? ' va.vendorID='.$_SESSION['currentVendor'] : ' va.vendorID=p.default_vendor_id ';
        $vendorItemsJoinOn = ($costMode == 1) ? ' v.vendorID='.$_SESSION['currentVendor'] : ' v.vendorID=p.default_vendor_id ';

        $upcs = array();

        //$args = array($username, $storeID);
        $args = array();
        if ($costMode == 1) {
            //$args[] = $_SESSION['currentVendor'];
        }
        $args[] = $username;
        $args[] = $storeID;
        $prep = $dbc->prepare("
            SELECT
                pf.flags,
                p.store_id,
                p.upc,
                v.sku,
                va.sku AS alias,
                lc.likeCode,
                va.isPrimary,
                p.brand,
                u.brand AS signBrand,
                p.description AS description,
                u.description AS signDescription,
                p.cost,
                'n/a' AS vcost,
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
                p.size,
                p.unitofmeasure AS uom,
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
                fslv.subSections AS floorSections,
                pr.comment,
                p.tax,
                r.details AS prtDetails,
                si.tare,
                m.super_name
            FROM products AS p
                LEFT JOIN vendorItems AS v ON $vendorItemsJoinOn AND p.upc=v.upc
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
                    $andReviewVendorID
                LEFT JOIN productCostChanges AS c ON p.upc=c.upc
                LEFT JOIN subdepts ON subdepts.subdept_no=p.subdept AND subdepts.dept_ID=p.department
                LEFT JOIN prodFlagsListView AS pf ON pf.upc=p.upc AND pf.storeID=p.store_id
                LEFT JOIN FloorSectionsListView2 AS fslv ON fslv.upc=p.upc AND fslv.storeID=p.store_id
                LEFT JOIN VendorAliases AS va ON $vendorAliasJoinOn AND va.upc=p.upc
                LEFT JOIN likeCodeView AS lc ON lc.upc=p.upc
                LEFT JOIN FloorSectionProductMap AS fspm ON fspm.upc=p.upc
                LEFT JOIN FloorSubSections AS fss ON fss.upc=p.upc
                LEFT JOIN FloorSections AS fs ON fs.floorSectionID=fspm.floorSectionID AND fs.storeID=p.store_id
                LEFT JOIN scaleItems AS si ON si.plu=p.upc
                LEFT JOIN MasterSuperDepts AS m ON m.dept_ID=p.department
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
        $csv = "UPC, SKU, ALIAS, LIKECODE, BRAND, SIGNBRAND, DESC, SIGNDESC, SIZE, UOM, UNITS, NETCOST, COST, VCOST, RECENT PURCHASE, PRICE, CUR SALE, AUTOPAR, CUR MARGIN, TARGET MARGIN, DIFF, RAW SRP, SRP, PRICE RULE, TAX, DEPT, SUBDEPT, SUPERDEPT, LOCAL, FLAGS, VENDOR, LAST TIME SOLD, SCALE, SCALE PLU, LAST REVIEWED, FLOOR SECTIONS, REVIEW COMMENTS, PRN, CASE COST, NOTES\r\n";

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
            <td title=\"likeCode\" data-column=\"likeCode\"class=\"likeCode column-filter\"></td>
            <td title=\"band\" data-column=\"brand\"class=\"brand column-filter\"></td>
            <td title=\"sign-brand\" data-column=\"sign-brand\"class=\"sign-brand column-filter\"></td>
            <td title=\"description\" data-column=\"description\"class=\"description column-filter\"></td>
            <td title=\"sign-description\" data-column=\"sign-description\"class=\"sign-description column-filter\"></td>
            <td title=\"size\" data-column=\"size\"class=\"size column-filter\"></td>
            <td title=\"uom\" data-column=\"uom\"class=\"uom column-filter\"></td>
            <td title=\"units\" data-column=\"units\"class=\"units column-filter\"></td>
            <td title=\"netCost\" data-column=\"netCost\"class=\"netCost column-filter\"></td>
            <td title=\"cost\" data-column=\"cost\"class=\"cost column-filter\"></td>
            <td title=\"vcost\" data-column=\"vcost\"class=\"vcost column-filter\"></td>
            <td title=\"recentPurchases\" data-column=\"recentPurchase\"class=\"recentPurchase column-filter\"></td>
            <td title=\"price\" data-column=\"price\"class=\"price column-filter\"></td>
            <td title=\"sale\" data-column=\"sale\"class=\"sale column-filter\"></td>
            <td title=\"autoPar\" data-column=\"autoPar\"class=\"autoPar column-filter\"></td>
            <td title=\"margin_target_diff\" data-column=\"margin_target_diff\"class=\"margin_target_diff column-filter\"></td>
            <td title=\"srp\" data-column=\"srp\"class=\"srp column-filter\"></td>
            <td title=\"rsrp\" data-column=\"rsrp\"class=\"rsrp column-filter\"></td>
            <td title=\"prid\" data-column=\"prid\"class=\"prid column-filter\"></td>
            <td title=\"prt\" data-column=\"prt\"class=\"prt column-filter\"></td>
            <td title=\"tax\" data-column=\"tax\"class=\"tax column-filter\"></td>
            <td title=\"dept\" data-column=\"dept\"class=\"dept column-filter\"></td>
            <td title=\"subdept\" data-column=\"subdept\"class=\"subdept column-filter\"></td>
            <td title=\"superdept\" data-column=\"superdept\"class=\"superdept column-filter\"></td>
            <td title=\"local\" data-column=\"local\"class=\"local column-filter\"></td>
            <td title=\"flags\" data-column=\"flags\"class=\"flags column-filter\"></td>
            <td title=\"vendor\" data-column=\"vendor\"class=\"vendor column-filter\"></td>
            <td title=\"last_sold\" data-column=\"last_sold\"class=\"last_sold column-filter\"></td>
            <td title=\"scaleItem\" data-column=\"scaleItem\"class=\"scaleItem column-filter\"></td>
            <td title=\"scalePLU\" data-column=\"scalePLU\"class=\"scalePLU column-filter\"></td>
            <td title=\"tare\" data-column=\"tare\"class=\"tare column-filter\"></td>
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
            <th class=\"likeCode\">likeCode</th>
            <th class=\"brand\">brand</th>
            <th class=\"sign-brand \">sign-brand</th>
            <th class=\"description\">description</th>
            <th class=\"sign-description \">sign-description</th>
            <th class=\"size\">size</th>
            <th class=\"uom\">uom</th>
            <th class=\"units\">units</th>
            <th class=\"netCost\">netCost</th>
            <th class=\"cost\">cost</th>
            <th class=\"vcost\">vcost</th>
            <th class=\"recentPurchase\">PO-unit</th>
            <th class=\"price\">price</th>
            <th class=\"sale\">sale</th>
            <th class=\"autoPar\">autoPar(*7)</th>
            <th class=\"margin_target_diff\">margin, target, diff</th>
            <th class=\"rsrp\">raw srp</th>
            <th class=\"srp\">srp</th>
            <th class=\"prid\">prid</th>
            <th class=\"prt\">prt</th>
            <th class=\"tax\">tax</th>
            <th class=\"dept\">dept</th>
            <th class=\"subdept\">subdept</th>
            <th class=\"superdept\">superdept</th>
            <th class=\"local\">local</th>
            <th class=\"flags\">flags</th>
            <th class=\"vendor\">vendor</th>
            <th class=\"last_sold\">last_sold</th>
            <th class=\"scaleItem\">scale</th>
            <th class=\"scalePLU\">scalePLU</th>
            <th class=\"tare\">tare</th>
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
            if ($costMode == 1) {
                if (isset($vendorCosts[$upc])) {
                    $sku = '';
                    $sku = $vendorCosts[$upc]['sku'];
                }
            }
            $alias = $row['alias'];
            $likeCode = $row['likeCode'];
            $isPrimary = $row['isPrimary'];
            if ($isPrimary == 1) {
                $alias = "<span style=\"background-color: #CBF6FF\">$alias [P]</span>";
            }
            list($recentPurchase, $received) = $this->getRecentPurchase($dbc,$upc);
            $brand = $row['brand'];
            //$autoPar = '';
            $autoPar = '<div style="height: 12px;"><table class="table table-small small" style="margin-top: -4.5px;
                background-color: rgba(0,0,0,0); border: 0px solid transparent;"><tr class="autoPar">';
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
                $autoPar .= "<td style=\"width: 25px; border-left: 5px solid $woSalesText; \" class=\"autoPar noauto\"> $par</td>";
                $csvAutoPar .= "[$storeID] $par ";
            }
            $autoPar .= "</tr></table></div>";
            $signBrand = $row['signBrand'];
            $description = $row['description'];
            $signDescription = $row['signDescription'];
            $netCost = $row['cost'];
            $cost = $row['cost'];
            $vcost = $row['vcost'];
            if (isset($vendorCosts[$upc]))
                $vcost = $vendorCosts[$upc]['cost'];
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
            $prt = $row['prtDetails'];
            $tax = $this->taxes[$row['tax']];
            $dept = $row['dept'];
            $subdept = $row['subdept'];
            $superdept = $row['super_name'];
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
            $uom = $row['uom'];
            $units = $row['units'];
            $costChangeDate = $row['costChangeDate'];
            $costChange = $row['costChange'];
            $floorSections = rtrim($row['floorSections'], "-");
            $reviewComments = $row['comment'];
            $prn = $row['PRN'];
            $scalePLU = ($bycount == null) ? '' : substr($upc, 3, 4);
            $tare = $row['tare'];
            $caseCost = $row['caseCost'];
            $ubid = uniqid();
            $td .= "<tr class=\"prod-row\" id=\"$rowID\">";
            $td .= "<td class=\"upc\" data-upc=\"$upc\">$uLink</td>";
            $td .= "<td class=\"sku\">$sku</td>";
            $td .= "<td class=\"alias\">$alias</td>";
            $td .= "<td class=\"likeCode\">$likeCode</td>";
            $td .= "<td class=\"brand editable editable-brand\" data-table=\"products\"
                style=\"text-transform:uppercase;\" id=\"b$ubid\">$brand</td>";
            $td .= "<td class=\"sign-brand editable editable-brand \" data-table=\"productUser\" id=\"sb$ubid\">$signBrand</td>";
            $td .= "<td class=\"description editable editable-description\" data-table=\"products\" 
                style=\"text-transform:uppercase;\" maxlength=\"30\" id=\"d$ubid\">$description</td>";
            $td .= "<td class=\"sign-description editable editable-description \" data-table=\"productUser\" spellcheck=\"true\" id=\"sd$ubid\">$signDescription</td>";
            $td .= "<td class=\"size editable editable-size\">$size</td>";
            $td .= "<td class=\"uom editable editable-uom\">$uom</td>";
            $td .= "<td class=\"units\">$units</td>";
            $td .= "<td class=\"netCost editable-cost\" data-vid=\"$vendorID\">$netCost</td>";
            $td .= "<td class=\"cost\" $ogCost>$cost</td>";
            $td .= "<td class=\"vcost editable-vcost\" data-current-vid=\"{$_SESSION['currentVendor']}\">$vcost</td>";
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
            $td .= "<td class=\"prt\">$prt</td>";
            $td .= "<td class=\"tax\">$tax</td>";
            //$td .= "<td class=\"dept\">
            //    <span class=\"dept-text\">$dept</span>
            //    <span class=\"dept-select hidden\">$deptOpts</span>
            //    </td>";
            $td .= "<td class=\"dept\">$dept</td>";
            $td .= "<td class=\"subdept\">$subdept</td>";
            $td .= "<td class=\"superdept\">$superdept</td>";
            $td .= "<td class=\"local\">$local</td>";
            $td .= "<td class=\"flags\">$flags</td>";
            $td .= "<td class=\"vendor\" data-vendorID=\"$vendorID\">$vendor</td>";
            $td .= "<td class=\"last_sold\">$lastSold</td>";
            $td .= "<td class=\"scaleItem\">$bycount</td>";
            $td .= "<td class=\"scalePLU\">$scalePLU</td>";
            $td .= "<td class=\"tare\">$tare</td>";
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

            $prepCsv = strip_tags("\"$upc\", \"$sku\", \"$alias\", \"$likeCode\", \"$brand\", \"$signBrand\", \"$description\", \"$signDescription\", $size, $uom, $units, $netCost, $cost, $vcost, $recentPurchase, $price, $sale, $csvAutoPar, $curMargin, $margin, $diff, $rsrp, $srp, $prid, $prt, $tax, $dept, $subdept, $superdept, $local, \"$flags\", \"$vendor\", $lastSold, $bycount, \"$scalePLU\", \"$tare\" \"$reviewed\", \"$floorSections\", \"$reviewComments\", \"$prn\", $caseCost, \"$notes\"");
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
        <tr><td class="noauto">$textarea</td></tr>
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
        $admin = ($_COOKIE['user_type'] == 2) ? true : false;

        if (!isset($_SESSION['notepad'.$username])) {
            $_SESSION['notepad'.$username] = "";
        }
        //$_SESSION['notepad'.$username] .= "0000000001234\n";

        $costMode = FormLib::get('costModeSwitch', false);
        if ($costMode === false) {
            if (isset($_SESSION['costMode'])) {
                $costMode = $_SESSION['costMode'];
            } else { $costMode = 0;
                $_SESSION['costMode'] = $costMode;
            }
        } else {
            $_SESSION['costMode'] = $costMode;
        }

        if (isset($_SESSION['scrollMode'])) {
            $scrollMode = ($_SESSION['scrollMode'] == 0) ? 'on' : 'off';
        }

        if (!isset($_SESSION['columnBitSet'])) {
            // define default columns to check (view)
            $_SESSION['columnBitSet'] = 0;
            $x = 0;
            $x |= 1 << 0; //check 
            $x |= 1 << 1; //upc
            $x |= 1 << 2; //sku
            $x |= 1 << 5; //brand
            $x |= 1 << 7; //description
            $x |= 1 << 9; //size
            $x |= 1 << 10;//uom
            $x |= 1 << 11;//units
            $x |= 1 << 12;//netcost
            $x |= 1 << 16;//price
            $x |= 1 << 17;//saleprice
            $x |= 1 << 18;//autopar
            $x |= 1 << 24;//tax
            $x |= 1 << 25;//dept
            $x |= 1 << 36;//notes
            $x |= 1 << 37;//reviewed
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
        $newSavedLists = "";
        $datalist = "<datalist id=\"savedLists\">";
        while ($row = $dbc->fetchRow($res)) {
            $date = $row['date'];
            $saved = $row['savedAs'];
            $sel = ($saved == $loaded) ? ' selected ' : '';
            $style = (strpos(strtolower($saved), 'review') !== false) ? "style=\"background-color: lightblue; border: 1px solid grey;\"" : "";
            $savedLists .= "<option value=\"$saved\" $style  $sel>[$date] $saved</option>";
            $datalist .= "<option value=\"$saved\">";
            $href = "AuditReport.php?loadList=$saved&username=$username&storeID=$storeID";
            $newSavedLists .= "<div class=\"saved-list-item\" $style><a href=\"$href\">[$date] $saved</a></div>";
        }
        $datalist .= "</datalist>";

        $this->addScript('http://'.$FANNIE_ROOTDIR . '/src/javascript/chosen/chosen.jquery.min.js');
        $this->addCssFile('http://'.$FANNIE_ROOTDIR. '/src/javascript/chosen/bootstrap-chosen.css');
        // chosen breaks the select scroll feature
        //$this->add_onload_command('$(\'.chosen-select:visible\').chosen();');
        //$this->add_onload_command('$(\'#store-tabs a\').on(\'shown.bs.tab\', function(){$(\'.chosen-select:visible\').chosen();});');

        $this->vendors = array();
        $vselect = '<option value="-1">Select a Vendor</option>';
        $curVendor = FormLib::get('vendor');
        $prep = $dbc->prepare("SELECT vendorName, vendorID FROM vendors 
            WHERE vendorID NOT IN (-2,-1,1,2)
            ORDER BY vendorName ASC;");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
             $vid = $row['vendorID'];
             $vname = $row['vendorName'];
             $vselect .= "<option value='$vid'>$vname</option>";
             $this->vendor[$vid] = $vname;
         }

        $bselect = '<option value="-1">Select a Brand</option>';
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
        if ($admin == true) {
            // user is admin 
            $tempClass = "btn-secondary";
            $vncBtn = '
        <div class="form-group dummy-form">
            <button class="btn btn-default btn-sm small text-secondary" id="validate-notes-cost">VNC</button> |
            <button class="btn btn-default btn-sm small text-secondary" id="validate-notes-cost-two">VNC-CC</button> |
            <button class="btn btn-default btn-sm small text-secondary" id="hide-validated">Hide VNC\'d</button>
        </div>';
            $checkPriceBtn = '
        <!--
            <div class="form-group dummy-form">
            <button class="btn btn-default btn-sm small" id="check-prices">Check Prices</button>
            </div> -->
                ';

            /* Manually Set Vendor */
            //$_SESSION['currentVendor'] = 358;

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
            $saveReviewBtn = '<button id="saveReviewList" class="btn btn-secondary btn-sm page-control">Save List <span class="mini-q"
                title="Save items with notes (as REVIEW LIST)">?</span></button>';

            $reviewForm = '
<div class="form-group dummy-form">
    <form method="post" action="AuditReport.php">
        '.$reviewBtn.'
        <input type="hidden" name="review" value="'.$tempInputVal.'"/>
        <input type="hidden" name="username" value="'.$username.'"/>
    </form>
</div>';

            $costModeHeader = array(0=>'Products', 1=>'Vendor Items');
            $costModeOpts = '';
            foreach ($costModeHeader as $v => $name) {
                $sel = ($v == $costMode) ? ' selected ' : '';
                $costModeOpts .= "<option value=\"$v\" $sel>$name Table</option>";
            }

            $costModeSwitch = '
<label for="costModeSwitch" title="\$costMode"><b>Table</b>: </label>
<div class="form-group dummy-form">
    <form method="post" name="costModeForm" action="AuditReport.php">
        <select name="costModeSwitch" id="costModeSwitch">
            <option value="null"></option>
            '.$costModeOpts.'
        </select>
    </form>
</div>
| Current Vendor: <input type="text" style="border: 0px solid transparent;" id="currentVendor" value="'.$_SESSION['currentVendor'].'" />';

            // don't show cost mode swith for other users at this time
            if ($_COOKIE['user_type'] != 2) {
                $costModeSwitch = '';
            }
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
        //$nFilter = "<div style=\"font-size: 12px;\"><b>Note Filter</b>:$noteStr</div>";
        $nFilter = '';

        $columns = $this->columns;
        $columnCheckboxes = "<div style=\"font-size: 12px; padding: 10px;\"><b>Table Columns: </b>";
        $i = count($columns) - 1;
        foreach ($columns as $column) {
            $columnCheckboxes .= "<span class=\"column-checkbox\"><label for=\"check-$column\">$column</label> <input type=\"checkbox\" name=\"column-checkboxes\" id=\"check-$column\" data-colnum=\"$i\" value=\"$column\" class=\"column-checkbox\" checked></span>";
            $i--;
        }
        $columnCheckboxes .= "</div>";

        $adminModal = '';

        $modal = "
            <div id=\"upcs_modal\" class=\"modal\">
                <div class=\"modal-dialog\" role=\"document\">
                    <div class=\"modal-content\" style=\"\" >
                      <div class=\"modal-header\" style=\"background: repeating-linear-gradient(#68747F,  #565E66, #68747F 5px)\">
                        <h3 class=\"modal-title\" style=\"color: white; text-shadow: 1px 1px black; background: rgba(206,151,207,0.5); padding: 10px; width: 100%;\">Enter a list of Barcodes</h3>
                        <button type=\"button\" class=\"close\" data-dismiss=\"modal\" aria-label=\"Close\"
                                style=\"position: absolute; top:20; right: 20\">
                              <span aria-hidden=\"true\">&times;</span>
                            </button>
                          </div>
                      <div style=\"height: 15px; background: linear-gradient(#586069,  white); margin-top: -5px;\"></div>
                          <div class=\"modal-body\">
                            <div align=\"center\">
                                <form method=\"post\" class=\"\">
                                    <div class=\"form-group\">
                                        <textarea class=\"form-control\" name=\"upcs\" rows=\"10\" style=\"background: rgba(255,255,255,0.8);\"></textarea>
                                    </div>
                                    <div class=\"form-group\">
                                        <button type=\"submit\" class=\"btn btn-default btn-xs\">Submit</button>
                                    </div>
                                    <div class=\"form-group\" align=\"right\">
                                        <label for=\"add-delete-list\" style=\"background: rgba(255,255,255,0.0); padding: 2px; border-radius: 4px; padding-right: 5px; padding-left: 5px;\">
                                            <span style=\"font-weight: bold; color: tomato; text-shadow: 1px 1px lightgrey;\">Delete</span> <span style=\"color: black; text-shadow: 1px 1px lightgrey;\">Instead of Add</span></label>
                                        <input type=\"checkbox\" id=\"add-delete-list\" name=\"add-delete-list\" value=1 />
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
                        onclick=\"var c = confirm('Delete list?'); if (c == true) { document.forms['deleteListForm'].submit(); }\">Delete This List</span>
                </div>
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

        $itBug = "<span> [IT] </span>";
        $adminFxOpts = ($admin) ? "
            <option value=\"hideNOF\">Hide Rows With 'NOF' entered as notes</option>
            <option value=\"pullReviewListToPrn\">$itBug Review List () => PRN</option>
            <option value=\"exportJSONbatch\">$itBug JSON Export Batch </option>
            <option value=\"updateViSrps\">$itBug SRP () => Notes</option>
            <option value=\"updateViSrps2\">$itBug SRP () => Notes II (include PR items)</option>
            <option value=\"ViClearNotes\">$itBug Clear Notes</option>
            <option value=\"jsUnitsDivision\">$itBug Divide Notes / Units</option>
            <option value=\"jsPrnDivision\">$itBug Divide Notes / PRN</option>
            <option value=\"roundPriceNotes\">$itBug Price Round Notes</option>
            <option value=\"genericUploadCostsPr\">$itBug Generic UpL cost => Notes (on upcs)</option>
            <option value=\"genericUploadCostsVi\">$itBug Generic UpL cost => Notes (on skus)</option>
            <option value=\"genericUploadNewSRPVi\">$itBug Generic UpL NewSRP => Notes</option>
            <option value=\"futureCostFromNotes\">$itBug Notes => Future Costs</option>
            <option value=\"vendorItemSrpReset\">$itBug Reset Vendor Item SRPs (to normal_price)</option>
            <option value=\"addColumnVcase\">$itBug Add Column VCASE </option>
            <option value=\"setPriceRuleDetails\">$itBug Set PriceRules.details = notes</option>
            <option value=\"setProductCosts\">$itBug Set products.cost = notes</option>
            <option value=\"lpadGeneric\" data-value=\"lpadGeneric\">$itBug LPAD GenericUpload upc column</option>
            <option value=\"created2prn\" data-value=\"created2prn\">$itBug set created => PRN</option>
            <option value=\"uncheckall\" data-value=\"uncheckall\">$itBug Uncheck All</option>
            <option value=\"notes2notes\" data-value=\"notes2notes\">$itBug notes 2 notes</option>
            <option value=\"getLastPrice\" data-value=\"getLastPrice\">$itBug get last price</option>
            <option value=\"setPrnDiff\" data-value=\"setPrnDiff\">$itBug Set PRN < DIFF: Notes (NewSRP) - Price</option>
        " : "";
            //<option value=\"clearTableData\">$itBug Clear Table Data</option>

        $adminFxOptsNew = ($admin) ? "
            <div class=\"fxExtOption\" data-value=\"hideNOF\">Hide Rows With 'NOF' entered as notes</div>
            <div class=\"fxExtOption\" data-value=\"pullReviewListToPrn\">$itBug Review List () => PRN</div>
            <div class=\"fxExtOption\" data-value=\"exportJSONbatch\">$itBug JSON Export Batch </div>
            <div class=\"fxExtOption\" data-value=\"updateViSrps\">$itBug SRP () => Notes</div>
            <div class=\"fxExtOption\" data-value=\"updateViSrps2\">$itBug SRP () => Notes II (include PR items)</div>
            <div class=\"fxExtOption\" data-value=\"ViClearNotes\">$itBug Clear Notes</div>
            <div class=\"fxExtOption\" data-value=\"jsUnitsDivision\">$itBug Divide Notes / Units</div>
            <div class=\"fxExtOption\" data-value=\"jsPrnDivision\">$itBug Divide Notes / PRN</div>
            <div class=\"fxExtOption\" data-value=\"roundPriceNotes\">$itBug Price Round Notes</div>
            <div class=\"fxExtOption\" data-value=\"genericUploadCostsPr\">$itBug Generic UpL cost => Notes (on upcs)</div>
            <div class=\"fxExtOption\" data-value=\"genericUploadCostsVi\">$itBug Generic UpL cost => Notes (on skus)</div>
            <div class=\"fxExtOption\" data-value=\"genericUploadNewSRPVi\">$itBug Generic UpL NewSRP => Notes</div>
            <div class=\"fxExtOption\" data-value=\"futureCostFromNotes\">$itBug Notes => Future Costs</div>
            <div class=\"fxExtOption\" data-value=\"vendorItemSrpReset\">$itBug Reset Vendor Item SRPs (to normal_price)</div>
            <div class=\"fxExtOption\" data-value=\"addColumnVcase\">$itBug Add Column VCASE </div>
            <div class=\"fxExtOption\" data-value=\"setPriceRuleDetails\">$itBug Set PriceRules.details = notes</div>
            <div class=\"fxExtOption\" data-value=\"setProductCosts\">$itBug Set products.cost = notes</div>
            <div class=\"fxExtOption\" data-value=\"lpadGeneric\">$itBug LPAD GenericUpload upc column</div>
            <div class=\"fxExtOption\" data-value=\"uncheckall\">$itBug Uncheck All</div>
            <div class=\"fxExtOption\" data-value=\"notes2notes\">$itBug Reset Notes to Current Values</div>
            <div class=\"fxExtOption\" data-value=\"getLastPrice\">$itBug Reset Notes to Last Known Price</div>
            <div class=\"fxExtOption\" data-value=\"setPrnDiff\">$itBug Set PRN < DIFF: Notes (NewSRP) - Price</div>
        " : "";
            //<div class=\"fxExtOption\" data-value=\"clearTableData\">$itBug WIPE DB INFO FOR ITEMS IN LIST</div>

        $newExcelFilename = "AuditReport_" . uniqid() . ".csv";

        if ($demo == true) {
            echo $this->postFetchHandler($demo);
        } else {
            return <<<HTML
<div id="sessionNotepad">
    <div style="color: plum; font-weight: bold;  margin-bottom: 05px">Session Notepad <span style="font-size: 14px; color: grey">(Will not reset when you reload the page)</span></div>
    <div style="position: absolute; top: 5px; right: 10px; cursor: pointer;" id="closeSessionNotepad">x</div>
    <div><textarea id="sessionNotepadText" class="form-control" rows=15>{$_SESSION['notepad'.$username]}</textarea></div>
</div>
<div id="tmpTableContainer"></div>
<div id="floating-window">
    <div id="fw-border">
        <!--<span style="position: absolute; top: 0px; right: 0px; background-color: white; width: 19px; height: 19px; margin: 0px; text-align: center; padding: 0px; cursor: pointer;">x</span>-->
        <span id="fw-border-text">&nbsp;Output</span>
        <span style="float: right; display: inline-block; cursor: pointer;"
            onclick="$('#floating-window').css('display', 'none');">x&nbsp;&nbsp;</span>
    </div>
    <textarea id="fw-text"></textarea>
</div>

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
<!--<div class="form-group dummy-form">
    <a class="btn btn-info btn-sm page-control" href="ProductScanner.php ">Scanner</a>
</div>-->
$costModeSwitch
<div class="form-group dummy-form" style="float: right;">
    {$this->StoreSelector('storeID')}
</div>
<div></div>

<div id="loadListGui">
    <div id="showLoadLists">
        <div><span class="btn btn-default btn-sm" id="btn-show-saved-lists">Load a List</span></div>
    </div>
    <div id="loadListContainer">
        <input type="text" style="width: 100%; outline: none;" id="new-saved-list-filter" placeholder="search"/>
        $newSavedLists
    </div>
</div>

<div class="gui-group-2">
    <form name="load" id="loadList" method="post" action="AuditReport.php" style="display: inline-block">
        <input name="username" type="hidden" value="$username" />
        <input name="storeID" type="hidden" value="$storeID" />
        <div class="form-group dummy-form">
            <select name="loadList" class="form-control form-control-sm chosen-select hidden">
                <option val=0>Saved Lists</option>
                <option val=0>&nbsp;</option>
                $savedLists
            </select>
        </div>
        <div class="form-group dummy-form">
            <button class="btn btn-default btn-sm hidden" type="submit">Load</button>
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
            <span class="load-select-tabs">Load Vendor List <span class="mini-q" title="Loads only items that are in-use for at least one store">?</span></span>
            <select name="vendCat" class="form-control form-control-sm" placeholder="Select a Vendor Catalog">
                $vselect
            </select>
        </div>
        <div class="form-group dummy-form">
            <span class="btn btn-default btn-sm" id="loadCatBtn" tabindex="0">Load</span>
        </div>
    </form>
</div>

<div class="gui-group">
    <form name="loadBrandList" id="loadBrandList" method="post" action="AuditReport.php" style="display: inline-block">
        <input name="username" type="hidden" value="$username" />
        <input name="storeID" type="hidden" value="$storeID" />
        <div class="form-group dummy-form" style="padding: 5px">
            <span class="load-select-tabs">Load Items by Brand <span class="mini-q" title="Loads all items regardless of in-use status">?</span></span>
            <select name="brandList" class="form-control form-control-sm" placeholder="Select a Brand">
                $bselect
            </select>
        </div>
        <div class="form-group dummy-form">
            <span class="btn btn-default btn-sm" type="submit" id="loadBrandBtn" tabindex="0">Load</span>
        </div>
    </form>
</div>
<div class="row">
    <div class="col-lg-2">
        <div style="font-size: 12px;" id="GenerateExcelFileDiv">
            <li><a href="#" id="ExportCsvAnchor">Generate File (csv)</a></li>
            <li><a href="../../../../git/fannie/batches/newbatch/BatchImportExportPage.php" target="_blank">Batch Import</a> 
                | <span style="cursor: pointer; color: purple" id="openSessionNotepad">Open Session Notepad</span> </li>
        </div>
    </div>
    <div class="col-lg-2">
        <!--
        <div style="font-size: 12px;">
            <label for="check-pos-descript"><b>Switch POS/SIGN Descriptors</b>:&nbsp;</label>
            <input type="checkbox" name="check-pos-descript" id="check-pos-descript" class="" checked>
        </div>
        -->
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
<div class="row" id="BtnFx1">
    <div class="col-lg-6">
        <div id="countDisplay" style="font-size: 12px; padding: 10px; display: none;">
            <span id="checkedCount"></span> <b>/
            <span id="itemCount"></span></b> ->
            <span id="percentComplete"></span>
            <div id="percentComplete"></div>
            <div id="percentComplete2"></div>
        </div>
        <div style="font-size: 12px; padding: 10px;">
            <div class="form-group dummy-form">
                <button class="btn btn-default btn-sm small" id="view-unchecked"><span style="background: white;">View * UnChecked</span></button>
            </div>
            <div class="form-group dummy-form">
                <button class="btn btn-default btn-sm small" id="view-checked"><span style="background: lightgrey;">View * Checked</span></button>
            </div>
            <div class="form-group dummy-form">
                <button class="btn btn-default btn-sm small" id="view-all">View * All</button>
            </div>
            <div class="form-group dummy-form">
                <button class="btn btn-default btn-sm small" id="view-sel-unchecked"><span style="background: white;">View SelUn</span></button>
            </div>
            <div class="form-group dummy-form">
                <button class="btn btn-default btn-sm small" id="view-sel-checked"><span style="background: lightgrey;">View SelCh</span></button>
            </div>
            <div class="form-group dummy-form">
                <button class="btn btn-dark btn-sm small" id="invert-show">Invert View</button>
            </div>
            $checkPriceBtn
            $vncBtn
            <select id="extHideFx" class="form-control form-control-sm" style="display: none;">
                <option value=null>More Filter/IT Methods</option>
                <option value="hideNoPar">Hide Rows With 0 AutoPar for both stores</option>
                <option value="hideXsPar">Hide Rows With < 0.3 AutoPar for both stores</option>
                <option value="hideHillNoPar">Hide Rows With 0 AutoPar for Hillside</option>
                <option value="hideDenNoPar">Hide Rows With 0 AutoPar for Denfeld</option>
                $adminFxOpts
            </select>

            <br/>
            <div class="form-group">
                <button id="fxBtnNew" class="btn btn-default btn-sm">More Filters & Methods</button>
            </div>
            <div id="fxExtMenu" tabindex=0>
                <div class="fxExtHeader"><span style="background: white; padding: 2px; padding-right: 10px;padding-left: 10px;border-radius: 2px;">More Filters & Methods</span></div>
                <div class="fxExtOptionContainer">
                    <div class="fxExtOption" data-value="hideNoPar">Hide Rows With 0 AutoPar for both stores</div>
                    <div class="fxExtOption" data-value="hideXsPar">Hide Rows With < 0.3 AutoPar for both stores</div>
                    <div class="fxExtOption" data-value="hideHillNoPar">Hide Rows With 0 AutoPar for Hillside</div>
                    <div class="fxExtOption" data-value="hideDenNoPar">Hide Rows With 0 AutoPar for Denfeld</div>
                    $adminFxOptsNew
                </div>
            </div>

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

        $FANNIE_URL = 'git/fannie/';
        $HOST = $_SERVER['HTTP_HOST'];
        $syncUrl = "'" . $HOST . '/' . $FANNIE_URL . '/modules/plugins2.0/SMS/scan/SmsProdSyncList.php' . "'";

        return <<<JAVASCRIPT
var syncUrl = $syncUrl;
var startup = 1;
var columnSet = $config;
var tableRows = $('#table-rows').val();
var storeID = $('#storeID').val();
var username = $('#username').val();
var scrollMode = $scrollMode;
var columnFilterLast = null;
var lastKeyUp = [];

var stripeIndex = 0;
var restripe = function() {
    $('tr.prod-row').each(function(){
        $(this).removeClass('stripe');
    });
    $('tr.prod-row').each(function(){
        let isVisible = $(this).is(":visible");
        if (isVisible) {
            if (stripeIndex % 2 == 0) {
                $(this).addClass('stripe');
            }
            stripeIndex++; 
        }
    });
};
restripe();

//$(document).mouseup(function(e) {
//    restripe();
//});

$("#mytable").bind('sortEnd', function(){
    restripe();
});


$('#clearNotesInputB').click(function() {
    ScanConfirm("Are you sure?", 'clear_notes', function() {
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
    });
});
$('#saveReviewList').click(function() {
    ScanConfirm("<br/><br/>Save notated rows as review list?", 'save_review_list', function() {
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
    });
});
$('#clearAllInputB').click(function() {
    ScanConfirm("<br/><br/>Delete list<br/> Are you sure?", 'clear_entire_list', function() {
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
    });
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

var saveEditCost = function(elm) {
    var cost = elm.text();
    var upc = elm.parent().find('.upc').attr('data-upc');
    var vendorID = elm.attr('data-vid'); 
    var username = $('#username').val();
    var element = elm;
    if (lastCost != cost) {
        $.ajax({
            type: 'post',
            data: 'setCost=true&upc='+upc+'&cost='+cost+'&vendorID='+vendorID+'&username='+username,
            dataType: 'json',
            url: 'AuditReport.php',
            success: function(response)
            {
                console.log('Saved: '+response);
                if (response.saved == true) {
                    /*
                        success!
                    */
                    // check the associated checkbox 
                    let checkbox = element.parent().find('input[type=checkbox]');
                    console.log(checkbox.attr('name'));
                    checkbox.prop('checked', true);
                    checkbox.closest('tr').addClass('highlight-checked');
                    //checkbox.trigger('click');
                    ajaxRespPopOnElm(element);
                    syncItem(upc);
                } else {
                    /*
                        failure
                    */
                    ajaxRespPopOnElm(element, 1);
                }
            },
            error: function(response)
            {
                ajaxRespPopOnElm(element, 1);
            },
        });
    }
    elm.attr('contentEditable', 'false');
    elm.css('font-weight', 'normal');
}

$('.editable-cost').on('focusout', function() {
    saveEditCost($(this));
});


var lastVCost = null;
$('.editable-vcost').click(function(){
    if ($(this).text() == 'n/a') {
        return false;
    }
    lastVCost = $(this).text();
    $(this).attr('contentEditable', 'true');
    $(this).css('font-weight', 'bold');
});
$('.editable-vcost').focusout(function(){
    var cost = $(this).text();
    var upc = $(this).parent().find('.upc').attr('data-upc');
    var vendorID = $(this).attr('data-current-vid'); 
    var element = $(this);
    if (lastVCost != cost) {
        $.ajax({
            type: 'post',
            data: 'setVendorCost=true&upc='+upc+'&cost='+cost+'&vendorID='+vendorID,
            dataType: 'json',
            url: 'AuditReport.php',
            success: function(response)
            {
                //console.log(response);
                if (response.saved == true) {
                    /*
                        success!
                    */
                    // check the associated checkbox 
                    let checkbox = element.parent().find('input[type=checkbox]');
                    console.log(checkbox.attr('name'));
                    //checkbox.prop('checked', true);
                    checkbox.trigger('click');
                    ajaxRespPopOnElm(element);
                    // syncing won't do anything at this time for a vendor cost change only
                } else {
                    /*
                        failure
                    */
                    ajaxRespPopOnElm(element, 1);
                }
            },
            error: function(response) {
                console.log('error');
                console.log(response);
            },
        });
    }
    $(this).attr('contentEditable', 'false');
    $(this).css('font-weight', 'normal');
});

var lastNotes = null
$('.editable-notes').on('focus', function(){
    lastNotes = $(this).text();
});
$('.editable-notes').focusout(function(){
    var element = $(this);
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
                    ajaxRespPopOnElm(element, 1);
                } else {
                    ajaxRespPopOnElm(element);
                }
            },
        });
    }

});

var lastSize = null;
var lastUom = null;
$('.editable-size').each(function(){
    $(this).attr('contentEditable', true);
    $(this).attr('spellCheck', false);
});
$('.editable-size').click(function(){
    lastSize = $(this).text();
});
$('.editable-size').focus(function(){
    lastSize = $(this).text();
});
$('.editable-size').focusout(function(){
    var table = "products";
    var upc = $(this).parent().find('td.upc').attr('data-upc');
    var elemID = $(this).attr('id');
    var element = $(this);
    var size = $(this).text();
    if (size != lastSize) {
        size = encodeURIComponent(size);
        $.ajax({
            type: 'post',
            data: 'setSize=true&upc='+upc+'&size='+size+'&table='+table,
            dataType: 'json',
            url: 'AuditReport.php',
            success: function(response)
            {
                if (response.saved == 'true') {
                    ajaxRespPopOnElm(element, 1);
                } else {
                    ajaxRespPopOnElm(element);
                }
                if (table == 'productUser') {
                    syncItem(upc);
                }
            },
        });
    }
});

$('.editable-uom').each(function(){
    $(this).attr('contentEditable', true);
    $(this).attr('spellCheck', false);
});
$('.editable-uom').click(function(){
    lastUom = $(this).text();
});
$('.editable-uom').focus(function(){
    lastUom = $(this).text();
});
$('.editable-uom').focusout(function(){
    var table = "products";
    var upc = $(this).parent().find('td.upc').attr('data-upc');
    var elemID = $(this).attr('id');
    var element = $(this);
    var uom = $(this).text();
    if (uom != lastUom) {
        uom = encodeURIComponent(uom);
        $.ajax({
            type: 'post',
            data: 'setUom=true&upc='+upc+'&uom='+uom+'&table='+table,
            dataType: 'json',
            url: 'AuditReport.php',
            success: function(response)
            {
                if (response.saved == 'true') {
                    ajaxRespPopOnElm(element, 1);
                } else {
                    ajaxRespPopOnElm(element);
                }
                if (table == 'productUser') {
                    syncItem(upc);
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
    var element = $(this);
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
                    ajaxRespPopOnElm(element, 1);
                } else {
                    ajaxRespPopOnElm(element);
                }
                if (table == 'productUser') {
                    syncItem(upc);
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
    var element = $(this);
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
                console.log(response);
                if (response.saved == 'true') {
                    ajaxRespPopOnElm(element, 1);
                } else {
                    ajaxRespPopOnElm(element);
                }
                if (table == 'products') {
                    syncItem(upc);
                }
            },
        });
    }
});

$(document).keydown(function(e){
    var key = e.keyCode;
    $('#keydown').val(key);

    if (e.keyCode == 13) {
        $('.confirm-yes').each(function(){
            $(this).trigger('click');
        });
    }
    if (e.keyCode == 27) { // keyCode 27 => ESC
        $('.confirm-no').each(function(){
            $(this).trigger('click');
        });
        if ($('#fxExtMenu').is(':visible')) {
            $('#fxExtMenu').css('display', 'none');
        }
    }

    let hlElm = document.getElementsByClassName("highlight")[0];
    console.log(hlElm);
    if (hlElm != 'undefined') {
        switch (e.keyCode) {
            case 40:
                // don't allow down while udc in progress
                if (!$('#udc-animation').is(":visible")) {
                    e.preventDefault();
                    $('#down-btn').trigger('click');
                }
                break;
            case 38:
                // don't allow up while udc in progress
                if (!$('#udc-animation').is(":visible")) {
                    e.preventDefault();
                    $('#up-btn').trigger('click');
                }
                break; 
            case 39: // right arrow key
                if (e.ctrlKey) { // && ctrl key
                    let curFoc = document.activeElement;
                    if ($(curFoc).hasClass('editable')) {
                        // do nothing, user is editing data
                    } else {
                        $('#udc-btn').trigger('click'); 
                    }
                }
                break;
            default:
                break;
        }
    }
});
$(document).keyup(function(e){
    var key = e.keyCode;
    $('#keydown').val(0);
});

var currentItem = {
    "upc": "null",
    "netCost": "null",
    "notes": "null",
}
var setCurrentItem = function(target) {
    currentItem.upc = target.closest('tr').find('.upc').attr('data-upc');
    currentItem.netCost = target.closest('tr').find('.netCost').text();
    currentItem.notes = target.closest('tr').find('.notes').text();
    console.log(currentItem);
}

$(document).mousedown(function(e){
    if (e.which == 1 && e.shiftKey) {
    //if (e.which == 1 && e.ctrlKey) {
        e.preventDefault();
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
            setCurrentItem(target);
        }
        $('#keydown').val(0);
    }
    if (e.which == 1 && $('#keydown').val() == 81) {
        // 'q' key + Left Click
        e.preventDefault();
        var target = $(e.target);
        let newSize = target.closest('tr').find('.notes').text();
        target.closest('tr').find('.notes').text('');
        target.closest('tr').find('.size').focus();
        target.closest('tr').find('.size').text(newSize);
        target.closest('tr').find('.size').focusout();
        target.closest('tr').find('.check').find('input').trigger('click');
    }
});

var setPercentComplete = function() {
    let items = 0;
    let checked = 0;
    $('.row-check').each(function(){
        items++;
        if ($(this).prop('checked') == true) {
            checked++;
        }
        console.log(items);
    });
    console.log('items: '+items+', checked: '+checked);

    let width = 190;
    let part = width / items;
    console.log('part: '+part);

    $('#percentComplete2').text('');
    let j=0;
    for (let i=0; i<items; i++) {
        let color = (j < checked && checked != 0) ? 'lightgreen' : 'lightgrey';
        let elm = document.createElement('div');
        elm.style.display = "inline-block";
        elm.style.width = part + "px";
        elm.style.background = color;
        elm.style.height = '10px';
        j++;
        
        $('#percentComplete2').append(elm);
    }

};

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
    //for (i; i < percent; i += 10) {
    //    strpercent += '<span style="color: lightgreen; border: 1px solid grey;">&#9608;</span>';
    //}
    //for (i; i < 100; i += 10) {
    //    strpercent += '<span style="color: grey; border: 1px solid grey;">&#9608;</span>';
    //}
    $('#percentComplete').html(Math.round(percent, 4) + '% Complete ' + strpercent);
    setPercentComplete();
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
$('.row-check').click(function(event){
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

    /*
        On click checkbox, focus next available checkbox
    */
    let tableSize = $('tr').filter(function(){
        return $(this).css('display') !== 'none';
    }).length - 2;

    nextElm = $(this).closest('tr').next('tr').find('.row-check');
    while (!nextElm.is(':visible')) {
        nextElm = $(nextElm).closest('tr').next('tr').find('.row-check');
        // !! don't allow infinite loop
        if (nextElm.length == 0) {
            break;
        }
    }
    nextElm.focus();
});
styleChecked();

$('#invert-show').click(function(){
    $('#mytablebody tr').each(function(){
        var visible = $(this).is(':visible');
        var isAutoPar = $(this).hasClass('autoPar');
        if (visible) {
            if (!isAutoPar) {
                $(this).hide();
            }
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
    columnFilterLast = $(this);
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
    restripe();
});


$('#view-all').click(function(){
    $('#mytablebody tr').each(function(){
        $(this).show();
    });
    restripe();
});
$('#view-checked').click(function(){
    $('#mytablebody tr').each(function(){
        $(this).show();
    });
    $('#mytablebody tr').each(function(){
        var isAutoPar = $(this).hasClass('autoPar');
        var checked = $(this).find('.row-check').is(':checked');
        if (checked == true) {
            $(this).show();
        } else {
            if (!isAutoPar) {
                $(this).hide();
            }
        }
    });
    restripe();
});
$('#view-sel-checked').click(function(){
    $('#mytablebody tr').each(function(){
        var isAutoPar = $(this).hasClass('autoPar');
        var checked = $(this).find('.row-check').is(':checked');
        if (checked == true) {
            // do nothing 
        } else {
            if (!isAutoPar) {
                $(this).hide();
            }
        }
    });
    restripe();
});
$('#view-unchecked').click(function(){
    $('#mytablebody tr').each(function(){
        $(this).show();
    });
    $('#mytablebody tr').each(function(){
        var isAutoPar = $(this).hasClass('autoPar');
        let checked = $(this).find('.row-check').is(':checked');
        let note = $(this).find('.editable-notes').text();
        if (checked == false && !note.includes('NOF') && !note.includes('skip')) {
            $(this).show();
        } else {
            if (!isAutoPar) {
                $(this).hide();
            }
        }
    });
    restripe();
});
$('#view-sel-unchecked').click(function(){
    $('#mytablebody tr').each(function(){
        var isAutoPar = $(this).hasClass('autoPar');
        let checked = $(this).find('.row-check').is(':checked');
        let note = $(this).find('.editable-notes').text();
        if (checked == false && !note.includes('NOF') && !note.includes('skip')) {
            // do nothing
        } else {
            if (!isAutoPar) {
                $(this).hide();
            }
        }
    });
    restripe();
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

$('#loadCatBtn').on('click keypress', function(){
    ScanConfirm("<br/>Are you sure you would like to load this catalog? This will replace the current list.", 'loadCatBtn', function() {
        $('#loadVendCat').submit();
    });
});
$('#loadBrandBtn').on('click keypress', function(){
    ScanConfirm("<br/>Are you sure you would like to load all from this brand? This will replace the current list.", 'loadBrandBtn', function() {
        $('#loadBrandList').submit();
    });
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
$('#validate-notes-cost-two').click(function(){
    $('tr').each(function(){
        if ($(this).hasClass('prod-row')) {
            var col1 = $(this).find('td.caseCost').text();
            col1 = parseFloat(col1);
            var col2 = $(this).find('td.notes').text();
            col2 = parseFloat(col2);
            let range = [];
            range.push(col2);
            range.push(col2+0.01);
            range.push(col2+0.02);
            range.push(col2+0.03);
            range.push(col2-0.01);
            range.push(col2-0.02);
            range.push(col2-0.03);
            if (range.includes(col1)) {
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
$( function() {
    $('#tmpTableContainer').draggable();
});

if (storeID == 1) {
    $('#storeSelector-storeID').css('border', '1px solid darkgreen')
        .css('background', 'linear-gradient(45deg, #ECFFDC, #C1E1C1')
        .css('font-weight', 'bold');
} else {
    $('#storeSelector-storeID').css('border', '1px solid darkgreen')
        .css('background', 'linear-gradient(45deg, #FBCEB1, #FBCEB1')
        .css('font-weight', 'bold');
}
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

// Sync to New POS
var syncItem = function (upc) 
{
    $.ajax({
        url: 'http://'+syncUrl,
        type: 'post',
        data: 'SyncUpcs='+upc,
        success: function(resp) {
            console.log('success');
        },
        complete: function()
        {
        }
    });
}

var tmpRet = '';
$('#extHideFx').change(function(){
    let c = false;
    let storeID = $('#storeID').val();
    let username = $('#username').val();
    let chosen = $(this).find(':selected').val();
    let vendorID = $('#currentVendor').val();
    console.log('chosen: '+chosen);
    switch (chosen) {
        case 'hideNoPar':
            $('tr').each(function(){
                v = $(this).find('td.autoPar').text();
                x = v.substring(2,5);
                y = v.substring(7,10);
                if (x == '0.0' && y == '0.0') {
                    $(this).hide();
                }
            });
            break;
        case 'hideXsPar':
            $('tr').each(function(){
                v = $(this).find('td.autoPar').text();
                x = v.substring(2,5);
                x = parseFloat(x);
                y = v.substring(7,10);
                y = parseFloat(y);
                if (x < 0.3 && y < 0.3) {
                    console.log('x: '+x+', y: '+y);
                    $(this).hide();
                }
            });
            break;
        case 'hideHillNoPar':
            $('tr').each(function(){
                v = $(this).find('td.autoPar').text();
                x = v.substring(2,5);
                if (x == '0.0') {
                    $(this).hide();
                }
            });
            break;
        case 'hideDenNoPar':
            $('tr').each(function(){
                v = $(this).find('td.autoPar').text();
                y = v.substring(7,10);
                if (y == '0.0') {
                    $(this).hide();
                }
            });
            break;
        case 'hideNOF':
            $('.notes').each(function(){
                let text = $(this).text();
                if (text == 'NOF' || text == 'n/a' || text == 'NA') {
                    $(this).closest('tr').hide();
                }
            });
            break;
        case 'pullReviewListToPrn':
            $.ajax({
                type: 'post',
                data: 'setPRN=true&username='+username,
                url: 'AuditReport.php',
                success: function(response) {
                    location.reload();
                },
                error: function(response) {
                },
            });
            break;
        case 'exportJSONbatch':
            // create export JSON batch text as string
            let batchType = prompt('Enter batch type (1=CD, 2=CHA, 4=PC, 12=OVR, 17=FC)');
            let start = prompt('Enter start date');
            let end = prompt('Enter end date');
            let owner = prompt('Enter owner (super department)');
            owner = owner.toUpperCase();
            let batchName = prompt('Enter batch name');
            let forcesuper = prompt('Use strict super dept (opt. if entered, will only include items under provided super dept.');
            let discountType = (batchType == 4) ? 0 : 1;
            tmpRet = '{ "startDate":"'+start+' 00:00:00", "endDate":"'+end+' 00:00:00", "batchName":"'+batchName+'", "batchType":"'+batchType+'", "discountType":"'+discountType+'", "priority":"0", "owner":"'+owner+'", "transLimit":"0", "items":[ ';
            $('tr.prod-row').each(function(){
                if (forcesuper.length > 0) {
                    if ($(this).find('.superdept').text() != forcesuper) {
                        return true;
                    }
                }
                let upc = $(this).find('td:eq(0)').text();
                let salePrice = $(this).find('td.notes').text();
                if (salePrice != null && salePrice != '' && salePrice > 0) {
                    tmpRet += '{ "upc":"'+upc+'", "salePrice":"'+salePrice+'", "groupSalePrice":null, "active":"0", "pricemethod":"0", "quantity":"0", "signMultiplier":"1"},';
                }
            });
            tmpRet = tmpRet.slice(0, -1);
            tmpRet += ' ] } ';
            $('#floating-window').css('display', 'block');
            $('#fw-border-text').text('');
            $('#fw-border-text').text('Batch Import');
            $('#fw-text').val('');
            $('#fw-text').val(tmpRet);
            break;
        case 'updateViSrps':
            ScanConfirm("<br/><br/>Update vendor item SRPs for items in list?", 'update_vi_srps', function() {
                $.ajax({
                    type: 'post',
                    data: 'username='+username+'&storeID='+storeID+'&vendorID='+vendorID+'&updatesrps=true',
                    url: 'AuditReport.php',
                    success: function(response) {
                        console.log('success');
                        console.log(response);
                        window.location.reload();
                    },
                    error: function(response) {
                        console.log('error: '+response);
                    },
                });
            });
            break;
        case 'updateViSrps2':
            ScanConfirm("<br/>Update vendor item SRPs<br/>for items in list<br/>(including items<br/>with price rules)?", 'update_vi_srps_2', function() {
                $.ajax({
                    type: 'post',
                    data: 'username='+username+'&storeID='+storeID+'&vendorID='+vendorID+'&updatesrps=true&includePR=1',
                    url: 'AuditReport.php',
                    success: function(response) {
                        console.log('success');
                        console.log(response);
                        window.location.reload();
                    },
                    error: function(response) {
                        console.log('error: '+response);
                    },
                });
            });
            break;
        case 'genericUploadCostsPr':
            ScanConfirm("<br/><br/>Get costs from Generic Upload <br/>(on upcs)?", 'get_generic_upload_costs_p', function() {
                $.ajax({
                    type: 'post',
                    data: 'username='+username+'&storeID='+storeID+'&genericupc=true',
                    url: 'AuditReport.php',
                    success: function(response) {
                        console.log('success');
                        console.log(response);
                        window.location.reload();
                    },
                    error: function(response) {
                        console.log('error: '+response);
                    },
                });
            });
            break;
        case 'genericUploadNewSRPVi':
            ScanConfirm("<br/><br/>Get NewSRP from Generic Upload?", 'get_generic_upload_newsrp_v', function() {
                $.ajax({
                    type: 'post',
                    data: 'username='+username+'&storeID='+storeID+'&vendorID='+vendorID+'&genericnewsrp=true',
                    url: 'AuditReport.php',
                    success: function(response) {
                        console.log('success');
                        console.log(response);
                        window.location.reload();
                    },
                    error: function(response) {
                        console.log('error: '+response);
                    },
                });
            });
            break;
        case 'genericUploadCostsVi':
            ScanConfirm("<br/><br/>Get costs from Generic Upload (on skus)?", 'get_generic_upload_costs_v', function() {
                $.ajax({
                    type: 'post',
                    data: 'username='+username+'&storeID='+storeID+'&vendorID='+vendorID+'&genericsku=true',
                    url: 'AuditReport.php',
                    success: function(response) {
                        console.log('success');
                        console.log(response);
                        window.location.reload();
                    },
                    error: function(response) {
                        console.log('error: '+response);
                    },
                });
            });
            break;
        case 'vendorItemSrpReset':
            ScanConfirm("<br/>Reset Vendor Item SRPs<br/>to match Products normal prices?<br/>!Important!: VENDOR ID<br/> MUST BE ENTERED<br/>", 'update_reset_srps', function() {
                let vendorID = $('#currentVendor').val();
                $.ajax({
                    type: 'post',
                    data: 'username='+username+'&storeID='+storeID+'&vendorID='+vendorID+'&updateResetSrps=true',
                    url: 'AuditReport.php',
                    success: function(response) {
                        console.log('success');
                        console.log(response);
                        window.location.reload();
                    },
                    error: function(response) {
                        console.log('error: '+response);
                    },
                });
            });
            break;
        case 'addColumnVcase':
            $("#mytable th").each(function() {
                let newth = document.createElement('th');
                newth.innerHTML = 'vcase';
                let text = $(this).text();
                if (text == 'vcost') {
                    $(this).after(newth);
                }
            });
            $("#mytable tr").each(function() {
                let vcost = $(this).find('td.vcost').text();
                let units = $(this).find('td.units').text();
                let vcase = parseFloat(vcost) * parseFloat(units);
                vcase = vcase.toFixed(2);
                let newtd = document.createElement('td');
                newtd.innerHTML = vcase;
                $(this).find('td.vcost').after(newtd);
            });
            break;
        case 'clearTableData':
            let textConfirm = prompt('Warning: this function clears table data from products, vendorItems, productUser, VendorAliases, & scaleItems. To continue, type I WANT TO WIPE ITEM DATA');
            if (textConfirm == 'I WANT TO WIPE ITEM DATA') {
                $.ajax({
                    type: 'post',
                    data: 'username='+username+'&storeID='+storeID+'&clearItemData=true',
                    url: 'AuditReport.php',
                    success: function(response) {
                        console.log('success');
                        console.log(response);
                        window.location.reload();
                    },
                    error: function(response) {
                        console.log('error: '+response);
                    },
                });
            }
            break;
        case 'setPriceRuleDetails':
            ScanConfirm("<br/><br/>Set Price Rule Details<br/>to equal Notes Column?", 'update_price_rule_details', function() {
                $.ajax({
                    type: 'post',
                    data: 'username='+username+'&storeID='+storeID+'&setPriceRuleDetails=true',
                    url: 'AuditReport.php',
                    success: function(response) {
                        console.log('success');
                        console.log(response);
                        window.location.reload();
                    },
                    error: function(response) {
                        console.log('error: '+response);
                    },
                });
            });
            break;
        case 'setProductCosts':
            ScanConfirm("<br/><br/>Set Product Costs<br/>equal to Notes Column?", 'update_product_costs', function() {
                $.ajax({
                    type: 'post',
                    data: 'username='+username+'&storeID='+storeID+'&setProductCosts=true',
                    url: 'AuditReport.php',
                    success: function(response) {
                        console.log('success');
                        console.log(response);
                        window.location.reload();
                    },
                    error: function(response) {
                        console.log('error: '+response);
                    },
                });
            });
            break;
        case 'created2prn':
            ScanConfirm("<br/><br/>Show created date<br/>in PRN?", 'created_prn', function() {
                $.ajax({
                    type: 'post',
                    data: 'createdprn=true&username='+username,
                    url: 'AuditReport.php',
                    success: function(response) {
                        console.log('success');
                        console.log(response);
                        window.location.reload();
                    },
                    error: function(response) {
                        console.log('error: '+response);
                    },
                });
            });
            break;
        case 'lpadGeneric':
            ScanConfirm("<br/><br/>LPAD upc column in<br/>GenericUpload?", 'lpad_generic', function() {
                $.ajax({
                    type: 'post',
                    data: 'lpad=true',
                    url: 'AuditReport.php',
                    success: function(response) {
                        console.log('success');
                        console.log(response);
                        window.location.reload();
                    },
                    error: function(response) {
                        console.log('error: '+response);
                    },
                });
            });
            break;
        case 'notes2notes':
            ScanConfirm("<br/><br/>Re-save all notes<br/>to current values?", 'resave_notes', function() {
                let upcs = '';
                let json = {};
                $('.notes').each(function(){
                    let text = $(this).text();
                    let upc = $(this).parent().find('.upc').attr('data-upc');
                    upcs += upc + ', ' + text + "\\n";
                    if (!json.hasOwnProperty(upc)) {
                        json[upc] = '';
                    }
                    json[upc] = text;
                });
                console.log(upcs);
                console.log(json);
                json = JSON.stringify(json);
                $.ajax({
                    type: 'post',
                    data: 'savenotes=true&username='+username+'&notes='+json,
                    url: 'AuditReport.php',
                    success: function(response) {
                        console.log('success');
                        console.log(response);
                        window.location.reload();
                    },
                    error: function(response) {
                        console.log('error: '+response);
                    },
                });
            });
            break;
        case 'getLastPrice':
            ScanConfirm("<br/><br/>Set Notes =<br/>Last Known Price?", 'last_known_price', function() {
                $.ajax({
                    type: 'post',
                    data: 'lastKnownPrice=true&username='+username,
                    url: 'AuditReport.php',
                    success: function(response) {
                        console.log('success');
                        console.log(response);
                        window.location.reload();
                    },
                    error: function(response) {
                        console.log('error: '+response);
                    },
                });
            });
            break;
        case 'setPrnDiff':
            ScanConfirm("<br/><br/>Set PRN < Notes - Price?", 'set_prn_diff', function() {
                $.ajax({
                    type: 'post',
                    data: 'setprndiff=true&username='+username,
                    url: 'AuditReport.php',
                    success: function(response) {
                        console.log('success');
                        console.log(response);
                        window.location.reload();
                    },
                    error: function(response) {
                        console.log('error: '+response);
                    },
                });
            });
            break;
        case 'uncheckall':
            ScanConfirm("<br/><br/>Uncheck all rows?", 'uncheck_all_rows', function() {
                $.ajax({
                    type: 'post',
                    data: 'uncheckall=true&username='+username,
                    url: 'AuditReport.php',
                    success: function(response) {
                        console.log('success');
                        console.log(response);
                        window.location.reload();
                    },
                    error: function(response) {
                        console.log('error: '+response);
                    },
                });
            });
            break;
        case 'futureCostFromNotes':
            let startDate = prompt('Enter future cost START DATE');
            ScanConfirm("<br/><br/>Create Future Vendor Item<br/>costs from notes?", 'update_future_costs', function() {
                $.ajax({
                    type: 'post',
                    data: 'username='+username+'&storeID='+storeID+'&vendorID='+vendorID+'&startDate='+startDate+'&updatefuturecosts=true',
                    url: 'AuditReport.php',
                    success: function(response) {
                        console.log('success');
                        console.log(response);
                        window.location.reload();
                    },
                    error: function(response) {
                        console.log('error: '+response);
                    },
                });
            });
            break;
        case 'ViClearNotes':
            ScanConfirm("<br/><br/>Clear all notes?", 'clear_vi_notes', function() {
                $.ajax({
                    type: 'post',
                    data: 'username='+username+'&storeID='+storeID+'&viclearnotes=true',
                    url: 'AuditReport.php',
                    success: function(response) {
                        console.log('success');
                        window.location.reload();
                    },
                    error: function(response) {
                        console.log('error: '+response);
                    },
                });
            });
            break;
        case 'jsUnitsDivision':
            ScanConfirm("<br/><br/>Divide all notes (case prices) by units?", 'js_units_d_notes', function() {
                $('tr').each(function() {
                    let col1 = $(this).find('td.units').text();
                    col1 = parseFloat(col1);
                    let col2 = $(this).find('td.notes').text();
                    col2 = parseFloat(col2);

                    let answer = col2 / col1;
                    answer = Math.ceil(answer * 1000) / 1000;
                    console.log('col2: '+ col2 + ',' + col2.length);
                    if (answer > 0) {
                        $(this).find('td.notes').text(answer);
                    }
                    
                });
            });
            break;
        case 'jsPrnDivision':
            ScanConfirm("<br/><br/>Divide all notes (case prices) by PRN Column?", 'js_prn_d_notes', function() {
                $('tr').each(function() {
                    let col1 = $(this).find('td.PRN').text();
                    col1 = parseFloat(col1);
                    let col2 = $(this).find('td.notes').text();
                    col2 = parseFloat(col2);

                    let answer = col2 / col1;
                    answer = Math.ceil(answer * 1000) / 1000;
                    console.log('col2: '+ col2 + ',' + col2.length);
                    if (answer > 0) {
                        $(this).find('td.notes').text(answer);
                    }
                    
                });
            });
            break;
        case 'roundPriceNotes':
            ScanConfirm("<br/><br/>Round Notes (Pricing)?", 'js_round_notes', function() {
                $.ajax({
                    type: 'post',
                    data: 'username='+username+'&storeID='+storeID+'&roundPriceNotes=true',
                    url: 'AuditReport.php',
                    success: function(response) {
                        console.log('success');
                        window.location.reload();
                    },
                    error: function(response) {
                        console.log('error: '+response);
                    },
                });
            });
            break;
        default:
            break;
    }
});

var ajaxRespPopOnElm = function(el=false, error=0) {
    var pos = [];

    if  (el == false) {
        let target = $(this);
    }
    let target = $(el);

    let response = (error == 0) ? 'Saved' : 'Error';
    let responseColor = (error == 0) ? '' : '';
    let inputBorder = target.css('border');
    target.css('border', '0px solid transparent');

    let offset = target.offset();
    $.each(offset, function (k,v) {
        pos[k] = parseFloat(v);
    });
    pos['top'] -= 30;
    pos['left'] -= 55;

    

    let zztmpdiv = "<div id='zztmp-div' style='position: absolute; top: "+pos['top']+"; left: "+pos['left']+"; color: black; background-color: white; padding: 5px; border-radius: 5px;border-bottom-right-radius: 0px; border: 1px solid grey;'>"+response+"</div>";
    $('body').append(zztmpdiv);

    setTimeout(function(){
        target.css('border', inputBorder);
        $('#zztmp-div').empty();
        $('#zztmp-div').remove();
    }, 1000);
}

$('#costModeSwitch').change(function(){
    document.forms['costModeForm'].submit();
});

/*
    Press ` to set focus to last column-filter

    Press Enter with only one row showing 
    to check that row's checkbox
*/
$('html').keypress(function(e){
    let keyCode = e.keyCode;
    //console.log('keyCode: '+keyCode);
    if (keyCode == 96) {
        // ` grave acccent was pressed 
        e.preventDefault();
        $(columnFilterLast).focus();
    }
    if (keyCode == 12345) {
        // non existing key was pressed
        e.preventDefault();
        let count = 0;
        $('tr.prod-row').each(function(){
            let isVisible = $(this).is(':visible');
            if (isVisible)
                count++;
        });
        if (count == 1) {
            $('tr.prod-row').each(function(){
                isVisible = $(this).is(':visible');
                if (isVisible) {
                    console.log('trigger checkbox click');
                    let what = $(this).find('.row-check').trigger('click');
                    console.log(what);
                }
            });
        }
    }
});

var holdTimer = 0;

$('html').keydown(function(e){
    let keyCode = e.keyCode;
    if (lastKeyUp.length > 0) {
        lastKeyUp[1] = lastKeyUp[0];
        lastKeyUp[0] = keyCode;
    } else {
        lastKeyUp.push(keyCode);
    }
    console.log(lastKeyUp);

    if (keyCode == 17 && lastKeyUp[0] == 40) {
        console.log("HI!");
    }
});

let btnsdiv = document.createElement('div');
btnsdiv.setAttribute("id", "btnsDiv");
btnsdiv.style.background = "rgba(155,155,155,0.2)";
btnsdiv.innerHTML = "&nbsp;";
btnsdiv.border = "2px solid grey";
btnsdiv.style.width = "120px";
$('#BtnFx1').find('.col-lg-6').append(btnsdiv);

let btnUp = document.createElement('button');
btnUp.innerHTML = '&uarr;';
btnUp.setAttribute("id", "up-btn");
btnUp.classList.add('btn');
btnUp.classList.add('btn-default');
btnUp.classList.add('btn-sm');
btnUp.style.margin = '2px';

let btnDown = document.createElement('button');
btnDown.innerHTML = '&darr;';
btnDown.setAttribute("id", "down-btn");
btnDown.classList.add('btn');
btnDown.classList.add('btn-default');
btnDown.classList.add('btn-sm');
btnDown.style.margin = '5px';

let btnUdc = document.createElement('button');
btnUdc.innerText = 'udc';
btnUdc.setAttribute("id", "udc-btn");
btnUdc.classList.add('btn');
btnUdc.classList.add('btn-default');
btnUdc.classList.add('btn-sm');
btnUdc.style.margin = '5px';

let processing = document.createElement('div');
processing.innerHTML = '&nbsp;';
processing.setAttribute("id", "udc-animation");
processing.style.border = '14px solid lightblue';
processing.style.borderRadius = '100%';
processing.style.position = 'fixed';
processing.style.height = '50px';
processing.style.width = '50px';
processing.style.top = '50%';
processing.style.left = '50%';
processing.style.marginTop = '-25px';
processing.style.marginLeft = '-25px';
processing.style.display = 'none';


$('#btnsDiv').append(btnUp);
$('#btnsDiv').append(btnDown);
$('#btnsDiv').append(btnUdc);
$('body').append(processing);

/*
    Detect Viewport Visibility
    jQuery.expr.finter.offscreen thanks to scurker (2024-07-05):
    --https://stackoverflow.com/questions/8897289/how-to-check-if-an-element-is-off-screen
jQuery.expr.filters.offscreen = function(el) {
    var rect = el.getBoundingClientRect();
    return (
        (rect.x + rect.width) < 0
            || (rect.y + rect.height) < 0
            || (rect.x > window.innerWidth || rect.y > window.innerHeight)
    );
}
*/

$(window).on('scroll', function(){
    var scrollTop = $(this).scrollTop();
    if (scrollTop > 300) {
        $('#btnsDiv').css('position', 'fixed')
            .css('top', '5px')
            .css('left', '5px');
    } else {
        $('#btnsDiv').css('position', 'relative');
    }
});

/*
    Move Selected Item (move .highlight up, down)
*/
var ScreenMiddle = window.innerHeight / 2;

$('#up-btn').on('click', function(){
    $('#mytable').find('.highlight')
        .removeClass('highlight')
        .closest('tr').prev('tr').addClass('highlight');

    let helm = document.getElementsByClassName("highlight")[0];
    while (!$(helm).is(':visible')) {
        let ret = $(helm).removeClass('highlight')
        .closest('tr').prev('tr').addClass('highlight');

        if (!ret.length > 0) {
            break;
        }

        helm = document.getElementsByClassName("highlight")[0];
    }

    const elm = document.getElementsByClassName("highlight")[0];
    if (elm) {
        let ans = elm.getBoundingClientRect();
        console.log(ans.y);
        console.log(ScreenMiddle);
        if (ans.y <= ScreenMiddle) {
            window.scrollBy(0, -25);
        }

        let target = $(elm);
        setCurrentItem(target);
    }
});

$('#down-btn').on('click', function(){
    console.log("I CLICK DOWN!");
    $('#mytable').find('.highlight')
        .removeClass('highlight')
        .closest('tr').next('tr').addClass('highlight');

    let helm = document.getElementsByClassName("highlight")[0];
    while (!$(helm).is(':visible')) {
        let ret = $(helm).removeClass('highlight')
        .closest('tr').next('tr').addClass('highlight');

        if (!ret.length > 0) {
            break;
        }

        helm = document.getElementsByClassName("highlight")[0];
    }

    const elm = document.getElementsByClassName("highlight")[0];
    if (elm) {
        let ans = elm.getBoundingClientRect();
        console.log(ans.y);
        console.log(ScreenMiddle);
        if (ans.y >= ScreenMiddle) {
            window.scrollBy(0, 25);
        }

        let target = $(elm);
        setCurrentItem(target);
    }
});



$('.editable-description, .editable-brand').on('keydown', function(e) {
    let keyCode = e.keyCode;
    let elem = $(this);
    let elemIndex = elem.index();
    let nextElem = null;
    // On TAB use defined behavior
    if (e.keyCode == 9) {

        nextElem = elem.closest('tr').next().find('td:eq('+elemIndex+')');
        while (!nextElem.is(":visible")) {
            nextElem = nextElem.closest('tr').next().find('td:eq('+elemIndex+')');
            if (nextElem.length == 0)
                break;
        }
        if (lastKeyUp.length > 0) {
            // if SHIFT key was pressed, go backward 
            if (lastKeyUp[0] == '16') {
                nextElem = elem.closest('tr').prev().find('td:eq('+elemIndex+')');
                while (!nextElem.is(":visible")) {
                    nextElem = nextElem.closest('tr').prev().find('td:eq('+elemIndex+')');
                    if (nextElem.length == 0)
                        break;
                }
            }
        }

        e.preventDefault();
        nextElem.focus();
    }
});


/*
    Write a CSV file of table 
*/
var CsvTitleData = [];
var CsvTableData = [];
const PrepCSV = function() {

    $('thead').each(function(){
        $(this).find('th').each(function(){
            let value = $(this).text();
            if (value.substring(0, 7) == 'autoPar') {
                // do nothing
            } else {
                value = value.replaceAll(",", " ");
                CsvTitleData.push(value);
            }
        });
    });

    $('tr.prod-row').each(function(){
        let tmpArr = [];
        $(this).find('td').each(function(){
            if ($(this).hasClass('autoPar')) {
                // do nothing
            } else {
                let value = $(this).text();
                tmpArr.push(value);
            }
        });
        CsvTableData.push(tmpArr);
    });

    let thead = JSON.stringify(CsvTitleData);
    let td = JSON.stringify(CsvTableData);
    td = encodeURIComponent(td);

    $.ajax({
        type: 'post',
        data: "exportCsv=1&thead="+thead+"&tableData="+td,
        url: 'AuditReport.php',
        success: function(response) {
            console.log('Export CSV');

            let download = document.createElement("a");
            download.href = 'noauto/'+response;
            download.innerHTML = 'Download File';
            download.style.padding = '5px';
            download.style.borderRadius = '3px';
            download.style.background = 'lightgrey';
            download.style.background = 'linear-gradient(45deg, lightgrey, #FAFAFA, lightgrey)';
            download.style.width = '100px';
            download.style.textAlign = 'center';
            download.addEventListener('click', function(){
                $(this).remove();
            }, false);

            $('#GenerateExcelFileDiv').append(download);
        },
        error: function(response) {
        },
    });
}
$('#ExportCsvAnchor').on('click', function(){
    PrepCSV();
});

$('.description').on('keydown', function(e) {
    let strlen = $(this).text().length;
    console.log(strlen)
    console.log(e.keyCode)
    if (strlen > 29 && e.keyCode != 8) {
        e.preventDefault();
        console.log('max string limit reached')
    }
});

$("#fxBtnNew").click(function() {
    $("#fxExtMenu").css("display", "inline-block");
    $("#fxExtMenu").focus();
});

$("#fxExtMenu").focusout(function() {
    $("#fxExtMenu").css("display", "none");
});

let i=0;
$(".fxExtOption").each(function() {
    let o = '';
    i++;
    if (i < 10)
        o = '0';
    let text = $(this).text();
    $(this).text(o + i + ': ' + text);
});

$(".fxExtOption").on("click", function() {
    $("#fxExtMenu").css("display", "none");
    let chosen = $(this).attr("data-value");
    console.log(chosen);
    $("#extHideFx").val(chosen).trigger('change');
});


$('#currentVendor').on('change', function() {
    let vendorID = $('#currentVendor').val();

    $.ajax({
        type: 'post',
        data: 'setVendorID=1&vendorID='+vendorID,
        dataType: 'json',
        url: 'AuditReport.php',
        success: function(response) {
            //location.reload();
        },
        error: function(response) {
        },
    });
});

var RcResponse = '';
var RcAjax = function(x, y, upc) {
    $.ajax({
        type: 'post',
        data: 'getFamilyItems=1&upc='+upc,
        url: 'AuditReport.php',
        success: function(response) {
            //location.reload();
            console.log(response);
            RcResponse = response;

            y -= 200;

            let menu = document.createElement("div");
            menu.innerHTML = RcResponse;
            //menu.style.position = "fixed";
            menu.style.position = "relative";
            //menu.style.top = y + "px";
            //menu.style.left = x + "px"; 
            menu.style.width = "600px";
            menu.style.border = "1px solid lightgrey";
            menu.style.background = "white";
            //menu.style.maxHeight = "600px";
            menu.classList.add('tmpTable');

            close = document.createElement("span");
            close.innerText = "x";
            close.style.position = "absolute";
            close.style.bottom = "10px";
            close.style.right = "5px"; 
            close.style.width = "10px";
            close.style.height = "10px";
            close.style.cursor = "pointer";
            close.onclick = function() {
                $(this).parent().remove();
            }

            menu.append(close);

            //document.body.append(menu);
            $('#tmpTableContainer').css('top', y + "px")
                .css('left', x + "px")
                .append(menu);
        },
        error: function(jqXHR, textStatus, errorThrown) {
            console.log('ERROR');
            console.error('AJAX Error:', textStatus, errorThrown);
            //console.log(response);
            //table = response;
        },
    });
}
$('.upc').mousedown(function(e) {
    if (e.which == 3) {
        if (window.getSelection() == "" && e.altKey) {
            e.preventDefault();

            let upc = $(this).text();
            var x = event.clientX;
            var y = event.clientY;

            RcAjax(x, y, upc);

        }
    }
});

$('#sessionNotepadText').on('change', function(){
    let contents = $(this).val();
    contents = encodeURIComponent(contents);
    let username = $('#username').val();
    console.log(contents);
    $.ajax({
        type: 'post',
        data: 'sessionNotepad='+contents+'&username='+username,
        url: 'AuditReport.php',
        success: function(response) {
            //location.reload();
            console.log('success');
            console.log('response: '+response);
        },
        error: function(response) {
        },
    });

});
$("#closeSessionNotepad").on('click', function() {
    $("#sessionNotepad").hide();
});

$("#openSessionNotepad").on('click', function() {
    let visible = $("#sessionNotepad").is(":visible");
    if (visible == true) {
        $("#sessionNotepad").hide();
    } else {
        $("#sessionNotepad").show();
    }
});

var navSearchText = [];
$("#nav-search").on('keyup', function() {
    let text = $(this).val();
    navSearchText.push(text);
    console.log('navSearchText: '+navSearchText);
});
$("#nav-search").on('focusout', function() {
    console.log('navSearchText: '+navSearchText);
    let prev = $("#sessionNotepadText").val();
    let cur = navSearchText[navSearchText.length - 1];
    console.log('prev: '+prev);
    console.log('cur: '+cur);
    $("#sessionNotepadText").val(prev + "\\n" + cur);
    $("#sessionNotepadText").trigger('change');
});

$('#udc-btn').on('click', function() {
    let vendorID = $('#currentVendor').val(); 
    let username = $('#username').val();
    $('#udc-animation').show();
    $.ajax({
        type: 'post',
        data: 'udc=1&upc='+currentItem.upc+'&newCost='+currentItem.notes+'&username='+username+'&vendorID='+vendorID,
        dataType: 'json',
        url: 'AuditReport.php',
        success: function(response)
        {
            console.log("RESP: " + response.errors);
            if (response.errors == '') {
                $('.highlight').find('td.notes').text('');
                $('.highlight').find('td.netCost').text(currentItem.notes);
                $('.highlight').find('td.check').find('.row-check').prop('checked', true);
                $('.highlight').addClass('highlight-checked');
            }
            syncItem(currentItem.upc);
        },
        error: function(response, errorThrown)
        {
            console.log(errorThrown);
            console.log("there was an error with your ajax request!");
        },
        complete: function()
        {
            $('#udc-animation').hide();
        },
    });
});


$(document).ready(function() {
    function colorCycle() {
    $("#udc-animation").animate({
        borderColor: "blue" 
    }, 1000).animate({
        borderColor: "#00FF00" // Green
    }, 1000, colorCycle); // Call colorCycle again
}

colorCycle(); // Start the animation
});

$('#btn-show-saved-lists').on('click', function() {
    console.log("OK");
    let isshown = $('#loadListContainer').is(":visible");
    if (isshown) {
        $('#loadListContainer').hide();
    } else {
        $('#loadListContainer').show();
    }
    $('#new-saved-list-filter').focus();
});

$('#new-saved-list-filter').on('keyup', function(e) {
    //console.log(e.key);
    let searchText = $(this).val();
    searchText = searchText.toLowerCase();
    console.log(searchText);

    $('.saved-list-item').each(function() {
        $(this).show();
    });

    $('.saved-list-item').each(function() {

        let itemText = $(this).text();
        itemText = itemText.toLowerCase();
        if (!itemText.includes(searchText)) {
            $(this).hide();
        }
    });
});
JAVASCRIPT;
    }

    public function cssContent()
    {
        $username = ($un = scanLib::getUser()) ? $un : "Generic User";
        $dbc = ScanLib::getConObj();

        $prep = $dbc->prepare("SELECT altHLColor, altHLColorB FROM woodshed_no_replicate.ScannieAuth 
            WHERE name = ?"); 
        $res = $dbc->execute($prep, array($username));
        $row = $dbc->fetchRow($res);
        $altHLColor = $row['altHLColor'];
        $altHLColorB = $row['altHLColorB'];
        $stripeColor = ($altHLColor) ? $altHLColor : "FFFFCC";
        $highlightColor = ($altHLColorB) ? $altHLColorB : "FFB4D9";
        
        $cursor = '';
        if ($username=='csather') {
            $cursor = <<<HTML
    //cursor: url('../../../common/src/img/icons/reptaur-xs-pointer.png'), auto;
HTML;
        }

        return <<<HTML
body {
    $cursor
}
#loadListGui {
    background-color: #F2F2F2;
    display: inline-block;
    border-radius: 3px;
    margin: 5px;
    padding: 3px;
}
div.gui-group {
    background-color: #F2F2F2;
    display: inline-block;
    height: 42px;
    border-radius: 3px;
    margin: 5px;
}
div.gui-group-2 {
    //background-color: #F2F2F2;
    display: inline-block;
    //height: 42px;
    border-radius: 3px;
    //margin: 5px;
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
    //background: linear-gradient(#FFCCE5, #FF99CC);
    background: #$highlightColor;
}
.currentEdit {
    color: purple;
    font-weight: bold;
}
.stripe {
    background: #$stripeColor;
}
.stripe2 {
    background: #FFF9F9;
}
.graystripe {
    background: #F5F5F5;
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
.highlight.highlight-checked {
    color: black;
}

#floating-window {
    z-index: 999;
    min-height: 300px;
    width: 176px;
    background: rgba(255,255,255,0,5);;
    position: fixed;
    top: 0px;
    right: 0px;
    border: 1px solid grey;
    display: block;
    display: none;
}
#fw-border {
    background: lightblue;
    position: relative;
    height: 20px;
    border-bottom: 1px solid grey;
    padding-top: -5px;
    color: black;
    font-size: 13px
}
#fw-text {
    height: 300px;
    width: 100%;
    border: 1px solid transparent;
    outline: none;
}
.load-select-tabs {
    position: absolute;
    margin-top: -30px;
    margin-left: -5px;
    background-color: #F2F2F2;
    padding: 3px;
    font-size: 13px;
    border-top-right-radius: 5px;
    border-top-left-radius: 5px;
    font-weight: bold;
    color: grey;
    padding-left: 6px;
    padding-right: 6px;
    border-bottom: 0.5px solid lightgrey;
}
.mini-q {
    font-size: 10px;
    cursor: pointer;
}
tr.prod-row:hover {
}
#fxExtMenu {
    display: none;
    width: 600px;
    height: 300px;
    position: fixed;
    top: 50%;
    left: 50%;
    z-index: 9999;
    margin: -150px 0px 0px -300px;
}
.fxExtOptionContainer {
    border: 1px solid lightgrey;
    background: white;
    padding: 25px;
    overflow: auto;
    height: 300px;
    border-bottom-left-radius: 4px;
    border-bottom-right-radius: 4px;
}
.fxExtHeader {
    padding: 5px;
    padding-left: 30px;
    //background: linear-gradient(white 90%, lightblue);
    //background: lightblue;
    background: repeating-linear-gradient(#343A40,  #565E66, #343A40 5px);
    color: plum;
    //text-shadow: 1px 1px lightgrey;
    font-size: 16px;
    margin-top: -22;
    border-top-right-radius: 4px;
    border-top-left-radius: 4px;
    font-weight: bold;
}
.fxExtOption {
    font-size: 16px;
    cursor: pointer;
    margin-bottom: 5px;
    padding: 5px;
}
.fxExtOption:hover {
    //background: #FFC8BF;
    background: lightgrey;
    background: linear-gradient(45deg, lightgrey, white);
}
//#simpleInputCalc {
//    -webkit-transform: perspective(600px) rotateX(10deg);
//    -moz-transform: perspective(600px) rotateX(10deg);
//    -ms-transform: perspective(600px) rotateX(10deg);
//    -o-transform: perspective(600px) rotateX(10deg);
//    transform: perspective(600px) rotateX(10deg);
//}
#tmpTableContainer {
    width: 600px;
    z-index: 9999;
    position: fixed;
    top: 0px;
    left: 0px;
    background: lightgrey;
}
#sessionNotepad {
    display: none;
    border: 2px solid plum;
    background: rgba(255, 255, 255, 0.9);

    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 500px;
    z-index: 9999;
    padding: 25px;
}
#sessionNotepadText {
    border: 1px solid lightgrey;
}
#percentComplete2 {
    width: 250px;
}
#loadListContainer {
    display: none;
    max-height: 500px;
    overflow: auto;
    background: white;
    color: white;
    position: absolute;
    z-index: 9999;
    border: 1px solid lightgrey;
    padding: 4px;
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

    <li>Tables</li>
    <ul>
        <li><strong>Products Table</strong> This is the default table view. In this view, the client will see information
            as it applies to the default vendor IDs of items.</li>
        <li><strong>Vendor Items Table</strong> Parameter: a vendor ID must be entered for this view to work properly (start by
            loading a Vendor List). With this parameter entered, the client will see SKUs as they pertain to that vendor ID
            regardless of the current products default vendor ID. The client will also see the <strong>vcost</strong> column
            populated with data. This is the cost of the item according to the selected vendor ID.</li>
    </ul>

    <li>Current Vendor</li>
    <p>By default the Current Vendor is left blank. To set the current vendor, load that vendor's list (more items may be loaded after if needed).</p>

    <li>Definition of Columns</li>
    <p>Each checkbox in the <strong>Show/Hide Columns</strong> correlates with a column to show or hide in the Audit Report table.</p>
    <ul>
        <li><strong>Check</strong> Show checkboxes for each row.</li>
        <li><strong>UPC</strong> Numerical barcode for each item.</li>
        <li><strong>SKU</strong> Current SKU for each item in respect to the default vendor ID.</li>
        <li><strong>Alias</strong> Vendor alias, if any. Used for break-down and bulk items (assign one or more items to another item).</li>
        <li><strong>Like Code</strong> POS like code. Items grouped together as one item.</li>
        <li><strong>Brand*</strong> POS brand that shows up on shelf tags.</li>
        <li><strong>Sign-Brand*</strong> Brand that shows up on Sale/special signage.</li>
        <li><strong>Description*</strong> POS description on shelf tags.</li>
        <li><strong>Sign-Description*</strong> Special sign description.</li>
        <li><strong>Size</strong> Size of 1 unit of products.</li>
        <li><strong>Units</strong> Case size from vendor.</li>
        <li><strong>NetCost</strong> POS product cost before adjustments for shipping or discounts.</li>
        <li><strong>Cost</strong> POS product cost <i>after</i> adjustments.</li>
        <li><strong>VCost</strong> vendor item cost (Current Vendor must be set).</li>
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
        <li><strong>RSRP</strong> WFC calculated SRP before applying rounding rules.</li>
        <li><strong>SRP</strong> SRP as read from vendorItems table.</li>
        <li><strong>PRID</strong> Price rule ID.</li>
        <li><strong>PRT</strong> Price rule description [sic].</li>
        <li><strong>Tax</strong> Current tax status of item.</li>
        <li><strong>Dept</strong> Department.</li>
        <li><strong>Subdept</strong> Sub Department.</li>
        <li><strong>Local</strong> Local setting.</li>
        <li><strong>Flags</strong> Lists all current flags for item.</li>
        <li><strong>Subdept</strong> Sub Department.</li>
        <li><strong>Vendor</strong> Default vendor.</li>
        <li><strong>Last Sold</strong> Show the date each item was last sold at each store.</li>
        <li><strong>Scale Item</strong> Scale item type.</li>
        <li><strong>Scale PLUS</strong> Lists only the PLU as it should be entered into the scale.</li>
        <li><strong>Tare</strong> Current scale tare weight.</li>
        <li><strong>mnote</strong> For IT use. If a valid float is entered into the Notes column,
            clicking the mnote button will move that float to and update the cost of the item.</li>
        <li><strong>Notes</strong> A freely editable field.</li>
        <li><strong>Reviewed</strong> Shows the last time each product was reviewed, in respect to Fannie Product Review.</li>
        <li><strong>Cost Change</strong> Most recent cost change, taken only from when the <i>Review</i> button option is used.</li>
        <li><strong>Floor Sections</strong> Product physical locations.</li>
        <li><strong>Comment</strong> Any review comments entered for an item.</li>
        <li><strong>PRN</strong> A blank,  uneditable field. For IT use (it's just a second field to use for plugging in and comparing data).</li>
        <li><strong>Case Cost</strong> Shows the Cost column multiplied by the Units column.</li>
        <li><strong>*</strong> Columns with an asterisk in this list are editable fields.</li>
    </ul>
    <li>Forms & Functions (Grey Boxes)</li>
    <ul>
        <li><strong>Saved Lists</strong> Load a previously saved list of items. If a list is already loaded, there will also 
            be an option to remove this list from Saved Lists.</li>
        <li><strong>Save List As</strong> Save the current list of items.</li>
        <li><strong>Load by Vendor</strong> Loads an entire vendor catalog. By default, only loads items that are in use (at at least one store), and only items where the selected vendor is the default (ordering) vendor for those items.
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
    <li>More Filters & Methods (Button & Menu)</li>
    <p>Opens a growing list of additional filters & methods. Most methods will only show up for staff with Admin privileges.</p>
    <li>Special Hotkeys</li>
    <ul>
        <li>ALT + Right Click (on a UPC) - will open a separate table of all related items (by family code, brand, and size)</li>
        <li> ~ - Will return the focus to the last table header filter that was used</li>
    </ul>
</ul>
HTML;
    }

}
WebDispatch::conditionalExec();
