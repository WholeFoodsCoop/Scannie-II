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
        $this->__routes[] = 'post<rowCount>';
        $this->__routes[] = 'post<setSku>';
        $this->__routes[] = 'post<setBrand>';

        return parent::preprocess();
    }

    public function postTestHandler()
    {
        $json = array('test'=>'successful');
        echo json_encode($json);

        return false;
    }

    public function postSetBrandHandler()
    {
        $upc = FormLib::get('upc');
        $brand = FormLib::get('brand');
        $json = array();

        $dbc = ScanLib::getConObj();
        $mod = new DataModel($dbc);
        $json['saved'] = $mod->setBrand($upc, $brand);
        echo json_encode($json);

        return false;
    }

    public function postSetSkuHandler()
    {
        $upc = FormLib::get('upc');
        $sku = FormLib::get('sku');
        $lastSku = FormLib::get('lastSku');
        $vendorID = FormLib::get('vendorID');
        $json = array();

        $dbc = ScanLib::getConObj();
        $mod = new DataModel($dbc);
        $json['saved'] = $mod->setSku($vendorID, $lastSku, $upc, $sku);
        echo json_encode($json);

        return false;
    }

    public function postDeleteRowHandler()
    {
        $storeID = FormLib::get('storeID');
        $username = FormLib::get('username');
        $upc = FormLib::get('upc');
        $json = array();
        $json['test'] = 'test';

        $dbc = ScanLib::getConObj();
        $args = array($upc, $storeID, $username);
        $prep = $dbc->prepare('DELETE FROM woodshed_no_replicate.AuditScan WHERE upc = ? AND storeID = ? AND username = ?');
        $dbc->execute($prep, $args);
        if ($er = $dbc->error()) {
            $json['dbc-error'] = $er;
        }
        echo json_encode($json);

        return false;
    }

    public function postClearHandler()
    {
        $dbc = ScanLib::getConObj();
        $storeID = FormLib::get('storeID');
        $username = FormLib::get('username');
        $args = array($storeID, $username);
        $query = $dbc->prepare("DELETE FROM woodshed_no_replicate.AuditScan WHERE storeID = ? AND username = ?");
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
        $query = $dbc->prepare("UPDATE woodshed_no_replicate.AuditScan SET notes = '' WHERE storeID = ? AND username = ?");
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
            $args = array($upc, $username, $storeID);
            $prep = $dbc->prepare("INSERT IGNORE INTO woodshed_no_replicate.AuditScan (upc, username, storeID, date)
                VALUES (?, ?, ?, NOW());");
            $res = $dbc->execute($prep, $args);
        }

        return header('location: NewAuditReport.php');
    }

    public function postRowCountHandler()
    {
        $dbc = ScanLib::getConObj();

        $json = array('count' => null);
        $username = ($un = scanLib::getUser()) ? $un : "Generic User";
        $storeID = scanLib::getStoreID();
        $args = array($username, $storeID);
        $query = $dbc->prepare("
            SELECT upc
            FROM woodshed_no_replicate.AuditScan
            WHERE username = ?
                AND storeID = ?
        ");
        $result = $dbc->execute($query, $args);
        $json['count'] = $dbc->numRows($result);
        echo json_encode($json);

        return false;
    }

    public function postFetchHandler()
    {
        $dbc = ScanLib::getConObj();
        $username = ($un = scanLib::getUser()) ? $un : "Generic User";
        $storeID = scanLib::getStoreID();
        $rounder = new PriceRounder();

        // first, load the upcs, then fetch the data? 
        $args = array($username, $storeID);
        $prep = $dbc->prepare("SELECT upc FROM woodshed_no_replicate.AuditScan 
            WHERE username = ? AND storeID = ?");
        $res = $dbc->execute($prep, $args);
        $upcs = array();
        while ($row = $dbc->fetchRow($res)) {
            //echo $row['upc'];
            $upcs[$row['upc']] = $row['upc'];
        }

        list($args, $inStr) = $dbc->safeInClause($upcs);
        $prep = $dbc->prepare("
            SELECT 
                p.upc,
                v.sku, 
                p.brand, 
                u.brand AS signBrand,
                p.description AS description,
                u.description AS signDescription,
                p.cost, 
                p.normal_price AS price, 
                t.description AS priceRuleType, 
                CONCAT(p.department, ' - ', d.dept_name) AS dept,
                d.dept_no, 
                d.dept_name, 
                e.vendorID, 
                CONCAT(e.vendorID, ' - ', e.vendorName) AS vendor,
                e.vendorID AS vendorID,
                a.date,
                a.username,
                100 * (p.normal_price - p.cost) / p.normal_price AS curMargin,
                100 * ROUND(CASE
                    WHEN vd.margin > 0.01 THEN vd.margin ELSE dm.margin 
                END, 4) AS margin,
                a.notes,
                CASE
                    WHEN vd.margin > 0.01 THEN p.cost / (1 - vd.margin) ELSE p.cost / (1 - dm.margin)
                END AS rsrp
            FROM products AS p
                LEFT JOIN vendorItems AS v ON p.default_vendor_id=v.vendorID AND p.upc=v.upc
                LEFT JOIN productUser AS u ON p.upc=u.upc
                LEFT JOIN PriceRules AS r ON p.price_rule_id=r.PriceRuleID
                LEFT JOIN PriceRuleTypes AS t ON r.priceRuleTypeID=t.priceRuleTypeID
                LEFT JOIN departments AS d ON p.department=d.dept_no
                LEFT JOIN vendors AS e ON p.default_vendor_id=e.vendorID
                RIGHT JOIN woodshed_no_replicate.AuditScan AS a ON p.upc=a.upc
                LEFT JOIN deptMargin AS dm ON p.department=dm.dept_ID
                LEFT JOIN vendorDepartments AS vd
                    ON vd.vendorID = p.default_vendor_id AND vd.posDeptID = p.department 
            WHERE p.upc != '0000000000000'
            GROUP BY p.upc
            ORDER BY a.date DESC
        ");

        $td = "";
        $textarea = "<div style=\"position: relative\">
            <span class=\"status-popup\">Copied!</span>
            <textarea class=\"copy-text\" rows=3 cols=10>";
        $th = "
            <th class=\"upc\">upc</th>
            <th class=\"sku\">sku</th>
            <th class=\"brand\">brand</th>
            <th class=\"sign-brand hidden\">sign-brand</th>
            <th class=\"description\">description</th>
            <th class=\"sign-description hidden\">sign-description</th>
            <th class=\"cost\">cost</th>
            <th class=\"price\">price</th>
            <th class=\"margin_target_diff\">margin / target (diff)</th>
            <th class=\"srp\">srp</th>
            <th class=\"rsrp\">round srp</th>
            <th class=\"prid\">prid</th>
            <th class=\"dept\">dept</th>
            <th class=\"vendor\">vendor</th>
            <th class=\"notes\">notes</th>
            <th class=\"\"></th>
        ";
        $result = $dbc->execute($prep, $args);
        while ($row = $dbc->fetch_row($result)) {
            $upc = $row['upc'];
            $uLink = '<a class="upc" href="../../../../git/fannie/item/ItemEditorPage.php?searchupc='.$upc.
                '&ntype=UPC&searchBtn=" target="_blank">'.$upc.'</a>';
            $sku = $row['sku'];
            $brand = $row['brand'];
            $signBrand = $row['signBrand'];
            $description = $row['description'];
            $signDescription = $row['signDescription'];
            $cost = $row['cost'];
            $price = $row['price'];
            $margin = round($row['margin'], 2);
            $curMargin = round($row['curMargin'], 2);
            $rsrp = round($row['rsrp'], 2);
            $srp = $rounder->round($rsrp);
            $prid = $row['priceRuleType'];
            $dept = $row['dept'];
            $vendor = $row['vendor'];
            $notes = $row['notes'];
            $vendorID = $row['vendorID'];
            $rowID = uniqid();
            $td .= "<tr class=\"prod-row\" id=\"$rowID\">";
            $td .= "<td class=\"upc\" data-upc=\"$upc\">$uLink</td>";
            $td .= "<td class=\"sku editable editable-sku\">$sku</td>";
            $td .= "<td class=\"brand editable editable-brand\">$brand</td>";
            $td .= "<td class=\"sign-brand hidden\">$signBrand</td>";
            $td .= "<td class=\"description\">$description</td>";
            $td .= "<td class=\"sign-description hidden\">$signDescription</td>";
            $td .= "<td class=\"cost\">$cost</td>";
            $td .= "<td class=\"price\">$price</td>";
            $diff = round($curMargin - $margin, 1);
            $td .= "<td class=\"margin_target_diff\">$curMargin / $margin ($diff)</td>";
            $td .= "<td class=\"rsrp\">$rsrp</td>";
            $td .= "<td class=\"srp\">$srp</td>";
            $td .= "<td class=\"prid\">$prid</td>";
            $td .= "<td class=\"dept\">$dept</td>";
            $td .= "<td class=\"vendor\" data-vendorID=\"$vendorID\">$vendor</td>";
            $td .= "<td class=\"notes\">$notes</td>";
            $td .= "<td><span class=\"scanicon scanicon-trash scanicon-sm \"></span></td></td>";
            $td .= "</tr>";
            $textarea .= "$upc\r\n";
        }
        $textarea .= "</textarea></div>";
        $rows = $dbc->numRows($result);

        $ret = <<<HTML
<input type="hidden" id="table-rows" value="$rows" />
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
        $query = $dbc->prepare("SELECT notes FROM woodshed_no_replicate.AuditScan WHERE storeID = ? AND username = ? GROUP BY notes;");
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
        $test = new DataModel($dbc);

        $options = $this->getNotesOpts($dbc,$storeID,$username);
        $noteStr = "";
        $noteStr .= "<select id=\"notes\" style=\"font-size: 10px; font-weight: normal; margin-left: 5px; border: 1px solid lightgrey\">";
        $noteStr .= "<option value=\"viewall\">View All</option>";
        foreach ($options as $k => $option) {
            $noteStr .= "<option value=\"".$k."\">".$option."</option>";
        }
        $noteStr .= "</select>";
        $nFilter = "<div style=\"font-size: 12px; padding: 10px;\"><b>Note Filter</b>:$noteStr</div>";

        $columns = array('upc', 'sku', 'brand', 'sign-brand', 'description', 'sign-description', 'cost', 'price',
            'margin_target_diff', 'rsrp', 'srp', 'prid', 'dept', 'vendor', 'notes');
        $columnCheckboxes = "<div style=\"font-size: 12px; padding: 10px;\"><b>Show/Hide Columns: </b>";
        foreach ($columns as $column) {
            $columnCheckboxes .= "<span class=\"column-checkbox\"><label for=\"check-$column\">$column</label> <input type=\"checkbox\" name=\"column-checkboxes\" id=\"check-$column\" value=\"$column\" class=\"column-checkbox\" checked></span>";
        }
        $columnCheckboxes .= "</div>";

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
        $this->addScript('../../../common/javascript/tablesorter/js/jquery.tablesorter.min.js');
        $this->addScript('../../../common/javascript/tablesorter/js/jquery.metadata.js');
        $this->addOnloadCommand("$('#mytable').tablesorter();");

        return <<<HTML
$modal
<input type="hidden" name="keydown" id="keydown"/>
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
<div class="form-group dummy-form">
    <a class="btn btn-info btn-sm page-control" href="AuditScanner.php ">Scanner</a>
</div>
$nFilter
$columnCheckboxes
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
var tableRows = $('#table-rows').val();
var storeID = $('#storeID').val();
var username = $('#username').val();
var stripeTable = function(){
    $('tr.prod-row').each(function(){
        $(this).removeClass('stripe');
    });
    $('tr.prod-row').each(function(i = 0){
        if ($(this).is(':visible')) {
            if (i % 2 == 0) {
                $(this).addClass('stripe');
            } else {
                $(this).removeClass('stripe');
            }
        }
        i++;
    });

    return false;
};
$.ajax({
    type: 'post',
    data: 'test=true',
    dataType: 'json',
    url: 'NewAuditReport.php',
    success: function(response)
    {
        console.log("ajax test: "+response.test);
    },
});
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
                //fetchTable();
                location.reload();
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
                //fetchTable();
                location.reload();
            },
            error: function(response) {
                alert('error');
            },
        });
    };
});

