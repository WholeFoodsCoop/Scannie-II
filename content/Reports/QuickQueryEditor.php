<?php
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
class QuickQueryEditor extends PageLayoutA
{

    protected $must_authenticate = true;
    protected $auth_types = array(2);

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();
        $this->__routes[] = "post<name>";
        $this->__routes[] = "post<query>";

        return parent::preprocess();
    }

    public function postNameHandler()
    {
        $dbc = scanLib::getConObj('SCANALTDB'); 
        $id = FormLib::get('id');
        $name = FormLib::get('name');
       
        $prep = $dbc->prepare("UPDATE quickQueries SET name = ? WHERE id = ?"); 
        $res = $dbc->execute($prep, array($name, $id));
        echo ($er = $dbc->error()) ? $er : 'saved';

        return false;
    }

    public function postQueryHandler()
    {
        $dbc = scanLib::getConObj('SCANALTDB'); 
        $id = FormLib::get('id');
        $query = FormLib::get('query');
       
        $prep = $dbc->prepare("UPDATE quickQueries SET query = ? WHERE id = ?"); 
        $res = $dbc->execute($prep, array($query, $id));
        echo ($er = $dbc->error()) ? $er : 'saved';

        return false;
    }

    public function pageContent()
    {
        $today = new DateTime();
        $today = $today->format('Y-m-d');
        $td = '';

        $dbc = scanLib::getConObj('SCANALTDB'); 

        $prep = $dbc->prepare("SELECT id, name, query FROM quickQueries ORDER BY name ASC");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            $name = $row['name'];
            $query = $row['query'];
            $id = $row['id'];
            $td .= sprintf("<tr id='$id'>
                <td class='editableN' contentEditable=true>%s</td>
                <td class='editableQ' contentEditable=true>%s</td></tr>",
                $name, 
                nl2br($query)
            );
        }

        return <<<HTML
<div class="row" style="width: 100%; padding-top: 24px;">
    <div class="col-lg-2"></div>
    <div class="col-lg-8">
        <table class="table table-bordered"><thead></thead><tbody>$td</tbody></table>
    </div>
    <div class="col-lg-2"></div>
</div>
HTML;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
var lastN = '';
var lastQ = ''

$('.editableN').focus( function() {
    lastN = $(this).text();
});
$('.editableN').focusout( function() {
    var n = $(this).text();
    if (n != lastN) {
        var id = $(this).closest('tr').attr('id');
        $.ajax({
            type: 'post',
            url: 'QuickQueryEditor.php',
            data: 'id='+id+'&name='+n,
            success: function(resp) {
                console.log(resp);
                if (resp == 'saved') {
                    $('#'+id).animate({backgroundColor: '#AFE1AF'}, 'slow')
                        .animate({backgroundColor: '#FFFFFF'}, 'slow');
                } else {
                    $('#'+id).animate({backgroundColor: '#FF6347'}, 'slow')
                        .animate({backgroundColor: '#FFFFFF'}, 'slow');
                }
            },
        });
    }
});

$('.editableQ').focus( function() {
    lastQ = $(this).text();
});
$('.editableQ').focusout( function() {
    var q = $(this).text();
    if (q != lastQ) {
        var id = $(this).closest('tr').attr('id');
        $.ajax({
            type: 'post',
            url: 'QuickQueryEditor.php',
            data: 'id='+id+'&query='+q,
            success: function(resp) {
                console.log(resp);
                if (resp == 'saved') {
                    $('#'+id).animate({backgroundColor: '#AFE1AF'}, 'slow')
                        .animate({backgroundColor: '#FFFFFF'}, 'slow');
                } else {
                    $('#'+id).animate({backgroundColor: '#FF6347'}, 'slow')
                        .animate({backgroundColor: '#FFFFFF'}, 'slow');
                }
            },
        });
    }
});
JAVASCRIPT;
    }

    public function cssContent()
    {
        return <<<HTML
HTML;
    }

}
WebDispatch::conditionalExec();
