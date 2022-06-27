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
    protected $auth_types = array(2);

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();

        return false;
    }

    public function pageContent()
    {
        $today = new DateTime();
        $today = $today->format('Y-m-d');

        return <<<HTML
<div class="row">
    <div class="col-lg-10" id="col-1">
        <span id="filter-options"></span>
        <div id="response"></div>
    </div>
    <div class="col-lg-2" id="col-2">
        <div class="form-group">
            <form><textarea id="code" name="code"></textarea></form>
            <div style="display: none;">Key buffer: <span id="command-display"></span></div>
            <div style="display: none;">Vim mode: <span id="vim-mode"></span>
        </div>
        <div onclick="expandTextArea();" style="position: relative; z-index: 999; user-select: none;">
            <span class="scanicon scanicon-expand" style="position: absolute; top: -42; right: 0">&nbsp;</span>
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
        <input type="text" id="saved-queries-filter" class="form-control form-control-sm small">
        <ul style="font-size: 12px" id="saved-queries">
            <li><a href='#' class="quick_query">Run Everyday Sprouted Bagels</a>
                <span class="query">
SELECT * FROM is4c_trans.dlog_90_view  WHERE upc = "0005599104902" AND tdate > '2022-04-27'
                </span>
            </li>
            <li><a href='#' class="quick_query">Check For Badscans Today</a>
                <span class="query">

SELECT
t.store_id, DATE(datetime) AS date, SUBSTRING(datetime, 12, 5) AS time, register_no, t.upc, t.description AS trans_desc, p.description AS p_description, CONCAT( emp_no, '-', register_no, '-',trans_no) AS trans_num,
i.reason
FROM is4c_trans.dtransactions AS t
LEFT JOIN products AS p ON p.upc=t.upc
LEFT JOIN IgnoredBarcodes AS i ON i.upc=t.upc
WHERE datetime > '2022-05-18' 
AND t.description = 'BADSCAN'
AND t.upc > 99999
AND emp_no != 9999
AND t.upc < 0999999999999
GROUP BY t.upc, CONCAT( emp_no, '-', register_no, '-',trans_no)
ORDER BY store_id, datetime DESC
                </span>
            </li>
            <li><a href='#' class="quick_query">Get Nutri Fact Percent DV</a>
                <span class="query">
SELECT
units*
0.02
, name, units
FROM NutriFactStd   
                </span>
            </li>
            <li><a href='#' class="quick_query">UNFI Bulk SKU Check</a>
                <span class="query">
SELECT 
p.upc, p.brand, p.description, v.sku, r.reviewed, DATE(p.last_sold) AS lastSold
FROM products AS p 
LEFT JOIN MasterSuperDepts AS m ON p.department=m.dept_ID
LEFT JOIN vendorItems AS v ON v.upc=p.upc AND v.vendorID = 1
LEFT JOIN prodReview AS r ON r.upc=p.upc
WHERE p.last_sold > NOW() - INTERVAL 30 DAY
AND p.upc < 1000
AND p.default_vendor_id = 1
GROUP BY p.upc
ORDER BY v.sku, r.reviewed
                </span>
            </li>
            <li><a href='#' class="quick_query">Check Bulk Herb Movement</a>
                <span class="query">
SELECT m.*, ROUND(p.auto_par*7, 2) AS curPar, ROUND(p.auto_par*7, 2) - par  
FROM woodshed_no_replicate.bulkHerbMT20210923 AS m 
LEFT JOIN is4c_op.products AS p ON m.upc=p.upc 
    AND p.store_id = 2 
WHERE ROUND(p.auto_par*7, 2) <> par 
AND p.upc NOT IN ('0000000008366', '0024152000000')
ORDER BY ABS(ROUND(p.auto_par*7, 2) - par) DESC;
                </span>
            </li>
            <li><a href='#' class="quick_query">Cleanup Brand Names</a>
                <span class="query">SELECT u.brand, u.description, p.brand, p.description, p.upc
FROM batchList AS b 
LEFT JOIN products AS p ON b.upc=p.upc
LEFT JOIN productUser AS u ON p.upc=u.upc
WHERE b.batchID BETWEEN 17272 AND 17291
GROUP BY p.upc
ORDER BY p.brand</span>
            </li>
            <li><a href='#' class="quick_query">Find Items Removed From Basics</a>
                <span class="query">
# Upload Basics file to Generic Upload to use this query
# To check CSCS, change prid 6 to 12 and upload the CSCS file to Generic Upload
SELECT p.upc, v.sku, p.brand, p.description, p.last_sold, p.inUse FROM products AS p LEFT JOIN vendorItems AS v ON p.upc=v.upc AND p.default_vendor_id=v.vendorID LEFT JOIN PriceRules AS r ON p.price_rule_id=r.priceRuleID WHERE r.priceRuleTypeID = 6 AND p.upc NOT IN (SELECT LPAD(SUBSTRING(g.upc,1,12),13,"0") FROM GenericUpload) AND p.inUse = 1 AND p.last_sold >= DATE(NOW() - INTERVAL 60 DAY);
                </span>
            </li>
            <li><a href='#' class="quick_query">Get Alberts Price File</a>
                <span class="query">SELECT * FROM woodshed_no_replicate.AlbertsFileView</span>
            </li>
            <li><a href='#' class="quick_query">Get Bulk Herbs</a>
                <span class="query">select m.*, ROUND(p.auto_par*7, 2) AS curPar  from woodshed_no_replicate.bulkHerbMT20210923 as m LEFT JOIN is4c_op.products AS p ON m.upc=p.upc and p.store_id = 2 where ROUND(p.auto_par*7, 2) <> par;</span>
            </li>
            <li><a href='#' class="quick_query">Get Bulk Price Change List</a>
                <span class="query">
SELECT b.upc,
p.brand,
p.description,
p.normal_price,
b.salePrice
FROM batchList AS b
LEFT JOIN products
  AS p ON b.upc=p.upc
  WHERE b.upc IN ("...")
  AND b.batchID IN (1234)
GROUP BY p.upc
                </span>
            </li>
            <li><a href='#' class="quick_query">Get Bulk Sale Items</a>
                <span class="query">SELECT p.department, bl.upc, bl.salePrice, bl.batchID, p.brand, p.description, date(b.startDate) AS startDate, date(b.endDate) AS endDate
FROM batchList AS bl
LEFT JOIN products AS p ON bl.upc=p.upc
LEFT JOIN batches AS b ON bl.batchID=b.batchID
LEFT JOIN MasterSuperDepts AS m ON p.department=m.dept_ID
WHERE bl.batchID IN ( SELECT batchID FROM batches WHERE '$today' BETWEEN startDate AND endDate)
AND m.super_name = 'BULK'
AND p.department NOT IN (245, 251)
GROUP BY bl.upc
order by p.department, p.brand
</span>
            </li>
            <li><a href='#' class="quick_query">Get CMW File</a>
                <span class="query">SELECT upc, Product, RegUnit, Brand, Description, 
CASE WHEN WhsAvail like '%T%' THEN 'yes' ELSE 'no' END AS Avail
FROM woodshed_no_replicate.CMWFile
WHERE CASE WHEN WhsAvail like '%T%' THEN 'yes' ELSE 'no' END = 'yes'
                </span>
            </li>
            <li><a href='#' class="quick_query">Get Coop Basics File</a>
                <span class="query">SELECT * FROM woodshed_no_replicate.BasicsFiles
                </span>
            </li>
            <li><a href='#' class="quick_query">Get Coop Basics Items From List</a>
                <span class="query">select * from woodshed_no_replicate.BasicsFiles where upc in (1234) and month = 'November'
                </span>
            </li>
            <li><a href='#' class="quick_query">Get Current Sales</a>
                <span class="query">
SELECT p.department, bl.upc, bl.salePrice, bl.batchID, p.brand, p.description, date(b.startDate) AS startDate, date(b.endDate) AS endDate,
CONCAT(ROUND(100 * (1 - bl.salePrice / normal_price), 0), '%') AS PrcOff 
FROM batchList AS bl
LEFT JOIN products AS p ON bl.upc=p.upc
LEFT JOIN batches AS b ON bl.batchID=b.batchID
WHERE DATE(NOW()) BETWEEN b.startDate AND b.endDate
AND p.upc NOT LIKE '%LC%'
AND bl.salePrice <> 0
GROUP BY bl.upc
order by department, p.brand
# order by bl.salePrice / normal_price, p.department, p.brand

                </span>
            </li>
            <li><a href='#' class="quick_query">Get Current Linked PLU</a>
                <span class="query">SELECT p.department, bl.upc, bl.salePrice, bl.batchID, p.brand, p.description, date(b.startDate) AS startDate, date(b.endDate) AS endDate
FROM batchList AS bl
LEFT JOIN products AS p ON bl.upc=p.upc
LEFT JOIN batches AS b ON bl.batchID=b.batchID
LEFT JOIN MasterSuperDepts AS m ON p.department=m.dept_ID
WHERE bl.batchID IN ( SELECT batchID FROM batches WHERE '2020-04-29' BETWEEN startDate AND endDate)
AND m.super_name = 'BULK'
AND p.department NOT IN (245, 251)
GROUP BY bl.upc
order by p.department, p.brand                </span>
            </li>
            <li><a href='#' class="quick_query">Get DenHerb MT</a>
                <span class="query">select department, upc, brand, description, ROUND(auto_par*7,1) as den_par 
from products where inUse = 1 and store_id = 1 and scale = 1 and department > 251 and department < 260
order by department, upc
                </span>
            </li>
            <li><a href='#' class="quick_query">Get Items Created</a>
                <span class="query">select upc, brand, description from products where created > '2020-09-25 09:00:00' 
group by upc</span>
            </li>
            <li><a href='#' class="quick_query">Get Linked PLU Single Item</a>
                <span class="query">select plu, itemdesc, linkedPLU from scaleItems where linkedPLU = 0000000000864</span>
            </li>
            <li><a href='#' class="quick_query">Get Locations by Selected Material</a>
                <span class="query">
SELECT p.department, d.dept_name, p.upc, p.brand, p.description, v.sections FROM products p left join departments d on p.department=d.dept_no left join MasterSuperDepts m on p.department=m.dept_ID JOIN FloorSectionProductMap f on p.upc=f.upc JOIN FloorSections as s on f.floorSectionID=s.floorSectionID LEFT JOIN FloorSectionsListView v on p.upc=v.upc AND v.storeID = 2 WHERE super_name = 'REFRIGERATED' AND f.floorSectionID <> 45 GROUP BY p.upc ORDER BY p.department, p.upc;
                </span>
            </li>
            <li><a href='#' class="quick_query">Get Missing Sub-Locations</a>
                <span class="query">select i.upc, f.subSection, s.name, p.department, m.super_name
FROM PickupOrderItems as i 
LEFT JOIN FloorSubSections AS f ON i.upc=f.upc 
LEFT JOIN FloorSections AS s ON f.floorSectionID=s.floorSectionID 
LEFT JOIN products AS p ON p.upc=i.upc
LEFT JOIN MasterSuperDepts AS m ON p.department=m.dept_ID
WHERE f.subSection IS NULL
AND m.super_name NOT IN ('WELLNESS', 'BULK', 'PRODUCE','BREAD','DELI','GEN MERCH')
GROUP BY i.upc
ORDER BY m.super_name
                </span>
            </li>
            <li><a href='#' class="quick_query">Get Products In Floor Section</a>
                <span class="query">

SELECT p.upc, brand, description, 
ROUND(p.auto_par * 7, 2)
FROM products AS p 
LEFT JOIN MasterSuperDepts AS m ON p.department=m.dept_ID
LEFT JOIN FloorSectionProductMap AS f ON p.upc=f.upc
WHERE superID = 1
AND f.floorSectionID = 49 
AND ROUND(p.auto_par * 7, 2) > 0.5
GROUP BY p.upc
ORDER BY auto_par DESC
</span>
            </li>
            <li><a href='#' class="quick_query">New Items Entered Today</a>
                <span class="query">
SELECT upc, brand, description FROM products WHERE created >= DATE(NOW()) GROUP BY upc
                </span>
            </li>
            <li><a href='#' class="quick_query">Get Review-Comments</a>
                <span class="query">SELECT v.vendorName, r.upc, p.default_vendor_id AS vendorID, p.brand, p.description,
r.user, r.reviewed, r.comment
FROM prodReview AS r
    LEFT JOIN products AS p ON r.upc=p.upc
    LEFT JOIN vendors AS v ON p.default_vendor_id=v.vendorID
WHERE comment IS NOT NULL
    AND comment != ''
GROUP BY p.upc
ORDER BY p.default_vendor_id
                </span>
            </li>
            <li><a href='#' class="quick_query">Get Scale LinkedPLU in Batch</a>
                <span class="query">
SELECT linkedPLU AS pluInBatch, price AS currentPrice, plu AS pluMissingFromBatch, salePrice AS newPrice FROM scaleItems AS s LEFT JOIN batchList AS b ON s.linkedPLU=b.upc WHERE b.batchID = 
16411
</span>
            </li>
            <li><a href='#' class="quick_query">Get Single_Item_Movement 90</a>
                <span class="query">
SELECT SUM(itemQtty), DATE(tdate) AS Date, store_id
FROM is4c_trans.dlog_90_view
WHERE upc = "0028761000000"
GROUP BY DATE(tdate)
                </span>
            </li>
            <li><a href='#' class="quick_query">Get Single_Item_Movement All</a>
                <span class="query">SELECT upc, DATE(datetime), SUM(quantity), unitPrice FROM trans_archive.bigArchive WHERE upc = '0007349012827' 
AND store_id = 2 GROUP BY date_id;</span>
            </li>
            <li><a href='#' class="quick_query">Get Vendor Changes</a>
                <span class="query">SELECT t.upc, v.sku, t.cost as previousCost, p.cost as newCost, (p.cost - t.cost) AS difference,
p.brand, p.description, p.department as dept, m.super_name, CONCAT(t.upc, ': ', (p.cost - t.cost)) AS report ,
(p.cost - t.cost) / t.cost AS perDiff
FROM woodshed_no_replicate.temp AS t
LEFT JOIN is4c_op.products AS p ON t.upc = p.upc
LEFT JOIN is4c_op.MasterSuperDepts as m on p.department=m.dept_ID
LEFT JOIN is4c_op.vendorItems AS v ON p.default_vendor_id=v.vendorID AND p.upc=v.upc
WHERE (p.cost - t.cost) <> 0
AND ABS(p.cost - t.cost) > 0.09
GROUP BY p.upc
ORDER BY (p.cost - t.cost) ASC;</span>
            </li>
            <li><a href='#' class="quick_query">Get UNFI OOS</a>
                <span class="query">SELECT g.*, p.last_sold, p.store_id
FROM GenericUpload AS g
LEFT JOIN products AS p ON g.upc=p.upc
WHERE last_sold IS NOT NULL
AND last_sold > '2020-03-01'</span>
            </li>
            <li><a href='#' class="quick_query">Get Yesterday's Mercato Sales</a>
                <span class="query">SELECT upc, description, total, sum(ItemQtty), register_no 
FROM is4c_trans.dlog_15
WHERE register_no = 40
AND DATE(tdate) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
GROUP BY upc DESC;
                </span>
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
            <li><a href='#' class="quick_query"><span style="color: green">Coop Deals</span> File</a>
                <span class="query">SELECT
upc, sku, brand, description, featured, line_notes, promo, period, sale_price, dealset
FROM woodshed_no_replicate.FullCoopDealsFile
WHERE dealset = 'april'
AND (period = 'A' OR period = 'AB') 
                </span>
            </li>
            <li><a href='#' class="quick_query">Coop Deals Flyer Review</a>
                <span class="query">
SELECT p.upc, p.department, b.salePrice, p.brand, p.description 
FROM
batchList AS b
LEFT JOIN products AS p ON b.upc=p.upc
WHERE b.batchID IN (13581,13579,13576,13573,13570,13567,13565,13563,13560)
GROUP BY b.upc
                </span>
            </li>
            <li><a href='#' class="quick_query">Paycard Transactions [Hillside]</a>
                <span class="query">
SELECT registerNo, transNo, empNo, processor,
    cardType,requestDatetime,
        commErr, xResultMessage, paycardTransactionID as PID,
            transType
            FROM is4c_trans.PaycardTransactions
            WHERE dateID = REPLACE(DATE(NOW()), '-', '') 
                AND registerNo IN (1,2,3,4,5,6)
                ORDER BY requestDatetime DESC;
            </li>
            <li><a href='#' class="quick_query">Paycard Transactions [Denfeld]</a>
                <span class="query">
SELECT registerNo, transNo, empNo, processor,
    cardType,requestDatetime,
        commErr, xResultMessage, paycardTransactionID as PID,
            transType
            FROM is4c_trans.PaycardTransactions
            WHERE dateID = REPLACE(DATE(NOW()), '-', '') 
                AND registerNo IN (12,13,14,15,16)
                ORDER BY requestDatetime DESC;
            </li>
            <li><a href='#' class="quick_query">Get Prods by Floor Section</a>
                <span class="query">
SELECT p.upc, p.brand, p.description
FROM products as p 
LEFT JOIN FloorSectionProductMap AS m ON p.upc=m.upc 
WHERE 
m.floorSectionID = 8
AND p.inUse = 1
GROUP BY upc
            </li>
            <li><a href='#' class="quick_query">Get Vendors Reviewed Last Month</a>
                <span class="query">SELECT l.bid, DATE(l.created) AS created, DATE(l.forced) AS forced,
v.vendorName, v.vendorID
FROM batchReviewLog AS l 
LEFT JOIN vendors AS v ON l.vid=v.vendorID
WHERE forced >= '2021-07-30'
AND forced <= '2021-09-01'
GROUP BY vendorID
ORDER BY bid</span>
            </li>
            <li><a href='#' class="quick_query">Review Sale Sign Info Info</a>
                <span class="query">
SELECT u.brand, p.brand, p.department, bl.upc, bl.salePrice, bl.batchID,  p.description, date(b.startDate) AS startDate, date(b.endDate) AS endDate
FROM batchList AS bl
LEFT JOIN products AS p ON bl.upc=p.upc
LEFT JOIN batches AS b ON bl.batchID=b.batchID
LEFT JOIN productUser as u on p.upc=u.upc
WHERE bl.batchID IN ( SELECT batchID FROM batches WHERE '2020-09-13' BETWEEN startDate AND endDate)
GROUP BY bl.upc
order by u.brand 

            </li>
            <li><a href='#' class="quick_query">SIGN ALIAS: Get All SA Items In Batch</a>
                <span class="query">
SELECT * FROM 
woodshed_no_replicate.signAliasMap as s
LEFT join batchList as b on s.upc=b.upc
WHERE b.batchID = 18575
GROUP BY b.upc
ORDER BY aliasID </span>
            </li>
            <li><a href='#' class="quick_query">SIGN ALIAS: Get Groups In Bach </a>
                <span class="query">
SELECT * FROM 
woodshed_no_replicate.signAliasMap as s
LEFT join batchList as b on s.upc=b.upc
LEFT join woodshed_no_replicate.signAlias AS a ON s.aliasID=a.aliasID
WHERE b.batchID = 18575
GROUP BY a.aliasID

                </span>
            </li>
            <li><a href='#' class="quick_query">SIGN ALIAS: Get Non-SA In Batch</a>
                <span class="query">
SELECT upc FROM batchList WHERE batchID = 18575 and upc not in (
    SELECT b.upc FROM 
    woodshed_no_replicate.signAliasMap as s
    LEFT join batchList as b on s.upc=b.upc
    WHERE b.batchID = 18575
    GROUP BY b.upc
)
                </span>
            </li>
        </ul>
        <h4>Additional Features</h4>
        <ul style="height: 200px; overflow-y: auto; font-size: 12px">
            <li><a href='#' onclick="columnGroupFilter();">Column Group Filter</a></li>
        </ul>
    </div>
</div>
<input type="hidden" name="keydown" id="keydown"/>
HTML;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
// codemirror start
var editor = CodeMirror.fromTextArea(document.getElementById("code"), {
    lineNumbers: true,
    mode: "text/x-csrc",
    keyMap: "vim",
    matchBrackets: true,
    showCursorWhenSelecting: true
});
var commandDisplay = document.getElementById('command-display');
var keys = '';
    CodeMirror.on(editor, 'vim-keypress', function(key) {
    keys = keys + key;
    commandDisplay.innerText = keys;
});
    CodeMirror.on(editor, 'vim-command-done', function(e) {
    keys = '';
    commandDisplay.innerHTML = keys;
});
var vimMode = document.getElementById('vim-mode');
    CodeMirror.on(editor, 'vim-mode-change', function(e) {
    vimMode.innerText = JSON.stringify(e);
});
// codemirror end

var getCodeText = function() {
    var text = editor.getValue();    
    return text;
}
const downloadToFile = (content, filename, contentType) => {
    const a = document.createElement('a');
    const file = new Blob([content], {type: contentType});

    a.href= URL.createObjectURL(file);
    a.download = filename;
    a.click();

    URL.revokeObjectURL(a.href);
};
CodeMirror.commands.save = function(){ 
    let text = getCodeText();
    let filename = prompt("Save file as");
    downloadToFile(text, filename+'.txt', 'text/plain');
};

var putCodeText = function(text) {
    editor.setValue(text);    
}
        
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
    var query = $(this).next().text();
    putCodeText(query);
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
    var query = getCodeText();
    $.ajax({
        type: 'post',
        data: 'query='+query,
        url: '../../../git/fannie/reports/DBA/index.php',
        beforeSend: function() {
            //$('#processing').show();
            $('body').css('cursor', 'wait');
        },
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
                $(this).attr('id', 'dataTable');
            });
            stripeByColumn();
        },
        complete: function() {
            $('#processing').hide();
            $('body').css('cursor', 'default');
        }
    });
});

