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
    <div class="col-lg-10">
        <span id="filter-options"></span>
        <div id="response"></div>
    </div>
    <div class="col-lg-2">
        <div class="form-group">
            <form><textarea id="code" name="code"></textarea></form>
            <div style="display: none;">Key buffer: <span id="command-display"></span></div>
            <div style="display: none;">Vim mode: <span id="vim-mode"></span>
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
            <li><a href='#' class="quick_query">Get Alberts Price File</a>
                <span class="query">SELECT * FROM woodshed_no_replicate.AlbertsFileView</span>
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
                <span class="query">SELECT p.department, bl.upc, bl.salePrice, bl.batchID, p.brand, p.description, date(b.startDate) AS startDate, date(b.endDate) AS endDate
FROM batchList AS bl
    LEFT JOIN products AS p ON bl.upc=p.upc
    LEFT JOIN batches AS b ON bl.batchID=b.batchID
WHERE bl.batchID IN ( SELECT batchID FROM batches WHERE '$today' BETWEEN startDate AND endDate)
GROUP BY bl.upc
order by p.department, p.brand</span>
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
            <li><a href='#' class="quick_query">Get Linked PLU</a>
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
                <span class="query">SELECT plu, price, itemDesc, linkedPLU FROM scaleItems WHERE linkedPLU IN (SELECT upc FROM batchList WHERE batchID = 16411)</span>
            </li>
            <li><a href='#' class="quick_query">Get Single_Item_Movement 90</a>
                <span class="query">SELECT upc, DATE(tdate), SUM(quantity) FROM is4c_trans.dlog_90_view WHERE upc = '0074599850009' 
AND store_id = 2 GROUP BY date_id;</span>
            </li>
            <li><a href='#' class="quick_query">Get Single_Item_Movement All</a>
                <span class="query">SELECT upc, DATE(datetime), SUM(quantity), unitPrice FROM trans_archive.bigArchive WHERE upc = '0007349012827' 
AND store_id = 2 GROUP BY date_id;</span>
            </li>
            <li><a href='#' class="quick_query">Get Vendor Changes</a>
                <span class="query">SELECT t.upc, v.sku, t.cost as previousCost, p.cost as newCost, (p.cost - t.cost) AS difference,
p.brand, p.description, p.department as dept, m.super_name, CONCAT(t.upc, ': ', (p.cost - t.cost)) AS report 
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
CodeMirror.commands.save = function(){ alert("Saving"); };
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
HTML;
    }

}
WebDispatch::conditionalExec();
