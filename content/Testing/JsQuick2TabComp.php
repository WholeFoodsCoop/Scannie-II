<?php
include(__DIR__.'/../../config.php');
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
/*
**  @class JsQuick2TabComp 
*   Compare values between any two SQL tables with a 
*   common joining identifier 
**/
class JsQuick2TabComp extends PageLayoutA
{

    protected $must_authenticate = false;

    public function preprocess()
    {
        $this->displayFunction = $this->getView();
        $this->__routes[] = 'post<sopformat>';

        return parent::preprocess();
    }

    private function getTableCols($tables)
    {
        $dbc = scanLib::getConObj(); 
        $cols = array();

        $prep = $dbc->prepare("DESC {$tables[0]}");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            $cols[] = $row[0];
        }

        return $cols;
    }

    private function getTableData($table, $cols, $joinOn)
    {
        $dbc = scanLib::getConObj(); 
        $data = array();

        $prep = $dbc->prepare("SELECT * FROM $table");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            foreach ($cols as $col) {
                $data[$row[$joinOn]][$col] = $row[$col];
            }
        }

        return $data;
    }

    public function getView()
    {
        $tables = FormLib::get('tables', array());
        $column = FormLib::get('column', 'false');
        $joinOn = FormLib::get('joinOn', 'upc');
        $showA = FormLib::get('showA', 'false'); 
        $showB = FormLib::get('showB', 'false'); 
        $data = array();
        $td = '';
        $thead = '<tr><th>Identifier</th>';
        if ($showA != 'false')
            $thead .= "<th>$showA</th>";
        if ($showB != 'false')
            $thead .= "<th>$showB</th>";
        $thead .= '<th>Column</th><th>Table A</th><th>Table B</th></tr>';

        $dbc = scanLib::getConObj(); 
        $cols = $this->getTableCols($tables);
        $colsHTML = '<strong>Table Columns</strong>: ';
        foreach ($cols as $col) {
            $colsHTML .= "$col, ";
        }

        return <<<HTML
<div class="row" style="padding: 25px;">
    <div class="col-lg-2">
        <h4>Comp Tables</h4>
        <form>
        <div class="form-group">
            <label>Table A</label>
            <input type="text" name="tables[]" placeholder="table A (as database.table) " class="form-control" value="$tables[0]" required />
        </div>
        <div class="form-group">
            <label>Table B</label>
            <input type="text" name="tables[]" placeholder="table B (as database.table) " class="form-control" value="$tables[1]" required />
        </div>
        <div class="form-group">
            <label>JOIN on Column</label>
            <input type="text" name="joinOn" placeholder="column to join on" class="form-control" value="$joinOn" required />
        </div>
        <div class="form-group">
            <label>Comp Only Col (default=false)</label>
            <input type="text" name="column" placeholder="colum 2 check (opt)" class="form-control" value="$column" />
        </div>
        <div class="form-group">
            <label>(opt) Always Show Col</label>
            <input type="text" name="showA" placeholder="always show this col in out (opt)" class="form-control" value="$showA" />
        </div>
        <div class="form-group">
            <label>(opt) Always Show Col</label>
            <input type="text" name="showB" placeholder="always show this col in out (opt)" class="form-control" value="$showB" />
        </div>
        <div class="form-group">
            <input type="submit" class="form-control btn btn-info" />
        </div>
        </form>
    </div>
    <div class="col-lg-9">
        $colsHTML
        <div style="height: 10px"></div>
        <h4>Discrepancies Found</h4>
        <table class="table table-bordered table-consensed small" id="mytable">
            <thead>
                $thead
            </thead>
            <tbody id="mytbody">
                <tr></tr>
                $td
            </tbody>
        </table>
    </div>
    <div class="col-lg-1">
    </div>
</div>
HTML;
    }

    public function cssContent()
    {
        return <<<HTML
label {
    display: inline-block;
    background-color: #FAFAFA;
    padding: 5px;
    margin: 0px;
    border-top: 1px solid lightgrey;
    border-left: 1px solid lightgrey;
    border-right: 1px solid lightgrey;
    border-top-left-radius: 3px;
    border-top-right-radius: 3px;
    font-size: 13px;
}
HTML;
    }

    public function javascriptContent()
    {
        $tables = FormLib::get('tables', array());
        $column = FormLib::get('column', 'false');
        $column = json_encode($column);
        $joinOn = FormLib::get('joinOn', 'upc');
        $showA = FormLib::get('showA', 'false'); 
        $showA = json_encode($showA);
        $showB = FormLib::get('showB', 'false'); 
        $showB = json_encode($showB);

        $dbc = scanLib::getConObj();
        $cols = $this->getTableCols($tables);

        if (count($tables) > 0) {
            $tableA = $this->getTableData($tables[0], $cols, $joinOn);
            $tableB = $this->getTableData($tables[1], $cols, $joinOn);

            $tableA = json_encode($tableA);
            $tableB = json_encode($tableB);
            $cols = json_encode($cols);
        }

        return <<<JAVASCRIPT
const tableA = $tableA
const tableB = $tableB
const cols = $cols
const alwaysShowColA = $showA;
const alwaysShowColB = $showB;
const column = $column;
const onlyContainsNumbers = (str) => /^-?\d*\.?\d*$/.test(str);

$.each(tableA, function(upc, row) {
    if (column != 'false') {
        // only show a specific colum
        let col = column;
        if (tableA.hasOwnProperty(upc) && tableB.hasOwnProperty(upc)) {
            if (tableA[upc].hasOwnProperty(col) && tableB[upc].hasOwnProperty(col)) {
                let a = tableA[upc][col];
                let b = tableB[upc][col];

                // special case cast as float
                if (onlyContainsNumbers(a) && onlyContainsNumbers(b)) {
                    a = parseFloat(a);
                    b = parseFloat(b);
                }

                if (a != b) {

                    let newhtml = '';
                    newhtml += '<tr><td>'+upc+'</td>';
                    if (alwaysShowColA != 'false') {
                        newhtml += '<td>'+tableA[upc][alwaysShowColA]+'</td>';
                    }
                    if (alwaysShowColB != 'false') {
                        newhtml += '<td>'+tableA[upc][alwaysShowColB]+'</td>';
                    }
                    newhtml += '<td>'+col+'</td>';
                    newhtml += '<td>'+a+'</td>';
                    newhtml += '<td>'+b+'</td></tr>';
                    $('#mytable > tbody:last-child').append(newhtml);
                }
            }
        }
    } else {
        $.each(cols, function(k, col) {
            if (tableA.hasOwnProperty(upc) && tableB.hasOwnProperty(upc)) {
                if (tableA[upc].hasOwnProperty(col) && tableB[upc].hasOwnProperty(col)) {
                    let a = tableA[upc][col];
                    let b = tableB[upc][col];

                    // special case cast as float
                    if (onlyContainsNumbers(a) && onlyContainsNumbers(b)) {
                        a = parseFloat(a);
                        b = parseFloat(b);
                    }

                    if (a != b) {
                        let newhtml = '';
                        newhtml += '<tr><td>'+upc+'</td>';
                        if (alwaysShowColA != 'false') {
                            newhtml += '<td>'+tableA[upc][alwaysShowColA]+'</td>';
                        }
                        if (alwaysShowColB != 'false') {
                            newhtml += '<td>'+tableA[upc][alwaysShowColB]+'</td>';
                        }
                        newhtml += '<td>'+col+'</td>';
                        newhtml += '<td>'+a+'</td>';
                        newhtml += '<td>'+b+'</td></tr>';
                        $('#mytable > tbody:last-child').append(newhtml);
                    }
                }
            }
        });
    }
});
JAVASCRIPT;
    }

}
WebDispatch::conditionalExec();
