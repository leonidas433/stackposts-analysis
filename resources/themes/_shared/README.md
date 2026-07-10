# `resources/themes/_shared/`

Recursos de diseño **compartidos por todos los temas** (guest y app):

| Archivo | Qué contiene |
|---|---|
| `tokens.css` | Design tokens de marca (`--sp-*`): colores, tipografías, radios, sombras, movimiento. Única fuente de verdad para un rebrand. |
| `fonts.css` | `@font-face` de las fuentes self-hosted (Inter). Vite copia los woff2 al build de cada tema. |
| `fonts/*.woff2` | Binarios de las fuentes. |

## Por qué este directorio no es un tema

- **hexadog/laravel-themes-manager lo ignora**: su escáner solo registra
  directorios con un `composer.json` de `"type": "laravel-theme"` a
  profundidad 2 (`<grupo>/<tema>/composer.json`), y `_shared/` no lo tiene.
- **El selector de temas del admin lo ignora**: `FrontendThemeService` solo
  escanea `resources/themes/guest/*`, y el import de ZIPs rechaza `_shared`
  como nombre reservado.

## Cómo consumirlo desde un tema

En el CSS fuente del tema (`assets/css/app.css` o `base.css`):

```css
@import "../../../../_shared/tokens.css";
@import "../../../../_shared/fonts.css";
```

Ver la guía completa en `docs/guia-crear-un-tema.md`.
