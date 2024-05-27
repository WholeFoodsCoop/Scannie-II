<?php
include(__DIR__.'/../../config.php');
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
class PerfectGrid extends PageLayoutA
{

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();

        return parent::preprocess();
    }

    public function pageContent()
    {
        $ret = "";
        for ($i=0; $i<600; $i++) {
            $square = "<div class=\"colorSquare\" id=\"square{$i}\" style=\"background-color: white; border: 1px solid grey\"></div>";
            $ret .= $square;
        }
        
        return <<<HTML
{$ret}
HTML;
    }

    public function cssContent()
    {
        return <<<HTML
body {
    background-repeat: no-repeat;
    background-attachment: fixed;
    color: white;
    overflow-y: hidden;
}
.colorSquare {
    width: 2.5vw;
    height: 6vh;
    float: left;
}
HTML;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
JAVASCRIPT;
    }
}
WebDispatch::conditionalExec();
