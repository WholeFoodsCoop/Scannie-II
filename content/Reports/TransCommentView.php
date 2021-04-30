<?php
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
class TransCommentView extends PageLayoutA
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

        $td = '';
        $hidden = '';
        $dbc = scanLib::getConObj();

        $data = array();
        $prep = $dbc->prepare("SELECT description, tdate, trans_num 
            FROM is4c_trans.dlog_90_view where trans_subtype = 'CM'
            ORDER BY description;");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            $desc = $row['description'];
            if (!isset($data[$desc])) {
                $data[$desc]['count'] = 1;
                $data[$desc]['transs'][] = $row['tdate'] . ', <input value="' . $row['trans_num'] . '"/>';
            } else {
                $data[$desc]['count']++;
                $data[$desc]['transs'][] = $row['tdate'] . ', <input value="' . $row['trans_num'] . '"/>';
            }
        }
        foreach ($data as $desc => $row) {
            $id = str_replace(" ", "", $desc);
            $id = 'id'.$id;
            $td.= "<tr><td onclick=\"showRows('$id'); \" class=\"click\">$desc</td><td>{$row['count']}</td></tr>";
            $hidden .= "<div id=\"$id\" class=\"hidden\">";
            foreach ($row['transs'] as $transaction) {
                $hidden .= "<div>$transaction</div>";
            }
            $hidden .= "</div>";
        }

        return <<<HTML
<div class="container" style="padding: 25px">
    <div class="row">
        <div class="col-lg-1"></div>
        <div class="col-lg-10">
            $hidden
            <table class="table table-bordered"><tbody>$td</tbody></table>
        </div>
        <div class="col-lg-1"></div>
    </div>
</div>
HTML;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
var showRows = function(id) {
    $('div.hidden').each(function(){
        $(this).hide();
    });
    var elm = $('#'+id);
    var isShown = elm.is(':visible');
    if (isShown) {
        elm.hide();
    } else {
        elm.show();
    }
}
JAVASCRIPT;
    }

    public function cssContent()
    {
        return <<<HTML
div.hidden {
    display: none;
    position: fixed;
    //top: 10vh;
    //left: 10vw;
    top: 0px;
    left: 0px;
    background: rgba(255,255,255, 0.9);
    overflow-y: scroll;
    max-height: 100vh; 
}
td.click {
    cursor: pointer;
}
HTML;
    }

}
WebDispatch::conditionalExec();
