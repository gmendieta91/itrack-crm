<?php
require_once __DIR__ . '/includes/config.php';
requiereLogin();
$u  = usuarioActual();
$db = db();

// ── Filtros ──────────────────────────────────────────────
$q            = trim($_GET['q']            ?? '');
$filtro_est   = $_GET['estado']            ?? '';
$filtro_orig  = $_GET['origen']            ?? '';
$filtro_serv  = $_GET['tipo_servicio']     ?? '';
$filtro_desde = $_GET['desde']             ?? '';
$filtro_hasta = $_GET['hasta']             ?? '';
$filtro_asig  = (int)($_GET['asignado_a'] ?? 0);

$where  = '1=1';
$params = [];

if ($q) {
    $where .= " AND (c.empresa LIKE ? OR c.nombre LIKE ? OR c.apellido LIKE ? OR c.email LIKE ? OR c.telefono LIKE ?)";
    $lq = "%$q%"; $params = array_merge($params, [$lq,$lq,$lq,$lq,$lq]);
}
if ($filtro_est)   { $where .= " AND c.estado=?";        $params[] = $filtro_est; }
if ($filtro_orig)  { $where .= " AND c.origen=?";        $params[] = $filtro_orig; }
if ($filtro_serv)  { $where .= " AND c.tipo_servicio=?"; $params[] = $filtro_serv; }
if ($filtro_asig)  { $where .= " AND c.asignado_a=?";    $params[] = $filtro_asig; }
if ($filtro_desde) { $where .= " AND c.creado_en >= ?";  $params[] = $filtro_desde . ' 00:00:00'; }
if ($filtro_hasta) { $where .= " AND c.creado_en <= ?";  $params[] = $filtro_hasta . ' 23:59:59'; }

