<?php
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}

class Home extends PageLayoutA
{

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();

        return false;
    }

    public function pageContent()
    {
        $ret = '';
        $year = date('Y');

        return <<<HTML
<div align="center" style="padding-top: 35vh; font-size: 22px; font-weight: bold; text-shadow: 1px 1px lightgrey;">
    Scannie - version 2.0
    <div>&nbsp;</div>
    <div style="font-size: 14">by Corey Sather</div>
    <div style="font-size: 14">Duluth, MN</div>
    <div style="font-size: 14">Whole Foods Community Co-op &copy; $year</div>
    <!--<div style="font-size: 10"><p style="width: 300px; margin-top: 25px;"><i>For us believing physicists, the distinction between past, present and future is only a stubbornly persistent illusion.</i></p></div>-->
</div>
<div>
<!--<a href="../Testing/ScanTest.php">SCANTEST</a></div>-->
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
