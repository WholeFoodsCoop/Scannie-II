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
class PendingAction extends PageLayoutA
{

    protected $title = "Pending Actions";
    protected $description = "[Pending Actions] is a memory safety net.";
    protected $ui = TRUE;

    public function body_content()
    {
        $ret = '';
        $dbc = scanLib::getConObj();
        $storeID = scanLib::getStoreID();
        $FANNIE_ROOTDIR = $this->config->vars['FANNIE_ROOTDIR'];

        $ret .= '<div class="container">';
        $ret .= '<h4 style="margin-top: 15px;">Pending Actions</h4>';
        $ret .= $this->form_content();

        if ($addItem = str_pad($_POST['addItem'], 13, 0, STR_PAD_LEFT)) {
            $note = $_POST['note'];
            $args = array($addItem,$note,$storeID,$note);
            $prep = $dbc->prepare("INSERT INTO woodshed_no_replicate.exceptionItems 
                (upc,note,timestamp,storeID) VALUES (?,?,NOW(),?) 
                ON DUPLICATE KEY UPDATE note = ?");
            $dbc->execute($prep,$args);
            unset($_POST['addItem']);
            if ($dbc->error()) $ret .=  "<div class=\"alert alert-danger\">".$dbc->error()."</div>";
        }
        if ($rmItem = str_pad($_POST['rmItem'], 13, 0, STR_PAD_LEFT)) {
            $prep = $dbc->prepare("delete from woodshed_no_replicate.exceptionItems where upc = ?");
            $dbc->execute($prep,$rmItem);
            unset($_POST['rmItem']);
        }
        
        $data = array();
        $p = $dbc->prepare("SELECT upc, note, DATE(timestamp) AS timestamp
            FROM woodshed_no_replicate.exceptionItems ORDER BY timestamp DESC;");
        $r = $dbc->execute($p);
        while ($row = $dbc->fetchRow($r)) {
            $data[$row['upc']]['note'] = $row['note'];
            $data[$row['upc']]['timestamp'] = $row['timestamp'];
        }

        $query = '
            SELECT
                e.upc,
                p.brand,
                p.description,
                p.special_price,
                e.note,
                p.inUse,
                e.storeID
            FROM woodshed_no_replicate.exceptionItems AS e
                INNER JOIN products AS p ON p.upc=e.upc
            AND p.store_id = ?
        ';
        $args = array($storeID);
        $prep = $dbc->prepare($query);
        $result = $dbc->execute($prep,$args);
        while ($row = $dbc->fetch_row($result)) {
            $data[$row['upc']]['brand'] = $row['brand'];
            $data[$row['upc']]['desc'] = $row['description'];
            $data[$row['upc']]['salePrice'][] = $row['special_price'];
            $data[$row['upc']]['in_use'] = $row['inUse'];
            $data[$row['upc']]['storeID'] = $row['storeID'];
            if (!isset($data[$row['upc']]['note'])) {
                $data[$row['upc']]['note'] = $row['note'];
            }
        }
        unset($data['0000000000000']);
        if ($dbc->error()) $ret .=  $dbc->error();

        $ret .= '<form method="post" name="rmbtn" id="rmbtn">';

        $table_1 =  '<div class="panel panel-default table-responsive"><table id="table_1" class="table table-striped table-bordered table-sm small">';
        $table_1 .=  '
            <thead>
                <th>UPC</th>
                <th>Brand</th>
                <th>Description</th>
                <th>Action Required</th>
                <th>Timestamp</th>
                <th></th>
            </thead>';
        $table_2 =  '<div class="panel panel-default table-responsive"><table id="table_2" class="table table-striped table-bordered table-sm small">';
        $table_2 .=  '
            <thead>
                <th>UPC</th>
                <th>Brand</th>
                <th>Description</th>
                <th>Action Required</th>
                <th>Timestamp</th>
                <th></th>
            </thead>';

        foreach ($data as $upc => $array) {
                $action_data_value = $array['in_use'];
            if ($array['storeID'] == 1) {
                $upcLink = '<a id="upcLink" href="http://'.$FANNIE_ROOTDIR.'/item/ItemEditorPage.php?searchupc=' . $upc . '" target="_blank">' . $upc . '</a>';
                $table_1 .= '<tr>';
                $table_1 .= '<td>' . $upcLink . '</td>';
                $table_1 .= '<td>' . $array['brand'] . '</td>';
                $table_1 .= '<td>' . $array['desc'] . '</td>';
                $table_1 .= '<td>' . scanLib::strGetDate($array['note']) . '</td>';
                $table_1 .= '<td data-value="'.$action_data_value.'">' . $array['timestamp'] . '</td>';
                $table_1 .= "<td><button name=\"rmItem\" class=\"scanicon scanicon-trash btn btn-default\" value=$upc>&nbsp;</button></td>";
            } else {
                $upcLink = '<a id="upcLink" href="http://'.$FANNIE_ROOTDIR.'/item/ItemEditorPage.php?searchupc=' . $upc . '" target="_blank">' . $upc . '</a>';
                $table_2 .= '<tr>';
                $table_2 .= '<td>' . $upcLink . '</td>';
                $table_2 .= '<td>' . $array['brand'] . '</td>';
                $table_2 .= '<td>' . $array['desc'] . '</td>';
                $table_2 .= '<td>' . scanLib::strGetDate($array['note']) . '</td>';
                $table_2 .= '<td data-value="'.$action_data_value.'">' . $array['timestamp'] . '</td>';
                $table_2 .= "<td><button name=\"rmItem\" class=\"scanicon scanicon-trash btn btn-default\" value=$upc>&nbsp;</button></td>";

            }
        }
        $table_1 .=  '</table></div>';
        $table_2 .=  '</table></form></div>';
        $table_2 .= '</div>';

        return <<<HTML
<input type="hidden" id="store_id" value=$storeID />
$ret
Entered @ <h5 style="display: inline-block">Hillside</h5>
$table_1
Entered @ <h5 style="display: inline-block">Denfeld</h5>
$table_2
HTML;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
$('td').each(function(){
    var table_id = $(this).closest('table').attr('id');
    var in_use = $(this).attr('data-value');
    var current_store = $('#store_id').val();
    if (current_store == 1 && table_id == 'table_1') {
        if (in_use == 1) {
            var action = $(this).closest('td').prev('td').text();
            if (action == 'Item un-used') {
                $(this).addClass('text-danger')
                    .attr('title', 'item has new sales');
            }
        }
    } else if (current_store == 2 && table_id == 'table_2') {
        if (in_use == 1) {
            var action = $(this).closest('td').prev('td').text();
            if (action == 'Item un-used') {
                $(this).addClass('text-danger')
                    .attr('title', 'item has new sales');
            }
        }

    }
});
 $('#rmbtn').submit(function(){
     var c = confirm('Delete action from table?');
     if (c == true) {
         return true;
     } else {
         return false;
     }
 });
JAVASCRIPT;
    }

    public function form_content()
    {
        $ret .= '
            <form method="post">
                <div class="row">
                    <div class="col-lg-3">
                        <div class="form-group"><div class="input-group">
                            <span class="input-group-addon" title="Add an item to the list by entering a UPC here.">Add</span>
                            <input type="text" class="form-control" id="addItem" name="addItem" autofocus>
                        </div></div>
                    </div>
                    <div class="col-lg-7">
                        <div class="form-group"><div class="input-group">
                            <span class="input-group-addon">Action</span>
                            <input type="text" class="form-control" id="note" name="note" >
                        </div></div>
                    </div>
                    <div class="col-lg-1">
                        <div class="form-group">
                            <button type="submit" class="btn btn-default">Add to List</button>
                        </div>
                    </div>
                </div>
            </form>
        ';

        return $ret;
    }

    public function help_content()
    {
        return <<<HTML
<ul><p>{$this->description}</p>
    <li>In notes, enter dates as YYYY-MM-DD to utlize past-date highlighting.
</ul>
HTML;
    }

}

WebDispatch::conditionalExec();


