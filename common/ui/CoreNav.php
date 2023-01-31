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
        include(__DIR__.'/NavData.php');
        // $navData = array containing navbar data
        //var_dump($navData);

        $helptoggle = <<<JAVASCRIPT
var hidden = $('#help-contents').is(':visible');
if (hidden == false) {
    $('#help-contents').show();
    $('.dropdown-menu').hide();
} else {
    $('#help-contents').hide();
}
JAVASCRIPT;
    
        $type = null;
        $type = $_COOKIE['user_type'];
        $navRet = '';

        if ($type == 2) {
            $navRet .= "<li class=\"nav-item dropdown\">
                <a class=\"nav-link \" role=\"button\" data-toggle=\"\" aria-haspopup=\"true\" aria-expanded=\"false\">
                    <span style=\"color: plum; border: 1px solid plum; padding: 4px; background-color: rgba(255,55,0,0.2); font-size: 12px; opacity: 0.9;
                        border-radius: 3px\">ADMIN MODE</span>
                </a>
            </li>";
        }

        foreach ($navData as $header => $data) {
            $navRet .= "<li class=\"nav-item dropdown\">";
            $navRet .= "<a class=\"nav-link dropdown-toggle\" role=\"button\" data-toggle=\"dropdown\" aria-haspopup=\"true\" aria-expanded=\"false\"
                        onclick=\"dropdownMenuClick('$header');\">$header</a>";
            $navRet .= "<div class=\"dropdown-menu\" aria-labelledby=\"navbarDropdown\" id=\"$header\">";
            foreach ($data as $i => $row) {
                if ($type >= $row['user_type'] && $row['user_type'] > 0) {
                    // only show user_type == 2 pages if user_type > 1
                    if ($row['type'] == 'link') {
                        $navRet .= "<a class=\"dropdown-item\" href=\"http://{$MY_ROOTDIR}/content/{$row['url']}\">{$row['text']}</a>";
                    } elseif ($row['type'] == 'heading') {
                        $navRet .= "<div class=\"nav-item nav-label\"><span class=\"nav-label\">{$row['text']}</span></div>";
                    } elseif ($row['type'] == 'help') {
                        $navRet .= "<a class=\"dropdown-item\" onclick=\"{$helptoggle}\" ><strong>Help!</strong></a>";
                    }
                }
            }
            $navRet .= "</div>";
            $navRet .= "</li>";
        }
        //var_dump($navRet);
        

        $DIR = __DIR__;
        $user = null;
        $ud = "";
        $type = null;
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
          <a class="dropdown-item" href="http://{$FANNIE_ROOTDIR}">WFC - Duluth - KEY</a>
          <a class="dropdown-item" href="http://steve/">WFC - Duluth - STEVE</a>
          <a class="dropdown-item" href="http://{$FANNIE_COREY_ROOT}">DEV - Corey(1)</a>
          <a class="dropdown-item" href="http://{$FANNIE_COREY2_ROOT}">DEV - Corey(2)</a>
          <a class="dropdown-item" href="http://{$FANNIE_ANDY_ROOT}">DEV - Andy</a>
          <a class="dropdown-item" href="http://{$FANNIE_ROOTDIR}/../IS4C/pos/is4c-nf/">POS on Key</a>
        </div>
      </li>
      $navRet
    </ul>
    <div id="nav-search-container">
    <div style="float: left; display: inline-block; color: white; margin-right: 24px; 
        text-align: center;  cursor: pointer;"
        onclick="$('#site-navbar').hide(); $('#navbar-placeholder').show(); return false;">
        <span style="font-size: 11px;">
        <img src="http://$MY_ROOTDIR/common/src/img/upArrowLight.png" style="margin-top: 10px; height: 15px; width: 15px" />
    </div>
    <form class="form-inline my-2 my-lg-0">
      <input class="form-control mr-sm-2" type="search" id="nav-search" placeholder="Search" aria-label="Search" pattern="\d*">
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
var checkifclosenav = 0;
function navbarSupportedContent() {
    $('.dropdown-menu').each(function(){
        $(this).hide();
    });
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
        $('.dropdown-menu').each(function(){
            $(this).hide();
        });
        $('#'+target).show();
        checkifclosenav = 1;
    }
    
    return false;
}
$(document).mouseup(function(e) {
    var container = $('.dropdown-menu');

    if (!container.is(e.target) && container.has(e.target).length === 0) {
        container.hide();
    }
});
JAVASCRIPT;
    }

}
