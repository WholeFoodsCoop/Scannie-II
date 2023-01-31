<?php
if (!class_exists('PageLayoutA')) {
    include(__DIR__.'/../content/PageLayoutA.php');
}
if (!class_exists('SQLManager')) {
    include(__DIR__.'/../common/sqlconnect/SQLManager.php');
}
class Admin extends PageLayoutA
{

    public $ui = false;
    protected $must_authenticate = true;
    protected $auth_types = array(2);

    public function preprocess()
    {
        $this->displayFunction = $this->pageContent();
        $this->__routes[] = 'post<username>';

        return parent::preprocess();
    }

    public function pageContent()
    {

        $SESSION_ID = session_id();
        $type = FormLib::get('type', 'unknown');
        
        $ret = '';
        $saved = FormLib::get('saved', false);
        if ($saved != false) {
            $ret = "<div class=\"login-form\" align=\"center\">
                <div>$saved</div>
                <div>Type: $type</div>
                </div><div style=\"height: 15px\"></div>";
        }

        return <<<HTML
<div class="row" style="width: 100%">
    <div class="col-lg-4">
    </div>
    <div class="col-lg-4">
        $ret
        <div class="login-form" align="center" style="'.$width.'">
            <form method="post" action="Admin.php">
                <h2 class="login"><span style="color: plum">Create A User</span></h2>
                <p>Or enter an existing username + new password<br/>to RESET existing password</p>
                <div class="form-group">
                    <input type="text" name="username" class="form-control" placeholder="username">
                </div>
                <div class="form-group">
                    <input type="password" name="pw" class="form-control"  placeholder="password">
                </div>
                <div class="form-group">
                    <input type="email" name="email" class="form-control"  placeholder="email (optional)">
                </div>
                <div class="form-group">
                    <input type="submit" value="CREATE USER" class="btn btn-defult btn-login form-control" >
                </div>
            </form>
        </div>
    </div>
    <div class="col-lg-4">
    </div>
</div>
HTML;
    }

    public function postUsernameView()
    {
        $username = FormLib::get('username');
        $pw = FormLib::get('pw');
        $email = FormLib::get('email', 'n/a');
        $options = [ 'cost' => 10 ];
        $hash = password_hash($pw, PASSWORD_BCRYPT, $options);
        $type = 'unknown';
        $dbc = scanLib::getConObj('SCANALTDB');

        $exists = $this->validateUsername($dbc, $username);
        if ($exists === true) {
            $type = 'passwordchange';
            // user exists, change password hash
            $args = array($hash, $username);
            $prep = $dbc->prepare('UPDATE ScannieAuth SET hash = ? WHERE name = ?');
            $res = $dbc->execute($prep, $args);
        } else {
            // user does not exist, create user
            $type = 'usercreated';
            $args = array($username, $hash, $email);
            $prep = $dbc->prepare('INSERT INTO ScannieAuth (name, type, hash, email)
                VALUES (?, 1, ?, ?)');
            $res = $dbc->execute($prep, $args);
        }
        
        $saved = $this->validateUsername($dbc, $username);
        $ret = ($saved == true) ? 'Success!' : 'Something Went Wrong';

        return header("location: Admin.php?type=$type&saved=$ret");
    }

    private function validateUsername($dbc, $username)
    {
        $testP = $dbc->prepare("SELECT name FROM ScannieAuth WHERE name = ?");
        $testR = $dbc->execute($testP, array($username));
        $testW = $dbc->fetchRow($testR);
        $ret = (strlen($testW['name']) > 0) ? true : false;

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
