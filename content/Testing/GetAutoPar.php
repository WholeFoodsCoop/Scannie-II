<?php
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
class GetAutoPar
{

    private function GrabAutoPar($date, $storeID, $upc)
    {
        $upc = scanLib::padUPC($upc);
        $newArr = array(); 
        $ret = '';
        $sum = 0;
        $pars = array();
        $counts = array(0=>0, 1=>0, 2=>0);
        $dbc = scanLib::getConObj();
        $date1 = null;
        $date2 = null;

        $args = array($date, $date, $upc, $storeID, $date, $date, $date, $date);
        $prep = $dbc->prepare("
SELECT upc, SUM(quantity) AS sum,
MONTH(?) as month,
YEAR(?) as year,
discountType
FROM trans_archive.bigArchive
WHERE upc = ?
AND store_id = ? 
AND datetime >= CONCAT(YEAR(?), '-', MONTH(?), '-01')
AND datetime <= CONCAT(YEAR(?), '-', MONTH(?), '-31')
            ");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $date = $row['day'];
            $sum = $row['sum'];
        }

        return array(
            'date' => $date,
            'sum' => $sum
        );
    }

    public function run($date, $storeID, $upc)
    {
        $row = $this->GrabAutoPar($date, $storeID, $upc);

        return $row; 
    }

}