$clientes = $db->prepare("
    SELECT c.*,
           COUNT(DISTINCT p.id)        AS total_propuestas,
           SUM(p.estado='ganada')      AS prop_ganadas,
           MAX(s.fecha_contacto)       AS ult_seguimiento,
           uv.nombre AS vend_nombre, uv.apellido AS vend_apellido
    FROM clientes c
    LEFT JOIN propuestas p  ON p.cliente_id = c.id
    LEFT JOIN seguimientos s ON s.cliente_id = c.id
    LEFT JOIN usuarios uv   ON c.asignado_a = uv.id
    WHERE $where
    GROUP BY c.id
    ORDER BY c.actualizado_en DESC
");
$clientes->execute($params);
$clientes = $clientes->fetchAll();

// Listas para filtros y formulario
$tipos_servicio = $db->query("SELECT DISTINCT tipo_servicio FROM clientes WHERE tipo_servicio IS NOT NULL AND tipo_servicio!='' ORDER BY tipo_servicio")->fetchAll(PDO::FETCH_COLUMN);
$vendedores     = $db->query("SELECT id,nombre,apellido FROM usuarios WHERE activo=1 ORDER BY nombre")->fetchAll();

$estado_cfg = [
    'prospecto'  => ['label'=>'Prospecto',  'badge'=>'badge-amber', 'icon'=>'🔍'],
    'activo'     => ['label'=>'Activo',     'badge'=>'badge-blue',  'icon'=>'⚡'],
    'cliente'    => ['label'=>'Cliente',    'badge'=>'badge-green', 'icon'=>'✅'],
    'perdido'    => ['label'=>'Perdido',    'badge'=>'badge-red',   'icon'=>'❌'],
    'inactivo'   => ['label'=>'Inactivo',   'badge'=>'badge-gray',  'icon'=>'💤'],
];
$origen_labels = [
    'redes_sociales'  => '📱 Redes sociales',
    'referido'        => '🤝 Referido',
    'web'             => '🌐 Formulario web',
    'llamada_entrante'=> '📞 Llamada entrante',
    'feria_evento'    => '🎪 Feria / Evento',
    'publicidad'      => '📣 Publicidad',
    'otro'            => '📌 Otro',
];

$activos   = count(array_filter($clientes, fn($c)=>$c['estado']==='activo'));
$prospectos= count(array_filter($clientes, fn($c)=>$c['estado']==='prospecto'));
$clientesk = count(array_filter($clientes, fn($c)=>$c['estado']==='cliente'));

define('PAGE_TITLE', 'Prospectos y Clientes');
$topbar_actions = '<button class="btn btn-primary" onclick="abrirNuevo()">+ Nuevo prospecto</button>';
include __DIR__ . '/includes/layout_top.php';
?>

<!-- MINI KPIs -->
<div style="display:flex;gap:10px;margin-bottom:18px;flex-wrap:wrap">
  <?php foreach([
    ['🔍','Prospectos',$prospectos,'var(--amber)','prospecto'],
    ['⚡','Activos',   $activos,   'var(--blue)', 'activo'],
    ['✅','Clientes',  $clientesk, 'var(--green)','cliente'],
    ['📋','Total',     count($clientes), 'var(--muted)', ''],
  ] as [$ico,$lbl,$n,$color,$est]): ?>
  <a href="?estado=<?=$est?>" style="text-decoration:none;flex:1;min-width:110px">
    <div style="background:var(--navy-card);border:1px solid <?=$est===$filtro_est?'rgba(62,180,137,.5)':'var(--border)'?>;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:10px;transition:border-color .2s">
      <span style="font-size:20px"><?=$ico?></span>
      <div><div style="font-size:20px;font-weight:900;color:<?=$color?>"><?=$n?></div><div style="font-size:11px;color:var(--muted)"><?=$lbl?></div></div>
    </div>
  </a>
  <?php endforeach; ?>
</div>

<!-- FILTROS -->
<div style="background:var(--navy-card);border:1px solid var(--border);border-radius:12px;padding:16px 20px;margin-bottom:18px">
  <form method="GET" id="form-filtros">
    <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr auto;gap:10px;align-items:end;flex-wrap:wrap">
      <div class="search-bar">
        <input type="text" name="q" value="<?=esc($q)?>" placeholder="Buscar empresa, nombre, email, tel...">
      </div>
      <select name="estado" onchange="this.form.submit()">
        <option value="">Todos los estados</option>
        <?php foreach($estado_cfg as $k=>$v): ?>
          <option value="<?=$k?>" <?=$filtro_est===$k?'selected':''?>><?=$v['icon']?> <?=$v['label']?></option>
        <?php endforeach; ?>
      </select>
      <select name="origen" onchange="this.form.submit()">
        <option value="">Todos los orígenes</option>
        <?php foreach($origen_labels as $k=>$v): ?>
          <option value="<?=$k?>" <?=$filtro_orig===$k?'selected':''?>><?=$v?></option>
        <?php endforeach; ?>
      </select>
      <select name="tipo_servicio" onchange="this.form.submit()">
        <option value="">Todos los servicios</option>
        <?php foreach($tipos_servicio as $ts): ?>
          <option value="<?=esc($ts)?>" <?=$filtro_serv===$ts?'selected':''?>><?=esc($ts)?></option>
        <?php endforeach; ?>
      </select>
      <select name="asignado_a" onchange="this.form.submit()">
        <option value="">Todos los vendedores</option>
        <?php foreach($vendedores as $vd): ?>
          <option value="<?=$vd['id']?>" <?=$filtro_asig===$vd['id']?'selected':''?>><?=esc($vd['nombre'].' '.$vd['apellido'])?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary btn-sm">Buscar</button>
    </div>
    <div style="display:flex;gap:10px;margin-top:10px;align-items:center;flex-wrap:wrap">
      <span style="font-size:12px;color:var(--muted)">Período:</span>
      <input type="date" name="desde" value="<?=esc($filtro_desde)?>" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:6px;color:var(--white);padding:6px 10px;font-family:inherit;font-size:12px">
      <span style="font-size:12px;color:var(--muted)">al</span>
      <input type="date" name="hasta" value="<?=esc($filtro_hasta)?>" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:6px;color:var(--white);padding:6px 10px;font-family:inherit;font-size:12px" onchange="this.form.submit()">
      <?php if($q||$filtro_est||$filtro_orig||$filtro_serv||$filtro_asig||$filtro_desde||$filtro_hasta): ?>
        <a href="?" class="btn btn-secondary btn-sm">✕ Quitar filtros</a>
      <?php endif; ?>
      <span style="margin-left:auto;font-size:13px;color:var(--muted)"><?=count($clientes)?> resultado<?=count($clientes)!==1?'s':''?></span>
    </div>
  </form>
</div>

<!-- TABLA -->
<div class="card">
  <div class="table-wrap">
    <?php if($clientes): ?>
    <table>
      <thead>
        <tr>
          <th>Empresa / Contacto</th>
          <th>Contacto rápido</th>
          <th>Última actividad</th>
          <th>Servicio / Precio</th>
          <th>Origen</th>
          <th>Estado</th>
          <th>Vendedor</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($clientes as $c):
          $ecfg = $estado_cfg[$c['estado']] ?? $estado_cfg['prospecto'];
          $ult  = $c['ult_seguimiento'] ?? $c['ultima_contacto'];
          $dias_sin_contacto = $ult ? round((time()-strtotime($ult))/86400) : null;
          $color_dias = $dias_sin_contacto === null ? 'var(--gray)' : ($dias_sin_contacto > 14 ? 'var(--red)' : ($dias_sin_contacto > 7 ? 'var(--amber)' : 'var(--green)'));
        ?>
        <tr>
          <td>
            <a href="<?=APP_URL?>/cliente_detalle.php?id=<?=$c['id']?>" style="text-decoration:none">
              <div style="font-weight:800;color:var(--white)"><?=esc($c['empresa'])?></div>
              <div style="font-size:12px;color:var(--muted)"><?=esc($c['nombre'].' '.$c['apellido'])?></div>
              <?php if($c['localidad']): ?><div style="font-size:11px;color:var(--gray)">📍 <?=esc($c['localidad'].($c['provincia']?', '.$c['provincia']:''))?></div><?php endif; ?>
            </a>
          </td>
          <td>
            <div style="display:flex;gap:6px;align-items:center">
              <?php if($c['telefono']): ?>
                <?php $tel_clean = preg_replace('/\D/','',$c['telefono']); ?>
                <a href="https://wa.me/<?=$tel_clean?>?text=<?=urlencode('Hola '.$c['nombre'].', te contacto de iTrack.')?>"
                   target="_blank" title="WhatsApp: <?=esc($c['telefono'])?>"
                   class="btn btn-secondary btn-sm btn-icon" style="background:rgba(37,211,102,.1);border-color:rgba(37,211,102,.3);font-size:16px">💬</a>
                <a href="tel:<?=esc($c['telefono'])?>"
                   title="Llamar: <?=esc($c['telefono'])?>"
                   class="btn btn-secondary btn-sm btn-icon" style="font-size:16px">📞</a>
              <?php endif; ?>
              <?php if($c['email']): ?>
                <a href="mailto:<?=esc($c['email'])?>?subject=<?=urlencode('iTrack - Seguimiento comercial')?>&body=<?=urlencode('Hola '.$c['nombre'].','."\n\n")?>"
                   title="Mail: <?=esc($c['email'])?>"
                   class="btn btn-secondary btn-sm btn-icon" style="font-size:16px">✉️</a>
              <?php endif; ?>
            </div>
            <?php if($c['telefono']): ?><div style="font-size:11px;color:var(--gray);margin-top:4px"><?=esc($c['telefono'])?></div><?php endif; ?>
          </td>
          <td>
            <?php if($ult): ?>
              <div style="font-size:12px;color:<?=$color_dias?>">
                <?= $dias_sin_contacto == 0 ? 'Hoy' : ($dias_sin_contacto == 1 ? 'Ayer' : "Hace {$dias_sin_contacto} días") ?>
              </div>
              <div style="font-size:11px;color:var(--gray)"><?=date('d/m/Y',strtotime($ult))?></div>
            <?php else: ?>
              <span style="font-size:12px;color:var(--red)">Sin contacto</span>
            <?php endif; ?>
            <?php if($c['proxima_accion']): ?>
              <div style="font-size:11px;color:var(--amber);margin-top:3px">⏭️ <?=esc(substr($c['proxima_accion'],0,30))?></div>
            <?php endif; ?>
          </td>
          <td>
            <?php if($c['tipo_servicio']): ?>
              <div style="font-size:12px;font-weight:700"><?=esc($c['tipo_servicio'])?></div>
              <?php if($c['precio']): ?><div style="font-size:12px;color:var(--green)">$ <?=esc($c['precio'])?>/mes</div><?php endif; ?>
              <?php if($c['cant_vehiculos']): ?><div style="font-size:11px;color:var(--muted)"><?=$c['cant_vehiculos']?> vehículos</div><?php endif; ?>
            <?php else: ?>
              <span style="font-size:12px;color:var(--gray)">Sin cotizar</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if($c['origen']): ?>
              <span style="font-size:12px"><?=$origen_labels[$c['origen']]??$c['origen']?></span>
              <?php if($c['origen_detalle']): ?><div style="font-size:11px;color:var(--muted)"><?=esc($c['origen_detalle'])?></div><?php endif; ?>
            <?php else: ?>
              <span style="color:var(--gray);font-size:12px">—</span>
            <?php endif; ?>
          </td>
          <td>
            <select onchange="cambiarEstado(<?=$c['id']?>,this.value)"
                    style="background:transparent;border:none;color:inherit;font-family:inherit;font-size:12px;cursor:pointer;font-weight:700">
              <?php foreach($estado_cfg as $k=>$v): ?>
                <option value="<?=$k?>" <?=$c['estado']===$k?'selected':''?>><?=$v['icon']?> <?=$v['label']?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td style="font-size:12px">
            <?php if($c['asignado_a']): ?>
              <?=esc($c['vend_nombre'].' '.$c['vend_apellido'])?>
            <?php else: ?>
              <span style="color:var(--gray)">Sin asignar</span>
            <?php endif; ?>
          </td>
          <td>
            <div style="display:flex;gap:5px">
              <a href="<?=APP_URL?>/cliente_detalle.php?id=<?=$c['id']?>" class="btn btn-secondary btn-sm">Ver</a>
              <button class="btn btn-secondary btn-sm btn-icon"
                      onclick='editarCliente(<?=json_encode($c,JSON_HEX_QUOT|JSON_HEX_APOS)?>)'
                      title="Editar">✏️</button>
              <?php if($u['rol']==='admin'): ?>
              <button class="btn btn-danger btn-sm btn-icon"
                      onclick="eliminarCliente(<?=$c['id']?>,'<?=esc($c['empresa'])?>')"
                      title="Eliminar">🗑️</button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="empty-state">
      <div class="empty-icon">🏢</div>
      <div class="empty-title"><?=$q||$filtro_est?'Sin resultados':'Sin prospectos aún'?></div>
      <div class="empty-desc"><?=$q||$filtro_est?'Probá con otros filtros':'Cargá tu primer prospecto para empezar'?></div>
      <?php if(!$q&&!$filtro_est): ?><button class="btn btn-primary" onclick="abrirNuevo()">+ Nuevo prospecto</button><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     MODAL UNIFICADO: PROSPECTO + COTIZACIÓN
════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="modal-cliente">
  <div class="modal" style="max-width:700px">
    <div class="modal-header">
      <div class="modal-title" id="modal-cliente-titulo">Nuevo prospecto</div>
      <button class="btn-close-modal" onclick="closeModal('modal-cliente')">×</button>
    </div>
    <div class="modal-body" style="padding:0">

      <!-- TABS del modal -->
      <div style="display:flex;border-bottom:1px solid rgba(255,255,255,.06);padding:0 24px">
        <button class="modal-tab active" data-tab="datos" onclick="switchModalTab('datos')">🏢 Datos del contacto</button>
        <button class="modal-tab" data-tab="cotizacion" onclick="switchModalTab('cotizacion')">💰 Cotización</button>
        <button class="modal-tab" data-tab="extra" onclick="switchModalTab('extra')">📋 Origen y notas</button>
      </div>

      <form id="form-cliente" style="padding:24px 28px">
        <input type="hidden" id="c-accion" value="crear">
        <input type="hidden" id="c-id"     value="">

        <!-- TAB 1: Datos del contacto -->
        <div id="tab-datos">
          <div class="form-grid two" style="margin-bottom:14px">
            <div class="field"><label>Nombre <span class="req">*</span></label><input type="text" id="c-nombre" placeholder="Nombre del contacto" required></div>
            <div class="field"><label>Apellido <span class="req">*</span></label><input type="text" id="c-apellido" placeholder="Apellido"></div>
          </div>
          <div class="field" style="margin-bottom:14px">
            <label>Empresa <span class="req">*</span></label>
            <input type="text" id="c-empresa" placeholder="Nombre de la empresa" required>
          </div>
          <div class="form-grid two" style="margin-bottom:14px">
            <div class="field">
              <label>Teléfono / WhatsApp</label>
              <input type="text" id="c-telefono" placeholder="+54 11 xxxx-xxxx">
              <div class="field-hint">Con código de área, sin el 0 ni el 15</div>
            </div>
            <div class="field"><label>Email</label><input type="email" id="c-email" placeholder="mail@empresa.com"></div>
          </div>
          <div class="field" style="margin-bottom:14px"><label>Dirección</label><input type="text" id="c-direccion" placeholder="Calle y número"></div>
          <div class="form-grid two" style="margin-bottom:14px">
            <div class="field"><label>Localidad</label><input type="text" id="c-localidad" placeholder="Ciudad"></div>
            <div class="field"><label>Provincia</label><input type="text" id="c-provincia" placeholder="Provincia"></div>
          </div>
          <div class="form-grid two">
            <div class="field">
              <label>Estado</label>
              <select id="c-estado">
                <?php foreach($estado_cfg as $k=>$v): ?>
                  <option value="<?=$k?>"><?=$v['icon']?> <?=$v['label']?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label>Vendedor asignado</label>
              <select id="c-asignado">
                <option value="">— Sin asignar —</option>
                <?php foreach($vendedores as $vd): ?>
                  <option value="<?=$vd['id']?>"><?=esc($vd['nombre'].' '.$vd['apellido'])?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <!-- TAB 2: Cotización -->
        <div id="tab-cotizacion" style="display:none">
          <div class="field" style="margin-bottom:14px">
            <label>Tipo de servicio</label>
            <select id="c-tipo-servicio">
              <option value="">— Sin cotizar aún —</option>
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
          <div id="tipo-custom-wrap" style="display:none;margin-bottom:14px">
            <div class="field"><label>Descripción del servicio</label><input type="text" id="c-tipo-custom" placeholder="Ej: Plan especial 3 meses"></div>
          </div>
          <div class="form-grid two" style="margin-bottom:14px">
            <div class="field">
              <label>Precio mensual (ARS)</label>
              <input type="text" id="c-precio" placeholder="Ej: 15.000" oninput="fmtPrecio(this)">
              <div class="field-hint">Sin $ ni IVA</div>
            </div>
            <div class="field">
              <label>Cantidad de vehículos</label>
              <input type="number" id="c-vehiculos" placeholder="Ej: 5" min="1">
            </div>
          </div>

          <!-- Logo del cliente -->
          <div class="field" style="margin-bottom:14px">
            <label>Logo del cliente</label>
            <div style="border:2px dashed rgba(62,180,137,.3);border-radius:10px;padding:18px;text-align:center;cursor:pointer;position:relative" id="logo-drop" onclick="document.getElementById('c-logo-input').click()">
              <input type="file" id="c-logo-input" accept="image/*" style="display:none" onchange="handleLogo(this)">
              <div id="logo-ph"><div style="font-size:24px">📁</div><div style="font-size:12px;color:var(--muted)">PNG, JPG · Fondo transparente recomendado</div></div>
              <div id="logo-prev" style="display:none">
                <img id="logo-prev-img" style="max-height:50px;object-fit:contain">
                <div id="logo-prev-name" style="font-size:11px;color:var(--green);margin-top:6px"></div>
              </div>
            </div>
          </div>

          <div style="background:rgba(62,180,137,.06);border:1px solid rgba(62,180,137,.15);border-radius:10px;padding:14px 18px">
            <div style="font-size:12px;font-weight:700;color:var(--green);margin-bottom:8px">💡 Generar propuesta PDF</div>
            <div style="font-size:13px;color:var(--muted)">Una vez guardado el prospecto, desde su perfil podés generar y enviar la propuesta comercial con un clic.</div>
          </div>
        </div>

        <!-- TAB 3: Origen y notas -->
        <div id="tab-extra" style="display:none">
          <div class="form-grid two" style="margin-bottom:14px">
            <div class="field">
              <label>Origen del contacto</label>
              <select id="c-origen">
                <option value="">— No especificado —</option>
                <?php foreach($origen_labels as $k=>$v): ?>
                  <option value="<?=$k?>"><?=$v?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label>Detalle del origen</label>
              <input type="text" id="c-origen-detalle" placeholder="Ej: Referido por Juan García">
            </div>
          </div>
          <div class="field" style="margin-bottom:14px">
            <label>Próxima acción</label>
            <input type="text" id="c-prox-accion" placeholder="Ej: Llamar para confirmar reunión">
          </div>
          <div class="field" style="margin-bottom:14px">
            <label>Fecha de próxima acción</label>
            <input type="date" id="c-fecha-prox">
          </div>
          <div class="field">
            <label>Notas internas</label>
            <textarea id="c-notas" rows="4" placeholder="Información adicional, historial previo, observaciones..."></textarea>
          </div>
        </div>

      </form>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeModal('modal-cliente')">Cancelar</button>
      <button type="button" class="btn btn-primary" onclick="guardarCliente()">💾 Guardar</button>
    </div>
  </div>
</div>

<style>
.modal-tab {
  background:transparent;border:none;border-bottom:2px solid transparent;
  color:var(--muted);font-family:'Barlow',sans-serif;font-size:13px;font-weight:600;
  padding:14px 16px;cursor:pointer;transition:all .2s;white-space:nowrap;
}
.modal-tab:hover { color:var(--white); }
.modal-tab.active { color:var(--green);border-bottom-color:var(--green); }
</style>

<script>
let logoBase64 = null;

function abrirNuevo() {
  document.getElementById('modal-cliente-titulo').textContent = 'Nuevo prospecto';
  document.getElementById('c-accion').value = 'crear';
  document.getElementById('c-id').value = '';
  document.getElementById('form-cliente').reset();
  logoBase64 = null;
  document.getElementById('logo-ph').style.display='block';
  document.getElementById('logo-prev').style.display='none';
  switchModalTab('datos');
  openModal('modal-cliente');
}

function editarCliente(c) {
  document.getElementById('modal-cliente-titulo').textContent = 'Editar: ' + c.empresa;
  document.getElementById('c-accion').value     = 'editar';
  document.getElementById('c-id').value         = c.id;
  document.getElementById('c-nombre').value     = c.nombre||'';
  document.getElementById('c-apellido').value   = c.apellido||'';
  document.getElementById('c-empresa').value    = c.empresa||'';
  document.getElementById('c-telefono').value   = c.telefono||'';
  document.getElementById('c-email').value      = c.email||'';
  document.getElementById('c-direccion').value  = c.direccion||'';
  document.getElementById('c-localidad').value  = c.localidad||'';
  document.getElementById('c-provincia').value  = c.provincia||'';
  document.getElementById('c-estado').value     = c.estado||'prospecto';
  document.getElementById('c-asignado').value   = c.asignado_a||'';
  document.getElementById('c-tipo-servicio').value = c.tipo_servicio||'';
  document.getElementById('c-precio').value     = c.precio||'';
  document.getElementById('c-vehiculos').value  = c.cant_vehiculos||'';
  document.getElementById('c-origen').value     = c.origen||'';
  document.getElementById('c-origen-detalle').value = c.origen_detalle||'';
  document.getElementById('c-prox-accion').value= c.proxima_accion||'';
  document.getElementById('c-fecha-prox').value = c.fecha_proxima||'';
  document.getElementById('c-notas').value      = c.notas||'';
  logoBase64 = c.logo_cliente || null;
  if (logoBase64) {
    document.getElementById('logo-ph').style.display='none';
    document.getElementById('logo-prev').style.display='block';
    document.getElementById('logo-prev-img').src = logoBase64;
    document.getElementById('logo-prev-name').textContent = 'Logo cargado';
  }
  switchModalTab('datos');
  openModal('modal-cliente');
}

function switchModalTab(tab) {
  ['datos','cotizacion','extra'].forEach(t => {
    document.getElementById('tab-'+t).style.display = t===tab ? 'block' : 'none';
  });
  document.querySelectorAll('.modal-tab').forEach(b => {
    b.classList.toggle('active', b.dataset.tab===tab);
  });
}

document.getElementById('c-tipo-servicio').addEventListener('change', function() {
  document.getElementById('tipo-custom-wrap').style.display = this.value==='personalizado' ? 'block' : 'none';
});

function fmtPrecio(input) {
  let v = input.value.replace(/\D/g,'');
  if(v) v = parseInt(v).toLocaleString('es-AR');
  input.value = v;
}

function handleLogo(input) {
  if(!input.files[0]) return;
  if(input.files[0].size > 5*1024*1024) { toast('Archivo muy grande (máx 5MB)','err'); return; }
  const reader = new FileReader();
  reader.onload = e => {
    logoBase64 = e.target.result;
    document.getElementById('logo-ph').style.display='none';
    document.getElementById('logo-prev').style.display='block';
    document.getElementById('logo-prev-img').src = logoBase64;
    document.getElementById('logo-prev-name').textContent = input.files[0].name;
  };
  reader.readAsDataURL(input.files[0]);
}

function guardarCliente() {
  const accion   = document.getElementById('c-accion').value;
  const nombre   = document.getElementById('c-nombre').value.trim();
  const apellido = document.getElementById('c-apellido').value.trim();
  const empresa  = document.getElementById('c-empresa').value.trim();
  if(!nombre||!empresa) { toast('Nombre y Empresa son obligatorios','err'); return; }

  const tipoSel = document.getElementById('c-tipo-servicio').value;
  const tipo    = tipoSel==='personalizado' ? document.getElementById('c-tipo-custom').value.trim() : tipoSel;

  const payload = {
    accion, id: document.getElementById('c-id').value,
    nombre, apellido, empresa,
    telefono:       document.getElementById('c-telefono').value.trim(),
    email:          document.getElementById('c-email').value.trim(),
    direccion:      document.getElementById('c-direccion').value.trim(),
    localidad:      document.getElementById('c-localidad').value.trim(),
    provincia:      document.getElementById('c-provincia').value.trim(),
    estado:         document.getElementById('c-estado').value,
    asignado_a:     document.getElementById('c-asignado').value,
    tipo_servicio:  tipo,
    precio:         document.getElementById('c-precio').value.trim(),
    cant_vehiculos: document.getElementById('c-vehiculos').value,
    logo_cliente:   logoBase64,
    origen:         document.getElementById('c-origen').value,
    origen_detalle: document.getElementById('c-origen-detalle').value.trim(),
    proxima_accion: document.getElementById('c-prox-accion').value.trim(),
    fecha_proxima:  document.getElementById('c-fecha-prox').value,
    notas:          document.getElementById('c-notas').value.trim(),
  };

  fetch('<?=APP_URL?>/api/clientes.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  }).then(r=>r.json()).then(r => {
    if(r.ok) {
      toast(accion==='crear' ? '✅ Prospecto creado' : '✅ Actualizado');
      closeModal('modal-cliente');
      setTimeout(()=>location.reload(), 700);
    } else toast(r.error||'Error al guardar','err');
  });
}

function cambiarEstado(id, estado) {
  fetch('<?=APP_URL?>/api/clientes.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({accion:'cambiar_estado', id, estado})
  }).then(r=>r.json()).then(r => {
    if(r.ok) {
      toast('Estado actualizado ✅');
      // Si pasó a cliente, recargar para reflejar
      if(estado==='cliente') setTimeout(()=>location.reload(),800);
    } else toast(r.error||'Error','err');
  });
}

function eliminarCliente(id, empresa) {
  if(!confirm('¿Eliminar "'+empresa+'"?\nSe eliminarán sus propuestas y seguimientos.')) return;
  fetch('<?=APP_URL?>/api/clientes.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({accion:'eliminar', id})
  }).then(r=>r.json()).then(r => {
    if(r.ok) { toast('Eliminado'); setTimeout(()=>location.reload(),700); }
    else toast(r.error||'Error','err');
  });
}
</script>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
