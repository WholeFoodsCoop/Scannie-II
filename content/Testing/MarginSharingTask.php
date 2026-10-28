<?php
include(__DIR__.'/../../config.php');
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
if (!class_exists('PriceRounder')) {
    include_once(__DIR__.'/../../common/lib/PriceRounder.php');
}
/*
**  @class MarginSharingTask 
*/
class MarginSharingTask extends PageLayoutA
{

    protected $must_authenticate = true;

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();
        $this->__routes[] = "post<recalculate>";

        return parent::preprocess();
    }

    private function getWeightedInfo($upcs, $msID)
    {
        $dbc = scanLib::getConObj();
        $rounder = new PriceRounder();

        //CREATE TABLE MarginShareArch (msID INT, cost DECIMAL(10,3), majorityID INT, targetSrp DECIMAL(10,2), date DATETIME);
        $archiveP = $dbc->prepare("INSERT INTO MarginShareArch (msID, cost, majorityID, targetSrp, date) 
            SELECT msID, weightedCost, majorityID, targetSrp, NOW() FROM MarginShares WHERE msID=?");

        $updateP = $dbc->prepare("UPDATE MarginShares SET majorityID=?, weightedCost=?, margin=?, modified=NOW()
            WHERE msID=?");

        $updateSrpP = $dbc->prepare("UPDATE MarginShares SET targetSrp = ? WHERE msID=?");

        $marginP = $dbc->prepare("SELECT 
            p.upc,	
            vd.margin as c,
            c.margin as a,
            b.margin as b  ,
            CASE WHEN c.margin THEN c.margin ELSE b.margin END AS TargetMargin
        FROM products AS p
            LEFT JOIN vendors AS v ON v.vendorID=p.default_vendor_id
            LEFT JOIN vendorItems AS vi ON vi.upc=p.upc AND vi.vendorID=p.default_vendor_id
            LEFT JOIN departments AS b ON p.department=b.dept_no 
            LEFT JOIN VendorSpecificMargins AS c ON c.vendorID=p.default_vendor_id AND c.deptID=p.department
            LEFT JOIN vendorDepartments AS vd ON vd.vendorID=p.default_vendor_id AND vd.deptID=vi.vendorDept
        WHERE p.upc = ?
        GROUP BY upc");

        list($inStr, $args) = $dbc->safeInClause($upcs);
        $prep = $dbc->prepare("
SELECT t.upc, 
#p.cost, 
p.default_vendor_id,
CASE
    WHEN f.futureCost IS NOT NULL THEN f.futureCost
    ELSE p.cost
END AS cost,
COALESCE(p.brand, '') AS brand, 
CASE WHEN p.description IS NULL THEN t.description ELSE p.description END as description, 
SUM(CASE WHEN trans_status IN('','0') THEN 1 WHEN trans_status='V' THEN -1 ELSE 0 END) as rings, 
SUM(CASE WHEN t.trans_status = 'M' THEN 0 WHEN t.trans_subtype = 'OG' THEN 0 WHEN t.unitPrice = 0.01 THEN 1 ELSE t.quantity END) as mt,
SUM(t.total) AS total, 
t.department, d.dept_name, m.super_name, v.vendorName AS distributor, i.sku 
FROM is4c_trans.dlog_90_view AS t 
LEFT JOIN is4c_op.products AS p ON p.upc = t.upc AND p.store_id=1 
LEFT JOIN departments AS d ON t.department=d.dept_no 
LEFT JOIN MasterSuperDepts AS m ON t.department=m.dept_ID 
LEFT JOIN subdepts AS b ON p.subdept=b.subdept_no 
LEFT JOIN vendors AS v ON p.default_vendor_id=v.vendorID 
LEFT JOIN vendorItems AS i ON p.upc=i.upc AND p.default_vendor_id=i.vendorID 
LEFT JOIN FutureVendorItems AS f ON f.upc=p.upc AND f.vendorID=p.default_vendor_id AND f.startDate > DATE(NOW())
WHERE t.tdate > NOW() - INTERVAL 30 DAY
    AND t.memType NOT IN (4) 
    AND t.upc IN ($inStr) 
    GROUP BY t.upc, COALESCE(p.brand, ''), CASE WHEN p.description IS NULL THEN t.description ELSE p.description END, CASE WHEN t.trans_status='R' THEN 'Refund' ELSE 'Sale' END, t.department, d.dept_name, m.super_name, v.vendorName, i.sku 
    ORDER BY SUM(t.total) ASC
");
        $res = $dbc->execute($prep, $args);
        $td .= $dbc->error();
        $costAvg = 0;
        $vendors = array();

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

            if (!array_key_exists($vendorID, $vendors)) {
                $vendors[$vendorID] = $mt;
            } else {
                $vendors[$vendorID] += $mt;
            }
        }

        $owner = array_search(max($vendors), $vendors);

        $sampleUpc = '';
        foreach ($data as $upc => $row) {
            $sampleUpc = $upc;
            $brand = $row['brand'];
            $desc = $row['desc'];
            $cost = $row['cost'];
            $vendorID = $row['vendorID'];
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

        $marginR = $dbc->execute($marginP, array($sampleUpc));

        $marginW = $dbc->fetchRow($marginR);
        $target = $marginW["TargetMargin"];
        $rawSrp = $costAvg / (1 - $target);

        $updateR = $dbc->execute($updateP, array($owner, $costAvg, $target, $msID));
        $archiveR = $dbc->execute($archiveP, array($msID));
        echo $dbc->error();
        $updateSrpR = $dbc->execute($updateSrpP, array($rounder->round($rawSrp), $msID));
    }

    private function getShareUpcs($searchID, $items)
    {
        $upcs = array();

        foreach ($items as $upc => $curID) {
            if ($curID == $searchID) {
                $upcs[] = $upc;
            }
        }

        return $upcs;
    }

    public function postRecalculateView()
    {
        $dbc = scanLib::getConObj();
        $items = array();
        $shares = array();
        $ret = "";

        $itemsP = $dbc->prepare("SELECT * FROM MarginShareItems");
        $itemsR = $dbc->execute($itemsP);
        while ($row = $dbc->fetchRow($itemsR)) {
            $msID = $row['msID'];
            $items[$row['upc']] = $msID;

            if (!in_array($msID, $shares)) {
                $shares[] = $msID;
            }
        }

        foreach ($shares as $msID) {
            $upcs = $this->getShareUpcs($msID, $items);
            $this->getWeightedInfo($upcs, $msID);
        }

        $checkP = $dbc->prepare("SELECT 
                p.upc, i.msID, p.normal_price, s.targetSrp,
                p.brand, p.description
            FROM MarginShareItems i
                INNER JOIN MarginShares s ON s.msID=i.msID
                INNER JOIN products p ON p.upc=i.upc
            WHERE p.normal_price != s.targetSrp
                AND s.weightedCost <> 0
            GROUP BY p.upc
            ORDER BY i.msID, p.brand, p.upc");

        $checkR = $dbc->execute($checkP);
        $td = "";
        while ($row = $dbc->fetchRow($checkR)) {
            $upc = $row['upc'];
            $normal_price = $row['normal_price'];
            $msID = $row['msID'];
            $targetSrp = $row['targetSrp'];
            $brand = $row['brand'];
            $description = $row['description'];
            $td .= "<tr>";
            $td .= "<td>$msID</td>";
            $td .= "<td>$upc</td>";
            $td .= "<td>$brand</td>";
            $td .= "<td>$description</td>";
            $td .= "<td>$normal_price</td>";
            $td .= "<td>$targetSrp</td>";
            $td .= "</tr>";
        }

        return <<<HTML
<div class="container" style="margin-top: 25px;">
    <div class="row">
        <div class="col-lg-12">
            <div class="form-group">
                <div class="card" style="padding: 25px;">Line Price price rule items' costs & pricing information
                    has been updated and logged.</div>
            </div>
            <div class="form-group">
                <div class="card" style="padding: 25px;">Items in the table below ares suggested price changes (normal_price <> target SRP).</div>
            </div>
            <ul>
                <li>
                    Return <a href="MarginSharingTask.php">Home</a>
                </li>
                <li id="GenerateExcelFileDiv">
                    <a href="#" id="ExportCsvAnchor">Export CSV</a>
                </li>
            </ul>
            <table class="table table-bordered table-striped table-sm small">
                <thead><th>id</th><th>upc</th><th>brand</th><th>description</th><th>price</th><th>target</th></thead>
                <tbody>$td</tbody>
            </table>
        </div>
    </div>
</div>
HTML;
    }

    public function pageContent()
    {
        $dbc = scanLib::getConObj();
        $items = array();
        $shares = array();
        $ret = "";
        $td = "";

        // New Chunk Start
        $itemsP = $dbc->prepare("SELECT * FROM MarginShares");
        $itemsR = $dbc->execute($itemsP);
        while ($row = $dbc->fetchRow($itemsR)) {
            $msID = $row['msID'];
            $weightedCost = $row['weightedCost'];
            $margin = $row['margin'];
            $majorityID = $row['majorityID'];
            $name = $row['name'];
            $modified = $row['modified'];
            $targetSrp = $row['targetSrp'];
            $td .= "<tr>";
            $td .= "<td>$msID</td>";
            $td .= "<td>$name</td>";
            $td .= "<td>$majorityID</td>";
            $td .= "<td>$weightedCost</td>";
            $td .= "<td>$targetSrp</td>";
            $td .= "<td>$modified</td>";
            $td .= "</tr>";
        }

        return <<<HTML
<div class="container" style="margin-top: 25px;">
    <div class="row">
        <div class="col-lg-12">
            <div class="form-group">
                <div class="card" style="padding: 25px;">
                    <div style="font-size: 18px;">Margin Shares</div>
                    <br/>
                    <p>This page shows the current average weighted cost of each set of products that
                        are setupt to use AWC. If future costs are entered, the calculator will use 
                        those costs, else, they will use the current cost. Costs are weighted using 
                        the last 30 days transactions.</p>
                </div>
                <div style="padding: 10px;"></div>
                <ul>
                    <li>
                        <form action="MarginSharingTask.php" method="post" name="recalc-form">
                            <input type="hidden" name="recalculate" value=1 />
                            <a href='#'onclick="document.forms['recalc-form'].submit();">Re-calculate</a> Weighted Data by Movement
                        </form>
                    </li>
                </ul>
            </div>
            <table class="table table-bordered table-striped table-sm small">
                <thead><th>id</th><th>name</th><th>majority</th><th>weighted cost</th><th>SRP</th><th>modified</th></thead>
                <tbody>$td</tbody>
            </table>
        </div>
    </div>
</div>
HTML;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
/*
    Write a CSV file of table 
*/
var CsvTitleData = [];
var CsvTableData = [];
const PrepCSV = function() {

    $('th').each(function(){
        let value = $(this).text();
        value = value.replaceAll(",", " ");
        CsvTitleData.push(value);
    });

    $('tr').each(function(){
        let tmpArr = [];
        $(this).find('td').each(function(){
            if ($(this).hasClass('autoPar')) {
                // do nothing
            } else {
                let value = $(this).text();
                tmpArr.push(value);
            }
        });
        CsvTableData.push(tmpArr);
    });

    let thead = JSON.stringify(CsvTitleData);
    let td = JSON.stringify(CsvTableData);
    td = encodeURIComponent(td);

    $.ajax({
        type: 'post',
        data: "exportCsv=1&thead="+thead+"&tableData="+td,
        url: '../Scanning/AuditScanner/AuditReport.php',
        success: function(response) {
            console.log('Export CSV');

            let download = document.createElement("a");
            download.href = '../Scanning/AuditScanner/noauto/'+response;
            download.innerHTML = 'Download File';
            download.style.padding = '5px';
            download.style.borderRadius = '3px';
            download.style.border = '1px solid lightgrey';
            download.addEventListener('click', function(){
                $(this).remove();
            }, false);

            $('#GenerateExcelFileDiv').append(download);
        },
        error: function(response) {
        },
    });
}
$('#ExportCsvAnchor').on('click', function(){
    PrepCSV();
});
JAVASCRIPT;
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
