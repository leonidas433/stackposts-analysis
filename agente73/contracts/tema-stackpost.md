=== CONTRATO: TEMAS PARA STACKPOST (analytee.com) ===

Cuando te pidan un tema para StackPost, NO generes una web independiente:
genera un TEMA HIJO del tema base "guest/nova" con exactamente esta
estructura de archivos (y nada fuera de ella):

mitema/                          ← nombre: minúsculas, [a-z0-9_-], 2-50 chars
├── composer.json
├── theme.json
├── preview.png                  ← ~800×500 px (el selector recorta a ~220px alto)
├── assets/                      ← SOLO si cambias CSS/JS respecto al base
│   ├── css/app.css
│   └── js/app.js
├── resources/views/             ← SOLO las vistas que cambien (¡no copies el resto!)
│   ├── pages/home.blade.php     ← ejemplo: landing propia
│   └── partials/...             ← header/footer solo si difieren del base
└── lang/en.json                 ← solo si añades o cambias textos traducibles

composer.json (plantilla exacta):
{
    "name": "guest/mitema",
    "description": "…",
    "type": "laravel-theme",
    "version": "1.0.0",
    "extra": { "theme": { "parent": "guest/nova" } }
}

theme.json (campos obligatorios: name, version X.Y.Z, description ≤500):
{
  "name": "Mi Tema",
  "description": "…",
  "version": "1.0.0",
  "author": "…",
  "preview": "preview.png",
  "tags": ["…"],
  "parent": "guest/nova",
  "sort": 1
}

assets/css/app.css (plantilla exacta; los overrides van al final SIN @layer):
@import "../../../nova/assets/css/base.css";
@plugin "daisyui" { themes: light --default; }
@plugin "daisyui/theme" {
  name: "light";
  default: true;
  --color-primary: #4f46e5;   /* ← color de marca del tema */
}

assets/js/app.js (plantilla exacta):
import '../css/app.css';
import '../../../nova/assets/js/parts/search-overlay';
import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();

REGLAS DE LAS VISTAS BLADE que sobrescribas:
- Textos visibles siempre como {{ __('Texto') }}.
- Enlaces internos con url('ruta') o route('nombre'); NUNCA URLs absolutas.
- Imágenes propias en public/images/ del tema y referenciadas con
  {{ theme_public_asset('images/archivo.webp') }}.
- CSS: SOLO clases Tailwind/daisyUI (las compila el build del tema).
  Prohibido: <style> inline extensos, CDNs de CSS/JS externos, otro framework
  (Bootstrap, Bulma…), y <script> de terceros.
- Alpine.js está disponible para interactividad (x-data, x-show…).
- Accesibilidad: el layout heredado ya aporta skip-link y <main id="main">;
  NO añadas otro <main>; botones reales (<button>) para acciones; SVGs
  decorativos con aria-hidden="true".
- NO generes: layouts/app.blade.php propio (se hereda), vistas que no
  cambien, node_modules, ni archivos compilados (public/css, public/js).

ENTREGA: la carpeta del tema tal cual (o su ZIP con la carpeta como única
raíz). La compilación (npm run build --theme=guest/mitema) y la validación
(php scripts/validate-theme.php guest/mitema) las ejecuta el repositorio
de StackPost, no tú.
=== FIN DEL CONTRATO ===
