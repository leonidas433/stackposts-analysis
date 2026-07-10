# Informe UI/UX — Estructura de temas de StackPost (analytee.com)

**Fecha:** 2026-07-10
**Alcance:** análisis de la arquitectura de temas (themes) del proyecto Laravel que sirve analytee.com, desde la perspectiva de diseño UI/UX y mantenibilidad del frontend.

---

## 1. Resumen ejecutivo

La plataforma es un SaaS de publicación en redes sociales (StackPost) construido sobre **Laravel 11+ modular** (104 módulos vía `nwidart/laravel-modules`) con un sistema de temas basado en **`hexadog/laravel-themes-manager`**. Los temas se dividen en dos dominios:

- **`app/`** — el panel de aplicación y administración (actualmente un único tema: **Pico**, basado en Bootstrap 5 + jQuery).
- **`guest/`** — el sitio público/landing (tres temas: **nova**, **analytee** y **analytee_pro**, basados en Tailwind CSS 4 + Alpine.js).

El sistema es funcional y flexible (selección de tema en runtime, importación por ZIP, generador de temas por Artisan), pero presenta **dos stacks de frontend divergentes**, **duplicación entre temas guest** y varios puntos débiles de consistencia visual, rendimiento y gobernanza del metadato `theme.json` que se detallan en las secciones 6 y 7.

---

## 2. Stack tecnológico del frontend

| Capa | Tecnología | Dónde se usa |
|---|---|---|
| Framework | Laravel (PHP 8+), Blade | Todo el proyecto |
| Modularidad | `nwidart/laravel-modules` (104 módulos en `modules/`) | Vistas de página por módulo |
| Motor de temas | `hexadog/laravel-themes-manager` ^1.13 | Resolución de vistas y assets por tema |
| CSS (guest) | Tailwind CSS 4.1 + DaisyUI 5 + Preline 3 + plugins forms/typography | Temas `guest/*` y `app/pico/assets` |
| JS (guest) | Alpine.js 3 (CDN en analytee), Vite 5, laravel-vite-plugin | Temas `guest/*` |
| CSS (app) | Bootstrap 5 (incl. RTL), SCSS precompilado (`main.css`, ~110.000 líneas) | Tema `app/pico` |
| JS (app) | jQuery + jQuery UI, Select2, DataTables, FullCalendar, TinyMCE, CodeMirror 5, SweetAlert2, iziToast, moment, lodash… | Tema `app/pico` (carpeta `public/plugins/`) |
| Gráficas | Highcharts (cargado desde CDN `code.highcharts.com`) | Layout de `app/pico` |
| Tiempo real | Laravel Echo + Pusher/Reverb | Panel de aplicación |

**Observación clave:** conviven dos generaciones de frontend — un backend "legacy" (Bootstrap + jQuery + plugins servidos como estáticos) y un frontend público moderno (Tailwind 4 + Vite + Alpine). Esto tiene consecuencias directas de UX y coste de mantenimiento (ver §6).

---

## 3. Arquitectura del sistema de temas

### 3.1 Estructura de directorios

```
resources/themes/
├── app/                      # Temas del panel (usuario + admin + payment)
│   └── pico/                 # Único tema del panel
└── guest/                    # Temas del sitio público
    ├── analytee/             # "Clon exacto de Nova" (según su theme.json)
    ├── analytee_pro/         # Variante premium de Nova
    └── nova/                 # Tema base "Nova Modern"

modules/<Modulo>/resources/views/   # Vistas de contenido por módulo (104 módulos)
```

`config/view.php` registra **dos raíces de vistas**: `resources/themes` y `modules/`. El themes-manager antepone la ruta del tema activo al *view finder*, de modo que la cascada de resolución de una vista es:

1. Vista del **tema activo** (`resources/themes/<tipo>/<tema>/resources/views/...`)
2. Vista del **módulo** (`modules/<Modulo>/resources/views/...`, namespace `modulo::vista`)

