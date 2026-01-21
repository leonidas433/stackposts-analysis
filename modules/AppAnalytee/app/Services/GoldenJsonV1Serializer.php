<?php

namespace Modules\AppAnalytee\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;

class GoldenJsonV1Serializer
{
    public const VERSION = 'v1';

    public const MAX_REVIEWS = 2000;

    public const MAX_JSON_BYTES = 10485760;

    public function build(array $account, array $reviews): array
    {
        $filtered = $this->filterReviews($reviews);
        $sorted = $this->sortReviewsDesc($filtered);
        $limited = $this->limitReviews($sorted);
        $deduped = $this->deduplicate($limited);

        $payload = $this->buildPayload($account, $deduped);
        $this->validate($payload);

        return $payload;
    }

    public function encode(array $payload, bool $pretty = false): string
    {
        $this->validate($payload);

        $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($pretty) {
            $options |= JSON_PRETTY_PRINT;
        }

        $json = json_encode($payload, $options);
        if (! is_string($json) || $json === '') {
            throw new \RuntimeException('JSON inválido (json_encode falló)');
        }
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('JSON inválido: '.json_last_error_msg());
        }

        if (strlen($json) > self::MAX_JSON_BYTES) {
            throw new \RuntimeException('Payload JSON excede 10MB');
        }

