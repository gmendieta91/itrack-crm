<?php
require_once __DIR__ . '/includes/config.php';
requiereLogin();
$u  = usuarioActual();
$db = db();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . APP_URL . '/clientes.php'); exit; }

$cliente = $db->prepare("
    SELECT c.*, u.nombre AS creado_nombre, u.apellido AS creado_apellido,
           uv.nombre AS vend_nombre, uv.apellido AS vend_apellido
    FROM clientes c
    JOIN usuarios u  ON c.creado_por = u.id
    LEFT JOIN usuarios uv ON c.asignado_a = uv.id
    WHERE c.id = ?");
$cliente->execute([$id]);
$cliente = $cliente->fetch();
if (!$cliente) { header('Location: ' . APP_URL . '/clientes.php?msg_err=No+encontrado'); exit; }

$propuestas = $db->prepare("
    SELECT p.*, u.nombre AS v_nombre, u.apellido AS v_apellido
    FROM propuestas p JOIN usuarios u ON p.usuario_id = u.id
    WHERE p.cliente_id = ? ORDER BY p.creado_en DESC");
$propuestas->execute([$id]);
$propuestas = $propuestas->fetchAll();

$seguimientos = $db->prepare("
    SELECT s.*, u.nombre AS u_nombre, u.apellido AS u_apellido, p.tipo_servicio AS prop_tipo
    FROM seguimientos s
    JOIN usuarios u ON s.usuario_id = u.id
    LEFT JOIN propuestas p ON s.propuesta_id = p.id
    WHERE s.cliente_id = ? ORDER BY s.fecha_contacto DESC");
$seguimientos->execute([$id]);
$seguimientos = $seguimientos->fetchAll();

$vendedores = $db->query("SELECT id,nombre,apellido FROM usuarios WHERE activo=1 ORDER BY nombre")->fetchAll();

$estado_cfg = [
    'prospecto' =>['label'=>'Prospecto',  'badge'=>'badge-amber', 'icon'=>'🔍'],
    'activo'    =>['label'=>'Activo',     'badge'=>'badge-blue',  'icon'=>'⚡'],
    'cliente'   =>['label'=>'Cliente',    'badge'=>'badge-green', 'icon'=>'✅'],
    'perdido'   =>['label'=>'Perdido',    'badge'=>'badge-red',   'icon'=>'❌'],
    'inactivo'  =>['label'=>'Inactivo',   'badge'=>'badge-gray',  'icon'=>'💤'],
];
$prop_estados = [
    'borrador'       =>['label'=>'Borrador',       'badge'=>'badge-gray'],
    'enviada'        =>['label'=>'Enviada',         'badge'=>'badge-blue'],
    'en_negociacion' =>['label'=>'En negociación',  'badge'=>'badge-amber'],
    'ganada'         =>['label'=>'Ganada',           'badge'=>'badge-green'],
    'perdida'        =>['label'=>'Perdida',          'badge'=>'badge-red'],
    'pausada'        =>['label'=>'Pausada',          'badge'=>'badge-gray'],
];
$tipo_icons = ['llamada'=>'📞','reunion'=>'🤝','email'=>'✉️','whatsapp'=>'💬','nota'=>'📝','otro'=>'📌'];
$origen_labels=['redes_sociales'=>'📱 Redes sociales','referido'=>'🤝 Referido','web'=>'🌐 Web','llamada_entrante'=>'📞 Llamada entrante','feria_evento'=>'🎪 Feria/Evento','publicidad'=>'📣 Publicidad','otro'=>'📌 Otro'];

$tel_clean = preg_replace('/\D/', '', $cliente['telefono'] ?? '');
$ecfg = $estado_cfg[$cliente['estado']] ?? $estado_cfg['prospecto'];

define('PAGE_TITLE', esc($cliente['empresa']));
$topbar_actions = '<a href="' . APP_URL . '/clientes.php" class="btn btn-secondary btn-sm">← Volver</a>';
include __DIR__ . '/includes/layout_top.php';
?>

<div style="display:grid;grid-template-columns:300px 1fr;gap:20px;align-items:start">

<!-- ═══ SIDEBAR CLIENTE ═══ -->
<div style="display:flex;flex-direction:column;gap:14px">

  <!-- Tarjeta principal -->
  <div class="card">
    <div class="card-body">
      <!-- Avatar + nombre -->
      <div style="text-align:center;padding-bottom:18px;border-bottom:1px solid rgba(255,255,255,.06);margin-bottom:16px">
        <?php if ($cliente['logo_cliente']): ?>
          <img src="<?= esc($cliente['logo_cliente']) ?>" alt="Logo" style="height:64px;max-width:180px;object-fit:contain;border-radius:8px;background:rgba(255,255,255,.06);padding:10px;margin-bottom:10px">
        <?php else: ?>
          <div style="width:64px;height:64px;border-radius:50%;background:var(--green);color:var(--navy);display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:900;margin:0 auto 10px">
            <?= strtoupper(substr($cliente['empresa'], 0, 1)) ?>
          </div>
        <?php endif; ?>
        <div style="font-size:18px;font-weight:900"><?= esc($cliente['empresa']) ?></div>
        <div style="font-size:13px;color:var(--muted);margin-top:4px"><?= esc($cliente['nombre'] . ' ' . $cliente['apellido']) ?></div>
        <!-- Estado inline editable -->
        <div style="margin-top:10px">
          <select onchange="cambiarEstado(<?=$id?>,this.value)" style="background:transparent;border:1px solid rgba(255,255,255,.12);border-radius:20px;color:var(--white);font-family:inherit;font-size:12px;font-weight:700;padding:4px 12px;cursor:pointer;text-align:center">
            <?php foreach($estado_cfg as $k=>$v): ?>
              <option value="<?=$k?>" <?=$cliente['estado']===$k?'selected':''?>><?=$v['icon']?> <?=$v['label']?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- BOTONES DE CONTACTO RÁPIDO -->
      <div style="margin-bottom:16px">
        <div style="font-size:10px;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:var(--muted);margin-bottom:8px">Contacto rápido</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <?php if ($cliente['telefono']): ?>
          <a href="https://wa.me/<?= $tel_clean ?>?text=<?= urlencode('Hola ' . $cliente['nombre'] . ', te contacto de iTrack. ') ?>"
             target="_blank"
             onclick="registrarContacto(<?=$id?>,'whatsapp')"
             class="btn btn-sm" style="flex:1;justify-content:center;background:rgba(37,211,102,.12);border:1px solid rgba(37,211,102,.35);color:#25d366;font-size:13px">
            💬 WhatsApp
          </a>
          <a href="tel:<?= esc($cliente['telefono']) ?>"
             onclick="registrarContacto(<?=$id?>,'llamada')"
             class="btn btn-secondary btn-sm" style="justify-content:center">
            📞 Llamar
          </a>
          <?php endif; ?>
          <?php if ($cliente['email']): ?>
          <a href="mailto:<?= esc($cliente['email']) ?>?subject=<?= urlencode('iTrack - Seguimiento comercial') ?>&body=<?= urlencode('Hola ' . $cliente['nombre'] . ',' . "\n\n") ?>"
             onclick="registrarContacto(<?=$id?>,'email')"
             class="btn btn-secondary btn-sm" style="flex:1;justify-content:center">
            ✉️ Email
          </a>
          <?php endif; ?>
        </div>
        <?php if ($cliente['telefono']): ?>
          <div style="font-size:11px;color:var(--gray);margin-top:6px;text-align:center"><?= esc($cliente['telefono']) ?></div>
        <?php endif; ?>
        <?php if ($cliente['email']): ?>
          <div style="font-size:11px;color:var(--gray);margin-top:2px;text-align:center"><?= esc($cliente['email']) ?></div>
        <?php endif; ?>
      </div>

      <!-- Info del contacto -->
      <?php if ($cliente['direccion'] || $cliente['localidad']): ?>
      <div style="font-size:13px;color:var(--muted);display:flex;gap:8px;margin-bottom:10px">
        <span>📍</span>
        <span><?= esc(implode(', ', array_filter([$cliente['direccion'], $cliente['localidad'], $cliente['provincia']]))) ?></span>
      </div>
      <?php endif; ?>

      <!-- Última vez contactado -->
      <?php
        $ult_seg = $db->prepare("SELECT MAX(fecha_contacto) FROM seguimientos WHERE cliente_id=?");
        $ult_seg->execute([$id]);
        $ult_contact = $ult_seg->fetchColumn() ?: $cliente['ultima_contacto'];
        $dias = $ult_contact ? round((time()-strtotime($ult_contact))/86400) : null;
        $col_dias = $dias === null ? 'var(--gray)' : ($dias > 14 ? 'var(--red)' : ($dias > 7 ? 'var(--amber)' : 'var(--green)'));
      ?>
      <div style="background:rgba(0,0,0,.2);border-radius:8px;padding:10px 12px;font-size:12px;margin-bottom:12px">
        <div style="color:var(--muted);margin-bottom:3px">Último contacto</div>
        <div style="font-weight:700;color:<?= $col_dias ?>">
          <?php if ($dias === null): ?>Sin registrar
          <?php elseif ($dias == 0): ?>Hoy
          <?php elseif ($dias == 1): ?>Ayer
          <?php else: ?>Hace <?= $dias ?> días (<?= date('d/m/Y', strtotime($ult_contact)) ?>)
          <?php endif; ?>
        </div>
      </div>

      <!-- Próxima acción -->
      <?php if ($cliente['proxima_accion']): ?>
      <div style="background:rgba(245,166,35,.08);border:1px solid rgba(245,166,35,.25);border-radius:8px;padding:10px 12px;font-size:12px;margin-bottom:12px">
        <div style="color:var(--amber);font-weight:700;margin-bottom:3px">⏭️ Próxima acción</div>
        <div><?= esc($cliente['proxima_accion']) ?></div>
        <?php if ($cliente['fecha_proxima']): ?>
          <div style="color:var(--amber);margin-top:4px">📅 <?= date('d/m/Y', strtotime($cliente['fecha_proxima'])) ?></div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Datos de la cotización -->
      <?php if ($cliente['tipo_servicio']): ?>
      <div style="background:rgba(62,180,137,.06);border:1px solid rgba(62,180,137,.18);border-radius:8px;padding:12px;margin-bottom:12px">
        <div style="font-size:10px;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:var(--green);margin-bottom:8px">💰 Cotización</div>
        <div style="font-size:13px;font-weight:700"><?= esc($cliente['tipo_servicio']) ?></div>
        <?php if ($cliente['precio']): ?><div style="font-size:16px;font-weight:900;color:var(--green)">$ <?= esc($cliente['precio']) ?>/mes</div><?php endif; ?>
        <?php if ($cliente['cant_vehiculos']): ?><div style="font-size:12px;color:var(--muted)"><?= $cliente['cant_vehiculos'] ?> vehículos</div><?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Origen -->
      <?php if ($cliente['origen']): ?>
      <div style="font-size:12px;color:var(--muted);margin-bottom:8px">
        <?= $origen_labels[$cliente['origen']] ?? $cliente['origen'] ?>
        <?php if ($cliente['origen_detalle']): ?> · <?= esc($cliente['origen_detalle']) ?><?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Vendedor asignado -->
      <div style="font-size:12px;color:var(--muted);border-top:1px solid rgba(255,255,255,.06);padding-top:10px;margin-top:4px">
        👤 Asignado a: <strong style="color:var(--white)"><?= $cliente['asignado_a'] ? esc($cliente['vend_nombre'].' '.$cliente['vend_apellido']) : 'Sin asignar' ?></strong>
        <br>📅 Creado: <?= date('d/m/Y', strtotime($cliente['creado_en'])) ?>
        por <?= esc($cliente['creado_nombre'].' '.$cliente['creado_apellido']) ?>
      </div>
    </div>
  </div>

  <!-- Editar prospecto -->
  <button class="btn btn-secondary" style="width:100%;justify-content:center" onclick="openModal('modal-editar')">✏️ Editar datos</button>

</div>

<!-- ═══ CONTENIDO PRINCIPAL ═══ -->
<div style="display:flex;flex-direction:column;gap:16px">

  <!-- GENERAR PROPUESTA -->
  <div class="card" style="border-color:rgba(62,180,137,.35)">
    <div class="card-header">
      <span class="card-title">🚀 Generar propuesta comercial</span>
    </div>
    <div class="card-body">
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:12px;align-items:end">
        <div class="field">
          <label>Tipo de servicio</label>
          <select id="gen-tipo">
            <option value="">— Seleccioná —</option>
            <?php
            $tipos = ['Mensual por vehículo','Mensual por dispositivo','Plan Flota Pequeña (1-5 veh.)','Plan Flota Mediana (6-20 veh.)','Plan Flota Grande (21+ veh.)','Plan Corporativo','Plan Anual con descuento'];
            foreach($tipos as $t) echo '<option value="'.esc($t).'"'.($cliente['tipo_servicio']===$t?' selected':'').'>'.esc($t).'</option>';
            ?>
            <option value="personalizado">✏️ Otro...</option>
          </select>
        </div>
        <div class="field">
          <label>Precio (ARS/mes)</label>
          <input type="text" id="gen-precio" value="<?= esc($cliente['precio'] ?? '') ?>" placeholder="Ej: 15.000" oninput="fmtP(this)">
        </div>
        <div class="field">
          <label>Vehículos</label>
          <input type="number" id="gen-veh" value="<?= esc($cliente['cant_vehiculos'] ?? '') ?>" placeholder="Cantidad" min="1">
        </div>
        <button class="btn btn-primary" onclick="generarPropuesta()" id="btn-gen" style="white-space:nowrap">🚀 Generar</button>
      </div>
      <div id="gen-tipo-custom" style="display:none;margin-top:10px">
        <div class="field"><label>Descripción del servicio</label><input type="text" id="gen-tipo-txt" placeholder="Ej: Plan especial 3 meses"></div>
      </div>
      <div id="gen-logo-wrap" style="margin-top:12px;display:flex;align-items:center;gap:10px">
        <label style="font-size:12px;color:var(--muted);text-transform:none;letter-spacing:0">Logo del cliente:</label>
        <?php if ($cliente['logo_cliente']): ?>
          <img src="<?= esc($cliente['logo_cliente']) ?>" style="height:30px;object-fit:contain;background:rgba(255,255,255,.06);padding:4px 8px;border-radius:4px">
          <span style="font-size:11px;color:var(--green)">✓ Logo guardado</span>
        <?php endif; ?>
        <label class="btn btn-secondary btn-sm" style="cursor:pointer;margin-left:auto">
          📁 <?= $cliente['logo_cliente'] ? 'Cambiar logo' : 'Subir logo' ?>
          <input type="file" id="gen-logo" accept="image/*" style="display:none" onchange="handleLogo(this)">
        </label>
        <span id="gen-logo-name" style="font-size:11px;color:var(--green)"></span>
      </div>
      <div id="gen-resultado" style="display:none;margin-top:14px"></div>
    </div>
  </div>

  <!-- PROPUESTAS ANTERIORES -->
  <?php if ($propuestas): ?>
  <div class="card">
    <div class="card-header"><span class="card-title">📄 Propuestas generadas</span></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Servicio</th><th>Precio</th><th>Vehículos</th><th>Estado</th><th>Fecha</th><th></th></tr></thead>
        <tbody>
        <?php foreach($propuestas as $p):
          $pe = $prop_estados[$p['estado']] ?? ['label'=>$p['estado'],'badge'=>'badge-gray'];
        ?>
        <tr>
          <td style="font-weight:700"><?= esc($p['tipo_servicio']) ?></td>
          <td style="color:var(--green);font-weight:700">$ <?= esc($p['precio']) ?></td>
          <td style="color:var(--muted)"><?= $p['cant_vehiculos'] ? $p['cant_vehiculos'].' veh.' : '—' ?></td>
          <td>
            <select onchange="cambiarEstadoProp(<?=$p['id']?>,this.value)" style="background:transparent;border:none;font-family:inherit;font-size:12px;color:inherit;cursor:pointer">
              <?php foreach($prop_estados as $k=>$pe2): ?><option value="<?=$k?>" <?=$p['estado']===$k?'selected':''?>><?=$pe2['label']?></option><?php endforeach; ?>
            </select>
          </td>
          <td style="font-size:12px;color:var(--muted)"><?= date('d/m/Y',strtotime($p['creado_en'])) ?></td>
          <td>
            <?php $pf = PROPUESTAS_DIR . $p['nombre_archivo']; ?>
            <?php if ($p['nombre_archivo'] && file_exists($pf)): ?>
              <div style="display:flex;gap:6px">
                <a href="<?= APP_URL ?>/propuestas/<?= esc($p['nombre_archivo']) ?>" target="_blank" class="btn btn-secondary btn-sm">🔗 Ver</a>
                <button onclick="copiarLink('<?= esc($p['nombre_archivo']) ?>')" class="btn btn-secondary btn-sm" title="Copiar link">📋</button>
              </div>
            <?php else: ?>
              <span style="font-size:11px;color:var(--gray)">Sin archivo</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- SEGUIMIENTOS / TIMELINE -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">🎯 Historial de seguimiento</span>
      <button class="btn btn-primary btn-sm" onclick="openModal('modal-seg')">+ Agregar</button>
    </div>
    <div class="card-body">
      <?php if ($seguimientos): ?>
      <div class="timeline">
        <?php foreach($seguimientos as $s):
          $ico = $tipo_icons[$s['tipo']] ?? '📌';
        ?>
        <div class="timeline-item">
          <div class="timeline-dot"><?= $ico ?></div>
          <div class="timeline-content">
            <div class="timeline-header">
              <span class="timeline-titulo"><?= esc($s['titulo']) ?></span>
              <div style="display:flex;align-items:center;gap:10px">
                <span class="timeline-fecha"><?= date('d/m/Y H:i',strtotime($s['fecha_contacto'])) ?> · <?= esc($s['u_nombre'].' '.$s['u_apellido']) ?></span>
                <button onclick="eliminarSeg(<?=$s['id']?>)" class="btn btn-danger btn-sm btn-icon" title="Eliminar" style="padding:3px 6px;font-size:12px">🗑️</button>
              </div>
            </div>
            <?php if($s['descripcion']): ?><div class="timeline-desc"><?= nl2br(esc($s['descripcion'])) ?></div><?php endif; ?>
            <?php if($s['proxima_accion']): ?><div class="timeline-proxima">⏭️ <?= esc($s['proxima_accion']) ?><?= $s['fecha_proxima'] ? ' · '.date('d/m/Y',strtotime($s['fecha_proxima'])) : '' ?></div><?php endif; ?>
            <?php if($s['prop_tipo']): ?><div style="font-size:11px;color:var(--blue);margin-top:5px">📄 <?= esc($s['prop_tipo']) ?></div><?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
        <div class="empty-state" style="padding:24px 0">
          <div class="empty-icon">🎯</div>
          <div class="empty-title">Sin seguimientos</div>
          <div class="empty-desc">Registrá llamadas, reuniones, emails y más</div>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /contenido -->
</div><!-- /grid -->

<!-- MODAL EDITAR CLIENTE -->
<div class="modal-backdrop" id="modal-editar">
  <div class="modal" style="max-width:600px">
    <div class="modal-header">
      <div class="modal-title">✏️ Editar: <?= esc($cliente['empresa']) ?></div>
      <button class="btn-close-modal" onclick="closeModal('modal-editar')">×</button>
    </div>
    <form id="form-editar">
      <div class="modal-body">
        <input type="hidden" name="accion" value="editar">
        <input type="hidden" name="id"     value="<?= $id ?>">
        <div class="form-grid two" style="margin-bottom:14px">
          <div class="field"><label>Nombre</label><input type="text" name="nombre"   value="<?=esc($cliente['nombre'])?>"   required></div>
          <div class="field"><label>Apellido</label><input type="text" name="apellido" value="<?=esc($cliente['apellido'])?>"></div>
        </div>
        <div class="field" style="margin-bottom:14px"><label>Empresa</label><input type="text" name="empresa" value="<?=esc($cliente['empresa'])?>" required></div>
        <div class="form-grid two" style="margin-bottom:14px">
          <div class="field"><label>Teléfono</label><input type="text" name="telefono" value="<?=esc($cliente['telefono'])?>"></div>
          <div class="field"><label>Email</label><input type="email" name="email" value="<?=esc($cliente['email'])?>"></div>
        </div>
        <div class="form-grid two" style="margin-bottom:14px">
          <div class="field"><label>Localidad</label><input type="text" name="localidad" value="<?=esc($cliente['localidad'])?>"></div>
          <div class="field"><label>Provincia</label><input type="text" name="provincia" value="<?=esc($cliente['provincia'])?>"></div>
        </div>
        <div class="form-grid two" style="margin-bottom:14px">
          <div class="field"><label>Estado</label>
            <select name="estado">
              <?php foreach($estado_cfg as $k=>$v): ?><option value="<?=$k?>" <?=$cliente['estado']===$k?'selected':''?>><?=$v['icon']?> <?=$v['label']?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field"><label>Asignado a</label>
            <select name="asignado_a">
              <option value="">— Sin asignar —</option>
              <?php foreach($vendedores as $vd): ?><option value="<?=$vd['id']?>" <?=$cliente['asignado_a']==$vd['id']?'selected':''?>><?=esc($vd['nombre'].' '.$vd['apellido'])?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="field" style="margin-bottom:14px"><label>Próxima acción</label><input type="text" name="proxima_accion" value="<?=esc($cliente['proxima_accion'])?>"></div>
        <div class="form-grid two" style="margin-bottom:14px">
          <div class="field"><label>Fecha próxima acción</label><input type="date" name="fecha_proxima" value="<?=esc($cliente['fecha_proxima'])?>"></div>
          <div class="field"><label>Origen</label>
            <select name="origen">
              <option value="">— No especificado —</option>
              <?php foreach($origen_labels as $k=>$v): ?><option value="<?=$k?>" <?=$cliente['origen']===$k?'selected':''?>><?=$v?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="field"><label>Notas</label><textarea name="notas" rows="3"><?=esc($cliente['notas'])?></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-editar')">Cancelar</button>
        <button type="button" class="btn btn-primary" onclick="guardarEdicion()">💾 Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL SEGUIMIENTO -->
<div class="modal-backdrop" id="modal-seg">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Agregar seguimiento</div>
      <button class="btn-close-modal" onclick="closeModal('modal-seg')">×</button>
    </div>
    <form id="form-seg">
      <div class="modal-body">
        <input type="hidden" name="accion"     value="crear">
        <input type="hidden" name="cliente_id" value="<?= $id ?>">
        <div class="form-grid two" style="margin-bottom:14px">
          <div class="field"><label>Tipo</label>
            <select name="tipo">
              <option value="llamada">📞 Llamada</option>
              <option value="reunion">🤝 Reunión</option>
              <option value="email">✉️ Email</option>
              <option value="whatsapp">💬 WhatsApp</option>
              <option value="nota" selected>📝 Nota</option>
              <option value="otro">📌 Otro</option>
            </select>
          </div>
          <div class="field"><label>Propuesta vinculada</label>
            <select name="propuesta_id">
              <option value="">— Ninguna —</option>
              <?php foreach($propuestas as $p): ?><option value="<?=$p['id']?>"><?=esc($p['tipo_servicio'])?> (<?=date('d/m/Y',strtotime($p['creado_en']))?>) </option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="field" style="margin-bottom:14px"><label>Título <span class="req">*</span></label><input type="text" name="titulo" required placeholder="Ej: Llamada de seguimiento"></div>
        <div class="field" style="margin-bottom:14px"><label>Descripción</label><textarea name="descripcion" rows="3" placeholder="Detalle de lo conversado..."></textarea></div>
        <div class="form-grid two">
          <div class="field"><label>Próxima acción</label><input type="text" name="proxima_accion" placeholder="Ej: Enviar propuesta"></div>
          <div class="field"><label>Fecha</label><input type="date" name="fecha_proxima" value="<?=date('Y-m-d',strtotime('+3 days'))?>"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-seg')">Cancelar</button>
        <button type="button" class="btn btn-primary" onclick="guardarSeg()">Guardar seguimiento</button>
      </div>
    </form>
  </div>
</div>

<script>
// ── Logo ──────────────────────────────────────────────────
let logoNuevo = null;

function handleLogo(input) {
  if (!input.files[0]) return;
  if (input.files[0].size > 5*1024*1024) { toast('Archivo muy grande','err'); return; }
  const reader = new FileReader();
  reader.onload = e => {
    logoNuevo = e.target.result;
    document.getElementById('gen-logo-name').textContent = '✓ ' + input.files[0].name;
  };
  reader.readAsDataURL(input.files[0]);
}

function fmtP(i) {
  let v = i.value.replace(/\D/g,'');
  if(v) v = parseInt(v).toLocaleString('es-AR');
  i.value = v;
}

document.getElementById('gen-tipo').addEventListener('change', function() {
  document.getElementById('gen-tipo-custom').style.display = this.value==='personalizado' ? 'block' : 'none';
});

// ── Generar propuesta ────────────────────────────────────
async function generarPropuesta() {
  const tipoSel = document.getElementById('gen-tipo').value;
  const tipo    = tipoSel==='personalizado' ? document.getElementById('gen-tipo-txt').value.trim() : tipoSel;
  const precio  = document.getElementById('gen-precio').value.trim();
  if (!tipo)   { toast('Seleccioná el tipo de servicio','err'); return; }
  if (!precio) { toast('Ingresá el precio','err'); return; }

  const btn = document.getElementById('btn-gen');
  btn.disabled = true; btn.textContent='⏳ Generando...';
  const res = document.getElementById('gen-resultado');
  res.style.display='block';
  res.innerHTML='<div class="alert alert-info">⏳ Generando y guardando propuesta...</div>';

  const logo = logoNuevo || <?= $cliente['logo_cliente'] ? json_encode($cliente['logo_cliente']) : 'null' ?>;

  try {
    const r = await fetch('<?=APP_URL?>/api/propuestas.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({
        accion:'generar',
        cliente_id: <?=$id?>,
        tipo_servicio: tipo,
        precio,
        cant_vehiculos: document.getElementById('gen-veh').value || null,
        logo_cliente: logo,
        notas: ''
      })
    });
    const d = await r.json();
    if (d.ok) {
      const url = '<?= rtrim((isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].APP_URL, '/') ?>/propuestas/' + d.archivo;
      res.innerHTML = `<div class="alert alert-success">✅ Propuesta generada: <strong>${d.archivo}</strong></div>
        <div style="display:flex;gap:8px;margin-top:8px">
          <a href="${url}" target="_blank" class="btn btn-primary btn-sm">🔗 Ver propuesta</a>
          <button onclick="navigator.clipboard.writeText('${url}').then(()=>toast('Link copiado ✅'))" class="btn btn-secondary btn-sm">📋 Copiar link</button>
        </div>`;
      toast('✅ Propuesta generada');
      setTimeout(()=>location.reload(), 3000);
    } else {
      res.innerHTML='<div class="alert alert-error">⚠️ '+(d.error||'Error')+'</div>';
      toast(d.error||'Error','err');
    }
  } catch(e) {
    res.innerHTML='<div class="alert alert-error">⚠️ Error de conexión</div>';
  }
  btn.disabled=false; btn.textContent='🚀 Generar';
}

