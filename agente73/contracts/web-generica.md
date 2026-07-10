=== CONTRATO: WEB GENÉRICA (vertical EXPERIMENTAL) ===

Genera una web estática de una página (o pocas páginas) autocontenida:

estructura/
├── index.html          ← HTML5 semántico: <header>, <main id="main">, <footer>
├── css/styles.css      ← CSS propio, mobile-first, variables :root para la paleta
├── js/main.js          ← vanilla JS, sin frameworks ni CDNs externos
└── images/             ← placeholders con <svg> inline o data-URI (sin binarios)

Reglas:
- Accesibilidad: skip-link, un único <main>, contraste AA, alt en imágenes,
  botones reales, prefers-reduced-motion.
- SEO: <title> y meta description del negocio, jerarquía h1→h3 correcta,
  Open Graph básico.
- Rendimiento: cero peticiones externas (fuentes del sistema), CSS/JS mínimos.
- Textos en español salvo que el encargo pida otro idioma.
- PROHIBIDO: CDNs, trackers, frameworks CSS/JS, formularios que envíen datos
  (usa mailto: o placeholder), y cualquier archivo fuera de `estructura/`.

Entrega: los archivos de `estructura/` + un resumen de decisiones de diseño.
La publicación NUNCA es parte del job: la revisa y sube un humano tras el QA.

=== FIN DEL CONTRATO ===
