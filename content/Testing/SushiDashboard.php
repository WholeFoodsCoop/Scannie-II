<?php
include(__DIR__.'/../../config.php');
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include_once(__DIR__.'/../../common/sqlconnect/SQLManager.php');
}
/*
**  @class SushiDashboard 
*/
class SushiDashboard extends PageLayoutA
{

    protected $must_authenticate = false;

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();
        $this->__routes[] = 'get<test>';
        $this->__routes[] = 'post<test>';

        return parent::preprocess();
    }

    public function pageContent()
    {

        return <<<HTML
<div style="height: 25px"></div>
<div align="">
    <div class="row">
        <div class="col-lg-4" id="col-1"></div>
        <div class="col-lg-4" id="col-2">
            <div class="card">
                <div class="card-body">
                    <div class="card-header">
                        <div class="card-title"><h4>Ace Sushi Dashboard</h4></div>
                        <p class="card-text"><i>Get your sushi sales here</i></p>
                    </div>
                    <form action="SushiSalesII.php" method="get">
                        <div style="padding: 10px"></div>
                        <ul>
                            <ul>
                                <label>This Week</label>
                                <li> <button name="dayofweek" value="monday this week">Monday</button> </li>
                                <li> <button name="dayofweek" value="tuesday this week">Tuesday</button> </li>
                                <li> <button name="dayofweek" value="wednesday this week">Wednesday</button> </li>
                                <li> <button name="dayofweek" value="thursday this week">Thursday</button> </li>
                                <li> <button name="dayofweek" value="friday this week">Friday</button> </li>
                                <li> <button name="dayofweek" value="saturday this week">Saturday</button> </li>
                                <li> <button name="dayofweek" value="sunday this week">Sunday</button> </li>
                            </ul>
                            <div style="padding: 10px"></div>
                            <ul>
                                <label>Last Week</label>
                                <li> <button name="dayofweek" value="monday last week">Monday</button> </li>
                                <li> <button name="dayofweek" value="tuesday last week">Tuesday</button> </li>
                                <li> <button name="dayofweek" value="wednesday last week">Wednesday</button> </li>
                                <li> <button name="dayofweek" value="thursday last week">Thursday</button> </li>
                                <li> <button name="dayofweek" value="friday last week">Friday</button> </li>
                                <li> <button name="dayofweek" value="saturday last week">Saturday</button> </li>
                                <li> <button name="dayofweek" value="sunday last week">Sunday</button> </li>
                                <li>&nbsp;<a href="SushiSales.php">Last Weekend (Friday, Saturday, & Sunday) in one!</a> </li>
                            </ul>
                        </ul>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4"></div>
    </div>
</div>
HTML;
    }

    public function cssContent()
    {
        return <<<HTML
button {
    background: rgba(0, 0, 0, 0);
    border: 0px solid transparent;
    color: #0056B3;
}
label {
    width: 100%;
    border-bottom: 1px solid grey;
}
HTML;
    }

    public function javascriptContent()
    {
        return <<<JAVASCRIPT
JAVASCRIPT;
    }

}
WebDispatch::conditionalExec();