Esto permite que un tema **sobrescriba vistas de módulos** sin tocarlos (ejemplo real: `guest/analytee/resources/views/vendor/guest/home.blade.php` sobrescribe la home del módulo `Guest`).

### 3.2 Selección de tema en runtime

El middleware `app/Http/Middleware/Themes.php` decide el tema por segmento de URL:

```php
if ($request->segment(1) === 'app' || 'admin' || 'payment') {
    $theme = 'app/pico';                                        // hardcodeado
} else {
    $theme = 'guest/' . get_option('frontend_theme', env('THEME_FRONTEND'));
}
ThemesManager::set($theme);
app()->instance('theme', $theme);   // usado por theme_public_asset()
```

- El tema **guest es configurable** desde el admin (opción `frontend_theme` en BD, fallback a `THEME_FRONTEND` del `.env`).
- El tema **app está hardcodeado** a `app/pico`; el módulo `AdminBackendAppearance` solo gestiona ajustes de apariencia (p. ej. `backend_sidebar_type`), no el intercambio de tema.

### 3.3 Anatomía de un tema

Todos los temas comparten el mismo contrato de carpeta:

```
resources/themes/guest/nova/
├── theme.json          # Metadatos: name, description, version, author, preview, tags, [sort]
├── composer.json       # type: "laravel-theme" (requerido por hexadog)
├── preview.png         # Miniatura para el selector del admin
├── assets/             # FUENTES (entrada de Vite)
│   ├── css/app.css     #   @import "tailwindcss" + @plugin daisyui + utilidades propias
│   └── js/app.js       #   entrada JS
├── public/             # ARTEFACTOS compilados/estáticos (salida de Vite + vendor estático)
│   ├── css/  js/  img/  plugins/
├── resources/views/
│   ├── layouts/        # app.blade.php (y auth.blade.php en guest)
│   ├── partials/       # header, footer, sidebar, pricing, faqs…
│   ├── pages/          # home, pricing, blogs, contact, terms… (solo guest)
│   ├── auth/           # login, signup, forgot/recovery password… (solo guest)
│   ├── components/     # datatable*, pagination, sub_header… (solo app/pico)
│   ├── sections/       # hero… (nova)
│   └── vendor/<mod>/   # overrides de vistas de módulos (analytee)
└── lang/               # traducciones propias del tema (en.json)
```

**Peculiaridad:** existe además `resources/nova/` (fuera de `themes/`) con la misma estructura de tema — parece una copia huérfana/de trabajo que no es resoluble por el themes-manager.

### 3.4 Pipeline de assets

- **Compilación por tema:** `vite.config.js` raíz exige `--theme=<tipo>/<nombre>` (`npm run build --theme=guest/nova`). Compila `assets/{css,js}/app.*` del tema y **emite dentro del propio tema** (`resources/themes/<tema>/public/`), con manifest y nombres estables (`css/app.css`, `js/app.js`).
- **Helper `theme_vite($theme)`:** detecta el dev-server de Vite (HTTP ping con timeout 0.3 s a `VITE_DEV_URL`) y sirve HMR en desarrollo o el manifest compilado en producción. Lo usan `guest/nova` y `guest/analytee_pro`.
- **Helper `theme_public_asset($path)`:** genera `asset("resources/themes/{tema}/public/{$path}")` a partir del binding `app('theme')`. Lo usan `app/pico` (todos sus plugins) y `guest/analytee` (que enlaza directamente el CSS compilado, sin pasar por el manifest de Vite).

### 3.5 Gestión desde el admin y tooling

- **`AdminFrontendThemes`** (módulo funcional): lista los temas guest leyendo cada `theme.json` (ordenados por campo `sort` descendente), permite **activar** un tema (`update_option('frontend_theme', $id)`) e **importar temas por ZIP** (se extrae directamente a `resources/themes/guest/`).
- **`AdminThemes`** (módulo *scaffold*): controlador CRUD vacío — la gestión de temas del panel `app/` **no está implementada**.
- **Generador Artisan:** `php artisan theme:make:tailwind {tipo/nombre} [--with-preline] [--with-daisyui]` crea el esqueleto completo (assets, layout con `theme_vite`, theme.json, composer.json) usando stubs de `resources/stubs/themes/`.

