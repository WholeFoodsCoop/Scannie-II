<?php
class marginCalcAjax {

    public function run()
    {
        if (!class_exists('FormLib')) {
            include_once('../../common/lib/FormLib.php');
        }
        $round = FormLib::get('round', false);
        if ($round != false) {
            $this->roundSrp();
        }
    }

    private function roundSrp()
    {
        include(__DIR__.'/../../common/lib/PriceRounder.php');
        $srp = FormLib::get('srp');
        $rounder = new PriceRounder();
        $new_srp = $rounder->round($srp);
        echo $new_srp;

        return false;
    }

}
$obj = new marginCalcAjax();
$obj->run();
