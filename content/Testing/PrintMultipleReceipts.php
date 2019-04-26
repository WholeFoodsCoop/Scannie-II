<?php
include('../../config.php');
if (!class_exists('PageLayoutA')) {
    include(dirname(__FILE__).'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(dirname(__FILE__).'/../../../common/sqlconnect/SQLManager.php');
}
if (!class_exists('RenderReceiptPage')) {
    include_once('/var/www/html/git/IS4C/fannie/admin/LookupReceipt/RenderReceiptPage.php');
}

/**
 *  @class PrintMultipleReceipts  - print multiple receipts from 
 *  one page. 
 *  usage - in PIK detail, control click-copy the first 2 columns
 *  (date, full-trans-no). Paste these into the textarea and click
 *  submit. Sometimes this page doesn't work, I don't know why.
 */
class PrintMultipleReceipts extends PageLayoutA 
{

    public function body_content()
    {
        $ret = '';

        $receipts = FormLib::get('receipts');
        $formd = $receipts;
        $receipts = explode("\n", $receipts);
        $i = 0;

        foreach ($receipts as $receipt) {
            $id = "id$i";
            $receipt = preg_split('/\s+/', $receipt);
            $trans = $receipt[1];
            $date = $receipt[0];
            $transaction = $trans;
            $trans = preg_split('#-#', $trans);
            $empNo = $trans[0];
            $regNo = $trans[1];
            $transNo = $trans[2];
            $date = preg_split('#/#', $date);
            $m = $date[0];
            $d = $date[1];
            $y = $date[2];
            $ret .= "<iframe id=\"$id\" src=\"http://key/git/fannie/admin/LookupReceipt/RenderReceiptPage.php?receipt=$transaction&month=$m&day=$d&year=$y\" ></iframe>";
            $ret .= "<div class=\"divider\"></div>";
            $i++;
        }

        return <<<HTML
<div>
    <form name="receipts">
        <label>Paste List of Receipts Here</label>
        <div><textarea name="receipts" rows=10 class="form-control">
----leavethislineblank----
$formd 
        </textarea></div>
        <button class="btn btn-default" type="submit">Submit</button>
    </form>
    <div id="contents"></div>
    $ret
</div>
HTML;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
var contents = "";
$('iframe').each(function(){
    var curcontents = $(this).contents().find("html").html();
    contents = contents + curcontents;
    alert(contents);
});
$(document).ready(function(){
    //alert(contents);
    $('#contents').append(contents);
});
JAVASCRIPT;
    }

    public function cssContent()
    {
        return <<<HTML
iframe {
    border: none;
    width: 100%;
    height: 100%;
    display: block;
    overflow: visible;  
    display: none;
}
.divider {
    height: 15px;
    background: grey;
}
@media print {
    iframe {
        width: 100%;
        height: 100%;
        display: block;
        width: auto;
        height: auto;
        overflow: visible;  
    }
}
HTML;
    }

}
WebDispatch::conditionalExec();
