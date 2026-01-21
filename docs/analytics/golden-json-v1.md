# Golden JSON Contract v1 (AppAnalytee → analytics_engine)

## Version

- Contract version: `v1`
- Serializer implementation: `Modules\AppAnalytee\Services\GoldenJsonV1Serializer`

## Scope

This contract defines the exact JSON payload that AppAnalytee MUST generate as input for `analytics_engine`. This contract is immutable. Any change in structure, defaults, filtering, normalization, or deduplication MUST be released as a new version.

## Limits

- `reviews` length MUST be `<= 2000`
- Encoded JSON UTF-8 byte length MUST be `< 10485760` (10 MB)

## Root Object

The root MUST be a JSON object with the following keys, in this exact set:

| Key | Type | Required | Default / Rule |
|---|---:|:---:|---|
| `address` | `string|null` | YES | `trim` and `null` if empty |
| `category` | `string|null` | YES | First element of decoded `types` array, else `null` |
| `totalScore` | `number|null` | YES | `float` if numeric, else `null` |
| `reviewsCount` | `integer|null` | YES | `int` if numeric, else `null` |
| `website` | `string|null` | YES | `trim` and `null` if empty |
| `phoneNumber` | `null` | YES | Always `null` |
| `reviews` | `array` | YES | Always present (empty array is allowed by contract; production guardrail blocks execution) |

## Review Object

Each entry in `reviews` MUST be a JSON object with the following keys, in this exact set:

| Key | Type | Required | Default / Rule |
|---|---:|:---:|---|
| `author` | `string` | YES | `trim`, fallback to `"Autor"` if empty |
| `rating` | `integer` | YES | MUST be in range `1..5` |
| `date` | `string` | YES | Format MUST match `YYYY-MM-DD HH:MM:SS` |
| `text` | `string` | YES | Cast to string, no trimming applied |
| `responseFromOwnerText` | `string|null` | YES | `trim`, `null` if empty |
| `likes` | `integer` | YES | Always `0` |
| `photos` | `array` | YES | Always `[]` |

## Filtering Rules (Input Rows → Included Reviews)

Input rows are treated as “raw DB rows” with keys:
- `external_id`, `author_name`, `rating`, `text`, `published_at`, `owner_response_text`, `meta`

A row MUST be excluded if any of the following is true:

- `rating` is not an integer in range `1..5`
- `published_at` is `null` or empty string after trimming
- `external_id` matches regex `^[0-9a-f]{64}$` (hex 64 chars, case-insensitive)
- `meta.source` is not exactly `"gbp"`

`meta.source` extraction MUST follow these rules:
- If `meta` is array: use `meta['source']`
- If `meta` is object: use `meta->source`
- If `meta` is string: JSON-decode and use `decoded['source']`
- Otherwise: source is `null`

## Sorting Rule

After filtering, rows MUST be sorted descending by `published_at` (string comparison).

## Deduplication (Deterministic)

Deduplication is applied after sorting (and after applying the limit of `2000`).

### Normalization for Dedup Keys

Normalization function `N(x)` is:

- If `x` is `null`: return empty string `""`
- Else:
  - Convert to string
  - `trim`
  - Replace all Unicode whitespace runs with a single ASCII space using regex `\s+` → `" "`
  - Convert to lowercase
  - Convert to ASCII (diacritics removed)

### Deduplication Key

For each review row:

- Let `T = N(text)`
- Let `R = integer rating`
- Let `D = string published_at`

If `T != ""`:
- key = `sha256(T) + "|" + R + "|" + D`

If `T == ""`:
- Let `A = N(lowercase(trim(author_name)))`
- key = `"EMPTY|" + R + "|" + D + "|" + sha256(A)`

### Winner Selection (pickWinner)

When two rows share the same key, the kept row MUST be selected by this priority:

1. Prefer the row with non-empty `owner_response_text` after trimming
2. If tied, prefer the row with longer `text` byte length
3. If tied, prefer the row with later `published_at` timestamp
4. If tied, keep the first encountered row

## Production Guardrails

In production execution of the analysis job:

- If any required root key is missing, execution MUST throw an exception (no fallback)
- If `reviews` is empty, execution MUST abort with an exception
- If encoded JSON size exceeds the limit, execution MUST abort with an exception

## Example (Generated from Fixtures)

