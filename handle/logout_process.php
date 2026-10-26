<?php
require_once __DIR__ . '/../functions/auth.php';
startSessionOnce();
session_unset();
session_destroy();
header('Location: ../index.php');
exit();
