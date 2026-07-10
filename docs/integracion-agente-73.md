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

## 5. Lo que se sabe del Agente 73 (según memoria del propietario, 2026-07-10)

- **Ubicación:** `/home/claudedev/agente73/` en el VPS. **Versión:** 4.0 "Con Alma".
- **Arquitectura:** 20 subagentes, pipeline de 13 fases.
- **Trigger:** WhatsApp vía Remote Control con **gramática cerrada** de comandos.
- **Gate de calidad:** parada obligatoria de QA en la **Fase 09** — no existe
  camino a deploy desde WhatsApp (coincide con nuestra política: validador +
  staging antes de activar).
- **Notificaciones:** email vía Gmail (`holawebdoctor@gmail.com`); pendiente un
  error `535 BadCredentials` que requiere regenerar la app password de Google.
- **Pendiente de inspección en el VPS** (supervisor/systemd, puerto/API,
  estado del servicio): ver §7.

## 6. Encaje propuesto en el pipeline de 13 fases

(Provisional hasta la inspección de §7; asume la semántica habitual de sus fases.)

1. **Gramática de WhatsApp:** añadir un comando de tema a la gramática cerrada,
   p. ej. `TEMA <id> <color-marca> [descripcion...]` → el pipeline recibe como
   spec el contrato de la §2 con esos parámetros sustituidos.
2. **Fases de generación:** producen la carpeta `<id>/` del contrato (§2) en
   lugar de una web independiente. El bloque de la §2 se inyecta en el system
   prompt de los subagentes de generación (o como spec del job).
3. **Fase 09 (QA stop):** incorporar como criterio automático de la parada:
   `php scripts/validate-theme.php guest/<id>` = OK **y** build de Vite
   compilando. El QA humano revisa además el preview visual.
4. **Post-QA:** entrega del ZIP/carpeta al repo (PR) o al import del admin —
   nunca deploy directo, igual que hoy.

## 7. Pendiente para cerrar la integración

- [ ] **Inspección del VPS** (solo lectura). En el VPS:
      `cd /home/claudedev/agente73 && claude -p "Inspecciona este directorio y dame: 1) estructura de carpetas y archivos principales; 2) cómo corre el agente (supervisor, systemd, screen, nohup); 3) puerto o endpoint si tiene API; 4) estado actual del servicio. Solo lectura, sin tocar nada."`
      Pegar el resultado en la sesión de temas para adaptar §6 al montaje real.
- [ ] Decidir dónde vive el bloque §2 según el montaje (system prompt de
      subagentes vs. spec por job) y si puede adjuntarse `guest/analytee`
      como ejemplo de referencia.
- [ ] Si el código del Agente 73 está en un repo Git: añadirlo a la sesión
      (add_repo) y versionar ahí la especialización.
- [ ] Regenerar la app password de Gmail (error 535 BadCredentials en
      `holawebdoctor@gmail.com`) — ajeno a los temas pero bloquea sus
      notificaciones.
- [ ] Primer tema piloto de extremo a extremo (WhatsApp → 13 fases → QA F09
      con validador → PR/import → staging) para calibrar el bloque §2.
