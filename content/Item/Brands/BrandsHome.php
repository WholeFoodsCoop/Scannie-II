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
class BrandsHome extends PageLayoutA
{

    protected $title = "Brands Home Page"; 
    protected $description = "[]  ";
    protected $ui = TRUE; 
    protected $must_authenticate = true;
    protected $auth_types = array(2);

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();

        return parent::preprocess();
    }

    public function pageContent()
    {

        return <<<HTML
<div style="padding:25px; width: 100%;">
    <h4>{$this->title}</h4>
    <div class="row">
        <div class="col-lg-4">
            <ul>
                <li><a href="BrandFixer.php">Brand Fixer</a></li>
                <li><a href="BrandAbbrFix.php"><strong>FANNIE</strong> BrandAbbrFix</a></li>
            </ul>
        </div>
        <div class="col-lg-4"></div>
        <div class="col-lg-4"></div>
    </div>

    <div class="padding: 25px"></div>
</div>
HTML;
    }


    public function javascriptContent()
    {
        return <<<JAVASCRIPT
JAVASCRIPT;
    }

    public function cssContent()
    {
        return <<<HTML
HTML;
    }

}
WebDispatch::conditionalExec();
