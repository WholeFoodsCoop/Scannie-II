<?php
include(__DIR__.'/../../config.php');
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
if (!class_exists('scanLib')) {
    include_once(__DIR__.'/../../common/lib/scanLib.php');
}
/*
**  @class DeliQtySoldCheck 
*/
class DeliQtySoldCheck extends PageLayoutA
{

    public function body_content()
    {
        $dbc = scanLib::getConObj();
        $data = array();
        $dataCols = array('upc', 'POS_Brand', 'POS_Description', 'created', 'superID', 'super_name', 'store', 'Sales', 'Quantity');
        $mode = FormLib::get('mode', 'New');

        $radioArr = array('New', 'Returning', 'Both');
        $radioBtn = '';
        foreach ($radioArr as $k => $v) {
            $checked = ($mode == $v) ? 'checked' : '';
            $radioBtn .= "
                <input type=\"radio\" id=\"r$v\" name=\"mode\" value=\"$v\" $checked />
                <label for=\"r$v\">$v</label>
            ";
        }

        $date1 = FormLib::get('date1', '2022-01-01');
        $date2 = FormLib::get('date2', '2022-01-01');
        $storeID = FormLib::get('store', 2);

        if ($mode == 'Returning' || $mode == 'Both') {
            $list = $this->getNewOrReturningItems($storeID, $date1, $date2);
            $addData = $this->getAltListData($list, $date1, $date2, $storeID);
        }

        $upcs = array();
        $allP = $dbc->prepare("SELECT upc FROM products AS p  LEFT JOIN MasterSuperDepts AS m 
            ON m.dept_ID=p.department WHERE m.superID = 3 and inUse = 1 GROUP BY upc;");
        $allR = $dbc->execute($allP);
        while ($row = $dbc->fetchRow($allR)) {
            $upcs[$row['upc']] = $row['upc'];
        }

        $args = array($date1, $date2, $date1, $date2, $storeID);
        $prep = $dbc->prepare("
            SELECT
            p.store_id, 
            p.upc,
            p.brand AS POS_Brand,
            p.description AS POS_Description,
            pu.brand AS SIGN_Bbrand,
            pu.description AS SIGN_description,
            date(p.created) AS created,
            m.superID,
            m.super_name,
            p.department,
            d.dept_name,
            CASE
                WHEN weight = 0 THEN 'Random'
                WHEN weight = 1 THEN 'Fixed'
                ELSE 'not in scale'
            END AS scale,
            CASE
                WHEN p.store_id = 1 THEN 'Hillside'
                ELSE 'Denfeld'
            END AS store,
            CASE
                WHEN SUM(dlog.quantity) > 0 THEN 'Yes'
                ELSE 'No'
            END AS Sales,
            ROUND(SUM(dlog.quantity),1) as Quantity
            FROM products AS p
                LEFT JOIN departments AS d ON d.dept_no=p.department
                LEFT JOIN MasterSuperDepts AS m ON p.department=m.dept_ID
                LEFT JOIN productUser AS pu ON p.upc=pu.upc
                LEFT JOIN scaleItems AS s ON s.plu=p.upc
                LEFT JOIN is4c_trans.dlog_90_view AS dlog ON p.upc=dlog.upc
                    AND dlog.store_id=p.store_id
                    AND dlog.tdate >= ? AND dlog.tdate <= ? 
            WHERE p.created BETWEEN ? AND ?
                AND m.superID IN (3)
                AND p.store_id = ?
            GROUP BY p.upc, p.store_id
            ORDER BY p.department, p.brand
        ");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $upc = $row['upc'];
            if ($row['scale'] == "Random") {
                $qty = $this->getWeightQty($upc, $date1, $date2, $storeID);
            } else {
                $qty = $row['Quantity'];
            }

            $today = new DateTime();
            $created = new DateTime($row['created']);
            $days = $created->diff($today);

            $data[] = array(
                'upc' => $upc, 
                'store' => $row['store'],
                'brand' => $row['POS_Brand'],
                'description' => $row['POS_Description'],
                'created' => $row['created'],
                'superID' => $days->format('%R%a days'),
                'super_name' => $row['department'] . ' ' . $row['dept_name'],
                'store' => $row['store'],
                'Sales' => $row['Sales'],
                'Quantity' => $qty + 0
            );

        }

        echo $dbc->error();

        if ($mode == 'New') {
            // do nothing, $data == $data
        } elseif ($mode == 'Returning') {
            $data = $addData;
        } elseif ($mode == 'Both') {
            $data = array_merge($data, $addData);
        }

        $td = '';
        foreach ($data as $i => $row) {
            if ($row['Quantity'] <= 1 && $row['created'] == 'n/a') {
                // do nothing. Don't show return items with sales == 1 or less
            } else {
                $trs = ($row['Sales'] == 'No') ? 'style="background-color: #F0F0F0"' : '';
                $td .= "<tr $trs>";
                foreach ($row as $v) {
                    $td .= "<td>$v</td>";
                }
                $td .= "</tr>";
            }
        }

        $this->addScript('../../common/javascript/tablesorter/js/jquery.tablesorter.min.js');
        $this->addScript('../../common/javascript/tablesorter/js/jquery.metadata.js');
        $this->addOnloadCommand("$('#mytable').tablesorter();");

        $storeOpt = '';
        $stores = array(1=>'Hillside', 2=>'Denfeld');
        foreach ($stores as $i => $id) {
            $sel = ($i == $storeID) ? ' selected ' : '';
            $storeOpt .= "<option value=\"$i\" $sel>$id</option>";
        }

        return <<<HTML
<br/>
<div class="container-fluid">
    <h4>New Deli Products Report<h4>
    <div class="row">
        <div class="col-lg-4">
            <form action="DeliQtySoldCheck.php" method="get">
                <div class="form-group">
                    <input type="date" name="date1" value="$date1" class="form-control"/>
                </div>
                <div class="form-group">
                    <input type="date" name="date2" value="$date2" class="form-control"/>
                </div>
                <div class="form-group">
                    <select name="store" class="form-control">$storeOpt</select>
                </div>
                <div class="form-group">
                    <p style="font-size: 14px;">
                        <strong>New</strong> = Show only new items</br>
                        <strong>Returning</strong> = Show only items with creation dates prior to given date range with sales during given date range</br>
                        <strong>Both</strong> = Show both New & Returning items</br>
                    </p>
                    $radioBtn
                </div>
                <div class="form-group">
                    <input type="submit" class="btn btn-default"/>
                </div>
            </form>
        </div>
        <div class="col-lg-6">
            <p style="font-size: 14px;">
                <strong>New Deli Products Report</strong> Find new products sold in the given timeline. 
            </p>
            <p style="font-size: 14px;">
                This report will only work for the recent previous month.
            </p>
        </div>
        <div class="col-lg-2"></div>
    </div>
</div>
<div class="container-fluid">
        <table id="mytable" class="table table-bordered small">
            <thead class="small">
                <th>upc <img src="../../common/src/img/icons/tablesorter.png" height="10px;"/></th>
                <th>Store <img src="../../common/src/img/icons/tablesorter.png" height="10px;"/></th>
                <th>Brand <img src="../../common/src/img/icons/tablesorter.png" height="10px;"/></th>
                <th>Description <img src="../../common/src/img/icons/tablesorter.png" height="10px;"/></th>
                <th>Created <img src="../../common/src/img/icons/tablesorter.png" height="10px;"/></th>
                <th>Since Created <img src="../../common/src/img/icons/tablesorter.png" height="10px;"/></th>
                <th>Dept. <img src="../../common/src/img/icons/tablesorter.png" height="10px;"/></th>
                <th>Sales <img src="../../common/src/img/icons/tablesorter.png" height="10px;"/></th>
                <th>Qty. Sold <img src="../../common/src/img/icons/tablesorter.png" height="10px;"/></th>
            </thead>
            <tbody>$td</tbody>
        </table>
</div>
HTML;
    }

    // call this method once for every "random weight" scale item.
    public function getWeightQty($upc, $date1, $date2, $storeID)
    {
        $dbc = scanLib::getConObj();

        $prep = $dbc->prepare("
            SELECT
            upc,
            date(tdate) AS date,
            quantity, scale, unitPrice, 
            regPrice, total
            FROM is4c_trans.dlog_90_view
            WHERE upc = ? 
            AND tdate >= ?
            AND tdate <= ?
            AND store_id = ?
        ");
        $res = $dbc->execute($prep, array($upc, $date1, $date2, $storeID));
        $rows = array();
        while ($row = $dbc->fetchRow($res)) {
            $rows[] = $row;
        }

        $args = array($upc, $date);
        $prep = $dbc->prepare("SELECT price FROM prodUpdate WHERE upc = ? AND DATE(modified) <= ? ORDER BY modified DESC limit 1;");

        $qtySold = 0;
        foreach ($rows as $row) {
            $args = array($row['upc'], $row['date']);
            $res = $dbc->execute($prep, $args);
            $price = $dbc->fetchRow($res);
            $price = $price['price'];
            $total = $row['total'];
            $qty = $total / $price;
            $qty = round($qty, 2);
            $qtySold += $qty;
        }

        return $qtySold;
    }

    function getNewOrReturningItems($storeID, $date1, $date2)
    {
        $dbc = scanLib::getConObj();
        $date1 = new DateTime($date1);
        $date2 = new DateTime($date2);

        // get all deli items with sales during month selected at $storeID 
        $curUPCS = array();
        $curA = array($date1->format('Y-m-d'), $date2->format('Y-m-d'), $storeID);
        $curP = $dbc->prepare("SELECT 
            upc, description, SUM(quantity) As qty
            FROM is4c_trans.dlog_90_view AS d
                LEFT JOIN MasterSuperDepts AS m ON m.dept_ID=d.department
            WHERE m.superID = 3
                AND d.tdate >= ? 
                AND d.tdate < ?
                AND upc REGEXP '^[0-9]+$' = 1
                AND upc > 0
                AND store_id = ?
                AND quantity > 0
            GROUP BY d.upc;");
        $curR = $dbc->execute($curP, $curA);
        while ($row = $dbc->fetchRow($curR)) {
            $upc = $row['upc'];
            $qty = $row['qty'];
            if ($qty > 0) {
                $curUPCS[] = $upc;
            }
        }

        $date1->modify('first day of -1 month');
        $date2->modify('last day of -1 month');

        // get all deli items with sales during month prior to selected month, at $storeID 
        $prevUPCS = array();
        $prevA = array($date1->format('Y-m-d'), $date2->format('Y-m-d'), $storeID);
        $prevP = $dbc->prepare("SELECT 
            upc, description
            FROM is4c_trans.dlog_90_view AS d
                LEFT JOIN MasterSuperDepts AS m ON m.dept_ID=d.department
            WHERE m.superID = 3
                AND d.tdate >= ? 
                AND d.tdate < ?
                AND upc REGEXP '^[0-9]+$' = 1
                AND upc > 0
                AND store_id = ?
                AND quantity > 0
            GROUP BY d.upc;");
        $prevR = $dbc->execute($prevP, $prevA);
        while ($row = $dbc->fetchRow($prevR)) {
            $upc = $row['upc'];
            $prevUPCS[] = $upc;
        }

        $list = array();
        foreach ($curUPCS as $upc) {
            if (!in_array($upc, $prevUPCS)) {
                $list[] = $upc;
            }
        }

        return $list;
    }

    private function getAltListData($list, $date1, $date2, $storeID)
    {
        $dbc = scanLib::getConObj();
        $data = array();

        $args = array($date1, $date2, $storeID, $date1);
        list($inStr, $addArgs) = $dbc->safeInClause($list);
        $args = array_merge($args, $addArgs);
        $prep = $dbc->prepare("
            SELECT
            p.store_id, 
            p.upc,
            p.brand AS POS_Brand,
            p.description AS POS_Description,
            pu.brand AS SIGN_Bbrand,
            pu.description AS SIGN_description,
            date(p.created) AS created,
            m.superID,
            m.super_name,
            p.department,
            d.dept_name,
            CASE
                WHEN weight = 0 THEN 'Random'
                WHEN weight = 1 THEN 'Fixed'
                ELSE 'not in scale'
            END AS scale,
            CASE
                WHEN p.store_id = 1 THEN 'Hillside'
                ELSE 'Denfeld'
            END AS store,
            CASE
                WHEN SUM(dlog.quantity) > 0 THEN 'Yes'
                ELSE 'No'
            END AS Sales,
            ROUND(SUM(dlog.quantity),1) as Quantity
            FROM products AS p
                LEFT JOIN departments AS d ON d.dept_no=p.department
                LEFT JOIN MasterSuperDepts AS m ON p.department=m.dept_ID
                LEFT JOIN productUser AS pu ON p.upc=pu.upc
                LEFT JOIN scaleItems AS s ON s.plu=p.upc
                LEFT JOIN is4c_trans.dlog_90_view AS dlog ON p.upc=dlog.upc
                    AND dlog.store_id=p.store_id
                    AND dlog.tdate >= ? AND dlog.tdate <= ? 
            WHERE m.superID IN (3)
                AND p.store_id = ?
                AND p.created < ?
                AND p.upc IN ($inStr)
            GROUP BY p.upc, p.store_id
            ORDER BY p.department, p.brand
        ");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $upc = $row['upc'];
            if ($row['scale'] == "Random") {
                $qty = $this->getWeightQty($upc, $date1, $date2, $storeID);
            } else {
                $qty = $row['Quantity'];
            }

            if ($row['Sales'] == 'Yes') {
                $data[] = array(
                    'upc' => $upc, 
                    'store' => $row['store'],
                    'brand' => $row['POS_Brand'],
                    'description' => $row['POS_Description'],
                    'created' => 'n/a',
                    'superID' => 'n/a',
                    'super_name' => $row['department'] . ' ' . $row['dept_name'],
                    'store' => $row['store'],
                    'Sales' => $row['Sales'],
                    'Quantity' => $qty + 0
                );
            }
        }

        return $data;
    }


}

WebDispatch::conditionalExec();
