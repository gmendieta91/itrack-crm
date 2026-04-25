<?php
require_once __DIR__ . '/includes/config.php';
requiereLogin();
$u  = usuarioActual();
$db = db();

// API Keys para el webhook
$api_keys = $db->query("SELECT ak.*, u.nombre, u.apellido FROM api_keys ak JOIN usuarios u ON ak.creado_por=u.id ORDER BY ak.creado_en DESC")->fetchAll();

define('PAGE_TITLE', 'Importar Prospectos');
$topbar_actions = '<a href="'.APP_URL.'/clientes.php" class="btn btn-secondary btn-sm">← Volver a Prospectos</a>';
include __DIR__ . '/includes/layout_top.php';
?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">

<!-- ─── IMPORTAR CSV ─────────────────────────────────────── -->
<div class="card">
  <div class="card-header"><span class="card-title">📂 Importar desde CSV / Excel</span></div>
  <div class="card-body">
    <p style="font-size:13px;color:var(--muted);margin-bottom:16px">
      Importá prospectos desde un archivo CSV. Cada fila se convierte en un prospecto.
    </p>

    <!-- Descarga de plantilla -->
    <div style="background:rgba(62,180,137,.06);border:1px solid rgba(62,180,137,.15);border-radius:8px;padding:14px 16px;margin-bottom:18px">
      <div style="font-size:13px;font-weight:700;margin-bottom:6px">📋 Columnas esperadas (en orden):</div>
      <div style="font-size:12px;color:var(--muted);font-family:monospace;line-height:1.8">
        empresa · nombre · apellido · telefono · email · localidad · provincia · tipo_servicio · precio · origen · notas
      </div>
      <button onclick="descargarPlantilla()" class="btn btn-secondary btn-sm" style="margin-top:10px">⬇️ Descargar plantilla CSV</button>
    </div>

    <div style="border:2px dashed rgba(62,180,137,.3);border-radius:10px;padding:28px;text-align:center;cursor:pointer;position:relative;margin-bottom:16px" id="drop-zone" onclick="document.getElementById('csv-input').click()">
      <input type="file" id="csv-input" accept=".csv,.txt" style="display:none" onchange="previewCSV(this)">
      <div id="drop-placeholder">
        <div style="font-size:36px;margin-bottom:10px">📂</div>
        <div style="font-weight:700;margin-bottom:6px">Seleccioná o arrastrá el archivo CSV</div>
        <div style="font-size:13px;color:var(--muted)">CSV con separador coma o punto y coma · Máx 500 filas</div>
      </div>
      <div id="drop-preview" style="display:none">
        <div style="font-size:24px;margin-bottom:8px">📄</div>
        <div id="drop-filename" style="font-weight:700"></div>
        <div id="drop-count" style="font-size:13px;color:var(--green);margin-top:4px"></div>
      </div>
    </div>

    <!-- Preview de filas -->
    <div id="csv-preview-wrap" style="display:none;margin-bottom:16px">
      <div style="font-size:12px;font-weight:700;color:var(--muted);margin-bottom:8px">VISTA PREVIA (primeras 5 filas):</div>
      <div id="csv-preview-table" style="overflow-x:auto;font-size:12px;background:rgba(0,0,0,.2);border-radius:8px;padding:10px"></div>
    </div>

    <div id="import-result" style="display:none;margin-bottom:16px"></div>

    <button class="btn btn-primary" id="btn-importar" onclick="importarCSV()" disabled style="width:100%;justify-content:center">
      🚀 Importar prospectos
    </button>
  </div>
</div>

