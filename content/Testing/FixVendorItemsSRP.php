<?php
include(__DIR__.'/../../config.php');
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
/*
**  @class FixVendorItemsSRP
*   Fix srps for items that don't exist yet
*   to prevent new items from being created 
*   with bad prices.
*/
class FixVendorItemsSRP extends PageLayoutA
{

    protected $must_authenticate = true;

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();
        $this->__routes[] = "post<vendorID>";

        return parent::preprocess();
    }

    public function postVendorIDView()
    {
        $ret = '';
        $data = array();
        $vendorID = FormLib::get('vendorID', 358);
        $dbc = ScanLib::getConObj();

        $args = array($vendorID);
        $prep = $dbc->prepare("select v.upc, v.brand, v.description, v.cost, v.srp, v.srp / v.cost from vendorItems as v where vendorID = ? and srp / v.cost > 0.95 and srp / v.cost < 1.10");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $upc = $row['upc'];
            $data[$upc] = 1;
        }
        $ret .= $dbc->error();

        $args = array($vendorID, $vendorID);
        $prep = $dbc->prepare("select upc, brand, description, cost, srp, srp / cost from vendorItems where vendorID = ? and srp / cost > 0.95 and srp / cost < 1.10 and upc in (select upc from products where default_vendor_id = ?);");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $upc = $row['upc'];
            unset($data[$upc]);
        }
        $ret .= $dbc->error();

        $ret .= (count($data) == 0) ? "<div>No SRPs to fix for this vendor</div>" : "<div><b>Bad SRPs being fixed</b>: ".count($data)."</div>";

        if (count($data) > 0) {
            $actionP = $dbc->prepare("UPDATE vendorItems SET srp = cost / 0.69 WHERE upc = ? AND vendorID = ?");
            $dbc->startTransaction();
            foreach ($data as $upc => $na) {
                $actionA = array($upc, $vendorID);
                $actionR = $dbc->execute($actionP, $actionA);
                $ret .= $dbc->error();
            }
            $dbc->commitTransaction();
            $ret .= $dbc->error();
        }


        return <<<HTML
{$this->formContent()}
<div class="row" style="width: 100%;">
    <div class="col-lg-4"></div>
    <div class="col-lg-4">
        <div style="padding-top: 25;">
            <b>Vendor</b>: $vendorID
            <div>$ret</div>
        </div>
    </div>
    <div class="col-lg-4"></div>
</div>
HTML;
    }

    public function pageContent()
    {
        return $this->formContent();
    }

    private function formContent()
    {
        return <<<HTML
<div class="row" style="padding-top: 25px; width: 100%">
    <div class="col-lg-4"></div>
    <div class="col-lg-4">
        <h4>Find & Fix Bad SRP (new products)</h4>
        <div style="padding: 16px; background: #f8f8f8; border: 1px solid lightgrey; border-radius: 4px">
            <p>This page finds rows in vendoritems for products that do not yet exist - that also have close to matching
                cost & srp columns, and increases the srp to at least meet a 31% margin.</p>
        </div>
        <div style="padding-top: 25px;"></div>
        <form action="FixVendorItemsSRP.php" method="post">
            <div class="form-group">
                <label>Select a Vendor</label>
                <input type="text" name="vendorID" class="form-control" value=358 />
            </div>
            <div class="form-group">
                <input type="submit" class="form-control btn btn-default" id="submit" onclick=""/>
            </div>
        </form>
    </div>
    <div class="col-lg-4"></div>
</div>
HTML;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
$('#submit').click(function() {
    var c = confirm("By clicking Submit, you are choosing to update SRPs. Would you like to coninue?");
    if (c == true) {
        return true;
    } else {
        return false;
    }
});
JAVASCRIPT;
    }

    public function cssContent()
    {
        return <<<HTML
HTML;
    }

}
WebDispatch::conditionalExec();
