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
class BrandAbbrFix extends PageLayoutA
{

    protected $title = "True Brand Editor"; 
    protected $description = "[]  ";
    protected $ui = TRUE; 
    protected $must_authenticate = true;
    protected $auth_types = array(2);

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();
        $this->__routes[] = 'post<tid>';
        $this->__routes[] = 'post<insert>';

        return parent::preprocess();
    }

    public function postTidHandler()
    {
        $tid = FormLib::get('tid');
        $type = FormLib::get('type');
        $text = FormLib::get('text');
        $text = htmlspecialchars_decode($text);
        $dbc = scanLib::getConObj();

        $args = array($text, $tid);
        $prep = $dbc->prepare("UPDATE BrandAbbrFix SET $type = ? WHERE id = ? ");
        $res = $dbc->execute($prep, $args);

        //$er = ($dbc->error() != false) ? '&error='.$dbc->error() : '';

        return false;
    }

    private function getTrueBrandList()
    {
        $dbc = scanLib::getConObj();
        $td = '';
        $tb = array();
        $dups = '';

        $prep = $dbc->prepare("SELECT * FROM BrandAbbrFix");
        $res = $dbc->execute($prep);
        while ($row = $dbc->fetchRow($res)) {
            $td .= sprintf("<tr><td>%s</td><td class=\"editable\" data-type=\"badName\">%s</td><td class=\"editable\" data-type=\"goodName\">%s</td></tr>",
                $row['id'],
                $row['badName'],
                $row['goodName']
            );

            if (in_array($row['badName'], $tb)) {
                $dups .= sprintf("<tr style=\"background-color: tomato;\"><td>%s</td><td class=\"editable\" data-type=\"badName\">%s</td><td class=\"editable\" data-type=\"goodName\">%s</td></tr>",
                    $row['id'],
                    $row['badName'],
                    $row['goodName']
                );
            } else {
                $tb[] = $row['badName'];
            }
        }


        echo $dbc->error();

        return $dups.$td;
    }

    public function postInsertHandler()
    {
        $badName = FormLib::get('badName');
        $badName = trim($badName, " \n\r\t\v\x00");
        $goodName = FormLib::get('goodName');
        $goodName = trim($goodName, " \n\r\t\v\x00");
        $goodName = strtoupper($goodName);
        $dbc = scanLib::getConObj();

        $args = array($badName, $goodName);
        $prep = $dbc->prepare("INSERT INTO BrandAbbrFix (badName, goodName) 
            VALUES (?, ?)");
        $res = $dbc->execute($prep, $args);

        $er = ($dbc->error() != false) ? '&error='.$dbc->error() : '';

        return header("location: BrandAbbrFix.php$er");
    }

    public function pageContent()
    {
        return <<<HTML
<div class="row" style="width: 100%;">
    <div class="col-lg-4">
        <div style="padding: 25px; margin: 25px; border: 1px solid lightgrey;" id="myform">
            <h5 style="color: darkred">FANNIE OP</h5>
            <form action="BrandAbbrFix.php" method="post" id="">
                <label for="upc">Bad Name</label>
                <div class="form-group">
                    <input type="text" class="form-control" name="badName" autocomplete="off"/>
                </div>
                <label for="upc">Good Name</label>
                <div class="form-group">
                    <input type="text" class="form-control" name="goodName" style="text-transform:uppercase;" autocomplete="off"/>
                </div>
                <div class="form-group">
                    <input type="submit" class="btn btn-defaul" name="submit" />
                </div>
                <input type="hidden" class="btn btn-defaul" name="insert" value="1" />
            </form>
        </div>
        <ul>
            <li><a href="BrandFixer.php">Brand Fixer</a></li>
        </ul>
    </div>
    <div class="col-lg-6" style="padding: 25px">
        <table class="table table-bordered table-sm small"><thead></thead><tbody>{$this->getTrueBrandList()}</table>
    </div>
    <div class="col-lg-2">
    </div>
</div>
HTML;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
var last = '';
$('.editable').each(function(){
    $(this).attr('contentEditable', 'true');
});
$('.editable').focusin(function(){
    last = $(this).text();
});
$('.editable').focusout(function(){
    console.log(last);
    var current = $(this).text();
    var tid = $(this).parent().find('td:eq(0)').text();
    current = encodeURIComponent(current);
    var type = $(this).attr('data-type');
    var elm = $(this);
    if (current != last) {
        $.ajax({
            type: 'post',
            data: 'tid='+tid+'&type='+type+'&text='+current,
            url: 'BrandAbbrFix.php',
            success: function(resp) {
                console.log('SUCCESS');
                elm.animate({backgroundColor: '#AFE1AF'}, 'slow')
                    .animate({backgroundColor: '#FFFFFF'}, 'slow');
            },
            error: function(resp) {
                console.log('[ERROR]'+resp);
                elm.animate({backgroundColor: 'tomato'}, 'slow')
                    .animate({backgroundColor: '#FFFFFF'}, 'slow');
            }
        });
    }
    last = '';
});

$(window).scroll(function () {
    var scrollTop = $(this).scrollTop();
    if (scrollTop > 300) {
        $('#myform')
            .css('position', 'fixed')
            .css('top', '0px')
            .css('left', '0px')
            .css('border', '1px solid grey');
    } else {
        $('#myform')
            .css('position', 'relative')
            .css('border', '1px solid white');
    }
});
JAVASCRIPT;
    }

    public function cssContent()
    {
        return <<<HTML
HTML;
    }

}
WebDispatch::conditionalExec();
