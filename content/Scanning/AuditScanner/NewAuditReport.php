<?php
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../../common/sqlconnect/SQLManager.php');
}
if (!class_exists('PriceRounder')) {
    include(__DIR__.'/../../../common/lib/PriceRounder.php');
}
class NewAuditReport extends PageLayoutA
{

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();
        $this->__routes[] = 'post<test>';
        $this->__routes[] = 'post<notes>';
        $this->__routes[] = 'post<fetch>';
        $this->__routes[] = 'post<clear>';
        $this->__routes[] = 'post<upcs>';
        $this->__routes[] = 'post<deleteRow>';

        return parent::preprocess();
    }

    public function postTestHandler()
    {
        $json = array('test'=>'successful');
        echo json_encode($json);

        return false;
    }

    public function postDeleteRowHandler()
    {
        $store_id = FormLib::get('storeID');
        $username = FormLib::get('username');
        $upc = substr(FormLib::get('upc'), 3);
        $rowclicked = FormLib::get('rowclicked');
        $json = array();

        $dbc = ScanLib::getConObj();
        $args = array($upc, $store_id, $username);
        $prep = $dbc->prepare('DELETE FROM woodshed_no_replicate.AuditScanner WHERE upc = ? AND store_id = ? AND username = ?');
        $dbc->execute($prep, $args);
        if ($er = $dbc->error()) {
            $json['dbc-error'] = "<div class=\"alert alert-danger\">$er</div>";
        }

        return false;
    }

    public function postClearHandler()
    {
        $dbc = ScanLib::getConObj();
        $storeID = FormLib::get('storeID');
        $username = FormLib::get('username');
        $args = array($storeID, $username);
        $query = $dbc->prepare("DELETE FROM woodshed_no_replicate.AuditScanner WHERE store_id = ? AND username = ?");
        $dbc->execute($query, $args);

        $json = array('test'=>'successful');
        echo json_encode($json);

        return false;
    }


    public function postNotesHandler()
    {
        $dbc = ScanLib::getConObj();
        $storeID = FormLib::get('storeID');
        $username = FormLib::get('username');
        $args = array($storeID, $username);
        $query = $dbc->prepare("UPDATE woodshed_no_replicate.AuditScanner SET notes = '' WHERE store_id = ? AND username = ?");
        $dbc->execute($query, $args);

        $json = array('test'=>'successful');
        echo json_encode($json);

        return false;
    }

    public function postUpcsHandler()
    {
        $dbc = ScanLib::getConObj();
        $storeID = FormLib::get('storeID');
        $username = FormLib::get('username');

        $upcs = FormLib::get('upcs');
        $plus = array();
        $chunks = explode("\r\n", $upcs);
        foreach ($chunks as $key => $str) {
            $str = scanLib::upcParse($str);
            $str = scanLib::upcPreparse($str);
            $plus[] = $str;
        }

        foreach ($plus as $upc) {
            $args = array($storeID, $upc);
            $query = $dbc->prepare("
                SELECT
                    p.cost,
                    p.normal_price,
                    p.description,
                    p.brand,
                    p.default_vendor_id,
                    p.inUse,
                    p.auto_par,
                    v.vendorName,
                    vi.vendorDept,
                    p.department,
                    d.dept_name,
                    p.price_rule_id,
                    vd.margin AS unfiMarg,
                    d.margin AS deptMarg,
                    pu.description AS signdesc,
                    pu.brand AS signbrand,
                    v.shippingMarkup,
                    v.discountRate,
                    vi.sku
                FROM products AS p
                    LEFT JOIN productUser AS pu ON p.upc = pu.upc
                    LEFT JOIN departments AS d ON p.department=d.dept_no
                    LEFT JOIN vendors AS v ON p.default_vendor_id=v.vendorID
                    LEFT JOIN vendorItems AS vi
                        ON p.upc = vi.upc
                            AND p.default_vendor_id = vi.vendorID
                    LEFT JOIN vendorDepartments AS vd
                        ON vd.vendorID = p.default_vendor_id
                            AND vd.deptID = vi.vendorDept
                WHERE p.store_id = ?
                    AND p.upc = ?
                LIMIT 1
            ");
            $result = $dbc->execute($query, $args);
            while ($row = $dbc->fetchRow($result)) {
                $cost = $row['cost'];
                $price = $row['normal_price'];
                $desc = $row['description'];
                $brand = $row['brand'];
                $vendor = '<span class="vid">id['.$row['default_vendor_id'].'] </span>'.$row['vendorName'];
                $vd = $row['default_vendor_id'].' '.$row['vendorName'];
                $dept = $row['department'].' '.$row['dept_name'];
                $pid = $row['price_rule_id'];
                $unfiMarg = $row['unfiMarg'];
                $deptMarg = $row['deptMarg'];
                $signDesc = $row['signdesc'];
                $signBrand = $row['signbrand'];
                $inUse = $row['inUse'];
                $narrow = $row['narrow'];
                $markup = $row['shippingMarkup'];
                $discount = $row['discountRate'];
                $sku = $row['sku'];

                $adjcost = $cost;
                if ($markup > 0) $adjcost += $cost * $markup;
                if ($discount > 0) $adjcost -= $cost * $discount;

                if ($row['default_vendor_id'] == 1) {
                    $dMargin = $row['unfiMarg'];
                } else {
                    $dMargin = $row['deptMarg'];
                }
            }
            if ($dbc->error()) echo $dbc->error();

            $margin = ($price - $adjcost) / $price;
            $rSrp = $adjcost / (1 - $dMargin);
            $rounder = new PriceRounder();
            $srp = $rounder->round($rSrp);
            $sMargin = ($srp - $adjcost ) / $srp;

            $passcost = $cost;
            if ($cost != $adjcost) $passcost = $adjcost;

            $argsA = array($upc, $username, $storeID);
            $prepA = $dbc->prepare("SELECT * FROM woodshed_no_replicate.AuditScanner WHERE upc = ? AND username = ? AND store_id = ? LIMIT 1");
            $resA = $dbc->execute($prepA,$argsA);
            if ($dbc->numRows($resA) == 0) {
                $args = array(
                    $upc,
                    $brand,
                    $desc,
                    $price,
                    $margin,
                    $deptMarg,
                    $dept,
                    $vendor,
                    $rSrp,
                    $srp,
                    $pid,
                    $cost,
                    $storeID,
                    $username,
                    $sku
                );
                $prep = $dbc->prepare("
                    INSERT INTO woodshed_no_replicate.AuditScanner
                    (
                        upc, brand, description, price, curMarg, desMarg, dept,
                            vendor, rsrp, srp, prid, cost, store_id,
                            username, sku
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                    );
                ");
                $dbc->execute($prep, $args);
            }
        }

        return header('location: NewAuditReport.php');
    }

    public function postFetchHandler()
    {
        $dbc = ScanLib::getConObj();

        $username = ($un = scanLib::getUser()) ? $un : "Generic User";
        $storeID = scanLib::getStoreID();
        $args = array($username,$storeID);
        $query = $dbc->prepare("
            SELECT upc, sku, brand, description, cost, price, 
                CONCAT(curMarg, ' / ', desMarg, ' (', ROUND(100 * (curMarg - desMarg)), ')') AS margin_target_diff,
                rsrp, srp, prid, dept, vendor, notes 
            FROM woodshed_no_replicate.AuditScanner
            WHERE username = ?
                AND store_id = ?
                AND upc != '0000000000000'
            ORDER BY id;
        ");
        $td = "";
        $textarea = "<div style=\"position: relative\">
            <span class=\"status-popup\">Copied!</span>
            <textarea class=\"copy-text\" rows=3>";
        $th = "
            <th>upc</th>
            <th>sku</th>
            <th>brand</th>
            <th>description</th>
            <th>cost</th>
            <th>price</th>
            <th>margin / target / off</th>
            <th>srp</th>
            <th>round srp</th>
            <th>prid</th>
            <th>dept</th>
            <th>vendor</th>
            <th>notes</th>
            <th></th>
        ";
        $result = $dbc->execute($query, $args);
        while ($row = $dbc->fetch_row($result)) {
            $upc = $row['upc'];
            $uLink = '<a class="upc" href="../../../../git/fannie/item/ItemEditorPage.php?searchupc='.$upc.
                '&ntype=UPC&searchBtn=" target="_blank">'.$upc.'</a>';
            $sku = $row['sku'];
            $brand = $row['brand'];
            $description = $row['description'];
            $cost = $row['cost'];
            $price = $row['price'];
            $margin_target_diff = $row['margin_target_diff'];
            $rsrp = $row['rsrp'];
            $srp = $row['srp'];
            $prid = $row['prid'];
            $dept = $row['dept'];
            $vendor = $row['vendor'];
            $notes = $row['notes'];
            $td .= "<tr class=\"prod-row\">";
            $td .= "<td>$uLink</td>";
            $td .= "<td class=\"sku\">$sku</td>";
            $td .= "<td class=\"brand\">$brand</td>";
            $td .= "<td class=\"description\">$description</td>";
            $td .= "<td class=\"cost\">$cost</td>";
            $td .= "<td class=\"price\">$price</td>";
            $td .= "<td class=\"margin_target_diff\">$margin_target_diff</td>";
            $td .= "<td class=\"rsrp\">$rsrp</td>";
            $td .= "<td class=\"srp\">$srp</td>";
            $td .= "<td class=\"prid\">$prid</td>";
            $td .= "<td class=\"dept\">$dept</td>";
            $td .= "<td class=\"vendor\">$vendor</td>";
            $td .= "<td class=\"notes\">$notes</td>";
            $td .= "<td><span class=\"scanicon scanicon-trash scanicon-sm \"></span></td></td>";
            $td .= "</tr>";
            $textarea .= "$upc\r\n";
        }
        $textarea .= "</textarea></div>";

        $ret = <<<HTML
<table class="table table-bordered table-sm small items" id="mytable">
    <thead>$th</thead>
    <tbody>
        $td
        <tr><td>$textarea</td></tr>
    </tbody>
</table>
HTML;

        if (FormLib::get('fetch') == 'true') {
            echo $ret;
            return false;
        } else {
            return $ret;
        }

    }

    private function getNotesOpts($dbc,$storeID,$username)
    {
        $args = array($storeID,$username);
        $query = $dbc->prepare("SELECT notes FROM woodshed_no_replicate.AuditScanner WHERE store_id = ? AND username = ? GROUP BY notes;");
        $result = $dbc->execute($query,$args);
        $options = array();
        while ($row = $dbc->fetch_row($result)) {
            if ($row['notes'] != '') {
                $options[] = $row['notes'];
            }
        }
        echo $dbc->error();
        return $options;
    }

    public function pageContent()
    {
        $dbc = scanLib::getConObj();
        $username = ($un = scanLib::getUser()) ? $un : "Generic User";
        $storeID = scanLib::getStoreID();

        $options = $this->getNotesOpts($dbc,$storeID,$username);
        $noteStr = "";
        $noteStr .= "<select id=\"notes\" style=\"font-size: 10px; font-weight: normal; margin-left: 5px; border: 1px solid lightgrey\">";
        $noteStr .= "<option value=\"viewall\">View All</option>";
        foreach ($options as $k => $option) {
            $noteStr .= "<option value=\"".$k."\">".$option."</option>";
        }
        $noteStr .= "</select>";
        $nFilter = "<div style=\"font-size: 12px; padding: 10px;\"><b>Note Filter</b>:$noteStr</div>";

        $modal = "
            <div id=\"upcs_modal\" class=\"modal\">
                <div class=\"modal-dialog\" role=\"document\">
                    <div class=\"modal-content\">
                      <div class=\"modal-header\">
                        <h3 class=\"modal-title\" style=\"color: #8c7b70\">Upload a list of UPCs to scan</h3>
                        <button type=\"button\" class=\"close\" data-dismiss=\"modal\" aria-label=\"Close\"
                            style=\"position: absolute; top:20; right: 20\">
                          <span aria-hidden=\"true\">&times;</span>
                        </button>
                      </div>
                      <div class=\"modal-body\">
                        <div align=\"center\">
                            <form method=\"post\" class=\"form-inline\">
                                <div class=\"form-group\">
                                    <textarea class=\"form-control\" name=\"upcs\" rows=\"10\" cols=\"50\"></textarea>
                                </div>
                                <div class=\"form-group\">
                                    <button type=\"submit\" class=\"btn btn-default btn-xs\">Submit</button>
                                </div>
                                <input type=\"hidden\" name=\"storeID\" value=\"$storeID\" />
                                <input type=\"hidden\" name=\"username\" value=\"$username\" />
                            </form>
                        </div>
                      </div>
                    </div>
                </div>
            </div>
        ";

        //$this->addScript('auditScannerReport.js');

        return <<<HTML
$modal
<form id="page-info" style="display: none">
    <input type="hidden" id="storeID" value="$storeID" />
    <input type="hidden" id="username" value="$username" />
</form>

<div class="form-group dummy-form">
    <button id="clearNotesInputB" class="btn btn-secondary btn-sm page-control">Clear Notes</button>
</div>
<div class="form-group dummy-form">
    <button id="clearAllInputB" class="btn btn-secondary btn-sm page-control">Clear Queue</button>
</div>
<div class="form-group dummy-form">
    <button class="btn btn-secondary btn-sm page-control" data-toggle="modal" data-target="#upcs_modal">Upload a List</button>
</div>
<div class="form-group">
    <a class="btn btn-info btn-sm page-control" href="AuditScanner.php ">Scanner</a>
</div>
$nFilter
<div id="mytable-container">
    {$this->postFetchHandler()}
</div>
HTML;
    }

    public function formContent()
    {
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
var storeID = $('#storeID').val();
var username = $('#username').val();
$.ajax({
    type: 'post',
    data: 'test=true',
    dataType: 'json',
    url: 'NewAuditReport.php',
    success: function(response)
    {
        console.log(response.test);
    },
});
var stripeTable = function(){
    $('tr.prod-row').each(function(i = 0){
        if ($(this).is(':visible')) {
            if (i % 2 == 0) {
                $(this).addClass('stripe');
            }
        }
        i++;
    });
};
stripeTable();
$('#clearNotesInputB').click(function() {
    var c = confirm("Are you sure?");
    if (c == true) {
        $.ajax({
            type: 'post', 
            data: 'storeID='+storeID+'&username='+username+'&notes=true',
            dataType: 'json',
            url: 'NewAuditReport.php',
            success: function(response) {
                fetchTable();
            },
            error: function(response) {
            },
        });
    }
});
$('#clearAllInputB').click(function() {
    var c = confirm("Are you sure?");
    if (c == true) {
        $.ajax({
            type: 'post', 
            data: 'storeID='+storeID+'&username='+username+'&clear=true',
            dataType: 'json',
            url: 'NewAuditReport.php',
            success: function(response) {
                fetchTable();
            },
            error: function(response) {
                alert('error');
            },
        });
    };
});

var fetchTable = function() {
    $.ajax({
        type: 'post',
        data: 'fetch=true',
        url: 'NewAuditReport.php',
        success: function(response)
        {
            $('#mytable').remove();
            $('#mytable-container').html(response);
            $('#mytable').remove();
            stripeTable();
        },
    });
};

$("#notes").change( function() {
    var noteKey = $("#notes").val();
    var note = $("#notes").find(":selected").text();
    $("#mytable").each(function() {
        $(this).find("tr").each(function() {
            $(this).show();
        });
    });
    $("#mytable").each(function() {
        $(this).find("tr").each(function() {
            if (!$(this).parent('thead').is('thead')) {
                var notecell = $(this).find(".notes").text();
                if (note != notecell) {
                    $(this).closest("tr").hide();
                }
                if (noteKey == "viewall") {
                    $(this).show();
                }
                $(".blankrow").show();
            }
        });
    });
    stripeTable();
});

$('.copy-text').focus(function(){
    $(this).select();
    var status = document.execCommand('copy');
    if (status == true) {
        $(this).parent().find('.status-popup').show()
            .delay(800).fadeOut(400);
    }
});

//$('.scanicon-trash').click( function(event) {
//    var upc = $(this).closest('td').attr('id');
//    var store_id = $(this).closest('tr').find('.store_id').text();
//    var username = $(this).closest('tr').find('.username').text();
//    var rowclicked = $(this).closest('tr').attr('id')   ;
//    var r = confirm('Remove '+upc+' from Queue?');
//    if (r == true) {
//        $.ajax({        
//            url: 'AuditScannerReportAjax.php',
//            type: 'post',
//            data: 'store_id='+store_id+'&upc='+upc+'&username='+username+'&deleteRow=true',
//            success: function(response)
//            {
//                //alert(store_id+', '+upc+', '+username);
//                if($('#'+rowclicked).length == 0) {
//                    $('#firstTr').hide();
//                } else {
//                    $('#'+rowclicked).hide();
//                }
//                $('#resp').html(response);
//            }
//        });
//    }
//    //event.stopPropagation();
//});
JAVASCRIPT;
    }

    public function cssContent()
    {
        return <<<HTML
.stripe {
    background: #FFFFCC;
}
thead {
    background-color: lightgrey;
    //background: linear-gradient(lightgrey, #CACACA);
    //text-shadow: 1px 1px white;
}
.dummy-form {
    display: inline-block;
    padding: 5px;
}
.page-control {
    width: 140px;
}
.status-popup {
    display: none;
    position: absolute;
    top: -26px;
    right: 0px;
    background: white;
    padding: 5px;
}
HTML;
    }

//    public function helpContent()
//    {
//        return <<<HTML
//HTML;
//    }

}
WebDispatch::conditionalExec();
