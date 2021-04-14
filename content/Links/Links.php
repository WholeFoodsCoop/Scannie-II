<?php
include('../../config.php');
if (!class_exists('PageLayoutA')) {
    include(dirname(__FILE__).'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(dirname(__FILE__).'/../../common/sqlconnect/SQLManager.php');
}

class Links extends PageLayoutA
{
    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();

        return false;
    }

    public function pageContent()
    {
        $ret = '';

        return <<<HTML
<div align="center" style="padding-top: 15vh; font-size: 22px; font-weight: bold; text-shadow: 1px 1px lightgrey;">
    <div style="max-width: 500px;">
        WFC Useful Links 
        <ul style="text-align: left">
            <li><a href="https://wholesale.frontiercoop.com/datafeeds.html">Frontier Datafeeds</a></li>
            <li><a href="https://www.office.com/">Office 365</a></li>
            <li><a href="https://customers.unfi.com/Pages/ProductSearch.aspx">unfi.com</a></li>
            <li><a href="https://www.ncg.coop/user/14176/edit?pass-reset-token=bfut_MHRa_hkb_a__HXKBaoeYrYsFPV-0ZgsnDrPeXI">NCG.coop</a></li>
            <li><a href="../Scanning/AuditScanner/QuickScanner.php">dontuse</a></li>
        </ul>
    </div>
</div>
<div><a href=""></a></div>
HTML;
    }

    public function javascriptContent()
    {
        return <<<HTML
HTML;
    }

    public function cssContent()
    {
        return <<<HTML
HTML;
    }

}
WebDispatch::conditionalExec();