// ── Estados ───────────────────────────────────────────────
function cambiarEstado(id, estado) {
  fetch('<?=APP_URL?>/api/clientes.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({accion:'cambiar_estado', id, estado})
  }).then(r=>r.json()).then(r=>{
    if(r.ok) { toast('Estado actualizado ✅'); setTimeout(()=>location.reload(),600); }
    else toast(r.error||'Error','err');
  });
}

function cambiarEstadoProp(id, estado) {
  fetch('<?=APP_URL?>/api/propuestas.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({accion:'cambiar_estado', id, estado})
  }).then(r=>r.json()).then(r=>{ if(r.ok) toast('Estado actualizado ✅'); else toast(r.error,'err'); });
}

function copiarLink(archivo) {
  const url = '<?= rtrim((isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].APP_URL, '/') ?>/propuestas/' + archivo;
  navigator.clipboard && navigator.clipboard.writeText(url).then(()=>toast('Link copiado ✅'));
}

// ── Contacto rápido ───────────────────────────────────────
function registrarContacto(id, tipo) {
  // Registrar que hubo un contacto y actualizar ultima_contacto
  fetch('<?=APP_URL?>/api/clientes.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({accion:'actualizar_contacto', id})
  });
  // También crear un seguimiento automático liviano
  fetch('<?=APP_URL?>/api/seguimientos.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({
      accion:'crear', cliente_id:id, tipo,
      titulo: tipo==='whatsapp' ? 'Contacto por WhatsApp' : tipo==='email' ? 'Email enviado' : 'Llamada realizada',
      descripcion: ''
    })
  });
}

