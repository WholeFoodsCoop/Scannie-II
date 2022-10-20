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
        $this->__routes[] = 'post<quote>';
        $this->__routes[] = 'post<viewAllQuotes>';

        return parent::preprocess();
    }

    public function postViewAllQuotesView()
    {

        $dbc = scanLib::getConObj();
        $ret = '';
        $data = array();

        $args = array();
        $prep = $dbc->prepare("SELECT *, q.id AS qid FROM ref AS r LEFT JOIN quotes AS q ON q.bookID=r.id LEFT JOIN categories AS c ON c.quoteID=q.id");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $title = $row['title'];
            $author = $row['author'];
            $edition = $row['edition'];
            $copy = $row['ogCopyDate'];
            $text = $row['text'];
            $pages = $row['pages'];
            $qid = $row['qid'];
            //$ret .= "<div>$title, $author, $edition, $copy, $text</div>";
            $data[$author . ', ' . $title . ', ' . $edition . ', ' . $copy][$qid]['text'] = $text;
            $data[$author . ', ' . $title . ', ' . $edition . ', ' . $copy][$qid]['page'] = $pages;
        }
        //echo $dbc->error();

        foreach ($data as $work => $array) {
            $arr= explode(",", $work);
            $author = $arr[0] . ', ' . $arr[1];
            $title = $arr[2];
            $edition = $arr[3];
            $copy = $arr[4];
            $ret .= "<h5>$title</h5>";
            $ret .= "<h5>$author</h5>";
            $ret .= "<div>$edition, $copy</div>";
            foreach ($array as $qid => $row) {
                $ret .= "<p style=\"padding: 10px;\">{$row['text']}, ({$row['page']})</p>";
            }
        }

        return <<<HTML
<div class="row" style="padding-top: 25px; width: 100%;">
    <div class="col-lg-3"></div>
    <div class="col-lg-6">
        $ret
    </div>
    <div class="col-lg-3"></div>
