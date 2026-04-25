<?php
if (!defined('PAGE_TITLE')) define('PAGE_TITLE', 'iTrack CRM');
$u = usuarioActual();
$av_letter = strtoupper(substr($u['nombre'], 0, 1));
$av_colors = ['av-a','av-b','av-c','av-d','av-e','av-f'];
$av_color  = $av_colors[$u['id'] % 6];

$logo_path = __DIR__ . '/../assets/logo_itrack.png';
$logo_b64  = file_exists($logo_path)
    ? 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path))
    : '';

$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= esc(PAGE_TITLE) ?> · iTrack CRM</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
<?php if (isset($extra_head)) echo $extra_head; ?>
</head>
<body>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <?php if ($logo_b64): ?>
      <img src="<?= $logo_b64 ?>" alt="iTrack" style="height:32px;object-fit:contain">
    <?php else: ?>
      <span style="font-size:20px;font-weight:900;color:var(--green)">iTrack</span>
    <?php endif; ?>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section">Principal</div>
    <a href="<?= APP_URL ?>/dashboard.php"   class="nav-item <?= $current==='dashboard.php'   ?'active':'' ?>"><span class="icon">📊</span> Dashboard</a>
    <a href="<?= APP_URL ?>/clientes.php"    class="nav-item <?= $current==='clientes.php'||$current==='cliente_detalle.php' ?'active':'' ?>"><span class="icon">🏢</span> Prospectos y Clientes</a>
    <a href="<?= APP_URL ?>/propuestas.php"  class="nav-item <?= $current==='propuestas.php'  ?'active':'' ?>"><span class="icon">📄</span> Propuestas</a>
    <a href="<?= APP_URL ?>/seguimientos.php"class="nav-item <?= $current==='seguimientos.php'?'active':'' ?>"><span class="icon">🎯</span> Seguimientos</a>
    <a href="<?= APP_URL ?>/importar.php"    class="nav-item <?= $current==='importar.php'    ?'active':'' ?>"><span class="icon">📂</span> Importar</a>

    <?php if ($u['rol'] === 'admin'): ?>
    <div class="nav-section">Administración</div>
    <a href="<?= APP_URL ?>/usuarios.php"    class="nav-item <?= $current==='usuarios.php'    ?'active':'' ?>"><span class="icon">👥</span> Usuarios</a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="user-avatar <?= $av_color ?>"><?= esc($av_letter) ?></div>
      <div>
        <div class="user-name"><?= esc($u['nombre'] . ' ' . $u['apellido']) ?></div>
        <div class="user-role"><?= $u['rol']==='admin' ? 'Administrador' : 'Vendedor' ?></div>
      </div>
      <form method="POST" action="<?= APP_URL ?>/logout.php" style="margin-left:auto">
        <button class="btn-logout-sm" title="Cerrar sesión">🚪</button>
      </form>
    </div>
  </div>
</aside>

<div class="app-wrapper">
  <header class="topbar">
    <div style="display:flex;align-items:center;gap:12px">
      <button class="btn btn-secondary btn-icon" id="sidebar-toggle" style="display:none" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
      <div class="topbar-title"><?= PAGE_TITLE ?></div>
    </div>
    <div class="topbar-actions">
      <?php if (isset($topbar_actions)) echo $topbar_actions; ?>
    </div>
  </header>

  <main class="page-content">
    <?php if (!empty($_GET['msg_ok'])): ?>
      <div class="alert alert-success">✅ <?= esc($_GET['msg_ok']) ?></div>
    <?php endif; ?>
    <?php if (!empty($_GET['msg_err'])): ?>
      <div class="alert alert-error">⚠️ <?= esc($_GET['msg_err']) ?></div>
    <?php endif; ?>
