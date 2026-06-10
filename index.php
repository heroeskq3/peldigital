<?php
require __DIR__ . '/auth.php';
requerirLogin();
header('Location: reports.php?id=1');
exit;
