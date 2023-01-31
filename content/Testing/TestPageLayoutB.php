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
    include(__DIR__.'/../PageLayoutB.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
class TestPageLayoutB extends PageLayoutB
{

    protected $title = "New Page";
    protected $description = "[New Page] is a new page.";
    protected $ui = TRUE;
    protected $connect = true;

    public $domNumRows = 2;
    public $domColSize = array(1,3,8);
    public $content = array(
        0 => array(
            'This is totally a test',
            'This is still a test',
            'A test, still'
        ),
        1 => array(
            'These are also tests',
            'These are also tests',
            'Hello'
        )
    );

    public function run()
    {
        $ret = '';
        $dbc = scanLib::getConObj();

        $prep = $dbc->prepare("SELECT upc, brand, description FROM products LIMIT 1");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            $ret .= $row['upc'] . ', ';
            $ret .= $row['brand'] . ', ';
            $ret .= $row['description'];
        }

        $this->content[1][2] = $ret;

        return false;
    }

}
WebDispatch::conditionalExec();
