<?php
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../content/PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include(__DIR__.'/../common/sqlconnect/SQLManager.php');
}
class Page_403 extends PageLayoutA
{

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();

        return false;
    }

    public function pageContent()
    {
        $accesslevel = FormLib::get('accesslevel.php');
        $access = ($accesslevel == true) ? '' : '<div align="center"><div style="margin: 25px; '.$width.'; text-align: center;" class="alert alert-warning">  
                <div><h3>Error 403</h3></div>The page you are trying to access requires a higher authorization level.</div></div>';

        return <<<HTML
$access
HTML;
    }

    public function cssContent()
    {
        return <<<HTML
HTML;

    }

}
WebDispatch::conditionalExec();
