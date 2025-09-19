<?php
include(__DIR__.'/../../config.php');
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
/*
**  @class DefaultVendorChangesReport 
*/
class DefaultVendorChangesReport extends PageLayoutA
{

    protected $must_authenticate = false;

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();
        $this->__routes[] = 'get<startDate>';
        $this->__routes[] = 'get<upc>';
        $this->__routes[] = 'post<id>';

        return parent::preprocess();
    }

    public function PostIdHandler()
    {
        $dbc = scanLib::getConObj();
        $id = FormLib::get("id");
        $text = FormLib::get("text");

        $args = array($text, $id);
        $prep = $dbc->prepare("UPDATE DefaultVendorHistory
            SET details = ? WHERE id = ?");
        $res = $dbc->execute($prep, $args);

        echo "Wooooooo";
        return false;
    }

    public function GetUpcView()
    {
        return $this->pageContent();
    }


    public function GetStartDateView()
    {
        return $this->pageContent();
    }

    public function pageContent()
    {
        $dbc = scanLib::getConObj();
        $td = "";
        $thead = "<th>UPC</th><th>Brand</th><th>Description</th><th>From Vendor</th>
            <th>To Vendor</th><th>Updated On</th><th>Details</th>";
        $args = array();

        $searchupc = FormLib::get("upc", false);
        $startDate = FormLib::get("startDate", false);
        $endDate = FormLib::get("endDate", false);

        $whereA = "";
        if ($searchupc != false) {
            $searchupc = scanLib::padUPC($searchupc);
            $whereA =  " AND upc = ?  ";
            $args[] = $searchupc;
        }
        $whereB = "";
        if ($startDate != false) {
            $whereB =  " AND updated >= ?  ";
            $args[] = $startDate;
        }
        $whereC = "";
        if ($endDate != false) {
            $whereC = " AND updated <= ?  ";
            $args[] = $endDate;
        }
        $whereD = "";
        if ($whereA == false && $whereC == false && $whereD == false) {
            $whereD = " AND updated > (DATE(NOW()) - INTERVAL 30 DAY) ";
        }

        $query = "
                SELECT 
                *,
                va.vendorName AS oldName,
                vb.vendorName AS newName,
                h.id AS id
            FROM DefaultVendorHistory h
                LEFT JOIN vendors va ON va.vendorID=h.oldID
                LEFT JOIN vendors vb ON vb.vendorID=h.newID
                LEFT JOIN products p ON p.upc=h.upc
            WHERE 1=1
                $whereA
                $whereB
                $whereC
                $whereD
            GROUP BY h.upc, updated
            ORDER BY updated DESC
        ";

        $prep = $dbc->prepare($query);
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $id = $row['id'];
            $upc = $row['upc'];
            $oldID = $row['oldID'];
            $oldName = $row['oldName'];
            $newName = $row['newName'];
            $newID = $row['newID'];
            $updated = substr($row['updated'],0,-3);
            $details = $row['details'];
            $brand = $row['brand'];
            $description = $row['description'];

            $td .= "<tr>";
            $td .= "<td>$upc</td><td>$brand</td><td>$description</td>
                <td>$oldID $oldName</td><td>$newID $newName</td>
                <td>$updated</td><td class=\"edit-details\" data-tid=\"$id\">$details</td>";
            $td .= "</tr>";
        }

        $this->addOnloadCommand("$('#startDate').datepicker({dateFormat: 'yy-mm-dd'});");
        $this->addOnloadCommand("$('#endDate').datepicker({dateFormat: 'yy-mm-dd'});");

        return <<<HTML
<div style="padding: 25px">
    <div class="row">
        <div class="col-lg-12" id="col-2">
            <h3>Default Vendor Change Log</h3>
            <label>Search by Start / End Date(s)</label>
            <form class="form-inline">
                <div class="input-group" style="max-width: 500px">
                    <input type="text" class="form-control date-picker" name="startDate" id="startDate" value="$startDate" autocomplete="off" />
                    <input type="text" class="form-control date-picker" name="endDate" id="endDate" value="$endDate" autocomplete="off" />
                </div>
                &nbsp;
                &nbsp;
                <div class="input-group">
                    <button class="btn btn-default">Submit</button>
                </div>
            </form>
            <label>Search by UPC</label>
            <form class="form-inline">
                <div class="input-group" style="max-width: 500px">
                    <input type="text" class="form-control" name="upc" id="upc" value="$searchupc" autocomplete="off" />
                </div>
                <div class="input-group">
                    <button class="btn btn-default">Submit</button>
                </div>
            </form>
            <form>
                <div class="input-group">
                    <button class="btn btn-default" onclick="window.location.reload(true); ">Reset</button>
                </div>
            </form>
            <table class="table table-striped table-bordered table-sm"><thead>$thead</thead><tbody>$td</tbody></table>
        </div>
    </div>
</div>
HTML;
    }

    public function cssContent()
    {
        return <<<HTML
.list-active { 
    background: lightblue;
}
HTML;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
var typed = [ "", ""];
var lastElm = "";

$(".edit-details").on("click", function() {
    typed[0] = $(this).text();
    $(this).attr('contentEditable', 'true');
});

$(".edit-details").on("focusout", function() {
    $(this).attr('contentEditable', 'false');
    typed[1] = $(this).text();
    lastElm = $(this);

    if (typed[0] != typed[1]) {
        let id = $(this).attr('data-tid');
        $.ajax({
            type: "post",
            url: "DefaultVendorChangesReport.php",
            data: "id="+id+"&text="+typed[1],
            success: function(response){
                console.log("success");
                console.log(response);
                //window.location.reload();
                ajaxRespPopOnElm(lastElm);
            },
            error: function(response) {
                alert("[ERROR]: " + response);
                ajaxRespPopOnElm(lastElm);
            }
        });
    }
});

var ajaxRespPopOnElm = function(el=false, error=0) {
    var pos = [];

    if  (el == false) {
        let target = $(this);
    }
    let target = $(el);

    let response = (error == 0) ? 'Saved' : 'Error';
    let responseColor = (error == 0) ? '' : '';
    let inputBorder = target.css('border');
    target.css('border', '0px solid transparent');

    let offset = target.offset();
    $.each(offset, function (k,v) {
        pos[k] = parseFloat(v);
    });
    pos['top'] -= 30;
    pos['left'] -= 55;

    

    let zztmpdiv = "<div id='zztmp-div' style='position: absolute; top: "+pos['top']+"; left: "+pos['left']+"; color: black; background-color: white; padding: 5px; border-radius: 5px;border-bottom-right-radius: 0px; border: 1px solid grey;'>"+response+"</div>";
    $('body').append(zztmpdiv);

    setTimeout(function(){
        target.css('border', inputBorder);
        $('#zztmp-div').empty();
        $('#zztmp-div').remove();
    }, 1000);
}
JAVASCRIPT;
    }


}
WebDispatch::conditionalExec();
