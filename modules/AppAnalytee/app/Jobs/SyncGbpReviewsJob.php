<?php

namespace Modules\AppAnalytee\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\AppAnalytee\Services\PlacesReviewsService;
use Modules\AppChannelGBPLocations\Facades\Post as GbpPost;
use Modules\AppChannels\Models\Accounts;

class SyncGbpReviewsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $teamId,
        public int $accountId
    ) {}

    public function handle(): void
    {
        $teamId = (int) $this->teamId;
        $accountId = (int) $this->accountId;
        if ($teamId <= 0 || $accountId <= 0) {
            return;
        }

        $account = Accounts::query()
            ->byTeam($teamId)
            ->where('id', $accountId)
            ->first();

        if (! $account) {
            return;
        }

        if (! $this->tokenHasGbpScope($account->token)) {
            $this->markAccountNeedsReauth($account, 'missing_scope');

            DB::table('analytee_accounts')->updateOrInsert(
                ['team_id' => $teamId, 'account_id' => $accountId],
                [
                    'status' => 'needs_reauth',
                    'updated' => time(),
                    'created' => time(),
                ]
            );

            return;
        }

        $accountData = is_array($account->data) ? $account->data : (json_decode((string) ($account->data ?? ''), true) ?: []);
        $placeId = trim((string) ($accountData['place_id'] ?? ''));

        if ($placeId === '') {
            $placeId = $this->extractPlaceIdFromUrl((string) ($account->url ?? ''));
            if ($placeId !== '' && str_starts_with($placeId, 'ChIJ')) {
                $accountData['place_id'] = $placeId;
                Accounts::where('id', $account->id)->update([
                    'data' => json_encode($accountData, JSON_UNESCAPED_UNICODE),
                ]);
            }
        }

        if ($placeId === '' && $this->extractCidFromUrl((string) ($account->url ?? '')) !== '') {
            $resolved = app(PlacesReviewsService::class)->resolvePlaceIdFromCidUrl((string) ($account->url ?? ''), (string) ($account->name ?? ''));
            if (($resolved['status'] ?? 0) === 1) {
                $candidate = trim((string) ($resolved['place_id'] ?? ''));
                if ($candidate !== '' && str_starts_with($candidate, 'ChIJ')) {
                    $placeId = $candidate;
                    $accountData['place_id'] = $placeId;
                    Accounts::where('id', $account->id)->update([
                        'data' => json_encode($accountData, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            }
        }

        if ($placeId === '') {
            Log::warning('Analytee GBP sync: missing place_id', [
                'team_id' => $teamId,
                'account_id' => $accountId,
                'account_url' => (string) ($account->url ?? ''),
            ]);
        }

        $nowTs = time();
        DB::table('analytee_accounts')->updateOrInsert(
            ['team_id' => $teamId, 'account_id' => $accountId],
            [
                'place_id' => $placeId !== '' ? $placeId : null,
                'url' => (string) ($account->url ?? ''),
                'status' => 'sync_running',
                'updated' => $nowTs,
                'created' => $nowTs,
            ]
        );

        $pageToken = null;
        $pageSize = 50;
        $maxPages = 20;
        $pages = 0;
        $fetched = 0;
        $inserted = 0;
        $updated = 0;
        $ignored = 0;
        $errors = 0;

        $reviewModel = new class extends Model
        {
            protected $table = 'analytee_reviews';

            protected $guarded = [];

            public $timestamps = false;
        };

        while ($pages < $maxPages) {
            $pages++;
            $resp = $this->listReviewsWithRetry($teamId, $accountId, $account, $pageToken, $pageSize);
            if (($resp['status'] ?? 0) !== 1) {
                Log::warning('Analytee GBP sync failed', [
                    'team_id' => $teamId,
                    'account_id' => $accountId,
                    'http_status' => $resp['http_status'] ?? null,
                    'endpoint' => $resp['endpoint'] ?? null,
                    'message' => $resp['message'] ?? null,
                ]);

                return;
            }

            $data = $resp['data'] ?? [];
            $items = $data['reviews'] ?? [];
            if (! is_array($items)) {
                $items = [];
            }

            $now = time();
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $reviewName = trim((string) ($item['name'] ?? ''));
                if ($reviewName === '') {
                    continue;
                }

                $rating = $this->normalizeGbpRating($item['starRating'] ?? null);
                $comment = (string) ($item['comment'] ?? '');
                $reviewer = is_array($item['reviewer'] ?? null) ? $item['reviewer'] : [];
                $authorName = trim((string) ($reviewer['displayName'] ?? ''));
                if ($authorName === '') {
                    $authorName = 'Autor';
                }
                $authorPhotoUrl = trim((string) ($reviewer['profilePhotoUrl'] ?? ($reviewer['profilePhotoUri'] ?? '')));

                $publishedAt = $this->normalizeIsoDateTime($item['createTime'] ?? ($item['updateTime'] ?? null));
                $language = trim((string) ($item['languageCode'] ?? ''));

                $reply = is_array($item['reviewReply'] ?? null) ? $item['reviewReply'] : [];
                $ownerResponseText = trim((string) ($reply['comment'] ?? ''));
                $ownerResponseAt = $this->normalizeIsoDateTime($reply['updateTime'] ?? null);

                $meta = [
                    'source' => 'gbp',
                    'review_name' => $reviewName,
                ];
                if ($authorPhotoUrl !== '') {
                    $meta['author_photo_url'] = $authorPhotoUrl;
                }

                $record = [
                    'team_id' => $teamId,
                    'account_id' => $accountId,
                    'place_id' => $placeId !== '' ? $placeId : null,
                    'external_id' => $reviewName,
                    'author_name' => $authorName,
                    'author_url' => null,
                    'rating' => $rating,
                    'text' => $comment,
                    'owner_response_text' => $ownerResponseText !== '' ? $ownerResponseText : null,
                    'owner_response_at' => $ownerResponseAt,
                    'language' => $language !== '' ? $language : null,
                    'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                    'published_at' => $publishedAt,
                    'updated' => $now,
                ];

                $unique = [
                    'team_id' => $teamId,
                    'external_id' => $reviewName,
                ];

                try {
                    $existing = $reviewModel->newQuery()->where($unique)->first();
                    if ($existing) {
                        $compareFields = [
                            'account_id',
                            'place_id',
                            'author_name',
                            'author_url',
                            'rating',
                            'text',
                            'owner_response_text',
                            'owner_response_at',
                            'language',
                            'meta',
                            'published_at',
                        ];

                        $changed = false;
                        foreach ($compareFields as $field) {
                            $oldValue = $existing->getAttribute($field);
                            $newValue = $record[$field] ?? null;

                            if ($field === 'rating') {
                                $oldValue = (int) $oldValue;
                                $newValue = (int) $newValue;
                            } elseif ($field === 'owner_response_at' || $field === 'published_at') {
                                $oldValue = $oldValue instanceof \DateTimeInterface ? $oldValue->format('Y-m-d H:i:s') : (is_string($oldValue) ? trim($oldValue) : $oldValue);
                                $newValue = is_string($newValue) ? trim($newValue) : $newValue;
                                $oldValue = $oldValue === '' ? null : $oldValue;
                                $newValue = $newValue === '' ? null : $newValue;
                            } else {
                                $oldValue = is_string($oldValue) ? trim($oldValue) : $oldValue;
                                $newValue = is_string($newValue) ? trim($newValue) : $newValue;
                                $oldValue = $oldValue === '' ? null : $oldValue;
                                $newValue = $newValue === '' ? null : $newValue;
                            }

                            if ($oldValue != $newValue) {
                                $changed = true;
                                break;
                            }
                        }

                        if (! $changed) {
                            $ignored++;

                            continue;
                        }
                    }

                    $review = $reviewModel->newQuery()->updateOrCreate($unique, $record);
                    if ($review->wasRecentlyCreated) {
                        $review->setAttribute('created', $now);
                        $review->save();
                        $inserted++;
                    } else {
                        $updated++;
                    }
                } catch (QueryException $e) {
                    $isDuplicate = ((int) ($e->errorInfo[1] ?? 0)) === 1062;
                    if ($isDuplicate) {
                        try {
                            $updatedRows = DB::table('analytee_reviews')->where($unique)->update($record);
                            if ((int) $updatedRows > 0) {
                                $updated++;
                            } else {
                                $ignored++;
                            }
                        } catch (\Throwable $inner) {
                            $errors++;
                            Log::error('Analytee GBP sync: failed to recover after duplicate key', [
                                'team_id' => $teamId,
                                'account_id' => $accountId,
                                'external_id' => $reviewName,
                                'exception' => get_class($inner),
                                'error' => $inner->getMessage(),
                            ]);
                        }

                        continue;
                    }

                    $errors++;
                    Log::error('Analytee GBP sync: database error saving review', [
                        'team_id' => $teamId,
                        'account_id' => $accountId,
                        'external_id' => $reviewName,
                        'sql_state' => $e->errorInfo[0] ?? null,
                        'driver_code' => $e->errorInfo[1] ?? null,
                        'driver_message' => $e->errorInfo[2] ?? null,
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                } catch (\Throwable $e) {
                    $errors++;
                    Log::error('Analytee GBP sync: unexpected error saving review', [
                        'team_id' => $teamId,
                        'account_id' => $accountId,
                        'external_id' => $reviewName,
                        'exception' => get_class($e),
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                }
            }

            $fetched += count($items);
            $pageToken = isset($data['nextPageToken']) ? (string) $data['nextPageToken'] : null;

            if ($pageToken === null || trim($pageToken) === '' || count($items) === 0) {
                break;
            }
        }

        $placesSaved = 0;
        if ($placeId !== '') {
            try {
                $placesResult = app(PlacesReviewsService::class)->syncPlaceReviews($teamId, $accountId, $placeId);
                if (($placesResult['status'] ?? 0) === 1) {
                    $placesSaved = (int) ($placesResult['reviews_saved'] ?? 0);
                }
            } catch (\Throwable $e) {
                Log::warning('Analytee Places sync failed', [
                    'team_id' => $teamId,
                    'account_id' => $accountId,
                    'place_id' => $placeId,
                    'exception' => get_class($e),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        DB::table('analytee_accounts')->updateOrInsert(
            ['team_id' => $teamId, 'account_id' => $accountId],
            [
                'place_id' => $placeId !== '' ? $placeId : null,
                'url' => (string) ($account->url ?? ''),
                'status' => 'connected',
                'last_sync_at' => Carbon::now()->toDateTimeString(),
                'updated' => time(),
                'created' => time(),
            ]
        );

        Log::info('Analytee GBP sync completed', [
            'team_id' => $teamId,
            'account_id' => $accountId,
            'reviews_saved' => $inserted + $updated,
            'reviews_inserted' => $inserted,
            'reviews_updated' => $updated,
            'reviews_ignored' => $ignored,
            'review_errors' => $errors,
            'reviews_fetched' => $fetched,
            'places_saved' => $placesSaved,
        ]);
    }

    private function extractPlaceIdFromUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if (is_array($parts) && isset($parts['query']) && is_string($parts['query'])) {
            $qs = [];
            parse_str($parts['query'], $qs);
            foreach (['place_id', 'query_place_id', 'placeId', 'placeid'] as $key) {
                $candidate = trim((string) ($qs[$key] ?? ''));
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        if (preg_match('/(?:\?|&)(?:place_id|query_place_id)=([^&]+)/i', $url, $m)) {
            return trim(urldecode((string) ($m[1] ?? '')));
        }

        return '';
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

        if (preg_match('/(?:\\?|&)cid=([^&]+)/i', $url, $m)) {
            return trim(urldecode((string) ($m[1] ?? '')));
        }

        return '';
    }

    private function tokenHasGbpScope(mixed $token): bool
    {
        $tokenArr = is_string($token) ? (json_decode($token, true) ?: []) : (is_array($token) ? $token : []);
        $scope = trim((string) ($tokenArr['scope'] ?? ''));
        if ($scope === '') {
            return false;
        }

        $parts = preg_split('/\s+/', $scope) ?: [];
        $parts = array_values(array_unique(array_filter(array_map('trim', $parts), fn ($v) => $v !== '')));
        $required = [
            'https://www.googleapis.com/auth/business.manage',
            'https://www.googleapis.com/auth/business.manage.',
        ];

        foreach ($required as $r) {
            if (in_array($r, $parts, true)) {
                return true;
            }
        }

        return false;
    }

    private function markAccountNeedsReauth(Accounts $account, string $reason): void
    {
        $data = is_array($account->data) ? $account->data : (json_decode((string) ($account->data ?? ''), true) ?: []);
        $data['needs_reauth'] = 1;
        $data['needs_reauth_reason'] = $reason;

        Accounts::where('id', $account->id)->update([
            'status' => 2,
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function listReviewsWithRetry(int $teamId, int $accountId, Accounts $account, ?string $pageToken, int $pageSize): array
    {
        $maxRetries = 5;
        $baseDelayMs = 500;

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            $resp = GbpPost::listReviews($account, $pageToken, $pageSize);
            if (($resp['status'] ?? 0) === 1) {
                return $resp;
            }

            $httpStatus = (int) ($resp['http_status'] ?? 0);
            $isRetryable = $httpStatus === 429 || $httpStatus >= 500;
            if (! $isRetryable) {
                return $resp;
            }

            $delay = $baseDelayMs * (2 ** $attempt);
            $jitter = (int) round($delay * (mt_rand(-20, 20) / 100));
            $finalDelayMs = max(0, $delay + $jitter);
            usleep($finalDelayMs * 1000);
        }

        DB::table('analytee_accounts')->updateOrInsert(
            ['team_id' => $teamId, 'account_id' => $accountId],
            [
                'status' => 'error_rate_limited',
                'updated' => time(),
            ]
        );

        return [
            'status' => 0,
            'message' => 'Temporalmente limitado por cuota (GBP). Reintenta más tarde.',
            'http_status' => 429,
        ];
    }

    private function normalizeGbpRating(mixed $value): int
    {
        if (is_numeric($value)) {
            $v = (int) $value;

            return max(0, min(5, $v));
        }

        $map = [
            'ONE' => 1,
            'TWO' => 2,
            'THREE' => 3,
            'FOUR' => 4,
            'FIVE' => 5,
        ];

        $key = strtoupper(trim((string) $value));

        return $map[$key] ?? 0;
    }

    private function normalizeIsoDateTime(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }
}
