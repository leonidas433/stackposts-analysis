# Guía: crear y mantener temas en StackPost (analytee.com)

**Audiencia:** desarrolladores que van a crear un tema nuevo o mantener los existentes.
**Complementa a:** `docs/informe-estructura-temas-stackpost.md` (análisis y decisiones de arquitectura).

---

## 1. Conceptos en 60 segundos

- Los temas viven en `resources/themes/<tipo>/<nombre>`: `guest/` (sitio público) y `app/` (panel).
- El middleware `app/Http/Middleware/Themes.php` activa el tema por request: el panel usa la opción `backend_theme` (default `app/pico`) y el sitio público la opción `frontend_theme` (seleccionable en **Admin → Themes → Frontend**).
- Las **vistas de contenido** las aportan los módulos (`modules/<Modulo>/resources/views`); el tema aporta el **marco**: layout, partials, páginas guest, CSS/JS. El view-finder busca primero en el tema activo (y su cadena de padres) y después en el módulo.
- **`guest/nova` es el tema base.** Los temas nuevos del sitio público deben crearse como **hijos de nova**, no como copias.

## 2. Anatomía del tema base (`guest/nova`)

```
resources/themes/guest/nova/
├── composer.json          # Identidad para hexadog: name, type laravel-theme, extra.theme
├── theme.json             # Metadatos para el selector del admin (ver §6)
├── preview.png            # Miniatura del selector
├── assets/                # FUENTES (entrada de Vite)
│   ├── css/
│   │   ├── base.css       # Tailwind + tokens + fuentes + TODAS las utilidades Nova.
│   │   │                  #   Sin política daisyUI: pensado para ser importado por hijos.
│   │   └── app.css        # Entrada del tema: importa base.css y fija SU política
│   │                      #   daisyUI (light default + dark por prefers-color-scheme).
│   └── js/
│       ├── parts/search-overlay.js  # Comportamiento reutilizable por hijos
│       └── app.js         # Entrada: css + parts + Alpine (self-host, npm)
├── public/                # COMPILADOS + estáticos (css/, js/, fonts/, images/,
│   │                      #   fontawesome, flags, .vite/manifest.json)
│   └── ...                # Servidos por theme_public_asset() / theme_vite()
├── resources/views/
│   ├── layouts/app.blade.php   # Layout maestro (skip-link, <main>, cookie bar accesible)
│   ├── partials/               # header (landmark <header>), footer, pricing, faqs…
│   ├── pages/                  # home, pricing, blogs, contact, legales, 404…
│   ├── auth/                   # login, signup, activación, recuperación
│   └── sections/               # hero y otras secciones componibles
└── lang/en.json           # Traducciones del tema
```

Recursos compartidos entre TODOS los temas: `resources/themes/_shared/`
(`tokens.css`, `fonts.css`, `fonts/*.woff2`). Ver su README.

## 3. Herencia de temas (padre/hijo)

La herencia la implementa `hexadog/laravel-themes-manager` y se declara en el
`composer.json` **del hijo**:

```json
{
    "name": "guest/mitema",
    "type": "laravel-theme",
    "extra": {
        "theme": {
            "parent": "guest/nova"
        }
    }
}
```

Qué se hereda automáticamente:

| Recurso | Mecanismo | Nota |
|---|---|---|
| **Vistas** | Cascada del view-finder: primero el hijo, luego el padre | Cualquier blade que el hijo no tenga se sirve del padre |
| **Assets estáticos** (imágenes, fontawesome, flags, jquery, main.js) | `theme_public_asset()` recorre la cadena de padres con `file_exists` | El hijo solo guarda en `public/` lo que cambia |
| **Build de Vite** (css/js compilados) | `theme_vite()` cae al `manifest.json` del padre si el hijo no tiene build propio | Ver las dos variantes de hijo en §4 |
| **Traducciones (`lang/`)** | ⚠️ NO verificado en runtime todavía | Los hijos conservan su `lang/` completo hasta validarlo en staging |

**Importante:** `theme.json` NO participa en la herencia (hexadog lo ignora);
su campo `parent` es solo informativo para humanos y para el selector.

## 4. Crear un tema hijo en 5 pasos

Ejemplo real en el repo: `guest/analytee` (hijo con build propio) y
`guest/analytee_pro` (hijo "delgado" sin build).

1. **Esqueleto**
   ```
   resources/themes/guest/mitema/
   ├── composer.json   (con extra.theme.parent = "guest/nova", ver §3)
   ├── theme.json      (ver esquema en §6)
   └── preview.png     (≈800×500; la tarjeta del selector lo recorta a ~220px
                        de alto con object-fit:cover. Hasta tener captura real,
                        copia el preview.png del padre)
   ```
