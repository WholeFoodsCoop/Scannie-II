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

    private function getDVs()
    {
        $dbc = scanLib::getConObj();
        $prep = $dbc->prepare('SELECT * FROM NutriFactStd');
        $res = $dbc->execute($prep);
        $info = array();
        while ($row = $dbc->fetchRow($res)) {
            $info[$row['name']] = $row['units'];
        }

        return $info;
    }
    
    private function getDVValue($stdv, $val)
    {
        $val = preg_replace('/[^0-9.]+/', '', $val);
        if ($val != 0) {
            $retVal = 100 * ($val / $stdv);
        } else {
            $retVal = null;
        }
        $retVal = floor($retVal);

        return $retVal;
    }

    private function getItemNutrientVals()
    {
        $dbc = scanLib::getConObj();
        $info = array();
        $prep = $dbc->prepare("SELECT * FROM NutriFactOptItems WHERE upc IN (
        '0000000000191','0000000000277','0000000000829','0000000000282','0000000000341','0000000000355','0000000000376','0000000000425','0000000000446','0000000000531','0000000000604','0000000000706','0000000000721','0000000000735','0000000000279','0000000000362','0000000000402','0000000000489','0000000000551','0000000000725','0000000000737','0000000000749','0000000000886'
)
");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            $info[$row['upc']][$row['name']] = $row['percentDV'];
        }

        return $info;
    }

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
            " Total Carb Percent" // [sic]
        );

        $DVS = $this->getDVs();
        $nutrients = $this->getItemNutrientVals();

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
$fdfTail = "]
>>
>>
endobj 
trailer

<<
/Root 1 0 R
>>
%%EOF";

        $dbc = scanLib::getConObj();
        $data = array();

        $prp = $dbc->prepare("
SELECT 
p.upc, 
p.brand, 
CASE
    WHEN u.description IS NOT NULL THEN u.description
    ELSE p.description
END AS description,
v.sku,
#CASE
#    WHEN si.text != '' AND si.text IS NOT NULL THEN si.text
#    ELSE u.long_text
#END AS text,
#si.text,
#p.normal_price,
u.long_text AS text,
bl.salePrice AS normal_price,
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
LEFT JOIN batchList AS bl ON p.upc=bl.upc
WHERE p.upc IN (
'0000000000191','0000000000277','0000000000829','0000000000282','0000000000341','0000000000355','0000000000376','0000000000425','0000000000446','0000000000531','0000000000604','0000000000706','0000000000721','0000000000735','0000000000279','0000000000362','0000000000402','0000000000489','0000000000551','0000000000725','0000000000737','0000000000749','0000000000886'
)
AND bl.batchID IN (19697,19452)
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
            $data[$upc]['Ingredients'] = $ingredients;

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
                $data[$upc]['Item Name 2st Line'] = ''; // be sure to enter a blank line in second row
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

            // now staring in on percents
            $data[$upc]['Total Fat Percent'] = $this->getDVValue($DVS['Fat'], $totalFat)."%";
            $data[$upc]['Sat Fat Percent'] = $this->getDVValue($DVS['Sat Fat'], $saturatedFat)."%";
            $data[$upc]['Cholesterol Percent'] = $this->getDVValue($DVS['Cholesterol'], $cholesterol)."%";
            $data[$upc]['Sodium Percent'] = $this->getDVValue($DVS['Sodium'], $sodium)."%";
            $data[$upc]['Fiber Percent'] = $this->getDVValue($DVS['Fiber'], $fiber)."%";
            $data[$upc][' Total Carb Percent'] = $this->getDVValue($DVS['Carbohydrate'], $totalCarbs)."%";
            //$data[$upc]['Added Sugars Percent'] = $this->getDVValue($DVS['Added Sugars'], $addedSugar); // doesn't exist yet

            if (isset($nutrients[$upc]['Vitamin D'])) {
                $data[$upc]['Vitamin D Percent'] = $nutrients[$upc]['Vitamin D']."%";
            } else {
                $data[$upc]['Vitamin D Percent'] = "0%";
            }
            if (isset($nutrients[$upc]['Calcium'])) {
                $data[$upc]['Calcium Percent'] = $nutrients[$upc]['Calcium']."%";
            } else {
                $data[$upc]['Calcium Percent'] = "0%";
            }
            if (isset($nutrients[$upc]['Iron'])) {
                $data[$upc]['Iron Percent'] = $nutrients[$upc]['Iron']."%";
            } else {
                $data[$upc]['Iron Percent'] = "0%";
            }
            if (isset($nutrients[$upc]['Potassium'])) {
                $data[$upc]['Potassium Percent'] = $nutrients[$upc]['Potassium']."%";
            } else {
                $data[$upc]['Potassium Percent'] = "0%";
            }
        }

        // NEXT - for every 4 items, produce a .FDF file
        $sheetNum = 0;
        $quad = 1;
        $total = 0;
        $f = fopen('fdfs/'.uniqid().'.fdf', 'w');
        fwrite($f, 'fdfs/'.$fdfHead, 1000);
        echo "\n".count($data);
        foreach ($data as $upc => $row) {
            $total++;
            //echo "\nQuad: $quad";
            if ($total == count($data)) { // before checking to create a new sheet, check if end of list met and end process before creating a new sheet
                //$quad = 1;
                //$sheetNum++;
                //$f = fopen('fdfs/'.uniqid().'.fdf', 'w');
                //fwrite($f, $fdfHead, 1000); 
                foreach ($row as $k => $v) {
                    //echo "$k $quad" . ", " . $v  . "\n";
                    $fdfLine = "<<
/T ($k $quad) /V ($v)
>>";
                    fwrite($f, $fdfLine, 1000);
                }
                fwrite($f, $fdfTail, 1000);
            } else if ($quad == 5) { 
                //echo "\nQuad: $quad, Total: $total";
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
