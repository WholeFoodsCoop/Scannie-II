<?php
/*******************************************************************************

    Copyright 2016 Whole Foods Community Co-op.

    This file is a part of Scannie.

    Scannie is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    Scannie is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file LICENSE along with Scannie; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
class SkuTrimmer extends PageLayoutA
{

    protected $title = "Sku Trimmer";
    protected $description = "[Sku Trimmer] remove superfluous skus from
        UNFI's vendorItems.";
    protected $ui = TRUE;

    public function body_content()
    {
        $ret = '';
        $dbc = scanLib::getConObj();
        $storeID = scanLib::getStoreID();
        $FANNIE_ROOTDIR = $this->config->vars['FANNIE_ROOTDIR'];

        $prep = $dbc->prepare("
           SELECT v.sku, v.upc, v.description, v.cost, v.modified 
           FROM vendorItems AS v 
           INNER JOIN (SELECT * FROM vendorItems WHERE vendorID = 1 GROUP BY upc HAVING COUNT(upc)>1) dup 
               ON v.upc = dup.upc 
                   WHERE v.vendorID=1; 
        ");
        $res = $dbc->execute($prep);
        $data = array();
        while ($row = $dbc->fetchRow($res)) {
            $data[$row['upc']][] = $row['sku'];
        }

        $removeSkus = array();
        foreach ($data as $upc => $skus) {
            $dateA = 0;
            $dateB = 0;
            $skuA = $skus[0];
            $skuB = $skus[1];
            $argsA = array($skus[0]);
            $argsB = array($skus[1]);
            $prep = $dbc->prepare("select receivedDate from PurchaseOrderItems where sku = ?
                order by receivedDate DESC
                LIMIT 1;");
            $value = $dbc->getValue($prep, $argsA);
            $dateA = new DateTime($value);
            $value = null;
            $value = $dbc->getValue($prep, $argsB);
            $dateB = new DateTime($value);
            if ($dateA > $dateB) {
                $removeSkus[$upc] = $skuA;
                $ret .= $sku;
            } elseif ($dateB > $dateA) {
                $removeSkus[$upc] = $skuB;
                $ret .= $sku;
            }
        }

        foreach ($removeSkus as $upc => $sku) {
            $args = array(1, $upc, $sku);
            $prep = $dbc->prepare("DELETE FROM vendorItems  
                WHERE vendorID = ? AND upc = ? AND sku = ?");
            $res = $dbc->execute($prep, $args);
        }
        $ret  .= ($ret == '') ? "<div class=\"alert alert-success\" align=\"center\">UNFI vendor items clear of superfluous skus.</div>" : '';

        return <<<HTML
$ret
HTML;
    }

}
WebDispatch::conditionalExec();
