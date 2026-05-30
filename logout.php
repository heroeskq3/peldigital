<?php
require __DIR__ . '/auth.php';
cerrarSesion();
header('Location: login.php');
exit;
