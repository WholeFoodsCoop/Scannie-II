<?php
if (!class_exists('WebDispatch')) {
    include(__DIR__.'/../common/ui/WebDispatch.php');
}
class PageLayoutA extends WebDispatch
{

    public function body_content()
    {
        return <<<HTML
{$this->displayFunction}
HTML;
    }

    public function pageContent() {}

    public function preprocess() {
        return parent::preprocess();
    }

}
