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
    protected $BATCHID = 19745;

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();

        return parent::preprocess();
    }

    public function pageContent()
    {
        include(__DIR__.'/../../common/lib/PriceRounder.php');
        $rounder = new PriceRounder();
        $dbc = scanLib::getConObj();
        $data = array();
        $ret = '';
        $items = array();

        $args = array($this->BATCHID);
        $prep = $dbc->prepare("SELECT upc, salePrice FROM batchList WHERE batchID = ?");
        $res = $dbc->execute($prep, $args);
        $ret .= $dbc->error();

        while ($row = $dbc->fetchRow($res)) {
            $items[$row['upc']] = $rounder->round($row['salePrice']);
        }

        $updateP = $dbc->prepare("UPDATE batchList SET salePrice = ? WHERE upc = ? AND batchID = ?");
        foreach ($items as $upc => $salePrice) {
            $updateA = array($salePrice, $upc, $this->BATCHID);
            //$dbc->execute($updateP, $updateA);
        }

        return <<<HTML
<div class="container" style="margin-top: 25px;">
    <div class="row">
        <div class="col-lg-1"></div>
        <div class="col-lg-10">
            $ret
            Done!
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
