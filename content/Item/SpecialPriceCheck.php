<?php
/*******************************************************************************
    Copyright 2016 Whole Foods Community Co-op.

    This file is a part of Scannie.

    Scannie is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.
    Scannie is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
    You should have received a copy of the GNU General Public License
    in the file LICENSE along with Scannie; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
class SpecialPriceCheck extends WebDispatch 
{

    protected $title = "Special Price Check Version 2.0";
    protected $description = "[Special Price Check] Scans both Back
        and Front End DBMS for products that are either erroneously 
        priced in respect to current sales batches or are erroneously 
        missing a special_price at the lanes.";
    protected $ui = false;
    protected $upcs = array();

    private function getCurrentSales() 
    {
        $data = array();
        $upcs1 = array();
        $upcs2 = array();
        $dbc = ScanLib::getConObj();
        // this query takes 19 seconds. How can I make this process faster?
        $prep = $dbc->prepare("SELECT 
                p.upc, salePrice, m.storeID, bl.batchID, p.brand, p.description
            FROM batchList AS bl
                LEFT JOIN batches AS b ON bl.batchID=b.batchID
                LEFT JOIN StoreBatchMap AS m ON bl.batchID=m.batchID
                LEFT JOIN products AS p ON p.upc=bl.upc
            WHERE endDate >= NOW() 
                AND startDate <= NOW()
                AND salePrice > 0
            ");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            $upc = $row['upc'];
            $salePrice = $row['salePrice'];
            $storeID = $row['storeID'];
            $batchID = $row['batchID'];
            $brand = $row['brand'];
            $desc = $row['description'];
            $data[$upc][$storeID]['salePrice'] = $salePrice;
            $data[$upc][$storeID]['batchID'] = $batchID;
            $data[$upc][$storeID]['brand'] = $brand;
            $data[$upc][$storeID]['desc'] = $desc;
            if ($storeID == 1)
                $upcs1[] = $upc;
            if ($storeID == 2)
                $upcs2[] = $upc;
        }
        echo $dbc->error();

        return array($data, $upcs1, $upcs2);
    }

    private function checkCurrentSales($upcs, $store)
    {
        $data = array();
        $dbc = ScanLib::getConObj();
        list($inStr, $args) = $dbc->safeInClause($upcs);
        $args[] = $store;

        $prep = $dbc->prepare("SELECT 
                upc, special_price, store_id
            FROM products 
            WHERE upc IN ($inStr)
                AND special_price = 0
                AND store_id = ?");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $upc = $row['upc'];
            $storeID = $row['store_id'];
            $data[$upc][$storeID] = 1;
        }
        echo $dbc->error();

        return $data;
    }

    private function checkLaneSales($upcs, $store, $lane)
    {
        $data = array();
        $dbc = $this->getLaneDbcObj($lane, 'opdata');
        list($inStr, $args) = $dbc->safeInClause($upcs);
        $args[] = $store;

        $prep = $dbc->prepare("SELECT 
                upc, special_price, store_id
            FROM products 
            WHERE upc IN ($inStr)
                AND special_price = 0
                AND store_id = ?");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $upc = $row['upc'];
            $storeID = $row['store_id'];
            $data[$upc][$storeID] = 1;
        }
        echo $dbc->error();

        return $data;
    }

    public function body_content()
    {
        $missing = array();
        $missingLane = array();
        list($allSales, $upcs1, $upcs2) = $this->getCurrentSales();
        $td1 = "";
        $td2 = "";
        $td3 = "";

            $missing[] = $this->checkCurrentSales($upcs2, 2);

            foreach ($missing as $upcs) {
                foreach ($upcs as $upc => $stores) {
                    foreach ($stores as $storeID => $v) {
                        $td1 .= "<tr>";
                        $td1 .= "<td>{$upc}</td>";
                        $td1 .= "<td>{$storeID}</td>";
                        $td1 .= "<td>{$allSales[$upc][$storeID]['salePrice']}</td>";
                        $td1 .= "<td>{$allSales[$upc][$storeID]['batchID']}</td>";
                        $td1 .= "<td>{$allSales[$upc][$storeID]['brand']}</td>";
                        $td1 .= "<td>{$allSales[$upc][$storeID]['desc']}</td>";
                        $td1 .= "</tr>";
                    }
                }
            }

                foreach ($this->config->vars['FANNIE_HIL_LANES'] as $lane) {
                    $missingLane[] = $this->checkLaneSales($upcs1, 1, $lane);
                }
                foreach ($missingLane as $upcs) {
                    foreach ($upcs as $upc => $stores) {
                        foreach ($stores as $storeID => $v) {
                            $td2 .= "<tr>";
                            $td2 .= "<td>{$upc}</td>";
                            $td2 .= "<td>{$storeID}</td>";
                            $td2 .= "<td>{$allSales[$upc][$storeID]['salePrice']}</td>";
                            $td2 .= "<td>{$allSales[$upc][$storeID]['batchID']}</td>";
                            $td2 .= "<td>{$allSales[$upc][$storeID]['brand']}</td>";
                            $td2 .= "<td>{$allSales[$upc][$storeID]['desc']}</td>";
                            $td2 .= "</tr>";
                        }
                    }
                }

                unset($missingLane);
                foreach ($this->config->vars['FANNIE_DEN_LANES'] as $lane) {
                    try {
                        $missingLane[] = $this->checkLaneSales($upcs1, 1, $lane);
                    } catch (Exception $e) {
                        echo $e->getMessage();
                    }
                }
                foreach ($missingLane as $upcs) {
                    foreach ($upcs as $upc => $stores) {
                        foreach ($stores as $storeID => $v) {
                            $td3 .= "<tr>";
                            $td3 .= "<td>{$upc}</td>";
                            $td3 .= "<td>{$storeID}</td>";
                            $td3 .= "<td>{$allSales[$upc][$storeID]['salePrice']}</td>";
                            $td3 .= "<td>{$allSales[$upc][$storeID]['batchID']}</td>";
                            $td3 .= "<td>{$allSales[$upc][$storeID]['brand']}</td>";
                            $td3 .= "<td>{$allSales[$upc][$storeID]['desc']}</td>";
                            $td3 .= "</tr>";
                        }
                    }
                }


        return <<<HTML

<div id="salePriceDiscrepContainer">
    <button type="button" class="close btn-default" aria-label="Close" onclick="
        $('#salePriceDiscrepContainer').hide();
        var elm = parent.document.getElementById('specIframe');
        elm.style.height = '202px';
        elm.style.display = 'none';
    "><span aria-hidden="true">&times;</span>
    </button>
    <h4><i>Unforced/Unsynced Sales</i> found on SERVER</h4>
    <table class="table table-bordered table-sm small">
        <thead><th>upc</th><th>storeID</th><th>sale price</th><th>batchID</th><th>brand</th><th>description</th></thead>
        <tbody>$td1</tbody>
    </table>
    <h4><i>Unforced/Unsynced Sales</i> found on Hillside POS Lanes</h4>
    <table class="table table-bordered table-sm small">
        <thead><th>upc</th><th>storeID</th><th>sale price</th><th>batchID</th><th>brand</th><th>description</th></thead>
        <tbody>$td2</tbody>
    </table>
    <h4><i>Unforced/Unsynced Sales</i> found on Denfeld POS Lanes</h4>
    <table class="table table-bordered table-sm small">
    <table class="table table-bordered table-sm small">
        <thead><th>upc</th><th>storeID</th><th>sale price</th><th>batchID</th><th>brand</th><th>description</th></thead>
        <tbody>$td3</tbody>
    </table>
</div>
HTML;
    }

    private function getLaneDbcObj($h, $db)
    {
        $USER = $this->config->vars['SCANUSER'];
        $PASS = $this->config->vars['SCANPASS'];
        $dbc = new SQLManager($h, 'pdo_mysql', $db, $USER, $PASS);

        return $dbc;
    }
    
    public function cssContent()
    {
        return <<<HTML
h4 {
    backgorund: lightgrey;
    background: linear-gradient(to right, lightgrey, white, white);
}
HTML;
    }

}
WebDispatch::conditionalExec();
