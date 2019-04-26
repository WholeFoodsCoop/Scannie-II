<?php
include('../../config.php');
if (!class_exists('PageLayoutA')) {
    include(dirname(__FILE__).'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(dirname(__FILE__).'/../../../common/sqlconnect/SQLManager.php');
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
        include(__DIR__.'/../../config.php');
        $this->addScript('Home.js');

        return <<<HTML
<div align="center" style="padding-top: 15vh; font-size: 22px; font-weight: bold; text-shadow: 1px 1px lightgrey;">
    <div style="max-width: 500px;">
        WFC Useful Links 
        <ul style="text-align: left">
            <li><a href="https://customers.unfi.com/Pages/ProductSearch.aspx">unfi.com</a></li>
            <li><a href=""></a></li>
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
