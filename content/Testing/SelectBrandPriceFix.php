<?php
include(__DIR__.'/../../config.php');
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
if (!class_exists('FpdfLib')) {
    include_once(__DIR__.'/FpdfLib.php');
}
/*
**  @class SelectBrandPriceFix 
*   Attempts to identify SELECT (vendor)
*   lines that are split between SELECT
*   and UNFI. 
*/
class SelectBrandPriceFix extends PageLayoutA
{

    protected $must_authenticate = false;
    //protected $ui = false;

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();

        return parent::preprocess();
    }

    private function getItemsInBatch()
    {
        $upcs = array();
        $dbc = scanLib::getConObj();

        $prep = $dbc->prepare("SELECT upc FROM batchList WHERE batchID IN (25532, 25531)"); 
        $res =  $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            $upcs[] = $row['upc'];
        }

        return $upcs;
    }

    public function pageContent()
    {
        $data = array();
        $td = '';
        $dbc = scanLib::getConObj();
        $batched = $this->getItemsInBatch();

        $prep = $dbc->prepare("
            SELECT
                p.upc, p.brand, p.size, p.normal_price,
                substring(p.upc, 4,5) AS familyCode,
                p.default_vendor_id,
                p.description,
                CASE
                    WHEN sm.margin IS NOT NULL THEN sm.margin
                    ELSE dm.margin
                END AS margin,
                p.cost
            FROM products p
                INNER JOIN departments d ON d.dept_no=p.department
                LEFT JOIN VendorSpecificMargins sm ON sm.deptID=p.department AND sm.vendorID=p.default_vendor_id
                INNER JOIN deptMargin dm ON dm.dept_ID=p.department
WHERE SUBSTRING(upc, 4, 5) IN (00680,01619,02190,03813,06961,08078,09280,10486,13964,13986,15418,15486,15632,16237,16867,17279,17885,18334,18787,18858,28367,29835,30743,30985,33674,35269,35720,35824,40187,40749,41507,46985,50012,50021,51381,51669,51856,52865,54323,54973,54986,56045,57035,57334,60630,60860,61176,62692,64302,65231,67383,75534,75707,76970,77014,81738,82126,83000,85178,86648,87437,87647,89191,89287,90223,90985,91639,93268,94841)
                AND p.last_sold >= NOW() - INTERVAL 90 DAY
                and p.normal_price <> 0
            GROUP BY p.upc
            ORDER BY p.upc
            ");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            $upc = $row['upc'];
            $brand = $row['brand'];
            $description = $row['description'];
            $size = $row['size'];
            $family = $row['familyCode'];
            $price = $row['normal_price'];
            $cost = $row['cost'];
            $id = $row['default_vendor_id'];
            $margin = $row['margin'];
            $curMargin = 100 * (($price - $cost) / $price);
            $curMargin = round($curMargin,2);
            $myCode = $family.'-'.$size;

            $data[$myCode][$upc]['price'] = $price;
            $data[$myCode][$upc]['vendorID'] = ($id == 1) ? 'UNFI' : 'SELECT';
            $data[$myCode][$upc]['brand'] = $brand;
            $data[$myCode][$upc]['description'] = $description;
            $data[$myCode][$upc]['margin'] = $margin;
            $data[$myCode][$upc]['curMargin'] = $curMargin;
            $data[$myCode][$upc]['cost'] = $cost;
            $data[$myCode][$upc]['batched'] = (in_array($upc, $batched)) ? 'alert alert-danger' : '';

        }

        $thead = sprintf("<tr><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th></th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th></th></tr>",
            'Code', 'UPC', 'Price', 'Cost', 'VendorID', 'Current', 'Target', 'Brand', 'Description'
        );
        foreach ($data as $code => $array) {
            foreach ($array as $upc => $row) {
                if (count($array) > 1) {
                    $td .= sprintf("<tr><td>%s</td><td class=\"%s\">%s</td><td>%s</td><td>%s</td><td><input type=\"text\" class=\"\" style=\"width: 100px; border: 1px solid lightgrey;\"  name=\"price\" /></td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><input type=\"checkbox\"></td></tr>",
                        $code, $row['batched'], $upc, $row['price'], $row['cost'], $row['vendorID'], $row['curMargin'], $row['margin'], $row['brand'], $row['description']
                    );
                }
            }
        }

        return <<<HTML
<div class="container-fluid" style="padding-top: 25px;">
<button class="btn btn-default" id="btn-hide">Show JSON</button>
<div class="row">
    <div class="col-lg-3">
        <div style="display: none;" id="hidden-text">
{
    "startDate":"0000-00-00 00:00:00",
    "endDate":"0000-00-00 00:00:00",
    "batchName":"SELECT LINE UP PC 11.23",
    "batchType":"4",
    "discountType":"0",
    "priority":"0",
    "owner":"DELI",
    "transLimit":"0",
    "items":[
        </div>
    </div>
    <div class="col-lg-9">
        <table class="table table-bordered table-condensed small"><thead>$thead</thead><tbody>$td</tbody></table>
    </div>
</div>
HTML;
    }

    public function cssContent()
    {
        return <<<HTML
HTML;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT

var lastCode = ''
var vendors = [];
var skipCodes = [];
$(function() {
    $('tr').each(function(){
        let code = $(this).find('td:eq(0)').text();
        let vendor = $(this).find('td:eq(5)').text();
        if (code != lastCode && lastCode.length > 0) {
            if (vendors.length == 1) {
                skipCodes.push(code);
            }
            vendors = []
        } else {
            if (!vendors.includes(code)) {
                vendors.push(code);
            }
        }
        lastCode = code;
    }); 
});

console.log(skipCodes);
$.each(skipCodes, function(k, v) {
    $('tr').each(function(){
        let code = $(this).find('td:eq(0)').text();
        if (code == v) {
            $(this).hide();
        }
    });
});

function stripeByColumn()
{
    var prev_dept = '';
    var color = 'white';
    $('tr').each(function(){
        var dept = $(this).find('td:first-child').text();
        if (dept != prev_dept) {
            if (color == 'white') {
                color = '#faf8eb';
            } else {
                color = 'white';
            }
        }
        $(this).css('background', color);
        prev_dept = dept;
    });

}
stripeByColumn();

$('input[type="checkbox"]').on('click', function(){
    let checked = $(this).is(":checked");
    let upc = $(this).closest('tr').find('td:eq(1)').text();
    let newPrice = $(this).closest('tr').find('td:eq(4)').find('input').val();
    let json = ' { "upc":"'+upc+'", "salePrice":"'+newPrice+'", "groupSalePrice":"'+newPrice+'", "active":"0", "pricemethod":"0", "quantity":"0", "signMultiplier":"1" }, ';

    if (checked) {
        //console.log(upc+', '+newPrice);
        console.log(json);
        $('#hidden-text').append(json);
    }
});

$('#btn-hide').click(function(){
    let ishidden = $('#hidden-text').is(':visible');
    console.log('hi');
    if (ishidden == true) {
        $('#hidden-text').css('display', 'none');
    } else {
        $('#hidden-text').css('display', 'inline');
    }
});

$('tr').each(function() {
    $(this).find('td:eq(0)').css('user-select', 'none');
});

$('tr').click(function(e) {
    let shiftDown = e.shiftKey;
    if (shiftDown) {
        if ($(this).css('background') == 'rgb(221, 160, 221) none repeat scroll 0% 0% / auto padding-box border-box') {
            $(this).css('background', '');
        } else {
            $(this).css('background', 'plum');
        }
    }
    console.log($(this).css('background'));
});

// Prevent Reload
window.onbeforeunload = function() {
    return ''
}

JAVASCRIPT;
    }

}
WebDispatch::conditionalExec();
