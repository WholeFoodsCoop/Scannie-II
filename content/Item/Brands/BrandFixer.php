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
    include(__DIR__.'/../../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../../common/sqlconnect/SQLManager.php');
}
class BrandFixer extends PageLayoutA
{

    protected $title = "Brand-name Fixer Page"; 
    protected $description = "[]  ";
    protected $ui = TRUE; 
    protected $must_authenticate = true;
    protected $auth_types = array(2);

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();
        $this->__routes[] = 'post<insert>';
        $this->__routes[] = 'post<updateUpcs>';

        return parent::preprocess();
    }

    private function getTrueFix($upcStr, $args)
    {
        $dbc = scanLib::getConObj();

        $prep = $dbc->prepare("UPDATE products p RIGHT JOIN BrandAbbrFix AS t ON t.badName = p.brand
            SET p.brand = t.goodName WHERE p.upc IN ($upcStr)");
        $res = $dbc->execute($prep, $args);

        $prep = $dbc->prepare("UPDATE products p LEFT JOIN vendorItems AS v ON v.upc=p.upc
            SET v.brand = p.brand WHERE p.upc IN ($upcStr)");
        $res = $dbc->execute($prep, $args);

        return false;

    }

    public function postUpdateUpcsView()
    {
        $td = '';
        $upcs = FormLib::get('updateUpcs');
        $upcs = explode("\r\n", $upcs);

        $dbc = scanLib::getConObj();

        list($upcStr, $args) = $dbc->safeInClause($upcs);
        // first, use BrandAbbrFix to fix products.brand
        $this->getTrueFix($upcStr, $args);
        // second, copy products.brand to format and move to productUser.brand
        $prep = $dbc->prepare("SELECT upc, brand FROM products WHERE upc IN ($upcStr)");
        $res = $dbc->execute($prep, $args);

        $data = array();
        while ($row = $dbc->fetchRow($res)) {
            $upc = $row['upc'];
            $brand = $row['brand'];
            $brand = strtolower($brand);
            $brand = ucwords($brand);
            $brand = scanLib::specialBrandStrFix($brand);
            $data[$upc] = $brand;
        }

        $dbc->startTransaction();
        foreach ($data as $upc => $brand) {
            $a = array($brand, $upc, $brand);
            $p = $dbc->prepare("INSERT INTO productUser (brand, upc) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE brand = ?;");
            $r = $dbc->execute($p, $a);
        }
        $dbc->commitTransaction();

        $checkP = $dbc->prepare("SELECT upc, brand FROM productUser WHERE upc IN ($upcStr)");
        $checkR = $dbc->execute($checkP, $args);
        while ($row = $dbc->fetchRow($checkR)) {
            $brand = $row['brand'];
            $upc = $row['upc'];
            $td.= sprintf("<tr><td>%s</td><td>%s</td></tr>",
                $upc, $brand);
        }

        return <<<HTML
<div class="row" style="width: 100%; padding-top: 25px;">
    <div class="col-lg-1"></div>
    <div class="col-lg-10">
        <h4>The following SIGN brand descriptions were updated</h4>
        <table class="table table-bordered table-sm small"><thead></thead><tbody>$td</tbody></table>
        <div class="form-group">
            <a href="#" onClick="window.location.href = 'BrandFixer.php'" class="btn btn-default">Go Back</a>
        </div>
    </div>
    <div class="col-lg-1"></div>
</div>
HTML;
    }

    public function pageContent()
    {
        $td = '';
        $dbc = scanLib::getConObj();

        $prep = $dbc->prepare("SELECT p.upc, p.brand, u.brand AS ubrand, p.description, m.super_name, v.vendorName
            FROM products AS p
                LEFT JOIN productUser AS u ON u.upc=p.upc
                INNER JOIN MasterSuperDepts AS m ON m.dept_ID=p.department 
                LEFT JOIN vendors AS v ON v.vendorID=p.default_vendor_id
            WHERE LOWER(p.brand) != LOWER(u.brand)
                AND m.super_name != 'PRODUCE'
                AND p.last_sold > NOW() - INTERVAL 1 YEAR
                AND p.inUse = 1
            GROUP BY u.upc
            # GROUP BY p.brand
            ORDER BY u.brand, p.upc");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            $td .= sprintf("<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>",
                $row['upc'],
                //ucwords(strtolower($row['brand'])),
                $row['brand'],
                $row['ubrand'],
                $row['description'],
                $row['super_name'],
                $row['vendorName'],
            );
        }

        return <<<HTML
<div style="padding:25px; width: 100%;">
    <h4>{$this->title}</h4>

    <div class="row">
        <div class="col-lg-4">
            <form name="updateListForm" action="BrandFixer.php" method="post">
                <div class="form-group">
                    <label for="updateUpcs">Enter a list of UPCs to fix</label>
                    <textarea class="form-control" name="updateUpcs" id="updateUpcs"></textarea>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-default" />Update POS Brand to SIGN Brand</button>
                </div>
            </form>
        </div>
        <div class="col-lg-4">
            <ul>
                <li><a href="BrandAbbrFix.php">FANNIE POS Brand Abbr Fix</a></li>
            </ul>
        </div>
        <div class="col-lg-4"></div>
    </div>

    <table class="table table-bordered table-sm small">
        <thead><th>UPC</th><th>POS Brand</th><th>Sign Brand</th><th>POS Description</th><th>Super Dept.</th><th>Vendor</th></thead>
        <tbody>$td</tbody>
    </table>
    <div class="padding: 25px"></div>
</div>
HTML;
    }


    public function javascriptContent()
    {
        return <<<JAVASCRIPT
var last = '';
$('.editable').each(function(){
    $(this).attr('contentEditable', 'true');
});
$('.editable').focusin(function(){
    last = $(this).text();
});
$('.editable').focusout(function(){
    console.log(last);
    var current = $(this).text();
    var tid = $(this).parent().find('td:eq(0)').text();
    var type = $(this).attr('data-type');
    if (current != last) {
        $.ajax({
            'type': 'post',
            'url': 'BrandFixer.php',
            'data': 'trueID='+tid+'&type='+type+'&text='+current,
            success: function(resp) {
                alert('success!');
            }
        });
    }
    last = '';
});
JAVASCRIPT;
    }

    public function cssContent()
    {
        return <<<HTML
HTML;
    }

}
WebDispatch::conditionalExec();
