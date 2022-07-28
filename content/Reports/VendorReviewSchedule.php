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
        $this->__routes[] = 'post<watch>';
        $this->__routes[] = 'post<remove>';

        return parent::preprocess();
    }

    public function postRemoveHandler()
    {
        $dbc = scanLib::getConObj('SCANALTDB');
        $id = FormLib::get('id', false);

        $args = array($id);
        $prep = $dbc->prepare("DELETE FROM invoices2Look4 WHERE vendorID = ?");
        $res = $dbc->execute($prep, $args);

        return false;
    }

    public function postWatchHandler()
    {
        $dbc = scanLib::getConObj('SCANALTDB');
        $id = FormLib::get('watch', false);

        $args = array($id);
        $prep = $dbc->prepare("INSERT IGNORE into invoices2Look4 (vendorID, details) values (?, '[[Please enter details]]')");
        $res = $dbc->execute($prep, $args);

        return header('location: VendorReviewSchedule.php');
    }

    public function postDetailsHandler()
    {
        $dbc = scanLib::getConObj('SCANALTDB');
        $id = FormLib::get('id', false);
        $details = FormLib::get('details', false);
        $details = urldecode($details);

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
        $vendorOpts = "<option value=0>Choose A Vendor</option>";

        $vendP = $dbc->prepare("SELECT vendorID, vendorName
            FROM vendors");
        $vendR = $dbc->execute($vendP);
        while ($row = $dbc->fetchRow($vendR)) {
            $vendorOpts .= "<option value=\"{$row['vendorID']}\">{$row['vendorName']}</option>";
        }

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
            $invoiceWatch .= "<td class=\"editable-details\" data-id=\"$id\" id=\"id$id\" contentEditable=true>$details</td>";
            $invoiceWatch .= "<td><a href=\"#\" onclick=\"removeWatch($id); return false;\">Remove</a></td>";
            $invoiceWatch .= "</tr>";
        }
        $invoiceWatch .= "</tbody></table>";

        $prep = $dbc->prepare("
            SELECT sch.month, v.vendorID, v.vendorName, count(p.upc) AS count,
            SUM(CASE WHEN pr.reviewed >= DATE_SUB(NOW(), INTERVAL 1 MONTH) THEN 1 ELSE 0
                END) AS rev30,
            SUM(CASE WHEN pr.reviewed >= DATE_SUB(NOW(), INTERVAL 3 MONTH) THEN 1 ELSE 0
                END) AS rev90,
            GROUP_CONCAT(DISTINCT SUBSTRING(m.super_name, 1, 4) ORDER BY m.super_name) AS departments
            FROM woodshed_no_replicate.FixedVendorReviewSchedule AS sch
                LEFT JOIN is4c_op.vendors AS v ON v.vendorID = sch.vendorID
                LEFT JOIN is4c_op.vendorItems AS i ON i.vendorID = v.vendorID
                LEFT JOIN is4c_op.products AS p ON p.upc=i.upc AND p.default_vendor_id=i.vendorID
                LEFT JOIN is4c_op.MasterSuperDepts AS m ON m.dept_ID=p.department
                LEFT JOIN is4c_op.prodReview AS pr ON pr.upc=p.upc
            WHERE p.inUse = 1
                AND p.last_sold > DATE_SUB(NOW(), INTERVAL 2 MONTH)
                AND v.vendorID NOT IN ( 1, 2, 70, 7, 22, 23, 25, 28, 30, 35, 38, 42, 61, 65,127,143,146,147,171,191,196,228,230,232,263,264,358,374)
                AND v.vendorID NOT IN (SELECT vid FROM woodshed_no_replicate.top25)
            GROUP BY sch.vendorID, sch.month
            ORDER BY sch.month ASC, count(p.upc) DESC
        ");
        $data = array();
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            $vendorName = $row['vendorName'];
            $vendorID = $row['vendorID'];
            $itemCount = $row['count'];
            $countMerch = $row['countMerch'];
            $rev30 = $row['rev30'];
            $rev90 = $row['rev90'];
            $depts = $row['departments'];
            $month = $row['month'];

            $tmpDepts = explode(",", $depts);
            $styleDepts = '';
            foreach ($tmpDepts as $name) {
                $rgb = '#'.substr(md5($name), 0, 6);
                $styleDepts .= "<span style=\"font-weight: bold; color: $rgb;\">$name</span>, ";
            }
            $styleDepts = rtrim($styleDepts, " ,");

            $data[$month][] = array(
                'name' => $vendorName,
                'id' => $vendorID,
                'count' => $itemCount,
                'r30' => $rev30,
                'r90' => $rev90,
                'depts' => $styleDepts
            );

        }
        $thead = "<thead><th>ID</th><th>Vendor</th><th>Item Count</th><th>rev30</th><th>rev90</th><th>Master Depts</th></thead>";
        $tablesRet = '';
        $td = '';
        $checklists = '';
        foreach ($data as $month => $arr) {
            $i = 1;
            $dateObj = DateTime::createFromFormat('!m', $month);
            $monthName = $dateObj->format('F');
            $td = "<div id='$monthName' class='monthTable'>";
            $td .= "<h4>$monthName Review List</h4>";
            $td .= "<table class=\"table table-bordered table-sm small\">$thead<tbody>";
            $checklists = "<div class=\"checklists-sql\" data-month=\"$monthName\"><div>
                <label><strong>$monthName</strong> INSERT INTO Checklists Query</label></div>";
            foreach ($arr as $k => $row) {
                $checklistName = str_replace("'", "", $row['name']);
                $checklistName = strtoupper($checklistName);
                $td .= "<tr>";
                $td .= "<td>{$row['id']}</td>";
                $td .= "<td>{$row['name']}</td>";
                $td .= "<td>{$row['count']}</td>";
                $td .= "<td>{$row['r30']}</td>";
                $td .= "<td>{$row['r90']}</td>";
                $td .= "<td>{$row['depts']}</td>";
                $td .= "</tr>";
                $checklists .= "INSERT INTO checklists (tableID, description, active, row) VALUES ('SMV', '$checklistName', 1, $i); ";
                $i++;
            }
            $checklists .= "</div>";
            $td .= "</tbody></table>$checklists</div>";
            $tablesRet .= $td;

        }

        /*
            run one time to get a snapshot of generated schedule in order to create a fixed schedule
        var_dump($schedule);
        $json = json_encode($schedule);
        file_put_contents('test.json', $json);
        */

        return <<<HTML
<div class="container" style="padding:15px">
<h4>Vendor Invoices To Watch</h4>
    <div class="row">
        <div class="col-lg-6">
        </div>
        <div class="col-lg-4">
            <form action="VendorReviewSchedule.php" method="post">
            <div class="form-group">
                <select class="form-control" name="watch" id="watch">$vendorOpts</select>
            </div>
        </div>
        <div class="col-lg-2">
            <div class="form-group">
                <input type="submit" class="btn btn-default form-control">
            </div>
        </div>
    </div>
</form>
$invoiceWatch
<a href="#" onclick="viewCurrentMonth(); false">View Only Current Month</a> | 
<a href="#" onclick="viewAllMonth(); false">View All</a>
$tablesRet
$checklists
</div>
HTML;
    }

    public function javascriptContent()
    {
        $m = date('m');
        $dateObj = DateTime::createFromFormat('!m', $m);
        $currentMonth = $dateObj->format('F');

        return <<<JAVASCRIPT
var currentMonth = "$currentMonth";
$('tr').each(function(){
    let count = $(this).find('td:eq(2)').text();
    count = parseFloat(count);
    let rev30 = $(this).find('td:eq(3)').text();
    rev30 = parseFloat(rev30);
    percent = rev30 / count;
    //if (count == rev30) {
    if (percent > 0.70) {
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
    text = encodeURIComponent(text);
    var id = $(this).attr('data-id');
    console.log('id: '+id+', text: ',+text);
    if (text != lastEditDetails) {
        var curElemID = $(this).attr('id');
        console.log(curElemID);
        lastEditDetails = text;
        $.ajax({
            type: 'post',
            data: 'id='+id+'&details='+text,
            url: 'VendorReviewSchedule.php',
            success: function(resp) {
                console.log(resp);
                if (resp == 'saved') {
                    $('#'+curElemID).animate({backgroundColor: '#AFE1AF'}, 'slow')
                        .animate({backgroundColor: '#FFFFFF'}, 'slow');
                } else {
                    $('#'+curElemID).animate({backgroundColor: '#AFE1AF'}, 'slow')
                        .animate({backgroundColor: '#FF6347'}, 'slow');
                }
            }
        });
    }
});

var viewCurrentMonth = function()
{
    $('div.monthTable').each(function(){
        let id = $(this).attr('id');
        if (id !== 'undefined' && id != currentMonth) {
            $(this).hide();
        }
        $(this).find('.checklists-sql').show();
        console.log('id:'+id+',cm:'+currentMonth);
    });

}
var viewAllMonth = function()
{
    $('div.monthTable').each(function(){
        $(this).show();
    });
}

var removeWatch = function(id) {
    $.ajax({
        type: 'post',
        data: 'id='+id+'&remove=1',
        url: 'VendorReviewSchedule.php',
        success: function(resp) {
            location.reload();
        }
    });
}
JAVASCRIPT;
    }

    public function cssContent()
    {
        return <<<HTML
table {
    box-shadow: 2px 2px lightgrey;
}
.checklists-sql {
    padding: 25px;
    display: none;
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
