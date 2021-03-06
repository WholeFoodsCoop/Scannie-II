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
    include(__DIR__.'/../../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../../common/sqlconnect/SQLManager.php');
}
class BasicsScan extends PageLayoutA 
{

    public function body_content()
    {
        $username = scanLib::getUser();
        $storeID = scanLib::getStoreID();
        $storename = scanLib::getStoreName($storeID);
        $dbc = scanLib::getConObj($SCANALTDB);

        $ret = "";
        $heading = "";
        
        $userhead =  ($username == false) ? '<div class="alert alert-danger">Please log in to use this page.</div>'
            : "<div class='alert alert-info'>You are logged in as $username</div>";
        $heading .=  '<h4>Coop Basics Review for <strong>'.$storename.'</strong></h4>';
        $heading .=  "Don't forget to upload a <strong><a href='../../../../../git/fannie/reports/Store-Specific/WFC/Basics/BasicsList.php' target='_blank'>Coop Basics Checklist</a> </strong> as .CSV to <strong><a href='../../../../../git/fannie/admin/ExcelUpload.php' target='_blank'>Generic Upload</a></strong>.<br>";

        if (isset($_GET['session'])) {
            $session = substr($_GET['session'],0,-1);
            $ret .=  $session  . "<br>";
        }

        // get a list of BASICS items
        $list = array();
        $products = array();
        $argsA = array($storeID);
        $prepA = $dbc->prepare("
            SELECT g.upc,p.brand,p.description 
            FROM is4c_op.GenericUpload AS g 
                LEFT JOIN is4c_op.products AS p ON g.upc=p.upc 
            WHERE p.store_id = ? 
                AND inUse = 1
        ");
        $resA = $dbc->execute($prepA,$argsA);
        while ($row = $dbc->fetchRow($resA))  {
            $list[] = $row['upc'];
            $products[$row['upc']]['brand'] = $row['brand'];
            $products[$row['upc']]['description'] = $row['description'];
        }
        if ($er = $dbc->error()) $ret .=  "<div class='alert alert-warning>$er</div>";

        if (count($list) < 1) {
            $ret .=  '<div class="alert alert-danger">No scanned items were found.</div>
                <div class="alert alert-danger">Check that barcodes are in the correct queue.</div>
                <div class="alert alert-danger">Check your query.</div>';
        }

        // get list of products scanned
        $scanned = array();
        $argsB = array($username,$storeID);
        $prepB = $dbc->prepare("
            SELECT upc FROM woodshed_no_replicate.AuditScan WHERE username = ? and storeID = ?
        ");
        $resB = $dbc->execute($prepB,$argsB);
        while ($row = $dbc->fetchRow($resB))  {
            $scanned[] = $row['upc'];
        }
        if ($er = $dbc->error()) $ret .=  "<div class='alert alert-warning>$er</div>";

        // get list of sale items
        $saleitems = array();
        $prepC = $dbc->prepare('select bl.upc from is4c_op.batchList as bl left join is4c_op.batches as b on bl.batchID=b.batchID where CURDATE() between b.startDate and b.endDate group by upc;');
        $resC = $dbc->query($prepC);
        while ($row = $dbc->fetchRow($resC))  {
            $saleitems[] = $row['upc'];
        }
        if ($er = $dbc->error()) $ret .=  "<div class='alert alert-warning>$er</div>";

        $missing = array();  //<--sings that are missing, to put up
        $remove = array();   //<--signs to take down
        //  Find tags that are on the floor that should be taken down
        foreach ($scanned AS $key => $upc) {
            if (in_array($upc,$list)) {
                //  do nothing
            } else {
                $remove[] = $upc;
            }
        }
        //  Find tags that are missing/need to be put up on the floor
        foreach ($list AS $key => $upc) {
            if (in_array($upc,$scanned)) {
                //  do nothing - these signs were scanned, they don't need to go up.
            } else {
                $missing[] = $upc;
            }
        }
        foreach ($missing as $key => $upc) {
            if (in_array($upc,$saleitems)) {
                $missing[$key] = NULL;
            } else {
                //  do nothing
            }
        }

        foreach ($missing as $key => $upc) {
            if (is_null($upc)) unset($missing[$key]);
        }

        $tableA .=  '<table class="table table-condensed small"><thead>';
        $tableA .=  '<tr><th colspan="3" style="text-align: center"><strong>Signs missing from sales floor.</strong></th></tr></thead><tbody>';
        $missingCopyPaste = '';
        foreach ($missing as $upc) {
            if (!in_array($upc, $notInUse)) {
                $product = "<tr><td>".$upc."</td><td>".$products[$upc]['brand']."</td><td>"
                    . $products[$upc]['description']."</td></tr>";
                $tableA .= $product;
                $missingCopyPaste .= $upc . "\n";
            }
        }
        $tableA .=  '</tbody></table>';
        $tableA .=  '<textarea class="form-control copy-text" rows="6" cols="15">' . "MISSING\r\n" . $missingCopyPaste . '</textarea><br />';

        $tableB .=  '<table class="table table-condensed small"><thead>';
        $tableB .=  '<tr><th colspan="3">These signs are on the sales floor and should be taken down</th></tr></thead><tbody>';
        $removeCopyPaste = '';
        foreach ($remove as $upc) {
            $product = "<tr><td>".$upc."</td><td>".$products[$upc]['brand']."</td><td>"
                . $products[$upc]['description']."</td></tr>";
            $tableB .=  $product;
            $removeCopyPaste .= $upc . "\n";
        }
        $tableB .=  "</tbody></table>";
        $tableB .=  '<textarea class="form-control" rows="6" cols="15">' . "TAKE DOWN\r\n" . $removeCopyPaste . '</textarea><br />';

        $html = <<<HTML
<div style="margin-top: 15px;"></div>
<div class="row">
    <div class="col-lg-6">
        <div align="center">$userhead</div>
    </div>
    <div class="col-lg-6">
        $heading
    </div>
</div>
<div class="row">
    <div class="col-lg-6">
        $tableA
    </div>
    <div class="col-lg-6">
        $tableB
    </div>
</div>
HTML;

        return $html;
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

}
WebDispatch::conditionalExec();
