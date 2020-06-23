<?php
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../../common/sqlconnect/SQLManager.php');
}
class newMenu extends PageLayoutA
{
    protected $title = "Batch Check Menu";
    protected $description = "[] ";
    protected $ui = true;

    public function preprocess()
    {
        $dbc = scanLib::getConObj(); 
        if (FormLib::get('deleteSession', false)) {
            $this->displayFunction = $this->deleteSessionHandler();
            die();
        }
        if (FormLib::get('delete', false)) {
            $this->displayFunction = $this->deleteView();
        } else {
            $this->displayFunction = $this->view();
        }

        return false;
    }

    private function deleteSessionHandler()
    {
        $dbc = scanLib::getConObj();
        $storeID = scanLib::getStoreID();
        $sessionName = FormLib::get('sessionName');
        $args = array($storeID,$sessionName);
        $argsB = array($sessionName);
        $prep = $dbc->prepare("DELETE FROM woodshed_no_replicate.batchCheckQueues WHERE storeID = ? AND session = ?;");
        $prepB = $dbc->prepare("DELETE FROM woodshed_no_replicate.batchCheckNotes WHERE session = ?;");
        $dbc->execute($prep,$args);
        $dbc->execute($prepB,$argsB);
        
    }

    private function deleteView()
    {
        $dbc = scanLib::getConObj();
        $storeID = scanLib::getStoreID();
        $sessions = ''; 
        $args = array($storeID);
        $prep = $dbc->prepare("SELECT session, storeID FROM woodshed_no_replicate.batchCheckQueues WHERE storeID = ? GROUP BY session;");
        $res = $dbc->execute($prep,$args);
        while ($row = $dbc->fetchRow($res)) {
            $s = $row['session'];
            $id = $row['storeID'];
            $sessions .= "<div class=\"delete\" 
                style=\"border: 1px solid lightgrey; padding: 15px; margin: 15px;\" id='$s' data-storeID='$id'> 
                <span><i>name</i></span> $s 
                <span><i>store-id</i></span> $id
            </div>"; 
                
        }
        return <<<HTML
<div class="row">
    <div class="col-lg-4"></div>
    <div class="col-lg-4">
        $sessions
        <div align="left"><span class="close">close</span></div>
    </div>
    <div class="col-lg-4"></div>
HTML;
    }

    private function view()
    {
        $session = FormLib::get('session');
        $links = array(
            'Batch Check - Scan Items'  => 'SCS.php',
            'Alternate Scanner (Audie)' => '../AuditScanner/ProductScanner.php',
            'Batch Check - View Queues' => 'newpage.php?queue=2&session='.$session,
            'Coop Deals Item Check' => '../../../../../git/IS4C/fannie/item/CoopDealsLookupPage.php',
            'Chat IM' => 'BatchCheckChat.php#page-bottom',
            //'ScannieV2.0 Home' => '../../',
            'Sign Out' => 'newpage.php?login=1',
            'Fannie - Mobile' => '../../../../../mobile/',
            '*Cleanup*<br>Delete Sessions' => 'newMenu.php?delete=1',
            '*Cleanup*<br>Delete Chat' => 'BatchCheckChat.php?delete=1',
            //'testpage' => '../../page.php',
        );
        $linksContent = '<table class="table table-bordered">';
        foreach ($links as $name => $href) {
            $confirmJs = <<<JAVASCRIPT
var c = confirm('Are you sure?');
if (c == true) {
    location.href = '{$href}';
} 
JAVASCRIPT;
            if (strpos($name, 'Cleanup') !== false ) {
                $linksContent .= "<tr align='center'><td><a href='#' onclick=\"var name = '$name'; $confirmJs\">$name</a></td></tr>";
            } else {
                $linksContent .= "<tr align='center'><td><a href='$href'>$name</a></td></tr>";
            }
        }
        $linksContent .= '</table>';
        //echo $_SESSION['sessionName'];
        return <<<HTML
<div id="menu in" style="margin-top: 25px;">
    <div align="center">
        <h1>Batch Check Menu</h1>
        <br/>
        $linksContent
    </div>
</div>
HTML;
    }

    public function javascriptContent()
    {
        return <<<HTML
$('.menuOption').click(function(){
    //var c = confirm('Are you sure?');
    if (c == true) {
        
    }
});
$('.delete').click(function(){
    var id = $(this).attr('id');
    var storeID = $(this).attr('storeID');
    var c = confirm('Delete '+id+' from Batch Check Queues?');
    if (c == true) {
        $.ajax({
            url: 'BatchCheckMenu.php',
            data: 'deleteSession=1&sessionName='+id+'&storeID='+storeID,
            success: function(resp) {
                window.location.reload(); 
            }
        });
    }
});
$('.close').click(function(){
    window.location.href = 'newMenu.php';
});
HTML;
    }

    public function cssContent()
    {

        return <<<HTML

HTML;
    }
}
WebDispatch::conditionalExec();
