<?php
require __DIR__ . '/auth.php';
registrarBitacora('logout', 'Cierre de sesión');
cerrarSesion();
header('Location: ' . appUrl('login'));
exit;
