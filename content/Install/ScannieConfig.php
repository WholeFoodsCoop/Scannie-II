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
class ScannieConfig extends PageLayoutA 
{

    protected $title = "Scannie Config";
    protected $description = "[Scannie Config] install or configure Scannie Version 2 Setup.";
    protected $ui = TRUE;
    protected $must_authenticate = true;

    public function cssContent()
    {
        return <<<HTML
.label {
    border: 1px solid transparent;
    background: rgba(0,0,0,0);
}
.form-control {
    margin-top: 5px;
}
.table-borderless td,
.table-borderless th {
        border: 0;
}
HTML;
    }

    public function body_content()
    {
        $ret = '';
        $dbc = scanLib::getConObj('SCANALTDB');
        $prep = $dbc->prepare("SELECT 1 FROM ScannieAuth LIMIT 1");
        $res = $dbc->execute($prep);
        $row = $dbc->numRows($res);
        if ($row !== 1) {
            $prep = $dbc->prepare("CREATE TABLE IF NOT EXISTS ScannieAuth 
                name VARCHAR(64), email VARCHAR(255), type TINYINT(), hash VARCHAR(255), 
                id AUTO_INCREMENT, PRIMARY KEY(id))");
            $res = $dbc->execute($prep);
            $dbc->numRows($res);
        }


        if (!file_exists(__DIR__."/../../config.php")) {
            $ret .= "<div class=\"alert alert-danger\">Scannie config 
                file does not exist. Create file named 'config.php' in 
                Scannie root directory.</div>";
        } else {
            include(__DIR__.'/../../config.php');
        }
        if (!is_writable(__DIR__."/../../config.php")) {
            $ret .= "<div class=\"alert alert-danger\">Scannie config 
                file is not writable.</div>";
        }

        //echo $this->formValuesToJSON();
        //echo $this->formValuesToPHP();

        return <<<HTML
<div class="container-fluid" style="padding-top: 25px;"><form method="post">
    $ret
    <div class="row">
        <div class="col-lg-1"></div>
        <div class="col-lg-7">
            <div class="alert alert-danger">Warning, this page doesn't actually do anything.</div>
            <table class="table table-borderless table-striped"><thead></thead><tbody>
                <tr>
                    <td><strong class="form-control label">Scannie Root Directory</strong></td>
                    <td><input class="form-control" name="my_rootdir" value="$MY_ROOTDIR" /></td>
                </tr>
                <tr>
                    <td><strong class="form-control label">Operational Database</strong></td>
                    <td><input class="form-control" name="scandb" value="$SCANDB" /></td>
                </tr>
                <tr>
                    <td><strong class="form-control label">Alternate Database</strong class="form-control label"></td>
                    <td><input class="form-control" name="scanaltdb" value="$SCANALTDB" /></td>
                </tr>
                <tr>
                    <td><strong class="form-control label">Transactional Database</strong class="form-control label"></td>
                    <td><input class="form-control" name="scantransdb" value="$SCANTRANSDB" /></td>
                </tr>
                <tr>
                    <td><strong class="form-control label">DBMS Host</strong class="form-control label"></td>
                    <td><input class="form-control" name="scanhost" value="$SCANHOST" /></td>
                </tr>
                <tr>
                    <td><strong class="form-control label">DBMS User</strong class="form-control label"></td>
                    <td><input class="form-control" name="scanuser" value="$SCANUSER" /></td>
                </tr>
                <tr>
                    <td><strong class="form-control label">DBMS Password</strong class="form-control label"></td>
                    <td><input type="password" class="form-control" name="scanpass" value="$SCANPASS" /></td>
                </tr>
                <tr>
                    <td><strong class="form-control label">IS4C Office Root Directory</strong class="form-control label"></td>
                    <td><input class="form-control" name="fannie_rootdir" value="$FANNIE_ROOTDIR" /></td>
                </tr>
                <tr>
                    <td><strong class="form-control label">Alt. Dev Office I</strong class="form-control label"></td>
                    <td><input class="form-control" name="fannie_corey_root" value="$FANNIE_COREY_ROOT" /></td>
                </tr>
                <tr>
                    <td><strong class="form-control label">Alt. Dev Office II</strong class="form-control label"></td>
                    <td><input class="form-control" name="fannie_andy_root" value="$FANNIE_ANDY_ROOT" /></td>
                </tr>
                <tr>
                    <td><strong class="form-control label">Alt. Dev Office III</strong class="form-control label"></td>
                    <td><input class="form-control" name="host" value="" /></td>
                </tr>
            </tbody></table>
            <table class="table table-borderless table-striped" style="border: 1px solid lightgrey;"><thead></thead><tbody>
                <tr>
                    <td><strong class="form-control label">Username</strong></td>
                    <td><input type="text" class="form-control" name="un" /></td>
                    <td><strong class="form-control label">Password</strong></td>
                    <td><input type="password" class="form-control" name="pw" value=""/></td>
                    <td><button type="submit" class="form-control btn btn-primary" value="">SAVE</button></td>
                </tr>
            </tbody></table>
        </div>
    </div>
</form></div>
HTML;
    }

    private function formValuesToPHP()
    {
        $php = "<?php\r\n";
        $php .= '$HOST'." = ".'$_SERVER[\'HTTP_HOST\'];'."\r\n";
        $VALUES = array('my_rootdir', 'scandb', 'scanaltdb', 'scantransdb', 'scanhost', 'scanuser', 'scanpass', 'fannie_rootdir');
        foreach ($VALUES as $k => $value) {
            $curv = null;
            $curv = $_POST[$value];
            $php .= "$".strtoupper($value)." = \"$curv\";\r\n";
        }
        $php .= "\r\n";
        file_put_contents('test.php', $php);

        return false;
    }

    private function formValuesToJSON()
    {
        $json = "[{\r\n";
        $VALUES = array('my_rootdir', 'scandb', 'scanaltdb', 'scantransdb', 'scanhost', 'scanuser', 'scanpass', 'fannie_rootdir');
        foreach ($VALUES as $k => $value) {
            $curv = null;
            $curv = $_POST[$value];
            $comma = (isset($VALUES[$k+1])) ? ',' : '';
            $json .= "\t\"$".strtoupper($value)."\": \"$curv\"$comma\r\n";
        }
        $json .= "}]\r\n";
        file_put_contents('test.json', $json);

        return false;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
});
JAVASCRIPT;
    }

}
WebDispatch::conditionalExec();
