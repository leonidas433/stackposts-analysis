# Brief SEO — tema `guest/resenas` (análisis de reseñas + programación social)

**Fecha:** 2026-07-10 · **Mercado:** España / hispanohablante ·
**Fuentes:** investigación web (ver PR) — pendiente de contrastar volúmenes
con DataForSEO vía el skill `keyword-research-seo` en una sesión claude.ai.

## 1. Mapa de keywords

| Rol | Keyword | Intención | Dónde |
|---|---|---|---|
| **Principal** | análisis de reseñas | comercial | H1 del hero + primer párrafo |
| Secundaria 1 | gestión de reseñas de Google | comercial | H2 sección features |
| Secundaria 2 | reputación online | informacional/comercial | hero sub + H2 |
| Secundaria 3 | análisis de sentimiento con IA | comercial | H3 feature |
| Secundaria 4 | responder reseñas con IA | transaccional | H3 feature |
| Secundaria 5 | programar publicaciones en redes sociales | transaccional | H2 sección valor añadido |
| Secundaria 6 | gestionar redes sociales en un solo lugar | transaccional | copy valor añadido |
| Long-tail | cómo responder reseñas negativas de Google | informacional | FAQ |
| Long-tail | software de gestión de reseñas para negocios locales | comercial | FAQ / copy |

Dato de apoyo usable en copy (citado en la investigación, origen BrightLocal):
~87% de los consumidores leen reseñas de Google antes de visitar un negocio.

## 2. Estructura on-page del tema

1. **Hero** — H1: "Análisis de reseñas con IA para cuidar tu reputación online".
   Sub: monitoriza, entiende y responde las reseñas de Google desde un panel.
   CTA primario: "Empieza gratis" → signup. CTA secundario: "Ver precios".
2. **Features (gestión de reseñas de Google)** — 4 tarjetas:
   análisis de sentimiento con IA · respuestas con IA en tu tono de marca ·
   monitorización multi-plataforma · informes de reputación.
3. **Valor añadido (todo en uno)** — H2: "Y además: programa las publicaciones
   de todas tus redes sociales en un solo lugar" (módulos de publicación de
   StackPost: calendario, campañas, borradores, RSS).
4. **Prueba social** — el dato del 87% + logos/testimonios placeholder.
5. **CTA final** + FAQs (las 2 long-tail) — las FAQs alimentan AI Overviews.

## 3. Recomendaciones fuera del tema (configurar en el admin)

- **Meta title** (`website_title`): "Análisis de reseñas con IA y gestión de
  redes sociales | Analytee" (≤60 car.).
- **Meta description** (`website_description`): "Analiza el sentimiento de tus
  reseñas de Google, responde con IA y programa las publicaciones de todas tus
  redes desde un solo lugar. Prueba Analytee gratis." (≤155 car.).
- **Schema.org** (vía Admin → Embed Code, JSON-LD): `SoftwareApplication` +
  `FAQPage` con las preguntas de la sección FAQs — usar el skill
  `schema-markup` de la cuenta claude.ai para generarlo.
- Tras publicar: auditar con los skills `seo-audit-v2` y `ai-seo-v2`.
