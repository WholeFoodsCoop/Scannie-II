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
*   Precursor to an FDF->PDF generator to fill out NCG templates for bulk bin labels
*   Note: enter $organic and $local to match what is being printed for sanity check that 
*   product types match the type being printed. This will go away once we can call pdftk in PHP,
*   but for now, only one type of bin labels can be generated at a time, eg - local organic.
*   I think the only thing storeID is being used for is generating the movement numbers.  
*   Note that movement is not being re-calculated here, you may want to run the list of 
*   UPCs through a sign printing page in Fannie and select HYBRID to re-calculate movement.
*   Params: @storeID INT,  @organic bool, @local bool, @upcs array of INT
*/
class FDFBulkFormatter 
{

    public $storeID = 2;
    public $parMod = array(3, 7);
    // once pdftk is handled by PHP, a dimension may be added to $data for each flag set (conv, local conv, organic, local organic)
    // but for now, this will have to be handled manually
    public $organic = true;
    public $local = false;
    // I also need to flag whether the price is current or comming from a price change batch
    public $upcs = array(
'604', '489', '355', '277'
);

    public function __construct()
    {
        $tmp = array();
        foreach ($this->upcs as $upc) {
            $tmp[] = scanLib::padUPC($upc);
        }
        $this->upcs = $tmp;
    }

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
        list($inStr, $args) = $dbc->safeInClause($this->upcs);
        $prep = $dbc->prepare("SELECT * FROM NutriFactOptItems WHERE upc IN ($inStr)");
        $res = $dbc->execute($prep, $args);
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

        $date = new DateTime();
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
        list($inStr, $args) = $dbc->safeInClause($this->upcs);

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
p.normal_price,
u.long_text AS text,
#bl.salePrice AS normal_price,
ven.vendorName,
p.auto_par,
CASE 
    WHEN p.numflag & (1 << 16) <> 0 THEN true
    ELSE false 
END AS organic, 
CASE WHEN p.local <> 0 THEN true 
    ELSE false
END AS local,
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
WHERE p.upc IN ($inStr)
#AND bl.batchID IN (19697,19452)
AND p.store_id = ?
GROUP BY p.upc
ORDER BY v.sku, r.reviewed
        ");
        $args[] = $this->storeID;
        $res = $dbc->execute($prp, $args);
        //var_dump($res);
        while ($row = $dbc->fetchRow($res)) {
            $upc = $row['upc'];
            $desc = $row['description'];
            $brand = $row['brand'];
            $sku = $row['sku'];
            $price = $row['normal_price']."/lb";
            $vendorName = $row['vendorName'];
            $ingredients = $row['text'];
            $ingredients = preg_replace('#<br\s*/?>#i', "\n", $ingredients);
            $movement = round($row['auto_par'] * $this->parMod[$this->storeID - 1], 1);
            $local = $row['local'];
            $organic = $row['organic'];

            // this chunk is temporary, for sanity check, only until pdftk is handled by PHP
            if ($local != $this->local || $organic != $this->organic) {
                $mode1 = ($this->organic == true) ? 'organic' : 'conventional';
                $mode2 = ($this->local == true) ? 'local' : 'not local';
                echo "This set: '$mode1' & '$mode2' \n";
                if ($local != $this->local) echo "local discrepancy found for $upc \n";
                if ($organic != $this->organic) echo "organic discrepancy found for $upc \n";
                echo "FDF FILES HAVE NOT BEEN GENERATED \n";

                return false;
            }

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
            $data[$upc]['Eff. Date'] = $date->format('Y-m-d') . ' | MT: ' . $movement;

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
                $data[$upc]['Item Name 2nd Line'] = ''; // be sure to enter a blank line in second row
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

        $fileNames = $this->createFileNames($data);

        // NEXT - for every 4 items, produce a .FDF file
        $sheetNum = 0;
        $quad = 1;
        $total = 0;
        //$f = fopen('fdfs/'.uniqid().'.fdf', 'w');
        $f = fopen('fdfs/'.$fileNames[$sheetNum].'.fdf', 'w');
        fwrite($f, 'fdfs/'.$fdfHead, 1000);
        //echo "\n".count($data);
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
                //$f = fopen('fdfs/'.uniqid().'.fdf', 'w');
                $f = fopen('fdfs/'.$fileNames[$sheetNum].'.fdf', 'w');
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

    private function createFileNames($data)
    {
        $names = array();
        $i = 0;
        $j = 0;
        $last = null;
        foreach ($data as $upc => $row) {
            if (!isset($names[$j])) {
                $names[$j] = '';
            }
            $names[$j] .= substr($upc, -3) . ".";
            $i++;
            if ($i % 4 == 0) {
                $names[$j] = rtrim($names[$j], '.');
                $j++;
            }
        }

        return $names;
    }

}

$obj = new FDFBulkFormatter();
$obj->run();
