<?php
require_once __DIR__ . '/includes/config.php';
requiereLogin();
$u  = usuarioActual();
$db = db();

// ── KPIs ────────────────────────────────────────────────
$total_clientes   = $db->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
$total_prosp      = $db->query("SELECT COUNT(*) FROM clientes WHERE estado='prospecto'")->fetchColumn();
$total_activos    = $db->query("SELECT COUNT(*) FROM clientes WHERE estado='activo'")->fetchColumn();
$total_clientes_c = $db->query("SELECT COUNT(*) FROM clientes WHERE estado='cliente'")->fetchColumn();
$total_propuestas = $db->query("SELECT COUNT(*) FROM propuestas")->fetchColumn();
$prop_ganadas     = $db->query("SELECT COUNT(*) FROM propuestas WHERE estado='ganada'")->fetchColumn();
$prop_pendientes  = $db->query("SELECT COUNT(*) FROM propuestas WHERE estado IN('enviada','en_negociacion')")->fetchColumn();
$tasa_cierre      = $total_propuestas > 0 ? round(($prop_ganadas/$total_propuestas)*100) : 0;
$nuevos_mes       = $db->query("SELECT COUNT(*) FROM clientes WHERE MONTH(creado_en)=MONTH(NOW()) AND YEAR(creado_en)=YEAR(NOW())")->fetchColumn();

