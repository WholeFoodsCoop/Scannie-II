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
class DoNotTrack extends PageLayoutA 
{

    protected $title = "";
    protected $description = "[] ";
    protected $ui = false;
    protected $must_authenticate = false;

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();
        $this->__routes[] = 'get<test>';
        $this->__routes[] = 'post<delete_row>';
        $this->__routes[] = 'post<upc>';

        return parent::preprocess();
    }

    public function postUpcHandler()
    {
        $upc = FormLib::get('upc');
        $page = FormLib::get('page');
        $method = FormLib::get('method');
        $dbc = scanLib::getConObj('SCANALTDB');

        $args = array($upc, $method, $page);
        $prep = $dbc->prepare("INSERT INTO doNotTrack (upc, method, page)
            VALUES (?, ?, ?)");
        $res = $dbc->execute($prep, $args);

        return false;
    }

    public function getTestView()
    {
        return <<<HTML
well, hello there, world!
HTML;
    }

    public function postDelete_rowView()
    {
        $id = FormLib::get('delete_row', false);
        $dbc = scanLib::getConObj('SCANALTDB');
        $prep = $dbc->prepare("DELETE FROM doNotTrack WHERE id = ?");
        $res = $dbc->execute($prep, array($id));

        return header('location: DoNotTrack.php');
    }

    public function cssContent()
    {
return <<<HTML
select, input {
    border: 1px solid lightgrey;
}
.my-table {
    overflow-y: auto;
    border: 1px solid lightgrey;
    height: 495px;
}
HTML;
    }

    public function pageContent()
    {
        $ret = '';
        $dbc = scanLib::getConObj('SCANALTDB');
        $data = array();
        $pages = array();
        $methods = array();

        $prep = $dbc->prepare("SELECT d.*, p.brand, p.description FROM doNotTrack AS d
            LEFT JOIN is4c_op.products AS p ON d.upc=p.upc");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            $id = $row['id'];
            $upc = $row['upc'];
            $method = $row['method'];
            $page = $row['page'];
            $description = $row['description'];
            $brand = $row['brand'];
            $data[$id]['upc'] = $upc;
            $data[$id]['method'] = $method;
            $data[$id]['page'] = $page;
            $data[$id]['description'] = $description;
            $data[$id]['brand'] = $brand;
            if (!in_array($page, $pages)) $pages[] = $page;
            if (!in_array($method, $methods)) $methods[] = $method;
        }

        $selects = "<select id=\"filter-pages\" class=\"filter\"><option value=\"null\">by Page</option>";
        foreach ($pages as $page) {
            $selects .= "<option value=\"$page\">$page</option>";
        }
        $selects .= "</select><select id=\"filter-method\" class=\"filter\"><option value=\"null\">by Method</option>";
        foreach ($methods as $method) {
            $selects .= "<option value=\"$method\">$method</option>";
        }
        $selects .= "</select>";

        $table = $this->getTable($data);

        $form = "<div id\"upcForm\">
            <h5>Add UPC</h5>
            <input type=\"text\" name=\"upc\" id=\"upc\" disabled>
            <button id=\"submitUpcForm\" disabled>Submit</button>
            </div>";

        return <<<HTML
<div class="container-fluid" style="margin-top: 15px">
    <h2>Do Not Track</h2>
    <hr/>
    <div id="controls"></div>
    <div class="row">
        <div class="col-lg-3">
            <h3>Filters</h3>
            <hr/>
            $selects
            <hr/>
            $form
        </div>
        <div class="col-lg-9">
            <div id="mode"></div>
            $table
        </div>
    </div>
</div>
HTML;
    }

    private function getTable($data)
    {
        $table = "<div class=\"my-table\"><form method=\"post\"><table class=\"table table-bordered table-sm small \"><thead>
            <tr><th>upc</th><th>page</th><th>method</th><th>brand</th><th>description</th><th>delete</th></thead><tbody>";
        foreach ($data as $id => $row) {
            $table .= "<tr data-row=\"$id\">";
            $table .= "<td class=\"upc\">{$row['upc']}</td>";
            $table .= "<td class=\"page\">{$row['page']}</td>";
            $table .= "<td class=\"method\">{$row['method']}</td>";
            $table .= "<td class=\"brand\">{$row['brand']}</td>";
            $table .= "<td class=\"description\">{$row['description']}</td>";
            $table .= "<td><button name=\"delete_row\" value=$id class=\"scanicon scanicon-trash scanicon-sm btn btn-default\"></button></td>";
            $table .= "</tr>";
        }
        $table .= "</tbody></table></form></div>";

        return $table;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
$('.filter').on('change', function(){
    var page = $('option:selected', '#filter-pages').text();
    var method = $('option:selected', '#filter-method').text();
    var filter = $(this).attr('id').substring(7);
    var option = $('option:selected',this).attr('value');
    if (option != 'null') {
        $('#mode').text('');
        $('#mode').append('<h4>'+page+' - '+method+'</h4>');
        $('td').each(function(){
            $(this).closest('tr').show();     
        });
        $('td').each(function(){
            if ($(this).hasClass(filter)) {
                var text = $(this).text();
                if (text != option) {
                    $(this).closest('tr').hide();
                }
            }
        });
    } else {
        $('#mode').text('');
        $('td').each(function(){
            $(this).closest('tr').show();     
        });
    }
    if (page == 'by Page' || method == 'by Method') {
        $('#upc').attr('disabled', true);
        $('#submitUpcForm').attr('disabled', true);
    } else {
        $('#upc').attr('disabled', false);
        $('#submitUpcForm').attr('disabled', false);
    }
});
$('#submitUpcForm').click(function(){
    var page = $('option:selected', '#filter-pages').attr('value');
    var method = $('option:selected', '#filter-method').attr('value');
    var upc = $('#upc').val();
    $.ajax({
        type: 'post',
        data: 'page='+page+'&method='+method+'&upc='+upc,
        url: 'DoNotTrack.php',
        success: function(response){
            alert(upc+' => added to '+page+'/'+method);
        },
    });
});
$('.scanicon-trash').click(function(){
    var c = confirm('Delete row?');
    if (c == true) {
        return true;
    } else {
        return false;
    }
});
JAVASCRIPT;
    }

}
WebDispatch::conditionalExec();
