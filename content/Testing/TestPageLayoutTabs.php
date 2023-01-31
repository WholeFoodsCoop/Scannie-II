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
    include(__DIR__.'/../PageLayoutTabs.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
class TestPageLayoutTabs extends PageLayoutTabs
{

    protected $title = "New Page";
    protected $description = "[New Page] is a new page.";
    protected $ui = true;
    protected $connect = true;

    public function run()
    {
        $ret = '';
        $dbc = scanLib::getConObj();

        $prep = $dbc->prepare("SELECT upc, brand, description FROM products LIMIT 5");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            $ret .= $row['upc'] . ', ';
            $ret .= $row['brand'] . ', ';
            $ret .= $row['description'];
        }

        $this->content[0]['OutputL'] = $ret;

        return false;
    }

//    public function javascriptContent()
//    {
//        return <<<JAVASCRIPT
//$('.nav-link').click(function(){
//    $('.nav-link').each(function(){
//        $(this).removeClass('active');
//    });
//    $('.tab-pane').each(function(){
//        $(this).removeClass('active');
//    });
//});
//JAVASCRIPT;
//    }

}
WebDispatch::conditionalExec();