//var fetchTable = function() {
//    $.ajax({
//        type: 'post',
//        data: 'fetch=true',
//        url: 'NewAuditReport.php',
//        success: function(response)
//        {
//            $('#mytable').remove();
//            $('#mytable-container').html(null);
//            $('#mytable').html(null);
//            //$('#mytable-container').html(response);
//            $('#mytable-container').append(response);
//            $('#mytable').remove();
//            $('#mytable').each(function(){
//                //alert('hello');
//            });
//            stripeTable();
//        },
//    });
//};


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
            .delay(400).fadeOut(400);
    }
});

$('.scanicon-trash').click( function(event) {
    var upc = $(this).parent().parent().find('.upc').attr('data-upc');
    var rowclicked = $(this).parent().parent().closest('tr').attr('id');
    var r = confirm('Remove '+upc+' from Queue?');
    if (r == true) {
        $.ajax({        
            url: 'NewAuditReport.php',
            type: 'post',
            dataType: 'json',
            data: 'storeID='+storeID+'&upc='+upc+'&username='+username+'&deleteRow=true',
            success: function(response)
            {
                console.log(response);
                //$('#'+rowclicked).hide();
                location.reload();
            },
            error: function(response)
            {
                console.log(response);
            },
        });
    }
});

var lastSku = null
$('.editable').each(function(){
    $(this).attr('contentEditable', true);
    $(this).attr('spellCheck', false);
});
$('.editable').click(function(){
    $(this).addClass('currentEdit');
});
$('.editable').focusout(function(){
    $(this).removeClass('currentEdit');
});

