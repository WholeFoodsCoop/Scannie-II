<?php
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
class BulkReusePluReport extends PageLayoutA
{

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();

        return false;
    }

    public function pageContent()
    {
        $dbc = scanLib::getConObj();

        $data = array();
        $prep = $dbc->prepare("
            SELECT upc, brand, description,
            DATE(last_sold) AS last_sold,
            DATE(created) AS created
            FROM products AS p
                LEFT JOIN MasterSuperDepts AS m ON p.department=m.dept_ID
            WHERE upc > 99
                AND upc < 1000
                AND m.super_name = 'BULK'
        ");
        $res = $dbc->execute($prep);
        $table = "";
        while ($row = $dbc->fetchRow($res)) {
            if (!isset($data[$upc]['last_sold_array'])) {
                $data[$upc]['last_sold_array'] = array();
                $data[$upc]['last_sold'] = '';
            }
            $upc = $row['upc'];
            $data[$upc]['brand'] = $row['brand'];
            $data[$upc]['description'] = $row['description'];
            $data[$upc]['created'] = $row['created'];
            $data[$upc]['store'] = $row['store'];
            $last_sold = $row['last_sold'];
            $data[$upc]['last_sold_array'][] = $last_sold;
        }

        foreach ($data as $upc => $row) {
            $date1 = $row['last_sold_array'][0];
            $date2 = $row['last_sold_array'][1];

            $data[$upc]['last_sold'] = ($date1 > $date2) ? $row['last_sold_array'][0] : $row['last_sold_array'][1];
            //if ($upc == '0000000000281'){
            //    //var_dump($row);
            //    echo "<div>";
            //    echo "date1: $date1";
            //    echo "</div>";
            //    echo "<div>";
            //    echo "date2: $date2";
            //    echo "</div>";
            //    echo "<div>";
            //    echo "last_sold:" . $data[$upc]['last_sold'];
            //    echo "</div>";
            //}
        }

        function date_compare($a, $b)
        {
            $t1 = strtotime($a['last_sold']);
            $t2 = strtotime($b['last_sold']);

            return $t1 - $t2;
        }
        uasort($data, 'date_compare');

        foreach($data as $upc => $row) {
            $link = '<a class="upc" href="../../../../git/fannie/item/ItemEditorPage.php?searchupc='.$upc.
                '&ntype=UPC&searchBtn=" target="_blank">'.$upc.'</a>';
            $table .= sprintf("<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>",
                $link,
                $row['brand'],
                $row['description'],
                $data[$upc]['last_sold_array'][0],
                $data[$upc]['last_sold_array'][1],
                $row['created']
            );

        }
        echo $dbc->error();

        return <<<HTML
<div class="container-fluid" style="margin-top: 15px">
    <div class="row">
        <div class="col-lg-2"></div>
        <div class="col-lg-8">
            <h4 align=center style="padding: 25px"><i></i>CORE-POS Scale PLUs That May Be Reused</h4>
            <div class="table-responsive">
                <table class="table table-condensed table-bordered table-striped table-sm small" id="main-table">
                    <thead><th>upc</th><th>brand</th><th>description</th><th>last sold A</th><th>last sold B</th><th>created</th></thead>
                    <tbody>$table</tbody>
                </table>
            </div>
        </div>
        <div class="col-lg-2"> </div>
    </div>
</div>
HTML;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
JAVASCRIPT;
    }

    public function helpContent()
    {
        return <<<HTML
HTML;
    }

}
WebDispatch::conditionalExec();
