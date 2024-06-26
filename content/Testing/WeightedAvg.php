<?php
include(__DIR__.'/../../config.php');
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
/*
**  @class WeightedAvg 
*/
class WeightedAvg extends PageLayoutA
{

    protected $must_authenticate = true;

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();

        return parent::preprocess();
    }

    public function pageContent()
    {
        $dbc = scanLib::getConObj();
        $data = array();
        $td = "";
        $UNFupcs = FormLib::get('upcs');
        $upcs = explode("\r\n", $UNFupcs);

        list($inStr, $args) = $dbc->safeInClause($upcs);
        $prep = $dbc->prepare("
SELECT p.upc, p.brand, p.description, 

#    p.cost,

    CASE
        WHEN f.futureCost IS NOT NULL THEN f.futureCost
        ELSE p.cost
    END AS cost,

    p.default_vendor_id,
    SUM(d.ItemQtty) AS mt
FROM products AS p 
LEFT JOIN is4c_trans.dlog_90_view AS d
    ON p.upc=d.upc
LEFT JOIN FutureVendorItems AS f ON f.upc=p.upc AND f.vendorID=p.default_vendor_id AND f.startDate > DATE(NOW())
WHERE d.tdate > NOW() - INTERVAL 30 DAY
    AND p.upc IN ($inStr)
    GROUP BY d.upc
    ORDER BY SUM(ItemQtty)
");
        $res = $dbc->execute($prep, $args);
        $td .= $dbc->error();
        $costAvg = 0;

        $sumMt = 0;
        while ($row = $dbc->fetchRow($res)) {
            $upc = $row['upc'];
            $brand = $row['brand'];
            $desc = $row['description'];
            $cost = $row['cost'];
            $mt = $row['mt'];
            $vendorID = $row['default_vendor_id'];
            $data[$upc]['brand'] = $brand;
            $data[$upc]['desc'] = $desc;
            $data[$upc]['mt'] = $mt;
            $data[$upc]['cost'] = $cost;
            $data[$upc]['vendorID'] = $vendorID;
            $sumMt += $mt;
        }

        foreach ($data as $upc => $row) {
            $brand = $row['brand'];
            $desc = $row['desc'];
            $cost = $row['cost'];
            $vendorID = $row['vendorID'];
            if ($vendorID == 1) 
                $vendorID = 'UNFI';
            if ($vendorID == 2) 
                $vendorID = 'SELECT';
            $mt = $row['mt'];
            $costMt = $cost * $mt / $sumMt;
            $costAvg += $costMt;

            $td .= sprintf(
                '<tr">
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                </tr>',
                $upc, $vendorID, $brand, $desc, $mt, $cost, round($mt / $sumMt, 2)*100 . '%'
            );
        }
        $costAvg = round($costAvg, 3);

        return <<<HTML
<div class="container" style="margin-top: 25px;">
    <div class="row">
        <div class="col-lg-3">
            <form>
                <div class="form-group">
                    <textarea class="form-control" rows=20 name="upcs">$UNFupcs</textarea>
                </div>
                <div class="form-group">
                    <input type="submit" class="btn btn-default">
                </div>
            </form>
        </div>
        <div class="col-lg-9">
            <table class="table table-bordered condensed small table-sm">
                <thead>
                    <th>upc</th>
                    <th>vendor</th>
                    <th>brand</th>
                    <th>description</th>
                    <th>mt</th>
                    <th>cost</th>
                    <th>mt%</th>
                </thead>
                <tbody>$td</tbody>
            </table> 
            <table class="table table-bordered condensed small table-sm">
                <tbody>
                    <tr><td>Total Units Moved</td><td>$sumMt</td></tr>
                    <tr><td>Weighted Avg Cost</td><td>$costAvg</td></tr>
                </tbody>
            </table> 
        </div>
    </div>
</div>
HTML;
    }

    public function helpContent()
    {
        return <<<HTML
<div><strong>Weighted Average</strong></div>
<p>Enter a list of UPCs to calculate an average cost (calculation
    based on item movement over the last 90 days).</p>
HTML;
    }

}
WebDispatch::conditionalExec();
