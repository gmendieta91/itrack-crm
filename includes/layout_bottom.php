  </main><!-- /page-content -->
</div><!-- /app-wrapper -->

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toast-container"></div>

<script>
// ── Toast global ──
function toast(msg, type = 'ok', ms = 3500) {
  const c = document.getElementById('toast-container');
  const t = document.createElement('div');
  t.className = 'toast ' + type;
  t.innerHTML = (type === 'ok' ? '✅ ' : '⚠️ ') + msg;
  c.appendChild(t);
  requestAnimationFrame(() => { t.classList.add('show'); });
  setTimeout(() => {
    t.classList.remove('show');
    setTimeout(() => t.remove(), 400);
  }, ms);
}

// ── Modal helpers ──
function openModal(id)  { document.getElementById(id).classList.add('show'); document.body.style.overflow = 'hidden'; }
function closeModal(id) { document.getElementById(id).classList.remove('show'); document.body.style.overflow = ''; }
document.querySelectorAll('.modal-backdrop').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) closeModal(m.id); });
});

// ── Responsive sidebar ──
const sidebar = document.getElementById('sidebar');
const tog = document.getElementById('sidebar-toggle');
function checkResp() {
  if (window.innerWidth <= 900) { tog.style.display = 'flex'; }
  else { tog.style.display = 'none'; sidebar.classList.remove('open'); }
}
window.addEventListener('resize', checkResp);
checkResp();
</script>
<?php if (isset($extra_scripts)) echo $extra_scripts; ?>
</body>
</html>
