<?php
include(__DIR__.'/../../config.php');
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
/*
**  @class RestTest 
*/
class RestTest extends PageLayoutA
{

    protected $must_authenticate = true;

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();
        $this->__routes[] = 'get<test>';
        $this->__routes[] = 'post<test>';

        return parent::preprocess();
    }

    public function getTestView()
    {
        return <<<HTML
Hello, GET routing test successful.
<div><button onclick="location.href = 'RestTest.php';">RELOAD test</button></div>
HTML;
    }

    public function postTestView()
    {
        return <<<HTML
Hello, POST routing test successful.
<div><button onclick="location.href = 'RestTest.php';">RELOAD test</button></div>
HTML;
    }

    public function pageContent()
    {
        $post = $_POST['test'];
        $get = $_GET['test'];
        return <<<HTML
<div align="center">
<p style="max-width: 250px;  text-align: justify;">click either of the following buttons. If the page does not change, then RESTful routing is broken.</p>
<div><form method="post"><button name="test" value="1">POST test</button></form></div>
<div><form method="get"><button name="test" value="1">GET test</button></form></div>
<div>POST value: $post</div>
<div>GET value: $get</div>
</div>
HTML;
    }

    public function cssContent()
    {
        return <<<HTML
button {
    width: 100px;
}
HTML;
    }

}
WebDispatch::conditionalExec();
