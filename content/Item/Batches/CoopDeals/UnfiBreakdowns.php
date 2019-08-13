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
        $FANNIE_ROOTDIR = $this->config['FANNIE_ROOTDIR'];
        $start = FormLib::get('start');
        $end = FormLib::get('end');
        if ($end == '') $end = $start;
        $ret .= "<p> <a href='CoopDealsReview.php'>
            Coop Deals Review Page (QA)</a> | Breakdown Items </p> ";
        $ret .=  self::form_content();
        $dbc = ScanLib::getConObj();
        $upcs = array();
        $skus = array();
        $args = array($start,$end);
        $prep = $dbc->prepare("
            SELECT 
                bl.upc, 
                bl.salePrice,
                bl.batchID,
                p.description,
                p.brand,
                v.sku,
                p.inUse
            FROM batchList AS bl
                LEFT JOIN batches AS b ON bl.batchID=b.batchID
                LEFT JOIN products AS p ON bl.upc=p.upc
                LEFT JOIN vendorItems AS v ON p.default_vendor_id=v.vendorID AND p.upc=v.upc
            WHERE b.batchID >= ?
                AND b.batchID <= ?
        ");
        $res = $dbc->execute($prep, $args);
        $batchList = array();
        $batchListUpcs = array();
        $batchIDs = array();
        while ($row = $dbc->fetchRow($res)) {
           $upcs[$row['upc']]['saleprice'] = $row['salePrice'];
           $upcs[$row['upc']]['batchID'] = $row['batchID'];
           $upcs[$row['upc']]['description'] = $row['description'];
           $upcs[$row['upc']]['brand'] = $row['brand'];
           $skus[$row['sku']] = null;
        }

        $key = '
            <div class="row"><div class="col-lg-4">
            <table class="table table-bordered table-condensed table-sm small"><thead></thead>
            <tbody>
                <tr><td class="alert-success">&nbsp;</td><td>Item found in batch</td></tr>
                <tr><td class="alert-danger">&nbsp;</td><td>Item missing from batch</td></tr>
            </tbody></table></div></div>';
        $table =  '<table class="table table-condensed table-bordered table-sm small" id="break_table">
            <thead><tr>
                <th>SKU</th>
                <th>UPC</th>
                <th>batchID</th>
                <th>Brand</th>
                <th>Description</th>
                <th>SSP</th>
            </tr></thead><tbody>';
        $id = 0;
        foreach ($skus as $sku => $na) {
            $args = array($sku);
            $prep = $dbc->prepare("SELECT sku, upc, isPrimary, multiplier
                FROM VendorAliases WHERE sku = ?");
            $res = $dbc->execute($prep, $args);
            while ($row = $dbc->fetchRow($res)) {
                $id++;
                $is_primary = $row['isPrimary'];
                $multiplier = $row['multiplier'];
                $upc = $row['upc'];
                $saleprice = $upcs[$upc]['saleprice'];
                $batchID = $upcs[$upc]['batchID'];
                $bid_link = "<a href=\"http://$FANNIE_ROOTDIR/batches/newbatch/EditBatchPage.php?id=$batchID\" target=\"_blank\">$batchID</a>";
                $class = (array_key_exists($upc, $upcs)) ? 'alert-success' : 'alert-danger';
                $table .= sprintf("<tr id=\"%s\" class=\"%s\" data-sku=\"%s\" data-type=\"%s\" data-multiplier=\"%s\" data-saleprice=\"%s\" data-batchID=\"%s\"><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td class=\"ssp\">%s</td>",
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
var i = 0;
var ssp = null;
$('tr').each(function(){
    var tableID = $(this).closest('table').attr('id');
    if (tableID == 'break_table') {
        var tr_id = $(this).attr('id');
        var sku = $(this).attr('data-sku');
        var type = $(this).attr('data-type');
        var batchID = $(this).attr('data-batchID');
        var ssp = null;
        if (batchID == '') {
            var oppo_type = (type == 1) ? 0 : 1;
            oppo_type = parseInt(oppo_type, 10);
            var saleprice = $("[data-type='"+oppo_type+"'][data-sku='"+sku+"']").attr('data-saleprice');
            var cur_bid = $("[data-type='"+oppo_type+"'][data-sku='"+sku+"']").attr('data-batchID');
            var multiplier = $(this).attr('data-multiplier');
            saleprice = parseFloat(saleprice);
            multiplier = parseFloat(multiplier);
            ssp = saleprice * multiplier;

            $.ajax({
                type: 'post',
                data: 'price='+ssp+'&round=true',
                url: '../../../../common/lib/priceRoundAjax.php',
                success: function(response, ssp)
                {
                    ssp = response;
                    $('#'+tr_id).find('td:last').text(ssp);
                }
            });
            $(this).find('td:eq(2)').text(cur_bid);

        }
    }
});
JAVASCRIPT;
    }

    public function helpContent()
    {
        return "
            <h5>Find Coop Deals Breakdown Items</h5>
            <p>Enter a single batch ID or range of ID's to find all breakdown items within.</p>
            <li>Green rows are items that were found in the batch and should not need to be adjusted.</li>
            <li>Red rows are relatives of items found in batches that need to be added to the batch</li>
        ";
    }
    
}
WebDispatch::conditionalExec();
