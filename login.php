<?php
require_once __DIR__ . '/includes/config.php';
iniciarSesion();

// Si ya está logueado, redirigir
if (usuarioLogueado()) {
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['usuario'] ?? '');
    $p = $_POST['password'] ?? '';

    if ($u && $p) {
        $stmt = db()->prepare("SELECT * FROM usuarios WHERE (usuario = ? OR email = ?) AND activo = 1 LIMIT 1");
        $stmt->execute([$u, $u]);
        $user = $stmt->fetch();

        if ($user && password_verify($p, $user['password'])) {
            $_SESSION['usuario_id'] = $user['id'];
            $_SESSION['nombre']     = $user['nombre'];
            $_SESSION['apellido']   = $user['apellido'];
            $_SESSION['email']      = $user['email'];
            $_SESSION['usuario']    = $user['usuario'];
            $_SESSION['rol']        = $user['rol'];

            // Actualizar último login
            db()->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?")->execute([$user['id']]);

            header('Location: ' . APP_URL . '/dashboard.php');
            exit;
        } else {
            $error = 'Usuario o contraseña incorrectos.';
        }
    } else {
        $error = 'Completá todos los campos.';
    }
}

// Logo
$logo_b64 = file_exists(__DIR__ . '/assets/logo_itrack.png')
    ? 'data:image/png;base64,' . base64_encode(file_get_contents(__DIR__ . '/assets/logo_itrack.png'))
    : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Iniciar sesión · iTrack CRM</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
<style>
  body { display:flex; align-items:center; justify-content:center; min-height:100vh;
    background: radial-gradient(ellipse at 30% 50%, rgba(62,180,137,.07) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 20%, rgba(62,180,137,.04) 0%, transparent 50%), #181530; }
  .login-wrap { width:100%; max-width:420px; padding:20px; }
  .login-box { background:var(--navy-card); border:1px solid var(--border); border-radius:20px; padding:48px 40px; box-shadow:0 40px 80px rgba(0,0,0,.4); }
  .login-logo { text-align:center; margin-bottom:36px; }
  .login-logo img { height:50px; object-fit:contain; }
  .login-logo h1 { font-size:22px; font-weight:900; margin-top:14px; }
  .login-logo p { font-size:12px; color:var(--muted); letter-spacing:.15em; text-transform:uppercase; margin-top:4px; }
  .login-footer { text-align:center; font-size:12px; color:var(--gray); margin-top:24px; }
  @keyframes shake { 0%,100%{transform:translateX(0)} 20%{transform:translateX(-8px)} 40%{transform:translateX(8px)} 60%{transform:translateX(-6px)} 80%{transform:translateX(6px)} }
  .shake { animation:shake .4s ease; }
</style>
</head>
<body>
<div class="login-wrap">
  <div class="login-box" id="login-box">
    <div class="login-logo">
      <?php if ($logo_b64): ?>
        <img src="<?= $logo_b64 ?>" alt="iTrack">
      <?php else: ?>
        <span style="font-size:36px;font-weight:900;color:var(--green)">iTrack</span>
      <?php endif; ?>
      <h1>CRM Comercial</h1>
      <p>Panel Interno · Acceso seguro</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= esc($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="login-form">
      <div class="field" style="margin-bottom:16px">
        <label>Usuario o Email</label>
        <input type="text" name="usuario" placeholder="Tu usuario" autocomplete="username" required
               value="<?= esc($_POST['usuario'] ?? '') ?>">
      </div>
      <div class="field" style="margin-bottom:24px">
        <label>Contraseña</label>
        <input type="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:14px;font-size:15px">
        Ingresar al panel
      </button>
    </form>
  </div>
  <div class="login-footer">iTrack · Sistema CRM Comercial</div>
</div>
<?php if ($error): ?>
<script>
  const b = document.getElementById('login-box');
  b.classList.add('shake');
  b.addEventListener('animationend', () => b.classList.remove('shake'));
</script>
<?php endif; ?>
</body>
</html>
