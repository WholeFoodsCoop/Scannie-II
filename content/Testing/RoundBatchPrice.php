<?php
include(__DIR__.'/../../config.php');
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
/*
**  @class RoundBatchPrice 
*   Round all prices in a batch
*   Used one time to round prices in a batch.
*/
class RoundBatchPrice extends PageLayoutA
{

    protected $must_authenticate = true;
    protected $batchID = 20852;

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();
        $this->__routes[] = "post<batchID>";

        return parent::preprocess();
    }


    public function pageContent()
    {
        return <<<HTML
<div class="row" style="padding-top: 25px">
    <div class="col-lg-4"></div>
    <div class="col-lg-4">
        <form action="RoundBatchPrice.php" method="post">
            <div class="form-group">
                <label>Enter Batch ID of Batch To Round</label>
                <input type="text" name="batchID" class="form-control" />
            </div>
            <div class="form-group">
                <input type="submit" class="form-control btn btn-default"/>
            </div>
        </form>
    </div>
    <div class="col-lg-4"></div>
</div>
HTML;
    }

    public function postBatchIDView()
    {
        include(__DIR__.'/../../common/lib/PriceRounder.php');
        $rounder = new PriceRounder();
        $dbc = scanLib::getConObj();
        $data = array();
        $ret = '';
        $items = array();
        $this->batchID = FormLib::get('batchID', false);
        if ($this->batchID === false) {
            return header('location: RoundBatchPrice.php');
        }

        $args = array($this->batchID);
        $prep = $dbc->prepare("SELECT upc, salePrice FROM batchList WHERE batchID = ?");
        $res = $dbc->execute($prep, $args);
        $ret .= $dbc->error();

        while ($row = $dbc->fetchRow($res)) {
            $items[$row['upc']] = $rounder->round($row['salePrice']);
        }

        $updateP = $dbc->prepare("UPDATE batchList SET salePrice = ? WHERE upc = ? AND batchID = ?");
        foreach ($items as $upc => $salePrice) {
            $updateA = array($salePrice, $upc, $this->batchID);
            $dbc->execute($updateP, $updateA);
        }

        return <<<HTML
<div class="container" style="margin-top: 25px;">
    <div class="row">
        <div class="col-lg-1"></div>
        <div class="col-lg-10">
            $ret
            <div class="alert alert-success">Done!</div>
            <a href="#" onClick="window.location.href = 'RoundBatchPrice.php';">Back</a>
        </div>
        <div class="col-lg-2"></div>
    </div>
</div>
HTML;
    }

    public function cssContent()
    {
        return <<<HTML
HTML;
    }

}
WebDispatch::conditionalExec();
