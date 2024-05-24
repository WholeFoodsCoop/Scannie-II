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
        $column = FormLib::get('column');
        $checked = FormLib::get('checked');
        $checked = ($checked == 'true') ? 1 : 0;
        $_SESSION['ScannieConfig']['AuditSettings'][$column] = $checked;
        $a = array($checked, session_id());
        echo $column . ' edited.';

        return false;
    }

    public function postView()
    {
        $SESSION_ID = session_id();
        $a = array($SESSION_ID);
        foreach ($_SESSION['ScannieConfig']['AuditSettings'] as $setting => $value) {
            ${'checked_'.$setting} = ($value) ? 'checked' : '';
        }

        $scanner_settings = array();
        return <<<HTML
<div class="container-fluid" style="margin-top: 25px;">
    <div class="row">
        <div class="col-lg-4"></div>
        <div class="col-lg-4">
            <label><strong>GO TO</strong></label>
            <ul>
                <li><div class="form-group"><a href="AuditScanner/ProductScanner.php" class="btn btn-default form-control" style="font-weight: bold">Audit Scanner</a></div></li>
                <li><div class="form-group"><a href="BatchCheck/SCS.php" class="btn btn-default form-control" style="color: green; font-weight: bold">Batch Check</a></div></li>
            </ul>
            <form method="post">
            <label><strong>Add Linea Sound on Scan</strong>:</label>
                <ul>
                    <li>
                        <label>Beep After Scan: </label>
                        <input type="checkbox" name="scanBeep" value=1 id="toggleBeep" $checked_scanBeep/>
                    </li>
                </ul>
                <input type="hidden" name="sessionID" id="sessionID" value="$a" />
            </form>
            <form method="post">
                <label><strong>Audit Scanner</strong> <a href="" data-target="#scanner-settings" data-toggle="collapse">Settings</a></label>
                <ul class="" id="scanner-settings">
                    <li>
                        <label>Par</label>
                        <input type="checkbox" name="auditPar" value=1 id="togglePar" $checked_auditPar/>
                    </li>
                    <li>
                        <label>Cost/Price/Margin</label>
                        <input type="checkbox" name="auditCost" value=1 id="toggleCost" $checked_auditCost/>
                    </li>
                    <li>
                        <label>SRP/Margin</label>
                        <input type="checkbox" name="auditSrp" value=1 id="toggleSrp" $checked_auditSrp/>
                    </li>
                    <li>
                        <label>Price Rule Type</label>
                        <input type="checkbox" name="auditPrtID" value=1 id="togglePrtID" $checked_auditPrtID/>
                    </li>
                    <li>
                        <label>Desc, Brand, Dept</label>
                        <input type="checkbox" name="auditProdInfo" value=1 id="toggleProdInfo" $checked_auditProdInfo/>
                    </li>
                    <li>
                        <label>Vendor Info</label>
                        <input type="checkbox" name="auditVendorInfo" value=1 id="toggleVendorInfo" $checked_auditVendorInfo/>
                    </li>
                    <li>
                        <label>Prod Location</label>
                        <input type="checkbox" name="auditLocations" value=1 id="toggleLocations" $checked_auditLocations/>
                    </li>
                    <li>
                        <label>Size</label>
                        <input type="checkbox" name="auditSize" value=1 id="toggleSize" $checked_auditSize/>
                    </li>
                    <li>
                        <label>Sign Info</label>
                        <input type="checkbox" name="auditSignInfo" value=1 id="toggleSignInfo" $checked_auditSignInfo/>
                    </li>
                    <li>
                        <label>Sale Info</label>
                        <input type="checkbox" name="auditSaleInfo" value=1 id="toggleSaleInfo" $checked_auditSaleInfo/>
                    </li>
                    <li>
                        <label>Socket Device</label>
                        <input type="checkbox" name="socketDevice" value=1 id="toggleSocketDevice" $checked_socketDevice/>
                    </li>
                </ul>
                <input type="hidden" name="sessionID" id="sessionID" value="$a" />
            </form>
        </div>
        </div>
        <div class="col-lg-4"></div>
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
            console.log('save successful');
            console.log(response);
        }
    });
});
JAVASCRIPT;
    }
}
WebDispatch::conditionalExec();
