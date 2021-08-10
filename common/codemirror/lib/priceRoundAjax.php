<?php
/**
 *  @class priceRoundAjax
 *  Invoke priceRounder in javascript via ajax
 *  parameter POST/GET "round" != false
 *  returns float
 */
class priceRoundAjax {

    public function run()
    {
        if (!class_exists('FormLib')) {
            include_once('FormLib.php');
        }
        $round = FormLib::get('round', false);
        if ($round != false) {
            $this->roundPrice();
        }
    }

    private function roundPrice()
    {
        include(__DIR__.'/PriceRounder.php');
        $price = FormLib::get('price');
        $rounder = new PriceRounder();
        $new_price = $rounder->round($price);
        echo $new_price;

        return false;
    }

}
$obj = new priceRoundAjax();
$obj->run();
