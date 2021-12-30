<?php
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
class DeliReusePluReport extends PageLayoutA
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
            SELECT upc, brand, description, DATE(last_sold) AS last_sold, DATE(created) AS created, 
                CASE WHEN store_id = 1 THEN 'Hillside' ELSE 'Denfeld' END AS store
            FROM products AS p
                LEFT JOIN MasterSuperDepts AS m ON p.department=m.dept_ID
            WHERE upc > 20001000000 
                AND upc < 29999000000
                AND upc LIKE '%00000'
                AND created < '2020-01-01'
                AND m.super_name = 'DELI'
        ");
        $res = $dbc->execute($prep);
        $table = "";
        while ($row = $dbc->fetchRow($res)) {
            $upc = $row['upc'];
            $data[$upc]['brand'] = $row['brand'];
            $data[$upc]['description'] = $row['description'];
            $data[$upc]['created'] = $row['created'];
            $data[$upc]['store'] = $row['store'];
            $last_sold = $row['last_sold'];
            if (!isset($data[$upc]['last_sold'])) {
                $data[$upc]['last_sold'] = $row['last_sold'];
            } else {
                $d1 = strtotime($row['last_sold']);
                $d2 = strtotime($data[$upc]['last_sold']);
                if ($d1 > $d2) {
                    $data[$upc]['last_sold'] = $last_sold;
                }
            }
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
            $table .= sprintf("<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>",
                $link,
                $row['brand'],
                $row['description'],
                $row['last_sold'],
                $row['created']
            );

        }
        echo $dbc->error();

        return <<<HTML
<div class="container-fluid" style="margin-top: 15px">
    <div class="row">
        <div class="col-lg-2"></div>
        <div class="col-lg-8">
            <h4 align=center style="padding: 25px"><i><strong>Dani's</strong></i> Deli Scale PLUSE to REUSE... page</h4>
            <div class="table-responsive">
                <table class="table table-condensed table-bordered table-striped table-sm small" id="main-table">
                    <thead><th>upc</th><th>brand</th><th>description</th><th>last sold</th><th>created</th></thead>
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
