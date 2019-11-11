<?php
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
class DBA extends PageLayoutA
{

    protected $must_authenticate = true;

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();

        return false;
    }

    public function pageContent()
    {
        $today = new DateTime();
        $today = $today->format('Y-m-d');
        //$this->addScript('tableColumnFilters.js');
        return <<<HTML
<div class="row">
    <div class="col-lg-10">
        <div id="response"></div>
    </div>
    <div class="col-lg-2">
        <div class="form-group">
            <textarea name="query" id="query" class="form-control" spellcheck="false" autofocus></textarea>
        </div>
        <div class="form-group">
            <button id="submit" class="form-control btn btn-default">Submit</button>
        </div>
        <div class="form-group" style="position: relative">
            <label>Watch:</label>
            <input type="number" value=0 min=0 max=1 name="watch_n" id="watch_n" class="form-control" />
            <span id="watch_v" style="position: absolute; top: 40px; left: 10px; background: white; color: red;">OFF</span>
        </div>
        <h4>Saved Queries</h4>
        <ul>
            <li><a href='#' class="quick_query">Get CMW File</a>
                <span class="query">SELECT upc, Product, RegUnit, Brand, Description
FROM woodshed_no_replicate.CMWFile
                </span>
            </li>
            <li><a href='#' class="quick_query">Get Current Sales</a>
                <span class="query">SELECT p.department, bl.upc, bl.salePrice, bl.batchID, p.brand, p.description, date(b.startDate) AS startDate, date(b.endDate) AS endDate
FROM batchList AS bl
    LEFT JOIN products AS p ON bl.upc=p.upc
    LEFT JOIN batches AS b ON bl.batchID=b.batchID
WHERE bl.batchID IN ( SELECT batchID FROM batches WHERE '$today' BETWEEN startDate AND endDate)
GROUP BY bl.upc
order by p.department</span>
            </li>
            <li><a href='#' class="quick_query">Get Vendor Changes</a>
                <span class="query">SELECT t.upc, v.sku, t.cost as previousCost, p.cost as newCost, (p.cost - t.cost) AS difference,
p.brand, p.description, p.department as dept, m.super_name
FROM woodshed_no_replicate.temp AS t
    LEFT JOIN is4c_op.products AS p ON t.upc = p.upc
    LEFT JOIN is4c_op.MasterSuperDepts as m on p.department=m.dept_ID
    LEFT JOIN is4c_op.vendorItems AS v ON p.default_vendor_id=v.vendorID AND p.upc=v.upc
WHERE (p.cost - t.cost) <> 0
    AND p.inUse = 1
GROUP BY p.upc
ORDER BY (p.cost - t.cost) ASC;</span>
            </li>
            <li><a href='#' class="quick_query">Grocery Likecodes</a>
                <span class="query">SELECT 
l.likeCode, l.likeCodeDesc, 
    round(avg(cost),2) as AvgCost,
        round(stddev(cost),2) as STDEV
        FROM likeCodeView AS l 
        INNER JOIN products AS p ON l.upc=p.upc
        INNER JOIN MasterSuperDepts AS m ON m.dept_ID=p.department 
        WHERE m.superID=4
            AND p.upc IN (SELECT upc FROM likeCodeView)
                AND p.inUse = 1
                GROUP BY l.likeCode;</span>
            </li>
            <li><a href='#' class="quick_query">Coop Deals File</a>
                <span class="query">SELECT
upc, sku, brand, description, featured, line_notes, promo, period, sale_price, dealset
FROM woodshed_no_replicate.FullCoopDealsFile
WHERE dealset = 'november'
AND (period = 'B' OR period = 'AB') 
                </span>
            </li>

        </ul>
        <h4>Additional Features</h4>
        <ul>
            <li><a href='#' onclick="stripeByColumn();">Stripe Rows</a></li>
        </ul>
    </div>
</div>
<input type="hidden" name="keydown" id="keydown"/>
HTML;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
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
        if (target.hasClass('highlight-row')) {
            target.removeClass('highlight-row');
        } else {
            target.addClass('highlight-row');
        }
        $('#keydown').val(0);
    }
});

$('.quick_query').click(function(){
    $('#query').text('');
    var query = $(this).next().text();
    $('#query').text(query);
    $('#submit').trigger('click');
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
jQuery.loadScript = function (url, callback) {
    jQuery.ajax({
        url: url,
        dataType: 'script',
        success: callback,
        async: true
    });
}
$('#submit').click(function(){
    var query = $('#query').val();
    $.ajax({
        type: 'post',
        data: 'query='+query,
        url: '../../../git/fannie/reports/DBA/index.php',
        success: function(response) {
            $('#response').html(response);
            $('th').each(function(){
                var column_name = $(this).text();
                $(this).attr('data-column', column_name);
            });
            $.loadScript('tableColumnFilters.js', function(){
            });
            $('table').each(function(){
                $(this).addClass('table')
                    .addClass('table-bordered')
                    .addClass('table-sm')
                    .addClass('small');
            });
            stripeByColumn();
        }
    });
});

var timer = setInterval('watch_query()', 1000);
clearInterval(timer);
$('#watch_n').on('change', function(){
    var val = $(this).val();
    if (val == 0) {
        clearInterval(timer);
        $('#watch_v').text('OFF')
            .css('color', 'red');
    } else {
        timer = setInterval('watch_query()', parseInt(val, 10) * 1000);
        $('#watch_v').text('ON')
            .css('color', 'green');
    }
});
function watch_query()
{
   $('#submit').trigger('click');
}
JAVASCRIPT;
    }

    public function cssContent()
    {
        return <<<HTML
.highlight-row {
    background: plum;
    color: white;
}
.query {
    display: none;
}
.row {
    padding: 15px;
}
textarea.form-control {
    font-size: 14px;
    min-height: 250px;
}
#response {
    overflow-x: auto;
}
HTML;
    }

}
WebDispatch::conditionalExec();