<!-- ─── WEBHOOK PARA FORMULARIO WEB ─────────────────────── -->
<div style="display:flex;flex-direction:column;gap:16px">
  <div class="card">
    <div class="card-header">
      <span class="card-title">🔗 Webhook para formulario web</span>
      <?php if($u['rol']==='admin'): ?>
        <button class="btn btn-primary btn-sm" onclick="crearApiKey()">+ Nueva API Key</button>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <p style="font-size:13px;color:var(--muted);margin-bottom:16px">
        Cuando alguien completa el formulario de tu web, un POST a esta URL crea automáticamente el prospecto en el CRM.
      </p>

      <!-- Endpoint URL -->
      <div style="background:rgba(0,0,0,.25);border-radius:8px;padding:12px 14px;margin-bottom:14px">
        <div style="font-size:10px;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:var(--muted);margin-bottom:6px">URL del endpoint</div>
        <div style="display:flex;gap:8px;align-items:center">
          <code id="webhook-url" style="font-size:12px;color:var(--green);flex:1;word-break:break-all">
            <?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . APP_URL ?>/api/webhook.php
          </code>
          <button class="btn btn-secondary btn-sm" onclick="copiar('webhook-url')">📋</button>
        </div>
      </div>

      <!-- API Keys -->
      <?php if($api_keys): ?>
      <div style="margin-bottom:14px">
        <div style="font-size:11px;font-weight:700;color:var(--muted);letter-spacing:.1em;text-transform:uppercase;margin-bottom:8px">API Keys activas</div>
        <?php foreach($api_keys as $ak): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);border-radius:8px;margin-bottom:6px">
          <div style="flex:1">
            <div style="font-size:13px;font-weight:700"><?=esc($ak['nombre'])?></div>
            <code id="key-<?=$ak['id']?>" style="font-size:11px;color:var(--green)"><?=esc($ak['api_key'])?></code>
          </div>
          <button class="btn btn-secondary btn-sm" onclick="copiar('key-<?=$ak['id']?>')">📋</button>
          <?php if(!$ak['activo']): ?><span class="badge badge-red">Inactiva</span><?php endif; ?>
          <?php if($u['rol']==='admin'): ?>
          <button class="btn btn-danger btn-sm btn-icon" onclick="revocarKey(<?=$ak['id']?>)">🗑️</button>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
        <div class="empty-state" style="padding:20px 0">
          <div class="empty-icon" style="font-size:32px">🔑</div>
          <div class="empty-title" style="font-size:15px">Sin API Keys</div>
          <?php if($u['rol']==='admin'): ?><button class="btn btn-primary btn-sm" onclick="crearApiKey()">Crear primera key</button><?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- Ejemplo de integración -->
      <details style="margin-top:14px">
        <summary style="font-size:13px;font-weight:700;cursor:pointer;color:var(--green)">Ver ejemplo de integración</summary>
        <div style="margin-top:12px;background:rgba(0,0,0,.3);border-radius:8px;padding:14px;font-size:12px;font-family:monospace;color:var(--muted);line-height:1.8;overflow-x:auto">
          <div style="color:var(--green);margin-bottom:6px">// Ejemplo con fetch (JavaScript)</div>