$('.editable-sku').click(function(){
    lastSku = $(this).text();
});
$('.editable-sku').focusout(function(){
    var sku = $(this).text();
    var vendorID = $(this).parent().find('td.vendor').attr('data-vendorID');
    var upc = $(this).parent().parent().find('.upc').attr('data-upc');
    $.ajax({
        type: 'post',
        data: 'setSku=true&lastSku='+lastSku+'&sku='+sku+'&vendorID='+vendorID+'&upc='+upc,
        dataType: 'json',
        url: 'NewAuditReport.php',
        success: function(response)
        {
            console.log(response);
            if (response.saved != true) {
                // alert user of error
            }
        },
    });

});
var lastBrand = null;
$('.editable-brand').click(function(){
    lastBrand = $(this).text();
});
$('.editable-brand').focusout(function(){
    var upc = $(this).parent().find('td.upc').attr('data-upc');
    var brand = $(this).text();
    if (brand != lastBrand) {
        $.ajax({
            type: 'post',
            data: 'setBrand=true&upc='+upc+'&brand='+brand,
            dataType: 'json',
            url: 'NewAuditReport.php',
            success: function(response)
            {
                console.log(response);
                if (response.saved != true) {
                    // alert user of error
                }
            },
        });
    }

});

$(document).keydown(function(e){
    var key = e.keyCode;
    $('#keydown').val(key);
});
$(document).keyup(function(e){
    var key = e.keyCode;
    $('#keydown').val(0);
});
$(document).mousedown(function(e){
    if (e.which == 1 && $('#keydown').val() == 16) {
        e.preventDefault();
        // SHIFT + LEFT CLICK
        //console.log(e.target);
        var target = $(e.target);
        if (target.closest('tr').hasClass('highlight')) {
            target.closest('tr').removeClass('highlight');
        } else {
            $('tr').each(function(){
                if ($(this).hasClass('highlight')) {
                    $(this).removeClass('highlight');
                };
            });
            target.closest('tr').addClass('highlight');
        }
        $('#keydown').val(0);
    }
});

