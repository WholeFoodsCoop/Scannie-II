<?php
include(__DIR__.'/../../config.php');
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
/*
**  @class SushiSales 
*/
class SushiSales extends PageLayoutA
{

    protected $must_authenticate = false;

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();
        $this->__routes[] = 'get<test>';
        $this->__routes[] = 'post<test>';

        return parent::preprocess();
    }

    public function pageContent()
    {

        $ret = '';
        $dbc = scanLib::getConObj();
        $stores = array(1, 2);
        $dates = array();
        $days = array('Friday', 'Saturday', 'Sunday');

        $lastFriday = date('Y-m-d', strtotime('last Friday'));
        $lastSaturday = date('Y-m-d', strtotime('last Saturday'));
        $lastSunday = date('Y-m-d', strtotime('last Sunday'));
        $dates[] = $lastFriday;
        $dates[] = $lastSaturday;
        $dates[] = $lastSunday;

        $tables = array();

        $prep = $dbc->prepare("
            SELECT
            store_id,
            CASE
             WHEN store_id=1 THEN 'HILLSIDE' ELSE 'DENFELD'
            END AS store,
            date(tdate),
            upc,
            description,
            sum(ItemQtty) AS qtySold,
            ROUND(sum(total), 2) AS total,
            trans_num,
            trans_status
            FROM is4c_trans.dlog_15
            WHERE department = 276
                AND DATE(tdate) = ? 
                AND store_id = ?
            GROUP BY upc, store_id
            ORDER BY store_id, SUM(ItemQtty) DESC"); 
        $res = $dbc->execute($prep, array("2026-08-15", 1));
        while ($row = $dbc->fetchRow($res)) {
            $store = $row['store'];
            $description = $row['description'];
            $qtySold = $row['qtySold'];
            $total = $row['total'];
        }

        foreach ($dates as $i => $date) {
            foreach ($stores as $j => $storeID) {
                $res = $dbc->execute($prep, array($date, $storeID));
                    while ($row = $dbc->fetchRow($res)) {
                        $store = $row['store'];
                        $description = $row['description'];
                        $qtySold = $row['qtySold'];
                        $total = $row['total'];
                        $tables[$date][$store][] = array($description, $qtySold, $total, $date);
                    }
            }
        }

        $ttls = array( 0 => 0, 1 => 0);
        $tds = array();
        $thead = "<th>Item</th><th>Qty Sold</th><th>Sales</th><th>Date</th>";
        $tableHTML = "<table class=\"table table-bordered table-sm\"><thead>$thead</thead><tbody>";
        $tableEnd = '</tbody></table>';
        foreach ($tables as $date => $table) {
            foreach ($table as $storeID => $row) {
                $tds[$date][$storeID] = $tableHTML;
                foreach ($row as $item) {
                    $tds[$date][$storeID] .= "<tr>";
                    $tds[$date][$storeID] .= "<td>{$item[0]}</td>";
                    $tds[$date][$storeID] .= "<td>{$item[1]}</td>";
                    $tds[$date][$storeID] .= "<td>{$item[2]}</td>";
                    $tds[$date][$storeID] .= "<td>{$item[3]}</td>";
                    $tds[$date][$storeID] .= "</tr>";
                    $ttls[0] += $item[1];
                    $ttls[1] += $item[2];
                }
                $tds[$date][$storeID] .= "<tr>";
                $tds[$date][$storeID] .= "<td><strong>Total</strong></td>";
                $tds[$date][$storeID] .= "<td><strong>{$ttls[0]}</strong></td>";
                $tds[$date][$storeID] .= "<td><strong>{$ttls[1]}</strong></td>";
                $tds[$date][$storeID] .= "</tr>";

                $ttls[0] = 0;
                $ttls[1] = 1;
                $tds[$date][$storeID] .= $tableEnd;

            }
        }

        return <<<HTML
<div align="center" style="padding-top: 25px;">
    <table><tbody>
        <tr> <td><div style="border-bottom: 1px solid grey; padding: 10px;"></div></td> <td><div style="border-bottom: 1px solid grey; padding: 10px;"></div></td> </tr>
        <tr>
            <tr><td><h4>{$days[0]} {$dates[0]}</h4></td></tr>
            <td>
                <h4>Hillside</h4>
                {$tds[$dates[0]]['HILLSIDE']}
            </td>
            <td>
                <h4>Denfeld</h4>
                {$tds[$dates[0]]['DENFELD']}
            </td>
        </tr>
        <tr> <td><div style="border-bottom: 1px solid grey; padding: 10px;"></div></td> <td><div style="border-bottom: 1px solid grey; padding: 10px;"></div></td> </tr>
        <tr>
            <tr><td><h4>{$days[1]} {$dates[1]}</h4></td></tr>
            <td>
                <h4>Hillside</h4>
                {$tds[$dates[1]]['HILLSIDE']}
            </td>
            <td>
                <h4>Denfeld</h4>
                {$tds[$dates[1]]['DENFELD']}
            </td>
        </tr>
        <tr> <td><div style="border-bottom: 1px solid grey; padding: 10px;"></div></td> <td><div style="border-bottom: 1px solid grey; padding: 10px;"></div></td> </tr>
        <tr>
            <tr><td><h4>{$days[2]} {$dates[2]}</h4></td></tr>
            <td>
                <h4>Hillside</h4>
                {$tds[$dates[2]]['HILLSIDE']}
            </td>
            <td>
                <h4>Denfeld</h4>
                {$tds[$dates[2]]['DENFELD']}
            </td>
        </tr>
    </tbody></table>
</div>
HTML;
    }

    public function cssContent()
    {
        return <<<HTML
HTML;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
JAVASCRIPT;
    }

}
WebDispatch::conditionalExec();