</div>
HTML;
    }

    private function getCategories($dbc)
    {
        $cats = array();
        $prep = $dbc->prepare("SELECT name FROM categories GROUP BY name");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            $name = $row['name'];
            $hex = "#".substr(md5($name), 0, 6);
            $cats[$name] = $hex;
        }

        return $cats;
    }

    public function postQuoteHandler()
    {
        $dbc = scanLib::getConObj();
        $id = FormLib::get('id', false);
        $quote = FormLib::get('quote', false);
        $page = FormLib::get('page', false);
        $date = FormLib::get('date', false);
        $category = FormLib::get('category', false);
        if ($date == '') 
            $date = '1999-01-01';

        $args = array($id, $page, $quote, $date);
        $prep = $dbc->prepare("INSERT INTO quotes (bookID, pages, text, dateref)
            VALUES (?, ?, ?, ?)");
        $res = $dbc->execute($prep, $args);
        $ret = ($dbc->error()) ? $dbc->error() : 'success';

        if ($category != '' && $category != null) {
            $args = array($id, $category);
            $prep = $dbc->prepare("INSERT INTO categories (bookID, name)
                VALUES (?, ?)");
            $res = $dbc->execute($prep, $args);
            $ret .= ($dbc->error()) ? ' | '.$dbc->error() : ' | success';
        }

        
        return header("location: Sources.php?status=$ret");
    }

    private function getAddNewSource()
    {
        return <<<HTML
        <div class="light-bkg">
        <h4>Add New Source</h4>
        <div align="" style="padding: 15px">
        <form name="newSource" action="Sources.php" method="post">
            <label class="label-grey">&#9608; Title</label class="label-grey">
            <div class="form-group">
                <input name="newTitle" id="new-source" type="text" class="form-control" />
            </div>
            <label class="label-grey">&#9608; Author (Last, First)</label class="label-grey">
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
        </div>
    </div>
HTML;
    }

    private function getCatDatalist($dbc)
    {
        $data = array();
        $prep = $dbc->prepare("SELECT name FROM categories GROUP BY name");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            $data[] = $row['name'];
        }

        return $data;
    }

    private function getSourceForm()
    {
        $ret = '';
        $dbc = scanLib::getConObj();
        $selOpts = "<select name=\"bookID\" id=\"select-bookID\" class=\"form-control\">
            <option value=\"null\">Choose a Source</option>";
        $sources = array();

        $cols = array('title', 'author', 'edition', 'ogCopyDate');
        $prep = $dbc->prepare("SELECT * FROM ref ORDER BY author, title");
        $res = $dbc->execute($prep); 
        while ($row = $dbc->fetchRow($res)) {
            if (!array_key_exists($row['title'].', '.$row['author'].', '.$row['edition'], $sources)) {
                $sources[$row['author'].', '.$row['title'].', '.$row['edition']] = $row['id'];
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
        <form name="bookSelectForm" action="Sources.php" method="post">
            <h4>Select a Source</h4>
            <div class="form-group">
                $selOpts
            </div>
            <!--
            <div class="form-group">
                <input type="submit" class="btn btn-default"/>
            </div>
            -->
        </form>
HTML;
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

    public function postBookIDView()
    {
        $dbc = scanLib::getConObj();
        $bookID = FormLib::get('bookID', false);
        $quotes = array();
        $td = '';
        $catData = '<datalist id="categories">';

        $categories = $this->getCatDatalist($dbc);
        foreach ($categories as $category) {
            $catData .= "<option value=\"$category\">";
        }
        $catData .= '</datalist>';

        $args = array($bookID);
        $prep = $dbc->prepare("SELECT * FROM ref WHERE id = ?");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $title = $row['title'];
            $author = $row['author'];
            $edition = $row['edition'];
            $copy = $row['ogCopyDate'];
        }

        $args = array($bookID);
        $prep = $dbc->prepare("SELECT id, text, pages, DATE(dateref) AS dateref FROM quotes WHERE bookID = ?");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $quotes[] = array(
                'id' => $row['id'],
                'text' => $row['text'],
                'pages' => $row['pages'],
                'dateref' => $row['dateref']
            );
        }

        foreach ($quotes as $k => $row) {
            $td .= "<tr data-bookID=\"{$row['id']}\">";
            $td .= "<td contentEditable=true>{$row['text']}</td>";
            $td .= "<td contentEditable=true>{$row['pages']}</td>";
            //$td .= "<td contentEditable=true style=\"font-size: 12px;\">{$row['dateref']}</td>";
            $td .= "</tr>";
        }

        return <<<HTML
<div align="center" style="padding-top: 14px;">
</div>
<div class="row" style="width:100%; padding: 15px;">
    <div class="col-lg-2"></div>
    <div class="col-lg-8">
        <div align="right">
            <a href="#" onclick="window.location.href = 'Sources.php'" style="font-size: 20px;">Home</a>
        </div>
        <div class="clear-bkg">
            {$this->getSourceForm()}
        </div>
        <div style="background-color: #F5F5F5; padding: 15px; border: 1px solid lightgrey;">
            <h4>$title<h4>
            <h5>$author</h5>
            <div>$edition</div>
            <div>$copy</div>
        </div>
        <table class="table">
            <thead><th>Quote/Reference Text</th><th>Page(s)</th></thead>
            <tbody>$td</tbody></table>
        <div style="background-color: #F5F5F5; padding: 15px; border: 1px solid lightgrey;">
            <h5>Add New Quote For This Source</h5>
            <form action="Sources.php" method="post">
                <label class="label-grey">&#9608; Text</label class="label-grey">
                <div class="form-group">
                    <textarea name="quote" type="text" class="form-control" rows="6"></textarea>
                </div>
                <label class="label-grey">&#9608; Page(s)</label class="label-grey">
                <div class="form-group">
                    <input name="page" type="text" class="form-control" />
                </div>
                <label class="label-grey">&#9608; Date Accessed</label class="label-grey">
                <div class="form-group">
                    <input name="date" type="text" class="form-control" />
                </div>
                <label class="label-grey">&#9608; Category</label class="label-grey">
                <div class="form-group">
                    <input name="category" list="categories" type="text" class="form-control" />
                    $catData
                </div>
                <input type="hidden" name="id" value="$bookID" />
                <div class="form-group">
                    <input type="submit" class="btn btn-default" />
                </div>
            </form>
        </div>
    </div>
    <div class="col-lg-2">
    </div>
</div>
HTML;
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
        $dbc = scanLib::getConObj();
        $catStr = '';
        $categories = $this->getCategories($dbc);
        foreach ($categories as $name => $hex) {
            $catStr .= "<div><span style=\"color: $hex; font-weight: bold;\">$name</span></div>";
        }


        return <<<HTML
<div align="center" style="padding-top: 14px;">
</div>
<div class="row" style="width: 100%; padding: 15px;">
    <div class="col-lg-2"></div>
    <div class="col-lg-4">
        <div class="light-bkg">
            {$this->getSourceForm()}
        </div>
        <div class="light-bkg" style="margin-top: 15px;">
            <h4>Flags In Use</h4>
            <p>$catStr</p>
        </div>
        <div class="clear-bkg" style="margin-top: 15px;">
            <ul>
                <li><a href="#" onclick="document.forms['viewAllQuotesForm'].submit();" style="cursor: pointer; color: #6495ED ; font-size: 20px;">View All Quotes (read-only)</a></li>
            </ul>
            <form action="Sources.php" method="post" name="viewAllQuotesForm">
                <input type="hidden" name="viewAllQuotes" value="1" />
            </form>
        </div>
        <div id="ajax-resp-body"></div>
    </div>
    <div class="col-lg-4">
        {$this->getAddNewSource()}
    </div>
    <div class="col-lg-2"></div>
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
        return <<<JAVASCRIPT
$('#select-bookID').on('change', function(){
    document.forms['bookSelectForm'].submit();
});
JAVASCRIPT;
    }

    public function cssContent()
    {
        return <<<HTML
.label-grey {
    color: grey;
    padding: 0px;
}
.black {
    padding: 15px;
    bottom-border: 1px solid lightgrey;
}
body {
    background-image: url('https://lildoodlecloud.com/Research/common/src/img/white-wall-3.png');
    background-repeat: repeat;
}
input, textarea, form-control, select {
    //background-color:rgba(255,255,255,0.3) !important;
}
table, tr, td, th {
    //background-color: rgba(250, 150, 55, 0.05);
}
.light-bkg {
    background-color: rgba(255,255,255,0.3); border: 1px solid lightgrey; padding: 14px;
    padding: 15px;
}
.clear-bkg {
    padding: 15px;
}
HTML;
    }

}
WebDispatch::conditionalExec();