The following example is an exact full JSON payload generated from fixtures under `tests/Fixtures/AppAnalytee`.

```json
{
    "address": "Calle Falsa 123, Madrid",
    "category": "restaurant",
    "totalScore": 4.3,
    "reviewsCount": 128,
    "website": "https://fixture.example",
    "phoneNumber": null,
    "reviews": [
        {
            "author": "Ana Gómez",
            "rating": 5,
            "date": "2024-01-10 09:00:00",
            "text": "  EXCELENTE   atención  y  comida.  Volveremos  seguro. ",
            "responseFromOwnerText": "Gracias por tu visita",
            "likes": 0,
            "photos": []
        },
        {
            "author": "Carlos Ruiz",
            "rating": 4,
            "date": "2024-01-09 08:00:00",
            "text": "   ",
            "responseFromOwnerText": "Gracias por tu reseña",
            "likes": 0,
            "photos": []
        },
        {
            "author": "María López",
            "rating": 3,
            "date": "2024-01-08 14:30:00",
            "text": "Correcto, aunque tardaron un poco.",
            "responseFromOwnerText": "Sentimos la espera, gracias por avisar",
            "likes": 0,
            "photos": []
        },
        {
            "author": "Luis Martín",
            "rating": 2,
            "date": "2024-01-07 20:15:00",
            "text": "El servicio fue lento y la comida llegó fría.",
            "responseFromOwnerText": null,
            "likes": 0,
            "photos": []
        },
        {
            "author": "Sofía Pérez",
            "rating": 1,
            "date": "2024-01-06 11:45:00",
            "text": "Mala experiencia.",
            "responseFromOwnerText": "Lamentamos lo ocurrido, contáctanos",
            "likes": 0,
            "photos": []
        },
        {
            "author": "Javier Torres",
            "rating": 5,
            "date": "2024-01-05 19:10:00",
            "text": "Un sitio increíble. La carta es amplia y el personal muy amable. Recomendado.",
            "responseFromOwnerText": null,
            "likes": 0,
            "photos": []
        },
        {
            "author": "Elena Díaz",
            "rating": 4,
            "date": "2024-01-04 21:05:00",
            "text": "Buen ambiente, precios correctos.",
            "responseFromOwnerText": null,
            "likes": 0,
            "photos": []
        },
        {
            "author": "Pablo Sánchez",
            "rating": 3,
            "date": "2024-01-03 13:00:00",
            "text": "Normal.",
            "responseFromOwnerText": null,
            "likes": 0,
            "photos": []
        },
        {
            "author": "Isabel Romero",
            "rating": 2,
            "date": "2024-01-02 10:20:00",
            "text": "Podría mejorar la limpieza.",
            "responseFromOwnerText": null,
            "likes": 0,
            "photos": []
        },
        {
            "author": "Miguel Ortega",
            "rating": 5,
            "date": "2024-01-01 16:40:00",
            "text": "La mejor paella que he probado en mucho tiempo.",
            "responseFromOwnerText": "¡Qué alegría leerte! Gracias",
            "likes": 0,
            "photos": []
        },
        {
            "author": "Raquel Navarro",
            "rating": 4,
            "date": "2023-12-31 12:00:00",
            "text": "Volvimos por segunda vez y mantiene el nivel.",
            "responseFromOwnerText": null,
            "likes": 0,
            "photos": []
        },
        {
            "author": "Daniel Castillo",
            "rating": 3,
            "date": "2023-12-30 18:25:00",
            "text": "Está bien, pero el ruido era alto.",
            "responseFromOwnerText": null,
            "likes": 0,
            "photos": []
        },
        {
            "author": "Teresa Gil",
            "rating": 1,
            "date": "2023-12-29 09:50:00",
            "text": "No lo recomiendo.",
            "responseFromOwnerText": null,
            "likes": 0,
            "photos": []
        },
        {
            "author": "Héctor Molina",
            "rating": 5,
            "date": "2023-12-28 22:10:00",
            "text": "Texto largo: Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.",
            "responseFromOwnerText": null,
            "likes": 0,
            "photos": []
        },
        {
            "author": "Nuria Vega",
            "rating": 2,
            "date": "2023-12-27 21:30:00",
            "text": "La música demasiado alta.",
            "responseFromOwnerText": null,
            "likes": 0,
            "photos": []
        }
    ]
}
```

