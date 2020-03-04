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
    include(__DIR__.'/../../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../../common/sqlconnect/SQLManager.php');
}
/**
 *  @class QuickScanner
 *  update array $upcs. scan a barcode to see if item
 *  is on list.
 */
class QuickScanner extends PageLayoutA 
{

    protected $title = "Audit Scanner";
    protected $description = "[Audit Scanner] is a light-weight, all around product
        scanner for use with iUnfi iPod Touch scanners.";
    protected $ui = true;
    protected $use_preprocess = TRUE;
    protected $enable_linea = true;

    public function body_content()
    {

        $this->addOnloadCommand("");
        $this->addScript('auditScanner.js?unique='.$timestamp);
        $ret = "";
        //$beep = $scannerConfig['scanBeep'];
        $beep = true;
        if ($beep == true) {
        }

        $upc = scanLib::upcPreparse(FormLib::get('upc'));

        $upcs = array(
'0076333205567' => 'x', 
'0076333205494' => 'x',
'0076333205493' => 'x',
'0076333205492' => 'x',
'0076333205490' => 'x',
'0076333205489' => 'x',
'0076333205485' => 'x',
'0076333205484' => 'x',
'0076333205481' => 'x',
'0076333205480' => 'x',
'0076333205479' => 'x',
'0076333205477' => 'x',
'0076333204365' => 'x',
'0076333204363' => 'x',
'0076333204356' => 'x',
'0076333204351' => 'x',
'0076333204349' => 'x',
'0076333204348' => 'x',
'0076333204345' => 'x',
'0076333204335' => 'x',
'0076333204333' => 'x',
'0076333204332' => 'x',
'0076333204330' => 'x',
'0076333204329' => 'x',
'0076333204328' => 'x',
'0076333203575' => 'x',
'0076333203277' => 'x',
'0076333202730' => 'x',
'0076333202675' => 'x',
'0076333202495' => 'x',
'0076333202470' => 'x',
'0076333202464' => 'x',
'0076333202430' => 'x',
'0007545090650' => 'x',
        );

        $table = "<table class=\"table small\">";
        foreach ($upcs as $plu => $price) {
            $tr_color = ($upc == $plu) ? "alert alert-warning" : "";
            $table .= ($upc == $plu) ? "<tr class=\"$tr_color\"><td>$plu</td><td>$price</td></tr>" : '';
            if ($upc == $plu) {
                $this->addOnloadCommand("
                    WebBarcode.Linea.emitTones(
                        [
                            { 'tone':100, 'duration':50 },
                            { 'tone':300, 'duration':50 },
                            { 'tone':600, 'duration':50 },
                            { 'tone':900, 'duration':50 },
                            { 'tone':1200, 'duration':50 },
                            { 'tone':1500, 'duration':50 },
                            { 'tone':1800, 'duration':50 },
                            { 'tone':2100, 'duration':50 },
                            { 'tone':2400, 'duration':50 },
                        ] 
                    );
                ");
            }
        }

        if ($table == '');


        $uid = '<span class="userSymbol-plus"><b>'.strtoupper(substr($username,0,1)).'</b></span>';

        $ret .= $this->form_content();

        return $ret.$table;
    }

    private function form_content($dbc)
    {

        $upc = ScanLib::upcPreparse(FormLib::get('upc'));
        $ret .= '';
        $ret .= '
            <div align="center">
                <form method="post" class="" id="my-form" name="main_form">
                    <input class="form-control input-sm info" name="upc" id="upc" value="'.$upc.'"
                        style="text-align: center; width: 140px; border: none;" pattern="\d*">
                    <input type="hidden" id="sku" name="sku" />
                    <input type="hidden" name="success" value="empty"/>
                    <span id="auto_par" class="sm-label"></span><span id="par_val" class="norm-text"></span>
                    <!-- <button type="submit" class="btn btn-xs"><span class="go-icon"></span></button> -->
                </form>
            </div>
        ';

        return $ret;

    }

    public function cssContent()
    {
        return <<<HTML
HTML;
    }

}
WebDispatch::conditionalExec();