$('.column-checkbox').change(function(){
    var checked = $(this).is(':checked');
    var column = $(this).val();
    if (checked == true) {
        // show column
        $('.'+column).each(function(){
            $(this).show();
        }); 
    } else {
        // hide column
        $('.'+column).each(function(){
            $(this).hide();
        }); 
    }
});

$('.column-checkbox').each(function(){
    var column = $(this).val();
    if (column == 'sign-brand') {
        $(this).prop('checked', false);
    }
    if (column == 'sign-description') {
        $(this).prop('checked', false);
    }
});

// check for new rows, replace table if new scans found
var fetchNewRows = function()
{
    $.ajax({
        type: 'post',
        data: 'rowCount=true',
        dataType: 'json',
        url: 'NewAuditReport.php',
        success: function(response)
        {
            var newCount = response.count;
            //console.log(tableRows+', '+newCount);
            if (newCount > tableRows) {
                //fetchTable();
                tableRows = newCount;
                location.reload();
                //console.log(document);
            }
        },
    });
}
setInterval('fetchNewRows()', 1000);
//fetchTable();

$('[id]').each(function(){
    var ids = $('[id="'+this.id+'"]');
    if(ids.length>1 && ids[0]==this)
        console.warn('Multiple IDs #'+this.id);
});

JAVASCRIPT;
    }

    public function cssContent()
    {
        return <<<HTML
th, .editable {
    cursor: pointer;
}
.hidden {
    display: none;
}
span.column-checkbox {
    padding: 5px; 
}
tr, td {
    position: relative;
}
tr.highlight {
    background-color: plum;
    background: linear-gradient(#FFCCE5, #FF99CC);
}
.currentEdit {
    color: purple;
    font-weight: bold;
}
.stripe {
    background: #FFFFCC;
}
thead {
    background-color: lightgrey;
    background: linear-gradient(lightgrey, #DEDEDE);
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
    top: 0px;
    right: 0px;
    background: white;
    padding: 5px;
    font-weight: bold;
    border-top-left-radius: 5px;
    border-top-right-radius: 5px;
    border-bottom-right-radius: 5px;
    border-style: solid solid solid solid;
    border-color: grey;
    border-width: 1px;
    box-shadow: 1px 1px slategrey;
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
