<?php
require_once __DIR__ . '/includes/config.php';
iniciarSesion();
session_destroy();
header('Location: ' . APP_URL . '/login.php');
exit;
