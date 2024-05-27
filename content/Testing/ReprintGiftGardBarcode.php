<?php
include(__DIR__.'/../../config.php');
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
/*
**  @class ReprintGiftGardBarcode 
*/
class ReprintGiftGardBarcode extends PageLayoutA
{

    protected $must_authenticate = false;

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();
        $this->__routes[] = 'post<gcnum>';

        return parent::preprocess();
    }

    public function postGcnumView()
    {
        $dbc = scanLib::getConObj();
        $pad = 4900000000000;
        $gcnum = FormLib::get('gcnum');
        $gc = $pad + $gcnum;

        $args = array($gc);
        $prep = $dbc->prepare("INSERT INTO shelftags (id, upc) VALUES (29, ?);");
        $dbc->execute($prep, $args);
        $er = $dbc->error();

        return $this->pageContent($er);
    }

    public function pageContent($er=null)
    {
        $post = $_POST['test'];
        $get = $_GET['test'];
        
        $error = ($er == null) ? '' : "<div class=\"alert alert-danger\">$er</div>";

        return <<<HTML
<div class="row" style="padding:25px">
    <div class="col-lg-4"></div>
    <div class="col-lg-4">
        $error
        <form action="ReprintGiftGardBarcode.php" method="post">
        <div class="form-group">
            <label for="gcnum">Type just the last digits of the gift card to reprint.</label>
            <input class="form-control" name="gcnum" id="gcnum" />
        </div>
        <div class="form-group">
            <input class="btn btn-default" type="submit" />
        </div>
        </form>
        <label>Tags are sent to [Denfeld] SCANNING barcodes/shelftags Queue on the </label> 
        <a href="http://key/git/fannie/admin/labels/ShelfTagIndex.php">Print Tags Page</a>
    </div>
    <div class="col-lg-4"></div>
</div>
HTML;
    }

    public function cssContent()
    {
        return <<<HTML
HTML;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
JAVASCRIPT;
    }

}
WebDispatch::conditionalExec();
