<?php
if (!class_exists('WebDispatch')) {
    include(__DIR__.'/../common/ui/WebDispatch.php');
}
class PageLayoutTabs extends WebDispatch
{

    public $content = array(
        // each array is a different, numbered nav-tab
        0 => array(
            'OutputL' => "",
            'OutputR' => "",
            'OutputM' => "",
        )
    );
    public $navTabs = array();
    public $tabNames = array('one', 'two', 'three');

    public function body_content()
    {
        $ret = '';

        $this->run(); 

        return <<<HTML
<ul class="nav nav-tabs">
    <li class="nav-item">
        <a class="nav-link active" href="#one" data-toggle="tab" id="one-tab">{$this->tabNames[0]}</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="#two" data-toggle="tab" id="two-tab">{$this->tabNames[1]}</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="#three" data-toggle="tab" id="three-tab">{$this->tabNames[2]}</a>
    </li>
</ul>

<div style="padding: 25px;">
<div class="tab-content" id="myTabContent">
    <div class="tab-pane show active" id="one" role="tabpanel" aria-labelledby="one-tab">
            <div class="row">
                <div class="col-lg-4">{$this->content[0]['OutputL']}</div>
                <div class="col-lg-4">{$this->content[0]['OutputM']}</div>
                <div class="col-lg-4">{$this->content[0]['OutputR']}</div>
            </div>
    </div>
    <div class="tab-pane " id="two" role="tabpanel" aria-labelledby="two-tab">
        Some totally different stuff here 
    </div>
    <div class="tab-pane " id="three" role="tabpanel" aria-labelledby="three-tab">
        Here too, different stuff altogether
    </div>
</div>
</div>
HTML;
    }

    public function pageContent() {}

    public function preprocess() {
        return parent::preprocess();
    }

}
