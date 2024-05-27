<?php
/*
    @class FixSchedule
    A one time initializer for the
    Scannie: Vendor Review Schedule
*/
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
class FixSchedule extends PageLayoutA
{

    protected $must_authenticate = true;
    protected $auth_types = array(2);

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();
        $this->routes[] = 'post<test>';

        return parent::preprocess();
    }

    public function pageContent()
    {

        $dbc = ScanLib::getConObj();
        $f = fopen("test.json", "r");
        $json = '';
        $ret = '';
        while ($line = fgets($f)) {
            $json .= $line;
        }
        $data = json_decode($json);
        //var_dump($data);
        
        $prep = $dbc->prepare("INSERT INTO woodshed_no_replicate.FixedVendorReviewSchedule
            (vendorID, month) VALUES (?, ?)");

        foreach ($data as $month => $row) {
            $ret .= "<div style='padding: 15px;'>$month";
            foreach ($row as $x => $y) {
                $name = $y->name;
                $color = '#'.substr(md5($name), 0, 6);
                $id = $y->id;
                $ret .= "<span style='color: $color; font-weight: bold'>$name, $id</span>";
                /*
                    run once to setup review schedule

                $args = array($id, $month);
                $res = $dbc->execute($prep, $args);
                if ($dbc->error()) 
                    $ret .= '<div class="alert alert-danger">'.$dbc->error().'</div>';
                */
            }
            $ret .= "</div>";
        }

        return <<<HTML
$ret
HTML;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
JAVASCRIPT;
    }

    public function cssContent()
    {
HTML;
    }

    public function helpContent()
    {
        return <<<HTML
HTML;
    }

}
WebDispatch::conditionalExec();