        return $json;
    }

    public function validate(array $payload): void
    {
        $requiredRoot = ['address', 'category', 'totalScore', 'reviewsCount', 'website', 'phoneNumber', 'reviews'];
        foreach ($requiredRoot as $k) {
            if (! array_key_exists($k, $payload)) {
                throw new \RuntimeException("Falta root key: {$k}");
            }
        }

        if (! is_array($payload['reviews'])) {
            throw new \RuntimeException('reviews debe ser array');
        }

        if (count($payload['reviews']) > self::MAX_REVIEWS) {
            throw new \RuntimeException('reviews excede MAX_REVIEWS');
        }

        foreach ($payload['reviews'] as $idx => $r) {
            if (! is_array($r)) {
                throw new \RuntimeException("review[{$idx}] inválida");
            }
            $required = ['author', 'rating', 'date', 'text', 'responseFromOwnerText', 'likes', 'photos'];
            foreach ($required as $k) {
                if (! array_key_exists($k, $r)) {
                    throw new \RuntimeException("Falta review[{$idx}].{$k}");
                }
            }

            if (! is_string($r['author']) || trim($r['author']) === '') {
                throw new \RuntimeException("review[{$idx}].author inválido");
            }

            $rating = (int) $r['rating'];
            if ($rating < 1 || $rating > 5) {
                throw new \RuntimeException("review[{$idx}].rating inválido");
            }

            $date = (string) $r['date'];
            if (! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date)) {
                throw new \RuntimeException("review[{$idx}].date inválida");
            }

            if (! is_string($r['text'])) {
                throw new \RuntimeException("review[{$idx}].text inválido");
            }

            $resp = $r['responseFromOwnerText'];
            if (! (is_null($resp) || is_string($resp))) {
                throw new \RuntimeException("review[{$idx}].responseFromOwnerText inválido");
            }

            if (! is_int($r['likes']) || $r['likes'] !== 0) {
                throw new \RuntimeException("review[{$idx}].likes inválido");
            }

            if (! is_array($r['photos']) || $r['photos'] !== []) {
                throw new \RuntimeException("review[{$idx}].photos inválido");
            }
        }
    }

    private function buildPayload(array $account, array $reviews): array
    {
        $address = $this->normalizeNullableString($account['vicinity'] ?? null);
        $website = $this->normalizeNullableString($account['website'] ?? null);

        $typesRaw = $account['types'] ?? '[]';
        $types = json_decode((string) $typesRaw, true);
        $types = is_array($types) ? $types : [];
        $category = ! empty($types) ? ($types[0] ?? null) : null;

        $totalScore = $account['rating'] ?? null;
        $totalScore = is_numeric($totalScore) ? (float) $totalScore : null;

        $reviewsCount = $account['user_ratings_total'] ?? null;
        $reviewsCount = is_numeric($reviewsCount) ? (int) $reviewsCount : null;

        $outReviews = [];
        foreach ($reviews as $r) {
            $publishedAt = Carbon::parse((string) ($r['published_at'] ?? ''))->format('Y-m-d H:i:s');
            $outReviews[] = [
                'author' => $this->normalizeAuthor($r['author_name'] ?? null),
                'rating' => (int) ($r['rating'] ?? 0),
                'date' => $publishedAt,
                'text' => (string) ($r['text'] ?? ''),
                'responseFromOwnerText' => $this->normalizeOwnerResponse($r['owner_response_text'] ?? null),
                'likes' => 0,
                'photos' => [],
            ];
        }

        return [
            'address' => $address,
            'category' => $category,
            'totalScore' => $totalScore,
            'reviewsCount' => $reviewsCount,
            'website' => $website,
            'phoneNumber' => null,
            'reviews' => $outReviews,
        ];
    }

    private function filterReviews(array $reviews): array
    {
        $out = [];
        foreach ($reviews as $r) {
            if (! is_array($r)) {
                continue;
            }

            $rating = (int) ($r['rating'] ?? 0);
            if ($rating < 1 || $rating > 5) {
                continue;
            }

            $publishedAt = $r['published_at'] ?? null;
            if ($publishedAt === null || trim((string) $publishedAt) === '') {
                continue;
            }

            $externalId = (string) ($r['external_id'] ?? '');
            if ($externalId !== '' && preg_match('/^[0-9a-f]{64}$/i', $externalId) === 1) {
                continue;
            }

            $source = $this->extractMetaSource($r['meta'] ?? null);
            if ($source !== 'gbp') {
                continue;
            }

            $out[] = $r;
        }

        return array_values($out);
    }

    private function extractMetaSource(mixed $meta): ?string
    {
        if (is_array($meta)) {
            return isset($meta['source']) ? (string) $meta['source'] : null;
        }

        if (is_object($meta)) {
            return isset($meta->source) ? (string) $meta->source : null;
        }

        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            if (is_array($decoded) && array_key_exists('source', $decoded)) {
                return is_string($decoded['source']) ? $decoded['source'] : (is_null($decoded['source']) ? null : (string) $decoded['source']);
            }
        }

        return null;
    }

    private function sortReviewsDesc(array $reviews): array
    {
        usort($reviews, function (array $a, array $b): int {
            return strcmp((string) ($b['published_at'] ?? ''), (string) ($a['published_at'] ?? ''));
        });

        return $reviews;
    }

    private function limitReviews(array $reviews): array
    {
        if (count($reviews) > self::MAX_REVIEWS) {
            return array_slice($reviews, 0, self::MAX_REVIEWS);
        }

        return $reviews;
    }

    private function deduplicate(array $reviews): array
    {
        $seen = [];
        foreach ($reviews as $r) {
            $text = $r['text'] ?? null;
            $rating = (int) ($r['rating'] ?? 0);
            $publishedAt = (string) ($r['published_at'] ?? '');

            $normText = $this->normalizeForDedup($text);
            if ($normText !== '') {
                $key = hash('sha256', $normText).'|'.$rating.'|'.$publishedAt;
            } else {
                $authorKey = hash('sha256', $this->normalizeForDedup(Str::lower(trim((string) ($r['author_name'] ?? '')))));
                $key = 'EMPTY|'.$rating.'|'.$publishedAt.'|'.$authorKey;
            }

            $existing = $seen[$key] ?? null;
            if ($existing === null) {
                $seen[$key] = $r;

                continue;
            }

            $seen[$key] = $this->pickWinner($existing, $r);
        }

        return array_values($seen);
    }

    private function pickWinner(array $a, array $b): array
    {
        $aOwner = $this->normalizeOwnerResponse($a['owner_response_text'] ?? null) !== null;
        $bOwner = $this->normalizeOwnerResponse($b['owner_response_text'] ?? null) !== null;
        if ($aOwner !== $bOwner) {
            return $aOwner ? $a : $b;
        }

        $aLen = strlen((string) ($a['text'] ?? ''));
        $bLen = strlen((string) ($b['text'] ?? ''));
        if ($aLen !== $bLen) {
            return $aLen > $bLen ? $a : $b;
        }

        $aTs = Carbon::parse((string) ($a['published_at'] ?? ''))->timestamp;
        $bTs = Carbon::parse((string) ($b['published_at'] ?? ''))->timestamp;
        if ($aTs !== $bTs) {
            return $aTs > $bTs ? $a : $b;
        }

        return $a;
    }

    private function normalizeAuthor(mixed $value): string
    {
        $s = trim((string) ($value ?? ''));

        return $s !== '' ? $s : 'Autor';
    }

    private function normalizeOwnerResponse(mixed $value): ?string
    {
        $s = trim((string) ($value ?? ''));

        return $s !== '' ? $s : null;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $s = trim((string) ($value ?? ''));

        return $s !== '' ? $s : null;
    }

    private function normalizeForDedup(mixed $text): string
    {
        if ($text === null) {
            return '';
        }

        $s = trim((string) $text);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        $s = Str::lower($s);
        $s = Str::ascii($s);

        return $s;
    }
}
