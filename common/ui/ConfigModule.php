<?php
class config
{
    public $vars = array();
    
    public function getConfig()
    {
        $this->vars = $this->getDefinedVars();

        return false;
    }

    private function getDefinedVars()
    {
        include(__DIR__.'/../../config.php');
        $vars = get_defined_vars();

        return $vars;
    }
}
