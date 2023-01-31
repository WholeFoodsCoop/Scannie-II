<?php
if (session_status() == 'PHP_COOKIE_NONE') {
    session_start();
}
?>
<?php
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../content/PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include(__DIR__.'/../common/sqlconnect/SQLManager.php');
}
class Login extends PageLayoutA
{

    public $ui = false;

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();

        return false;
    }

    public function pageContent()
    {

        include(__DIR__.'/../config.php');
        $dbc = new SQLManager($SCANHOST, 'pdo_mysql', $SCANALTDB, $SCANUSER, $SCANPASS);
        $SESSION_ID = session_id();
        $session_expired = FormLib::get('session_expired', false);

        $cur_from_page = basename($_SERVER['HTTP_REFERER']);
        if (isset($_COOKIE['user_name'])) {

            $curUser = $_COOKIE['user_name'];
        }
        
        $ret = '';
        $expired = 0;
        if  (array_key_exists('session_token', $_COOKIE) && $session_expired != false) {
            unset($_COOKIE['user_type']);
            setcookie('user_type', '', time() - 3600, '/');
            unset($_COOKIE['user_name']);
            setcookie('user_name', '', time() - 3600, '/');
            unset($_COOKIE['user_id']);
            setcookie('user_id', '', time() - 3600, '/');
            unset($_COOKIE['session_token']);
            setcookie('session_token', '', time() - 3600, '/');
            $expired = 1;
        }
        $ret .= $this->form_content($expired);

        $user = array();

        if (isset($_COOKIE['notadmin'])) $ret .=  'You must be logged in as admin to access the previous page.';
        if (isset($_POST['username'])) {
            //check if password is correct
            $curUser = $_POST['username'];
            $query = $dbc->prepare("
                SELECT hash
                FROM ScannieAuth
                WHERE name = ?
                LIMIT 1;
            ");
            $result = $dbc->execute($query,$curUser);
            $hash = $dbc->fetchRow($result);
            //if ( hash_equals($hash[0], crypt($_POST['pw'], $hash[0])) ) {
            if (password_verify($_POST['pw'], $hash[0]) == true) {
                $queryB = $dbc->prepare("SELECT name, type, id FROM ScannieAuth WHERE name = ? ;");
                $resultB = $dbc->execute($queryB,$curUser);
                while ($row = $dbc->fetchRow($resultB)) {
                    $user_id = $row['id'];
                    if (!isset($_COOKIE['user_type'])) {
                        $cookie_name = 'user_type';
                        $cookie_value = $row['type'];
                        setcookie($cookie_name, $cookie_value, time() + (86400 * 30), '/'); // 86400 = 1 day
                    }
                    if (!isset($_COOKIE['user_name'])) {
                        $cookie_name = 'user_name';
                        $cookie_value = $row['name'];
                        setcookie($cookie_name, $cookie_value, time() + (86400 * 30), '/'); // 86400 = 1 day
                    }
                    if (!isset($_COOKIE['user_id'])) {
                        $cookie_name = 'user_id';
                        $cookie_value = $row['id'];
                        setcookie($cookie_name, $cookie_value, time() + (86400 * 30), '/'); // 86400 = 1 day
                    }
                    if (!isset($_COOKIE['session_token'])) {
                        $cookie_name = 'session_token';
                        $cookie_value = session_id(); 
                        setcookie($cookie_name, $cookie_value, time() + (86400 * 30), '/'); // 86400 = 1 day
                    }
                }
                if ($dbc->error()) $ret .=  $dbc->error();
                $argsC = array(session_id(), $user_id);
                $queryC = $dbc->prepare("UPDATE ScannieAuth SET session_token = ?, last = NOW() WHERE id = ?");
                $resC = $dbc->execute($queryC, $argsC);
                $ret .=  "<div align='center' style='margin-top: 25px;'>
                    <div class='alert alert-success login-resp' style='max-width: 90vw;'>logging in <strong>".$curUser."</strong>, please wait.";
                $ret .=  '</div></div>';
                $ret .=  <<<JAVASCRIPT
<script type="text/javascript">
window.setTimeout(function(){
    window.location.href = ".."
}, 1000);
</script>
JAVASCRIPT;
            } else {
                $ret .=  "<br /><br /><div align='center'><div class='alert alert-danger login-resp' style='max-width: 90vw;'>Username or Password is incorrect</div></div>";
            }
        }

        return $ret;

    }

    private function form_content($expired)
    {
        include(__DIR__.'/../config.php');
        $ret = '';

        if ($ipod = scanLib::isDeviceIpod()) {
            $width = 'width: 90vw;';
        } else {
            $width = '';
        }
        $expired_text = ($expired == 1) ? '<div align="center"><div style="margin: 25px; '.$width.'; text-align: center;" class="alert alert-warning">  
                Your Session has expired.</div></div>' : '';
        $ret .= $expired_text.'
            <div class="login-form" align="center" style="'.$width.'">
                <form method="post">
                    <h2 class="login"><a href="http://'.$MY_ROOTDIR.'" title="Back to Home Page">SV2.0 Login</a>
                    <div class="form-group">
                        <input type="text" name="username" class="form-control" placeholder="username">
                    </div>
                    <div class="form-group">
                        <input type="password" name="pw" class="form-control"  placeholder="password">
                    </div>
                    <div class="form-group">
                        <input type="submit" value="LOG IN" class="btn btn-defult btn-login form-control" >
                    </div>
                </form>

            </div>
        ';

        return $ret;
    }

    public function cssContent()
    {
        return <<<HTML
html,body {
    display:table;
    width:100%;
    height:100%;
    margin:0;
 }
body {
    display:table-cell;
    vertical-align:middle;
 }
.alert {
    width: 400px;
}
.login-form {
    display:block;
    width: 400px;
    border-radius: 5px;
    margin:auto;
    box-shadow:0.7vw 0.7vw 0.7vw #272822;
    background: linear-gradient(rgba(255,255,255,0.9), rgba(200,200,200,0.8));
    //opacity: 0.9;
    padding: 20px;
    color: black;
}
.login-resp {
    width:400px;
}
.btn-login {
    border: 2px solid lightblue;
    width: 170px;
}
h2.login {
    text-shadow: 1px 1px grey;
}
@media only screen and (max-width: 600px) {
    
}
body, html {
    background: black;
    background: repeating-linear-gradient(#343A40,  #565E66, #343A40 5px); 
}
.form-control {
    margin-top: 25px;
}
HTML;

    }

}
WebDispatch::conditionalExec();
