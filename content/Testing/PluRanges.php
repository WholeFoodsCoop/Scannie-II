<?php
include(__DIR__.'/../../config.php');
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
/*
**  @class PluRanges
**  find "pockets" of null, potential new UPCs
**  also find open 1-4 digit PLUs
*/
class PluRanges extends PageLayoutA
{
    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();

        return false;
    }

    public function pageContent()
    {
        $ret = '';
        include(__DIR__.'/../../config.php');
        $dbc = scanLib::getConObj();

        $prep = $dbc->prepare("select upc from products WHERE upc LIKE '002%000000'");
        $res = $dbc->execute($prep);
        $upcs = array();
        while ($row = $dbc->fetchRow($res)) {
            $upc = $row['upc'];
            $upcs[] = $upc;
        }

        $pockets = array();
        foreach ($upcs as $k => $upc) {
            $upc = substr($upc,2,5);
            $next = substr($upcs[$k+1],2,5);
            $diff = $next - $upc;
            $diff--;
            if ($diff > 0) {
                $pockets[$upc] = $diff;
            }
        }
        foreach ($pockets as $upc => $pocket) {
            $size = $pocket-1;
            if ($pocket > 30 && $pocket < 99) {
                $ret .= "starting upc: $upc, pocket: $pocket<span class='size' style='width: $size;'></span><br/>";
            }
        }

        $upcs = array();
        $usables = array();
        $freePlus = '<table ><tr>';
        $prep = $dbc->prepare("SELECT upc FROM products WHERE upc <= 9999 GROUP BY upc;");
        $res = $dbc->execute($prep);
        $temp = null;
        while ($row = $dbc->fetchRow($res)) {
            $upc = intval($row['upc']);
            $upcs[$upc] = 1;
        }
        $j = 0;
        for ($i=1; $i<=9999;$i++) {
            if (array_key_exists($i, $upcs) == false) {
                $freePlus .= "<td>$i</td>";
                if ($j % 19 == 0 && $j > 2) {
                    $freePlus .= "</tr><tr>";
                }
                $j++;
            }
        }
        $freePlus .= '</tr></table>';

        
        return <<<HTML
<div class="row" style="padding: 25px;">
    <div class="col-lg-4">
        <div><label><strong>Open PLU Ranges</strong> (Scale Item PLUs)</label></div>
        $ret
    </div>
    <div class="col-lg-4">
        <div><label><strong>Free PLUs</strong></label> (Unused PLUs)</div>
        $freePlus
    </div>
</div>
HTML;
    }

    public function javascriptContent()
    {
        return <<<HTML
HTML;
    }

    public function cssContent()
    {
        return <<<HTML
table, tr, td {
    border: 1px dotted grey;
}
HTML;
    }

}
WebDispatch::conditionalExec();