2. **Decide la variante de assets:**
   - **Sin build propio** (como `analytee_pro`): no crees `assets/`. `theme_vite()`
     servirá el build del padre y `theme_public_asset()` sus estáticos. Ideal si
     solo cambias vistas o traducciones.
   - **Con build propio** (como `analytee`): crea `assets/css/app.css` y
     `assets/js/app.js` así:
     ```css
     /* assets/css/app.css */
     @import "../../../nova/assets/css/base.css";

     @plugin "daisyui" { themes: light --default; }   /* tu política de temas */
     @plugin "daisyui/theme" {
       name: "light";
       default: true;
       --color-primary: #4f46e5;   /* o tu color de marca */
     }

     /* Tus overrides SIN @layer para que ganen a base.css */
     ```
     ```js
     /* assets/js/app.js */
     import '../css/app.css';
     import '../../../nova/assets/js/parts/search-overlay';
     import Alpine from 'alpinejs';
     window.Alpine = Alpine;
     Alpine.start();
     ```
3. **Sobrescribe solo las vistas que cambien**, replicando la ruta del padre
   (p. ej. `resources/views/pages/home.blade.php`). Para sobrescribir vistas de
   un módulo usa `resources/views/vendor/<modulo>/<vista>.blade.php`.
4. **Compila** (solo variante con build): `npm run build --theme=guest/mitema`.
   El output va a `public/` del propio tema (css/js/fonts + `.vite/manifest.json`).
5. **Activa**: Admin → Themes → Frontend → *Use Theme* (o
   `update_option('frontend_theme', 'mitema')`).

## 5. Design tokens

`resources/themes/_shared/tokens.css` define las variables `--sp-*`
(color primario `#4f46e5` indigo-600, tipografías, radios, sombras, movimiento).
Es la **única fuente de verdad para un rebrand**.

- **Temas guest**: `base.css` ya las importa. Mapéalas a daisyUI en el bloque
  `@plugin "daisyui/theme"` de tu `app.css` (daisyUI necesita valores literales
  de color en build; mantenlos sincronizados con los tokens).
- **Panel (pico)**: `assets/css/app.css` compila utilidades Tailwind **con
  prefijo `tw:` y sin preflight** (Bootstrap sigue mandando). Uso:
  `class="tw:flex tw:gap-4 tw:bg-primary"`. Las utilidades v4 usan propiedades
  lógicas → compatibles con el RTL dinámico del panel.

Tras cambiar tokens, recompila cada tema con build propio:
`npm run build --theme=guest/nova && npm run build --theme=guest/analytee && npm run build --theme=app/pico`.

## 6. Esquema de `theme.json`

Validado por `Modules\AdminFrontendThemes\Services\ThemeManifestValidator`:
en el selector un manifest inválido muestra badge de aviso y bloquea la
activación; en el import ZIP es un rechazo.

| Campo | Oblig. | Regla | Ejemplo |
|---|---|---|---|
| `name` | ✅ | string 1..100 | `"Analytee"` |
| `version` | ✅ | `X.Y` o `X.Y.Z` | `"1.1.0"` |
| `description` | ✅ | string 1..500 | `"Tema oficial…"` |
| `author` | – | string ≤100 | `"Analytee"` |
| `preview` | – | nombre de archivo png/jpg/webp (sin rutas) | `"preview.png"` |
| `tags` | – | array de strings (≤20×30) | `["modern","saas"]` |
| `sort` | – | entero; mayor = primero en el selector | `10` |
| `parent` | – | informativo (la herencia real va en composer.json) | `"guest/nova"` |

## 7. Build y desarrollo

- **Build de producción:** `npm run build --theme=<tipo>/<nombre>`. Compila
  `assets/{css,js}/app.*` y emite a `<tema>/public/` con nombres estables
  (`css/app.css`, `js/app.js`, `fonts/*`). **Los artefactos se commitean.**
- **Dev-server (HMR):** `npm run dev --theme=<tipo>/<nombre>`. `theme_vite()`
  solo detecta el dev-server cuando `APP_ENV=local` (o `VITE_DEV_SERVER=true`);
  en producción no hay ping HTTP. URL configurable con `VITE_DEV_URL`
  (default `http://localhost:5173`).
- Un tema sin `assets/` no se compila (Vite sale limpio) y hereda el build del padre.
- **Nota esperada:** un hijo con build propio re-emite las fuentes de `_shared`
  (~133 KB de woff2) en su `public/fonts/` — es el comportamiento correcto
  (cada tema compilado es autocontenido), no un error de duplicación.

## 7b. Automatización: agente y validador CLI

- **Agente `stackpost-theme-builder`** (`.claude/agents/stackpost-theme-builder.md`):
  subagente de Claude Code especializado en este contrato. En cualquier sesión
  sobre este repo, pide p. ej. *"crea un tema hijo guest/blackfriday con la
  paleta X"* o *"revisa el tema analytee"* y Claude delegará en él. Crea temas
  hijos, **audita temas existentes** (validador + contrato + herencia +
  paridad + a11y, sin tocar nada) y sabe **portar HTML generado por otras
  herramientas/agentes** a un tema hijo (traduce paleta a tokens, textos a
  `__()`, assets a `theme_public_asset()`).
