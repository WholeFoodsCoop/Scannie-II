<?php
include(__DIR__.'/../../config.php');
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
/*
**  @class InUseDateTask 
*/
class InUseDateTask extends PageLayoutA
{

    protected $must_authenticate = true;

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();
        $this->__routes[] = 'get<test>';
        $this->__routes[] = 'post<updateInUseDate>';

        return parent::preprocess();
    }

    public function getTestView()
    {
        return <<<HTML
Hello, GET routing test successful.
<div><button onclick="location.href = 'InUseDateTask.php';">RELOAD test</button></div>
HTML;
    }

    public function postUpdateInUseDateView()
    {
        $dbc = scanLib::getConObj();
        $upcs = array();
        $ret = "";
        $d = new DateTime();
        $curDate = $d->format('Y-m-d');

        $prp = $dbc->prepare("SELECT MAX(date) AS maxDate FROM woodshed_no_replicate.inUseDate");
        $res = $dbc->execute($prp);
        $row = $dbc->fetchRow($res);
        $maxDate = $row['maxDate'];
        $ret .= "<div> Task Last Run On: <strong>$curDate</strong></div>";

        if ($curDate == $maxDate) {
            $ret .= "<div class=\"alert alert-warning\">Cannot run task again today</div>";
        } else {
            $ret .= "<div class=\"alert alert-success\">Task Complete</div>";
            $prp = $dbc->prepare("
                SELECT p.upc, p.store_id, p.inUse 
                FROM is4c_op.products AS p 
                LEFT JOIN woodshed_no_replicate.inUseDate AS d ON p.upc=d.upc AND p.store_ID=d.storeID
                WHERE p.inUse <> d.inUse");
            $res = $dbc->execute($prp);
            while ($row = $dbc->fetchRow($res)) {
                $upc = $row['upc'];
                $storeID = $row['store_id'];
                $inUse = $row['inUse'];
                $upcs[$upc][$storeID] = $inUse;
            }
            foreach ($upcs as $upc => $row) {
                foreach ($row as $storeID => $inUse) {
                    $ret .= "<div>CHANGED: [$upc][$storeID] $inUse</div>";
                }
            }

            $prp = $dbc->prepare("
                UPDATE woodshed_no_replicate.inUseDate AS d 
                JOIN is4c_op.products AS p ON d.upc=p.upc AND d.storeID=p.store_id
                SET d.inUse = p.inUse,
                    d.date = NOW(),
                    d.storeID = p.store_id
                WHERE p.inUse <> d.inUse");
            $res = $dbc->execute($prp);
        }



        return <<<HTML
<div class="container" style="margin-top: 25px;">
    <div class="row">
        <div class="col-lg-4"></div>
        <div class="col-lg-4">
            <div>$ret</div>
            <div>
                <div><button class="btn btn-default" onclick="location.href = 'InUseDateTask.php';">Home</button></div>
            </div>
            <div class="col-lg-4"></div>
        </div>
    </div>
</div>
HTML;
    }

    public function pageContent()
    {
        $post = $_POST['test'];
        $get = $_GET['test'];
        return <<<HTML
<div class="container" style="margin-top: 25px;">
    <div class="row">
        <div class="col-lg-4"></div>
        <div class="col-lg-4">
            <p style="max-width: 250px;  text-align: justify;">
                This page updates the woodshed table inUseDate. Run this page one per day.
            </p>
            <div>
                <form method="post" action="InUseDateTask.php">
                    <button class="btn btn-default form-control" name="updateInUseDate" value="1">
                        Run inUseDate Task</button>
                </form>
            </div>
            $post
            $get
        </div>
        <div class="col-lg-4"></div>
    </div>
</div>
HTML;
    }

    public function cssContent()
    {
        return <<<HTML
button {
    width: 100px;
}
HTML;
    }

}
WebDispatch::conditionalExec();
