<?php
if (!class_exists('Search')) {
    require('Search.php');
}
Class coreNav
{
    public $ln = array(
        'Home' => "__DIR__./../../Home/Home.php",
    );

    public function run()
    {
        $ret = '';
        $menu = new coreNav();
        $ret .= $menu->navBar();
        return $ret;
    }

    public function navBar()
    {
        include(__DIR__.'/../../config.php');
        $helptoggle = <<<JAVASCRIPT
var hidden = $('#help-contents').is(':visible');
if (hidden == false) {
    $('#help-contents').show();
} else {
    $('#help-contents').hide();
}
JAVASCRIPT;

        $DIR = __DIR__;
        $user = null;
        $ud = "";
        if (!empty($_COOKIE['user_name'])) {
            $user = $_COOKIE['user_name'];
            $ud = '<span class="userSymbol"><b>'.strtoupper(substr($user,0,1)).'</b></span>';
            $type = $_COOKIE['user_type'];
        }
        if (empty($user)) {
            $user = 'Generic User';
            $logVerb = 'Login';
            $link = "<a class='nav-login' href='http://{$MY_ROOTDIR}/auth/Login.php'>[{$logVerb}]</a>";
        } else {
            $logVerb = 'Logout';
            $link = "<a class='nav-login' href='http://{$MY_ROOTDIR}/auth/logout.php'>[{$logVerb}]</a>";
        }
        $loginText = '
            <div style="color: #cacaca; margin-left: 25px; margin-top: 5px;" align="center">
                <span style="color:#cacaca">'.$ud.'&nbsp;'.$user.'</span><br/>
            '.$link.' 
            </div>
       ';

        $admin = "";
        if ($type > 1) {
            $admin = <<<HTML
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
            onclick="dropdownMenuClick('adminMenuOpts');">
            Admin 
        </a>
        <div class="dropdown-menu" aria-labelledby="navbarDropdown" id="adminMenuOpts">
          <a class="dropdown-item" href="http://{$MY_ROOTDIR}/content/Reports/DBA.php">DBA/</a>
        </div>
      </li>
HTML;

        }

        return <<<HTML
<script type="text/javascript">{$this->js()}</script>
<img class="backToTop collapse no-print" id="backToTop" src="http://$MY_ROOTDIR/common/src/img/upArrow.png" />
<div id="navbar-placeholder" style="height: 5px; background-color: black; 
    background: repeating-linear-gradient(#343A40,  #565E66, #343A40 5px);
    cursor: pointer;"
    onclick="$('#site-navbar').show(); $(this).hide(); return false;"></div>
<nav class="navbar navbar-expand-md navbar-dark bg-custom mynav no-print" id="site-navbar">
  <a class="navbar-brand" href="http://{$MY_ROOTDIR}">Sv2</a>
  <button class="navbar-toggler" type="button" data-toggle="collapse" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation"
    data-target="navbarSupportedContent" onclick="navbarSupportedContent();" return false;">
    <span class="navbar-toggler-icon"></span>
  </button>
  <div class="collapse navbar-collapse" id="navbarSupportedContent">
    <ul class="navbar-nav mr-auto">
      <li class="nav-item dropdown active">
        <a class="nav-link dropdown-toggle" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
            onclick="dropdownMenuClick('corePosMenuOpts');">
            CORE-POS
        </a>
        <div class="dropdown-menu" aria-labelledby="navbarDropdown" id="corePosMenuOpts">
          <a class="dropdown-item" href="http://{$FANNIE_ROOTDIR}">WFC - Duluth</a>
          <a class="dropdown-item" href="http://{$FANNIE_COREY_ROOT}">DEV - Corey(1)</a>
          <a class="dropdown-item" href="http://{$FANNIE_COREY2_ROOT}">DEV - Corey(2)</a>
          <a class="dropdown-item" href="http://{$FANNIE_ANDY_ROOT}">DEV - Andy</a>
        </div>
      </li>
      <!--
      <li class="nav-item">
        <a class="nav-link" >Link</a>
      </li>
      -->
      $admin
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
            onclick="dropdownMenuClick('productsMenuOpts');">
            Products 
        </a>
        <div class="dropdown-menu" aria-labelledby="navbarDropdown" id="productsMenuOpts">
          <a class="dropdown-item" href="http://{$MY_ROOTDIR}/content/Item/CheckScannedDate.php">Check PLU Queues</a>
          <a class="dropdown-item" href="http://{$MY_ROOTDIR}/content/Item/ProdUserChangeReport.php">Edits by User</a>
          <a class="dropdown-item" href="http://{$MY_ROOTDIR}/content/Item/FloorSectionMapper.php">Floor Section Mapper</a>
          <a class="dropdown-item" href="http://{$MY_ROOTDIR}/content/Item/LastSoldDates.php?paste_list=1">Last Sold</a>
          <a class="dropdown-item" href="http://{$MY_ROOTDIR}/content/Item/PendingAction.php">Pending Action</a>
          <a class="dropdown-item" href="http://{$MY_ROOTDIR}/content/Item/CheckUnfiWhs.php">UNFI Warehouse</a>
          <a class="dropdown-item" href="http://{$MY_ROOTDIR}/content/Item/NaturalizeProdInfo.php">Update Sign Info</a>
        </div>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
            onclick="dropdownMenuClick('reportsMenuOpts');">
            Reports 
        </a>
        <div class="dropdown-menu" aria-labelledby="navbarDropdown" id="reportsMenuOpts">
          <div class="nav-item nav-label" align=""><span class="nav-label">Cashless</span></div>
          <a class="dropdown-item" href="http://{$MY_ROOTDIR}/content/Tables/CashlessCheckPage.php">Cashless Transactions</a>
          <div class="nav-item nav-label" align=""><span class="nav-label">Tables</span></div>
          <a class="dropdown-item" href="http://{$MY_ROOTDIR}/content/Tables/CoopDealsFile.php">Coop Deals File Report</a>
          <div class="nav-item nav-label" align=""><span class="nav-label">Reports</span></div>
          <a class="dropdown-item" href="http://{$MY_ROOTDIR}/content/Item/Batches/BatchReview/BatchReviewPage.php">Batch Review Report</a>
          <a class="dropdown-item" href="http://{$MY_ROOTDIR}/content/Reports/BatchHistory.php">Batch Activity Report (All)</a>
          <a class="dropdown-item" href="http://{$MY_ROOTDIR}/content/Reports/DeliReusePluReport.php">Deli, Find PLUs to Reuse</a>
          <a class="dropdown-item" href="http://{$MY_ROOTDIR}/content/Reports/PriceRuleTypeReport.php">Price Rule Report</a>
          <a class="dropdown-item" href="http://{$MY_ROOTDIR}/content/Item/Batches/CoopDeals/CoopDealsReview.php">Q.A. & Breakdowns</a>
        </div>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
            onclick="dropdownMenuClick('scanningMenuOpts');">
            Scanning
        </a>
        <div class="dropdown-menu" aria-labelledby="navbarDropdown" id="scanningMenuOpts">
          <a class="dropdown-item" href="http://{$MY_ROOTDIR}/content/Home/Dashboard.php">Scan Dept. <strong>Dashboard</strong></a>
          <a class="dropdown-item" href="http://{$MY_ROOTDIR}/content/Scanning/BatchCheck/newpage.php"><strong style="color: green">Batch Check</strong></a>
          <a class="dropdown-item" href="http://{$MY_ROOTDIR}/content/Scanning/AuditScanner/ProductScanner.php"><strong style="color: #4286f4">Audit</strong> Scanner</a>
          <a class="dropdown-item" href="http://{$MY_ROOTDIR}/content/Scanning/AuditScanner/AuditReport.php"><strong style="color: #4286f4">Audit</strong> Report</a>
          <a class="dropdown-item" href="http://{$MY_ROOTDIR}/content/Scanning/AuditScanner/BasicsScan.php"><strong style="color: purple">Basics</strong> Scan</a>
          <a class="dropdown-item" href="http://{$MY_ROOTDIR}/content/Scanning/ScannerSettings.php">Scanner Settings</a>
          <!--
          <div class="dropdown-divider"></div>
          <a class="dropdown-item" ></a>
          -->
        </div>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
            onclick="dropdownMenuClick('miscMenuOpts');">
            Misc
        </a>
        <div class="dropdown-menu" aria-labelledby="navbarDropdown" id="miscMenuOpts">
          <div class="nav-item nav-label" align=""><span class="nav-label">Misc. Pages</span></div>
          <a class="dropdown-item" href="http://{$MY_ROOTDIR}/content/Finance/FindPurchaseOrders.php">Find Purchase Orders</a>
          <a class="dropdown-item" href="http://{$MY_ROOTDIR}/content/Tables/OAMUsageReport.php">OAM Usage Report</a>
          <a class="dropdown-item" href="http://{$MY_ROOTDIR}/content/Testing/PrintMultipleReceipts.php">Print Multiple Receipts</a>
          <div class="nav-item nav-label" align=""><span class="nav-label">Misc. Utils.</span></div>
          <a class="dropdown-item" href="http://{$MY_ROOTDIR}/content/Item/Popups.php">Popups</a>
          <a class="dropdown-item" href="http://{$MY_ROOTDIR}/content/Links/Links.php">Useful Links</a>
          <div class="nav-item nav-label" align=""><span class="nav-label">Help</span></div>
          <a class="dropdown-item" onclick="{$helptoggle}" >Help</a>
        </div>
      <!--
      <li class="nav-item">
        <a class="nav-link disabled" >Disabled</a>
      </li>
      -->
    </ul>
    <div id="nav-search-container">
    <div style="float: left; display: inline-block; color: white; margin-right: 24px; 
        text-align: center;  cursor: pointer;"
        onclick="$('#site-navbar').hide(); $('#navbar-placeholder').show(); return false;">
        <span style="font-size: 11px;">
        <img src="http://$MY_ROOTDIR/common/src/img/upArrowLight.png" style="margin-top: 10px; height: 15px; width: 15px" />
    </div>
    <form class="form-inline my-2 my-lg-0">
      <input class="form-control mr-sm-2" type="search" id="nav-search" placeholder="Search" aria-label="Search">
      <div id="search-resp"></div>
    </form>
    </div>
    <div class="login-nav">
        $loginText
    </div>
  </div>
  <div class="toggle-control-center">
  </div>
</nav>
<div class="control-center">
</div>
HTML;
    }

    private function js()
    {
        return <<<JAVASCRIPT
function navbarSupportedContent() {
    if ($('#navbarSupportedContent').is(':visible')) {
        $('#navbarSupportedContent').hide();
    } else {
        $('#navbarSupportedContent').show();
    }
    
    return false;
}
function dropdownMenuClick(target) {
    if ($('#'+target).is(':visible')) {
        $('#'+target).hide();
    } else {
        $('#'+target).show();
    }
    
    return false;
}
JAVASCRIPT;
    }

}
