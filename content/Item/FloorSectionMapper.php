<?php
/*******************************************************************************

    Copyright 2020 Whole Foods Community Co-op.
    
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
class FloorSectionMapper extends PageLayoutA 
{
    
    protected $title = "";
    protected $description = "[] ";
    protected $ui = true;

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();

        return parent::preprocess();
    }

    public function pageContent()
    {           
        $dbc = scanlib::getConObj();
        $storeID = FormLib::get('storeID', false);
        $department = FormLib::get('department', false);
        $checkText = "Store: $storeID, Department: $department";
        if ($storeID == false) $storeID = 1;
        $args = array($storeID);
        $prep = $dbc->prepare("SELECT * FROM FloorSections 
            WHERE storeID = ? ORDER BY name");
        $res = $dbc->execute($prep, $args);
        $floorLocations = array();
        $fsLegend = '';
        $floorLocationOpts = "<option value=0>Filter by Sections</option>";
        while ($row = $dbc->fetchRow($res)) {
            $name = $row['name'];
            $fsID = $row['floorSectionID'];
            $substr = $floorLocations[$row['floorSectionID']] = substr($name, 0, 2) . substr($name, -1, 1); 
            $fsLegend .= "<div style=\"padding-left: 5px; padding-right: 5px; display: inline-block; 
                width: 200px; border: 1px solid lightgrey;\">$name</div>";
            $sel = (FormLib::get('floorLocation') == $fsID) ? 'selected' : '';
            $floorLocationOpts .= "<option value=\"$fsID\" data-substr=\"$substr\" $sel>$name</option>";
        }
        $prep = $dbc->prepare("SELECT * FROM departments");
        $res = $dbc->execute($prep);
        $departmentOpts = "<option value=\"0\">ALL DEPARTMENS</option>";
        while ($row = $dbc->fetchRow($res)) {
            $dept_no = $row['dept_no'];
            $dept_name = $row['dept_name'];
            $sel = (FormLib::get('department') == $dept_no) ? 'selected' : '';
            $departmentOpts .= "<option value=\"$dept_no\" $sel>$dept_name - $dept_no</option>";
        }

        $legend = "<div><strong>Column Legend</strong></div>$fsLegend";
        $th = '<th>BRAND</th><th>DESCRIPTION</th><th>SIZE</th>';
        foreach ($floorLocations as $id => $location) {
            $th .= "<th>$location</th>";
        }

        $args = array();
        if ($storeID) {
            $qstoreid = 'AND s.storeID = ?';
            $args[] = $storeID;
        } else {
            $qfsid = '';
        }
        if ($department != false) {
            $deptSpec = 'AND department = ?'; 
            $args[] = $department;
        }
        $prep = $dbc->prepare("
            SELECT f.floorSectionID, s.name, f.upc, p.size,
            CASE WHEN (pu.brand IS NOT NULL AND pu.brand != '') THEN pu.brand ELSE p.brand END AS brand,
            CASE WHEN (pu.description IS NOT NULL AND pu.description != '') THEN pu.description ELSE p.description END AS description
            FROM FloorSectionProductMap AS f
                LEFT JOIN FloorSections AS s ON f.floorSectionID=s.floorSectionID 
                LEFT JOIN products AS p ON p.upc=f.upc 
                LEFT JOIN productUser AS pu ON p.upc=pu.upc 
            WHERE 1
                $qstoreid
                $deptSpec
            GROUP BY f.upc, f.floorSectionID
        ");
        $res = $dbc->execute($prep, $args);
        $products = array();
        while ($row = $dbc->fetchRow($res)) {
            $upc = $row['upc'];
            $fsID = $row['floorSectionID'];
            $brand = $row['brand'];
            $desc = $row['description'];
            $size = $row['size'];
            $products[$upc]['fsIDs'][] = $fsID;
            $products[$upc]['brand'] = $brand;
            $products[$upc]['description'] = $desc;
            $products[$upc]['size'] = $size;
        };

        $td = '';
        foreach ($products as $upc => $row) {
            $td .= "<tr data-upc=\"$upc\">";
            $td .= "<td id=\"upc\" style=\"display: none;\">$upc</td>";
            $td .= "<td title=\"{$upc}\">{$row['brand']}</td>";
            $td .= "<td>{$row['description']}</td>";
            $td .= "<td>{$row['size']}</td>";
            foreach ($floorLocations as $id => $location) {
                $isSelected = (in_array($id, $row['fsIDs'])) ? 'selected' : '';
                $td .= "<td class=\"edit-location $isSelected\" data-location=\"$id\" id=\"upc$upc\">$location</td>";
            };
            $td .= "</tr>";
        };


        return <<<HTML
<input type="hidden" name="keydown" id="keydown"/>
<div style="position: fixed; top: 70px; right: 15; font-weight: bold; text-size: 20px; 
    background: rgba(255,255,255,0.7); border: 1px solid lightgrey; padding-left: 5px; padding-right: 5px;">
    $checkText
</div>
<div class="alert alert-info" align="center" style="position: fixed; left: 42%; top: 6px; display: none;" id="loading-content">LOADING</div>
{$this->formContent($floorLocationOpts, $departmentOpts)}
<div style="padding-bottom: 25px;">$legend</div>
<div class="table-responsive">
    <table class="table table-bordered table-sm small">
        <thead><tr>$th</tr></thead>
        <tbody>$td</tbody>
    </table>
</div>
HTML;
    }

    private function formContent($floorLocationOpts, $departmentOpts)
    {
        $storeID = (FormLib::get('storeID'));
        $stores = array(0=>'Select a store', 1=>'Hillside', 2=>'Denfeld');
        $storeSelectOpts = '';
        foreach ($stores as $id => $name) {
            $sel = ($storeID == $id) ? 'selected' : '';
            $storeSelectOpts .= "<option value=\"$id\" $sel>$name</option>";
        }

        return <<<HTML
<form id="form-content" name="form-content" method="get">
    <select name="storeID" id="storeID">$storeSelectOpts</select>
    <select name="floorLocation">$floorLocationOpts</select>
    <select name="department">$departmentOpts</select>
</form>
HTML;
    }
    
    
    public function javascriptContent()
    {
        /*
            -ajax-
            on right click, send to handler to clear location and reset to chosen
            on shift + click, sendto handler to add an additional location
        */
        return <<<JAVASCRIPT
