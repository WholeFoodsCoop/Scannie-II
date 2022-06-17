<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Community Co-op.

    This file is a part of CORE-POS.

    CORE-POS is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    CORE-POS is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file LICENSE along with CORE-POS; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/
class Search
{

    protected $data = array();
    protected $pagelist = array();

    private function parser($input)
    {
        $dto = new DateTime();
        $today = $dto->format('Y-m-d');
        if (1) {
            $pages = array(
                'Item Editor UPC' => '/../../../../git/IS4C/fannie/item/ItemEditorPage.php?searchupc='.$input.'&ntype=UPC&searchBtn=',
                'Item Editor SKU' => '/../../../../git/IS4C/fannie/item/ItemEditorPage.php?searchupc='.$input.'&ntype=SKU&searchBtn=',
                //'Coop Deals Add' => '/../../../../git/IS4C/fannie/item/CoopDealsLookupPage.php?upc='.$input,
                'Track Change' => '/../Scannie/content/Item/TrackItemChange.php?upc='.$input,
                'Edit Batch Page' => '/../../../../git/IS4C/fannie/batches/newbatch/EditBatchPage.php?id='.$input,
                "Trnx Lookup" => "/../../../../git/fannie/admin/LookupReceipt/RenderReceiptPage.php?date=$today&receipt=$input",
                //'Item Batch History' => 'http://'.$FANNIEROOT_DIR.'/reports/ItemBatches/ItemBatchesReport.php?upc='.$input,
                //'Batch Review' => 'http://'.$SCANROOT_DIR.'/item/Batches/BatchReview/BatchReviewPage.php?id='.$input,
                //'Unfi_DB_Check' => 'https://customers.unfi.com/Pages/ProductSearch.aspx?SearchTerm='.$input
            );
            foreach ($pages as $name => $path) {
                $this->data[$name] = $path;
            }
        } else {
            $this->getList();
        }

        return false;
    }

    public function run()
    {
        $s = $_GET['search'];
        $ret = $this->parser($s);

        foreach ($this->data as $name => $path) {
            //if ( (strstr($name,$s) || strstr($name,ucwords($s))) && strlen($s) > 2 || is_numeric($s) ) {
            if (1) {
                $ret .= (is_numeric($s)) ? '<a class="search-resp" href="'.$path.'">' : '<a class="search-resp" href="'.$path.$name.'">';
                $replace = '<b>'.$s.'</b>';
                $newstring = str_replace($s,$replace,$name);
                $ret .= $newstring;
                $ret .= '</a><br />';
            }
        }
        return <<<HTML
<u style="color: #cacaca; text-decoration: none;font-weight: bold;">
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
    </u><br />
<div>{$ret}</div>
HTML;
    }

}
if (isset($_GET['search'])) {
    if ($_GET['search']) {
        $obj = new search();
        echo $obj->run();
    }
}
