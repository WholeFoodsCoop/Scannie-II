<?php
/*******************************************************************************

    Copyright 2021 Whole Foods Community Co-op.

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
class TrackItemChange extends PageLayoutA 
{

    protected $title = "Track Change";
    protected $description = "[Track Change] Track all changes made to an item in POS/OFFICE.";
    protected $connect = true;
    public $ui = TRUE;

    public function body_content()
    {
        $ret = '';
        $dbc = $this->connect;
        $upc = FormLib::get('upc');
        $upc = scanLib::upcParse($upc);
        $stores = array(1 => 'Hillside', 2 => 'Denfeld');

        $prodInfoP = $dbc->prepare("SELECT upc, brand, description, created, DATE(last_sold) AS last_sold, store_id FROM products 
            WHERE upc = ?");
        $prodInfoR = $dbc->execute($prodInfoP, array($upc));
        $lastSold = '';
        while ($row = $dbc->fetchRow($prodInfoR)) {
            $brand = $row['brand'];
            $description = $row['description'];
            $created = $row['created'];
            $lastSold .= "[{$stores[$row['store_id']]}] " . $row['last_sold'] . " ";
        }
        
        $vendP = $dbc->prepare("SELECT e.vendorName, e.vendorID, v.sku, v.brand, v.description, v.size, v.units, 
            v.cost, v.modified FROM vendorItems AS v 
            LEFT JOIN vendors AS e ON v.vendorID=e.vendorID WHERE upc = ? GROUP BY v.vendorID");
        $vendR = $dbc->execute($vendP, array($upc));
        $tdV = "";
        $thV = "<tr><th>Vendor</th><th>Vendor ID</th><th>SKU</th><th>Brand</th><th>Description</th><th>Size</th><th>Units</th><th>Cost</th><th>Modified</th></tr>";
        while ($row = $dbc->fetchRow($vendR)) {
            $tdV .= "<tr>";
            foreach ($row as $col => $v) {
                if (!is_numeric($col)) 
                    $tdV .= "<td>$v</td>";
            }
            $tdV .= "</tr>";
        }

        $purchP = $dbc->prepare("SELECT orderID, internalUPC AS upc, sku, description, unitCost AS cost, caseSize, DATE(receivedDate) AS received, isSpecialOrder AS SO FROM PurchaseOrderItems WHERE internalUPC = ? ORDER BY receivedDate DESC LIMIT 3;");
        $purchR = $dbc->execute($purchP, array($upc));
        $cols = array();
        $tdP = "";
        while ($row = $dbc->fetchRow($purchR)) {
            $tdP .= "<tr>";
            foreach ($row as $col => $v) {
                if (!is_numeric($col)) {
                    $tdP .= "<td>$v</td>";
                    if (!in_array($col, $cols))
                        $cols[] = $col;
                }
            }
            $tdP .= "</tr>";
        }
        $thP = "<tr>";
        foreach ($cols as $col) {
            $thP .= "<th>$col</th>";
        }
        $thP .= "</tr>";

        $data = array();
        $td = ""; $i = 0;
        $skips = array('updateType', 'storeID', 'modified' );
        $prep = $dbc->prepare("SELECT updateType, storeID, description, price, salePrice, cost, dept, tax, fs, wic, scale, likeCode, 
            modified, name AS user, forceQty, noDisc, inuse 
            FROM prodUpdate AS p
                LEFT JOIN Users AS u ON p.user=u.uid
            WHERE upc = ? AND storeID = ? 
            ORDER BY modified DESC, storeID");
        foreach (array(1,2) as $storeID) {
            $res = $dbc->execute($prep, array($upc, $storeID));
            while ($row = $dbc->fetchRow($res)) {
                foreach ($row as $col => $v) {
                    if (!is_numeric($col)) 
                        $data[$i][$col] = $v;
                }
                $i++;
            }
        }

        function sortByDate($a, $b)
        {
            $t1 = strtotime($a['modified']);
            $t2 = strtotime($b['modified']);

            return $t2 - $t1;
        }
        usort($data, 'sortByDate');

        $test = array();

        foreach ($data as $k => $row) {
            if ($k === 0) {
                $td .= "<tr>";
                foreach ($row as $col => $v) {
                    if ($col == 'modified') {
                        $date = substr($v, 0, 10);
                        $time = substr($v, 10);
                        $v = "<span class=\"date\">$date</span><span style=\"color: grey;\">$time</span>";
                    }
                    if ($col != 'storeID') {
                        $td .= "<td class=\"$col\">$v</td>";
                    }
                }
                $td .= "</tr>";
            }
            $show = 0;
            //$show = 1;
            if ($k !== 0) {
                $add = '';
                $teststr = '';
                foreach ($row as $col => $v) {
                    $test[$k][$col] = 0;
                    if ($v != $data[$k-1][$col] && !in_array($col, $skips)) {
                        $show = 1;
                        $test[$k][$col] = 1;
                    }
                }
            }
            if ($show === 1) {
                $td .= "<tr>";
                foreach ($row as $col => $v) {
                    $bold = ($test[$k][$col] == 1) ? 'color: black; text-shadow: 0.5px 0.5px lightgrey; background: #DEDCDC;' : '';
                    if ($col == 'modified') {
                        $date = substr($v, 0, 10);
                        $time = substr($v, 10);
                        $v = "<span class=\"date\">$date</span><span style=\"color: grey;\">$time</span>";
                    }
                    if ($col != 'storeID') {
                        $td .= "<td style=\"$bold\" class=\"$col\">$v</td>";
                    }
                }
                $td .= "</tr>";
            }
            $show = 0;
        }
        $columns = array('updateType', 'description', 'price', 'salePrice', 'cost', 'dept', 'tax', 'fs', 'wic', 'scale', 'likeCode', 'date', 'user', 'forceQty', 'noDisc', 'inuse');
        $th = '';
        foreach ($columns as $col) {
            $th .= "<th>$col</th>";
        }
        $this->addScript('http://key/git/fannie/src/javascript/jquery.js');
        $this->addScript('http://key/git/fannie/src/javascript/Chart.min.js');

        return <<<HTML


<div class="container-fluid" style="padding-top: 15px;">
    <h5>Track Product Changes</h5>
    <div class="row">
        <div class="col-lg-3">
            <form class="form-inline">
                    <span class="input-group-text" id="basic-addon1">UPC</span> &nbsp;
                    <input type="text" value="$upc" name="upc" id="upc" class="form-control form-control-sm" pattern="\d*" autofocus/>
                &nbsp;
                    <button type="submit" class="btn btn-default btn-sm">Submit</button>
            </form>
            <div><strong>Brand</strong>: $brand </div>
            <div><strong>Description</strong>: $description </div>
            <div><strong>Created</strong>: $created</div>
            <div><strong>Last Sold</strong>: $lastSold</div>
        </div>
        <div class="col-lg-4">
        </div>
        <div class="col-lg-4">
            <div id="myChartContainer">
                <canvas id="myChart"></canvas>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-4"></div>
        <div class="col-lg-4">
        </div>
        <div class="col-lg-4"></div>
    </div>

    <label style="background: #d8ffd4;" class="form-control">Vendor Items</label>
    <div class="table-responsive">
    <table class="table table-bordered table-condensed table-sm small"><thead style="background: #d8ffd4;">$thV</thead><tbody>$tdV</tbody></table>
    </div>

    <label style="background: #D4FFFC;" class="form-control">Recent Purchases</label>
    <div class="table-responsive">
    <table class="table table-bordered table-condensed table-sm small"><thead style="background: #d4fffc;">$thP</thead><tbody>$tdP</tbody></table>
    </div>

    <label style="background: #D4E9FF;" class="form-control">Product Changes</label>
    <div class="table-responsive">
    <table id="table-changes" class="table table-bordered table-condensed table-sm table-striped small"><thead style="background: #d4e9ff;">$th</thead><tbody>$td</tbody></table>
    </div>
</div>
HTML;

    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
$('#upc').focusin(function(){
    $(this).select();
});
$('#upc').focusout(function(){
    let text = $(this).val();
    text = text.replace(/\D/g, '');
    $(this).val(text);
});
$('#table-changes tr').each(function(){
    let storeID = $(this).find('td:eq(1)').text();
    if (storeID == 2)
        $(this).css('background-color', '#fff1c2');
});

var tmpcost = '';
var tmp = null;
tmpcost = $('#table-changes').closest('table').find(' tbody tr:first').find('td.cost').text();
var costs = [];
var dates = []; 
var prices = [];
var chartCosts = [];
var chartPrices = [];
var chartLabels = [];
$('#table-changes tr').each(function(){
    let cost = $(this).find('td.cost').text();
    let price = $(this).find('td.price').text();
    let date = $(this).find('span.date').text();
    if (cost == '') {
        // do nothing
    } else {
        // console.log(cost+', '+date);
        costs.push(cost);
        dates.push(date);
        prices.push(price);
    }
});
costs.reverse();
dates.reverse();
prices.reverse();
$.each(costs, function(k,v){
    date = dates[k];
    price = prices[k];
    if (tmp != v && v != 0) {
        console.log(v + ', ' + date);
        chartCosts.push(v);
        chartPrices.push(price);
        chartLabels.push(date);
    }
    tmp = v;
});

ctx = document.getElementById('myChart');
var setChart = function()
{
    var data = [];
    var datasets = {};
    var labels = dates;

    ctx = document.getElementById('myChart');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [
                {
                    label: 'cost',
                    data: chartCosts,
                    borderWidth: 1,
                    borderColor: '#36A2EB',
                },
                {
                    label: 'price',
                    data: chartPrices,
                    borderWidth: 1,
                    borderColor: 'darkgreen',
                }
            ]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}
setChart();
JAVASCRIPT;
    }

    public function cssContent()
    {
        return <<<HTML
.table-striped > tbody > tr:nth-child(2n+1) > td, .table-striped > tbody > tr:nth-child(2n+1) > th {
   background-color: #FAFCFF;
}
HTML;
    }

}
WebDispatch::conditionalExec();
