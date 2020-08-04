<?php
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
class ScannerSettings extends PageLayoutA
{
    protected $title = "Scanner Settings";
    protected $description = "[Scanner Settings] Control Settings.";
    protected $ui = true;

    public function preprocess()
    {
        $this->displayFunction = $this->postView();
        $this->__routes[] = 'post<save>';

        return parent::preprocess();
    }

    public function postSaveHandler()
    {
        $dbc = scanLib::getConObj('SCANALTDB');
        $column = FormLib::get('column');

        $checked = FormLib::get('checked');
        $checked = ($checked == 'true') ? 1 : 0;
        $a = array($checked, session_id());
        $p = $dbc->prepare("UPDATE ScannieConfig SET $column = ? WHERE session_id = ?;");
        $dbc->execute($p, $a);
        if ($er =  $dbc->error()) echo "<div class=\"alert alert-danger\">$er</div>";
        echo "HI";

        return false;
    }

    public function postView()
    {
        $dbc = scanLib::getConObj('SCANALTDB');
        $SESSION_ID = session_id();
        $a = array($SESSION_ID);
        $p = $dbc->prepare("SELECT * FROM ScannieConfig WHERE session_id = ?;");
        $r = $dbc->execute($p, $a);
        while ($row = $dbc->fetchRow($r)) {
            $beepChecked = ($row['scanBeep']) ? 'checked' : '';
            $checkPar = ($row['auditPar']) ? 'checked' : '';
            $checkCost = ($row['auditCost']) ? 'checked' : '';
            $checkSrp = ($row['auditSrp']) ? 'checked' : '';
            $checkProdInfo = ($row['auditProdInfo']) ? 'checked' : '';
            $checkVendorInfo = ($row['auditVendorInfo']) ? 'checked' : '';
            $checkLocations = ($row['auditLocations']) ? 'checked' : '';
            $checkSize = ($row['auditSize']) ? 'checked' : '';
            $checkSignInfo = ($row['auditSignInfo']) ? 'checked' : '';
            $checkSaleInfo = ($row['auditSaleInfo']) ? 'checked' : '';
            $checkSaleInfo = ($row['auditSaleInfo']) ? 'checked' : '';
            $isSocketDevice = ($row['socketDevice']) ? 'checked' : '';
        };

        $scanner_settings = array();
        return <<<HTML
<div class="container-fluid" style="margin-top: 25px;">
    <label>Go To:</label>
    <ul>
        <li><div class="form-group"><a href="AuditScanner/QuickScanner.php" class="btn btn-default">Quick Scanner</a></div></li>
        <li><div class="form-group"><a href="AuditScanner/ProductScanner.php" class="btn btn-default">Audit Scanner</a></div></li>
        <li><div class="form-group"><a href="BatchCheck/SCS.php" class="btn btn-default">Batch Check</a></div></li>
    </ul>
    <form method="post">
    <label>Add Linea Sound on Scan:</label>
        <ul>
            <li>
                <label>Beep After Scan: </label>
                <input type="checkbox" name="scanBeep" value=1 id="toggleBeep" $beepChecked />
            </li>
        </ul>
        <input type="hidden" name="sessionID" id="sessionID" value="$a" />
    </form>
    <form method="post">
        <label>Audit Scanner <a href="" data-target="#scanner-settings" data-toggle="collapse">Settings</a></label>
        <ul class="" id="scanner-settings">
            <li>
                <label>Par</label>
                <input type="checkbox" name="auditPar" value=1 id="togglePar" $checkPar/>
            </li>
            <li>
                <label>Cost/Price/Margin</label>
                <input type="checkbox" name="auditCost" value=1 id="toggleCost" $checkCost/>
            </li>
            <li>
                <label>SRP/Margin</label>
                <input type="checkbox" name="auditSrp" value=1 id="toggleSrp" $checkSrp/>
            </li>
            <li>
                <label>Desc, Brand, Dept</label>
                <input type="checkbox" name="auditProdInfo" value=1 id="toggleProdInfo" $checkProdInfo/>
            </li>
            <li>
                <label>Vendor Info</label>
                <input type="checkbox" name="auditVendorInfo" value=1 id="toggleVendorInfo" $checkVendorInfo/>
            </li>
            <li>
                <label>Prod Location</label>
                <input type="checkbox" name="auditLocations" value=1 id="toggleLocations" $checkLocations/>
            </li>
            <li>
                <label>Size</label>
                <input type="checkbox" name="auditSize" value=1 id="toggleSize" $checkSize/>
            </li>
            <li>
                <label>Sign Info</label>
                <input type="checkbox" name="auditSignInfo" value=1 id="toggleSignInfo" $checkSignInfo/>
            </li>
            <li>
                <label>Sale Info</label>
                <input type="checkbox" name="auditSaleInfo" value=1 id="toggleSaleInfo" $checkSaleInfo/>
            </li>
            <li>
                <label>Socket Device</label>
                <input type="checkbox" name="socketDevice" value=1 id="toggleSocketDevice" $isSocketDevice/>
            </li>
        </ul>
        <input type="hidden" name="sessionID" id="sessionID" value="$a" />
    </form>
</div>
HTML;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
$('input[type="checkbox"]').on('change', function(){
    var checked = $(this).prop('checked');
    var sessionID = $('#sessionID');
    var column = $(this).attr('name');
    $.ajax({
        url: 'ScannerSettings.php',
        data: 'checked='+checked+'&column='+column+'&save=1',
        type: 'post',
        dataType: 'text',
        success: function(response)
        {
            console.log('save successful')
        }
    });
});
JAVASCRIPT;
    }
}
WebDispatch::conditionalExec();
