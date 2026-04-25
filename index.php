<?php
require_once __DIR__ . '/includes/config.php';
iniciarSesion();
if (usuarioLogueado()) {
    header('Location: ' . APP_URL . '/dashboard.php');
} else {
    header('Location: ' . APP_URL . '/login.php');
}
exit;
