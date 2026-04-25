<?php
require_once __DIR__ . '/includes/config.php';
requiereLogin();
$u = usuarioActual();
$db = db();

// Filtros
$q           = trim($_GET['q'] ?? '');
$filtro_est  = $_GET['estado'] ?? '';
$cliente_sel = (int)($_GET['cliente_id'] ?? 0);

$where  = '1=1';
$params = [];
if ($q) {
    $where .= " AND (c.empresa LIKE ? OR p.tipo_servicio LIKE ?)";
    $lq = "%$q%"; $params[] = $lq; $params[] = $lq;
}
if ($filtro_est) { $where .= " AND p.estado=?"; $params[] = $filtro_est; }
if ($cliente_sel) { $where .= " AND p.cliente_id=?"; $params[] = $cliente_sel; }

$propuestas = $db->prepare("
    SELECT p.*, c.empresa, c.nombre AS c_nombre, c.apellido AS c_apellido,
           u.nombre AS v_nombre, u.apellido AS v_apellido
    FROM propuestas p
    JOIN clientes c ON p.cliente_id = c.id
    JOIN usuarios u ON p.usuario_id = u.id
    WHERE $where ORDER BY p.creado_en DESC
");
$propuestas->execute($params);
$propuestas = $propuestas->fetchAll();

// Lista de clientes para el generador
$clientes = $db->query("SELECT id, empresa, nombre, apellido FROM clientes ORDER BY empresa")->fetchAll();

$estado_labels = [
    'enviada'        => ['label'=>'Enviada',        'badge'=>'badge-blue'],
    'en_negociacion' => ['label'=>'En negociación', 'badge'=>'badge-amber'],
    'ganada'         => ['label'=>'Ganada',         'badge'=>'badge-green'],
    'perdida'        => ['label'=>'Perdida',        'badge'=>'badge-red'],
    'pausada'        => ['label'=>'Pausada',        'badge'=>'badge-gray'],
];

define('PAGE_TITLE', 'Propuestas');
$topbar_actions = '<button class="btn btn-primary" onclick="openModal(\'modal-gen\')">🚀 Nueva propuesta</button>';
include __DIR__ . '/includes/layout_top.php';
?>

<!-- FILTROS -->
<div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;align-items:center">
  <form method="GET" style="display:flex;gap:10px;flex:1;flex-wrap:wrap">
    <div class="search-bar" style="flex:1;min-width:200px">
      <input type="text" name="q" value="<?= esc($q) ?>" placeholder="Buscar empresa o servicio...">
    </div>
    <select name="estado" onchange="this.form.submit()" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:8px;color:var(--white);padding:10px 14px;font-family:inherit">
      <option value="">Todos los estados</option>
      <?php foreach ($estado_labels as $k => $l): ?>
        <option value="<?= $k ?>" <?= $filtro_est===$k?'selected':'' ?>><?= $l['label'] ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <span style="font-size:13px;color:var(--muted)"><?= count($propuestas) ?> propuesta<?= count($propuestas)!==1?'s':'' ?></span>
</div>

<!-- TABLA -->
<div class="card">
  <div class="table-wrap">
    <?php if ($propuestas): ?>
    <table>
      <thead>
        <tr><th>Cliente / Empresa</th><th>Servicio</th><th>Precio</th><th>Vendedor</th><th>Estado</th><th>Fecha</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($propuestas as $p): $e = $estado_labels[$p['estado']] ?? ['label'=>$p['estado'],'badge'=>'badge-gray']; ?>
        <tr>
          <td>
            <a href="<?= APP_URL ?>/cliente_detalle.php?id=<?= $p['cliente_id'] ?>" style="text-decoration:none">
              <div class="td-empresa" style="color:var(--green)"><?= esc($p['empresa']) ?></div>
            </a>
            <div class="td-sub"><?= esc($p['c_nombre'] . ' ' . $p['c_apellido']) ?></div>
          </td>
          <td><?= esc($p['tipo_servicio']) ?></td>
          <td style="font-weight:700;color:var(--green)">$ <?= esc($p['precio']) ?></td>
          <td><?= esc($p['v_nombre'] . ' ' . $p['v_apellido']) ?></td>
          <td>
            <select onchange="cambiarEstado(<?= $p['id'] ?>, this.value)" style="background:transparent;border:none;color:inherit;font-family:inherit;font-size:12px;cursor:pointer">
              <?php foreach ($estado_labels as $k => $lbl): ?>
                <option value="<?= $k ?>" <?= $p['estado']===$k?'selected':'' ?>><?= $lbl['label'] ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td style="font-size:12px;color:var(--muted)"><?= date('d/m/Y', strtotime($p['creado_en'])) ?></td>
          <td>
            <div style="display:flex;gap:6px">
              <?php $ppath = PROPUESTAS_DIR . $p['nombre_archivo']; ?>
              <?php if (file_exists($ppath)): ?>
                <a href="<?= APP_URL ?>/propuestas/<?= esc($p['nombre_archivo']) ?>" target="_blank" class="btn btn-secondary btn-sm">🔗 Ver</a>
                <button class="btn btn-secondary btn-sm" onclick="copiarLink('<?= esc($p['nombre_archivo']) ?>')" title="Copiar link">🔗</button>
              <?php endif; ?>
              <button class="btn btn-danger btn-sm btn-icon" onclick="eliminarProp(<?= $p['id'] ?>)" title="Eliminar">🗑️</button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
      <div class="empty-state">
        <div class="empty-icon">📄</div>
        <div class="empty-title"><?= $q||$filtro_est ? 'Sin resultados' : 'Sin propuestas aún' ?></div>
        <div class="empty-desc"><?= $q||$filtro_est ? 'Probá con otros filtros' : 'Generá tu primera propuesta' ?></div>
        <?php if (!$q && !$filtro_est): ?>
          <button class="btn btn-primary" onclick="openModal('modal-gen')">🚀 Nueva propuesta</button>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- MODAL GENERADOR DE PROPUESTA -->
<div class="modal-backdrop" id="modal-gen">
  <div class="modal modal-lg">
    <div class="modal-header">
      <div class="modal-title">🚀 Generar nueva propuesta</div>
      <button class="btn-close-modal" onclick="closeModal('modal-gen')">×</button>
    </div>
    <div class="modal-body">

      <!-- ESTADO DEL GUARDADO -->
      <div id="estado-gen" style="display:none;margin-bottom:16px"></div>

      <div class="form-grid" style="margin-bottom:16px">
        <div class="field">
          <label>Cliente <span class="req">*</span></label>
          <select id="gen-cliente" required onchange="autoFillContacto()">
            <option value="">— Seleccioná un cliente —</option>
            <?php foreach ($clientes as $c): ?>
              <option value="<?= $c['id'] ?>" data-contacto="<?= esc($c['nombre'] . ' ' . $c['apellido']) ?>" <?= $cliente_sel===$c['id']?'selected':'' ?>>
                <?= esc($c['empresa']) ?> — <?= esc($c['nombre'] . ' ' . $c['apellido']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (!$clientes): ?>
            <div class="field-hint" style="color:var(--amber)">⚠️ No hay clientes. <a href="<?= APP_URL ?>/clientes.php" style="color:var(--green)">Creá uno primero.</a></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-grid two" style="margin-bottom:16px">
        <div class="field">
          <label>Tipo de servicio <span class="req">*</span></label>
          <select id="gen-tipo">
            <option value="">— Seleccioná —</option>
            <option value="Mensual por vehículo">Mensual por vehículo</option>
            <option value="Mensual por dispositivo">Mensual por dispositivo</option>
            <option value="Plan Flota Pequeña (1-5 veh.)">Plan Flota Pequeña (1-5 veh.)</option>
            <option value="Plan Flota Mediana (6-20 veh.)">Plan Flota Mediana (6-20 veh.)</option>
            <option value="Plan Flota Grande (21+ veh.)">Plan Flota Grande (21+ veh.)</option>
            <option value="Plan Corporativo">Plan Corporativo</option>
            <option value="Plan Anual con descuento">Plan Anual con descuento</option>
            <option value="personalizado">✏️ Otro...</option>
          </select>
        </div>
        <div class="field" id="tipo-custom-field" style="display:none">
          <label>Descripción del servicio</label>
          <input type="text" id="gen-tipo-custom" placeholder="Ej: Plan especial 3 meses">
        </div>
        <div class="field">
          <label>Precio mensual (ARS) <span class="req">*</span></label>
          <input type="text" id="gen-precio" placeholder="Ej: 15.000" oninput="fmtPrecio(this)">
          <div class="field-hint">Sin $ ni IVA — se agregan automáticamente</div>
        </div>
      </div>

      <div class="field" style="margin-bottom:16px">
        <label>Logo del cliente</label>
        <div style="border:2px dashed rgba(62,180,137,.3);border-radius:10px;padding:20px;text-align:center;position:relative;cursor:pointer;transition:all .2s" id="logo-drop" onclick="document.getElementById('gen-logo').click()">
          <input type="file" id="gen-logo" accept="image/*" style="display:none" onchange="handleLogo(this)">
          <div id="logo-placeholder">
            <div style="font-size:28px;margin-bottom:8px">📁</div>
            <div style="font-size:13px;font-weight:700">Subí el logo del cliente</div>
            <div style="font-size:12px;color:var(--muted)">PNG, JPG · Hasta 5 MB · Fondo transparente recomendado</div>
          </div>
          <div id="logo-preview-wrap" style="display:none">
            <img id="logo-preview-img" style="max-height:60px;max-width:180px;object-fit:contain">
            <div id="logo-preview-name" style="font-size:12px;color:var(--green);margin-top:8px"></div>
            <div style="font-size:11px;color:var(--muted);text-decoration:underline;margin-top:4px">Cambiar logo</div>
          </div>
        </div>
        <div class="field-hint">💡 Sin logo, la propuesta muestra solo el nombre de la empresa</div>
      </div>

      <div class="field">
        <label>Notas internas</label>
        <textarea id="gen-notas" placeholder="Observaciones sobre esta propuesta (solo visibles en el CRM)..." rows="2"></textarea>
      </div>

    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeModal('modal-gen')">Cancelar</button>
      <button type="button" class="btn btn-primary" id="btn-generar" onclick="generarPropuesta()">🚀 Generar y guardar propuesta</button>
    </div>
  </div>
</div>

<script>
let logoBase64 = null;

function autoFillContacto() {}

function fmtPrecio(input) {
  let v = input.value.replace(/\D/g, '');
  if (v) v = parseInt(v).toLocaleString('es-AR');
  input.value = v;
}

document.getElementById('gen-tipo').addEventListener('change', function() {
  document.getElementById('tipo-custom-field').style.display = this.value === 'personalizado' ? 'block' : 'none';
});

function handleLogo(input) {
  if (!input.files[0]) return;
  const file = input.files[0];
  if (file.size > 5*1024*1024) { toast('Archivo muy grande (máx 5 MB)', 'err'); return; }
  const reader = new FileReader();
  reader.onload = e => {
    logoBase64 = e.target.result;
    document.getElementById('logo-placeholder').style.display = 'none';
    document.getElementById('logo-preview-wrap').style.display = 'block';
    document.getElementById('logo-preview-img').src = logoBase64;
    document.getElementById('logo-preview-name').textContent = file.name;
    document.getElementById('logo-drop').style.borderColor = 'rgba(62,180,137,.6)';
  };
  reader.readAsDataURL(file);
}

async function generarPropuesta() {
  const cliente_id = document.getElementById('gen-cliente').value;
  const tipoSel    = document.getElementById('gen-tipo').value;
  const tipoCustom = document.getElementById('gen-tipo-custom').value.trim();
  const tipo       = tipoSel === 'personalizado' ? tipoCustom : tipoSel;
  const precio     = document.getElementById('gen-precio').value.trim();
  const notas      = document.getElementById('gen-notas').value.trim();

  if (!cliente_id) { toast('Seleccioná un cliente', 'err'); return; }
  if (!tipo)       { toast('Completá el tipo de servicio', 'err'); return; }
  if (!precio)     { toast('Completá el precio', 'err'); return; }

  const btn = document.getElementById('btn-generar');
  btn.disabled = true;
  btn.textContent = '⏳ Generando...';

  const estado = document.getElementById('estado-gen');
  estado.style.display = 'block';
  estado.innerHTML = '<div class="alert alert-info">⏳ Generando propuesta y guardando en el servidor...</div>';

  try {
    const resp = await fetch('<?= APP_URL ?>/api/propuestas.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ accion:'generar', cliente_id, tipo_servicio: tipo, precio, notas, logo_cliente: logoBase64 })
    });
    const r = await resp.json();

    if (r.ok) {
      const url = '<?= rtrim(APP_URL, '/') ?>/' + 'propuestas/' + r.archivo;
      estado.innerHTML = `
        <div class="alert alert-success">
          ✅ Propuesta generada: <strong>${r.archivo}</strong><br>
          <span style="font-size:12px;color:var(--muted)">URL: ${url}</span>
        </div>
        <div style="display:flex;gap:8px;margin-top:8px">
          <a href="${url}" target="_blank" class="btn btn-primary btn-sm">🔗 Ver propuesta</a>
          <button class="btn btn-secondary btn-sm" onclick="navigator.clipboard && navigator.clipboard.writeText('${url}').then(()=>toast('Link copiado ✅'))">📋 Copiar link</button>
        </div>`;
      toast('Propuesta generada ✅');
      setTimeout(() => location.reload(), 2500);
    } else {
      estado.innerHTML = '<div class="alert alert-error">⚠️ ' + (r.error || 'Error al generar') + '</div>';
      toast(r.error || 'Error al generar', 'err');
    }
  } catch(e) {
    estado.innerHTML = '<div class="alert alert-error">⚠️ Error de conexión con el servidor</div>';
    toast('Error de conexión', 'err');
  }

  btn.disabled = false;
  btn.textContent = '🚀 Generar y guardar propuesta';
}

function cambiarEstado(id, estado) {
  fetch('<?= APP_URL ?>/api/propuestas.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({accion:'cambiar_estado', id, estado})
  }).then(r=>r.json()).then(r => { if(r.ok) toast('Estado actualizado ✅'); else toast(r.error,'err'); });
}

function copiarLink(archivo) {
  const url = location.origin + '<?= APP_URL ?>/propuestas/' + archivo;
  navigator.clipboard && navigator.clipboard.writeText(url).then(() => toast('Link copiado ✅'));
}

function eliminarProp(id) {
  if (!confirm('¿Eliminar esta propuesta?')) return;
  fetch('<?= APP_URL ?>/api/propuestas.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({accion:'eliminar', id})
  }).then(r=>r.json()).then(r => {
    if(r.ok) { toast('Eliminada'); setTimeout(()=>location.reload(),700); }
    else toast(r.error,'err');
  });
}
</script>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
