<?php
include(__DIR__.'/../../config.php');
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
/*
**  @class TurboItemEditor
*/
class TurboItemEditor extends PageLayoutA
{

    protected $must_authenticate = false;

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();
        $this->__routes[] = 'get<upcs>';

        return parent::preprocess();
    }

    public function getUpcsView()
    {

        $upcs = FormLib::get('upcs');
        $upcs = explode("\n", $upcs);

        return <<<HTML
<button style="z-index:9999; position: fixed; top: 70px; right: 0px; cursor:pointer; " class="btn btn-sm btn-warning" id="next">Next</button>
<button style="z-index:9999; position: fixed; top: 70px; right: 052px; cursor:pointer; " class="btn btn-sm btn-warning" id="prev">Prev</button>
<a href="TurboItemEditor.php" style="z-index:9999; position: fixed; top: 70px; right: 103px; cursor:pointer; " class="btn btn-sm btn-warning" id="prev">Home</a>
<div style="z-index:9999; position: fixed; top: 105px; right: 1px; box-shadow: 1px 1px grey;" id="list"></div>
<iframe id="coreIframe" src="../../../git/fannie/item/ItemEditorPage.php?searchupc={$upcs[0]}"
    width="1000px" height="700px"></iframe>
HTML;
    }

    public function pageContent()
    {

        return <<<HTML
<div style="padding: 25px">
    <div class="row">
        <div class="col-lg-4" id="col-1">
        </div>
        <div class="col-lg-4" id="col-2">
            <form method="get" action="TurboItemEditor.php">
                <div class="form-group">
                    Paste a list of UPCs
                </div>
                <div class="form-group">
                    <textarea name="upcs" rows=10 columns=10 class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-default" >Submit</button>
                </div>
            </form>
        </div>
        <div class="col-lg-4"></div>
    </div>
</div>
HTML;
    }

    public function cssContent()
    {
        return <<<HTML
.list-active { 
    background: lightblue;
}
HTML;
    }

    public function javascriptContent()
    {
        $upcs = FormLib::get('upcs');
        $jsonUpcs = explode("\n", $upcs);
        $jsonUpcs = json_encode($jsonUpcs);

        return <<<JAVASCRIPT
const upcs = $jsonUpcs;
var curIndex = 0;

const list = document.createElement('div');
list.innerHTML = "<b>List</b>";
list.style.background = "rgba(155,155,155,0.3)";
list.style.border = "1px solid lightgrey";
upcs.forEach((upc, index) => {
    let active = (index == 0) ? ' list-active ' : '';
    list.innerHTML += "<div class=\"list-upc "+active+"\" data-index=\"" + index + "\">"+upc+"</div>";
});
list.classList.add('alert-warning');
$('#list').append(list);

$('#coreIframe').css('width', window.innerWidth)
    .css('height', window.innerHeight)

$('#next').on('click', function() {
    let activeElm = $('#list').find('div.list-active');
    let activeID = $('#list').find('div.list-active').attr('data-index');
    curIndex++;
    let nextElm = $('#list').find("[data-index='" + (curIndex) + "']");

    activeElm.removeClass('list-active');
    nextElm.addClass('list-active');
    let nextUpc = $(".list-active").text();

    let newSrc = "../../../git/fannie/item/ItemEditorPage.php?searchupc=" + nextUpc;
    console.log(newSrc);
    $('#coreIframe').attr('src', newSrc);
});
$('#prev').on('click', function() {
    let activeElm = $('#list').find('div.list-active');
    let activeID = $('#list').find('div.list-active').attr('data-index');
    curIndex--;
    let nextElm = $('#list').find("[data-index='" + (curIndex) + "']");

    activeElm.removeClass('list-active');
    nextElm.addClass('list-active');
    let nextUpc = $(".list-active").text();

    let newSrc = "../../../git/fannie/item/ItemEditorPage.php?searchupc=" + nextUpc;
    console.log(newSrc);
    $('#coreIframe').attr('src', newSrc);
});

JAVASCRIPT;
    }


}
WebDispatch::conditionalExec();
