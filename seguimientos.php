<?php
require_once __DIR__ . '/includes/config.php';
requiereLogin();
$u = usuarioActual();
$db = db();

// Filtros
$tipo_fil     = $_GET['tipo'] ?? '';
$pendientes   = isset($_GET['pendientes']);
$cliente_fil  = (int)($_GET['cliente_id'] ?? 0);

$where  = '1=1';
$params = [];
if ($tipo_fil)   { $where .= " AND s.tipo=?";       $params[] = $tipo_fil; }
if ($pendientes) { $where .= " AND s.fecha_proxima >= CURDATE()"; }
if ($cliente_fil){ $where .= " AND s.cliente_id=?"; $params[] = $cliente_fil; }

$seguimientos = $db->prepare("
    SELECT s.*, c.empresa, c.nombre AS c_nombre, c.apellido AS c_apellido,
           u.nombre AS u_nombre, u.apellido AS u_apellido,
           p.tipo_servicio AS prop_tipo
    FROM seguimientos s
    JOIN clientes c ON s.cliente_id = c.id
    JOIN usuarios u ON s.usuario_id = u.id
    LEFT JOIN propuestas p ON s.propuesta_id = p.id
    WHERE $where
    ORDER BY s.fecha_contacto DESC
    LIMIT 100
");
$seguimientos->execute($params);
$seguimientos = $seguimientos->fetchAll();

// Próximas acciones urgentes (hoy y mañana)
$urgentes = $db->query("
    SELECT s.*, c.empresa, c.nombre AS c_nombre, c.apellido AS c_apellido
    FROM seguimientos s JOIN clientes c ON s.cliente_id = c.id
    WHERE s.fecha_proxima BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 2 DAY)
    ORDER BY s.fecha_proxima ASC LIMIT 10
")->fetchAll();

$tipo_icons = ['llamada'=>'📞','reunion'=>'🤝','email'=>'✉️','whatsapp'=>'💬','nota'=>'📝','otro'=>'📌'];
$tipo_labels= ['llamada'=>'Llamada','reunion'=>'Reunión','email'=>'Email','whatsapp'=>'WhatsApp','nota'=>'Nota','otro'=>'Otro'];

define('PAGE_TITLE', 'Seguimientos');
include __DIR__ . '/includes/layout_top.php';
?>

<?php if ($urgentes): ?>
<!-- ALERTAS URGENTES -->
<div style="margin-bottom:20px">
  <div style="font-size:13px;font-weight:700;color:var(--amber);margin-bottom:10px;display:flex;align-items:center;gap:6px">
    ⏰ Acciones urgentes (hoy / mañana)
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:10px">
    <?php foreach ($urgentes as $ug):
      $hoy = date('Y-m-d');
      $es_hoy = $ug['fecha_proxima'] === $hoy;
    ?>
    <div style="background:<?= $es_hoy ? 'rgba(245,166,35,.1)' : 'rgba(255,255,255,.04)' ?>;border:1px solid <?= $es_hoy ? 'rgba(245,166,35,.4)' : 'rgba(255,255,255,.08)' ?>;border-radius:10px;padding:14px 16px">
      <div style="font-size:13px;font-weight:700"><?= esc($ug['empresa']) ?></div>
      <div style="font-size:12px;color:var(--muted);margin:4px 0"><?= esc($ug['proxima_accion']) ?></div>
      <div style="display:flex;align-items:center;justify-content:space-between">
        <span style="font-size:11px;color:<?= $es_hoy ? 'var(--amber)' : 'var(--muted)' ?>;font-weight:700">
          <?= $es_hoy ? '🔴 HOY' : '🟡 Mañana' ?> · <?= date('d/m', strtotime($ug['fecha_proxima'])) ?>
        </span>
        <a href="<?= APP_URL ?>/cliente_detalle.php?id=<?= $ug['cliente_id'] ?>" class="btn btn-secondary btn-sm">Ver cliente</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- FILTROS -->
<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;align-items:center">
  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap">
    <select name="tipo" onchange="this.form.submit()" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:8px;color:var(--white);padding:9px 14px;font-family:inherit;font-size:13px">
      <option value="">Todos los tipos</option>
      <?php foreach ($tipo_labels as $k => $l): ?>
        <option value="<?= $k ?>" <?= $tipo_fil===$k?'selected':'' ?>><?= $tipo_icons[$k] ?> <?= $l ?></option>
      <?php endforeach; ?>
    </select>
    <label style="display:flex;align-items:center;gap:7px;font-size:13px;cursor:pointer;padding:9px 14px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:8px">
      <input type="checkbox" name="pendientes" value="1" <?= $pendientes?'checked':'' ?> onchange="this.form.submit()">
      Solo con acción pendiente
    </label>
    <?php if ($tipo_fil || $pendientes || $cliente_fil): ?>
      <a href="<?= APP_URL ?>/seguimientos.php" class="btn btn-secondary btn-sm">✕ Quitar filtros</a>
    <?php endif; ?>
  </form>
  <span style="font-size:13px;color:var(--muted);margin-left:auto"><?= count($seguimientos) ?> seguimiento<?= count($seguimientos)!==1?'s':'' ?></span>
</div>

<!-- LISTADO -->
<div class="card">
  <div class="table-wrap">
    <?php if ($seguimientos): ?>
    <table>
      <thead>
        <tr>
          <th>Tipo</th>
          <th>Cliente</th>
          <th>Detalle</th>
          <th>Próxima acción</th>
          <th>Vendedor</th>
          <th>Fecha</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($seguimientos as $s):
          $icon = $tipo_icons[$s['tipo']] ?? '📌';
          $tiene_proxima = !empty($s['fecha_proxima']);
          $proxima_pasada = $tiene_proxima && $s['fecha_proxima'] < date('Y-m-d');
        ?>
        <tr>
          <td>
            <span style="font-size:20px"><?= $icon ?></span>
            <div style="font-size:11px;color:var(--muted)"><?= $tipo_labels[$s['tipo']] ?? $s['tipo'] ?></div>
          </td>
          <td>
            <a href="<?= APP_URL ?>/cliente_detalle.php?id=<?= $s['cliente_id'] ?>" style="text-decoration:none">
              <div class="td-empresa" style="color:var(--green)"><?= esc($s['empresa']) ?></div>
            </a>
            <div class="td-sub"><?= esc($s['c_nombre'] . ' ' . $s['c_apellido']) ?></div>
          </td>
          <td>
            <div style="font-weight:700;font-size:13px"><?= esc($s['titulo']) ?></div>
            <?php if ($s['descripcion']): ?>
              <div style="font-size:12px;color:var(--muted);margin-top:2px;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= esc($s['descripcion']) ?></div>
            <?php endif; ?>
            <?php if ($s['prop_tipo']): ?>
              <div style="font-size:11px;color:var(--blue);margin-top:3px">📄 <?= esc($s['prop_tipo']) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($s['proxima_accion']): ?>
              <div style="font-size:12px;<?= $proxima_pasada ? 'color:var(--red)' : ($tiene_proxima && $s['fecha_proxima'] <= date('Y-m-d',strtotime('+2 days')) ? 'color:var(--amber)' : 'color:var(--muted)') ?>">
                <?= $proxima_pasada ? '⚠️' : '⏭️' ?> <?= esc($s['proxima_accion']) ?>
                <?php if ($tiene_proxima): ?>
                  <div style="font-size:11px;margin-top:2px"><?= date('d/m/Y', strtotime($s['fecha_proxima'])) ?></div>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <span style="color:var(--gray);font-size:12px">—</span>
            <?php endif; ?>
          </td>
          <td style="font-size:13px"><?= esc($s['u_nombre'] . ' ' . $s['u_apellido']) ?></td>
          <td style="font-size:12px;color:var(--muted)"><?= date('d/m/Y H:i', strtotime($s['fecha_contacto'])) ?></td>
          <td>
            <button class="btn btn-danger btn-sm btn-icon" onclick="eliminarSeg(<?= $s['id'] ?>)" title="Eliminar">🗑️</button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
      <div class="empty-state">
        <div class="empty-icon">🎯</div>
        <div class="empty-title">Sin seguimientos</div>
        <div class="empty-desc">Los seguimientos se registran desde el perfil de cada cliente</div>
        <a href="<?= APP_URL ?>/clientes.php" class="btn btn-primary">Ver clientes</a>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
function eliminarSeg(id) {
  if (!confirm('¿Eliminar este seguimiento?')) return;
  fetch('<?= APP_URL ?>/api/seguimientos.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ accion: 'eliminar', id })
  })
  .then(r => r.json())
  .then(r => {
    if (r.ok) { toast('Eliminado'); setTimeout(() => location.reload(), 700); }
    else toast(r.error || 'Error', 'err');
  });
}
</script>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
