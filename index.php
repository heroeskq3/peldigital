<?php
require __DIR__ . '/auth.php';
requerirLogin();
header('Location: home.php');
exit;
