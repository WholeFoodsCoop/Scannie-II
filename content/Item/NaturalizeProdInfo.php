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
class NaturalizeProdInfo extends PageLayoutA
{

    protected $title = "Cleanup Sign Information"; 
    protected $description = "[Naturalize Product Information] Clean-up discrepancies 
        within and between POS products and SIGN information."; 
    protected $ui = TRUE; 
    protected $must_authenticate = true;
    protected $auth_types = array(2);

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();
        $this->__routes[] = 'get<line>';
        $this->__routes[] = 'post<brand>';
        $this->__routes[] = 'post<alt_description>';
        $this->__routes[] = 'post<description>';
        $this->__routes[] = 'post<department>';
        $this->__routes[] = 'post<alt_brand>';
        $this->__routes[] = 'post<cleanup>';
        $this->__routes[] = 'post<vendor>';

        return parent::preprocess();
    }

    public function postAlt_brandHandler()
    {
        $upc = FormLib::get('upc');
        $brand = FormLib::get('alt_brand');
        $dbc = scanLib::getConObj();

        $args = array($brand, $upc);
        $prep = $dbc->prepare("UPDATE products 
            SET brand = ?
            WHERE upc = ?");
        $res = $dbc->execute($prep, $args);
        if ($er = $dbc->error()) {
            echo $er;
        }

        return false;
    }

    public function postBrandHandler()
    {
        $upc = FormLib::get('upc');
        $brand = FormLib::get('brand');
        $dbc = scanLib::getConObj();

        $args = array($brand, $upc);
        $prep = $dbc->prepare("UPDATE productUser
            SET brand = ?
            WHERE upc = ?");
        $res = $dbc->execute($prep, $args);
        if ($er = $dbc->error()) {
            echo $er;
        }

        return false;
    }

    public function postDescriptionHandler()
    {
        $upc = FormLib::get('upc');
        $description = FormLib::get('description');
        $description = str_replace('and', '&', $description);
        $description = str_replace('\n', "\n", $description);
        $dbc = scanLib::getConObj();

        $args = array($description, $upc);
        $prep = $dbc->prepare("UPDATE productUser
            SET description = ?
            WHERE upc = ?");
        $res = $dbc->execute($prep, $args);
        if ($er = $dbc->error()) {
            echo $er;
        }

        return false;
    }

    public function postAlt_descriptionHandler()
    {
        $upc = FormLib::get('upc');
        $description = FormLib::get('alt_description');
        $description = str_replace('and', '&', $description);
        $dbc = scanLib::getConObj();

        $args = array($description, $upc);
        $prep = $dbc->prepare("UPDATE products 
            SET description = ? 
            WHERE upc = ?");
        $res = $dbc->execute($prep, $args);
        if ($er = $dbc->error()) {
            echo $er;
        }

        return false;
    }

    public function getVendorView()
    {
        $vendor = (FormLib::get('vendor')) ? FormLib::get('vendor') : 1;
        $dbc = scanLib::getConObj();
        $data = array();
        
        $args = array($vendor);
        $query = "SELECT 
            pu.upc, pu.brand, pu.description,
                p.brand AS alt_brand, p.description AS alt_description
            FROM productUser AS pu
                LEFT JOIN products AS p ON pu.upc=p.upc
            WHERE p.default_vendor_id = ?
            GROUP BY pu.upc";
        $prep = $dbc->prepare($query);
        $res = $dbc->execute($prep, $args);
        $data = array();
        $product_lines = array();
        $brands = array();
        $columns = array('upc','alt_brand','brand','description', 'alt_description');
        $brands = array();
        $table = "<table class=\"table table-sm small\">    
            <thead> 
                <th>UPC</th>
                <th>POS Brand <input class=\"edit-all\" data-change=\"alt_brand\" placeholder=\"edit all\" style=\"\"></th>
                <th>SIGN Brand <input class=\"edit-all\" data-change=\"brand\" placeholder=\"edit all\" style=\"\"></th>
                <th>Description</th>
            </thead><tbody>";
        while ($row = $dbc->fetchRow($res)) {
            $table .= "<tr>";
            foreach ($columns as $column) {
                $placeholder = null;
                $placeholder = $row['alt_'.$column];
                if ($column != 'upc') {
                    $table .= "<td><input class=\"editable\" contenteditable=\"true\" data-column=\"$column\"   
                        data-upc=\"{$row['upc']}\" value=\"{$row[$column]}\" placeholder=\"$placeholder\"/>
                        </td>";
                } else {
                    $table .= "<td>{$row['upc']}</td>";
                }
            }
            $table .= "</tr>";
        }
        $table .= "</tbody></table>";

        return <<<HTML
<div style="position: fixed; top: 0px; width: 100%; opacity: 0.55;">
<div class="progress" id="alert-wait" style="display: none">
  <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100" style="width: 100%"></div>
  </div>
</div>
<form method="get">
    <strong>Vendor:</strong>
    <input type="number" name="vendor" value="$vendor" style="border: 1px solid lightgrey; width: 75px"/>
    <button type="submit">Change Vendor</button>
</form>
$table
HTML;
    }

    public function getDepartmentView()
    {
        $department = FormLib::get('department');
        $vendor = (FormLib::get('vendor')) ? FormLib::get('vendor') : 1;
        $dbc = scanLib::getConObj();
        $data = array();

        $vendor_select = "<strong>Vendor: </strong><select name=\"vendor\" id=\"vendor-select\">";
        $args = array($department);
        $prep = $dbc->prepare("SELECT p.default_vendor_id, v.vendorName FROM products AS p
            LEFT JOIN vendors AS v ON p.default_vendor_id=v.vendorID
            WHERE department = ? GROUP BY default_vendor_id;");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $sel = ($vendor == $row['default_vendor_id']) ? 'selected' : '';
            $vendor_select .= "<option value=\"{$row['default_vendor_id']}\" $sel>{$row['vendorName']}</option>";
        }
        $vendor_select .= "</select>";

        $department_select = "<strong>Department: </strong><select name=\"department\" id=\"department-select\">";
        $prep = $dbc->prepare("SELECT dept_name, dept_no FROM departments ORDER BY dept_no;");
        $res = $dbc->execute($prep, $args);
        while ($row = $dbc->fetchRow($res)) {
            $sel = ($department == $row['dept_no']) ? 'selected' : '';
            $department_select .= "<option value=\"{$row['dept_no']}\" $sel>{$row['dept_no']} - {$row['dept_name']}</option>";
        }
        $department_select .= "</select>";
        
        $args = array($department, $vendor);
        $default_vendor_id = ($vendor != 0) ? "AND p.default_vendor_id = ?" : "";
        $query = "SELECT 
            pu.upc, pu.brand, pu.description,
                p.brand AS alt_brand, p.description AS alt_description
            FROM productUser AS pu
                LEFT JOIN products AS p ON pu.upc=p.upc
            WHERE p.department = ?
                $default_vendor_id
            GROUP BY pu.upc";
        $prep = $dbc->prepare($query);
        $res = $dbc->execute($prep, $args);
        $data = array();
        $product_lines = array();
        $brands = array();
        $columns = array('upc','alt_brand','brand','description', 'alt_description');
        $brands = array();
        $table = "<table class=\"table table-sm small\">    
            <thead> 
                <th>UPC</th>
                <th>POS Brand <input class=\"edit-all\" data-change=\"alt_brand\" placeholder=\"edit all\" style=\"\"></th>
                <th>SIGN Brand <input class=\"edit-all\" data-change=\"brand\" placeholder=\"edit all\" style=\"\"></th>
                <th>Description</th>
                <th>POS Description</th>
            </thead><tbody>";
        while ($row = $dbc->fetchRow($res)) {
            $table .= "<tr>";
            foreach ($columns as $column) {
                $placeholder = null;
                $placeholder = $row['alt_'.$column];
                if ($column != 'upc') {
                    $table .= "<td><input class=\"editable\" contenteditable=\"true\" data-column=\"$column\"   
                        data-upc=\"{$row['upc']}\" value=\"{$row[$column]}\" placeholder=\"$placeholder\"/>
                        </td>";
                } else {
                    $table .= "<td>{$row['upc']}</td>";
                }
            }
            $table .= "</tr>";
        }
        $table .= "</tbody></table>";

        return <<<HTML
<div style="position: fixed; top: 0px; width: 100%; opacity: 0.55;">
<div class="progress" id="alert-wait" style="display: none">
  <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100" style="width: 100%"></div>
  </div>
</div>
<form name="vendor-select-form">
    $vendor_select <button class="dept-prev-btn small" data-select="vendor"> prv </button> <button class="dept-next-btn small" data-select="vendor"> nxt </button>
    $department_select <button class="dept-prev-btn small" data-select="department"> prv </button> <button class="dept-next-btn small" data-select="department"> nxt </button>
</form>
$table
HTML;
    }

    public function getLineView()
    {
        $line = FormLib::get('line');
        $dbc = scanLib::getConObj();
        $data = array();
        
        $prep = $dbc->prepare("SELECT pu.upc, pu.brand, pu.description,
                p.brand AS alt_brand, p.description AS alt_description
            FROM productUser AS pu
                LEFT JOIN products AS p ON pu.upc=p.upc
            WHERE pu.upc like '%$line%'
            GROUP BY pu.upc");
        $res = $dbc->execute($prep);
        $data = array();
        $product_lines = array();
        $brands = array();
        $columns = array('upc','brand','description');
        $brands = array();
        $table = "<table class=\"table table-sm small\">    
            <thead> 
                <th>UPC</th>
                <th>Brand <input class=\"edit-all\" data-change=\"brand\" placeholder=\"edit all\" style=\"\"></th>
                <th>Description</th>
            </thead><tbody>";
        while ($row = $dbc->fetchRow($res)) {
            $table .= "<tr>";
            foreach ($columns as $column) {
                $placeholder = null;
                $placeholder = $row['alt_'.$column];
                if ($column != 'upc') {
                    $table .= "<td><input class=\"editable\" contenteditable=\"true\" data-column=\"$column\"   
                        data-upc=\"{$row['upc']}\" value=\"{$row[$column]}\" placeholder=\"$placeholder\"/>
                        </td>";
                } else {
                    $table .= "<td>{$row['upc']}</td>";
                }
            }
            $table .= "</tr>";
        }
        $table .= "</tbody></table>";

        return <<<HTML
<div style="position: fixed; top: 0px; width: 100%; opacity: 0.55;">
<div class="progress" id="alert-wait" style="display: none">
  <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100" style="width: 100%"></div>
  </div>
</div>
$table
HTML;
    }

    public function pageContent()
    {
        return <<<HTML
<div style="padding:25px;">
<h2>{$this->title}</h2>
<ul>
    <li><a href="NaturalizeProdInfo.php?cleanup=1">Review Cleaup Suggestions</a></li>
    <li><a href="NaturalizeProdInfo.php?department=1">Cleanup by Department/Brand</a></li>
    <li><a href="NaturalizeProdInfo.php?vendor=3">Cleanup by Vendor</a></li>
</ul>
</div>
HTML;
    }

    public function getCleanupView()
    {
        $ret = '';
        $dbc = scanLib::getConObj();
        $data = array();
        
        $prep = $dbc->prepare("SELECT pu.* FROM productUser as pu
            LEFT JOIN products AS p ON pu.upc=p.upc
            WHERE p.inUse = 1 GROUP BY p.upc");
        $res = $dbc->execute($prep);
        $data = array();
        $product_lines = array();
        $brands = array();
        $columns = array('description','brand','sizing','photo','nutritionFacts','long_text','enableOnline','soldOut','signCount','narrow');
        $brands = array();
        while ($row = $dbc->fetchRow($res)) {
            $upc = $row['upc'];
            foreach ($columns as $column) {
                $data[$upc][$column] = $row[$column];
                ${$column} = $row[$column];
            }
            if (!in_array($brand, $brands)) {
                $brands[] = $brand;
            }
            $line = substr($upc, 0, 8);
            $data[$upc]['category'] = $line;
            if (!in_array($brand, $product_lines[$line]) && $line != 0) {
                if ($brand == null || $brand == '') {
                    $product_lines[$line][] = 'EMPTY';
                } else {
                    $product_lines[$line][] = $brand;
                }
            }
        }
        $pre_brand = null;
        $ret = '';
        foreach ($product_lines as $line => $array) {
            foreach ($array as $brand) {
                if ($brand != 'EMPTY' && count($array) > 1&& strtolower($brand) != 'organic') {
                    $target = "NaturalizeProdInfo.php?line=$line";
                    $ret .= $url = "<div><a href=\"$target\" target=\"_blank\">$line - $brand</a></div>";
                }
            }
        }

        return <<<HTML
$ret;
HTML;

    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
var count_edits = 0;
$('.editable').change(function(){
    var upc = $(this).attr('data-upc');
    var text = $(this).val();
    var column = $(this).attr('data-column');
    $.ajax({
        beforeSend: function(){
            count_edits++;
        },
        type: 'post',
        data: 'upc='+upc+'&'+column+'='+text,
        success: function(response){
            console.log('success');  
            count_edits--;
        },
        fail: function(response){
            console.log('failed');
        }
    });
});
$('.edit-all').change(function(){
    if ($(this).val() != '') {
        var c = confirm('Permanently change all products?');
        if (c == true) {
            var change_column = $(this).attr('data-change');
            var text = $(this).val();
            $('.editable').each(function(){
                var column = $(this).attr('data-column');
                if (column == change_column) {
                    if ($(this).val() != text) {
                        $(this).val(text);
                        $(this).trigger('change');
                    }
                }
            });
        }
    }
});
var interval = setInterval('checkEdits()', 100);
var checkEdits = function(){
    if (count_edits == 0) {
        $('#alert-wait').hide();
    } else {
        $('#alert-wait').show();
    }
};
$('a').click(function(){
    $(this).css('background', 'lightgrey');
});
$('#vendor-select').change(function(){
    document.forms['vendor-select-form'].submit();
});
$('#department-select').change(function(){
    document.forms['vendor-select-form'].submit();
});
$('.dept-next-btn').click(function(e){
    e.preventDefault(); 
    var select_id = $(this).attr('data-select');
    $('#'+select_id+'-select option:selected').next().attr('selected', 'selected');
    document.forms['vendor-select-form'].submit();
});
$('.dept-prev-btn').click(function(e){
    e.preventDefault(); 
    var select_id = $(this).attr('data-select');
    $('#'+select_id+'-select option:selected').prev().attr('selected', 'selected');
    document.forms['vendor-select-form'].submit();
});
JAVASCRIPT;
    }

    public function cssContent()
    {
        return <<<HTML
input {
    width: 100%;
    border: 1px solid transparent;
}
HTML;
    }

}
WebDispatch::conditionalExec();
