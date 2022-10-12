<?php
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
if (!class_exists('priceRounder')) {
    include_once(__DIR__.'/../../common/lib/PriceRounder.php');
}
class OutOfUseVendorPricing extends PageLayoutA
{

    protected $must_authenticate = true;
    protected $auth_types = array(2);

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();
        $this->__routes[] = 'post<something>';

        return parent::preprocess();
    }

    public function pageContent()
    {
        $dbc = ScanLib::getConObj();
        $rounder = new PriceRounder();
        $vendorID = FormLib::get('vendorID', false);
        $today = new DateTime();
        $items = array();
        $ret = '';
        $table = '';
        $vendorName = '';
        $td = '';
        $counts = '';

        $vendorOpts = "<option value=0>Choose A Vendor</option>";
        $vendP = $dbc->prepare("SELECT vendorID, vendorName
            FROM vendors");
        $vendR = $dbc->execute($vendP);
        while ($row = $dbc->fetchRow($vendR)) {
            $sel = ($row['vendorID'] == $vendorID) ? ' selected ' : '';
            if ($sel == ' selected ') { 
                $vendorName = $row['vendorName'];
            }
            $vendorOpts .= "<option value=\"{$row['vendorID']}\" $sel>{$row['vendorName']}</option>";
        }

        $json = "
{
\"startDate\":\"1900-01-01 00:00:00\",
\"endDate\":\"1900-01-01 00:00:00\",
\"batchName\":\"Unsold $vendorName PC {$today->format('Y\/m\/d')} INC.\",
\"batchType\":\"4\",
\"discountType\":\"0\",
\"priority\":\"0\",
\"owner\":\"All\",
\"transLimit\":\"0\",
\"items\":[
";

        // 1. grab every single item in catalog
        $infoA = array($vendorID);
        $infoP = $dbc->prepare("
            SELECT
                p.upc, 
                SUBSTRING(p.brand,1,10) AS brand, 
                SUBSTRING(p.description,1,16) AS description, 
                p.normal_price, v.srp, DATE(p.last_sold) AS last_sold, p.store_id,
                SUBSTRING(m.super_name,1,6) AS super_name,
                p.inUse, 
                CASE
                    WHEN vsm.margin > 0.00 THEN vsm.margin ELSE dm.margin
                END AS margin,
                CASE
                    WHEN vsm.margin > 0.00 THEN 'VSM' ELSE 'DM'
                END AS marginText,
                p.cost, 
                SUBSTRING(prt.description,1,5) AS price_rule_id 
            FROM products AS p
                LEFT JOIN vendorItems AS v ON v.upc=p.upc AND v.vendorID=p.default_vendor_id
                LEFT JOIN MasterSuperDepts As m ON m.dept_ID = p.department
                LEFT JOIN prodReview AS r ON r.upc=p.upc
                LEFT JOIN VendorSpecificMargins AS vsm ON vsm.deptID=p.department 
                    AND vsm.vendorID=p.default_vendor_id
                LEFT JOIN deptMargin AS dm ON dm.dept_ID=p.department 
                LEFT JOIN PriceRules AS pr ON pr.priceRuleID=p.price_rule_id
                LEFT JOIN PriceRuleTypes AS prt ON prt.priceRuleTypeID=pr.priceRuleTypeID
            WHERE p.default_vendor_id = ?
                AND super_name NOT IN ('WELLNESS','PRODUCE')
                AND p.cost > 0
            ORDER BY prt.description, p.upc 
        ");

        $infoR = $dbc->execute($infoP, $infoA);
        while ($row = $dbc->fetchRow($infoR)) {
            $upc = $row['upc'];
            $storeID = $row['store_id'];
            $lastSold = $row['last_sold'];
            $brand = $row['brand'];
            $description = $row['description'];
            $price = $row['normal_price'];
            $srp = $row['srp'];
            $inUse = $row['inUse'];
            $margin = $row['margin'];
            $marginText = $row['marginText'];
            $cost = $row['cost'];
            $super = $row['super_name'];
            $prid = $row['price_rule_id'];
            $items[$upc][$storeID]['lastSold'] = $lastSold;
            $items[$upc][$storeID]['brand'] = $brand;
            $items[$upc][$storeID]['description'] = $description;
            $items[$upc][$storeID]['price'] = $price;
            $items[$upc][$storeID]['srp'] = $srp;
            $items[$upc][$storeID]['super'] = $super;
            $items[$upc][$storeID]['inUse'] = $inUse;
            $items[$upc][$storeID]['margin'] = $margin;
            $items[$upc][$storeID]['marginText'] = $marginText;
            $items[$upc][$storeID]['cost'] = $cost;
            $items[$upc][$storeID]['prid'] = $prid;
        }

        $counts .= "Items found in vendor catalog: ".count($items)."<br/>";

        // 2. remove every upc that in inUse at a store
        foreach ($items as $upc => $array) {
            foreach ($array as $storeID => $row) {
                if ($row['inUse'] == 1) {
                    unset($items[$upc]);
                }
            }
        }

        $counts .= "Items after removing in-use products: ".count($items)."<br/>";


        /*
            remove recently sold items
        */
        $dateB = new DateTime();
        $dateB->sub(new DateInterval('P2M'));
        foreach ($items as $upc => $array) {
            foreach ($array as $storeID => $row) {
                if ($row['inUse'] == 1) {
                    unset($items[$upc]);
                }
                $dateA = new DateTime($row['lastSold']);
                if ($dateA > $dateB) {
                    unset($items[$upc]);
                }
            }
        }

        $counts .= "Items after removing products with recent sales: ".count($items)."<br/>";

        $last = '';
        $countf = 0;
        foreach ($items as $upc => $stores) {
            foreach ($stores as $store => $storeRow) {
                if ($upc != $last) {
                    $price = $storeRow['price'];
                    $price = floatval($price);
                    $srp = $storeRow['srp'];
                    $brand = $storeRow['brand'];
                    $desc = $storeRow['description'];
                    $lastSold = $storeRow['lastSold'];
                    $super = $storeRow['super'];
                    $margin = $storeRow['margin'];
                    $marginText = $storeRow['marginText'];
                    $cost = $storeRow['cost'];
                    $prid = $storeRow['prid'];

                    $srp2 = $cost / (1-$margin);
                    $srp2 = $rounder->round($srp2);

                    $trCol = '';
                    if ($srp2 == $price) {
                        $trCol = ' tomato ';
                    }
                    if ($prid != '') {
                        $trCol = ' orange ';
                    }

                    if ($srp2 == $price) {
                        // Skip, no price change necessary
                    } else {
                        $countf++;
                        $ret .= "<div>price: {$storeRow['price']}, srp: {$storeRow['srp']}</div>";
                        $td .= "<tr style=\"background-color: $trCol\">";
                        $td .= "<td>$upc</td>";
                        $td .= "<td>$super</td>";
                        $td .= "<td>$brand</td>";
                        $td .= "<td>$desc</td>";
                        $td .= "<td>$price</td>";
                        $td .= "<td>$lastSold</td>";
                        $td .= "<td>$cost</td>";
                        $td .= "<td>$margin ($marginText)</td>";
                        $td .= "<td>$srp2</td>";
                        $td .= "<td>$prid</td>";
                        $td .= "</tr>";
                        $json .= ($prid == '') ? "
 {
\"upc\":\"$upc\",
\"salePrice\":\"$srp2\",
\"groupSalePrice\":null,
\"active\":null,
\"pricemethod\":\"0\",
\"quantity\":\"0\",
\"signMultiplier\":\"1\"
}," : '';
                    }


                    $last = $upc;
                }
            }
        }
        $json = rtrim($json, ",");
        $json = $json . " ] }";
        $counts .= "Items with prices that don't match WFC-SRP: $countf<br/>";

        return <<<HTML
<div class="container-fluid" style="padding:15px">
<h4>Out Of Use Vendor Items To PC Batch</h4>
    <div class="row">
        <div class="col-lg-9">
            $counts
            <table class="table table-bordered small">
                <thead><th>upc</th><th>$super</th><th>brand</th><th>description</th><th>price</th>
                    <th>last sold</th><th>cost</th><th>margin</th><th>WFC-SRP</th><th>PR</th></thead>
                <tbody>$td</tbody></table>
            <form name="updateForm" id="updateForm" method="post" action="OutOfUseVendorPricing.php">
            </form>
        </div>
        <div class="col-lg-3">
            <form action="OutOfUseVendorPricing.php" method="post">
            <div class="form-group">
                <select class="form-control" name="vendorID" id="vendorID">$vendorOpts</select>
            </div>
            <div class="form-group">
                <input type="submit" class="btn btn-default form-control">
            </div>
            <label for="json">Copy & Paste into <a href="../../../git/fannie/batches/newbatch/BatchImportExportPage.php" target="_blank">Batch Import/Export</a> to create batch</label>
            <textarea id="json" class="form-control copy-text" rows="10">$json</textarea>
        </div>
    </div>
</form>
</div>
HTML;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
$('.copy-text').focus(function(){
    $(this).select();
    var status = document.execCommand('copy');
    if (status == true) {
        $(this).parent().find('.status-popup').show()
            .delay(400).fadeOut(400);
    }
});
JAVASCRIPT;
    }

    public function cssContent()
    {
        return <<<HTML
HTML;
    }

    public function helpContent()
    {
        return <<<HTML
HTML;
    }

}
WebDispatch::conditionalExec();