- **Validador CLI** (`php scripts/validate-theme.php <tipo>/<nombre>`):
  valida composer.json (identidad + herencia), theme.json (esquema §6) y la
  estructura (build presente, hijo sin copias masivas de vistas) sin necesidad
  de Laravel. Exit 0 = válido. Úsalo en CI y antes de cada PR de tema.

## 8. Import/export de temas (ZIP)

El import (Admin → Themes → Frontend → Import) exige:
- ZIP ≤ 50 MB, ≤ 5000 entradas, ≤ 300 MB descomprimido.
- Un único directorio raíz con nombre `^[a-z0-9][a-z0-9_-]{1,49}$` que no
  colisione con temas instalados ni con `_shared`.
- Sin rutas absolutas ni `..` en las entradas (se extrae a staging en
  `storage/app/tmp/` y solo se publica tras validar).
- `theme.json` válido según §6.

El borrado desde el selector rehúsa eliminar el tema activo y cualquier tema
que sea padre de otro instalado.

⚠️ **Un tema es código ejecutable** (Blade = PHP): importa solo ZIPs de
fuentes de confianza. La validación protege contra errores y ataques de
empaquetado, no convierte un tema malicioso en seguro.

## 9. Checklist de accesibilidad para temas nuevos

El tema base ya cumple; consérvalo al sobrescribir vistas:

- [ ] Skip-link como primer elemento del `<body>` (`.skip-link`, apunta a `#main`).
- [ ] Contenido dentro de `<main id="main">`; header con `<header>`/`role=banner`.
- [ ] `:focus-visible` visible (lo aporta `base.css` vía tokens).
- [ ] `prefers-reduced-motion` respetado (lo aporta `tokens.css`).
- [ ] Botones reales (`<button>`) para acciones JS — p. ej. la cookie bar usa
      `<button class="btn-accept|btn-decline">` (los handlers jQuery son delegados).
- [ ] SVGs decorativos con `aria-hidden="true" focusable="false"`.
- [ ] Política de dark mode explícita en tu `@plugin "daisyui"`:
      `light --default` (solo claro) o `light --default, dark --prefersdark`.
      Si activas dark, revisa las superficies con colores fijos (`bg-white`…).
- [ ] Contraste AA en el color primario sobre superficies claras/oscuras.

## 10. Opciones de plataforma

| Opción / env | Dónde | Efecto |
|---|---|---|
| `frontend_theme` (tabla `options`) | Admin → Themes → Frontend | Tema guest activo |
| `backend_theme` (tabla `options`) | BD (sin UI aún) | Tema del panel; default `app/pico` |
| `THEME_FRONTEND` (.env) | fallback | Tema guest si la opción no existe |
| `THEMES_DIR` (.env) | producción | Raíz de temas para hexadog (`resources/themes`) |
| `VITE_DEV_URL` / `VITE_DEV_SERVER` (.env) | desarrollo | Dev-server de Vite para `theme_vite()` |

## 11. Checklist de despliegue (staging antes de producción)

1. `composer install` y `php artisan theme:list` → los hijos deben listar su parent.
2. `php artisan theme:cache:clear` (y `view:clear`).
3. Smoke-test de las 10 páginas guest (`pages/`) + login/signup con el tema
   activo, y de un dashboard del panel con gráficas (verifica que Highcharts
   carga bajo demanda: pestaña Network, no debe aparecer en páginas sin gráficas).
4. Selector admin: tarjetas correctas, activar/desactivar, borrar un tema de
   prueba, importar un ZIP de prueba inválido (debe rechazarse con mensaje claro).
5. Si el docroot del vhost es `public/`, confirma que existen los symlinks
   `public/resources/themes/<tipo>/<tema>` (hexadog los crea al activar el tema).

## 12. Deuda técnica conocida (candidatos a follow-up)

- **General Sans sigue en CDN** (fonts.cdnfonts.com). Para self-hostearla:
  descargar los woff2 (400/500/600/700) desde https://www.fontshare.com/fonts/general-sans
  (ITF Free Font License), colocarlos en `resources/themes/_shared/fonts/` y
  añadir los `@font-face` en `_shared/fonts.css`; quitar el `<link>` del layout
  de nova y recompilar los temas guest.
- **`analytee/public/` y `analytee/lang/` completos**: adelgazables (como
  analytee_pro) cuando staging confirme la herencia de estáticos y de lang.
- **jquery + main.js en el guest**: la cookie bar y utilidades legacy dependen
  de jQuery; migrables a Alpine para eliminar jQuery del sitio público.
- **Highcharts por CDN**: self-host posible en `pico/public/plugins/highcharts/`
  (revisar licencia Highcharts antes).
- **Panel Bootstrap legacy**: migración progresiva a utilidades `tw:` +
  tokens; `main.css` (110k líneas) es el objetivo final a desmontar.
- **`public/resources/themes/app/pico`**: copia materializada commiteada del
  `public/` de pico (debería ser un symlink creado en runtime). Inofensiva,
  pero conviene retirarla del repo cuando se confirme el docroot de producción.
