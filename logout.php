<?php
require __DIR__ . '/includes/bootstrap.php';
if (!empty($_SESSION['uid'])) audit('LOGOUT');
$_SESSION = [];
session_destroy();
header('Location: login.php');
