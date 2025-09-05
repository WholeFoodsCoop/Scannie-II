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
class Dashboard extends PageLayoutA 
{

    protected $title = "Scannie Dashboard";
    protected $description = "[Dashboard] .";
    protected $ui = TRUE;
    protected $ALTDB = "";

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();

        return false;
    }

    public function pageContent()
    {
        $dealSets = array();
        $SCANALTDB = $this->config->vars['SCANALTDB'];
        $MY_ROOTDIR = $this->config->vars['MY_ROOTDIR'];
        $curEdlpSet = '';
        if (isset($_SESSION['dashEdlpSet'])) {
            $curEdlpSet = $_SESSION['dashEdlpSet'];
        } else {
            $date = new DateTime('first day of this month');
            $curEdlpSet = $date->format('Y-m-d');
            $_SESSION['dashEdlpSet'] = $curEdlpSet;
        }
        if (isset($_SESSION['dashEdlpSet']))
            $_SESSION['dashEdlpSet'];
        $this->ALTDB = $SCANALTDB;
        $ret = '';
        $dbc = scanLib::getConObj();
        $data = "";
        $d = new DateTime();
        $datetime = $d->format('Y-m-d H:i');

        $dealSetP = $dbc->prepare("select dealSet from CoopDealsItems group by dealSet order by coopDealsItemID DESC limit 2");
        $dealSetR = $dbc->execute($dealSetP);
        while ($dealSetW = $dbc->fetchRow($dealSetR)) {
            $dealSets[] = $dealSetW['dealSet'];
        }

        $tdReview = "";
        $pre = $dbc->prepare("SELECT 
store_id,
SUM(CASE WHEN r.reviewed > DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS Thirty,
SUM(CASE WHEN r.reviewed BETWEEN DATE_SUB(NOW(), INTERVAL 60 DAY) AND DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS Sixty,
SUM(CASE WHEN r.reviewed BETWEEN DATE_SUB(NOW(), INTERVAL 90 DAY) AND DATE_SUB(NOW(), INTERVAL 60 DAY) THEN 1 ELSE 0 END) AS Ninty,
SUM(CASE WHEN r.reviewed < DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) AS Longer 
FROM products AS p 
LEFT JOIN prodReview AS r ON p.upc=r.upc 
LEFT JOIN MasterSuperDepts AS m ON p.department=m.dept_ID
WHERE last_sold > DATE_SUB(NOW(), INTERVAL 30 DAY)
AND m.super_name NOT IN ('BRAND', 'MISC', 'PRODUCE')
AND p.created < DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY store_id
ORDER BY r.reviewed;");
        $res = $dbc->execute($pre);
        while ($row = $dbc->fetchRow($res)) {
            $id = $row['store_id'];
            $three = $row['Thirty'];
            $six = $row['Sixty'];
            $nine = $row['Ninty'];
            $long = $row['Longer'];
            $tdReview .= "<tr>";
            $tdReview .= "<td>$id</td><td>$three</td><td>$six</td><td>$nine</td><td>$long</td>";
            $tdReview .= "</tr>";
        }
        if ($dbc->error())
            $tdReview.= "<div class=\"alert alert-danger\">{$dbc->error()}</div>";
        //echo "<div class=\"alert alert-danger\">{$dbc->error()}</div>";

        $tdDetailed = "";
        $pre = $dbc->prepare("SELECT 
v.vendorName AS VendorName,
COUNT(DISTINCT p.upc) AS ProductCount,
SUM(CASE WHEN r.reviewed > DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS Thirty,
SUM(CASE WHEN r.reviewed BETWEEN DATE_SUB(NOW(), INTERVAL 60 DAY) AND DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS Sixty,
SUM(CASE WHEN r.reviewed BETWEEN DATE_SUB(NOW(), INTERVAL 90 DAY) AND DATE_SUB(NOW(), INTERVAL 60 DAY) THEN 1 ELSE 0 END) AS Ninty,
SUM(CASE WHEN r.reviewed < DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) AS Longer 
FROM products AS p 
LEFT JOIN prodReview AS r ON p.upc=r.upc 
LEFT JOIN MasterSuperDepts AS m ON p.department=m.dept_ID
LEFT JOIN vendors AS v ON p.default_vendor_id=v.vendorID
WHERE last_sold > DATE_SUB(NOW(), INTERVAL 30 DAY)
AND m.super_name NOT IN ('BRAND', 'MISC', 'PRODUCE')
AND p.created < DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY v.vendorID
ORDER BY COUNT(p.upc) DESC");
        $res = $dbc->execute($pre);
        while ($row = $dbc->fetchRow($res)) {
            $vendorName = $row['VendorName'];
            $count = $row['ProductCount'];
            $three = $row['Thirty'];
            $six = $row['Sixty'];
            $nine = $row['Ninty'];
            $long = $row['Longer'];
            $tdDetailed .= "<tr>";
            $tdDetailed .= "<td>$vendorName</td><td>$count</td><td>$three</td><td>$six</td><td>$nine</td><td>$long</td>";
            $tdDetailed .= "</tr>";
        }
        if ($dbc->error())
            $tdDetailed.= "<div class=\"alert alert-danger\">{$dbc->error()}</div>";


        $reports = array(
            array(
                'handler' => self::getOwnerSoOam($dbc), 
                'ranges' => array(0, 3, 999),
            ),
            array(
                'handler' => self::getBogoSoBadPrice($dbc), 
                'ranges' => array(0, 3, 999),
            ),
            array(
                'handler' => self::getOffSrps($dbc), 
                'ranges' => array(0, 50, 999),
            ),
            array(
                'handler' => self::getGenericPRIDItems($dbc), 
                'ranges' => array(10, 100, 999),
            ),
            array(
                'handler' => self::getSmsZeroPriceItems($dbc), 
                'ranges' => array(0, 4, 999),
            ),
            array(
                'handler' => self::getSmsRedundantPriceTabItems($dbc), 
                'ranges' => array(50, 100, 999),
            ),
            array(
                'handler' => self::getProdMissingCost($dbc), 
                'ranges' => array(0, 4, 999),
            ),
            array(
                'handler' => self::getProdMissingVendor($dbc), 
                'ranges' => array(0, 4, 999),
            ),
            /*
            array(
                'handler' => self::getMissingMovementTags($dbc), 
                'ranges' => array(99, 999, 9999),
            ),
            */
            array(
                'handler' => self::getVendorList($dbc), 
                'ranges' => array(0, 1, 99),
            ),
            /*
            array(
                'handler' => self::getMissingSKU($dbc),
                'ranges' => array(0, 50, 999999),
            ),
            */
            array(
                'handler' => self::getVendorSkuDiscrep($dbc),
                'ranges' => array(0, 100, 9999),
            ),

            /*
            array(
                'handler' => self::getAliasMultipleUpcs($dbc),
                'ranges' => array(0, 5, 9999),
            ),
            */

            array(
                'handler' => self::getProdsMissingLocation($dbc),
                'ranges' => array(0, 100, 99999),
            ),
            array(
                'handler' => self::getMissingScaleItems($dbc),
                'ranges' => array(0, 5, 999),
            ),
            array(
                'handler' => self::badPriceCheck($dbc),
                'ranges' => array(0, 5, 999),
            ),
            array(
                'handler' => self::getBreakdownBadPrice($dbc), 
                'ranges' => array(2, 10, 9999),
            ),
            // class isn't currently comparing prices, it's just
            // grabbing every aliased item that's in a batch
            array(
                'handler' => self::getBreakdownBadBatchPrice($dbc), 
                'ranges' => array(2, 10, 9999),
            ),
            array(
                'handler' => self::limboPcBatch($dbc),
                'ranges' => array(0, 2, 999),
            ),
            array(
                'handler' => self::badDeliDepts($dbc),
                'ranges' => array(0, 2, 999),
            ),
            array(
                'handler' => self::organicFlags($dbc),
                'ranges' => array(0, 10, 999),
            ),
            array(
                'handler' => self::organicDesc($dbc),
                'ranges' => array(0, 10, 9999),
            ),
            array(
                'handler' => self::getZeroScaleItems($dbc),
                'ranges' => array(0, 10, 9999),
            ),
            array(
                'handler' => self::getOneScaleItems($dbc),
                'ranges' => array(0, 10, 9999),
            ),
            array(
                'handler' => self::getLocalDiscrepancies($dbc),
                'ranges' => array(0, 10, 999),
            ),
            array(
                'handler' => self::getZeroVendorItems($dbc),
                'ranges' => array(5, 10, 999),
            ),
            /*
            array(
                'handler' => self::getNewSuperValueItems($dbc),
                'ranges' => array(1, 10, 999),
            ),
            */
            array(
                'handler' => self::getProdMissingEDLP($dbc),
                'ranges' => array(0, 10, 999),
            ),
            array(
                'handler' => self::getBadBogoDeals($dbc),
                'ranges' => array(0, 10, 999),
            ),
            array(
                'handler' => self::getProdMissingSale($dbc),
                'ranges' => array(0, 10, 999),
            ),
            array(
                'handler' => self::getTooBigUPC($dbc),
                'ranges' => array(0, 10, 999),
            ),
            array(
                'handler' => self::checkDeliProductionDepts($dbc),
                'ranges' => array(0, 10, 999),
            ),
            array(
                'handler' => self::getBadSaleCost($dbc),
                'ranges' => array(0, 5, 9999),
            ),
            array(
                'handler' => self::getWatchList($dbc),
                'ranges' => array(0, 5, 9999),
            ),
        );

        $muData = $this->multiStoreDiscrepCheck($dbc);
        $multi = $this->getReportHeader(array('desc'=>'Discrepancies between stores', 'data'=>$muData['data']), array(5, 10, 999));
        $multi .= " <button class='btn-collapse' data-target='#tableMulti' type='button'>view</button><br/>";
        $multi .= "<div id='tableMulti' class='table-responsive-lg'>";
        $multi .= "<div class='card'><div class='card-body' style='overflow-x: scroll'>";
        $multi .= $muData['table'] . "</div></div></div>";

        $table = "";
        foreach ($reports as $row) {
            $data = $row['handler'];
            $table .= $this->getReportHeader($data, $row['ranges']);
            $table .= self::getTable($data);
        }

        //$edlpMonths = $this->getEdlpMonths($curEdlpSet);
        //$dealSetSelector = $this->getDealSetSelector($dealSets);
        $futureVendorItemsT = $this->getFutureVendorItems();
        
        $this->addScript('http://'.$MY_ROOTDIR.'/common/javascript/tablesorter/js/jquery.tablesorter.min.js');
        $this->addScript('http://'.$MY_ROOTDIR.'/common/javascript/tablesorter/js/jquery.metadata.js');
        $this->addOnloadCommand('$(".table").tablesorter();');

        return <<<HTML
<div class="container-fluid">
    <div style="margin-top: 20px;"></div>
    <div class="card">
        <div class="card-content">
            <div class="card-body">
                <div class="card-title">
                    <h2>Scannie Dashboard</h2>
                </div>
                <div class="row">
                    <div class="col-lg-6">
                        $table 
                        $multi
                    </div>
                    <div class="col-lg-6">
                        <label style="border: 1px solid #E9ECEF; background-color: #F2F4F7; 
                            margin-bottom: -2px; border-top-right-radius: 3px; 
                            border-top-left-radius: 3px; padding: 5px;">Staged Future Costs</label>
                        $futureVendorItemsT
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div style="margin-top: 20px;"></div>
    <div class="card">
        <div class="card-content">
            <div class="card-body">
                <div class="card-title">
                    <legend>Product Review Dash</legend>
                </div>
                <div class="card-text">
                    <table class="table table-bordered small table-sm table-hover">
                        <thead>
                            <th>Store ID</th>
                            <th>Reviewed This Month</th>
                            <th>Last 60 Days</th>
                            <th>Last 90 days</th>
                            <th>> 90 days</th>
                        </thead>
                        <tbody>$tdReview</tbody>
                    </table>
                    <legend data-toggle="collapse" data-target="#detailedView">
                        <span class="scanicon scanicon-expand"></span>
                        Detailed View</legend>
                    <div id="detailedView" class="collapse">
                        <table class="table table-bordered small table-sm table-hover">
                            <thead>
                                <th>Vendor Name</th>
                                <th>Product Count</th>
                                <th>Reviewed This Month</th>
                                <th>Last 60 Days</th>
                                <th>Last 90 days</th>
                                <th>> 90 days</th>
                            </thead>
                            <tbody>$tdDetailed</tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div style="margin-top: 20px;"></div>
    <div class="card">
        <div class="card-content">
            <div class="card-body">
                <div class="card-title">
                    <legend>Scan Utilities</legend>
                </div>
                <ul>
                    <li>Scan POS for  
                        <a href="#specIframe" onclick="
                            $('#specIframe').css('display', 'block'); 
                            $('#specIframe').attr('src', 'http://key/Scannie/content/Item/SpecialPriceCheck.php');
                            var h = $('#specIframe').outerHeight();
                            h += parseInt(h, 10);
                            $('#specIframe').css('height', h+'px');
                            this.preventDefault;
                        "
                        >Sale Price Discrepancies</a>
                        <div>
                        </div>
                    </li>
                    <li>Do Not Track 
                        <a href="#doNotTrack" onclick="
                            $('#doNotTrack').css('display', 'block'); 
                            //$('#specIframe').attr('src', 'http://key/Scannie/content/Item/SpecialPriceCheck.php');
                            //var h = $('#specIframe').outerHeight();
                            //h += parseInt(h, 10);
                            //$('#specIframe').css('height', h+'px');
                            //this.preventDefault;
                        "
                        >Interface</a>
                    </li>
                </ul>
                <div id="iframeContainer" data-test="test">
                    <iframe src="pleasewait.html" id="specIframe" style="width: 100%; height: auto; padding: 25px; border: 1px solid lightgrey; display:none;">
                    </iframe>
                </div>
                <div id="doNotTrack" style="display:none; margin-top: 25px;">
                    <iframe src="../Admin/DoNotTrack.php" style="width: 100%; height: 800px; border: 1px solid lightgrey;"></iframe>
                </div>
            </div>
        </div>
    </div>
    <div style="margin-top: 20px;"></div>
    <div class="card"
        <div class="card-content">
            <div class="card-body">
                <div class="card-title">
                </div>
            </div>
        </div>
    </div>
    <div style="margin-bottom: 20px;"></div>
</div>
HTML;
    }

    private function getReportHeader($data, $range)
    {
        $pcount = number_format(count($data['data']), 0, '.', ',');
        $count = count($data['data']);
        $alert = "";
        if ($count <= $range[0]) {
            $alert = 'alert-success';
        } elseif ($count <= $range[1]) {
            $alert = 'alert-warning';
        } elseif ($count <= $range[2]) {
            $alert = 'alert-danger';
        }
        
        $ret = "";
        $ret .= "<div class='count $alert'>$pcount</div>";
        $ret .= "<div class='desc'>" . $data['desc'] . "</div>";
        return $ret;
    }
    
    /*
    *   parameters: array data with the following indexes: 'cols', 'data', 'count', 'desc'
    */
    public function getTable($data)
    {
        $tid = substr(md5(microtime()),rand(0,26),5);
        $table = " <button class='btn-collapse' data-target='#table$tid' type='button'>view</button><br/>";
        $table .= "<div id='table$tid'><table class='table table-sm table-bordered tablesorter'><thead>";
        foreach ($data['cols'] as $col) {
            $table .= "<th>$col</th>"; 
        }
        $table .= "</thead><tbody>";
        foreach ($data['data'] as $temp) {
            $table .= "<tr>";
            foreach ($data['cols'] as $col) {
                $table .= "<td>{$temp[$col]}</td>";
            }
            $table .= "</tr>";
        }
        $table .= "</tbody></table></div>";
        
        return $table;
    }

    public function empty_template($dbc)
    {
        $desc = "";
        $a = array();
        $p = $dbc->prepare("");
        $r = $dbc->execute($p, $a);
        $cols = array('');
        $data = array();
        while ($row = $dbc->fetchRow($r)) {
            foreach ($cols as $col) $data[$row['upc']][$col] = $row[$col];
        }
        if ($er = $dbc->error()) echo "<div class='alert alert-danger'>$er</div>";

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);
    }

    private function multiStoreDiscrepCheck($dbc)
    {
        $desc = "Discrepancies with products between stores";
        $fields = array('description','normal_price','cost','tax','foodstamp','wicable','discount','scale',
            'department','brand','local','price_rule_id');
        $data = array();
        $tempData = array();
        foreach ($fields as $field) {
            $tempData = $this->getDiscrepancies($dbc,$field);
            if ($tempData != false) {
                foreach ($tempData as $k => $upc) {
                    $data[] = $upc;
                }
            }
        }

        $data = array_unique($data);
        $ret = $this->getProdInfo($dbc,$data,$fields);

        return array('table'=>$ret, 'data'=>$data);
    }

    private function getProdInfo($dbc,$data)
    {
        $ret = '';
        $FANNIE_SERVE_DIR = $this->config->vars['FANNIE_SERVE_DIR'];
        $fields = array(
            'super_name',
            'description',
            'price',
            'cost',
            'dept',
            'tax',
            'fs',
            'wic',
            'scale',
            'forceQty'
        );
        list($inClause,$args) = $dbc->safeInClause($data);
        $queryH = 'SELECT p.*, m.super_name FROM prodUpdate AS p LEFT JOIN MasterSuperDepts AS m ON p.dept=m.dept_id WHERE storeID = 1 AND upc IN ('.$inClause.')';
        $queryD = 'SELECT * FROM prodUpdate WHERE storeID = 2 AND upc IN ('.$inClause.')';
        $itemH = array();
        $itemD = array();

        //  Get Hillside Prod. Info
        $prepH = $dbc->prepare($queryH);
        $resH = $dbc->execute($prepH,$args);
        if ($dbc->error()) $ret .= '<div class="alert alert-danger">'.$dbc->error().'</div>';
        while ($row = $dbc->fetchRow($resH)) {
            foreach ($fields as $field) {
                $itemH[$row['upc']][$field] = $row[$field];
            }
        }

        //  Get Denfeld Prod. Info
        $prepD = $dbc->prepare($queryD);
        $resD = $dbc->execute($prepD,$args);
        if ($dbc->error()) $ret .= '<div class="alert alert-danger">'.$dbc->error().'</div>';
        while ($row = $dbc->fetchRow($resD)) {
            foreach ($fields as $field) {
                if ($field != 'super_name') {
                    $itemD[$row['upc']][$field] = $row[$field];
                }
            }
        }

        $headers = array('Hill Desc','Den Desc','Hill Cost','Den Cost');
        $ret .= '<table class="table small">';
        $ret .= '<thead><tr><th>upc</th><th>chg</th><th>sup_dept</th>';
        foreach ($fields as $field) {
            if ($field != 'super_name') {
                $ret .= '<th><b>[H]</b>'.$field.'</th><th><b>[D]</b>'.$field.'</th>';
            }
        }

        $ret .= '</tr></thead><tbody>';
        foreach ($itemH as $upc => $row) {
            $ret .= '<tr>';
            $ret .= '<td class="okay">
                <a class="text" href="../../../../'.$FANNIE_SERVE_DIR.'item/ItemEditorPage.php?searchupc='.$upc.'" target="_blank">' . $upc . '</a></td>
                    <td class="okay">
                    <a class="text" href="../Item/TrackItemChange.php?upc=' . $upc . '" target="_blank">
                    dx
                </a></td>';
            $ret .= '<td class="'.$row['super_name'].'">' . $row['super_name'] . '</td>';
            foreach ($fields as $field) {
                if ($field != 'super_name') {
                    $td = '';
                    if ($row[$field] == $itemD[$upc][$field]) {
                        $td = '<td class="okay">';
                    } else {
                        $td = '<td class="bad alert alert-warning">';
                    }
                    $ret .= $td;
                    $ret .= $row[$field] . '</td>';

                    $ret .= $td;
                    $ret .= $itemD[$upc][$field] . '</td>';
                }

            }
            $ret .= '</tr>';
        }
        $ret .= '</tbody></table>';

        return $ret;
    }

    private function getDiscrepancies($dbc, $field)
    {

        $data = array();
        $diffR = $dbc->query("
            SELECT upc, description
            FROM products
            WHERE inUse IN (0,1)
                AND brand NOT IN (
                    'BOLTHOUSE FARMS', 
                    'BEETOLOGY',
                    'COLUMBIA GORGE',
                    'EVOLUTION FRESH',
                    'WILD POPPY',
                    'SUJA',
                    'HONEYDROP',
                    'SO GOOD SO YOU'
                )
            AND upc NOT IN (
                SELECT upc FROM {$this->ALTDB}.doNotTrack 
                WHERE method = 'getDiscrepancies'   
                    AND page = 'Dashboard'
            )
            AND numflag & (1 << 19) = 0
            AND department <> 500
            AND department <> 708
            GROUP BY upc
            HAVING MIN({$field}) <> MAX({$field})
            ORDER BY department
        ");
        $count = $dbc->numRows($diffR);
        $msg = "";
        if ($count > 0 ) {
            while ($row = $dbc->fetchRow($diffR)) {
                $data[] = $row['upc'];
            }
        }

        if ($count > 0) {
            return $data;
        } else {
            return false;
        }
    }

    public function limboPcBatch($dbc)
    {
        $count = 0;
        $desc = 'Forgotten Price-Change Batches';
        $p = $dbc->prepare("SELECT batchID, batchName, batchType, owner
            FROM batches 
            WHERE batchID NOT IN 
                (SELECT bid AS batchID FROM batchReviewLog) 
            AND batchType = 4
            AND batchID > 13768
            AND owner != 'PRODUCE' 
            AND batchID NOT IN (
                SELECT upc FROM woodshed_no_replicate.doNotTrack
                    WHERE method='limboPcBatch' 
            )
            ;");
        $r = $dbc->execute($p);
        $cols = array('batchID', 'batchName', 'owner');
        $data = array();
        while ($row = $dbc->fetchRow($r)) {
            foreach ($cols as $col) $data[$row['batchID']][$col] = $row[$col];
        }
        if ($er = $dbc->error()) echo "<div class='alert alert-danger'>$er</div>";

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);

    }

    static function badDeliDepts($dbc)
    {
        $count = 0;
        $desc = 'Products in unused Deli Departments';
        $p = $dbc->prepare("SELECT 
            upc, brand, description, last_sold, store_id, department
            FROM products where department IN (70, 71)
            ORDER BY upc;");
        $r = $dbc->execute($p);
        $cols = array('upc', 'brand', 'description', 'department');
        $data = array();
        while ($row = $dbc->fetchRow($r)) {
            foreach ($cols as $col) $data[$row['upc']][$col] = $row[$col];
        }
        if ($er = $dbc->error()) echo "<div class='alert alert-danger'>$er</div>";

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);
    }


    static function organicFlags($dbc)
    {
        $count = 0;
        $desc = 'Products Missing Organic Flag';
        $p = $dbc->prepare("SELECT upc, brand, description, numflag FROM products 
            WHERE description LIKE '%,OG%' 
                AND description NOT LIKE '%OG3%'
                AND NOT numflag & (1<<16) <> 0
                ");
        $r = $dbc->execute($p);
        $cols = array('upc', 'brand', 'description');
        $data = array();
        while ($row = $dbc->fetchRow($r)) {
            foreach ($cols as $col) $data[$row['upc']][$col] = $row[$col];
        }

        $p = $dbc->prepare("SELECT p.upc, p.brand, p.description, p.numflag 
            FROM products AS p 
                LEFT JOIN productUser AS u ON p.upc=u.upc
                LEFT JOIN MasterSuperDepts AS m ON p.department=m.dept_ID
            WHERE u.description LIKE '%organic%' 
                AND superID <> 6 
                AND NOT numflag & (1<<16) <> 0;");
        $r = $dbc->execute($p);
        while ($row = $dbc->fetchRow($r)) {
            foreach ($cols as $col) $data[$row['upc']][$col] = $row[$col];
        }

        if ($er = $dbc->error()) echo "<div class='alert alert-danger'>$er</div>";

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);
    }

    static function organicDesc($dbc)
    {
        $count = 0;
        $desc = 'Products Missing Organic In Sign Description';
        $cols = array('upc', 'brand', 'ubrand', 'description', 'udescription', 'numflag');
        $data = array();

        $p = $dbc->prepare("SELECT p.upc, p.brand AS brand, u.brand AS ubrand, 
                p.description AS description, u.description AS udescription, flags AS numflag
            FROM products AS p 
                LEFT JOIN productUser AS u ON p.upc=u.upc
                LEFT JOIN MasterSuperDepts AS m ON p.department=m.dept_ID
                LEFT JOIN prodFlagsListView AS v ON p.upc=v.upc
            WHERE UPPER(p.brand) NOT LIKE '%ORGANIC%' 
                AND UPPER(u.description) NOT LIKE '%ORGANIC%'
                AND superID NOT IN (6,3,1)
                AND p.inUse = 1
                AND numflag & (1<<16) <> 0
                AND UPPER(p.brand) != 'FOUR SIGMATIC'
                ");
        $r = $dbc->execute($p);
        while ($row = $dbc->fetchRow($r)) {
            foreach ($cols as $col) $data[$row['upc']][$col] = $row[$col];
        }

        if ($er = $dbc->error()) echo "<div class='alert alert-danger'>$er</div>";

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);
    }

    public function badPriceCheck($dbc)
    {
        $count = 0;
        $desc = "Products with bad prices";
        $p = $dbc->prepare("
            SELECT upc,
                CONCAT(SUBSTRING(brand, 1, 8), '~') AS brand,
                CONCAT(SUBSTRING(description, 1, 16), '~') AS description,
                cost, normal_price AS price,
                CONCAT(p.department, ': ', d.dept_name, ' - ',  m.super_name) AS department
            FROM products p
                INNER JOIN MasterSuperDepts m ON m.dept_ID=p.department
                INNER JOIN departments d ON d.dept_no=p.department
            WHERE (
            inUse = 1
            AND cost <> 0
            AND m.super_name NOT IN ('PRODUCE')
            AND upc NOT IN (
              SELECT upc FROM {$this->ALTDB}.doNotTrack
              WHERE method = 'badPriceCheck'
                  AND page = 'Dashboard'
              )
            AND p.upc NOT IN ('0000000112212')
            AND p.description NOT LIKE '%OPEN PLU%'
            AND p.upc NOT LIKE '000000008999%'
            ) AND
            (
              normal_price = 0
              OR normal_price > 134.99
              OR normal_price < cost
            )
            GROUP BY p.upc
        ");
        $r = $dbc->execute($p);
        $cols = array('upc', 'brand', 'description', 'cost', 'price', 'department');
        $data = array();
        while ($row = $dbc->fetchRow($r)) {
            foreach ($cols as $col) $data[$row['upc']][$col] = $row[$col];
        }
        if ($er = $dbc->error()) echo "<div class='alert alert-danger'>$er</div>";

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);
    }

    public function getMissingScaleItems($dbc)
    {
        $count = 0;
        $desc = "Scale-items set to scale = 0";
        $p = $dbc->prepare("
            SELECT upc, brand, description, normal_price 
            FROM products 
            WHERE upc < 1000 
                AND upc > 99
                AND scale = 0 
                AND upc NOT IN (
                    SELECT upc FROM {$this->ALTDB}.doNotTrack 
                    WHERE method = 'getMissingScaleItems'   
                        AND page = 'Dashboard'
                )
            GROUP BY upc;");
        $r = $dbc->execute($p);
        $cols = array('upc', 'brand', 'description', 'normal_price');
        $data = array();
        while ($row = $dbc->fetchRow($r)) {
            foreach ($cols as $col) $data[$row['upc']][$col] = $row[$col];
        }
        if ($er = $dbc->error()) echo "<div class='alert alert-danger'>$er</div>";

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);
    }

    public function getProdsMissingLocation($dbc)
    {
        $count = 0;
        $desc = "Products missing physical locations";
        $a = array();
        $p = $dbc->prepare("
            SELECT upc, brand, description, department, store_id
            FROM products AS p
                LEFT JOIN MasterSuperDepts AS m ON p.department=m.dept_ID
            WHERE upc NOT IN (
                SELECT f.upc
                FROM FloorSectionProductMap AS f
                    INNER JOIN products AS p ON p.upc=f.upc
                    INNER JOIN FloorSections AS s ON f.floorSectionID=s.floorSectionID
                        AND s.storeID=p.store_id
            )
                AND inUse = 1
                AND p.department NOT IN (240, 241, 250, 235)
                AND m.superID IN (1,13,9,4,8,17,5,18) 
                AND p.upc != '0000000000105'
                AND p.default_vendor_id <> 200 #klean kanteen
        ");
        $r = $dbc->execute($p, $a);
        $cols = array('upc', 'brand', 'description', 'department', 'store_id');
        $data = array();
        while ($row = $dbc->fetchRow($r)) {
            foreach ($cols as $col) $data[$row['upc']][$col] = $row[$col];
        }
        if ($er = $dbc->error()) echo "<div class='alert alert-danger'>$er</div>";

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);
    }

    public function getAliasMultipleUpcs($dbc)
    {
        $desc = "Products Assigned to Multiple Aliases";
        $prep_data = array();
        $p = $dbc->prepare("
            SELECT v.vendorAliasID, v.upc, v.vendorID, v.sku
            FROM VendorAliases AS v 
        ");
        $r = $dbc->execute($p);
        $cols = array('vendorAliasID', 'upc', 'vendorID', 'sku');
        $dm = array();
        $upcs = array();
        $multiple_upcs = array();
        while ($row = $dbc->fetchRow($r)) {
            $upc = $row['upc'];
            if (in_array($upc, $upcs)) $multiple_upcs[] = $upc;
            $upcs[] = $upc;
            foreach ($cols as $col) $prep_data[$row['vendorAliasID']][$col] = $row[$col];
            $dm[$row['upc']][] = $row['sku'];
        }
        foreach ($prep_data as $sku => $row) {
            $upc = $row['upc'];
            $vendorAliasID = $row['vendorAliasID'];
            if (in_array($upc, $multiple_upcs)) {
                foreach ($cols as $col) $data[$row['vendorAliasID']][$col] = $row[$col];
            }
        }

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);
    }

    public function getOneScaleItems($dbc)
    {
        $count = 0;
        $desc = "scale items set to scale and should not be";
        $p = $dbc->prepare("SELECT upc, brand, description, department, last_sold, weight,
            CASE
                WHEN weight = 0 THEN 'Random'
                WHEN weight = 1 THEN 'Fixed'
                ELSE 'not in scale'
            END AS weightType 
            FROM products AS p 
            LEFT JOIN scaleItems AS s ON p.upc=s.plu
            LEFT JOIN MasterSuperDepts AS m ON m.dept_ID=p.department
            WHERE upc LIKE '002%'
            AND scale = 1
            AND weight = 1
            AND upc NOT IN (
                0020140000000,
                0020130000000,
                0020120000000
            )
            AND m.superID IN (18,1,3,13,9,4,8,17,5)
            ;");
        /*
            I then also need to check for scale items set to 1 that are set to scale = 1 
            also, do not include produce items!!!
        */
        $r = $dbc->execute($p);
        $cols = array('upc', 'brand', 'description', 'department', 'last_sold', 'weight');
        $data = array();
        while ($row = $dbc->fetchrow($r)) {
            foreach ($cols as $col) $data[$row['upc']][$col] = $row[$col];
        }

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);
    }

    public function getLocalDiscrepancies($dbc)
    {
        $count = 0;
        $desc = "Brands with Local setting discrepancies";
        $cols = array('brand');
        $data = array();
        $prep = $dbc->prepare("SELECT brand, local 
            FROM products 
                    LEFT JOIN MasterSuperDepts AS m ON department=m.dept_ID
            WHERE brand IS NOT NULL 
                AND m.superID IN (1, 3, 4, 5, 8, 9, 13, 17, 18)
                AND brand != '' 
                AND default_vendor_id > 0
                AND brand != 'BULK'
                AND department <> 110
            GROUP BY brand, local");
        $res = $dbc->execute($prep);
        $brands = array();
        while ($row = $dbc->fetchRow($res)) {
            $local = (isset($row['local'])) ? $row['local'] : null;
            if ($local >= 0) {
                $brands[$row['brand']][] = $local;
            }
        }
        foreach ($brands as $brand => $local) {
            if (count($local) > 1) {
                $data[$brand]['brand'] = $brand;
            }
        }

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);
    }

    public function getNewSuperValueItems($dbc)
    {
        $count = 0;
        $desc = "NEW UNFI Conventional / Supervalue Items";
        $cols = array('upc', 'brand', 'description');
        $data = array();
        $prep = $dbc->prepare("SELECT brand, description, p.upc
            FROM products AS p
            WHERE p.default_vendor_id = 401
                AND p.upc NOT IN (SELECT upc FROM woodshed_no_replicate.unfiConvRev);");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            foreach ($cols as $col) $data[$row['upc']][$col] = $row[$col];
            $count++;
        }
        if ($count > 0) {
            $data['count'] = $count;
        }

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);
    }

    public function getEdlpMonths($curEdlpSet)
    {
        $dates = array();
        $date1 = new DateTime('first day of this month');
        $dates[1] = $date1->format('Y-m-d');
        $date2 = new DateTime('first day of last month');
        $dates[0] = $date2->format('Y-m-d');
        $date3 = new DateTime('first day of next month');
        $dates[2] = $date3->format('Y-m-d');

        $html = "
                <label for=\"curEdlp\" >Current EDLP Set:</label>
                <div id=\"curEdlpLabel\"></div>
                <form action=\"Dashboard.php\" method=\"post\">
                <div  class=\"form-group\">
                    <select name=\"curEdlp\" id=\"curEdlp\" class=\"form-control\">
        ";
        foreach ($dates as $date) {
            $sel = ($curEdlpSet == $date) ? ' selected ' : '';
            $html .="<option val=\"$date\" $sel>$date</option>";
        }
        $html .= "
                </select>
            </div>
        ";

        return $html;
    }

    public function getDealSetSelector($dealSets)
    {
        $ret = "Coop Deals Sets To Check
            <div class=\"form-group\">";
        foreach ($dealSets as $dealSet) {
            $ret .= "
                <input id=\"$dealSet\" name=\"dealSetA\" type=\"checkbox\" />
                <label for=\"$dealSet\">$dealSet</label>";
        }
        $ret .= "
            </div>";

        return $ret;
    }

    public function checkDeliProductionDepts($dbc)
    {
        $data = array();
        $desc = "Deli: Should be default_vendor_id 70, WFC DELI";
        $cols = array('upc', 'brand', 'description', 'created', 'department', 'default_vendor_id');
        $count = 0;
        $prep = $dbc->prepare("SELECT upc, brand, description, created, default_vendor_id, department FROM products WHERE department IN (63,65,66,78,223,225,226,228,229) AND (default_vendor_id <> 70 || brand != 'WFC DELI') AND description != 'OPEN PLU';");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            foreach ($cols as $col) $data[$row['upc']][$col] = $row[$col];
            $count++;
        }

        if ($count > 0) {
            $data['count'] = $count;
        }

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);
    }

    public function getBadSaleCost($dbc)
    {
        $data = array();
        $desc = "Items with bad vendorItems.saleCost";
        $cols = array('upc', 'brand', 'description', 'vendorID', 'cost', 'saleCost');
        $count = 0;
        $prep = $dbc->prepare("SELECT upc, brand, description, vendorID, cost, saleCost FROM vendorItems WHERE saleCost > cost AND cost > 0;");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            foreach ($cols as $col) $data[$row['upc']][$col] = $row[$col];
            $count++;
        }

        if ($count > 0) {
            $data['count'] = $count;
        }

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);
    }

    public function getWatchList($dbc)
    {
        $data = array();
        $desc = "Items in Watch List";
        $cols = array('upc', 'brand', 'description', 'created', 'department', 'default_vendor_id');
        $count = 0;
        $prep = $dbc->prepare("SELECT a.upc, p.* FROM woodshed_no_replicate.AuditScan a
            INNER JOIN products p ON p.upc=a.upc
            WHERE a.username='csather'
                AND a.savedAs = 'WATCH LIST'");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            foreach ($cols as $col) $data[$row['upc']][$col] = $row[$col];
            $count++;
        }

        if ($count > 0) {
            $data['count'] = $count;
        }

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);
    }

    public function getTooBigUPC($dbc)
    {
        $data = array();
        $desc = "New Items With Too Big UPC";
        $cols = array('upc', 'brand', 'description', 'created');
        $prep = $dbc->prepare("SELECT upc, brand, description, created
            FROM products p
                INNER JOIN MasterSuperDepts m ON m.dept_ID=p.department
            WHERE upc > 999999999999
            AND m.super_name != 'PRODUCE'");
        $res = $dbc->execute($prep);
        $count = 0;
        while ($row = $dbc->fetchRow($res)) {
            foreach ($cols as $col) $data[$row['upc']][$col] = $row[$col];
            $count++;
        }

        if ($count > 0) {
            $data['count'] = $count;
        }

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);
    }

    public function getBadBogoDeals($dbc)
    {
        $count = 0;
        $desc = "Items with bad BOGO deals";
        $cols = array('upc', 'brand', 'description', 'normal_price', 'salePrice');
        $data = array();
        
        $prep = $dbc->prepare("
            SELECT
                p.upc, p.brand, p.description, p.normal_price,
                    l.salePrice
            FROM batchList l
                INNER JOIN batches b ON b.batchID=l.batchID
                INNER JOIN products p ON p.upc=l.upc
            WHERE l.signMultiplier = -3
                AND p.normal_price <> l.salePrice
                AND b.startDate <= NOW()
                AND b.endDate >= NOW()
            GROUP BY p.upc
        ");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
                foreach ($cols as $col) $data[$row['upc']][$col] = $row[$col];
                $count++;
            }

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);
    }

    public function getProdMissingEDLP($dbc)
    {
        $count = 0;
        $desc = "New/Returning Items Missing EDLP Pricing";
        $cols = array('upc', 'brand', 'description', 'cost', 'ecost', 'normal_price', 
            'maxprice', 'EdlpSet', 'type', 'created', 'vendorID');
        $data = array();
        $dateTime = new DateTime('first day of this month');
        $curMonth = (isset($_SESSION['dashEdlpSet'])) ? $_SESSION['dashEdlpSet'] : $dateTime->format('Y-m-d');
        //$curMonth = '2022-10-01';
        $args = array($curMonth);
        $prep = $dbc->prepare("SELECT brand, description, p.upc, p.normal_price, p.cost, 
                e.cost AS ecost, e.maxprice, DATE_FORMAT(date, '%b-%Y') AS EdlpSet, 
                e.type,
                DATE(p.created) AS created,
                p.default_vendor_id AS vendorID
            FROM products AS p
                LEFT JOIN woodshed_no_replicate.EdlpItems AS e ON e.upc=p.upc
                RIGHT JOIN NcgEdlpVendors AS edlp ON p.default_vendor_id=edlp.vendorID 
            WHERE p.normal_price > e.maxprice
                AND date = ?
                AND p.inUse = 1
                AND p.normal_price <> 54.95
                AND p.default_vendor_id <> 401
        ");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            foreach ($cols as $col) $data[$row['upc']][$col] = $row[$col];
            $count++;
        }
        if ($count > 0) {
            $data['count'] = $count;
        }

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);
    }

    public function getProdMissingSale($dbc)
    {
        $count = 0;
        $desc = "Items Missing From Coop Deals";
        $cols = array('upc', 'brand', 'description', 'dept', 'dealSet', 'price',
            'salePrice', 'ABT', 'promoDiscount', 'vendorName', 'created');
        $data = array();

        $dealSets = array();
        $dateTime = new DateTime();
        $curYear = $dateTime->format('Y');

        $dealSetP = $dbc->prepare("select dealSet from CoopDealsItems group by dealSet order by coopDealsItemID DESC limit 2");
        $dealSetR = $dbc->execute($dealSetP);
        while ($dealSetW = $dbc->fetchRow($dealSetR)) {
            $dealSets[] = $dealSetW['dealSet'];
        }
        //var_dump($dealSets);
        
        foreach ($dealSets as $dealSet) {
            $args = array($dealSet);
            $prep = $dbc->prepare("
                SELECT c.*, p.brand, p.description, DATE(p.created) AS created,
                    c.price AS salePrice,
                    p.normal_price AS price,
                    GROUP_CONCAT(DISTINCT abtpr separator ',') AS ABT, 
                    v.vendorName,
                    SUBSTR(m.super_name, 1, 4) AS dept
                FROM CoopDealsItems AS c
                    RIGHT JOIN products AS p ON p.upc=c.upc
                    LEFT JOIN vendors AS v ON v.vendorID=p.default_vendor_id
                    LEFT JOIN MasterSuperDepts AS m ON m.dept_ID=p.department
                WHERE c.dealSet = ? 
                    AND c.upc NOT IN (
                        SELECT l.upc
                        FROM batchList AS l
                        INNER JOIN batches AS b ON l.batchID=b.batchID
                        WHERE b.batchName like  '%$dealSet'
                    )
                    AND c.upc > 9999
                    AND p.normal_price > c.price
                GROUP BY c.upc
                ORDER BY m.super_name
            ");
            $res = $dbc->execute($prep, $args);
            while ($row = $dbc->fetchRow($res)) {
                // temp(1) - don't show previous month cycle once A starts
                if ($row['dealSet'] != 'July2025' && $row['ABT'] != 'A') {
                    foreach ($cols as $col) $data[$row['upc']][$col] = $row[$col];
                    $count++;
                }
            }

        }

        if ($count > 0) {
            $data['count'] = $count;
        }

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);
    }

    public function getZeroVendorItems($dbc)
    {
        $count = 0;
        $desc = "Items In Vendor Items With UPC 000000000000";
        $cols = array('count');
        $data = array();
        $prep = $dbc->prepare("SELECT COUNT(upc) AS count FROM vendorItems WHERE upc = 0");
        //$res = $dbc->execute($prep);
        $val = $dbc->getValue($prep);
        $count = $val;        
        if ($count > 0) {
            $data[0]['count'] = $count;
        }

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);
    }

    public function getZeroScaleItems($dbc)
    {
        $count = 0;
        $desc = "scale items not set to scale and should be";
        $p = $dbc->prepare("SELECT upc, brand, description, department, last_sold, weight,
            CASE
                WHEN weight = 0 THEN 'Random'
                WHEN weight = 1 THEN 'Fixed'
                ELSE 'not in scale'
            END AS weightType 
            FROM products AS p 
            LEFT JOIN scaleItems AS s ON p.upc=s.plu
            LEFT JOIN MasterSuperDepts AS m ON m.dept_ID=p.department
            WHERE upc LIKE '002%'
            AND scale = 0
            AND weight = 0
            AND upc NOT IN (
                0020140000000,
                0020130000000,
                0020120000000,
                0024954010599,
                0021249080499
            )
            AND m.superID IN (18,1,3,13,9,4,8,17,5)
            ;");
        /*
            I then also need to check for scale items set to 1 that are set to scale = 1 
            also, do not include produce items!!!
        */
        $r = $dbc->execute($p);
        $cols = array('upc', 'brand', 'description', 'department', 'last_sold', 'weight');
        $data = array();
        while ($row = $dbc->fetchrow($r)) {
            foreach ($cols as $col) $data[$row['upc']][$col] = $row[$col];
        }

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);
    }

    public function getvendorskudiscrep($dbc)
    {
        $count = 0;
        $desc = "products with multiple skus by vendor";
        $p = $dbc->prepare("select vendorid from vendors
            where vendorid not in (1, 2, 285) ;");
        $r = $dbc->execute($p);
        $vendors = array();
        while ($row = $dbc->fetchrow($r)) {
            $vendors[] = $row['vendorid'];
        }
        $data = array();
        foreach ($vendors as $vid) {
            $a = array($vid, $vid);
            /** query to get vendoritemid of items with 2+ skus and one sku matches the upc. 
            $p = $dbc->prepare("
                select v.sku, v.upc, v.description, v.cost, v.modified, v.vendorid, v.vendoritemid
                from vendoritems as v 
                    left join products as p on p.upc=v.upc
                    inner join (select * from vendoritems where vendorid = ? group by upc having count(upc)>1) dup on v.upc = dup.upc where v.vendorid=?
                and v.upc <> 0 
                and p.upc=v.sku
            ");
            */
            $p = $dbc->prepare("
                select v.sku, v.upc, v.description, v.cost, v.modified, v.vendorid
                from vendoritems as v 
                    inner join products as p on v.upc=p.upc and p.default_vendor_id = v.vendorid
                    left join mastersuperdepts as m on p.department=m.dept_id
                    inner join (select * from vendoritems where vendorid = ? group by upc having count(upc)>1) dup on v.upc = dup.upc where v.vendorid=?
                and v.upc <> 0 
                and m.superid not in (0, 6)
            ");
            $r = $dbc->execute($p,$a);
            $cols = array('upc', 'description', 'modified', 'sku', 'vendorid');
            $dm = array();
            while ($row = $dbc->fetchrow($r)) {
                foreach ($cols as $col) $data[$row['sku']][$col] = $row[$col];
                $dm[$row['upc']][] = $row['sku'];
            }
            /**
                return first of each duplicate sku
            foreach ($dm as $upc => $row) {
                if ($k == 0) {
                    echo "<div>{$row[0]}</div>";
                }
            }
            **/
        }

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);
    }

    public function getMissingSKU($dbc)
    {
        $desc = "Products with recent sales missing SKU";
        // think about excluding vendorIDs since some vendors don't use SKUs
        $p = $dbc->prepare("
            SELECT p.upc, p.brand, p.description, p.department, p.default_vendor_id AS dvid
            FROM products AS p 
                LEFT JOIN vendorItems AS v ON v.vendorID=p.default_vendor_id
                    AND p.upc=v.upc
                LEFT JOIN MasterSuperDepts AS m ON p.department=m.dept_ID
            WHERE (v.sku IS NULL OR v.sku=p.upc)
                AND p.inUse = 1
                AND m.superID IN (1,13,9,4,8,17,5,18) 
                AND p.default_vendor_id NOT IN (0, 1, 2)
                AND p.default_vendor_id > 0 
                AND p.default_vendor_id IS NOT NULL
            GROUP BY p.upc
        ");
        $r = $dbc->execute($p);
        $data = array();
        $cols = array('upc', 'brand', 'description', 'department', 'dvid');
        while ($row = $dbc->fetchRow($r)) {
            foreach ($cols as $col) $data[$row['upc']][$col] = $row[$col];
        }

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);
    }

    public function getVendorList($dbc)
    {
        $count = 0;
        $desc = "Vendors missing from Vendor Review Schedule";
        $p = $dbc->prepare("
            SELECT vendorID, vendorName, 
                count(upc), GROUP_CONCAT(DISTINCT SUBSTRING(m.super_name, 1, 4) ORDER BY m.super_name) AS departments
            FROM products AS p
                LEFT JOIN vendors AS v ON v.vendorID=p.default_vendor_id
                LEFT JOIN MasterSuperDepts AS m ON m.dept_ID=p.department
            WHERE p.inUse = 1
                AND vendorID NOT IN (SELECT vendorID FROM woodshed_no_replicate.FixedVendorReviewSchedule)
                AND vendorID NOT IN (SELECT vid FROM woodshed_no_replicate.top25)
                AND vendorID > 0
                AND vendorID NOT IN (
                    SELECT upc FROM woodshed_no_replicate.doNotTrack WHERE method = 'getVendorList'
                )
                AND m.super_name NOT IN ('PRODUCE', 'BRAND', 'MISC')
                AND p.numflag & (1<<19) = 0
                AND p.department NOT IN  (235,240)
                AND vendorID NOT IN (156)
            GROUP BY v.vendorID
        ");
        $r = $dbc->execute($p);
        $data = array();
        $cols = array('vendorID', 'vendorName');
        while ($row = $dbc->fetchRow($r)) {
            foreach ($cols as $col) $data[$row['vendorID']][$col] = $row[$col];
        }

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);
    }

    public function getBreakdownBadPrice($dbc)
    {
        $desc = "Breakdown Items w/ Bad Prices";
        $cols = array('sku', 'upc', 'brand', 'description', 'vendorID', 'onSale');
        $data = array();
        $count = 0;
        $pre = $dbc->prepare("
            SELECT
            p.upc,

            p.brand,
            CONCAT(SUBSTRING(p.brand, 1, 8), '~') AS brand,
            p.description, 

            REPLACE(REPLACE(REPLACE(p.size, ' CT', ''), ' OZ', ''), ' FZ', '') AS count,

            ROUND(p.normal_price / REPLACE(p.size, ' CT', ''), 2) AS breakDownPrice,
            ROUND(p.normal_price / FLOOR(1/multiplier)) AS breakUpPrice,
            CASE WHEN CEIL(REPLACE(p.size, ' CT', '')) = p.size THEN 'multiple units' ELSE 'single unit' END AS UnitType,

            v.sku, v.isPrimary, v.multiplier,
            p.normal_price,
            ROUND(p.normal_price * v.multiplier , 2) as completePrice,
            vi.units,
            p.auto_par,
            p2.auto_par AS auto_par2,
            p.special_price,
            p2.special_price AS special_price_2,
            p.inUse,
            p2.inUse AS p2inUse,
            v.vendorID
            FROM products p
                LEFT JOIN VendorAliases v ON v.upc=p.upc
                    AND v.vendorID=p.default_vendor_id
                LEFT JOIN vendorItems vi ON vi.upc=p.upc
                LEFT JOIN products p2 ON p2.upc=p.upc AND p2.store_id=2
            WHERE p.store_id=1
                AND p.department != 110
                AND v.sku NOT IN ('01514652')
                AND p.upc NOT IN ('0085177000306','0000000000436','0000000000940')
                AND p.upc != '0000000000751' ## I have no idea why this one started coming up
            GROUP by p.upc
            ORDER BY v.vendorID, p.brand
        ");
        $res = $dbc->execute($pre);
        //$count = $dbc->numRows($res);
        while ($row = $dbc->fetchRow($res)) {
            $par1 = $row['auto_par'];
            $par2 = $row['auto_par2'];
            $inUse = $row['inUse'];
            $inUse2 = $row['p2inUse'];
            /*
                Show only items that have an auto_par > 0 at either store
                    && are in-use at either store
            */
            if (($par1 > 0 || $par2 > 0) && ($inUse == 1 || $inUse2 == 1)) {
                $sku = $row['sku'];
                $upc = $row['upc'];
                $brand = $row['brand'];
                $description = $row['description'];
                $special_price = $row['special_price'];
                $case_size = $row['units'];
                $vendorID = $row['vendorID'];
                if ($special_price == 0) {
                    $special_price = $row['special_price_2'];
                }
                $normal_price = $row['normal_price'];
                $multiplier = $row['multiplier'];
                $skus[$sku][$upc]['normal_price'] = $normal_price;
                $skus[$sku][$upc]['multiplier'] = $multiplier;
                $skus[$sku][$upc]['special_price'] = $special_price;
                $skus[$sku][$upc]['brand'] = $brand;
                $skus[$sku][$upc]['description'] = $description;
                $skus[$sku][$upc]['case_size'] = $case_size;
                $skus[$sku][$upc]['vendorID'] = $vendorID;
            }
        }

        foreach ($skus as $sku => $arr) {
            $a = 0;
            $lastUpc = '';
            foreach ($arr as $upc => $row) {
                /*
                    Set Parent & Child Status 
                */
                if ($row['normal_price'] > $a && $a == 0) {
                    $skus[$sku][$upc]['type'] = 'parent';
                } else if ($row['normal_price'] > $a) {
                    $skus[$sku][$upc]['type'] = 'parent';
                    $skus[$sku][$lastUpc]['type'] = 'child';
                } else if ($row['normal_price'] == $a) {
                    $skus[$sku][$upc]['type'] = 'n/a';
                    $skus[$sku][$lastUpc]['type'] = 'n/a';
                } else {
                    $skus[$sku][$lastUpc]['type'] = 'parent';
                    $skus[$sku][$upc]['type'] = 'child';
                }

                $a = $row['normal_price'];
                $lastUpc = $upc;
            }
        }

        $td = "";
        $alt = 0;
        foreach ($skus as $sku => $arr) {
            $alt = ($alt == 0) ? 1 : 0;
            foreach ($arr as $upc => $row) {
                if (count($arr) > 1 && $row['type'] != 'n/a') {
                    $units = 0;
                    $multiplier = $row['multiplier'];
                    if ($multiplier < 0.99 && $multiplier != 1.0) {
                        $units = round(100 / (100 * $row['multiplier']));
                    } else if ($multiplier != 1.0) {
                        $units = 1;
                    } else {
                        $units = $row['case_size'];
                    }
                    $total = $units * $row['normal_price'];
                    $tdClass = ($alt == 1) ? 'altRow' : '';
                    $tdWarn = '';

                    if ($row['type'] == 'child' && $total < $this->getParentPrice($skus, $sku)) {
                        $tdWarn = 'danger';
                        foreach ($cols as $col) {
                            $data[$sku]['sku'] = $sku;
                            $data[$sku]['upc'] = $upc;
                            $data[$sku]['brand'] = $row['brand'];
                            $data[$sku]['description'] = $row['description'];
                            $data[$sku]['vendorID'] = $row['vendorID'];
                            $data[$sku]['onSale'] = ($row['special_price'] == 0) ? 'no' : 'yes';
                        }
                    }
                }
            }
        }

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);
    }

    public function getBreakdownBadBatchPrice($dbc)
    {
        $desc = "Breakdown Items w/ Bad Staged PC Prices";
        $cols = array('sku', 'upc', 'brand', 'description', 'vendorID', 'batchID', 'relation');
        $data = array();
        $count = 0;
        $items = array();
        $pre = $dbc->prepare("
            SELECT
            p.upc,

            p.brand,
            CONCAT(SUBSTRING(p.brand, 1, 8), '~') AS brand,
            p.description, 

            REPLACE(REPLACE(REPLACE(p.size, ' CT', ''), ' OZ', ''), ' FZ', '') AS count,

            ROUND(p.normal_price / REPLACE(p.size, ' CT', ''), 2) AS breakDownPrice,
            ROUND(p.normal_price / FLOOR(1/multiplier)) AS breakUpPrice,
            CASE WHEN CEIL(REPLACE(p.size, ' CT', '')) = p.size THEN 'multiple units' ELSE 'single unit' END AS UnitType,

            v.sku, v.isPrimary, v.multiplier,
            p.normal_price,
            ROUND(p.normal_price * v.multiplier , 2) as completePrice,
            vi.units,
            p.auto_par,
            p2.auto_par AS auto_par2,
            p.special_price,
            p2.special_price AS special_price_2,
            p.inUse,
            p2.inUse AS p2inUse,
            v.vendorID,
            l.bid AS batchID,
            bl.salePrice
            FROM batchList bl
                LEFT JOIN products p ON p.upc=bl.upc 
                LEFT JOIN VendorAliases v ON v.upc=p.upc
                    AND v.vendorID=p.default_vendor_id
                LEFT JOIN vendorItems vi ON vi.upc=p.upc
                LEFT JOIN products p2 ON p2.upc=p.upc AND p2.store_id=2
                INNER JOIN batchReviewLog AS l ON l.bid=bl.batchID
            WHERE p.store_id=1
                AND p.department > 24
                AND p.department NOT IN (110, 245, 253, 254, 255, 256, 257, 234, 230, 231, 150)
                AND v.sku NOT IN ('01514652')
                AND p.upc NOT IN ('0085177000306')
                AND l.forced = '0000-00-00 00:00:00'
            GROUP by p.upc
            ORDER BY v.vendorID, p.brand
        ");
        $res = $dbc->execute($pre);
        $count = $dbc->numRows($res);
        $skus = array();
        while ($row = $dbc->fetchRow($res)) {
            $sku = $row['sku'];
            $skus[$sku] = array();
        }

        $normInfoP = $dbc->prepare("SELECT p.upc, p.brand, p.description, p.normal_price, a.multiplier, a.vendorID, a.sku, a.isPrimary FROM products AS p LEFT JOIN VendorAliases AS a ON a.upc=p.upc AND a.vendorID=p.default_vendor_id WHERE a.sku=? GROUP BY a.upc");
        $saleInfoP = $dbc->prepare("SELECT p.upc, p.brand, p.description, p.normal_price, a.multiplier, a.vendorID, a.sku, l.salePrice, l.batchID, a.isPrimary  FROM products AS p LEFT JOIN VendorAliases AS a ON a.upc=p.upc AND a.vendorID=p.default_vendor_id LEFT JOIN batchList l ON l.upc=a.upc LEFT JOIN batchReviewLog AS log ON log.bid=l.batchID WHERE a.sku=? AND log.forced = \"0000-00-00 00:00:00\" GROUP BY a.upc");
        foreach ($skus as $sku => $array) {
            $normInfoA = array($sku);
            $normInfoR = $dbc->execute($normInfoP, $normInfoA);
            while ($row = $dbc->fetchRow($normInfoR)) {
                $upc = $row['upc'];
                $brand = $row['brand'];
                $description = $row['description'];
                $normal_price = $row['normal_price'];
                $multiplier = $row['multiplier'];
                $vendorID = $row['vendorID'];
                $isPrimary = $row['isPrimary'];

                $items[$upc]['sku'] = $sku;
                $items[$upc]['upc'] = $upc;
                $items[$upc]['brand'] = $brand;
                $items[$upc]['description'] = $description;
                $items[$upc]['normal_price'] = $normal_price;
                $items[$upc]['multiplier'] = $multiplier;
                $items[$upc]['vendorID'] = $vendorID;
                $items[$upc]['isPrimary'] = $isPrimary;
                $items[$upc]['salePrice'] = 0;
            }
            //echo $dbc->error();
        }
        //var_dump($items);
        foreach ($skus as $sku => $array) {
            $saleInfoA = array($sku);
            $saleInfoR = $dbc->execute($saleInfoP, $saleInfoA);
            while ($row = $dbc->fetchRow($saleInfoR)) {
                $upc = $row['upc'];
                $salePrice = $row['salePrice'];
                $batchID = $row['batchID'];

                $items[$upc]['salePrice'] = $salePrice;
                $items[$upc]['batchID'] = $batchID;
            }
            //echo $dbc->error();
        }
        //var_dump($items);
        
        foreach ($items as $upc => $row) {
            $skus[$row['sku']][] = $upc;
        }
        //var_dump($skus);
        foreach ($skus as $sku => $upcs) {
            if (count($upcs) > 1) {
                //echo "SKU: $sku <br/>";
                $parent = 0;
                $child = 0;
                $multi = 0;
                $parentUpc = 0;
                $childUpc = 0;
                $alert = "";
                foreach ($upcs as $upc) {
                    if ($items[$upc]['isPrimary'] == 1) {
                        $parent = ($items[$upc]['salePrice'] > 0) ? $items[$upc]['salePrice'] : $items[$upc]['normal_price'];
                        $parentUpc = $upc;
                    } else {
                        $child = ($items[$upc]['salePrice'] > 0) ? $items[$upc]['salePrice'] : $items[$upc]['normal_price'];
                        $multi = $items[$upc]['multiplier'];
                        $childUpc = $upc;
                    }
                }
                $alert = "<div>child: $child, multi: $multi, child/multi: ".$child / $multi." < parent: $parent</div>";
                if ( ($child / $multi) < $parent ) {
                    // bad price detected
                    foreach ($cols as $col) {
                        $data[$childUpc][$col] = $items[$childUpc][$col];
                        $data[$parentUpc][$col] = $items[$parentUpc][$col];

                        $data[$childUpc]['relation'] = "(*$multi) = " . ($child / $multi);
                        $data[$parentUpc]['relation'] = $parent;
                    }
                } 
                //echo "child: $childUpc, parent: $parentUpc<br/>";
            }
        }

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);
    }

    public function getOwnerSoOam($dbc)
    {
        $desc = "Owner SOs Missing 10% on Coop Deals";
        $data = array();
        $pre = $dbc->prepare("
            SELECT order_id, c.CardNo, datetime, o.description, o.upc, o.total, p.normal_price,
                ROUND(((l.salePrice - (l.salePrice * 0.10)) * o.quantity) * o.ItemQtty, 2) AS CDPrice,
                l.salePrice ,
                CASE WHEN s.memtype2 IS NOT NULL THEN s.memtype2 ELSE c.Type END AS memberStatus,
                CASE WHEN s.memtype2 IS NOT NULL THEN c.Type ELSE null END AS activeStatus,
            GROUP_CONCAT(distinct b.batchName ORDER BY b.batchName),
                ABS(ABS(o.total) - ABS(ROUND(((l.salePrice - (l.salePrice * 0.10)) * o.quantity) * o.ItemQtty, 2))) AS diff
            FROM is4c_trans.PendingSpecialOrder AS o
                INNER JOIN is4c_op.products p on p.upc=o.upc
                INNER JOIN is4c_op.vendorItems v on v.upc=p.upc and v.vendorID=p.default_vendor_id
                INNER JOIN is4c_op.MasterSuperDepts ma ON ma.dept_ID=p.department

                INNER JOIN is4c_op.batchList AS l ON l.upc=o.upc
                INNER JOIN is4c_op.batches AS b ON b.batchID=l.batchID

                LEFT JOIN is4c_op.custdata AS c ON c.CardNo=o.card_no
                LEFT JOIN is4c_op.meminfo AS mi ON c.CardNo=mi.card_no
                LEFT JOIN is4c_op.suspensions AS s ON c.CardNo=s.cardno
            WHERE l.salePrice <> 0
                AND ABS(ABS(o.total) - ABS(ROUND(((l.salePrice - (l.salePrice * 0.10)) * o.quantity) * o.ItemQtty, 2))) > 0.01
                AND ma.super_name != \"WELLNESS\"
                AND o.deleted = 0
                #AND o.voided = 0
                AND b.startDate <= DATE(NOW()) AND b.endDate >= DATE(NOW())
                AND b.startDate <= datetime AND b.endDate >= datetime
                AND b.batchName LIKE \"%Co-op Deals%\"
                AND b.batchName NOT LIKE \"%TPR%\"
                AND b.batchName NOT LIKE \"%BOGO%\"
                AND CASE WHEN s.memtype2 IS NOT NULL THEN s.memtype2 ELSE c.Type END IS NOT NULL
                AND CASE WHEN s.memtype2 IS NOT NULL THEN s.memtype2 ELSE c.Type END != \"REG\"
            GROUP BY o.upc, total
        ");
        $res = $dbc->execute($pre);
        $count = $dbc->numRows($res);
        $cols = array('order_id', 'CardNo', 'datetime', 'description', 'upc', 'total', 'normal_price', 'special_price');
        while ($row = $dbc->fetchRow($res)) {
            foreach ($cols as $col) {
                $data[$row['upc']][$col] = $row[$col];
            }
        }

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);
    }

    public function getBogoSoBadPrice($dbc)
    {
        $desc = "BOGO SOs with Bad Prices";
        $data = array();
        $pre = $dbc->prepare("
            SELECT order_id, c.CardNo, datetime, o.description, o.upc, o.total, p.normal_price, v.units,
                ROUND(p.normal_price * v.units, 2) AS CasePrice,
                ROUND((p.normal_price * v.units)/2, 2) AS BogoPrice,
                ROUND(((l.salePrice - (l.salePrice * 0.10)) * o.quantity) * o.ItemQtty, 2) AS CDPrice,
                l.salePrice ,
                CASE WHEN s.memtype2 IS NOT NULL THEN s.memtype2 ELSE c.Type END AS memberStatus,
                CASE WHEN s.memtype2 IS NOT NULL THEN c.Type ELSE null END AS activeStatus,
            GROUP_CONCAT(distinct b.batchName ORDER BY b.batchName),
                ABS(ABS(o.total) - ABS(ROUND(((l.salePrice - (l.salePrice * 0.10)) * o.quantity) * o.ItemQtty, 2))) AS diff
            FROM is4c_trans.PendingSpecialOrder AS o
                INNER JOIN is4c_op.products p on p.upc=o.upc
                INNER JOIN is4c_op.vendorItems v on v.upc=p.upc and v.vendorID=p.default_vendor_id
                INNER JOIN is4c_op.MasterSuperDepts ma ON ma.dept_ID=p.department

                INNER JOIN is4c_op.batchList AS l ON l.upc=o.upc
                INNER JOIN is4c_op.batches AS b ON b.batchID=l.batchID

                LEFT JOIN is4c_op.custdata AS c ON c.CardNo=o.card_no
                LEFT JOIN is4c_op.meminfo AS mi ON c.CardNo=mi.card_no
                LEFT JOIN is4c_op.suspensions AS s ON c.CardNo=s.cardno
            WHERE l.salePrice <> 0
                #AND ABS(ABS(o.total) - ABS(ROUND(((l.salePrice - (l.salePrice * 0.10)) * o.quantity) * o.ItemQtty, 2))) > 0.01
                AND ma.super_name != \"WELLNESS\"
                AND o.deleted = 0
                #AND o.voided = 0
                AND b.startDate <= DATE(NOW()) AND b.endDate >= DATE(NOW())
                AND b.startDate <= datetime AND b.endDate >= datetime
                #AND b.batchName LIKE \"%Co-op Deals%\"
                #AND b.batchName NOT LIKE \"%TPR%\"
                AND b.batchName LIKE \"%BOGO%\"
                AND CASE WHEN s.memtype2 IS NOT NULL THEN s.memtype2 ELSE c.Type END IS NOT NULL
                AND CASE WHEN s.memtype2 IS NOT NULL THEN s.memtype2 ELSE c.Type END != \"REG\"
            GROUP BY o.upc, total
        ");
        $res = $dbc->execute($pre);
        $count = $dbc->numRows($res);
        $cols = array('order_id', 'CardNo', 'datetime', 'description', 'upc', 'total', 'BogoPrice', 'normal_price');
        while ($row = $dbc->fetchRow($res)) {
            if ($row['BogoPrice'] != $row['total']) {
                foreach ($cols as $col) {
                    $data[$row['upc']][$col] = $row[$col];
                }
            }
        }

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);
    }

    public function getOffSrps($dbc)
    {
        $desc = "SRPs that do not match normal prices";
        $data = array();
        $pre = $dbc->prepare("SELECT
            v.upc, p.brand, p.description, p.normal_price, v.srp, e.vendorName,
                e.vendorID
            FROM vendorItems v
                INNER JOIN products p ON p.upc=v.upc AND p.default_vendor_id=v.vendorID
                INNER JOIN MasterSuperDepts m ON m.dept_ID=p.department
                INNER JOIN vendors e ON e.vendorID=v.vendorID
            WHERE v.srp <> p.normal_price
                AND m.super_name != \"PRODUCE\"
                AND p.upc NOT IN (\"0000000001010\",\"0000000001011\",\"0000000001012\",\"0000000001009\") ## tmp, state line eggs intentional discrep
                AND p.department NOT IN (226)
                and p.default_vendor_id != 70
            ORDER BY e.vendorName
            ");
        $res = $dbc->execute($pre);
        $count = $dbc->numRows($res);
        $cols = array('upc', 'brand', 'description', 'normal_price', 'srp', 'vendorID', 'vendorName');
        while ($row = $dbc->fetchRow($res)) {
            foreach ($cols as $col) {
                $data[$row['upc']][$col] = $row[$col];
            }
        }

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);
    }

    public function getGenericPRIDItems($dbc)
    {
        $desc = "Products using generic variable pricing rule";
        $data = array();
        $pre = $dbc->prepare("SELECT upc, brand, description, 
            department, default_vendor_id, inUse FROM products WHERE 
            price_rule_id = 1");
        $res = $dbc->execute($pre);
        $count = $dbc->numRows($res);
        $cols = array('upc', 'brand', 'description', 'department',
             'default_vendor_id', 'inUse');
        while ($row = $dbc->fetchRow($res)) {
            foreach ($cols as $col) {
                if ($col == 'inUse' && isset($data[$row['upc']][$col])) {
                    $data[$row['upc']][$col] .= ', '.$row[$col];
                } else {
                    $data[$row['upc']][$col] = $row[$col];
                }
            }
        }
        foreach ($data as $upc => $row) {
            if ($row['inUse'] == '0, 0') $data[$upc]['inUse'] = 'item not in use';
        }

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);
    }

    public function getSmsZeroPriceItems($dbc)
    {
        $desc = "Products With Zero Price in SMS";
        $data = array();
        $count = 0;
        $cols = array('Details');
        $output = array();
        exec("php ../../../git/IS4C/fannie/modules/plugins2.0/SMS/noauto/GetZeroPriceSmsItems.php", $output);
        foreach ($output as $line) {
            if ($line != "Count: 0") {
                $data[]['Details'] = "$line";
            }
        }
        $count = count($a) - 2;
        if ($er = $dbc->error()) echo "<div class='alert alert-danger'>$er</div>";

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);

    }

    public function getSmsRedundantPriceTabItems($dbc)
    {
        $desc = "Products With Redundant PRICE_TAB rows in SMS";
        $data = array();
        $count = 0;
        $cols = array('Details');
        $output = array();
        exec("php ../../../git/IS4C/fannie/modules/plugins2.0/SMS/noauto/GetRedundantPriceTabRows.php", $output);
        foreach ($output as $line) {
            if ($line != "Count: 0") {
                $data[]['Details'] = "$line";
            }
        }
        $count = count($a) - 2;
        if ($er = $dbc->error()) echo "<div class='alert alert-danger'>$er</div>";

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);

    }

    public function getProdMissingCost($dbc)
    {
        $desc = "Products missing cost";
        $data = array();
        $pre = $dbc->prepare("SELECT upc, brand, description, 
            p.department, default_vendor_id, cost, created, DATEDIFF(NOW(), p.last_sold) AS days_since_sold
            FROM products AS p
                LEFT JOIN MasterSuperDepts AS m ON p.department=m.dept_ID
            WHERE m.superID IN (1,13,9,4,8,17,5,18) 
                AND cost = 0 
                AND default_vendor_id > 0
                AND p.inUse = 1
                AND p.department NOT IN (240, 241, 242, 243, 244, 235)
                AND p.upc NOT IN (
                    SELECT upc FROM {$this->ALTDB}.doNotTrack 
                    WHERE method = 'getProdMissingCost'   
                        AND page = 'Dashboard'
                )
            GROUP BY upc
            ORDER BY last_sold DESC;");
        $res = $dbc->execute($pre);
        $count = $dbc->numRows($res);
        $cols = array('upc', 'brand', 'description', 'department',
             'default_vendor_id', 'cost', 'created', 'days_since_sold');
        while ($row = $dbc->fetchRow($res)) {
            foreach ($cols as $col) $data[$row['upc']][$col] = $row[$col];
        }
        if ($er = $dbc->error()) echo "<div class='alert alert-danger'>$er</div>";

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);

    }

    public function getProdMissingVendor($dbc)
    {
        $desc = "Products not assigned a vendor";
        $data = array();
        $pre = $dbc->prepare("SELECT upc, brand, description, 
            p.department, default_vendor_id, cost 
            FROM products AS p
                LEFT JOIN MasterSuperDepts AS m ON p.department=m.dept_ID
            WHERE m.superID IN (1,13,9,4,8,17,5,18) 
                AND default_vendor_id = 0
                AND p.inUse = 1
                AND p.numflag & (1 << 19) = 0
                AND p.department NOT IN (235)
                AND p.description NOT LIKE '%BOGO%'
                AND upc NOT IN (
                    SELECT upc FROM {$this->ALTDB}.doNotTrack 
                    WHERE method = 'getProdMissingVendor'   
                        AND page = 'Dashboard'
                )
                AND p.department NOT IN (240,244)
            GROUP BY upc;");
        //$pre = $dbc->prepare("select * from products limit 1");
        $res = $dbc->execute($pre);
        $count = $dbc->numRows($res);
        $cols = array('upc', 'brand', 'description', 'department',
             'default_vendor_id' );
        while ($row = $dbc->fetchRow($res)) {
            foreach ($cols as $col) $data[$row['upc']][$col] = $row[$col];
        }
        if ($er = $dbc->error()) echo "<div class='alert alert-danger'>$er</div>";

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);

    }

    public function getMissingMovementTags($dbc)
    {
        $desc = "Products missing movement tag rows";
        $data = array();
        $argA = array(1, 1, 1, 1);
        $argB = array(2, 2, 2, 2);
        $pre = $dbc->prepare("
            SELECT upc, brand, description, created, ? AS store_id,
                CONCAT('INSERT INTO MovementTags (upc, storeID, lastPar, modified) VALUES (\"', upc, '\", ',?,', 0.00, \"', NOW(), '\");') 
            FROM products AS p 
                left join MasterSuperDepts AS m ON p.department=m.dept_ID
            WHERE upc not in (select upc from MovementTags where storeID = ?) 
                AND m.superID IN (1, 4, 5, 9, 13, 17) 
                AND upc NOT IN (
                    SELECT upc FROM {$this->ALTDB}.doNotTrack 
                    WHERE method = 'getMissingMovementTags'   
                        AND page = 'Dashboard'
                )
                AND store_id = ? 
            GROUP by p.upc;
        ");
        $res = $dbc->execute($pre, $argA);
        $count = $dbc->numRows($res, $argA);
        //$cols = array('upc', 'brand', 'description', 'created', 'store_id');
        $cols = array('sql');
        $i = 0;
        while ($row = $dbc->fetchRow($res)) {
            foreach ($cols as $col) $data[$i]['sql'] = $row[5];
            $i++;
        }
        if ($er = $dbc->error()) echo "<div class='alert alert-danger'>$er</div>";
        $res = $dbc->execute($pre, $argB);
        $count = $dbc->numRows($res, $argB);
        //$cols = array('upc', 'brand', 'description', 'created', 'store_id');
        $cols = array('sql');
        while ($row = $dbc->fetchRow($res)) {
            foreach ($cols as $col) $data[$i]['sql'] = $row[5];
            $i++;
        }
        if ($er = $dbc->error()) echo "<div class='alert alert-danger'>$er</div>";

        return array('cols'=>$cols, 'data'=>$data, 'count'=>$count, 
            'desc'=>$desc);
    }

    private function getFutureVendorItems()
    {
        $ret = '<table class="table table-bordered table-sm">
            <thead>
                <th>Vendor Name</th><th>VendorID</th><th>Number of Items</th><th>Start Date</th>
            </thead>
            <tbody>';
        $dbc = scanLib::getConObj();
        $prep = $dbc->prepare("SELECT COUNT(upc) AS count, f.vendorID, vendorName, startDate
            FROM FutureVendorItems AS f
                INNER JOIN vendors AS v ON v.vendorID=f.vendorID
            WHERE startDate >= DATE(NOW())
            GROUP BY f.vendorID, f.startDate
            ORDER BY f.startDate, f.vendorID ");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            $ret .= "<tr>";
            $ret .= "<td>{$row['vendorName']}</td><td>{$row['vendorID']}</td><td>{$row['count']}</td><td>{$row['startDate']}</td>";
            $ret .= "</tr>";
        }
        $ret .= '</tbody></table>';

        return $ret;
    }

    private function getParentPrice($skus, $sku) {

        foreach ($skus[$sku] as $upc => $row) {
            if ($row['type'] == 'parent') {
                if ($row['special_price'] == 0) {
                    return $row['normal_price'];
                } else {
                    return $row['special_price'];
                }
            }
        }

        return false;
    }

    private function getVendorAliasPair($upc)
    {
        $dbc = scanLib::getConObj();
        $items = array();
        //echo "<div>$upc</div>";

        $args = array($upc);
        $prep = $dbc->prepare("SELECT sku FROM VendorAliases WHERE upc = ?");
        $res = $dbc->execute($prep, $args);
        while ($dbc->fetchRow($res)) {
            $sku = $row['sku'];
        }
        echo $dbc->error();

        $args = array($sku);
        $prep = $dbc->prepare("SELECT * FROM VendorAliases WHERE sku = ?");
        $res = $dbc->execute($prep, $args);
        while ($dbc->fetchRow($res)) {
            echo $upc = $row['upc'];
            $multiplier = $row['multiplier'];
            $isPrimary = $row['isPrimary'];
            $items[$upc] = array('multiplier' => $multiplier,  'primary' => $isPrimary);
        }
        echo $dbc->error();

        return $items;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
$(document).ready(function(){
    $('.btn-collapse').each(function(){
        $(this).trigger('click');
    });
});
$('.btn-collapse').click(function(){
    var target = $(this).attr('data-target');
    $(target).toggle();
});

$('#curEdlp').change(function(){
    var edlpDate = $(this).find(':selected').val();
    $.ajax({
        type: 'post',
        data: 'edlpDate='+edlpDate,
        url: 'dashboardajax.php',
        success: function(resp) {
            console.log('success, I guess');
            $('#curEdlpLabel').html('<i>-page must be reloaded to apply changes-</i>');
        }
    });
});
JAVASCRIPT;
    }

    public function cssContent()
    {
return <<<HTML
body {
    background-color: #555D65;
}
.disabled {
    pointer-events: none; 
    background-color: lightgrey;
    display: inline-block;
}
.card {
    //box-shadow: 5px 5px 5px #cacaca;
    box-shadow: 5px 5px 5px black;
    margin: 25px;
}
fieldset {
    border: 1px solid lightgrey;
}
.smh4 {
    font-size: 14px;
    padding: 15px;
}
.small {
    //font-size: 12px;
}
.btn-collapse {
    background: rgba(0,0,0,0);
    color: #84B3FF;
    padding: 0px;
    border-width: 1px 1px 1px 1px;
    border-color: lightblue;
    border-style: solid;
    margin-top: -1px;
    cursor: pointer;
}
th {
    cursor: pointer;
}
.btn-collapse:focus {
    outline: none;
}
div.list {
    display: inline-block;
    width: 400px;
}
div.count {
    display: inline-block;
    width: 50px;
    margin-right: 5px;
}
div.desc {
    display: inline-block;
    width: 400px;
}
h4 {
    padding-top: 25px;
    padding-bottom: 25px;
}
HTML;
    }

    public function helpContent()
    {
        return <<<HTML
<label>Scanning Department Dashboard</label>
<ul>
    <li>
        <strong>Products using generic variable pricing rule</strong>
        <p>Generig "Variable" pricing rules have been deprecated at WFC. All products
            should fall into a specific pricing rule category.</p>
    </li>
    <li>
        <strong>Products missing movement tag rows</strong>
        <p>The data returned in this table is in sql format and is ready to be queries directly 
            into the operational database, which is <i>currently handled manually.</i></p>
    </li>
    <li>
        <strong>Vendors missing from Vendor Review Schedule</strong>
        <p>There is a script in /home/csather/ (newScheduleVendor.sh) that will insert a new 
            vendor into the operational database.</p>
    </li>
    <li>
        <strong>Products with multiple SKUs by Vendor</strong>
        <p>Eeach vendor should only have one SKU for each item. When updating vendors, make 
            sure to remove the irrelevant SKUs before running the Vendor Pricing Batch Page, 
            or there can be some discrepancies which may prohibit some items from showing
            up in the list of desired changes.</p>
    </li>
    <li>
        <strong>Products missing physical locations</strong>
        <p>Use the product location editor <i>list of UPCs Update</i> to update physical 
            product locations.</p> 
    </li>
</ul>    
<label>Product Review Dashboard</label>
<ul>
    <li>This data reflects the number of products WFC has sold in the past 30 days and how 
        recently those items have been reviewed. <i>Note</i> that the counts of items 
        reviewed include duplicate upcs.
    </li>
</ul>
    
HTML;
    }

}
WebDispatch::conditionalExec();
