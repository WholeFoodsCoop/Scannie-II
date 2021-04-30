<?php
include(__DIR__.'/../../config.php');
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
if (!class_exists('FpdfLib')) {
    include_once(__DIR__.'/FpdfLib.php');
}
class DeliIngredientSOPFix extends PageLayoutA
{

    protected $must_authenticate = true;
    protected $cap_strs = array('MN', 'WI', 'TVP', 'TSP', 'BBQ');

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();

        return parent::preprocess();
    }
   
    private function getUniqueScaleIngredients()
    {
        $dbc = scanLib::getConObj();
        $uniqueUpcs = array();

        $prep = $dbc->prepare("SELECT s.upc FROM ScaleIngredients AS s INNER JOIN (SELECT upc, ingredients FROM ScaleIngredients GROUP BY upc HAVING COUNT(upc)>1) AS dup ON s.upc=dup.upc WHERE s.ingredients != dup.ingredients;");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            $uniqueUpcs[$row['upc']] = $row['upc'];
        }

        return $uniqueUpcs;
    }

    public function updateText($upc)
    {
        //$upc = '0029623000000'; //for testing a single upc, overwrite $upc
        $dbc = scanLib::getConObj();

        $args = array($upc);
        $prep = $dbc->prepare("SELECT text FROM scaleItems WHERE plu = ?");
        $res = $dbc->execute($prep, $args);
        $text = null;
        while ($row = $dbc->fetchRow($res)) {
            $text = $row['text'];
            $text = strtolower($text);
            $text = ucwords($text);
            $text = FpdfLib::strtolower_inpara($text);
            $text = FpdfLib::abbreviation_to_upper($text);
        }
        $args = array($text, $upc);
        $prep = $dbc->prepare("UPDATE scaleItems SET text = ? WHERE plu = ?");
        $res = $dbc->execute($prep, $args);

        $args = array($upc);
        $prep = $dbc->prepare("SELECT ingredients FROM ScaleIngredients WHERE upc = ? GROUP BY upc");
        $res = $dbc->execute($prep, $args);
        $ingredients = null;
        while ($row = $dbc->fetchRow($res)) {
            $ingredients = $row['ingredients'];
            $ingredients = strtolower($ingredients);
            $ingredients = ucwords($ingredients);
            $ingredients = FpdfLib::strtolower_inpara($ingredients);
            $ingredients = FpdfLib::abbreviation_to_upper($ingredients);
        }
        if ($ingredients != null) {
            $args = array($ingredients, $upc);
            $prep = $dbc->prepare("UPDATE ScaleIngredients SET ingredients = ? WHERE upc = ?");
            $res = $dbc->execute($prep, $args);
        }

        return false;
    }

    public function pageContent()
    {
        $dbc = scanLib::getConObj();

        $args = array(228);
        $prep = $dbc->prepare("SELECT upc FROM products WHERE department = ? GROUP BY upc");
        $res = $dbc->execute($prep, $args);
        $upcs = array();
        while ($row = $dbc->fetchRow($res)) {
            $upcs[$row['upc']] = $row['upc']; 
        }
        
        $uniqueUpcs = $this->getUniqueScaleIngredients();

        $upcs = array();

        $dbc->startTransaction();
        //$this->updateText($upc); // for testing, update a single $upc
        foreach ($upcs as $upc) {
            // don't update products that have unique ingredients lists
            if (!array_key_exists($upc, $uniqueUpcs)) {
                // un comment this line to update all text in list of upcs
                //$this->updateText($upc);
            }
        }
        $dbc->commitTransaction();

        return <<<HTML
Updating some scale item text
HTML;
    }


}
WebDispatch::conditionalExec();