$('select').change(function(){
    if ($(this).attr('name') == 'floorLocation') {
        sortByLocation($(this));
    } else if ($(this).attr('name') == 'storeID') {
        document.forms['form-content'].submit();
    } else if ($(this).attr('name') == 'department') {
        document.forms['form-content'].submit();
    }
}); 
var sortByLocation = function(thisObj) 
{
    var id = thisObj.find('option:selected').val();
    var name = thisObj.find('option:selected').text();
    var substr = thisObj.find('option:selected').attr('data-substr');
    $('tr').each(function(){
        $(this).show();
    });
    $('td').each(function(){
        if ($(this).text() == substr) {
            var isSelected = $(this).hasClass('selected');
            if (isSelected != true) {
                $(this).closest('tr').hide();
            }
        }
    });
    var i = 0;
    $('tr').each(function(){
        if ($(this).is(':visible')) {
            if (i % 2 == 0) {
                $(this).css('background-color', '#FEF7E2'); 
            } else {
                $(this).css('background-color', 'white'); 
            }
            i++;
        }
    });
}

// distinguish left click from shift + click
$(document).keydown(function(e){
    var key = e.keyCode;
    $('#keydown').val(key);
});
$(document).keyup(function(e){
    var key = e.keyCode;
    $('#keydown').val(0);
});
$(document).mousedown(function(e){
    if (e.which == 1 && $('#keydown').val() == 16) {
        var target = $(e.target);
        var upc = target.closest('tr').attr('data-upc');
        var storeID = $('#storeID').find('option:selected').val();
        var floorSectionID = target.attr('data-location');
        //alert(upc+','+floorSectionID);
        if (target.is('td')) {
            e.preventDefault();
            // on shift + click, send ajax to insert a row
            $.ajax({
                url: 'floorSectionMapperAjax.php',
                type: 'post',
                data: 'upc='+upc+'&floorSectionID='+floorSectionID+'&shift=1',
                success: function(response)
                {
                    console.log('ajax success');
                    console.log(response);
                },
                fail: function(response)
                {
                    console.log('ajax failed');
                },
            });
            if (target.hasClass('selected')) {
                target.removeClass('selected');
            } else {
                target.addClass('selected');
            }
            $('#keydown').val(0);
        }
    } else if (e.which == 1) {
        // on left click, remove previous mapping and set to selected 
        var target = $(e.target);
        var upc = target.closest('tr').attr('data-upc');
        var storeID = $('#storeID').find('option:selected').val();
        var floorSectionID = target.attr('data-location');
        //alert(upc+','+floorSectionID);
        if (target.is('td')) {
            e.preventDefault();
            $.ajax({
                url: 'floorSectionMapperAjax.php',
                type: 'post',
                data: 'upc='+upc+'&floorSectionID='+floorSectionID+'&shift=0'+'&storeID='+storeID,
                success: function(response)
                {
                    console.log('ajax success');
                    console.log(response);
                },
                fail: function(response)
                {
                    console.log('ajax failed');
                },
            });
            var i = 0;
            target.closest('tr').find('td').each(function(){
                i++;
                if (i>2) {
                    if ($(this).hasClass('selected')) {
                        $(this).removeClass('selected');
                    }
                }
                target.addClass('selected');
            });
            $('#keydown').val(0);
        }
    }
});
JAVASCRIPT;
    }

    public function cssContent()
    {
        return <<<HTML
td {
    cursor: pointer;
}
.selected {
    background-color: rgba(255, 55, 55, 0.4);
}
HTML;
    }
    
}
WebDispatch::conditionalExec();
