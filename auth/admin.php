<?php
/*
*   @class admin deprecated. The only use for this page is to 
*   create a password hash from cli.
*   To create a password hash, run script as $ php admin.php <password_str>
*/
class admin
{
    static public function run($pw)
    {
        if ($pw == null ) {
            echo "# createSv2UsrPwd requires one argument\r\n";
        } else {
            $password = $pw;
            $options = [ 'cost' => 10, ];
            echo $hash = password_hash($password, PASSWORD_BCRYPT, $options);
        }

        return false;
    }
}
$isCli = (php_sapi_name() == 'cli');
if ($isCli) {
    $pw = ($v = isset($argv[1])) ? $v : null;
    admin::run($pw);
}
