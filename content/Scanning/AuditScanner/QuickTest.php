<?php
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../../common/sqlconnect/SQLManager.php');
}
if (!class_exists('PriceRounder')) {
    include(__DIR__.'/../../../common/lib/PriceRounder.php');
}
class QuickTest extends PageLayoutA
{

    public function preprocess()
    {
        $this->displayFunction = $this->postView();
        $this->__routes[] = 'post<test>';

        return parent::preprocess();
    }

    public function postTestView()
    {
        //var_dump($_POST);
        $ret = '';
        $v = FormLib::get('test');
        $ret .= "<div>v: $v</div>";

        $json = json_decode($v);
        $ret .= "<div>{$json->foo}</div>";
        $ret .= "<div>{$json->one}</div>";

        return <<<HTML
$ret
HTML;
    }

    public function postView()
    {
        $dbc = scanLib::getConObj();

        return <<<HTML
<form method="post" action="QuickTest.php">
    <select name="test">
        <option value="{"values":[1,2,3]}">option A</option>
        <option value='{"foo":"bar","one":"two"}'>Option two</option>
    </select>
    <input type="submit" />
</form>
HTML;
    }


}
WebDispatch::conditionalExec();
