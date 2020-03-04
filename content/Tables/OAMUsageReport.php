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
class OAMUsageReport extends PageLayoutA 
{

    protected $title = "";
    protected $description = "[] .";
    protected $ui = true;

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();

        return false;
    }

    public function pageContent()
    {
        $dbc = scanLib::getConObj();
        // get totals
        $prep = $dbc->prepare("
SELECT card_no,
    SUM(CASE WHEN upc='0049999900376' THEN -1*total ELSE 0 END) AS saved,
    SUM(CASE WHEN trans_type in ('I','D') THEN total ELSE 0 END) as purchased,
    SUM(CASE WHEN trans_type in ('I','D') THEN total ELSE 0 END) * 0.15 AS couldHaveSaved
FROM is4c_trans.dlog_90_view
WHERE
    tdate > '2020-02-11 00:00:00'
    AND tdate < '2020-02-16 23:59:59'
    AND card_no NOT IN (11,9)
    AND (department IN (230,231,232,233,70,71,72,73) or upc='0049999900376')
    AND card_no < 99999
GROUP BY card_no
        ");
        $res = $dbc->execute($prep);
        $saved = 0; $purchased = 0; $couldHaveSaved = 0; 
        while ($row = $dbc->fetchRow($res)) {
            $saved += $row['saved'];
            $purchased += $row['purchased'];
            $couldHaveSaved += $row['couldHaveSaved'];
        }
        echo $dbc->error();
        $td = sprintf("<tr><td>$%s</td><td>$%s</td><td>$%s</td>",
            number_format($saved,0,'.',','),
            number_format($purchased,0,'.',','),
            number_format($couldHaveSaved,0,'.',',')
        );

        // num trans coupon was used
        $prep = $dbc->prepare("
            SELECT trans_num
            FROM is4c_trans.dlog_90_view
            WHERE tdate > '2020-02-11 00:00:00'
            AND tdate < '2020-02-16 23:59:59'
            AND card_no NOT IN (11,9)
            AND trans_subtype = 'IC'
            AND upc = '0049999900376'
            GROUP BY tdate, card_no, trans_no;");
        $res = $dbc->execute($prep);
        echo $dbc->error();
        $couponsUsed = $dbc->numRows($res);

        // num eligible transactions 
        $prep = $dbc->prepare("
            SELECT trans_num
            FROM is4c_trans.dlog_90_view 
            WHERE tdate > '2020-02-11 00:00:00'
            AND tdate < '2020-02-16 23:59:59'
            AND card_no NOT IN (11,9)
            AND department IN (230,231,232,233,70,71,72,73 )
            GROUP BY DATE(tdate), card_no, trans_no;");
        $res = $dbc->execute($prep);
        echo $dbc->error();
        $eligibleTrans = $dbc->numRows($res);
        $percentUsed = 100 * ($couponsUsed / $eligibleTrans);
        $td .= sprintf("<td>%s/%s - %0.2f%%</td></tr>", 
            $couponsUsed,
            $eligibleTrans,
            $percentUsed);

        $th = "<th>Saved</th><th>Purchased</th><th>Could Have Saved</th><th>Coupons Used / Elligible Transasctions</th>";

        return <<<HTML
<h5>OAM Usage Report</h5>
<p>This report needs to be manually configured, not all coupons are alike.</p>
<table class="table table-bordered table-sm sm" style="width: 800px;"><thead>$th</thead><tbody>$td</tbody></table>
HTML;
    }


    public function javascriptContent()
    {
        return <<<JAVASCRIPT
$(document).ready(function(){
});
JAVASCRIPT;
    }

    public function cssContent()
    {
return <<<HTML
h5, p {
    padding: 15px;
}
th, td {
    text-align: center;
}
HTML;
    }

    public function helpContent()
    {
        return <<<HTML
<label>Coop Deals File</label>
<ul>
    <li>
        <strong>Help Content</strong>
        <p>Missing from this page.</p>
    </li>
</ul>    
HTML;
    }

}
WebDispatch::conditionalExec();