---

## 4. Los temas existentes en detalle

### 4.1 `app/pico` — panel de aplicación y administración

- **Identidad según `theme.json`:** "Perfect for SaaS platforms… sleek and modern" — tags `modern, minimal, creative, pastel, dashboard`.
- **Layout** (`layouts/app.blade.php`): header + sidebar colapsable (opción `backend_sidebar_type` por usuario vía `UserInfo`), zona `main` con patrón `sub_header` + `content`, y un mecanismo peculiar: si la vista define las secciones `sub_header` + `content` + `form`, el layout **envuelve todo el contenido en un `<form>`** cuyos atributos llegan como JSON por `@section('form')`.
- **UX destacable:** overlay global de *drag & drop* de ficheros, loader de página, soporte **RTL** (Bootstrap RTL + `dir` desde el helper `Language`), modo sidebar compacta persistida por usuario.
- **Componentes propios:** familia `datatable*` (tabla, filtros, búsqueda, acciones, selección, script), `pagination`, `sub_header`, `main-message` — un mini design-system server-side para los CRUD del panel.
- **Deuda técnica:** ~20 librerías jQuery cargadas globalmente en cada página, `main.css` de ~110.000 líneas sin *tree-shaking*, Highcharts y su world-map cargados **desde CDN externo en todas las páginas** del panel, versionado de caché manual (`?version=9.0.3`).
- Curiosamente tiene `assets/css/app.css` con Tailwind 4 + DaisyUI preparado (pipeline Vite listo), pero el layout **no lo usa**: sigue cargando el `public/css/main.css` legacy.

### 4.2 `guest/nova` — tema base del sitio público

- Tailwind 4 + DaisyUI, carga por `theme_vite('guest/nova')` (HMR en dev, manifest en prod).
- Vistas: `pages/` (home, pricing, blogs, blog_detail, contact, faqs, about, legales, 404), `auth/` completo (login, signup, activación, recuperación), `partials/` (header, footer, pricing, faqs, login-screen…), `sections/hero`.
- Es el tema de referencia: analytee y analytee_pro derivan de él (26 vistas cada uno, misma estructura).

### 4.3 `guest/analytee` — el tema activo de analytee.com

- Su `theme.json` lo confiesa: `"description": "Clon exacto de Nova"`. Metadatos mínimos (sin `preview`, `author`, `tags` ni `sort`), lo que degrada su tarjeta en el selector del admin.
- Diferencias reales con nova: layout propio (no usa `theme_vite`, enlaza `css/app.css` compilado + **Alpine.js desde CDN jsdelivr**), carpeta `vendor/guest/home.blade.php` (override de la home del módulo Guest) y ausencia de `sections/`.
- Carga tipografías desde **tres orígenes externos** (fonts.gstatic, fonts.cdnfonts — "General Sans", fonts.googleapis — Inter), más flag-icons y FontAwesome locales.
- Incluye barra de consentimiento de cookies fija en el layout.

### 4.4 `guest/analytee_pro` — variante premium

- theme.json completo (nombre "Analytee Pro", pero descripción copiada literalmente de "Nova Modern" — texto sin actualizar).
- Usa `theme_vite`, como nova. Estructuralmente es un tercer clon de la misma base.

---

## 5. Flujo completo de una petición (vista UI)

```
Request → Middleware Themes
   ├─ /app|admin|payment → ThemesManager::set('app/pico')
   └─ resto              → ThemesManager::set('guest/' . frontend_theme)
→ Controlador de módulo (p. ej. AppDashboard) → view('appdashboard::index')
→ View finder: 1º tema activo (overrides), 2º modules/AppDashboard/resources/views
→ La vista @extends('layouts.app')  ← resuelto DENTRO del tema activo
→ Layout carga assets con theme_public_asset() / theme_vite()
```

