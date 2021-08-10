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
class ProductionItemMovement extends PageLayoutA 
{

    protected $title = "Production Movement";
    protected $description = "[Track Change] Track all changes made to an item in POS/OFFICE.";
    public $ui = TRUE;

    public function body_content()
    {
        $ret = '';
        $dbc = scanLib::getConObj();
        $upc = FormLib::get('upc', 0);
        $upc = scanLib::upcParse($upc);
        $stores = array(1 => 'Hillside', 2 => 'Denfeld');

        $td = '';
        $description = '';
        $price = '';
        $prep = $dbc->prepare("select p.description, tdate, DATE(tdate) AS date, d.upc, d.description, SUM(ROUND(total/p.normal_price,2)) AS dailyTotal, normal_price from is4c_trans.dlog_15 AS d INNER JOIN is4c_op.products AS p on d.upc=p.upc and d.store_id=p.store_id where p.upc = ? AND card_no <> 5700 AND d.store_id = 2 GROUP BY DATE(tdate) ORDER BY tdate DESC;");
        $res = $dbc->execute($prep, array($upc));
        while ($row = $dbc->fetchRow($res)) {
            $dailyTotal = $row['dailyTotal'];
            $date = $row['date'];
            $description = $row['description'];
            $price = $row['normal_price'];
            $td .= "<tr><td>$date</td><td>$dailyTotal</td></tr>";
        }

        $td2 = "";
        $prep = $dbc->prepare("select trans_num, p.description, tdate, DATE(tdate) AS date, d.upc, d.description, ROUND(total/p.normal_price,2) total from is4c_trans.dlog_15 AS d INNER JOIN is4c_op.products AS p on d.upc=p.upc and d.store_id=p.store_id where p.upc = ? AND card_no <> 5700 AND d.store_id = 2 ORDER BY tdate DESC;");
        $res = $dbc->execute($prep, array($upc));
        while ($row = $dbc->fetchRow($res)) {
            $total = $row['total'];
            $date = $row['date'];
            $transNum = $row['trans_num'];
            $td2 .= "<tr><td>$date</td><td>$transNum</td><td>$total</td></tr>";
        }

        $lastChange = "";
        $prep = $dbc->prepare("SELECT price, DATE(modified) AS modified FROM prodUpdate WHERE upc = ? AND price <> ? ORDER BY modified DESC LIMIT 1");
        $res = $dbc->execute($prep, array($upc, $price));
        while ($row = $dbc->fetchRow($res)) {
            $lastPrice = $row['price'];
            $modified = $row['modified'];
            $lastChange = "<div>Price changed from $$lastPrice to $$price on $modified, <i>movement before</i> $modified <i>is inaccurate.</i></div>";
        }
            
        
        return <<<HTML
<div class="row" style="padding: 15px">
    <div class="col-lg-4">
        <h5>Daily Scale Label Product Movement</h5>
        <form method="get" action="ProductionItemMovement.php">
            <input type="text" name="upc" value="$upc" placeholder="upc" />
        </form>
        <div>$description</div> 
        <p>This table shows <strong>daily movement</strong> <i>excluding</i> NABs</p>
        <table class="table table-bordered table-sm small"><thead></thead><tbody>$td</tbody></table>
    </div>
    <div class="col-lg-4">
        This table shows <strong>all</strong> transactions <i>excluding</i> NABs
        <table class="table table-bordered table-sm small"><thead></thead><tbody>$td2</tbody></table>
    </div>
    <div class="col-lg-4">$lastChange</div>
</div>
HTML;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
function stripeByColumn()
{
    var prev_dept = '';
    var color = 'white';
    $('tr').each(function(){
        var dept = $(this).find('td:first-child').text();
        if (dept != prev_dept) {
            if (color == 'white') {
                color = '#faf9e3';
            } else {
                color = 'white';
            }
        }
        $(this).css('background', color);
        prev_dept = dept;
    });

}
stripeByColumn();
JAVASCRIPT;

    }

    public function cssContent()
    {
        return false;
    }

}
WebDispatch::conditionalExec();
