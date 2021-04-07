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
class SignAlias extends PageLayoutA
{

    protected $title = "Sign Alias";
    protected $description = "[Sign Alias] define special instructions 
        for printing signs for groups of items.";
    protected $ui = TRUE;

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();
        $this->__routes[] = 'post<aliasselect>';
        $this->__routes[] = 'post<newaliastype>';

        return parent::preprocess();
    }

    public function postNewaliastypeHandler()
    {
        $dbc = scanLib::getConObj('SCANALTDB');
        $type = FormLib::get('newaliastype');
        $desc = FormLib::get('newaliasdescription');
        $brand = FormLib::get('newaliasbrand');
        $args = array($brand, $desc, $type);
        $prep = $dbc->prepare("INSERT INTO signAlias (brand, description,
            type) VALUES (?, ?, ?)");
        $res = $dbc->execute($prep, $args);

        return header('location: SignAlias.php');
    }

    public function postAliasselectHandler()
    {
        $dbc = scanLib::getConObj('SCANALTDB');
        $id = FormLib::get('aliasselect');
        $text = FormLib::get('upcs');
        $upcs = explode("\r\n", $text);
        $prep = $dbc->prepare("INSERT INTO signAliasMap (upc, aliasID)
            VALUES (?, ?)");
        foreach ($upcs as $upc) {
            $upc = ScanLib::padUPC($upc);
            $args = array($upc, $id);
            $res = $dbc->execute($prep, $args);
        }

        return header('location: SignAlias.php');
    }

    public function pageContent()
    {
        $ret = '';
        $dbc = scanLib::getConObj('SCANALTDB');

        $td = "";
        $aliases = array();
        $aliasSelect = "";
        $prep = $dbc->prepare("SELECT * FROM signAliasView ORDER BY brand");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            $td .= "<tr>";
            $td .= "<td>{$row[1]}</td>";
            $td .= "<td>{$row[2]}</td>";
            $td .= "<td>{$row[3]}</td>";
            $td .= "<td>{$row[0]}</td>";
            $td .= "</tr>";
            $td .= "</tr>";
        }
        echo $dbc->error();

        $td2 = "";
        $prep = $dbc->prepare("SELECT * FROM signAlias");
        $res = $dbc->execute($prep);
        $types = array('', 'Abbreviated Signage', 'List Signage');
        while ($row = $dbc->fetchRow($res)) {
            $row[3] = $types[$row[3]];
            $td2 .= "<tr>";
            $td2 .= "<td>{$row[0]}</td>";
            $td2 .= "<td>{$row[1]}</td>";
            $td2 .= "<td>{$row[2]}</td>";
            $td2 .= "<td>{$row[3]}</td>";
            $td2 .= "</tr>";
            $td2 .= "</tr>";
            $aliases[$row[0]] = $row[2];
            $aliasSelect .= "<option value=\"{$row[0]}\">{$row[1]}: {$row[2]}</option>";
        }
        echo $dbc->error();

        $td3 = "";

        $prep = $dbc->prepare("SELECT s.*, p.brand, p.description FROM signAliasMap AS s 
            INNER JOIN is4c_op.products AS p ON s.upc=p.upc ORDER BY s.aliasID");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            $td3 .= "<tr>";
            $td3 .= "<td>{$row[0]}</td>";
            $td3 .= "<td>{$row[1]}</td>";
            $td3 .= "<td>{$row[2]}</td>";
            $td3 .= "<td>{$row[3]}</td>";
            $td3 .= "</tr>";
            $td3 .= "</tr>";
        }
        echo $dbc->error();

        return <<<HTML
<div class="row" style="padding: 15px;">
    <div class="col-lg-4">
        <h4>Sign Alias View</h4>
        <table class="table table-bordered table-sm small"><thead></thead><tbody>$td</tbody></table>
        <h4>Sign Alias Table</h4>
        <table class="table table-bordered table-sm small"><thead></thead><tbody>$td2</tbody></table>
        <h4>Sign Alias Map</h4>
        <table class="table table-bordered table-sm small"><thead></thead><tbody>$td3</tbody></table>
    </div>
    <div class="col-lg-4">
        <h4>Add items to alias</h4>
        <form name="addlisttoalias" method="post" action="SignAlias.php">
            <div class="form-group">
                <select name="aliasselect" class="form-control">
                    $aliasSelect
                </select>
            </div>
            <div class="form-group">
                <textarea class="form-control" name="upcs"></textarea>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-default">Submit</button>
            </div>
        </form>
    </div>
    <div class="col-lg-4">
        <h4>Create New Alias</h4>
        <form name="createnewalias" method="post" action="SignAlias.php">
            <div class="form-group">
                <input name="newaliasdescription" type="text" class="form-control" placeholder="description">
            </div>
            <div class="form-group">
                <input name="newaliasbrand" type="text" class="form-control" placeholder="brand">
            </div>
            <div class="form-group">
                <select name="newaliastype" class="form-control">
                    <option value="1">One Sign For All Varieties</option>
                    <option value="2">LIST style sign</option>
                </select>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-default">Submit</button>
            </div>
        </form>
    </div>
</div>

HTML;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
JAVASCRIPT;
    }

}
WebDispatch::conditionalExec();
