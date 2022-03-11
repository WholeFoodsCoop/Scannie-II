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
if (!class_exists('FpdfLib')) {
    include_once(__DIR__.'/../../../git/IS4C/fannie/admin/labels/pdf_layouts/FpdfLib.php');
}
/*
**  @class ReformatOneLabel 
*/
class ReformatOneLabel extends PageLayoutA
{


    public function body_content()
    {

        $long_text = strtolower(FormLib::get('ingredients'));
        $long_text = str_replace('ingredients', '', $long_text);
        $long_text = str_replace(':', '', $long_text);
        $long_text = str_replace('.', '', $long_text);
        $allergens = strtolower(FormLib::get('allergens'));
        $allergens = str_replace('contains', '', $allergens);
        $allergens = str_replace(':', '', $allergens);
        $allergens = str_replace('.', '', $allergens);

        $contains = "
Contains: " . ucwords($allergens);
        $contains = rtrim($contains, ',');
        $contains = rtrim($contains, ':');
        
        $ing = "Ingredients: " . ucwords($long_text);
        if ($allergens != null) {
            $ing .= "
";
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




        $ret = <<<HTML
<div class="container" style="padding-top: 15px">
    <div class="row">
        <div class="col-lg-1"></div>
        <div class="col-lg-10">
            <form>
                <div class="form-group">
                    <label><strong>Ingredients</strong></label>
                    <textarea name="ingredients" class="form-control" rows=6>$long_text</textarea>
                </div>
                <div class="form-group">
                    <label><strong>Allergents</strong></label>
                    <textarea name="allergens" class="form-control" rows=4>$allergens</textarea>
                </div>
                <div class="form-group">
                    <input type="submit">
                </div>
            </form>
            <table class="table table-bordered">
                $td
            </table>

            <div class="form-group">
                <label><strong>Formatted Text</strong></label>
                <textarea class="form-control" rows=6>$ing</textarea>
            </div>
        </div>
        <div class="col-lg-1"></div>
    </div>
</div>
HTML;

        return  $ret;
    }

}

WebDispatch::conditionalExec();
