<?php
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../../../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../../../common/sqlconnect/SQLManager.php');
}
class UnfiBreakdowns extends WebDispatch 
{

    protected $title = 'UNFI Breakdowns Page';
    protected $description = '[UNFI Breakdowns] Find product relatives 
        missing from sales batches.';
    
    public function body_content()
    {        
        $FANNIE_ROOTDIR = $this->config->vars['FANNIE_ROOTDIR'];
        $start = FormLib::get('start');
        $end = FormLib::get('end');
        if ($end == '') $end = $start;
        $ret .= "<p> <a href='CoopDealsReview.php'>
            Coop Deals Review Page (QA)</a> | Breakdown Items </p> ";
        $ret .=  self::form_content();
        $dbc = ScanLib::getConObj();
        $upcs = array();
        $skus = array();
        $upcs = array();
        $args = array($start,$end);
        $prep = $dbc->prepare("
            SELECT 
                bl.upc, 
                bl.salePrice,
                bl.batchID,
                p.description,
                p.brand,
                v.sku
            FROM batchList AS bl
                LEFT JOIN batches AS b ON bl.batchID=b.batchID
                LEFT JOIN products AS p ON bl.upc=p.upc
                LEFT JOIN vendorItems AS v ON p.default_vendor_id=v.vendorID AND p.upc=v.upc
                LEFT JOIN MasterSuperDepts AS m ON p.department=m.dept_ID
            WHERE b.batchID >= ?
                AND b.batchID <= ?
                AND m.superID <> 1
            GROUP BY bl.upc
            ORDER BY bl.batchID
        ");
        $res = $dbc->execute($prep, $args);
        $batchList = array();
        $batchListUpcs = array();
        $batchIDs = array();
        while ($row = $dbc->fetchRow($res)) {
           //echo "<div>{$row['upc']}</div>";
           $upcs[$row['upc']]['saleprice'] = $row['salePrice'];
           $upcs[$row['upc']]['batchID'] = $row['batchID'];
           $upcs[$row['upc']]['description'] = $row['description'];
           $upcs[$row['upc']]['brand'] = $row['brand'];
           $upcs[$row['upc']]['sku'] = [$row['sku']];
           $upcs[$row['upc']]['upc'] = $row['upc'];
           $skus[$row['upc']] = $row['sku'];
           $upcs[] = $row['upc'];
        }

        $key = '
            <div class="row"><div class="col-lg-4">
            <table class="table table-bordered table-condensed table-sm small"><thead></thead>
            <tbody>
                <tr><td class="alert-success">&nbsp;</td><td>Item found in batch</td></tr>
                <tr><td class="alert-danger">&nbsp;</td><td>Item missing from batch</td></tr>
            </tbody></table></div>
            <div class="col-lg-4">
            </div> 
            </div>';
        $table =  '<table class="table table-condensed table-bordered table-sm small" id="break_table">
            <thead><tr>
                <th></th>
                <th>SKU</th>
                <th>UPC</th>
                <th>batchID</th>
                <th>Brand</th>
                <th>Description</th>
                <th>SSP</th>
            </tr></thead><tbody>';
        $id = 0;
        foreach ($skus as $upc => $sku) {
        //while (next($upcs)) {
            //$upc_data = current($upcs);
            //$upc = $upc_data['upc'];
            //$sku = $upc_data['sku'];
            $args = array($sku);
            $prep = $dbc->prepare("SELECT sku, upc, isPrimary, multiplier
                FROM VendorAliases WHERE sku = ?");
            $res = $dbc->execute($prep, $args);
            $num_rows = 0;
            $num_rows = $dbc->numRows($res);
            if ($num_rows == 0) {
                $args = array($upc);
                $prep = $dbc->prepare("SELECT sku FROM VendorAliases WHERE upc = ?");
                $res = $dbc->execute($prep, $args);
                $row = $dbc->fetchRow($res);
                $sku = $row['sku'];
                $args = array($sku);
                $prep = $dbc->prepare("SELECT sku, upc, isPrimary, multiplier
                    FROM VendorAliases WHERE sku = ?");
                $res = $dbc->execute($prep, $args);
            }
            while ($row = $dbc->fetchRow($res)) {
                //echo $row['sku'] . ', ' . $row['upc'] . "<br/>";
                $id++;
                $is_primary = $row['isPrimary'];
                $multiplier = $row['multiplier'];
                $upc = $row['upc'];
                $saleprice = $upcs[$upc]['saleprice'];
                $batchID = $upcs[$upc]['batchID'];
                $bid_link = "<a href=\"http://$FANNIE_ROOTDIR/batches/newbatch/EditBatchPage.php?id=$batchID\" target=\"_blank\">$batchID</a>";
                $class = (array_key_exists($upc, $upcs)) ? 'alert-success' : 'alert-danger';
                $table .= sprintf("<tr id=\"%s\" class=\"%s\" data-sku=\"%s\" data-type=\"%s\" data-multiplier=\"%s\" data-saleprice=\"%s\" data-batchID=\"%s\"><td style='width: 5px;'></td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td class=\"ssp\">%s</td>",
                    'id'.$id,
                    $class,
                    $sku,
                    $is_primary,
                    $multiplier,
                    $saleprice,
                    $upcs[$upc]['batchID'],
                    $sku,
                    $upc,
                    $bid_link,
                    $upcs[$upc]['brand'],
                    $upcs[$upc]['description'],
                    $upcs[$upc]['saleprice']
                );
            }
        }
        $table .= "</tbody></table>";
        
        return <<<HTML
<div class="container-fluid"> $ret $key $table </div>
HTML;
    }
    
    private function form_content()
    {
		
        $id1 = FormLib::get('start');
        $id2 = FormLib::get('end');
        if ($id2 == '') $id2 = $id1;
		
        return '
            <div id="test"></div>
            <form method="get"> 
                <input type="text" value="'.$id1.'" name="start" placeholder="start batchID" autofocus require>
                <input type="text" value="'.$id2.'" name="end" placeholder="end batchID (opt).">
                <button type="submit" class="">Submit</button>
            </form>
        ';
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
var superArray = function(the_array, name) {
    this.name = name;
    this.the_array = the_array;
    this.max_length = this.the_array.length - 1;
    this.next = function() {
        if (this.current >= this.max_length) {
            return this[this.current = 0];
        }
        return this[++this.current];
    };
    this.prev = function() {
        return this[--this.current];
    };
    this.current = 0;
    this.setName = function(name) {
        this.name = name;
    }
    this.getVal = function () {
        return this.the_array[this.current];
    };
};
var colors = ['#E0BBE4', '#957DAD', '#D291BC', '#FEC8D8',  '#FFDFD3'];
var colors_array = new superArray(colors, 'a_new_name');
var i = 0;
var ssp = null;
var last_sku = null;
$('tr').each(function(){
    var tableID = $(this).closest('table').attr('id');
    if (tableID == 'break_table') {
        var tr_id = $(this).attr('id');
        var sku = $(this).attr('data-sku');
        var type = $(this).attr('data-type');
        var batchID = $(this).attr('data-batchID');
        var ssp = null;
        if (sku == last_sku) {
            $(this).find('td:first').css('background', colors_array.getVal());
            colors_array.next();
        } else {
            $(this).find('td:first').css('background', colors_array.getVal());
        }
        if (batchID == '') {
            var oppo_type = (type == 1) ? 0 : 1;
            oppo_type = parseInt(oppo_type, 10);
            var saleprice = $("[data-type='"+oppo_type+"'][data-sku='"+sku+"']").attr('data-saleprice');
            var cur_bid = $("[data-type='"+oppo_type+"'][data-sku='"+sku+"']").attr('data-batchID');
            saleprice = parseFloat(saleprice);
            var multiplier = $(this).attr('data-multiplier');
            if (type == '1') {
                // use opposite multiplier if primary
                var oppo_multiplier = $("[data-type='"+oppo_type+"'][data-sku='"+sku+"']").attr('data-multiplier');
                var multiplier = 1 / parseFloat(oppo_multiplier);
            } 
            multiplier = parseFloat(multiplier);
            ssp = saleprice * multiplier;

            $.ajax({
                type: 'post',
                data: 'price='+ssp+'&round=true',
                url: '../../../../common/lib/priceRoundAjax.php',
                success: function(resp, ssp)
                {
                    ssp = (resp == -0.01) ? 'check' : resp;
                    $('#'+tr_id).find('td:last').text(ssp);
                }
            });
            $(this).find('td:eq(3)').text(cur_bid);
        }
        last_sku = sku;
    }
});
var removeDuplicateRows = function(table_id)
{
    var upcs = [];
    $('tr').each(function(){
        var cur_id = $(this).closest('table').attr('id');
        if (table_id == cur_id) {
           var upc = $(this).find('td:eq(2)').text();
            if (upcs.includes(upc)) {
                $(this).hide();
            } else {
                upcs.push(upc);
            }
        }
    });
}
removeDuplicateRows('break_table');
JAVASCRIPT;
    }

    public function helpContent()
    {
        return "
            <h5>Find Coop Deals Breakdown Items</h5>
            <p>Enter a single batch ID or range of ID's to find all breakdown items within.</p>
            <li>Green rows are items that were found in the batch and should not need to be adjusted.</li>
            <li>Red rows are relatives of items found in batches that need to be added to the batch</li>
            <li>The color coding on the far left side of the table is a visual aid to help in identifying 
                items that have an association.</li>
        ";
    }
    
}
WebDispatch::conditionalExec();
