<?php
if (!class_exists('WebDispatch')) {
    include(__DIR__.'/../common/ui/WebDispatch.php');
}
class PageLayoutB extends WebDispatch
{
    public $content = array(); // page content
    public $domColSize = array(4, 4, 4); // size of bootstrap scaffolding
    public $domNumRows = 1; // default number of columns

    public function body_content()
    {
        $this->run();

        $ret = '';
        for ($i=0; $i<$this->domNumRows; $i++) {
            $ret .= "<div class=\"row\">";
            for ($j=0; $j<3; $j++) {
                $ret .= "<div class=\"col-lg-{$this->domColSize[$j]}\">{$this->content[$i][$j]}</div>";
            }
            $ret .= "</div>";
        }

        return <<<HTML
<div style="padding: 25px;">
    $ret
</div>
HTML;
    }

    public function pageContent() {}

    public function preprocess() {
        return parent::preprocess();
    }

    public function cssContent()
    {
    }

}
