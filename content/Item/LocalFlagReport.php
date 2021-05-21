<?php
include(__DIR__.'/../../config.php');
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
/*
**  @class LocalFlagReport 
*/
class LocalFlagReport extends PageLayoutA
{

    protected $must_authenticate = true;
    protected $locals = array(0=>'not local', 1=>'SC', 2=>'MN/WI');

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();
        $this->__routes[] = 'post<brand>';

        return parent::preprocess();
    }

    public function postBrandHandler()
    {
        $brand = FormLib::get('brand');
        $dbc = ScanLib::getConObj();

        $args = array($brand);
        $prep = $dbc->prepare("SELECT upc, brand, description, local 
            FROM products 
                LEFT JOIN MasterSuperDepts AS m ON department=m.dept_ID
            WHERE brand = ?  
                AND m.superID IN (1, 3, 4, 5, 8, 9,13,17,18)
                AND default_vendor_id > 0
            GROUP BY upc ORDER BY local");
        $res = $dbc->execute($prep, $args);
        echo $dbc->error();
        $td = '';
        while ($row = $dbc->fetchRow($res)) {
            $td .= "<tr>";
            $td .= "<td>{$row['upc']}</td>";
            $td .= "<td>{$row['brand']}</td>";
            $td .= "<td>{$row['description']}</td>";
            $td .= "<td>{$row['local']}</td>";
            $td .= "</tr>";
        }
        echo <<<HTML
<table class="table table-bordered table-condensed small">$td</table>
HTML;

        return false;
    }

    public function pageContent()
    {
        $dbc = ScanLib::getConObj();
        $brands = array();
        $td = '';

        $prep = $dbc->prepare("SELECT brand, local 
            FROM products 
                    LEFT JOIN MasterSuperDepts AS m ON department=m.dept_ID
            WHERE brand IS NOT NULL 
                AND m.superID IN (1, 3, 4, 5, 8, 9,13,17,18)
                AND brand != '' 
                AND default_vendor_id > 0
                AND brand != 'BULK'
            GROUP BY brand, local");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            $brand = $row['brand'];
            $local = (isset($row['local'])) ? $row['local'] : null;
            if ($local >= 0) {
                $brands[$row['brand']][] = $local;
            }
        }
        foreach ($brands as $brand => $local) {
            if (count($local) > 1) {
                $td .= "<tr>";
                $td .= "<td><strong class=\"brand\">$brand</strong></td>";
                foreach ($local as $num) {
                    $td .= "<td>".$this->locals[$num]."</td>";
                }
                $td .= "</tr>";
            }
        }

        return <<<HTML
<div class="row">

    <div class="col-lg-6">
        <div>Local Settings by Brand</div>
        <table class="table table-bordered table-sm small">$td</table>
    </div>
    <div class="col-lg-6">
        <div id="tables"></div>
    </div>
</div>
HTML;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
$('.brand').click(function(){
    let brand = $(this).text();
    $.ajax({
        type: 'post',
        url: 'LocalFlagReport.php',
        data: 'brand='+brand,
        success: function(resp) {
            $('#tables').html(resp);
        }
    });
});
JAVASCRIPT;
    }

    public function cssContent()
    {
        return <<<HTML
.line {
    padding-left: 5px; 
    padding-right: 5px; 
    border-right: 1px solid lightgrey;
}
.brand {
    cursor: pointer;
}
.col-lg-6 {
    padding :25px;
}
HTML;
    }

    public function helpContent()
    {
        return <<<HTML
<h4>Local Flag Report</h4>
<p>The table on the left side of the screen displays brands that contain
more than one <strong>local</strong> designation. Click on a brand name
to pull up a list of products with that brand name along with the numeric
local flags for each item.</p>

HTML;
    }

}
WebDispatch::conditionalExec();
