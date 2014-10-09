<?php

namespace BoostMyAllowanceApp;
?>

    <!DOCTYPE html>

<?php
use BoostMyAllowanceApp\Controller as Ctrl;
ini_set("session.cookie_httponly", true); //prevent javascript to access session cookie
//ini_set('error_reporting', 0); //prevent error reporting on server code

session_start();

require_once("controller/auth-controller.php");
require_once("controller/app-controller.php");

setlocale(LC_TIME, 'sv_SE.UTF-8'); //problems on localhost but works on the server.
date_default_timezone_set('Europe/Stockholm');

$authCtrl = new Ctrl\AuthController();
$appCtrl = new Ctrl\AppController();
