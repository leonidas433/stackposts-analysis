<?php

namespace Modules\AppAnalytee\Services;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\AppChannels\Models\Accounts;

class PlacesReviewsService
{
    public function resolvePlaceIdFromCidUrl(string $url, ?string $name = null): array
    {
        $url = trim($url);
        if ($url === '') {
            return ['status' => 0, 'message' => 'URL inválida'];
        }

        $cid = $this->extractCidFromUrl($url);
        if ($cid === '') {
            return ['status' => 0, 'message' => 'URL sin cid'];
        }

        $apiKey = (string) (get_option('gbp_api_key', '') ?: env('GOOGLE_PLACES_API_KEY', ''));
        if ($apiKey === '') {
            return ['status' => 0, 'message' => 'API Key de Places no configurada'];
        }

        $queries = [];
        $queries[] = $url;
        $name = trim((string) $name);
        if ($name !== '') {
            $queries[] = $name;
        }

        foreach ($queries as $textQuery) {
            $resp = $this->searchTextPlaceId($apiKey, $textQuery);
            if (($resp['status'] ?? 0) === 1) {
                return $resp;
            }
        }

        return ['status' => 0, 'message' => 'No se pudo resolver place_id desde cid'];
    }

    public function syncPlaceReviews(int $teamId, int $accountId, string $placeId): array
    {
        if ($teamId <= 0) {
            return ['status' => 0, 'message' => 'team_id inválido'];
        }

        if ($accountId <= 0) {
            return ['status' => 0, 'message' => 'account_id inválido'];
        }

        $placeId = trim($placeId);
        if ($placeId === '') {
            return ['status' => 0, 'message' => 'place_id inválido'];
        }

        $account = Accounts::query()
            ->byTeam($teamId)
            ->where('id', $accountId)
            ->first();

        if (! $account) {
            return ['status' => 0, 'message' => 'Cuenta no encontrada para este team'];
        }

        $apiKey = (string) (get_option('gbp_api_key', '') ?: env('GOOGLE_PLACES_API_KEY', ''));
        if ($apiKey === '') {
            return ['status' => 0, 'message' => 'API Key de Places no configurada'];
        }

        $placeResponse = $this->getPlaceDetails($apiKey, $placeId);
        if (! $placeResponse['ok']) {
            return ['status' => 0, 'message' => $placeResponse['message'], 'http_status' => $placeResponse['http_status']];
        }

        $place = $placeResponse['data'];
        $now = time();

        $placeRating = $this->toFloat(data_get($place, 'rating'));
        $placeUserRatingsTotal = $this->toInt(data_get($place, 'userRatingCount')) ?: $this->toInt(data_get($place, 'user_ratings_total'));
        $placeUrl = (string) (data_get($place, 'googleMapsUri') ?: data_get($place, 'url') ?: '');
        $placeWebsite = (string) (data_get($place, 'websiteUri') ?: data_get($place, 'website') ?: '');
        $placeTypes = data_get($place, 'types');
        $placeVicinity = (string) (data_get($place, 'shortFormattedAddress') ?: data_get($place, 'vicinity') ?: data_get($place, 'formattedAddress') ?: '');

        $this->upsertAnalyteeAccount([
            'team_id' => $teamId,
            'account_id' => $accountId,
            'place_id' => $placeId,
            'rating' => $placeRating,
            'user_ratings_total' => $placeUserRatingsTotal ?: null,
            'url' => $placeUrl !== '' ? $placeUrl : null,
            'website' => $placeWebsite !== '' ? $placeWebsite : null,
            'types' => is_array($placeTypes) ? json_encode(array_values($placeTypes)) : null,
            'vicinity' => $placeVicinity !== '' ? $placeVicinity : null,
            'place_details' => json_encode($place),
            'last_sync_at' => Carbon::now()->toDateTimeString(),
            'updated' => $now,
        ]);

        $rawReviews = data_get($place, 'reviews', []);
        if (! is_array($rawReviews)) {
            $rawReviews = [];
        }
        $rawReviews = array_slice($rawReviews, 0, 5);

        $saved = 0;
        foreach ($rawReviews as $rawReview) {
            if (! is_array($rawReview)) {
                continue;
            }

            $normalized = $this->normalizeReview($rawReview, $placeId);
            if (! $normalized) {
                continue;
            }

            $record = [
                'team_id' => $teamId,
                'account_id' => $accountId,
                'place_id' => $placeId,
                'external_id' => $normalized['external_id'],
                'author_name' => $normalized['author_name'],
                'author_url' => $normalized['author_url'],
                'rating' => $normalized['rating'],
                'text' => $normalized['text'],
                'owner_response_text' => $normalized['owner_response_text'],
                'owner_response_at' => $normalized['owner_response_at'],
                'language' => $normalized['language'],
                'meta' => $normalized['meta'] ? json_encode($normalized['meta']) : null,
                'published_at' => $normalized['published_at'],
                'updated' => $now,
            ];

            $existing = DB::table('analytee_reviews')
                ->where('team_id', $teamId)
                ->where('external_id', $normalized['external_id'])
                ->first();

            if ($existing) {
                DB::table('analytee_reviews')
                    ->where('id', $existing->id)
                    ->update($record);
                $saved++;

                continue;
            }

            DB::table('analytee_reviews')->insert(array_merge($record, [
                'created' => $now,
            ]));
            $saved++;
        }

        return [
            'status' => 1,
            'place' => [
                'place_id' => $placeId,
                'rating' => $placeRating,
                'user_ratings_total' => $placeUserRatingsTotal,
                'url' => $placeUrl,
                'website' => $placeWebsite,
                'types' => is_array($placeTypes) ? array_values($placeTypes) : [],
                'vicinity' => $placeVicinity,
            ],
            'reviews_limit' => 5,
            'reviews_fetched' => count($rawReviews),
            'reviews_saved' => $saved,
        ];
    }