fetch('<?= (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].APP_URL ?>/api/webhook.php', {<br>
&nbsp;&nbsp;method: 'POST',<br>
&nbsp;&nbsp;headers: {'Content-Type':'application/json'},<br>
&nbsp;&nbsp;body: JSON.stringify({<br>
&nbsp;&nbsp;&nbsp;&nbsp;api_key: 'TU_API_KEY_AQUI',<br>
&nbsp;&nbsp;&nbsp;&nbsp;empresa: 'Transportes García SA',<br>
&nbsp;&nbsp;&nbsp;&nbsp;nombre: 'Carlos',<br>
&nbsp;&nbsp;&nbsp;&nbsp;apellido: 'García',<br>
&nbsp;&nbsp;&nbsp;&nbsp;telefono: '1155667788',<br>
&nbsp;&nbsp;&nbsp;&nbsp;email: 'carlos@empresa.com',<br>
&nbsp;&nbsp;&nbsp;&nbsp;localidad: 'Buenos Aires',<br>
&nbsp;&nbsp;&nbsp;&nbsp;tipo_servicio: 'Mensual por vehículo',<br>
&nbsp;&nbsp;&nbsp;&nbsp;cant_vehiculos: 5,<br>
&nbsp;&nbsp;&nbsp;&nbsp;origen: 'web',<br>
&nbsp;&nbsp;&nbsp;&nbsp;notas: 'Completó formulario de contacto'<br>
&nbsp;&nbsp;})<br>
});
        </div>
      </details>
    </div>
  </div>

  <!-- HISTORIAL DE IMPORTACIONES -->
  <div class="card">
    <div class="card-header"><span class="card-title">📊 Historial de importaciones</span></div>
    <div class="table-wrap">
      <?php
      $hist = $db->prepare("SELECT i.*, u.nombre, u.apellido FROM importaciones i JOIN usuarios u ON i.usuario_id=u.id ORDER BY i.creado_en DESC LIMIT 10");
      $hist->execute();
      $hist = $hist->fetchAll();
      ?>
      <?php if($hist): ?>
      <table>
        <thead><tr><th>Fecha</th><th>Usuario</th><th>Total</th><th>OK</th><th>Errores</th></tr></thead>
        <tbody>
          <?php foreach($hist as $h): ?>
          <tr>
            <td style="font-size:12px"><?=date('d/m/Y H:i',strtotime($h['creado_en']))?></td>
            <td style="font-size:13px"><?=esc($h['nombre'].' '.$h['apellido'])?></td>
            <td><?=$h['total']?></td>
            <td style="color:var(--green);font-weight:700"><?=$h['importados']?></td>
            <td style="color:<?=$h['errores']>0?'var(--red)':'var(--muted)'>;"><?=$h['errores']?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
        <div class="empty-state" style="padding:20px"><div class="empty-icon" style="font-size:28px">📊</div><div class="empty-desc">Sin importaciones aún</div></div>
      <?php endif; ?>
    </div>
  </div>
</div>
</div>

<script>
let csvRows = [];