La consecuencia de diseño más importante: **los módulos definen el contenido y el tema define el marco** (layout, navegación, tokens visuales). Un módulo como `AppDashboard` solo emite `@section('content')` con clases del tema (`container`, `py-5`, loaders `.ajax-pages`), por lo que el acoplamiento módulo↔tema se produce a través de las **clases CSS** — no hay una capa de componentes/design tokens compartida que lo aísle.

---

## 6. Evaluación UI/UX — Fortalezas

1. **Separación limpia guest/app.** Poder evolucionar la landing sin arriesgar el panel (y viceversa) es la decisión arquitectónica más valiosa del sistema.
2. **Override de vistas por tema** (`vendor/<modulo>/`): personalización sin tocar módulos, ideal para white-labeling del SaaS.
3. **Pipeline Vite por tema** con HMR autodetectado (`theme_vite`) y salida autocontenida dentro del tema: cada tema es un paquete portable (import/export por ZIP funciona porque el tema lleva sus propios artefactos).
4. **Generador de temas** (`theme:make:tailwind`) con Preline/DaisyUI opcionales: reduce la barrera para crear temas consistentes con el stack moderno.
5. **Internacionalización y RTL** de serie: `lang/` por tema, `dir` dinámico y Bootstrap RTL en el panel — cobertura poco habitual y valiosa para un SaaS global.
6. **Personalización de UX por usuario** en el panel (sidebar compacta persistida), overlay global de subida de archivos, patrón `sub_header` consistente en los CRUD.
7. **Selector de temas en admin** con activación en un clic y metadatos visuales (preview, tags) — buen camino hacia un marketplace (existe módulo `AdminMarketplace`).

## 7. Evaluación UI/UX — Debilidades y riesgos

**Consistencia visual y de marca**
1. **Doble stack (Bootstrap/jQuery vs Tailwind/Alpine)** ⇒ dos lenguajes visuales, dos sistemas de espaciado/tipografía/color. El usuario percibe un "salto" al pasar de la landing al panel; el equipo mantiene dos bases de estilos.
2. **Sin design tokens compartidos.** Colores, radios y tipografías viven repetidos en cada tema; un cambio de marca exige tocar N temas. DaisyUI (ya presente) soporta theming por variables CSS y no se está explotando como capa de tokens.
3. **Tres clones de Nova** (nova / analytee / analytee_pro) con 26 vistas duplicadas cada uno ⇒ *drift* garantizado: los arreglos aplicados a uno no llegan a los demás (ya ocurre: solo analytee tiene override `vendor/guest`, solo nova tiene `sections/`).

**Metadatos y gobernanza**
4. `theme.json` **sin esquema ni validación**: analytee carece de `preview/tags/author`; analytee_pro tiene descripción de otro tema; el campo `sort` que usa el ordenamiento del selector no existe en ningún theme.json. El selector del admin renderiza tarjetas desiguales.
5. `resources/nova/` (copia de tema fuera de `themes/`) y `AdminThemes` (módulo vacío) son **restos de trabajo** que confunden sobre cuál es la fuente de verdad.

**Rendimiento (impacta directamente en UX)**
6. Panel: ~25 requests de JS/CSS bloqueantes por página, `main.css` de 110 k líneas, y **Highcharts + world map desde CDN en todas las páginas** aunque no haya gráficas. First paint y TTI pagan ese peaje a diario.
7. Guest (analytee): **Alpine y 3 orígenes de fuentes por CDN** ⇒ dependencia de terceros para el render crítico de la landing, penalización de LCP y consideraciones RGPD (Google Fonts).
8. `theme_vite` hace un `get_headers()` HTTP en el primer render de cada request para detectar el dev-server; el caché es `static` (por request, no por proceso) — coste innecesario en producción si `VITE_DEV_URL` resuelve lento.

