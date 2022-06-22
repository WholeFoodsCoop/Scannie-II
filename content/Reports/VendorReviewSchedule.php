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
        $this->__routes[] = 'post<details>';

        return parent::preprocess();
    }

    public function postDetailsHandler()
    {
        $dbc = scanLib::getConObj('SCANALTDB');
        $id = FormLib::get('id', false);
        $details = FormLib::get('details', false);

        $args = array($details, $id);
        $prep = $dbc->prepare("UPDATE invoices2Look4 SET details = ?
            WHERE vendorID = ?");
        $res = $dbc->execute($prep, $args);

        $args = array($id);
        $prep = $dbc->prepare("SELECT details FROM invoices2Look4
            WHERE vendorID = ?");
        $res = $dbc->execute($prep, $args);
        $row = $dbc->fetchRow($res);
        $ret = $row['details'];
        $saved = ($ret == $details) ? 'saved' : 'error saving';

        echo $saved;
        return true;
    }

    public function pageContent()
    {
        $dbc = ScanLib::getConObj();
        $schedule = array();
        $invoiceWatch = "<table class=\"table table-bordered table-sm small\"><thead></thead><tbody>";

        $prep = $dbc->prepare("select i.vendorID, v.vendorName , i.details 
            from woodshed_no_replicate.invoices2Look4 as i 
            left join is4c_op.vendors as v on v.vendorID=i.vendorID;");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            $id = $row['vendorID'];
            $name = $row['vendorName'];
            $details = $row['details'];
            $invoiceWatch .= "<tr>";
            $invoiceWatch .= "<td>$id</td>";
            $invoiceWatch .= "<td>$name</td>";
            $invoiceWatch .= "<td class=\"editable-details\" data-id=\"$id\" contentEditable=true>$details</td>";
            $invoiceWatch .= "</tr>";
        }
        $invoiceWatch .= "</tbody></table>";

        $prep = $dbc->prepare("
            SELECT v.vendorID, v.vendorName, count(p.upc) AS count,
            SUM(CASE WHEN m.superID IN (1,3,4,5,8,9,13,17,18) THEN 1 ELSE 0
                END) AS countMerch,
            SUM(CASE WHEN pr.reviewed > DATE_SUB(NOW(), INTERVAL 1 MONTH) THEN 1 ELSE 0
                END) AS rev30,
            SUM(CASE WHEN pr.reviewed > DATE_SUB(NOW(), INTERVAL 3 MONTH) THEN 1 ELSE 0
                END) AS rev90
            FROM vendors AS v
                LEFT JOIN vendorItems AS i ON i.vendorID = v.vendorID
                LEFT JOIN products AS p ON p.upc=i.upc AND p.default_vendor_id=i.vendorID
                LEFT JOIN MasterSuperDepts AS m ON m.dept_ID=p.department
                LEFT JOIN prodReview AS pr ON pr.upc=p.upc
            WHERE p.inUse = 1
                AND p.last_sold > DATE_SUB(NOW(), INTERVAL 2 MONTH)
                AND v.vendorID NOT IN ( 1, 2, 70, 7, 22, 23, 25, 28, 30, 35, 38, 42, 61, 65,127,143,146,147,171,191,196,228,230,232,263,264,358,374)
                AND v.vendorID NOT IN (SELECT vid FROM woodshed_no_replicate.top25)
            GROUP BY v.vendorID
            ORDER BY count(p.upc) DESC, v.vendorID
            ");
        $res = $dbc->execute($prep);
        $i=1;
        $j=7;
        while ($row = $dbc->fetchRow($res)) {
            $vendorName = $row['vendorName'];
            $vendorID = $row['vendorID'];
            $itemCount = $row['count'];
            $countMerch = $row['countMerch'];
            $rev30 = $row['rev30'];
            $rev90 = $row['rev90'];

            if ($countMerch > 0) {
                $schedule[$i][$vendorID]['name'] = $vendorName;
                $schedule[$i][$vendorID]['id'] = $vendorID;
                $schedule[$i][$vendorID]['itemCount'] = $itemCount;
                $schedule[$i][$vendorID]['rev30'] = $rev30;
                $schedule[$i][$vendorID]['rev90'] = $rev90;
                $i++;
                if ($i == 13)
                    $i = 1;
                $schedule[$j][$vendorID]['name'] = $vendorName;
                $schedule[$j][$vendorID]['id'] = $vendorID;
                $schedule[$j][$vendorID]['itemCount'] = $itemCount;
                $schedule[$j][$vendorID]['rev30'] = $rev30;
                $schedule[$j][$vendorID]['rev90'] = $rev90;
                $j++;
                if ($j == 13)
                    $j = 1;
            }
        }
        $schedTxt = '';
        $thead = "<thead><th>ID</th><th>Vendor</th><th>Item Count</th><th>rev30</th><th>rev90</thead>";
        for ($i=1; $i<13; $i++) {
            $dateObj = DateTime::createFromFormat('!m', $i);
            $monthName = $dateObj->format('F');
            $schedTxt .= "<h4>$monthName Review List</h4>";
            $td = "<div id='$monthName'>"; // tab stuff goes here
            $td .= "<table class=\"table table-bordered table-sm small\">$thead";
            foreach ($schedule[$i] as $id => $row) {
                $td .= "<tr>";
                $td .= "<td>{$row['id']}</td>";
                $td .= "<td>{$row['name']}</td>";
                $td .= "<td>{$row['itemCount']}</td>";
                $td .= "<td>{$row['rev30']}</td>";
                $td .= "<td>{$row['rev90']}</td>";
                $td .= "</tr>";
            }
            $td .= "</table>";
            $td .= "</div>";
            $schedTxt .= $td;
        }

        return <<<HTML
<div class="container" style="padding:15px">
<h4>Vendor Invoices To Watch</h4>
$invoiceWatch
$schedTxt
</div>
HTML;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
$('tr').each(function(){
    let count = $(this).find('td:eq(2)').text();
    let rev30 = $(this).find('td:eq(3)').text();
    if (count == rev30) {
        $(this).css('background-color', 'lightgrey');
    }
});

var lastEditDetails = '';
$('.editable-details').on('focus', function(){
    lastEditDetails = $(this).text();
    console.log(lastEditDetails);
});
$('.editable-details').focusout(function() {
    var text = $(this).text();
    var id = $(this).attr('data-id');
    console.log('id: '+id+', text: ',+text);
    if (text != lastEditDetails) {
        lastEditDetails = text;
        $.ajax({
            type: 'post',
            data: 'id='+id+'&details='+text,
            url: 'VendorReviewSchedule.php',
            success: function(resp) {
                console.log(resp);
            }
        });
    }
});
JAVASCRIPT;
    }

    public function cssContent()
    {
        return <<<HTML
table {
    box-shadow: 2px 2px lightgrey;
}
HTML;
    }

    public function helpContent()
    {
        return <<<HTML
<p>This report details when to review "small vendors." Vendors who are reviewed on a monthly 
basis (UNFI, SELECT, Top 25, All Meat Vendors) are excluded from this report.</p>
<ul>
    <li><b>ID</b> POS vendor's ID</li>
    <li><b>Vendor</b> vendor's name</li>
    <li><b>Item Count</b> number of items sold at either store in the past 2 months</li>
    <li><b>rev30</b> number of items reviewed in the last 30 days</li>
    <li><b>rev90</b> number of items reviewed in the last 90 days</li>
    <li>Rows highlighted in light grey denote that this vendor has had all items reviewed in the past 30 days</li>
</ul>
HTML;
    }

}
WebDispatch::conditionalExec();
