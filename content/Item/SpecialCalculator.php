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
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
class SpecialCalculator extends PageLayoutA 
{
    
    protected $title = "Margin Calculator";
    protected $description = "[] ";
    protected $ui = FALSE;

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();

        return parent::preprocess();
    }

    public function pageContent()
    {           
    
        $ret = "";
        $ret .= '
            <form method="get">
            <div class="container-fluid" style="margin-top: 15px;">
                <p>Based on Discount</p>
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="small">Net Price</label>
                            <input class="form-control form-control-sm" name="price" id="price" autofocus>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-group">
                            <label class="small">% Discount</label>
                            <input class="form-control form-control-sm" name="discount" id="discount">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-4">
                        <div class="form-group">
                            <label class="small">&nbsp;</label>
                            <div align="right"><button class="btn btn-sm" id="submit-calc1" onclick="event.preventDefault(); return false;">Submit</button>&nbsp;&nbsp;</div>
                        </div>
                    </div>
                </div>
            </form>
        ';
        $ret .= "<div class='container-fluid'>";
        $ret .= "<table class=\"table table-condensed table-small small\" align=\"center\">";
        $ret .= "<tr><td>Original Price</td><td><span id=\"calc1-srp-view\"</tr>";

        $ret .= "</table>";
        $ret .= "</div>";
        $ret .= "</div>";


        $ret .= "</div>";
        $ret .= '<div style="height: 250px;"></div>';

        $calc2 = "";
        $calc2 .= '
            <form method="get">
            <div class="container-fluid" style="position: fixed; top: 49px;">
                <p>Based on MSRP</p>
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="small">SRP</label>
                            <input class="form-control form-control-sm" name="srp" id="srp" autofocus>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-group">
                            <label class="small">Margin</label>
                            <input class="form-control form-control-sm" id="margin" name="margin">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-group">
                            <label class="small">&nbsp;</label>
                            <div align="right"><button class="btn btn-sm" id="submit-calc2" onclick="event.preventDefault(); return false;">Submit</button>&nbsp;&nbsp;</div>
                        </div>
                    </div>
                </div>
            </form>
        ';
        $calc2 .= "<div class='container-fluid'>";
        $calc2 .= "<table class=\"table table-condensed table-small small\" align=\"center\">";

        $calc2 .= "<tr><td>Estimated Cost</td><td><strong class='success'><span id=\"calc2_rounded\"></span></strong></tr>";

        $calc2 .= "</table>";
        $calc2 .= "</div>";
        $calc2 .= "</div>";

        
        return <<<HTML
<div style="padding: 5px;">
    <a href="#" class="toggle-mode" id="toggle-first" data-target="calc1">Find Price</a> | 
    <a href="#" class="toggle-mode" data-target="calc2">FP2</a>
</div>
<div class="mode-view" id="calc1">$ret</div>
<div class="mode-view" id="calc2">$calc2</div>
HTML;
    }
    
    
    public function javascriptContent()
    {
        return <<<JAVASCRIPT
$('#submit-calc1').click(function(){
    var price = parseFloat($('#price').val());
    var discount = parseFloat($('#discount').val());

    var og_price = price / (1.0 - (discount * 0.01));

    $('#calc1-srp-view').text(og_price.toFixed(3));

});
$('#submit-calc2').click(function(){
    var srp = $('#srp').val();
    var margin = $('#margin').val();
    margin = margin * 0.01;
    //cost = srp - srp * margin;
    var cost = srp - (srp * margin);
    $('#calc2_rounded').text(cost.toFixed(3));
});
$('.mode-view').each(function(){
    $(this).hide();
});
$('#calc1').show();
$('#toggle-first').css('font-weight', 'bold');
$(window).resize(function(){
    window.resizeTo(300, 450);
});
$('.toggle-mode').click(function(){
    $('.toggle-mode').each(function(){
        $(this).css('font-weight', 'normal');
    });
    $(this).css('font-weight', 'bold');
    var target = $(this).attr('data-target');
    $('.mode-view').each(function(){
        $(this).hide();
    });
    $('#'+target).show();

});
JAVASCRIPT;
    }

    public function cssContent()
    {
        return <<<HTML
body, html {
    overflow-y: hidden;
}
.input-group-addon {
    width: 70px;
}
.btn-sm {
    border: 1px solid lightgrey;
}
HTML;
    }
    
}
WebDispatch::conditionalExec();
