<?php
require_once __DIR__ . '/includes/config.php';
requiereAdmin();
$u = usuarioActual();
$db = db();

$usuarios = $db->query("
    SELECT u.*,
           COUNT(DISTINCT p.id) AS total_propuestas,
           SUM(p.estado='ganada') AS prop_ganadas
    FROM usuarios u
    LEFT JOIN propuestas p ON p.usuario_id = u.id
    GROUP BY u.id
    ORDER BY u.creado_en DESC
")->fetchAll();

define('PAGE_TITLE', 'Usuarios');
$topbar_actions = '<button class="btn btn-primary" onclick="openModal(\'modal-nuevo-usuario\')">+ Nuevo usuario</button>';
include __DIR__ . '/includes/layout_top.php';

$av_colors = ['av-a','av-b','av-c','av-d','av-e','av-f'];
?>

<div class="card">
  <div class="table-wrap">
    <?php if ($usuarios): ?>
    <table>
      <thead>
        <tr>
          <th>Usuario</th>
          <th>Email</th>
          <th>Rol</th>
          <th>Propuestas</th>
          <th>Último acceso</th>
          <th>Estado</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($usuarios as $usr): $avc = $av_colors[$usr['id'] % 6]; ?>
        <tr style="<?= !$usr['activo'] ? 'opacity:.5' : '' ?>">
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div class="user-avatar <?= $avc ?>" style="width:36px;height:36px;font-size:14px">
                <?= strtoupper(substr($usr['nombre'], 0, 1)) ?>
              </div>
              <div>
                <div style="font-weight:700"><?= esc($usr['nombre'] . ' ' . $usr['apellido']) ?></div>
                <div style="font-size:12px;color:var(--muted)">@<?= esc($usr['usuario']) ?></div>
              </div>
            </div>
          </td>
          <td style="font-size:13px"><?= esc($usr['email']) ?></td>
          <td>
            <span class="badge <?= $usr['rol'] === 'admin' ? 'badge-amber' : 'badge-blue' ?>">
              <?= $usr['rol'] === 'admin' ? '⚙️ Admin' : '👤 Vendedor' ?>
            </span>
          </td>
          <td>
            <span class="badge badge-blue"><?= $usr['total_propuestas'] ?> total</span>
            <?php if ($usr['prop_ganadas'] > 0): ?>
              <span class="badge badge-green" style="margin-left:4px"><?= $usr['prop_ganadas'] ?> ganadas</span>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:var(--muted)">
            <?= $usr['ultimo_login'] ? date('d/m/Y H:i', strtotime($usr['ultimo_login'])) : 'Nunca' ?>
          </td>
          <td>
            <span class="badge <?= $usr['activo'] ? 'badge-green' : 'badge-red' ?>">
              <?= $usr['activo'] ? '● Activo' : '● Inactivo' ?>
            </span>
          </td>
          <td>
            <div style="display:flex;gap:6px">
              <button class="btn btn-secondary btn-sm btn-icon"
                      onclick="editarUsuario(<?= htmlspecialchars(json_encode($usr), ENT_QUOTES) ?>)"
                      title="Editar">✏️</button>
              <?php if ($usr['id'] !== $u['id']): ?>
                <button class="btn btn-secondary btn-sm btn-icon"
                        onclick="toggleActivo(<?= $usr['id'] ?>, <?= $usr['activo'] ? 0 : 1 ?>)"
                        title="<?= $usr['activo'] ? 'Desactivar' : 'Activar' ?>">
                  <?= $usr['activo'] ? '🔒' : '🔓' ?>
                </button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
      <div class="empty-state">
        <div class="empty-icon">👥</div>
        <div class="empty-title">Sin usuarios</div>
        <div class="empty-desc">Creá usuarios para tu equipo comercial</div>
        <button class="btn btn-primary" onclick="openModal('modal-nuevo-usuario')">+ Nuevo usuario</button>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- MODAL NUEVO / EDITAR USUARIO -->
<div class="modal-backdrop" id="modal-nuevo-usuario">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modal-usuario-titulo">Nuevo usuario</div>
      <button class="btn-close-modal" onclick="closeModal('modal-nuevo-usuario')">×</button>
    </div>
    <form id="form-usuario">
      <div class="modal-body">
        <input type="hidden" id="u-accion" value="crear">
        <input type="hidden" id="u-id"     value="">

        <div class="form-grid two" style="margin-bottom:14px">
          <div class="field">
            <label>Nombre <span class="req">*</span></label>
            <input type="text" id="u-nombre" placeholder="Nombre" required>
          </div>
          <div class="field">
            <label>Apellido <span class="req">*</span></label>
            <input type="text" id="u-apellido" placeholder="Apellido" required>
          </div>
        </div>

        <div class="field" style="margin-bottom:14px">
          <label>Email <span class="req">*</span></label>
          <input type="email" id="u-email" placeholder="mail@itrack.com.ar" required>
        </div>

        <div class="form-grid two" style="margin-bottom:14px">
          <div class="field">
            <label>Usuario <span class="req">*</span></label>
            <input type="text" id="u-usuario" placeholder="nombre.apellido" autocomplete="off">
            <div class="field-hint">Sin espacios ni caracteres especiales</div>
          </div>
          <div class="field">
            <label>Rol</label>
            <select id="u-rol">
              <option value="vendedor">👤 Vendedor</option>
              <option value="admin">⚙️ Administrador</option>
            </select>
          </div>
        </div>

        <div class="field">
          <label id="u-pass-label">Contraseña <span class="req">*</span></label>
          <input type="password" id="u-password" placeholder="Mínimo 6 caracteres" autocomplete="new-password">
          <div class="field-hint" id="u-pass-hint">Mínimo 6 caracteres</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-nuevo-usuario')">Cancelar</button>
        <button type="button" class="btn btn-primary" onclick="guardarUsuario()">Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
function editarUsuario(u) {
  document.getElementById('modal-usuario-titulo').textContent = 'Editar usuario';
  document.getElementById('u-accion').value   = 'editar';
  document.getElementById('u-id').value       = u.id;
  document.getElementById('u-nombre').value   = u.nombre || '';
  document.getElementById('u-apellido').value = u.apellido || '';
  document.getElementById('u-email').value    = u.email || '';
  document.getElementById('u-usuario').value  = u.usuario || '';
  document.getElementById('u-rol').value      = u.rol || 'vendedor';
  document.getElementById('u-password').value = '';
  document.getElementById('u-pass-label').innerHTML = 'Nueva contraseña <span style="color:var(--muted);font-weight:400">(dejar vacío para no cambiar)</span>';
  document.getElementById('u-pass-hint').textContent = 'Dejá vacío para mantener la contraseña actual';
  openModal('modal-nuevo-usuario');
}

function guardarUsuario() {
  const accion   = document.getElementById('u-accion').value;
  const id       = document.getElementById('u-id').value;
  const nombre   = document.getElementById('u-nombre').value.trim();
  const apellido = document.getElementById('u-apellido').value.trim();
  const email    = document.getElementById('u-email').value.trim();
  const usuario  = document.getElementById('u-usuario').value.trim();
  const rol      = document.getElementById('u-rol').value;
  const password = document.getElementById('u-password').value;

  if (!nombre || !apellido || !email) { toast('Completá nombre, apellido y email', 'err'); return; }
  if (accion === 'crear' && (!usuario || !password)) { toast('Usuario y contraseña son requeridos', 'err'); return; }

  const payload = { accion, id, nombre, apellido, email, usuario, rol };
  if (password) payload.password = password;

  fetch('<?= APP_URL ?>/api/usuarios.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  .then(r => r.json())
  .then(r => {
    if (r.ok) {
      toast(accion === 'crear' ? 'Usuario creado ✅' : 'Usuario actualizado ✅');
      closeModal('modal-nuevo-usuario');
      setTimeout(() => location.reload(), 800);
    } else {
      toast(r.error || 'Error al guardar', 'err');
    }
  });
}

function toggleActivo(id, activo) {
  const msg = activo ? '¿Activar este usuario?' : '¿Desactivar este usuario? No podrá ingresar al sistema.';
  if (!confirm(msg)) return;
  fetch('<?= APP_URL ?>/api/usuarios.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ accion: 'editar', id, activo })
  })
  .then(r => r.json())
  .then(r => {
    if (r.ok) { toast('Usuario actualizado ✅'); setTimeout(() => location.reload(), 700); }
    else toast(r.error || 'Error', 'err');
  });
}

// Auto-generar usuario desde nombre
document.getElementById('u-nombre').addEventListener('input', function() {
  if (document.getElementById('u-accion').value !== 'crear') return;
  const nom = this.value.trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z]/g,'');
  const ap  = (document.getElementById('u-apellido').value.trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z]/g,''));
  if (nom) document.getElementById('u-usuario').value = nom + (ap ? '.' + ap : '');
});
document.getElementById('u-apellido').addEventListener('input', function() {
  if (document.getElementById('u-accion').value !== 'crear') return;
  const nom = (document.getElementById('u-nombre').value.trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z]/g,''));
  const ap  = this.value.trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z]/g,'');
  if (nom) document.getElementById('u-usuario').value = nom + (ap ? '.' + ap : '');
});
</script>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
