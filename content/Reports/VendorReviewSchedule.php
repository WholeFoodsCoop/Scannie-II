<?php
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
class VendorReviewSchedule extends PageLayoutA
{

    protected $must_authenticate = true;
    protected $auth_types = array(2);

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();

        return false;
    }

    public function pageContent()
    {
        $dbc = ScanLib::getConObj();
        $schedule = array();

        $prep = $dbc->prepare("
            SELECT v.vendorID, v.vendorName, count(p.upc) AS count,
            SUM(CASE WHEN m.superID IN (1,3,4,5,8,9,13,17,18) THEN 1 ELSE 0
                END) AS countMerch,
            SUM(CASE WHEN pr.reviewed > DATE_SUB(NOW(), INTERVAL 1 MONTH) THEN 1 ELSE 0
                END) AS rev1mo
            FROM vendors AS v
                LEFT JOIN vendorItems AS i ON i.vendorID = v.vendorID
                LEFT JOIN products AS p ON p.upc=i.upc AND p.default_vendor_id=i.vendorID
                LEFT JOIN MasterSuperDepts AS m ON m.dept_ID=p.department
                LEFT JOIN prodReview AS pr ON pr.upc=p.upc
            WHERE p.inUse = 1
                AND p.last_sold > DATE_SUB(NOW(), INTERVAL 2 MONTH)
                AND v.vendorID NOT IN ( 1, 2, 70, 7, 22, 23, 25, 28, 30, 35, 38, 42, 61, 65,127,143,146,147,171,191,196,228,230,232,263,264,358,374)
            GROUP BY v.vendorID
            ORDER BY count(p.upc) DESC
            ");
        $res = $dbc->execute($prep);
        $i=1;
        $j=7;
        while ($row = $dbc->fetchRow($res)) {
            $vendorName = $row['vendorName'];
            $vendorID = $row['vendorID'];
            $itemCount = $row['count'];
            $countMerch = $row['countMerch'];
            $rev1mo = $row['rev1mo'];

            if ($countMerch > 0) {
                $schedule[$i][$vendorID]['name'] = $vendorName;
                $schedule[$i][$vendorID]['id'] = $vendorID;
                $schedule[$i][$vendorID]['itemCount'] = $itemCount;
                $schedule[$i][$vendorID]['rev1mo'] = $rev1mo;
                $i++;
                if ($i == 13)
                    $i = 1;
                $schedule[$j][$vendorID]['name'] = $vendorName;
                $schedule[$j][$vendorID]['id'] = $vendorID;
                $schedule[$j][$vendorID]['itemCount'] = $itemCount;
                $schedule[$j][$vendorID]['rev1mo'] = $rev1mo;
                $j++;
                if ($j == 13)
                    $j = 1;
            }
        }
        $schedTxt = '';
        $thead = "<thead><th>ID</th><th>Vendor</th><th>Item Count</th><th>rev1mo</th></thead>";
        for ($i=1; $i<13; $i++) {
            $dateObj = DateTime::createFromFormat('!m', $i);
            $monthName = $dateObj->format('F');
            $schedTxt .= "<h4 class='month-toggle' data-target='$monthName'>$monthName Review List</h4>";
            $td = "<div id='$monthName' class='month-tab'>"; // tab stuff goes here
            $td .= "<table class=\"table table-bordered table-sm small\">$thead";
            foreach ($schedule[$i] as $id => $row) {
                $td .= "<tr>";
                $td .= "<td>{$row['id']}</td>";
                $td .= "<td>{$row['name']}</td>";
                $td .= "<td>{$row['itemCount']}</td>";
                $td .= "<td>{$row['rev1mo']}</td>";
                $td .= "</tr>";
            }
            $td .= "</table>";
            $td .= "</div>";
            $schedTxt .= $td;
        }

        return <<<HTML
<div class="container">
$schedTxt
</div>
HTML;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
$('.month-toggle').click(function(){
    let target = $(this).attr('data-target');
});
JAVASCRIPT;
    }

    public function cssContent()
    {
        return <<<HTML
//.month-tab {
//    display: none;
//}
//.month-toggle {
//    cursor: pointer;
//}
HTML;
    }

}
WebDispatch::conditionalExec();
