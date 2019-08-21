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
class Popups extends PageLayoutA 
{

    protected $title = "";
    protected $description = "[] ";
    protected $ui = TRUE;

    public function cssContent()
    {
return <<<HTML
HTML;
    }

    public function body_content()
    {

        return <<<HTML
<div class="container-fluid" style="margin-top: 20px;">
    <ul>
        <li><a href="Handheld.html">Handheld (iPod like GUI)</a></li>
        <li><a href="MarginCalculator.html">Margin Calculator</a></li>
        <li><a href="SpecialCalculator.html">Special Calculator</a></li>
    </ul>
</div>
HTML;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
JAVASCRIPT;
    }

}
WebDispatch::conditionalExec();
