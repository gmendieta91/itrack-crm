<?php
/**
 * iTrack CRM · Reset de contraseña de emergencia
 *
 * USO:
 *   1. Subí este archivo a la carpeta del CRM
 *   2. Abrilo en el navegador: tudominio.com/crm/reset_password.php
 *   3. Seguí las instrucciones
 *   4. ELIMINÁ ESTE ARCHIVO del servidor después de usarlo
 *
 * SEGURIDAD: Este archivo no tiene autenticación, eliminalo apenas lo uses.
 */

require_once __DIR__ . '/includes/config.php';

$msg = '';
$ok  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario  = trim($_POST['usuario']  ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm']  ?? '');

    if (!$usuario || !$password) {
        $msg = 'Completá todos los campos.';
    } elseif (strlen($password) < 6) {
        $msg = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($password !== $confirm) {
        $msg = 'Las contraseñas no coinciden.';
    } else {
        try {
            $stmt = db()->prepare("SELECT id FROM usuarios WHERE usuario = ? OR email = ?");
            $stmt->execute([$usuario, $usuario]);
            $user = $stmt->fetch();
            if (!$user) {
                $msg = 'Usuario no encontrado: ' . htmlspecialchars($usuario);
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                db()->prepare("UPDATE usuarios SET password = ?, activo = 1 WHERE id = ?")
                     ->execute([$hash, $user['id']]);
                $ok  = true;
                $msg = '✅ Contraseña actualizada correctamente. Ya podés ingresar al sistema.';
            }
        } catch (Exception $e) {
            $msg = 'Error de base de datos: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset contraseña · iTrack CRM</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
  body { font-family:'Barlow',sans-serif; background:#181530; color:#fff; display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px; }
  .box { background:#2a2548; border:1px solid rgba(62,180,137,.2); border-radius:16px; padding:40px; width:100%; max-width:420px; }
  h1   { font-size:20px; font-weight:800; margin-bottom:6px; }
  p.sub { font-size:13px; color:#b0b0c0; margin-bottom:24px; }
  label { display:block; font-size:11px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#b0b0c0; margin-bottom:6px; }
  input { width:100%; background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.1); border-radius:8px; color:#fff; font-family:inherit; font-size:14px; padding:11px 14px; outline:none; margin-bottom:14px; }
  input:focus { border-color:#3EB489; }
  button { width:100%; background:#3EB489; color:#201B3A; border:none; border-radius:8px; font-family:inherit; font-size:14px; font-weight:800; padding:13px; cursor:pointer; margin-top:6px; }
  button:hover { background:#4ecf9e; }
  .alert { padding:12px 16px; border-radius:8px; font-size:13px; font-weight:600; margin-bottom:18px; }
  .alert.ok  { background:rgba(62,180,137,.1); border:1px solid rgba(62,180,137,.3); color:#3EB489; }
  .alert.err { background:rgba(224,92,92,.1);  border:1px solid rgba(224,92,92,.3);  color:#e05c5c; }
  .warning { background:rgba(245,166,35,.1); border:1px solid rgba(245,166,35,.3); color:#f5a623; border-radius:8px; padding:12px 16px; font-size:12px; margin-bottom:20px; }
  a { color:#3EB489; }
</style>
</head>
<body>
<div class="box">
  <h1>🔑 Reset de contraseña</h1>
  <p class="sub">Herramienta de emergencia · iTrack CRM</p>

  <div class="warning">
    ⚠️ <strong>Importante:</strong> Eliminá este archivo del servidor apenas termines de usarlo.
  </div>

  <?php if ($msg): ?>
    <div class="alert <?= $ok ? 'ok' : 'err' ?>"><?= $msg ?></div>
  <?php endif; ?>

  <?php if (!$ok): ?>
  <form method="POST">
    <label>Usuario o email</label>
    <input type="text" name="usuario" placeholder="admin" value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>" required>

    <label>Nueva contraseña</label>
    <input type="password" name="password" placeholder="Mínimo 6 caracteres" required>

    <label>Confirmar contraseña</label>
    <input type="password" name="confirm" placeholder="Repetí la contraseña" required>

    <button type="submit">Cambiar contraseña</button>
  </form>
  <?php else: ?>
    <div style="text-align:center;margin-top:16px">
      <a href="<?= APP_URL ?>/login.php" style="font-size:14px;font-weight:700">→ Ir al login</a>
    </div>
    <div style="margin-top:20px;padding-top:20px;border-top:1px solid rgba(255,255,255,.08);font-size:12px;color:#b0b0c0">
      <strong style="color:#f5a623">⚠️ No olvidés eliminar este archivo:</strong><br>
      En cPanel → Administrador de Archivos → buscá <code>reset_password.php</code> → Eliminá
    </div>
  <?php endif; ?>
</div>
</body>
</html>
