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
    </div>
</div>
HTML;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
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
