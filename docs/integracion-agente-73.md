# Integración del "Agente 73 — Con Alma" con los temas de StackPost

**Objetivo:** que el agente de creación de webs del VPS pueda producir temas
para StackPost/analytee.com sin romper el contrato del sistema de temas.

**Cómo usar este documento:** la §2 es un bloque de prompt **autocontenido**
listo para pegar en la configuración del Agente 73 (system prompt,
instrucciones adicionales o como mensaje fijo de contexto, según cómo esté
montado). No requiere que el agente tenga acceso a este repositorio.

---

## 1. Arquitectura de la integración

El Agente 73 NO escribe directamente en producción. Produce **uno de estos dos
artefactos**, y el resto del pipeline los valida:

| Modo | Artefacto que produce | Quién lo integra |
|---|---|---|
| **A. Tema hijo completo** (preferido si el agente puede seguir el contrato de §2) | Carpeta `mitema/` con la estructura de la §2 | Se copia a `resources/themes/guest/` en una rama y se valida con `php scripts/validate-theme.php guest/mitema` antes del PR |
| **B. Solo diseño** (si el agente diseña HTML/CSS libre) | HTML + assets + guía de estilo (paleta, tipografías) | El subagente `stackpost-theme-builder` de este repo lo porta a tema hijo (sabe traducir paleta→tokens, textos→`__()`, assets→`theme_public_asset()`) |

En ambos modos, el **gate objetivo** es el mismo: el validador CLI debe salir
`OK` y el build de Vite debe compilar. Un ZIP del tema también puede importarse
por el admin (Admin → Themes → Frontend → Import), que aplica la validación
estricta en servidor.

Recomendación: empieza por el **modo B** (cero cambios en el Agente 73, solo
recoges su output y se lo pasas al theme-builder); pasa al modo A cuando
quieras el flujo de una sola pieza.

## 2. Bloque de especialización para el Agente 73 (pegar tal cual)

```text
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
```

## 3. Flujo operativo recomendado (modo A)

1. En el VPS, el Agente 73 genera `mitema/` según §2 y lo comprime.
2. Súbelo al repo (rama nueva) o impórtalo por el admin:
   - **Rama**: descomprimir en `resources/themes/guest/`, ejecutar
     `php scripts/validate-theme.php guest/mitema` y
     `npm run build --theme=guest/mitema`, commit (incluidos los compilados) y PR.
     En la sesión de Claude Code puedes delegarlo entero: *"integra el tema
     mitema.zip que generó el Agente 73"* → lo hará `stackpost-theme-builder`.
   - **Admin import**: el ZIP pasa el validador estricto del servidor; después
     hay que compilar los assets igualmente (el import no ejecuta npm).
3. Checklist de staging de `docs/guia-crear-un-tema.md` §11 antes de activarlo
   en producción.

## 4. Qué NO delegar en el Agente 73

- Tocar `guest/nova` (tema base), helpers, middleware o el módulo
  AdminFrontendThemes: cambios de plataforma van por PR revisado.
- Elegir la política dark-mode global o cambiar tokens de `_shared/`:
  decisiones de marca, no de cada tema.
- Publicar/activar temas: siempre pasa por validador + staging.

## 5. Pendiente para cerrar la integración

- [ ] Confirmar cómo está montado el Agente 73 en el VPS (¿script propio,
      n8n, API de Anthropic/OpenAI, Claude Code?) para decidir dónde pegar el
      bloque §2 y si puede recibir ejemplos (adjuntar `guest/analytee` como
      referencia de hijo bien hecho ayuda mucho).
- [ ] Si el Agente 73 vive en un repo Git: añadirlo a la sesión con add_repo
      y adaptamos su prompt directamente en su código.
- [ ] Primer tema piloto de extremo a extremo (Agente 73 → validador → build
      → staging) para calibrar el bloque §2 con fricciones reales.
