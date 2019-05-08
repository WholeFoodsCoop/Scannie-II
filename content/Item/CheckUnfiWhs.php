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
class CheckUnfiWhs extends PageLayoutA 
{

    protected $title = "";
    protected $description = "[] ";
    protected $ui = TRUE;

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();

        return parent::preprocess();
    }

    public function pageContent()
    {
        $ret = '';
        include(__DIR__.'/../config.php');
        $dbc = scanLib::getConObj('SCANALTDB');

        $sku = FormLib::get('sku');
        $cols = array('Product','Brand', 'Pack', 'Size',  'Description',  'APPRV', 'RegCost', 'RegUnit', 'NetCost', 'NetUnit', 'upc', 'WhsAvail');
        $thead = "";
        foreach ($cols as $col) {
            $thead .= "<th>$col</th>";
        }
        $t = "<div class=\"table-responsive\"><table class='table'><thead>$thead</thead><tbody>";
        $args = array($sku);
        $prep = $dbc->prepare("SELECT * FROM bulkUnfiWhs 
            WHERE WhsAvail like '%T%' AND Product = ?;");
        $result = $dbc->execute($prep,$args);
        $msg = "<div class=\"text-warning\">Product not available from Warehouse</div>";
        while ($row = $dbc->fetchRow($result)) {
            $msg = "<div class=\"text-success\">Product available from Warehouse</div>";
            $t .= "<tr>";
            foreach ($cols as $col) {
                $t .= "<td>{$row[$col]}</td>";
            }
            $t .= "</tr>";
        }
        if ($er = $dbc->error()) {
            echo "<div class='alert alert-danger'>$er</div>";
        }
        $table .=  "</tbody></table></div>";
        $ret .= $t;

        return <<<HTML
<div class="container-fluid">
    <div>&nbsp;</div>
    {$this->form_content()}
    $msg
    $prodinfo
    $ret
</div>
HTML;
    }

    private function form_content()
    {
        $sku = (int)FormLib::get('sku');
        return <<<HTML
<form class =""  method="get" >
    <div class="row">
        <div class="col-md-2">
            <div class="form-group">
                <input type="text" class="form-control" name="sku" value="$sku" placeholder="Enter a SKU" autofocus>
            </div>
        </div>
        <div class="col-md-1">
            <div class="form-group">
                <input type="submit" class="btn btn-default" value="Submit">
            </div>
        </div>
        <div class="col-md-9">
            <label>Find products available from warehouse.</label>
        </div>
    </div>
</form>
HTML;
    }

    public function javascriptContent()
    {
        return <<<HTML
HTML;
    }
    
    public function helpContent()
    {
        return <<<HTML
<h4>Check Unfi Warehouse</h4>
<p>Enter a UNFI SKU (do <i>not</i> include leading zeros) to see if 
the corresponding product is available to Duluth stores. Rows will only 
appear in the table if the product is avaiilable from our warehouse.</p>
<p>The MySQL table that is checked was uploaded manually through Fannie 
Office Generic Upload Page.  
HTML;
    }

}
WebDispatch::conditionalExec();
