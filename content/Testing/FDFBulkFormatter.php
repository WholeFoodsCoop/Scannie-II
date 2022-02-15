<?php
include(__DIR__.'/../../config.php');
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
if (!class_exists('scanLib')) {
    include_once(__DIR__.'/../../common/lib/scanLib.php');
}
/*
**  @class FDFBulkFormatter 
*/
class FDFBulkFormatter 
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
            "Total Carb Percent"
        );

// FDF Heading 
$fdfHead = "%FDF-1.2
%âãÏÓ
1 0 obj 
<<
/FDF 
<<
/Fields [
";

// EOF format 
$fdfTail .= "]
>>
>>
endobj 
trailer

<<
/Root 1 0 R
>>
%%EOF";

        $dbc = scanLib::getConObj();
        $items = array();

        $prp = $dbc->prepare("
SELECT 
p.upc, 
p.brand, 
CASE
    WHEN u.description IS NOT NULL THEN u.description
    ELSE p.description
END AS description,
v.sku,
si.text,
p.normal_price,
ven.vendorName,
n.servingSize, n.numServings, n.calories, n.fatCalories, n.totalFat, n.saturatedFat, n.transFat, n.cholesterol, n.sodium, n.totalCarbs, n.fiber, n.sugar, n.protein
FROM products AS p 
LEFT JOIN MasterSuperDepts AS m ON p.department=m.dept_ID
LEFT JOIN vendorItems AS v ON v.upc=p.upc AND v.vendorID = 1
LEFT JOIN prodReview AS r ON r.upc=p.upc
LEFT JOIN scaleItems AS si ON si.linkedPLU=p.upc
LEFT JOIN productUser AS u ON u.upc=p.upc
LEFT JOIN vendors AS ven ON ven.vendorID=p.default_vendor_id
LEFT JOIN NutriFactReqItems AS n ON n.upc=p.upc
WHERE p.last_sold > NOW() - INTERVAL 30 DAY
AND p.upc < 1000
AND p.default_vendor_id = 1
AND p.inUse = 1
AND p.store_id = 2
GROUP BY p.upc
ORDER BY v.sku, r.reviewed
        ");
        $res = $dbc->execute($prp);
        while ($row = $dbc->fetchRow($res)) {
            $upc = $row['upc'];
            $desc = $row['description'];
            $brand = $row['brand'];
            $sku = $row['sku'];
            $price = $row['normal_price'];
            $vendorName = $row['vendorName'];
            $ingredients = $row['text'];

            $servingSize = $row['servingSize'];
            $numServings = $row['numServings'];
            $calories = $row['calories'];
            //$fatCalories = $row['fatCalories'];
            $totalFat = $row['totalFat'];
            $saturatedFat = $row['saturatedFat'];
            $transFat = $row['transFat'];
            $cholesterol = $row['cholesterol'];
            $sodium = $row['sodium'];
            $totalCarbs = $row['totalCarbs'];
            $fiber = $row['fiber'];
            $sugar = $row['sugar'];
            $protein = $row['protein'];

            if (!isset($data[$upc])) {
                foreach ($formFields as $field) {
                    $data[$upc][$field] = '';
                }
            }

            /*
                Split Description Into 2 Lines If Necessary 
            */
            $lines = array();
            if (strstr($desc, "\r\n")) {
                $lines = explode ("\r\n", $desc);
            } elseif (strlen($desc) > 16) {
                $wrp = wordwrap($desc, strlen($desc)/1.5, "*", false);
                $lines = explode('*', $wrp);
            } else {
                $lines[0] = $desc;
            }
            if (count($lines) > 1) {
                $data[$upc]['Item Name 1st Line'] = $lines[0];
                $data[$upc]['Item Name 2nd Line'] = $lines[1];
            } else {
                $data[$upc]['Item Name 1st Line'] = $lines[0];
                $data[$upc]['Ingredients'] = $ingredients;
            }

            // get all the straight forward data
            $data[$upc]['PLU#'] = substr($upc, -3);
            $data[$upc]['Price'] = $price;
            $data[$upc]['UNFI#'] = $sku;
            $data[$upc]['Company Name and Address'] = $vendorName;
            $data[$upc]['Serving Size'] = $servingSize;
            $data[$upc]['Calories'] = $calories;
            $data[$upc]['Total Fat Amount'] = $totalFat;
            $data[$upc]['Sat Fat Amount'] = $saturatedFat;
            $data[$upc]['Trans Fat Amount'] = $transFat;
            $data[$upc]['Cholesterol Amount'] = $cholesterol;
            $data[$upc]['Sodium Amount'] = $sodium;
            $data[$upc]['Total Carb Amount'] = $totalCarbs;
            $data[$upc]['Fiber Amount'] = $fiber;
            $data[$upc]['Total Sugars Amount'] = $sugar;
            $data[$upc]['Protein Amount'] = $protein;
        }


        // NEXT - for every 4 items, produce a .FDF file
        $sheetNum = 0;
        $quad = 1;
        $f = fopen('fdfs/'.uniqid().'.fdf', 'w');
        fwrite($f, 'fdfs/'.$fdfHead, 1000);
        foreach ($data as $upc => $row) {
            if ($quad == 5) {
                $quad = 1;
                $sheetNum++;
                fwrite($f, $fdfTail, 1000);
                $f = fopen('fdfs/'.uniqid().'.fdf', 'w');
                fwrite($f, $fdfHead, 1000);
            }
            //echo $str = $row['PLU#'] . "\n";
            foreach ($row as $k => $v) {
                //echo "$k $quad" . ", " . $v  . "\n";
                $fdfLine = "<<
/T ($k $quad) /V ($v)
>>";
                fwrite($f, $fdfLine, 1000);
            }
            $quad++;
        }


/*  this is the format FDF format. /T is the name of the field, /V is the value to enter
    each page contains 4 bulk templates. ever varaible has a " n" suffix at the end, numbered 1..4
    these correspond with the 4 bulk item templates on the page.

        $fdf.= "<<
/T ($str) /V ($info)
>>";

*/



        return true;
    }

}

$obj = new FDFBulkFormatter();
$obj->run();