// Sin contacto hace más de 7 días
$sin_contacto = $db->query("
    SELECT COUNT(*) FROM clientes
    WHERE estado IN('prospecto','activo')
    AND (ultima_contacto IS NULL OR ultima_contacto < DATE_SUB(NOW(), INTERVAL 7 DAY))
")->fetchColumn();

// ── Mis prospectos (del vendedor logueado) ───────────────
$mis_prospectos = $db->prepare("
    SELECT c.*, MAX(s.fecha_contacto) AS ult_seg
    FROM clientes c
    LEFT JOIN seguimientos s ON s.cliente_id=c.id
    WHERE c.asignado_a=? AND c.estado IN('prospecto','activo')
    GROUP BY c.id
    ORDER BY c.actualizado_en DESC LIMIT 8
");
$mis_prospectos->execute([$u['id']]);
$mis_prospectos = $mis_prospectos->fetchAll();

// ── Últimas propuestas ──────────────────────────────────
$ultimas_prop = $db->query("
    SELECT p.*, c.empresa, c.nombre AS c_nom, c.apellido AS c_ap,
           u.nombre AS v_nom, u.apellido AS v_ap
    FROM propuestas p
    JOIN clientes c ON p.cliente_id=c.id
    JOIN usuarios u ON p.usuario_id=u.id
    ORDER BY p.creado_en DESC LIMIT 6
")->fetchAll();

// ── Propuestas por estado ──────────────────────────────
$por_estado = $db->query("SELECT estado, COUNT(*) AS n FROM propuestas GROUP BY estado")->fetchAll(PDO::FETCH_KEY_PAIR);

// ── Top vendedores ─────────────────────────────────────
$top_vend = $db->query("
    SELECT u.nombre, u.apellido, COUNT(DISTINCT c.id) AS prospectos,
           COUNT(p.id) AS propuestas, SUM(p.estado='ganada') AS ganadas
    FROM usuarios u
    LEFT JOIN clientes  c ON c.asignado_a=u.id
    LEFT JOIN propuestas p ON p.usuario_id=u.id
    WHERE u.activo=1 AND u.rol='vendedor'
    GROUP BY u.id ORDER BY propuestas DESC LIMIT 5
")->fetchAll();

// ── Próximas acciones (7 días) ──────────────────────────
$proximas = $db->query("
    SELECT c.id, c.empresa, c.nombre, c.apellido, c.telefono, c.email,
           c.proxima_accion, c.fecha_proxima, c.estado
    FROM clientes c
    WHERE c.fecha_proxima BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
      AND c.proxima_accion IS NOT NULL
    ORDER BY c.fecha_proxima ASC LIMIT 8
")->fetchAll();

// ── Actividad reciente ──────────────────────────────────
$actividad = $db->query("
    SELECT s.tipo, s.titulo, s.creado_en, c.empresa, c.id AS cid,
           u.nombre, u.apellido
    FROM seguimientos s
    JOIN clientes c ON s.cliente_id=c.id
    JOIN usuarios u ON s.usuario_id=u.id
    ORDER BY s.creado_en DESC LIMIT 8
")->fetchAll();

$estado_cfg = [
    'enviada'        =>['label'=>'Enviada',        'badge'=>'badge-blue'],
    'en_negociacion' =>['label'=>'En negociación',  'badge'=>'badge-amber'],
    'ganada'         =>['label'=>'Ganada',           'badge'=>'badge-green'],
    'perdida'        =>['label'=>'Perdida',          'badge'=>'badge-red'],
    'pausada'        =>['label'=>'Pausada',          'badge'=>'badge-gray'],
    'borrador'       =>['label'=>'Borrador',         'badge'=>'badge-gray'],
];
$tipo_icons=['llamada'=>'📞','reunion'=>'🤝','email'=>'✉️','whatsapp'=>'💬','nota'=>'📝','otro'=>'📌'];

define('PAGE_TITLE', 'Dashboard');
$topbar_actions = '<span style="font-size:13px;color:var(--muted)">📅 ' . date('d/m/Y') . ' — Bienvenido/a, <strong style="color:var(--white)">' . esc($u['nombre']) . '</strong></span>';
include __DIR__ . '/includes/layout_top.php';
?>

<!-- ── KPI STATS ── -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon amber">🔍</div>
    <div>
      <div class="stat-value"><?= $total_prosp ?></div>
      <div class="stat-label">Prospectos activos</div>
      <div class="stat-trend" style="color:var(--amber)">+<?= $nuevos_mes ?> este mes</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green">✅</div>
    <div>
      <div class="stat-value"><?= $total_clientes_c ?></div>
      <div class="stat-label">Clientes activos</div>
      <div class="stat-trend trend-up"><?= $total_activos ?> en negociación</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue">📄</div>
    <div>
      <div class="stat-value"><?= $prop_pendientes ?></div>
      <div class="stat-label">Propuestas abiertas</div>
      <div class="stat-trend" style="color:var(--blue)"><?= $total_propuestas ?> totales</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon <?= $sin_contacto > 0 ? 'red' : 'green' ?>">⚠️</div>
    <div>
      <div class="stat-value" style="color:<?= $sin_contacto > 0 ? 'var(--red)' : 'var(--green)' ?>"><?= $sin_contacto ?></div>
      <div class="stat-label">Sin contacto +7 días</div>
      <div class="stat-trend" style="color:var(--<?=$sin_contacto>0?'red':'green'?>)"><?= $sin_contacto > 0 ? 'Requieren atención' : 'Todo al día ✓' ?></div>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;margin-bottom:20px">

  <!-- MIS PROSPECTOS ASIGNADOS -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">📋 Mis prospectos</span>
      <a href="<?= APP_URL ?>/clientes.php?asignado_a=<?= $u['id'] ?>" class="btn btn-secondary btn-sm">Ver todos</a>
    </div>
    <?php if ($mis_prospectos): ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Empresa</th><th>Contacto rápido</th><th>Último contacto</th><th>Próxima acción</th><th></th></tr></thead>
        <tbody>
        <?php foreach($mis_prospectos as $c):
          $ult = $c['ult_seg'] ?: $c['ultima_contacto'];
          $dias = $ult ? round((time()-strtotime($ult))/86400) : null;
          $col  = $dias===null ? 'var(--red)' : ($dias>14?'var(--red)':($dias>7?'var(--amber)':'var(--green)'));
          $tel_c = preg_replace('/\D/','',$c['telefono']??'');
        ?>
        <tr>
          <td>
            <a href="<?=APP_URL?>/cliente_detalle.php?id=<?=$c['id']?>" style="text-decoration:none;font-weight:700;color:var(--white)"><?=esc($c['empresa'])?></a>
            <div style="font-size:12px;color:var(--muted)"><?=esc($c['nombre'].' '.$c['apellido'])?></div>
          </td>
          <td>
            <div style="display:flex;gap:6px">
              <?php if($c['telefono']): ?>
                <a href="https://wa.me/<?=$tel_c?>?text=<?=urlencode('Hola '.$c['nombre'].', te contacto de iTrack.')?>"
                   target="_blank" class="btn btn-sm btn-icon" style="background:rgba(37,211,102,.12);border:1px solid rgba(37,211,102,.3);font-size:14px">💬</a>
                <a href="tel:<?=esc($c['telefono'])?>" class="btn btn-secondary btn-sm btn-icon" style="font-size:14px">📞</a>
              <?php endif; ?>
              <?php if($c['email']): ?>
                <a href="mailto:<?=esc($c['email'])?>" class="btn btn-secondary btn-sm btn-icon" style="font-size:14px">✉️</a>
              <?php endif; ?>
            </div>
            <?php if($c['telefono']): ?><div style="font-size:10px;color:var(--gray);margin-top:3px"><?=esc($c['telefono'])?></div><?php endif; ?>
          </td>
          <td>
            <div style="font-size:12px;color:<?=$col?>;font-weight:700">
              <?= $dias===null ? 'Nunca' : ($dias==0?'Hoy':($dias==1?'Ayer':"Hace {$dias}d")) ?>
            </div>
            <?php if($ult): ?><div style="font-size:11px;color:var(--gray)"><?=date('d/m/Y',strtotime($ult))?></div><?php endif; ?>
          </td>
          <td>
            <?php if($c['proxima_accion']): ?>
              <div style="font-size:12px"><?=esc(substr($c['proxima_accion'],0,35))?></div>
              <?php if($c['fecha_proxima']): ?><div style="font-size:11px;color:var(--amber)">📅 <?=date('d/m',strtotime($c['fecha_proxima']))?></div><?php endif; ?>
            <?php else: ?>
              <span style="font-size:12px;color:var(--gray)">—</span>
            <?php endif; ?>
          </td>
          <td><a href="<?=APP_URL?>/cliente_detalle.php?id=<?=$c['id']?>" class="btn btn-secondary btn-sm">Ver</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
      <div class="empty-state" style="padding:30px">
        <div class="empty-icon">📋</div>
        <div class="empty-title">Sin prospectos asignados</div>
        <div class="empty-desc">Los prospectos asignados a vos aparecerán aquí</div>
      </div>
    <?php endif; ?>
  </div>

  <!-- PANEL DERECHO -->
  <div style="display:flex;flex-direction:column;gap:16px">

    <!-- Propuestas por estado -->
    <div class="card">
      <div class="card-header"><span class="card-title">📊 Propuestas</span></div>
      <div class="card-body" style="padding:14px 18px">
        <?php
        $lbl = ['enviada'=>'Enviadas','en_negociacion'=>'En negociación','ganada'=>'Ganadas','perdida'=>'Perdidas','borrador'=>'Borrador'];
        $cols = ['enviada'=>'var(--blue)','en_negociacion'=>'var(--amber)','ganada'=>'var(--green)','perdida'=>'var(--red)','borrador'=>'var(--gray)'];
        foreach($lbl as $k=>$l):
          $n   = $por_estado[$k] ?? 0;
          $pct = $total_propuestas > 0 ? round(($n/$total_propuestas)*100) : 0;
        ?>
        <div style="margin-bottom:10px">
          <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px">
            <span style="color:var(--muted)"><?=$l?></span>
            <span style="font-weight:700;color:<?=$cols[$k]?>"><?=$n?></span>
          </div>
          <div style="height:4px;background:rgba(255,255,255,.07);border-radius:2px">
            <div style="height:4px;border-radius:2px;width:<?=$pct?>%;background:<?=$cols[$k]?>;transition:width .6s"></div>
          </div>
        </div>
        <?php endforeach; ?>
        <div style="text-align:center;margin-top:14px;font-size:22px;font-weight:900;color:var(--green)"><?=$tasa_cierre?>%<div style="font-size:11px;font-weight:400;color:var(--muted)">Tasa de cierre</div></div>
      </div>
    </div>

    <!-- Top vendedores -->
    <?php if ($top_vend && $u['rol']==='admin'): ?>
    <div class="card">
      <div class="card-header"><span class="card-title">🏆 Equipo</span></div>
      <div class="card-body" style="padding:10px 18px">
        <?php foreach($top_vend as $i=>$v): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:8px 0;<?=$i<count($top_vend)-1?'border-bottom:1px solid rgba(255,255,255,.04)':''?>">
          <span style="font-size:16px;width:20px"><?=['🥇','🥈','🥉','4️⃣','5️⃣'][$i]??($i+1)?></span>
          <div style="flex:1">
            <div style="font-size:13px;font-weight:700"><?=esc($v['nombre'].' '.$v['apellido'])?></div>
            <div style="font-size:11px;color:var(--muted)"><?=$v['prospectos']?> prospectos · <?=$v['propuestas']?> prop. · <?=$v['ganadas']?> ganadas</div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

  <!-- PRÓXIMAS ACCIONES -->
  <div class="card">
    <div class="card-header"><span class="card-title">⏰ Acciones próximos 7 días</span></div>
    <div class="card-body">
      <?php if($proximas): foreach($proximas as $p):
        $hoy = date('Y-m-d');
        $es_hoy = $p['fecha_proxima'] === $hoy;
        $tel_c = preg_replace('/\D/','',$p['telefono']??'');
      ?>
      <div style="display:flex;gap:12px;padding:10px 0;border-bottom:1px solid rgba(255,255,255,.05);align-items:flex-start">
        <div style="font-size:20px;margin-top:2px"><?=$es_hoy?'🔴':'🟡'?></div>
        <div style="flex:1">
          <div style="font-weight:700;font-size:13px">
            <a href="<?=APP_URL?>/cliente_detalle.php?id=<?=$p['id']?>" style="text-decoration:none;color:var(--white)"><?=esc($p['empresa'])?></a>
          </div>
          <div style="font-size:12px;color:var(--muted)"><?=esc($p['proxima_accion'])?></div>
          <div style="font-size:11px;color:<?=$es_hoy?'var(--amber)':'var(--muted)'?>;margin-top:3px">
            <?=$es_hoy?'⚡ HOY':'📅 '.date('d/m/Y',strtotime($p['fecha_proxima']))?>
          </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:4px">
          <?php if($tel_c): ?><a href="https://wa.me/<?=$tel_c?>" target="_blank" class="btn btn-sm btn-icon" style="background:rgba(37,211,102,.1);border:1px solid rgba(37,211,102,.25);font-size:14px">💬</a><?php endif; ?>
          <?php if($p['email']): ?><a href="mailto:<?=esc($p['email'])?>" class="btn btn-secondary btn-sm btn-icon" style="font-size:14px">✉️</a><?php endif; ?>
        </div>
      </div>
      <?php endforeach; else: ?>
        <div class="empty-state" style="padding:24px 0">
          <div class="empty-icon">✅</div>
          <div class="empty-title">Todo al día</div>
          <div class="empty-desc">Sin acciones pendientes en los próximos 7 días</div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ACTIVIDAD RECIENTE -->
  <div class="card">
    <div class="card-header"><span class="card-title">🕐 Actividad reciente</span></div>
    <div class="card-body">
      <?php if($actividad): foreach($actividad as $a):
        $ico = $tipo_icons[$a['tipo']] ?? '📌';
      ?>
      <div style="display:flex;gap:10px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.04)">
        <span style="font-size:18px;margin-top:1px"><?=$ico?></span>
        <div>
          <a href="<?=APP_URL?>/cliente_detalle.php?id=<?=$a['cid']?>" style="text-decoration:none;font-weight:700;font-size:13px;color:var(--white)"><?=esc($a['empresa'])?></a>
          <div style="font-size:12px;color:var(--muted)"><?=esc($a['titulo'])?></div>
          <div style="font-size:11px;color:var(--gray)"><?=esc($a['nombre'])?> · <?=date('d/m H:i',strtotime($a['creado_en']))?></div>
        </div>
      </div>
      <?php endforeach; else: ?>
        <div class="empty-state" style="padding:24px 0"><div class="empty-icon">💤</div><div class="empty-title">Sin actividad</div></div>
      <?php endif; ?>
    </div>
  </div>

</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