var columnGroupFilter = function()
{
    /*
        start select-filter chunk   vvv
    */
    var table_id = 'dataTable';
    var getNumCols = function(table_id) {
        var length = $('#'+table_id).find('tr')[0].cells.length;
        return length;
    };
    var col_count = getNumCols('dataTable');
    var getOptions = function(row, to_id, table_id)
    {
        var options = [];
        $('tr td:nth-child('+row+')').each(function(){
            var tid = $(this).closest('table').attr('id');
            if (tid == table_id) {
                var text = $(this).text();
                if ( $.inArray(text, options) == -1 ) {
                    options.push(text);
                }
            }
        });
        options.sort();
        var column = $('#'+table_id+' th:nth-child('+row+')').text();
        var html = '<select class="column-filter" name="row'+row+'" data-col-name="'+column+'" style="display: none;">';
        $.each(options, function(i,option) {
            html += '<option value='+option+'>'+option+'</option>';
        });
        html += '</select>';
        $('#'+to_id).append(html);
    }
    var getThead = function(to_id, table_id)
    {
        var html = '<select class="column-filter-control" name="column-filter-control">';
        $('#'+table_id+' th').each(function(){
            var header = $(this).text();
            html += '<option value='+header+'>'+header+'</option>';
        });
        html += '</select>';
        $('#'+to_id).prepend(html);
    }
    getThead('filter-options', 'dataTable');
    for (var i = 1; i <= col_count; i++) {
        getOptions(i, 'filter-options', 'dataTable');
    }
    $('.column-filter').change(function(){
        var name = $(this).attr('name');
        var row = name.substring(3);
        var value = $(this).children('option:selected').text();
        $('tr').each(function() {
            var tid = $(this).closest('table').attr('id');
            if (tid == table_id) {
                $(this).show();
            }
        });
        $('tr td:nth-child('+row+')').each(function(){
            var tid = $(this).closest('table').attr('id');
            if (tid == table_id) {
                var text = $(this).text();
                if (text != value) {
                    $(this).closest('tr').hide();
                }
            }
        });
    });
    $('.column-filter-control').change(function(){
        var value = $(this).children('option:selected').text();
        $('.column-filter').each(function(){
            var column = $(this).attr('data-col-name');
            if (column == value) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
    /*
        end select-filter chunk ^^^
    */

}

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

$('#saved-queries-filter').keyup(function(){
    var text = $(this).val();
    if (text == '') {
        $('.quick_query').each(function(){
            $(this).closest('li').show();
        });
    } else {
        $('.quick_query').each(function(){
            $(this).closest('li').show();
        });
        $('.quick_query').each(function(){
            var line = $(this).text(); 
            line = line.toUpperCase();
            text = text.toUpperCase();
            if (!line.includes(text)) {
                $(this).closest('li').hide();
            }
        });
    }
});

var expandTextArea = function() {
    let col1 = $('#col-1');
    let col2 = $('#col-2');
    if (col1.hasClass('col-lg-10')) {
        col1.removeClass('col-lg-10')
            .addClass('col-lg-2');
        col2.removeClass('col-lg-2')
            .addClass('col-lg-10');
    } else {
        col2.removeClass('col-lg-10')
            .addClass('col-lg-2');
        col1.removeClass('col-lg-2')
            .addClass('col-lg-10');
    }
};

JAVASCRIPT;
    }

    public function cssContent()
    {
        return <<<HTML
select {
    border: 1px solid lightgrey;
}
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
.CodeMirror {
    font-family: consolas, monospace;
    font-size: 12px;
    border: 1px solid lightgrey;
}
.scanicon-expand {
    background-image: url('http://key/Scannie/common/src/img/icons/expandIcon-small.png');
    background-repeat: no-repeat;
    display: inline-block;
    height: 25px;
}
HTML;
    }

}
WebDispatch::conditionalExec();
