<?php
include(__DIR__.'/../../config.php');
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
if (!class_exists('scanLib')) {
    include_once(__DIR__.'/../../common/lib/scanLib.php');
}
/*
**  @class AllergenFinderFixer
*   This class created for one time use to mass-edit all scale items to include sesame in allergens statement
*/
class AllergenFinderFixer extends PageLayoutA
{

    public function body_content()
    {
        $dbc = scanLib::getConObj();

        // get list of applicable items
        $items = array();
        $prep = $dbc->prepare("select upc, ingredients from ScaleIngredients where ingredients like '%esame%' group by upc;");
        //$prep = $dbc->prepare("select upc, ingredients from ScaleIngredients where ingredients like '%esame%'
        //    AND ingredients NOT LIKE '%ontains%' group by upc;");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            $items[$row['upc']] = $row['ingredients'];
        } 

        //$editP = $dbc->prepare("UPDATE ScaleIngredients SET ingredients = ? WHERE upc = ?"); 

        foreach ($items as $upc => $ingredients) {
            $tmpStr = '';
            $tmpArr = explode(" ", $ingredients);
            foreach ($tmpArr as $string) {
                if (strpos($string, 'Contains') !== false) {
                    //echo "<div>$upc $string</div>";
                    $tmpStr .= " $string Sesame,";
                } else {
                    $tmpStr .= " $string";
                }
            }
            echo "<div style=\"padding:15px;\">$upc $tmpStr</div>";
            //$editA = array($tmpStr, $upc);
            //$editR = $dbc->execute($editP, $editA);
        }

        return <<<HTML
Hello! This page didn't crash.
HTML;
    }

}

WebDispatch::conditionalExec();
