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
        $quickQueries = '';

        $dbc = scanLib::getConObj('SCANALTDB'); 

        $prep = $dbc->prepare("SELECT name, query FROM quickQueries");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            $quickQueries .= <<<HTML
            <li><a href='#' class="quick_query">{$row['name']}</a>
                <span class="query">{$row['query']}</span>
            </li>
HTML;
        }

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
            $quickQueries
        </ul>
        <h4>Additional Features</h4>
        <ul style="height: 200px; overflow-y: auto; font-size: 12px">
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
