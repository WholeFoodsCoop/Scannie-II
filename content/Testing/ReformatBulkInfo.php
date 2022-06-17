<?php
include(__DIR__.'/../../config.php');
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
if (!class_exists('scanLib')) {
    include_once(__DIR__.'/../../common/lib/scanLib.php');
}
if (!class_exists('FpdfLib')) {
    include_once(__DIR__.'/../../../git/IS4C/fannie/admin/labels/pdf_layouts/FpdfLib.php');
}
/*
**  @class ReformatBulkInfo 
*   Used once to mass-reformat scale info for bulk items. execution line commented out for safety
*/
class ReformatBulkInfo 
{


    public function run()
    {
        $formFields = array(
            "Item Name 1st Line",
            "Item Name 2nd Line",
            "Ingredients",
            "Price",
            "PLU#",
            "Company Name and Address",
            "UNFI#",
            "Eff. Date",
            "Serving Size",
            "Calories ",
            "Total Fat Amount",
            "Total Fat Percent",
            "Sat Fat Amount",
            "Sat Fat Percent",
            "Trans Fat Amount",
            "Trans Fat Percent",
            "Cholesterol Amount",
            "Cholesterol Percent",
            "Sodium Amount",
            "Sodium Percent",
            "Total Carb Amount",
            "Fiber Amount",
            "Fiber Percent",
            "Total Sugars Amount",
            "Added Sugars Amount",
            "Added Sugars Percent",
            "Protein Amount",
            "Vitamin D Percent",
            "Calcium Percent",
            "Iron Percent",
            "Potassium Percent",
            " Total Carb Percent"
        );

        $dbc = scanLib::getConObj();
        $data = array();

        $prp = $dbc->prepare("
            SELECT * FROM GenericUpload
        ");
        $res = $dbc->execute($prp);
        while ($row = $dbc->fetchRow($res)) {
            $upc = $row['upc'];
            $contains = "

Contains: " . ucwords($row['aller']);
            
            $ing = "Ingredients: " . ucwords($row['ingr']);
            if ($row['aller'] != null) {
                $ing .= $contains;
            }

            $ing = FpdfLib::strtolower_inpara($ing);
            $ing = str_replace("(", " (", $ing);
            $ing = str_replace("  ", " ", $ing);

            $ing = str_replace("organic", "Organic", $ing);
            $ing = str_replace("Certified", "", $ing);

            $ing = str_replace(";", ", ", $ing);

            $ing = rtrim($ing, ";");
            $ing = rtrim($ing, ",");

            $data[$upc] = $ing;
        }

        $prep = $dbc->prepare("UPDATE productUser SET long_text = ? WHERE upc = ?");
        $td = '';
        foreach ($data as $upc => $contains) {
            $td .= "<tr>";
            $td .= "<td>$upc</td><td>$contains</td>";
            $td .= "</tr>";
            // un-comment out following line to update re-formatted long_text
            //$dbc->execute($prep, array($contains, $upc));    
        }

        echo <<<HTML
<table class="table table-bordered">
    $td
</table>
HTML;

        return  false;
    }

}

$obj = new ReformatBulkInfo();
$obj->run();
