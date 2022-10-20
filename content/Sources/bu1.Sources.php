<?php
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}

class Sources extends PageLayoutA
{

    public $ui = true;
    public $must_authenticate = true;

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();
        $this->__routes[] = 'post<test>';
        $this->__routes[] = 'post<bookID>';
        $this->__routes[] = 'post<newTitle>';

        return parent::preprocess();
    }

    public function postNewTitleHandler()
    {
        $dbc = scanLib::getConObj();
        $title = FormLib::get('newTitle', false);
        $author = FormLib::get('newAuthor', false);
        $copy = FormLib::get('newCopy', false);
        $edition = FormLib::get('newEdition', false);

        $args = array($title, $author, $edition, $copy);
        $prep = $dbc->prepare("INSERT INTO ref (title, author, edition, ogCopyDate)
            VALUES (?, ?, ?, ?)");
        $res = $dbc->execute($prep, $args);
        $ret = ($dbc->error()) ? $dbc->error() : 'success';
        
        return header("location: Sources.php?status=$ret");
    }

    public function postBookIDHandler()
    {
        $dbc = scanLib::getConObj();
        $bookID = FormLib::get('bookID', false);
        $info = array();

        $args = array($bookID);
        $prep = $dbc->prepare("SELECT * FROM ref WHERE id = ?");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $title = $row['title'];
            $author = $row['author'];
            $edition = $row['edition'];
            $copy = $row['ogCopyDate'];
            $info[] = array(
                'title'=>$title,
                'author'=>$author,
                'edition'=>$edition,
                'copy'=>$copy
            );
        }

        $args = array($bookID);
        $prep = $dbc->prepare("SELECT * FROM quotes WHERE bookID = ?");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $info[] = array(
                'id' => $row['id'],
                'text' => $row['text'],
                'pages' => $row['pages'],
                'dateref' => $row['dateref']
            );
        }

        echo json_encode($info);
        return false;
    }

    public function postTestView()
    {
        return <<<HTML
This was a test
<a href="#" onclick="location.href = 'Sources.php'">Go Back</a>
HTML;
    }

    public function pageContent()
    {
        $ret = '';
        $dbc = scanLib::getConObj();
        $selOpts = "<select name=\"source\" id=\"select-source\" class=\"form-control\">
            <option value=\"null\">Choose a Source</option>";
        $sources = array();

        $cols = array('title', 'author', 'edition', 'ogCopyDate');
        $prep = $dbc->prepare("SELECT * FROM ref ORDER BY title, author");
        $res = $dbc->execute($prep); 
        while ($row = $dbc->fetchRow($res)) {
            if (!array_key_exists($row['title'].', '.$row['author'].', '.$row['edition'], $sources)) {
                $sources[$row['title'].', '.$row['author'].', '.$row['edition']] = $row['id'];
            }
            foreach ($cols as $col) {
                $ret .= "<div>{$row[$col]}</div>";
            }
        }

        $srcText = '';
        foreach ($sources as $source => $id) {
            $srcText .= "<div>key: $source, id: $source</div>";
            $selOpts .= "<option value=\"$id\">$source</option>";
        }
        $selOpts .= "</select>";

        $year = date('Y');

        return <<<HTML
<div align="center" style="padding-top: 14px;">
</div>
<div class="row">
    <div class="col-lg-2"></div>
    <div class="col-lg-7">
        <form>
            <label>Select a Source</label>
            <div class="form-group">
                $selOpts
            </div>
        </form>
        <div id="ajax-resp-body"></div>
    </div>
    <div class="col-lg-3">
        <label>Loaded:</label>
        <div class="form-group">
            <input name="loaded-title" id="loaded-title" type="text" class="form-control" />
        </div>
        <h4>Add New Source</h4>
        <div align="" style="padding: 15px">
        <form name="newSource" action="Sources.php" method="post">
            <label class="label-grey">&#9608; Title</label class="label-grey">
            <div class="form-group">
                <input name="newTitle" id="new-source" type="text" class="form-control" />
            </div>
            <label class="label-grey">&#9608; Author</label class="label-grey">
            <div class="form-group">
                <input name="newAuthor" id="new-source" type="text" class="form-control" />
            </div>
            <label class="label-grey">&#9608; First Copy Year</label class="label-grey">
            <div class="form-group">
                <input name="newCopy" id="new-source" type="text" class="form-control" />
            </div>
            <label class="label-grey">&#9608; Edition</label class="label-grey">
            <div class="form-group">
                <input name="newEdition" id="new-source" type="text" class="form-control" />
            </div>
            <div class="form-group">
                <input type="submit" class="form-control btn btn-default" />
            </div>
        </form>
        </div>
    </div>
    <div style="border: 1px solid grey;"></div>
</div>
<div><a href=""></a></div>
<!--
<form action="Sources.php" method="post">
    <label>This is a test</label>
    <input name="test" value="1" />
    <input type="submit" />
</form>
-->
HTML;
    }

    public function javascriptContent()
    {
        return <<<HTML
var AjaxRespELEM = $('#ajax-resp-body');
var tmpTxt1 = '';
var tmpTxt2 = '';
var loaded = {};
loaded.title = $('#loaded-title');
$('#select-source').change(function(){
    tmpTxt1 = '';
    tmpTxt2 = '';
    var bookID = $(this).find(":selected").val();
    $.ajax({
        type: 'post',
        data: 'bookID='+bookID,
        url: 'Sources.php',
        dataType: 'json',
        success: function(resp) {
            console.log('ajax success');
            console.log(resp);
            $.each(resp, function(k, v) {
                // start the table HTML here, include data as rows, add a blank row to enter as new
                $.each(v, function(i, value) {
                    if (k == 0) {
                        tmpTxt1 += value + ', ';
                    } else {
                        tmpTxt2 += value + ', ';
                    }
                });
            });
            AjaxRespELEM.text(tmpTxt1);
            $("<div>").appendTo(AjaxRespELEM);
            AjaxRespELEM.find('div').text(tmpTxt2);
            loaded.title.val(resp[0].title);
        },
    });
});
HTML;
    }

    public function cssContent()
    {
        return <<<HTML
.label-grey {
    color: grey;
    padding: 0px;
}
HTML;
    }

}
WebDispatch::conditionalExec();
