<?php
include('../../config.php');
if (!class_exists('PageLayoutA')) {
    include(dirname(__FILE__).'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(dirname(__FILE__).'/../../../common/sqlconnect/SQLManager.php');
}
/**
 *  @class PrintMultipleReceipts - print multiple receipts with 
 *  one click. 
 *  usage - in PIK detail, control click-copy the first 2 columns
 *  (date, full-trans-no). Paste these into the textarea and click
 *  submit and wait for ajax to load the results.
 */
class PrintMultipleReceipts extends PageLayoutA 
{

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();
        $this->__routes[] = 'get<receipts>';
        $this->__routes[] = 'post<text>';

        return parent::preprocess();
    }

    public function postTextHandler()
    {
        $text = FormLib::get('text');
        $json = array();

        $lines = array();
        $chunks = explode("\n", $text);
        foreach ($chunks as $key => $str) {
            $lines[] = $str;
        }
        $array = array();
        foreach ($lines as $k => $line) {
            $parts = explode("\t", $line);
            $date = $parts[0];
            $transNum = $parts[1];
            $d2 = new DateTime($date);
            $d2 = $d2->format('Y-m-d');
            $json[$d2][] = $transNum;
        }

        echo json_encode($json);

        return false; 
    }

    public function pageContent()
    {
        return <<<HTML
<div class="row">
    <div class="col-lg-2"></div>
    <div class="col-lg-8">
        <div class="no-print">
            <form name="receipts">
                <h4>Paste List of Receipts </h4>
                <p>Copy the first two columns of receipts in PIK->Details
                    (in Firefox, hold CTRL + left click and drag mouse over table data).
                    Paste contents into textarea below and submit.</p>
            <div>
                <textarea name="receipts" rows=10 class="form-control" id="receipts">$formd</textarea>
            </div>
                <span class="btn btn-default" type="submit" id="submit">Submit</span>
            </form>
        </div>
        <div id="contents"></div>
    </div>
    <div class="col-lg-2"></div>

</div>
HTML;
    }


    public function javascriptContent()
    {
        return <<<JAVASCRIPT
var transactions = {};
var year = null;
var day = null;
var month = null;
$('#submit').click(function(e){
    e.preventDefault();
    var text = $('#receipts').val();
    $.ajax({
        type: 'post',
        url: 'PrintMultipleReceipts.php',
        data: 'text='+text,
        success: function(response){
            transactions = JSON.parse(response);
            $.each(transactions, function(d, arr) {
                year = d.substr(0,4);
                month = d.substr(5,2);
                day = d.substr(8,2);
                $.each(arr, function(k, transnum) {
                    console.log('receipt='+transnum+'&month='+month+'&day='+day+'&year='+year);
                    $.ajax({
                        type: 'get',
                        url: 'http://key/git/fannie/admin/LookupReceipt/RenderReceiptPage.php',
                        data: 'receipt='+transnum+'&month='+month+'&day='+day+'&year='+year,
                        success: function(response) {
                            $('#contents').append(response);
                        },
                    });  
                });
            });
        }
    });
});
JAVASCRIPT;
    }

    public function cssContent()
    {
        return <<<HTML
@media print {
    .no-print, .no-print *
    {
        display: none !important;
    }
}
HTML;
    }

}
WebDispatch::conditionalExec();
