<?php
// ============================================================
// iTrack CRM · Configuración
// EDITAR estos valores con los datos de tu hosting cPanel
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'itrack_crm');      // nombre de la BD en cPanel
define('DB_USER', 'itrack_admin');   // usuario MySQL de cPanel
define('DB_PASS', 'Itrack2026!!$');  // contraseña MySQL de cPanel
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'iTrack CRM');
define('APP_URL',  '');   // dejar vacío si está en la raíz, o poner '/crm' si está en subcarpeta

// Carpeta donde se guardan las propuestas HTML generadas
define('PROPUESTAS_DIR', __DIR__ . '/../propuestas/');
define('PROPUESTAS_URL', APP_URL . '/propuestas/');

// Sesión
define('SESSION_NAME', 'itrack_crm_session');
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);

// ── Conexión PDO ─────────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die(json_encode(['ok' => false, 'error' => 'Error de conexión a la base de datos: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// ── Auth helpers ─────────────────────────────────────────
function iniciarSesion(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }
}

function usuarioLogueado(): bool {
    iniciarSesion();
    return !empty($_SESSION['usuario_id']);
}

function requiereLogin(): void {
    if (!usuarioLogueado()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

function requiereAdmin(): void {
    requiereLogin();
    if ($_SESSION['rol'] !== 'admin') {
        header('Location: ' . APP_URL . '/dashboard.php?error=sin_permiso');
        exit;
    }
}

function usuarioActual(): array {
    iniciarSesion();
    return [
        'id'       => $_SESSION['usuario_id']  ?? 0,
        'nombre'   => $_SESSION['nombre']       ?? '',
        'apellido' => $_SESSION['apellido']     ?? '',
        'email'    => $_SESSION['email']        ?? '',
        'usuario'  => $_SESSION['usuario']      ?? '',
        'rol'      => $_SESSION['rol']          ?? 'vendedor',
    ];
}

function esc(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
