<script>
(function() {
  var datos = %%DATOS%%;
  document.addEventListener('DOMContentLoaded', function() {
    var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, null, false);
    var nodes = [], n;
    while (n = walker.nextNode()) nodes.push(n);
    nodes.forEach(function(node) {
      var t = node.textContent;
      t = t.replace(/\{\{NOMBRE_CLIENTE\}\}/g,  datos.nombre_cliente  || '');
      t = t.replace(/\{\{NOMBRE_CONTACTO\}\}/g, datos.nombre_contacto || '');
      t = t.replace(/\{\{PRECIO\}\}/g,           datos.precio          || '');
      t = t.replace(/\{\{TIPO_SERVICIO\}\}/g,    datos.tipo_servicio   || '');
      if (t !== node.textContent) node.textContent = t;
    });
    document.title = 'Propuesta iTrack \u00b7 ' + (datos.nombre_cliente || '');
    document.querySelectorAll('a[href*="wa.me"]').forEach(function(a) {
      a.href = 'https://wa.me/541136690271?text=' + encodeURIComponent('Hola Karen, vi la propuesta de iTrack para ' + datos.nombre_cliente + ' y quiero m\u00e1s informaci\u00f3n.');
    });
    if (datos.logo_cliente) {
      var heroBg = document.querySelector('.hero-bg');
      if (heroBg) {
        var lb = document.createElement('div');
        lb.style.cssText = 'position:absolute;inset:0;background-image:url(' + datos.logo_cliente + ');background-size:55%;background-position:center;background-repeat:no-repeat;filter:blur(32px) brightness(0.18) saturate(0.5);transform:scale(1.15);opacity:0.85;z-index:0';
        heroBg.parentNode.insertBefore(lb, heroBg.nextSibling);
      }
      var heroBox = document.querySelector('.hero-client-logo');
      if (heroBox) {
        var wrap = document.createElement('div');
        wrap.style.cssText = 'display:inline-flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);border-radius:12px;padding:14px 28px;margin-bottom:28px;backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);box-shadow:0 4px 24px rgba(0,0,0,0.25)';
        var img = document.createElement('img');
        img.src = datos.logo_cliente;
        img.alt = datos.nombre_cliente;
        img.style.cssText = 'height:60px;max-width:220px;object-fit:contain;filter:brightness(0) invert(1);opacity:0.92';
        wrap.appendChild(img);
        heroBox.insertBefore(wrap, heroBox.firstChild);
      }
    }
  });
})();
</script>