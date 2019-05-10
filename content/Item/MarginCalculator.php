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
class MarginCalculator extends WebDispatch 
{
    
    protected $title = "Margin Calculator";
    protected $description = "[] ";
    protected $ui = FALSE;
    
    public function body_content()
    {           
    
        $ret = "";
        include(__DIR__.'/../../common/lib/PriceRounder.php');
        $rounder = new PriceRounder();
        
        $actualMargin = 0;
        $roundedSRP = 0;
        $rawSRP = 0;

        $dept_marg = FormLib::get('dept_margin', false);
        $cost = FormLib::get('cost');
        $markup = FormLib::get('markup');
        $adj_markup = ($markup > 1) ? $markup * 0.01 : $markup;

        if ($dept_marg != false) $_SESSION['dept_margin'] = $dept_marg;
        
        $ret .= '
            <form method="get">
            <div class="container-fluid" style="margin-top: 15px;">
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="small">Cost</label>
                            <input class="form-control form-control-sm" name="cost" value="'.$cost.'" autofocus>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-group">
                            <label class="small">% Markup</label>
                            <input class="form-control form-control-sm" name="markup" value="'.$markup.'">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="small">Margin</label>
                            <input class="form-control form-control-sm" name="dept_margin" value="'.$dept_marg.'">
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-group">
                            <label class="small">&nbsp;</label>
                            <div align="right"><button class="btn btn-sm">Submit</button>&nbsp;&nbsp;</div>
                        </div>
                    </div>
                </div>
            </form>
        ';
        $ret .= "<div class='container-fluid'>";
        $ret .= "<table class=\"table table-condensed table-small small\" align=\"center\">";

        //  Find SRP
        $dept_marg *= .01;
        $adjCost = ($adj_markup > 0) ? ($cost * $adj_markup) + $cost : $cost;
        $srp = $adjCost / (1 - $dept_marg);
        $round_srp = $rounder->round($srp);
        $ret .= "<tr><td>Raw SRP</td><td>" . sprintf('%.3f', $srp) . "</tr>";
        $ret .= "<tr><td>Rounded SRP</td><td><strong class='success'>" . $round_srp . "</strong></tr>";

        $ret .= "</table>";
        $ret .= "</div>";
        $ret .= "</div>";


        $ret .= "</div>";
        $ret .= '<div style="height: 250px;"></div>';

        
        return $ret;
    }
    
    
    public function javascriptContent()
    {
        return <<<JAVASCRIPT
$(window).resize(function(){
    window.resizeTo(300, 450);
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
//.form-control {
//    padding-bottom:5px;
//    background:
//        linear-gradient(
//            to left, 
//            rgba(92,7,52,1) 0%,
//            rgba(134,29,84,1) 12%,
//            rgba(255,93,177,1) 47%,
//            rgba(83,0,30,1) 100%
//        )
//        left 
//        bottom
//        #777    
//        no-repeat; 
//    background-size:100% 1px;
//}
HTML;
    }
    
}
WebDispatch::conditionalExec();