**Seguridad / robustez (afecta a la confianza del admin UX)**
9. **Import de temas por ZIP sin validación** de contenido (no verifica theme.json, estructura, ni sanitiza rutas del ZIP antes de extraer a `resources/themes/guest/`). Un ZIP malicioso con Blade ejecuta PHP en el servidor; es una superficie de riesgo típica de este patrón.
10. El layout de pico construye atributos del `<form>` desde JSON embebido en una sección y los imprime con `{!! !!}` (escape manual por atributo): patrón frágil, difícil de auditar.

**Accesibilidad**
11. No se observa gestión de foco/skip-links, `prefers-reduced-motion` ni modo oscuro coherente entre guest y app (DaisyUI trae `themes: all` pero el panel legacy no participa).

---

## 8. Recomendaciones priorizadas

| Prioridad | Acción | Impacto |
|---|---|---|
| 🔴 Alta | **Endurecer el import ZIP** (validar theme.json contra esquema, whitelist de extensiones, prevenir path traversal, extraer a staging antes de publicar) | Seguridad del admin |
| 🔴 Alta | **Consolidar los 3 temas guest en 1 tema base + capas de override** (usar la cascada de vistas que ya existe: un tema "hijo" solo con las vistas que cambian) o, mínimo, documentar cuál es canónico | Elimina drift y triplica la velocidad de cambios |
| 🔴 Alta | **Quitar Highcharts/CDN del layout global de pico** y cargarlo solo en vistas con gráficas (`@push`), self-host de Alpine y fuentes en guest | LCP/TTI y RGPD |
| 🟠 Media | **Definir design tokens compartidos** (variables CSS consumidas por DaisyUI en guest y mapeadas a Bootstrap en app) para unificar marca entre landing y panel | Consistencia de marca |
| 🟠 Media | **Migración progresiva del panel a la pipeline Tailwind ya preparada** en `app/pico/assets` (el terreno está listo: Vite + Tailwind 4 + DaisyUI configurados pero sin usar) | Reduce el doble stack |
| 🟠 Media | **Esquema formal para `theme.json`** (campos obligatorios: name, version, preview, tags, sort) + validación en `FrontendThemeService::all()` y en el import | UX del selector de temas |
| 🟡 Baja | Limpiar `resources/nova/` y el módulo `AdminThemes` vacío (o implementarlo para elegir tema `app/` y retirar el hardcode del middleware) | Higiene del código |
| 🟡 Baja | Cachear la detección de dev-server de `theme_vite` (config/env en producción en lugar de ping HTTP) | Rendimiento server-side |
| 🟡 Baja | Auditoría de accesibilidad (foco, contraste DaisyUI, `prefers-reduced-motion`, dark mode coherente) | Calidad UX |

---

## 9. Anexo — Mapa de archivos clave

| Archivo | Rol |
|---|---|
| `app/Http/Middleware/Themes.php` | Selección de tema por URL (app hardcodeado, guest por opción) |
| `config/themes-manager.php` | Config de hexadog (directorio `themes`, symlinks, caché off) |
| `config/view.php` | Raíces de vistas: `resources/themes` + `modules` |
| `app/Helpers/Helper.php` | `theme_public_asset()`, `theme_vite()`, `menu_active()`… |
| `vite.config.js` | Build por tema (`--theme=`), salida a `<tema>/public` |
| `app/Console/Commands/MakeTailwindTheme.php` | Generador `theme:make:tailwind` |
| `modules/AdminFrontendThemes/` | Selector admin de tema guest + import ZIP |
| `modules/AdminBackendAppearance/` | Ajustes de apariencia del panel (no cambia de tema) |
| `resources/themes/app/pico/resources/views/layouts/app.blade.php` | Layout maestro del panel |
| `resources/themes/guest/analytee/resources/views/layouts/app.blade.php` | Layout del sitio público activo |
