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
class MarginCalculator extends PageLayoutA 
{
    
    public $title = "Margin Calculator";
    public $description = "[] ";
    public $ui = FALSE;

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();

        return parent::preprocess();
    }

    public function pageContent()
    {           
        $cost = (!isset($cost)) ? null : $cost;
        $markup = (!isset($markup)) ? null : $markup;
        $dept_marg = (!isset($dept_marg)) ? null : $dept_marg;
    
        $ret = "";
        $ret .= '
            <form method="get">
            <div class="container-fluid" style="margin-top: 15px;">
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="small">Cost</label>
                            <input class="form-control form-control-sm" name="cost" id="cost" value="'.$cost.'" autofocus>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-group">
                            <label class="small" title="Leave blank if no shipping costs">Shipping %</label>
                            <input class="form-control form-control-sm" name="markup" id="markup" value="'.$markup.'">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="small">Margin</label>
                            <input class="form-control form-control-sm" name="dept_margin" id="margin" value="'.$dept_marg.'">
                        </div>
                    </div>
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
        $ret .= "<tr><td>Raw SRP</td><td><span id=\"calc1-srp-view\"</tr>";
        $ret .= "<tr><td>Rounded SRP</td><td><strong class='success'><span id=\"calc1_rounded\"></span><strong></tr>";

        $ret .= "</table>";
        $ret .= "</div>";
        $ret .= "</div>";


        $ret .= "</div>";
        $ret .= '<div style="height: 250px;"></div>';

        $calc2 = "";
        $calc2 .= '
            <form method="get">
            <div class="container-fluid" style="position: fixed; top: 49px;">
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="small">Normal Price</label>
                            <input class="form-control form-control-sm" name="normal_price" id="normal_price" autofocus>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-group">
                            <label class="small">% Off</label>
                            <input class="form-control form-control-sm" id="percent_off" name="percent_off">
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

        $calc2 .= "<tr><td>Raw SRP</td><td id=\"calc2_srp\"></td></tr>";
        $calc2 .= "<tr><td>Rounded Saleprice</td><td><strong class='success'><span id=\"calc2_rounded\"></span></strong></tr>";

        $calc2 .= "</table>";
        $calc2 .= "</div>";
        $calc2 .= "</div>";

        
        return <<<HTML
<div style="padding: 5px;">
    <a href="#" class="toggle-mode" data-target="calc1" id="toggle-first">Margin</a> | 
    <a href="#" class="toggle-mode" data-target="calc2">Percent</a>
</div>
<div class="mode-view" id="calc1">$ret</div>
<div class="mode-view" id="calc2">$calc2</div>
HTML;
    }
    
    
    public function javascriptContent()
    {
        return <<<JAVASCRIPT
$('#submit-calc1').click(function(){
    var cost = parseFloat($('#cost').val());
    var markup = parseFloat($('#markup').val());
    var margin = parseFloat($('#margin').val());
    var srp = 0.0;
    margin *= 0.01;
    markup *= 0.01;
    var adj_cost = (markup > 0) ? (cost * markup) + cost : cost;
    srp = adj_cost / (1 - margin);
    $('#calc1-srp-view').text(srp.toFixed(3));
    $.ajax({
        type: 'post',
        data: 'srp='+srp+'&round=true',
        url: 'marginCalcAjax.php',
        success: function(response)
        {
            $('#calc1_rounded').text(response);
        }
    });
});
$('#submit-calc2').click(function(){
    var price = $('#normal_price').val();
    var percent = $('#percent_off').val();
    var srp = 0;
    percent = percent * 0.01;
    srp = parseFloat(price) - (parseFloat(price) * parseFloat(percent));
    $('#calc2_srp').text(srp.toFixed(3));
    $.ajax({
        type: 'post',
        data: 'srp='+srp+'&round=true',
        url: 'marginCalcAjax.php',
        success: function(response)
        {
            $('#calc2_rounded').text(response);
        }
    });
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
a:hover {
    text-decoration: none;
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
