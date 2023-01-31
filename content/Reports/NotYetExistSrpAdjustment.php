<?php
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
if (!class_exists('priceRounder')) {
    include_once(__DIR__.'/../../common/lib/PriceRounder.php');
}
class NotYetExistSrpAdjustment extends PageLayoutA
{

    protected $must_authenticate = true;
    protected $auth_types = array(2);

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();
        $this->__routes[] = 'post<vendorID>';

        return parent::preprocess();
    }

    public function postVendorIDView()
    {
        return $this->pageContent();
    }

    private function setVendorSRP($dbc, $upc, $vendorID, $srp)
    {
        $args = array($srp, $upc, $vendorID);
        $prep = $dbc->prepare("UPDATE vendorItems SET srp = ? WHERE upc = ? AND vendorID = ?");
        $res = $dbc->execute($prep, $args);

        return $res;
    }


    public function pageContent()
    {
        $dbc = ScanLib::getConObj();
        $round = new PriceRounder();
        $prodList = array();
        $ret = '';
        $vendorID = FormLib::get('vendorID', false);
        if ($vendorID != false) {
            $prep = $dbc->prepare("SELECT upc FROM products GROUP BY upc");
            $res = $dbc->execute($prep);
            while ($row = $dbc->fetchRow($res)) {
                $upc = $row['upc'];
                $prodList[$upc] = 1;
            }

            $args = array($vendorID);
            $prep = $dbc->prepare("SELECT upc, brand, description, cost, srp FROM vendorItems WHERE vendorID = ?
                AND ABS(cost - srp) < 1");
            $res = $dbc->execute($prep, $args);

            $dbc->startTransaction();
            while ($row = $dbc->fetchRow($res)) {
                $upc = $row['upc'];
                $brand = $row['brand'];
                $description = $row['description'];
                $cost = $row['cost'];
                $srp = $row['srp'];
                $newSrp = $cost / (1 - 0.43);
                $newSrp = $round->round($newSrp);
                if (!array_key_exists($upc, $prodList)) {
                    if ($srp < $newSrp) {
                        $vendList[$upc][] = $row;
                        $ret .= "<div>$upc, $brand, $description, $cost, $srp => $newSrp</div>";
                        $this->setVendorSRP($dbc, $upc, $vendorID, $newSrp);
                    }
                }
            }
            $dbc->commitTransaction();
        }

        return <<<HTML
<div class="container-fluid" style="padding:15px">
<h4>Not Yet Exists Vendor Item SRP Adjustment</h4>
    <div class="row">
        <div class="col-lg-8">
            $ret 
        </div>
        <div class="col-lg-3">
            <form method="post" action="NotYetExistSrpAdjustment.php">
                <label for="vendorID">Enter Vendor ID</label>
                <div class="form-group">
                    <input type="text" name="vendorID" id="vendorID" class="form-control" placeholder="VendorID" value="$vendorID"/>
                </div>
                <div class="form-group">
                    <input type="submit" class="btn btn-default" />
                </div>
            </form>
        </div>
        <div class="col-lg-1"></div>
    </div>
</form>
</div>
HTML;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
JAVASCRIPT;
    }

    public function cssContent()
    {
        return <<<HTML
HTML;
    }

    public function helpContent()
    {
        return <<<HTML
HTML;
    }

}
WebDispatch::conditionalExec();