    private function getPlaceDetails(string $apiKey, string $placeId): array
    {
        $fieldMask = [
            'id',
            'rating',
            'reviews.authorAttribution.displayName',
            'reviews.authorAttribution.uri',
            'reviews.authorAttribution.photoUri',
            'reviews.rating',
            'reviews.publishTime',
            'reviews.text',
            'reviews.originalText',
            'reviews.ownerResponse',
            'userRatingCount',
            'googleMapsUri',
            'websiteUri',
            'types',
            'shortFormattedAddress',
            'formattedAddress',
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-Goog-Api-Key' => $apiKey,
            'X-Goog-FieldMask' => implode(',', $fieldMask),
        ])->get('https://places.googleapis.com/v1/places/'.rawurlencode($placeId));

        if (! $response->ok()) {
            return [
                'ok' => false,
                'http_status' => $response->status(),
                'message' => 'Error consultando Place Details',
            ];
        }

        $data = $response->json();
        if (! is_array($data)) {
            return [
                'ok' => false,
                'http_status' => $response->status(),
                'message' => 'Respuesta inválida de Place Details',
            ];
        }

        return [
            'ok' => true,
            'http_status' => $response->status(),
            'data' => $data,
        ];
    }

    private function extractCidFromUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if (is_array($parts) && isset($parts['query']) && is_string($parts['query'])) {
            $qs = [];
            parse_str($parts['query'], $qs);
            $cid = trim((string) ($qs['cid'] ?? ''));
            if ($cid !== '') {
                return $cid;
            }
        }

        if (preg_match('/(?:\?|&)cid=([^&]+)/i', $url, $m)) {
            return trim(urldecode((string) ($m[1] ?? '')));
        }

        return '';
    }

    private function searchTextPlaceId(string $apiKey, string $textQuery): array
    {
        $textQuery = trim($textQuery);
        if ($textQuery === '') {
            return ['status' => 0, 'message' => 'textQuery vacío'];
        }

        $attempts = 0;
        $response = null;

        while ($attempts < 2) {
            $attempts++;

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Goog-Api-Key' => $apiKey,
                'X-Goog-FieldMask' => 'places.id',
            ])->post('https://places.googleapis.com/v1/places:searchText', [
                'textQuery' => $textQuery,
            ]);

            if ($response->status() === 429 || $response->status() === 503 || $response->status() === 502 || $response->status() === 500) {
                usleep(500000);

                continue;
            }

            break;
        }

        if (! $response || ! $response->ok()) {
            return [
                'status' => 0,
                'message' => 'Error consultando Places searchText',
                'http_status' => $response ? $response->status() : null,
            ];
        }

        $data = $response->json();
        if (! is_array($data)) {
            return [
                'status' => 0,
                'message' => 'Respuesta inválida de Places searchText',
                'http_status' => $response->status(),
            ];
        }

        $places = data_get($data, 'places', []);
        if (! is_array($places) || empty($places)) {
            return [
                'status' => 0,
                'message' => 'Sin resultados en Places searchText',
                'http_status' => $response->status(),
            ];
        }

        $first = is_array($places[0] ?? null) ? $places[0] : [];
        $placeId = trim((string) ($first['id'] ?? ''));

        if ($placeId === '' || ! str_starts_with($placeId, 'ChIJ')) {
            return [
                'status' => 0,
                'message' => 'place_id inválido devuelto por Places searchText',
                'http_status' => $response->status(),
            ];
        }

        return [
            'status' => 1,
            'place_id' => $placeId,
            'http_status' => $response->status(),
        ];
    }

    private function normalizeReview(array $rawReview, string $placeId): ?array
    {
        $authorName = (string) (data_get($rawReview, 'authorAttribution.displayName')
            ?: data_get($rawReview, 'author_name')
            ?: data_get($rawReview, 'authorName')
            ?: '');
        $authorUrl = (string) (data_get($rawReview, 'authorAttribution.uri')
            ?: data_get($rawReview, 'author_url')
            ?: data_get($rawReview, 'authorUrl')
            ?: '');
        $authorPhotoUrl = (string) (data_get($rawReview, 'authorAttribution.photoUri')
            ?: data_get($rawReview, 'authorAttribution.photoUrl')
            ?: data_get($rawReview, 'profile_photo_url')
            ?: data_get($rawReview, 'profilePhotoUrl')
            ?: data_get($rawReview, 'photo_url')
            ?: data_get($rawReview, 'photoUrl')
            ?: '');

        $rating = $this->toInt(data_get($rawReview, 'rating'));
        if ($rating <= 0) {
            return null;
        }

        $text = (string) (data_get($rawReview, 'text.text')
            ?: data_get($rawReview, 'originalText.text')
            ?: data_get($rawReview, 'text')
            ?: '');
        $language = (string) (data_get($rawReview, 'text.languageCode')
            ?: data_get($rawReview, 'languageCode')
            ?: data_get($rawReview, 'language')
            ?: '');

        $publishedAt = $this->parseDateTime(
            data_get($rawReview, 'publishTime')
            ?: data_get($rawReview, 'time')
            ?: data_get($rawReview, 'published_at')
        );

        if (! $publishedAt) {
            $publishedAt = Carbon::now();
        }

        $ownerResponseText = (string) (data_get($rawReview, 'ownerResponse.text.text')
            ?: data_get($rawReview, 'ownerResponse.text')
            ?: data_get($rawReview, 'owner_response.text')
            ?: data_get($rawReview, 'owner_response_text')
            ?: '');
        $ownerResponseAt = $this->parseDateTime(
            data_get($rawReview, 'ownerResponse.publishTime')
            ?: data_get($rawReview, 'ownerResponse.time')
            ?: data_get($rawReview, 'owner_response.time')
            ?: data_get($rawReview, 'owner_response_at')
        );

        $aspectRatings = $this->normalizeAspectRatings($rawReview);

        $externalIdSource = implode('|', [
            $placeId,
            $authorName,
            $publishedAt->toIso8601String(),
            $text,
        ]);
        $externalId = hash('sha256', $externalIdSource);

        $meta = [
            'source' => 'places',
        ];
        if (trim($authorPhotoUrl) !== '') {
            $meta['author_photo_url'] = trim($authorPhotoUrl);
        }
        if (! empty($aspectRatings)) {
            $meta['aspects'] = $aspectRatings;
        }

        return [
            'external_id' => $externalId,
            'author_name' => $authorName !== '' ? $authorName : null,
            'author_url' => $authorUrl !== '' ? $authorUrl : null,
            'rating' => $rating,
            'text' => $text !== '' ? $text : null,
            'language' => $language !== '' ? $language : null,
            'published_at' => $publishedAt->toDateTimeString(),
            'owner_response_text' => $ownerResponseText !== '' ? $ownerResponseText : null,
            'owner_response_at' => $ownerResponseAt?->toDateTimeString(),
            'meta' => $meta,
        ];
    }

    private function normalizeAspectRatings(array $rawReview): array
    {
        $aspects = data_get($rawReview, 'reviewAspects');
        if (! is_array($aspects)) {
            $aspects = data_get($rawReview, 'aspects');
        }
        if (! is_array($aspects)) {
            return [];
        }

        $validTypes = [
            'appeal',
            'atmosphere',
            'decor',
            'facilities',
            'food',
            'overall',
            'quality',
            'service',
        ];

        $out = [];
        foreach ($aspects as $aspect) {
            if (! is_array($aspect)) {
                continue;
            }
            $type = (string) (data_get($aspect, 'type') ?: data_get($aspect, 'aspect') ?: data_get($aspect, 'name') ?: '');
            $type = strtolower(trim($type));
            if ($type === '') {
                continue;
            }
            if (! in_array($type, $validTypes, true)) {
                continue;
            }
            $rating = $this->toInt(data_get($aspect, 'rating'));
            if ($rating <= 0) {
                continue;
            }
            $out[] = ['type' => $type, 'rating' => $rating];
        }

        return array_values(Arr::unique($out, fn ($v) => $v['type']));
    }

    private function upsertAnalyteeAccount(array $values): void
    {
        $teamId = (int) ($values['team_id'] ?? 0);
        $accountId = (int) ($values['account_id'] ?? 0);
        if ($teamId <= 0 || $accountId <= 0) {
            return;
        }

        $existing = DB::table('analytee_accounts')
            ->where('team_id', $teamId)
            ->where('account_id', $accountId)
            ->first();

        if ($existing) {
            DB::table('analytee_accounts')
                ->where('id', $existing->id)
                ->update($values);

            return;
        }

        $now = time();
        DB::table('analytee_accounts')->insert(array_merge([
            'team_id' => $teamId,
            'account_id' => $accountId,
            'status' => 'connected',
            'created' => $now,
            'updated' => $now,
        ], $values));
    }

    private function parseDateTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value);
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function toInt(mixed $value): int
    {
        if ($value === null) {
            return 0;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }

        return (int) trim((string) $value);
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_float($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $v = trim((string) $value);
        if ($v === '') {
            return null;
        }

        return (float) $v;
    }
}
