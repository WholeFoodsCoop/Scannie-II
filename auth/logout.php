<?php session_start(); ?>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../common/bootstrap/bootstrap.min.css">
  <script src="../common/bootstrap/jquery.min.js"></script>
  <script src="../common/bootstrap/bootstrap.min.js"></script>
<style media="screen">

<?php
include(__DIR__.'/../common/sqlconnect/SQLManager.php');

class logout
{
    public function run()
    {
        include(__DIR__.'/../config.php');
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['user_type']);
        unset($_SESSION['user_name']);
        if (isset($_COOKIE["user_type"])) {
            setcookie("user_type", null, time() - 3600);
        }
        if (isset($_COOKIE["user_name"])) {
            setcookie("user_name", null, time() - 3600);
        }

        return header('location: http://'.$MY_ROOTDIR.'/auth/login.php');
    }

    private function jsRedirect()
    {
        $prevUrl = $_SESSION['prevUrl'];
        return '
<script type="text/javascript">
$(document).ready( function () {
    window.location.replace( "'.$prevUrl.'" );
});
</script>
        ';

    }
}

$obj = new logout;
echo $obj->run();