function descargarPlantilla() {
  const header = 'empresa,nombre,apellido,telefono,email,localidad,provincia,tipo_servicio,precio,origen,notas';
  const ejemplo = '"Transportes García SA","Carlos","García","1155667788","carlos@empresa.com","Buenos Aires","Buenos Aires","Mensual por vehículo","15000","referido","Interesado en 5 vehículos"';
  const blob = new Blob([header+'\n'+ejemplo], {type:'text/csv;charset=utf-8'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'plantilla_itrack_crm.csv';
  a.click();
}

function parseCSV(text) {
  // Detectar separador
  const sep = text.split('\n')[0].includes(';') ? ';' : ',';
  const lines = text.trim().split('\n').filter(l=>l.trim());
  const headers = lines[0].split(sep).map(h=>h.replace(/^"|"$/g,'').trim().toLowerCase());
  const rows = [];
  for(let i=1;i<lines.length;i++) {
    const vals = lines[i].match(/(".*?"|[^,;]+)(?=[,;\n]|$)/g)||lines[i].split(sep);
    const row = {};
    headers.forEach((h,j)=>{ row[h]=(vals[j]||'').replace(/^"|"$/g,'').trim(); });
    if(row.empresa||row.nombre) rows.push(row);
  }
  return rows;
}

function previewCSV(input) {
  if(!input.files[0]) return;
  const reader = new FileReader();
  reader.onload = e => {
    const text = e.target.result;
    csvRows = parseCSV(text);
    document.getElementById('drop-placeholder').style.display='none';
    document.getElementById('drop-preview').style.display='block';
    document.getElementById('drop-filename').textContent = input.files[0].name;
    document.getElementById('drop-count').textContent = csvRows.length + ' filas encontradas';
    document.getElementById('btn-importar').disabled = csvRows.length === 0;

    // Preview tabla
    const preview = csvRows.slice(0,5);
    if(preview.length) {
      const cols = Object.keys(preview[0]);
      let html = '<table style="width:100%;border-collapse:collapse"><thead><tr>';
      cols.forEach(c=>{html+=`<th style="padding:4px 8px;border-bottom:1px solid rgba(255,255,255,.1);text-align:left;font-size:10px;color:var(--muted);text-transform:uppercase">${c}</th>`;});
      html+='</tr></thead><tbody>';
      preview.forEach(row=>{
        html+='<tr>';
        cols.forEach(c=>{html+=`<td style="padding:4px 8px;border-bottom:1px solid rgba(255,255,255,.05);white-space:nowrap;overflow:hidden;max-width:120px;text-overflow:ellipsis">${row[c]||''}</td>`;});
        html+='</tr>';
      });
      html+='</tbody></table>';
      document.getElementById('csv-preview-table').innerHTML=html;
      document.getElementById('csv-preview-wrap').style.display='block';
    }
  };
  reader.readAsText(input.files[0], 'UTF-8');
}

async function importarCSV() {
  if(!csvRows.length) return;
  const btn = document.getElementById('btn-importar');
  btn.disabled=true; btn.textContent='⏳ Importando...';
  const res = document.getElementById('import-result');
  res.style.display='block';
  res.innerHTML='<div class="alert alert-info">⏳ Procesando '+csvRows.length+' filas...</div>';

  try {
    const r = await fetch('<?=APP_URL?>/api/importar.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({filas: csvRows})
    });
    const d = await r.json();
    if(d.ok) {
      res.innerHTML=`<div class="alert alert-success">✅ Importación completada: <strong>${d.importados}</strong> prospectos creados${d.errores>0?', <span style="color:var(--red)">'+d.errores+' errores</span>':''}</div>`;
      toast('✅ '+d.importados+' prospectos importados');
      setTimeout(()=>location.reload(),2000);
    } else {
      res.innerHTML='<div class="alert alert-error">⚠️ '+(d.error||'Error en la importación')+'</div>';
    }
  } catch(e) {
    res.innerHTML='<div class="alert alert-error">⚠️ Error de conexión</div>';
  }
  btn.disabled=false; btn.textContent='🚀 Importar prospectos';
}

function copiar(id) {
  const el = document.getElementById(id);
  const text = el.textContent.trim();
  navigator.clipboard && navigator.clipboard.writeText(text).then(()=>toast('Copiado ✅'));
}

function crearApiKey() {
  const nombre = prompt('Nombre para identificar esta key (ej: "Formulario web principal"):');
  if(!nombre) return;
  fetch('<?=APP_URL?>/api/apikeys.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({accion:'crear', nombre})
  }).then(r=>r.json()).then(r=>{
    if(r.ok) { toast('API Key creada ✅'); setTimeout(()=>location.reload(),700); }
    else toast(r.error||'Error','err');
  });
}

function revocarKey(id) {
  if(!confirm('¿Eliminar esta API Key? Los formularios que la usen dejarán de funcionar.')) return;
  fetch('<?=APP_URL?>/api/apikeys.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({accion:'eliminar', id})
  }).then(r=>r.json()).then(r=>{
    if(r.ok) { toast('API Key eliminada'); setTimeout(()=>location.reload(),700); }
    else toast(r.error||'Error','err');
  });
}

// Drag & drop
const dz = document.getElementById('drop-zone');
dz.addEventListener('dragover', e=>{e.preventDefault();dz.style.borderColor='var(--green)';});
dz.addEventListener('dragleave', ()=>{dz.style.borderColor='rgba(62,180,137,.3)';});
dz.addEventListener('drop', e=>{
  e.preventDefault(); dz.style.borderColor='rgba(62,180,137,.3)';
  const f = e.dataTransfer.files[0];
  if(f) { document.getElementById('csv-input').files=e.dataTransfer.files; previewCSV({files:[f]}); }
});
</script>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
