<?php
include(__DIR__.'/../../config.php');
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
/*
**  @class ItemsNotFound 
*   Facilitates getting data for
*   new POS "Badscans"
*   
*/
class ItemsNotFound extends PageLayoutA
{

    protected $must_authenticate = false;
    protected $ui = false;

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();
        $this->__routes[] = 'post<string>';

        return parent::preprocess();
    }

    public function postStringHandler()
    {
        $storeID = FormLib::get('storeID');
        $string = FormLib::get('string');
        $string = str_replace("Customer Service ", "CSC", $string);
        $dbc = scanLib::getConObj();
        $arr = array();
        $lines = array();

        $prep = $dbc->prepare("INSERT INTO woodshed_no_replicate.NewItemsNotFound (upc, user, datetime, userID, trans_no, storeID) VALUES (?, ?, ?, ?, ?, ?);");

        $prepB = $dbc->prepare("SELECT
            register_no, trans_no
            FROM is4c_trans.dlog_90_view
            WHERE emp_no = ? 
              AND tdate > ?
            LIMIT 1;");
        
        $arr = explode("\n", $string);
        foreach ($arr as $line) {
            $lines[] = explode(" ", $line);
        }

        foreach ($lines as $n => $line) {
            $date = $lines[$n][0] . ' ' . $lines[$n][1];
            $dt = new DateTime($date);
            //$lines[$n][5] = $dt->format('Y-m-d h:i:s');

            $fullDate = $dt->format('Y-m-d h:i:s');
            $upc = $lines[$n][4];
            $userName = $lines[$n][3];
            $userID = $lines[$n][2];

            // find transaction
            $argsB = array($userID, $fullDate);
            $resB = $dbc->execute($prepB, $argsB);
            $row = $dbc->fetch_row($resB);

            $reg_no = $row['register_no'];
            $trans_no = $row['trans_no'];
            $tx = $userID . '-' . $reg_no . '-' . $trans_no;

            // insert data into table
            $args = array($upc, $userName, $fullDate, $userID, $tx, $storeID);
            $res = $dbc->execute($prep, $args);

        }

        return false;
    }

    public function pageContent()
    {
        $storeID = FormLib::get('storeID', false);
        $sopt = array(''=>'Select A Store',
            '001'=>'Hillside',
            '002'=>'Denfeld');
        $storeOpts = '';
        foreach ($sopt as $store => $name) {
            $sel = ($store == $storeID) ? ' selected ' : '';
            $storeOpts .= "<option value=\"$store\" $sel>$name</option>";
        }

        return <<<HTML
<div class="row" style="margin-top: 25px">
    <div class="col-lg-4"></div>
    <div class="col-lg-4">
        <div id="actionText" style="position: absolute; top: 5px; left: 26px; background-color: orange; z-index: -1;
            border-radius: 5px; padding: 15px;">
            <div class="form-group">
                &nbsp;&nbsp;Uploading ...&nbsp;&nbsp;
            </div>
        </div> 
        <div id="myformcontainer">
            <form action="ItemsNotFound.php" method="post">
            <div class="form-group">
                <textarea class="form-control" id="mystring" rows=10></textarea>
            </div>
            <div class="form-group">
                <select class="form-control" id="storeID" name="storeID" placeholder="Store ID" required/>
                    $storeOpts
                </select>
            </div>
            <div class="form-group">
                <a href="#" class="btn btn-default" id="submit">Submit</a>
            </div>
            </form>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="form-group" style="padding-top:75px;">
            <a href="ItemsNotFound.php" class="btn btn-default">Reload Page</a>
        </div>
    </div>
</div>
HTML;
    }

    public function cssContent()
    {
        return <<<HTML
HTML;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
var text = '';
var storeID = 0;
$('#submit').click(function(){
    text = $('#mystring').val();
    storeID = $('#storeID').find(':selected').val();
    $('#myformcontainer').css('display', 'none');
    $.ajax({
        type: 'post',
        data: 'string='+text+'&storeID='+storeID,
        url: 'ItemsNotFound.php',
        success: function(response)
        {
            console.log('Success');
        },
        complete: function()
        {
            $('#actionText').css('background-color', 'lightgreen')
                .text('Upload Complete')
                .append('');

        }
    });
});
JAVASCRIPT;
    }

}
WebDispatch::conditionalExec();
