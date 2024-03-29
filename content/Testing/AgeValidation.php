<?php
include(__DIR__.'/../../config.php');
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
/*
**  @class AgeValidation
*/
class AgeValidation extends PageLayoutA
{

    protected $must_authenticate = false;
    protected $ui = false;

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();

        return parent::preprocess();
    }
    

    public function validateDate($date) {
        $y = substr($date, 0, 4);
        $m = substr($date, 4, 2);
        $d = substr($date, -2);

        $formattedDate = "$y-$m-$d";
        try {
            $bdate = new DateTime($formattedDate);
        } catch(Exception $e) {
            return 'error';
        }

        $minDate = new DateTime();
        $minDate = $minDate->sub(new DateInterval('P21Y'));

        $aDate = $minDate->format('Y-m-d');

        if ($minDate > $bdate) {
            return true;
        } else {
            return $minDate->format('Y-m-d');
        }

    }

    public function pageContent()
    {
        $year = date('Y');
        $message = "";
        $hues = array('prompt' => "#004080", 'good' => "#318000", 'bad' => "#801100");
        $input = FormLib::get('age', false);
        $hue = $hues['prompt'];

        if ($input !== false && strlen($input) == 8) {
            $res = $this->validateDate($input);
            if ($res === true) {
                $message = "Customer Age Verified";
                $hue = $hues['good'];
            } elseif ($res == 'error') {
                $message = "INVALID DATE ENTERED";
                $hue = $hues['bad'];
            } else {
                $message = "Minimum DOB is $res";
                $hue = $hues['bad'];
            }
        }

        return <<<HTML

<div class="container-fluid" style="padding-top: 25px;">
<div class="row">
    <div class="col-lg-4"> </div>
    <div class="col-lg-4">
        <h4 style="text-align: center; padding: 15px;">21+ Age Verification</h4>
        <div style="background-color: $hue; height: 200px; width: 100%; border-radius: 3px;">
            <p style="text-align: center; color: white; font-size: 18px; padding-top: 25px;"><strong>Customer Age</strong></p>
            <form name="myform" action="AgeValidation.php" method="post">
                <div class="form-group" align="center">
                    <input type="text" name="age" class="form-control" style="width: 300px" pattern="\d*" />
                </div>
            </form>
            <p style="text-align: center; color: white; font-size: 18px; ">Type customer birthdate YYYYMMDD</p>
            <p style="text-align: center; color: white; font-size: 18px; ">$message</p>
        </div>
    <div style="font-size: 14; text-align: center; padding: 25px;">Whole Foods Community Co-op &copy; $year</div>
    </div>
    <div class="col-lg-4"> </div>
</div>
HTML;
    }

    public function cssContent()
    {
        return <<<HTML
HTML;
    }

}
WebDispatch::conditionalExec();
