<?php
require __DIR__ . '/auth.php';
requerirLogin();
header('Location: ' . appUrl('home'));
exit;
