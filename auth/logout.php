<?php session_start(); ?>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../common/bootstrap/bootstrap.min.css">
  <script src="../common/bootstrap/jquery.min.js"></script>
  <script src="../common/bootstrap/bootstrap.min.js"></script>
<style media="screen">
<?php
class logout
{
    public function run()
    {
        unset($_COOKIE['user_type']);
        setcookie('user_type', '', time() - 3600, '/');
        unset($_COOKIE['user_name']);
        setcookie('user_name', '', time() - 3600, '/');
        unset($_COOKIE['session_token']);
        setcookie('session_token', '', time() - 3600, '/');

        return header('location: Login.php');
    }

}
$obj = new logout;
echo $obj->run();