// ── Editar cliente ────────────────────────────────────────
function guardarEdicion() {
  const data = Object.fromEntries(new FormData(document.getElementById('form-editar')));
  fetch('<?=APP_URL?>/api/clientes.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify(data)
  }).then(r=>r.json()).then(r=>{
    if(r.ok) { toast('✅ Guardado'); closeModal('modal-editar'); setTimeout(()=>location.reload(),700); }
    else toast(r.error||'Error','err');
  });
}

// ── Seguimientos ──────────────────────────────────────────
function guardarSeg() {
  const data = Object.fromEntries(new FormData(document.getElementById('form-seg')));
  if (!data.titulo) { toast('El título es obligatorio','err'); return; }
  fetch('<?=APP_URL?>/api/seguimientos.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify(data)
  }).then(r=>r.json()).then(r=>{
    if(r.ok) { toast('✅ Seguimiento registrado'); closeModal('modal-seg'); setTimeout(()=>location.reload(),700); }
    else toast(r.error||'Error','err');
  });
}

function eliminarSeg(id) {
  if(!confirm('¿Eliminar este seguimiento?')) return;
  fetch('<?=APP_URL?>/api/seguimientos.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({accion:'eliminar', id})
  }).then(r=>r.json()).then(r=>{
    if(r.ok) { toast('Eliminado'); setTimeout(()=>location.reload(),600); }
    else toast(r.error||'Error','err');
  });
}
</script>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
