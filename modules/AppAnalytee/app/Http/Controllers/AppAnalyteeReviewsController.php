<?php

namespace Modules\AppAnalytee\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\AdminAnalytee\Models\AnalyteePrompt;
use Modules\AppAnalytee\Jobs\RunAnalyticsEngineGoldenJsonJob;
use Modules\AppAnalytee\Services\GoldenJsonV1Reader;
use Modules\AppAnalytee\Services\GoldenJsonV1Serializer;
use Modules\AppAnalytee\Services\PlacesReviewsService;
use Modules\AppChannelGBPLocations\Facades\Post as GbpPost;
use Modules\AppChannels\Models\Accounts;

class AppAnalyteeReviewsController extends Controller
{
    private function extractPlaceIdFromUrl($url): string
    {
        $url = trim((string) $url);
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

    private function extractCidFromUrl($url): string
    {
        $url = trim((string) $url);
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

    private function resolveGbpAccountForAnalyteeAccountId(int $teamId, int $analyteeAccountId): array
    {
        $row = DB::table('analytee_accounts')
            ->where('team_id', $teamId)
            ->where('account_id', $analyteeAccountId)
            ->first(['place_id']);

        $placeId = $row ? trim((string) ($row->place_id ?? '')) : '';

        $account = null;
        if ($placeId !== '') {
            $candidates = Accounts::query()
                ->byTeam($teamId)
                ->where('social_network', 'google_business_profile')
                ->where('category', 'location')
                ->where('status', '!=', 0)
                ->get([
                    'id',
                    'team_id',
                    'pid',
                    'name',
                    'avatar',
                    'url',
                    'data',
                    'status',
                    'token',
                    'reconnect_url',
                ]);

            foreach ($candidates as $candidate) {
                $data = is_array($candidate->data ?? null) ? $candidate->data : (json_decode((string) ($candidate->data ?? ''), true) ?: []);
                $candidatePlaceId = trim((string) ($data['place_id'] ?? ''));
                if ($candidatePlaceId === $placeId) {
                    $account = $candidate;
                    break;
                }
            }
        }

        return [
            'exists' => (bool) $row,
            'place_id' => $placeId,
            'account' => $account,
        ];
    }

    public function index(Request $request, int $account_id)
    {
        \Access::check('appanalytee', true, true);

        $teamId = (int) $request->team_id;
        $accountId = (int) $account_id;

        $resolved = $this->resolveGbpAccountForAnalyteeAccountId($teamId, $accountId);
        if (! ($resolved['exists'] ?? false)) {
            $reviews = new LengthAwarePaginator([], 0, 10, 1, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);

            return view('appanalytee::reviews.index', [
                'team' => $request->team,
                'user' => auth()->user(),
                'account' => null,
                'accountId' => $accountId,
                'totalAll' => 0,
                'totalFiltered' => 0,
                'avgRating' => 0,
                'ratingCounts' => collect(),
                'languageCounts' => collect(),
                'reviews' => $reviews,
                'filters' => $this->filtersFromRequest($request),
                'placeId' => null,
                'accessDenied' => true,
                'datasetPrepared' => false,
                'analysisNotExecuted' => true,
                'golden' => null,
                'reportFiles' => [],
                'address' => null,
                'category' => null,
                'reviewsCount' => null,
                'totalScore' => null,
            ]);
        }

        $account = $resolved['account'] ?? null;
        $placeId = trim((string) ($resolved['place_id'] ?? ''));

        if ($account) {
            $accountData = is_array($account->data ?? null) ? $account->data : (json_decode((string) ($account->data ?? ''), true) ?: []);
            $accountPlaceId = trim((string) ($accountData['place_id'] ?? ''));
            if ($placeId === '' && $accountPlaceId !== '') {
                $placeId = $accountPlaceId;
            }

            if ($placeId === '') {
                $placeId = $this->extractPlaceIdFromUrl($account->url ?? '');
                if ($placeId !== '' && str_starts_with($placeId, 'ChIJ')) {
                    $accountData['place_id'] = $placeId;
                    Accounts::where('id', $account->id)->update([
                        'data' => json_encode($accountData, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            }

            if ($placeId === '' && $this->extractCidFromUrl($account->url ?? '') !== '') {
                $placesResolved = app(PlacesReviewsService::class)->resolvePlaceIdFromCidUrl((string) ($account->url ?? ''), (string) ($account->name ?? ''));
                if (($placesResolved['status'] ?? 0) === 1) {
                    $candidate = trim((string) ($placesResolved['place_id'] ?? ''));
                    if ($candidate !== '' && str_starts_with($candidate, 'ChIJ')) {
                        $placeId = $candidate;
                        $accountData['place_id'] = $placeId;
                        Accounts::where('id', $account->id)->update([
                            'data' => json_encode($accountData, JSON_UNESCAPED_UNICODE),
                        ]);
                    }
                }
            }
        }

        if ($placeId !== '') {
            DB::table('analytee_accounts')
                ->where('team_id', $teamId)
                ->where('account_id', $accountId)
                ->update([
                    'place_id' => $placeId,
                    'updated' => time(),
                ]);
        }

        $filters = $this->filtersFromRequest($request);

        $analyteeAccount = DB::table('analytee_accounts')
            ->where('team_id', $teamId)
            ->where('account_id', $accountId)
            ->first([
                'place_id',
                'rating',
                'user_ratings_total',
                'website',
                'types',
                'vicinity',
                'status',
                'updated',
            ]);

        $address = null;
        $category = null;
        if ($analyteeAccount) {
            $addressRaw = trim((string) ($analyteeAccount->vicinity ?? ''));
            $address = $addressRaw !== '' ? $addressRaw : null;

            $types = json_decode((string) ($analyteeAccount->types ?? '[]'), true);
            $types = is_array($types) ? $types : [];
            $categoryRaw = trim((string) ($types[0] ?? ''));
            $category = $categoryRaw !== '' ? $categoryRaw : null;
        }

        $limit = (int) ($filters['limit'] ?? 10);

        $baseQuery = DB::table('analytee_reviews')
            ->where('team_id', $teamId)
            ->where('account_id', $accountId)
            ->whereBetween('rating', [1, 5])
            ->whereNotNull('published_at');

        $this->applyGbpSourceFilter($baseQuery);
        $this->applyExternalIdFilter($baseQuery);

        if ($placeId !== '') {
            $baseQuery->where('place_id', $placeId);
        }

        $totalAllDb = (int) (clone $baseQuery)->count();
        $totalAll = $analyteeAccount && is_numeric($analyteeAccount->user_ratings_total ?? null)
            ? (int) $analyteeAccount->user_ratings_total
            : $totalAllDb;

        $avgRating = $analyteeAccount && is_numeric($analyteeAccount->rating ?? null)
            ? (float) $analyteeAccount->rating
            : null;

        if (! is_numeric($avgRating) && $totalAllDb > 0) {
            $avgRating = (float) (clone $baseQuery)->avg('rating');
        }

        $ratingCounts = (clone $baseQuery)
            ->select('rating', DB::raw('count(*) as c'))
            ->groupBy('rating')
            ->pluck('c', 'rating');

        $languageCounts = (clone $baseQuery)
            ->whereNotNull('language')
            ->where('language', '!=', '')
            ->select('language', DB::raw('count(*) as c'))
            ->groupBy('language')
            ->pluck('c', 'language');

        $filteredQuery = clone $baseQuery;
        $this->applyFilters($filteredQuery, $filters);
        $filteredCount = (int) (clone $filteredQuery)->count();
        $totalFiltered = $filteredCount;

        $perPage = 10;
        $page = max(1, (int) $request->query('page', 1));
        $offset = ($page - 1) * $perPage;

        $items = [];
        if ($offset < $totalFiltered) {
            $take = min($perPage, $totalFiltered - $offset);
            $rows = (clone $filteredQuery)
                ->orderByDesc('published_at')
                ->offset($offset)
                ->limit($take)
                ->get([
                    'external_id',
                    'author_name',
                    'rating',
                    'text',
                    'owner_response_text',
                    'published_at',
                    'language',
                    'meta',
                ]);

            $items = $rows->map(function ($r): array {
                $avatarUrl = null;
                $metaRaw = $r->meta ?? null;
                if (is_string($metaRaw) && trim($metaRaw) !== '') {
                    $decoded = json_decode($metaRaw, true);
                    if (is_array($decoded)) {
                        $candidate = $decoded['author_photo_url'] ?? ($decoded['authorPhotoUrl'] ?? null);
                        $candidate = is_string($candidate) ? trim($candidate) : null;
                        $avatarUrl = $candidate !== '' ? $candidate : null;
                    }
                }

                return [
                    'external_id' => (string) ($r->external_id ?? ''),
                    'author' => (string) ($r->author_name ?? ''),
                    'avatar_url' => $avatarUrl,
                    'rating' => (int) ($r->rating ?? 0),
                    'date' => (string) ($r->published_at ?? ''),
                    'text' => (string) ($r->text ?? ''),
                    'responseFromOwnerText' => $r->owner_response_text,
                    'language' => (string) ($r->language ?? ''),
                ];
            })->all();
        }

        $reviews = new LengthAwarePaginator($items, $totalFiltered, $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        $relativeDir = "analytee/exports/{$teamId}/{$accountId}";
        $datasetPrepared = Storage::disk('local')->exists("{$relativeDir}/input.json");
        $analysisNotExecuted = ! Storage::disk('local')->exists("{$relativeDir}/last_run.json");
        $analysisArtifacts = [
            'input' => "{$relativeDir}/input.json",
            'last_run' => "{$relativeDir}/last_run.json",
            'engine_stdout' => "{$relativeDir}/engine_stdout.log",
            'engine_stderr' => "{$relativeDir}/engine_stderr.log",
        ];
        $analysisArtifactsExist = [];
        foreach ($analysisArtifacts as $k => $p) {
            $analysisArtifactsExist[$k] = Storage::disk('local')->exists($p);
        }

        $analysisStatus = $analyteeAccount ? trim((string) ($analyteeAccount->status ?? '')) : '';
        $analysisStatus = $analysisStatus !== '' ? $analysisStatus : null;

        return view('appanalytee::reviews.index', [
            'team' => $request->team,
            'user' => auth()->user(),
            'account' => $account,
            'accountId' => $accountId,
            'totalAll' => $totalAll,
            'totalFiltered' => $totalFiltered,
            'avgRating' => $avgRating,
            'ratingCounts' => $ratingCounts,
            'languageCounts' => $languageCounts,
            'reviews' => $reviews,
            'filters' => $filters,
            'placeId' => $placeId !== '' ? $placeId : null,
            'accessDenied' => false,
            'datasetPrepared' => $datasetPrepared,
            'analysisNotExecuted' => $analysisNotExecuted,
            'analysisStatus' => $analysisStatus,
            'analysisArtifacts' => $analysisArtifacts,
            'analysisArtifactsExist' => $analysisArtifactsExist,
            'golden' => null,
            'reportFiles' => $this->readLastRunReportFiles($teamId, $accountId),
            'address' => $address,
            'category' => $category,
            'reviewsCount' => $totalAll,
            'totalScore' => $avgRating,
        ]);
    }

    public function report(Request $request, int $account_id, string $kind)
    {
        \Access::check('appanalytee', true, true);

        $teamId = (int) $request->team_id;
        $accountId = (int) $account_id;
        $kind = trim((string) $kind);

        if ($teamId <= 0 || $accountId <= 0) {
            abort(404);
        }

        $reports = $this->readLastRunReportFiles($teamId, $accountId);

        $key = match ($kind) {
            'pdf' => 'pdf',
            'docx' => 'docx',
            'analysis' => 'analysis',
            'log' => 'execution_log',
            'input' => 'input',
            'last-run' => 'last_run',
            'engine-stdout' => 'engine_stdout',
            'engine-stderr' => 'engine_stderr',
            default => null,
        };

        if (! $key) {
            abort(404);
        }

        $relativePath = (string) ($reports[$key] ?? '');
        if ($relativePath === '' && in_array($key, ['input', 'last_run', 'engine_stdout', 'engine_stderr'], true)) {
            $relativeDir = "analytee/exports/{$teamId}/{$accountId}";
            $relativePath = match ($key) {
                'input' => "{$relativeDir}/input.json",
                'last_run' => "{$relativeDir}/last_run.json",
                'engine_stdout' => "{$relativeDir}/engine_stdout.log",
                'engine_stderr' => "{$relativeDir}/engine_stderr.log",
                default => '',
            };
        }

        if ($relativePath === '' || ! Storage::disk('local')->exists($relativePath)) {
            abort(404);
        }

        return Storage::disk('local')->download($relativePath, basename($relativePath));
    }

    public function stats(Request $request, int $account_id)
    {
        \Access::check('appanalytee', true, true);

        $teamId = (int) $request->team_id;
        $accountId = (int) $account_id;

        $resolved = $this->resolveGbpAccountForAnalyteeAccountId($teamId, $accountId);
        if (! ($resolved['exists'] ?? false)) {
            return response()->json(['status' => 0, 'message' => 'Negocio no disponible'], 404);
        }

        $account = $resolved['account'] ?? null;
        $placeId = trim((string) ($resolved['place_id'] ?? ''));

        if ($account) {
            $accountData = is_array($account->data ?? null) ? $account->data : (json_decode((string) ($account->data ?? ''), true) ?: []);
            $accountPlaceId = trim((string) ($accountData['place_id'] ?? ''));
            if ($placeId === '' && $accountPlaceId !== '') {
                $placeId = $accountPlaceId;
            }

            if ($placeId === '') {
                $placeId = $this->extractPlaceIdFromUrl($account->url ?? '');
                if ($placeId !== '' && str_starts_with($placeId, 'ChIJ')) {
                    $accountData['place_id'] = $placeId;
                    Accounts::where('id', $account->id)->update([
                        'data' => json_encode($accountData, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            }

            if ($placeId === '' && $this->extractCidFromUrl($account->url ?? '') !== '') {
                $placesResolved = app(PlacesReviewsService::class)->resolvePlaceIdFromCidUrl((string) ($account->url ?? ''), (string) ($account->name ?? ''));
                if (($placesResolved['status'] ?? 0) === 1) {
                    $candidate = trim((string) ($placesResolved['place_id'] ?? ''));
                    if ($candidate !== '' && str_starts_with($candidate, 'ChIJ')) {
                        $placeId = $candidate;
                        $accountData['place_id'] = $placeId;
                        Accounts::where('id', $account->id)->update([
                            'data' => json_encode($accountData, JSON_UNESCAPED_UNICODE),
                        ]);
                    }
                }
            }
        }

        if ($placeId !== '') {
            DB::table('analytee_accounts')
                ->where('team_id', $teamId)
                ->where('account_id', $accountId)
                ->update([
                    'place_id' => $placeId,
                    'updated' => time(),
                ]);
        }

        $filters = $this->filtersFromRequest($request);

        $filteredQuery = DB::table('analytee_reviews')
            ->where('team_id', $teamId)
            ->where('account_id', $accountId)
            ->whereBetween('rating', [1, 5])
            ->whereNotNull('published_at');

        $this->applyGbpSourceFilter($filteredQuery);
        $this->applyExternalIdFilter($filteredQuery);

        if ($placeId !== '') {
            $filteredQuery->where('place_id', $placeId);
        }

        $this->applyFilters($filteredQuery, $filters);

        $row = (clone $filteredQuery)->selectRaw(
            "COUNT(*) as total,
            SUM(CASE WHEN rating IN (4,5) THEN 1 ELSE 0 END) as positive,
            SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as neutral,
            SUM(CASE WHEN rating IN (1,2) THEN 1 ELSE 0 END) as negative,
            SUM(CASE WHEN owner_response_text IS NOT NULL AND TRIM(owner_response_text) <> '' THEN 1 ELSE 0 END) as with_reply,
            SUM(CASE WHEN owner_response_text IS NULL OR TRIM(owner_response_text) = '' THEN 1 ELSE 0 END) as without_reply"
        )->first();

        $total = (int) ($row->total ?? 0);
        $positive = (int) ($row->positive ?? 0);
        $neutral = (int) ($row->neutral ?? 0);
        $negative = (int) ($row->negative ?? 0);
        $withReply = (int) ($row->with_reply ?? 0);
        $withoutReply = (int) ($row->without_reply ?? 0);

        $pct = function (int $count) use ($total): int {
            if ($total <= 0) {
                return 0;
            }

            return (int) round(($count / $total) * 100);
        };

        return response()->json([
            'status' => 1,
            'data' => [
                'total' => $total,
                'sentiment' => [
                    'positive' => ['count' => $positive, 'percent' => $pct($positive)],
                    'neutral' => ['count' => $neutral, 'percent' => $pct($neutral)],
                    'negative' => ['count' => $negative, 'percent' => $pct($negative)],
                ],
                'reply' => [
                    'with' => ['count' => $withReply, 'percent' => $pct($withReply)],
                    'without' => ['count' => $withoutReply, 'percent' => $pct($withoutReply)],
                ],
            ],
        ]);
    }

    private function readLastRunReportFiles(int $teamId, int $accountId): array
    {
        $relativeDir = "analytee/exports/{$teamId}/{$accountId}";
        $relativeLastRun = "{$relativeDir}/last_run.json";
        $abs = storage_path('app/'.str_replace('/', DIRECTORY_SEPARATOR, $relativeLastRun));

        if (! is_file($abs)) {
            return [];
        }

        $raw = (string) @file_get_contents($abs);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        $stored = $decoded['stored_reports'] ?? null;
        if (! is_array($stored)) {
            return [];
        }

        $files = $stored['files'] ?? null;
        if (! is_array($files)) {
            return [];
        }

        $out = [];
        foreach (['pdf', 'docx', 'analysis', 'execution_log'] as $k) {
            $v = $files[$k] ?? null;
            if (is_string($v) && trim($v) !== '') {
                $out[$k] = trim($v);
            }
        }

        return $out;
    }

    public function prepareInput(Request $request, int $account_id)
    {
        \Access::check('appanalytee', true, true);

        $teamId = (int) $request->team_id;
        $accountId = (int) $account_id;

        $resolved = $this->resolveGbpAccountForAnalyteeAccountId($teamId, $accountId);
        if (! ($resolved['exists'] ?? false)) {
            return redirect()->route('app.analytee.index')->with('error', 'Negocio no disponible');
        }

        $filters = $this->filtersFromRequest($request);
        $limit = (int) ($filters['limit'] ?? 10);

        $account = DB::table('analytee_accounts')
            ->where('team_id', $teamId)
            ->where('account_id', $accountId)
            ->first([
                'place_id',
                'rating',
                'user_ratings_total',
                'website',
                'types',
                'vicinity',
            ]);

        if (! $account) {
            return redirect()->route('app.analytee.reviews.index', ['account_id' => $accountId])->with('error', 'Cuenta Analytee no disponible');
        }

        $placeId = trim((string) ($account->place_id ?? ''));
        if ($placeId === '') {
            return redirect()->route('app.analytee.reviews.index', ['account_id' => $accountId])->with('error', 'place_id no disponible para preparar dataset');
        }

        $reviewsQuery = DB::table('analytee_reviews')
            ->where('team_id', $teamId)
            ->where('account_id', $accountId)
            ->where('place_id', $placeId)
            ->whereBetween('rating', [1, 5])
            ->whereNotNull('published_at');

        $this->applyGbpSourceFilter($reviewsQuery);
        $this->applyExternalIdFilter($reviewsQuery);

        $this->applyFilters($reviewsQuery, $filters);

        $rows = $reviewsQuery
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get([
                'external_id',
                'author_name',
                'rating',
                'text',
                'published_at',
                'owner_response_text',
                'meta',
            ]);

        $rawReviews = $rows->map(fn ($r) => (array) $r)->all();

        $serializer = new GoldenJsonV1Serializer;
        $payload = $serializer->build((array) $account, $rawReviews);

        $payload['dataset'] = [
            'generated_at' => Carbon::now()->toDateTimeString(),
            'team_id' => $teamId,
            'account_id' => $accountId,
            'place_id' => $placeId,
            'limit' => $limit,
            'filters' => [
                'period' => (string) ($filters['period'] ?? '30d'),
                'ratings' => array_values($filters['ratings'] ?? []),
                'languages' => array_values($filters['languages'] ?? []),
                'q' => (string) ($filters['q'] ?? ''),
            ],
            'external_ids' => array_values(array_filter(array_map(fn ($r) => (string) ($r['external_id'] ?? ''), $rawReviews), fn ($v) => $v !== '')),
        ];

        $jsonString = $serializer->encode($payload, pretty: true);
        $relativeInputPath = "analytee/exports/{$teamId}/{$accountId}/input.json";
        $written = Storage::disk('local')->put($relativeInputPath, $jsonString);
        if (! $written || ! Storage::disk('local')->exists($relativeInputPath)) {
            return redirect()->route('app.analytee.reviews.index', ['account_id' => $accountId])->with('error', 'No se pudo guardar input.json');
        }

        return redirect()->route('app.analytee.reviews.index', ['account_id' => $accountId])->with('success', 'Dataset preparado (input.json generado)');
    }

    public function runEngine(Request $request, int $account_id)
    {
        \Access::check('appanalytee', true, true);

        $teamId = (int) $request->team_id;
        $accountId = (int) $account_id;

        $resolved = $this->resolveGbpAccountForAnalyteeAccountId($teamId, $accountId);
        if (! ($resolved['exists'] ?? false)) {
            return redirect()->route('app.analytee.index')->with('error', 'Negocio no disponible');
        }

        $account = $resolved['account'] ?? null;
        $placeId = trim((string) ($resolved['place_id'] ?? ''));

        if ($placeId === '' && $account) {
            $accountData = is_array($account->data) ? $account->data : (json_decode((string) ($account->data ?? ''), true) ?: []);
            $placeId = trim((string) ($accountData['place_id'] ?? ''));
        }

        if ($placeId === '') {
            return redirect()->route('app.analytee.reviews.index', ['account_id' => $accountId])->with('error', 'place_id no disponible para ejecutar análisis');
        }

        $golden = app(GoldenJsonV1Reader::class)->read($teamId, $accountId);
        if (($golden['status'] ?? 0) !== 1) {
            return redirect()->route('app.analytee.reviews.index', ['account_id' => $accountId])->with('error', 'Primero prepara el dataset (input.json) antes de ejecutar el análisis');
        }

        $nowTs = time();
        DB::table('analytee_accounts')->updateOrInsert(
            ['team_id' => $teamId, 'account_id' => $accountId],
            [
                'place_id' => $placeId,
                'status' => 'analysis_queued',
                'updated' => $nowTs,
                'created' => $nowTs,
            ]
        );

        RunAnalyticsEngineGoldenJsonJob::dispatch($teamId, $accountId);

        return redirect()->route('app.analytee.reviews.index', ['account_id' => $accountId])->with('success', 'Análisis encolado');
    }

    public function sync(Request $request, int $account_id)
    {
        \Access::check('appanalytee', true, true);

        $teamId = (int) $request->team_id;
        $accountId = (int) $account_id;

        $resolved = $this->resolveGbpAccountForAnalyteeAccountId($teamId, $accountId);
        if (! ($resolved['exists'] ?? false)) {
            return redirect()->route('app.analytee.index')->with('error', 'Negocio no disponible');
        }

        $account = $resolved['account'] ?? null;
        if (! $account) {
            return redirect()->route('app.analytee.reviews.index', ['account_id' => $accountId])->with('error', 'Negocio no disponible');
        }

        if (! $this->tokenHasGbpScope($account->token)) {
            $this->markAccountNeedsReauth($account, 'missing_scope');

            return redirect()->route('app.analytee.reviews.index', ['account_id' => $accountId])->with('error', 'Reautorización requerida para leer y responder reseñas');
        }

        $accountData = is_array($account->data) ? $account->data : (json_decode((string) ($account->data ?? ''), true) ?: []);
        $placeId = trim((string) ($resolved['place_id'] ?? ''));
        if ($placeId === '') {
            $placeId = trim((string) ($accountData['place_id'] ?? ''));
        }

        if ($placeId === '') {
            $placeId = $this->extractPlaceIdFromUrl($account->url ?? '');
            if ($placeId !== '' && str_starts_with($placeId, 'ChIJ')) {
                $accountData['place_id'] = $placeId;
                Accounts::where('id', $account->id)->update([
                    'data' => json_encode($accountData, JSON_UNESCAPED_UNICODE),
                ]);
            }
        }

        if ($placeId === '') {
            $cid = $this->extractCidFromUrl($account->url ?? '');
            if ($cid !== '') {
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
        }

        if ($placeId === '') {
            Log::warning('Analytee GBP sync blocked: missing place_id', [
                'team_id' => $teamId,
                'account_id' => $accountId,
                'account_url' => (string) ($account->url ?? ''),
            ]);

            return redirect()->route('app.analytee.reviews.index', ['account_id' => $accountId])->with('error', 'place_id no disponible para sincronizar');
        }

        if (empty($account->pid)) {
            Log::warning('Analytee GBP sync blocked: missing pid', [
                'team_id' => $teamId,
                'account_id' => $accountId,
            ]);

            return redirect()->route('app.analytee.reviews.index', ['account_id' => $accountId])->with('error', 'PID de GBP no disponible');
        }

        try {
            Log::info('Analytee GBP sync started', [
                'team_id' => $teamId,
                'account_id' => $accountId,
                'place_id' => $placeId,
                'pid' => (string) ($account->pid ?? ''),
            ]);

            DB::connection()->getPdo();

            $gbpFetched = 0;
            $gbpPages = 0;
            $gbpInserted = 0;
            $gbpUpdated = 0;
            $gbpIgnored = 0;
            $gbpErrors = 0;

            $reviewModel = new class extends Model
            {
                protected $table = 'analytee_reviews';

                protected $guarded = [];

                public $timestamps = false;
            };

            $pageToken = null;
            $pageSize = 50;
            $maxPages = 20;

            while ($gbpPages < $maxPages) {
                $gbpPages++;
                $resp = $this->listReviewsWithRetry($teamId, $accountId, $account, $pageToken, $pageSize);
                if (($resp['status'] ?? 0) !== 1) {
                    return redirect()->route('app.analytee.reviews.index', ['account_id' => $accountId])->with('error', $resp['message'] ?? 'Error al sincronizar');
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

                    $reviewName = (string) ($item['name'] ?? '');
                    if (trim($reviewName) === '') {
                        continue;
                    }

                    $rating = $this->normalizeGbpRating($item['starRating'] ?? null);
                    $comment = (string) ($item['comment'] ?? '');

                    $reviewer = is_array($item['reviewer'] ?? null) ? $item['reviewer'] : [];
                    $authorName = (string) ($reviewer['displayName'] ?? '');
                    if ($authorName === '') {
                        $authorName = 'Autor';
                    }
                    $authorPhotoUrl = trim((string) ($reviewer['profilePhotoUrl'] ?? ($reviewer['profilePhotoUri'] ?? '')));

                    $publishedAt = $this->normalizeIsoDateTime($item['createTime'] ?? ($item['updateTime'] ?? null));
                    $language = (string) ($item['languageCode'] ?? '');

                    $reply = is_array($item['reviewReply'] ?? null) ? $item['reviewReply'] : [];
                    $ownerResponseText = (string) ($reply['comment'] ?? '');
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
                        'place_id' => $placeId,
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
                                $gbpIgnored++;

                                continue;
                            }
                        }

                        $review = $reviewModel->newQuery()->updateOrCreate($unique, $record);
                        if ($review->wasRecentlyCreated) {
                            $review->setAttribute('created', $now);
                            $review->save();
                            $gbpInserted++;
                        } else {
                            $gbpUpdated++;
                        }
                    } catch (QueryException $e) {
                        $isDuplicate = ((int) ($e->errorInfo[1] ?? 0)) === 1062;
                        if ($isDuplicate) {
                            try {
                                $updatedRows = DB::table('analytee_reviews')->where($unique)->update($record);
                                if ((int) $updatedRows > 0) {
                                    $gbpUpdated++;
                                } else {
                                    $gbpIgnored++;
                                }
                            } catch (\Throwable $inner) {
                                $gbpErrors++;
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

                        $gbpErrors++;
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
                        $gbpErrors++;
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

                $gbpFetched += count($items);
                $pageToken = isset($data['nextPageToken']) ? (string) $data['nextPageToken'] : null;

                if ($pageToken === null || trim($pageToken) === '' || count($items) === 0) {
                    break;
                }
            }

            $nowTs = time();
            DB::table('analytee_accounts')->updateOrInsert(
                ['team_id' => $teamId, 'account_id' => $accountId],
                [
                    'place_id' => $placeId,
                    'url' => (string) ($account->url ?? ''),
                    'status' => 'connected',
                    'last_sync_at' => Carbon::now()->toDateTimeString(),
                    'updated' => $nowTs,
                    'created' => $nowTs,
                ]
            );

            $placesSaved = 0;
            $placesMessage = null;
            if ($placeId !== '') {
                try {
                    $placesResult = app(PlacesReviewsService::class)->syncPlaceReviews($teamId, $accountId, $placeId);
                    if (($placesResult['status'] ?? 0) === 1) {
                        $placesSaved = (int) ($placesResult['reviews_saved'] ?? 0);
                    } else {
                        $placesMessage = (string) ($placesResult['message'] ?? '');
                    }
                } catch (\Throwable $e) {
                    $placesMessage = 'Error sincronizando reseñas de Places';

                    Log::warning('Analytee Places sync failed', [
                        'team_id' => $teamId,
                        'account_id' => $accountId,
                        'place_id' => $placeId,
                        'exception' => get_class($e),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $gbpSaved = $gbpInserted + $gbpUpdated;
            $message = "Sincronización completada (GBP): insertadas {$gbpInserted}, actualizadas {$gbpUpdated}, ignoradas {$gbpIgnored}, errores {$gbpErrors} (guardadas: {$gbpSaved}, recibidas: {$gbpFetched}).";
            if ($placeId !== '') {
                $message .= " Places: {$placesSaved} reseñas de muestra (máx. 5).";
            }

            $redirect = redirect()->route('app.analytee.reviews.index', ['account_id' => $accountId])->with('success', $message);
            if ($placesMessage) {
                $redirect = $redirect->with('error', $placesMessage);
            }

            return $redirect;
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Database error during GBP sync', [
                'team_id' => $teamId,
                'account_id' => $accountId,
                'place_id' => $placeId,
                'pid' => (string) ($account->pid ?? ''),
                'connection' => $e->getConnectionName(),
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
                'sql_state' => $e->errorInfo[0] ?? null,
                'driver_code' => $e->errorInfo[1] ?? null,
                'driver_message' => $e->errorInfo[2] ?? null,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('app.analytee.reviews.index', ['account_id' => $accountId])->with('error', 'Error de base de datos durante la sincronización');
        } catch (\Throwable $e) {
            Log::error('Unexpected error during GBP sync', [
                'team_id' => $teamId,
                'account_id' => $accountId,
                'place_id' => $placeId,
                'pid' => (string) ($account->pid ?? ''),
                'exception' => get_class($e),
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('app.analytee.reviews.index', ['account_id' => $accountId])->with('error', 'Error inesperado durante la sincronización');
        }
    }

    public function reply(Request $request, int $account_id, string $external_id)
    {
        \Access::check('appanalytee', true, true);

        $teamId = (int) $request->team_id;
        $accountId = (int) $account_id;
        $externalIdRaw = trim((string) $external_id);
        $externalId = $this->decodeExternalId($externalIdRaw);

        $resolved = $this->resolveGbpAccountForAnalyteeAccountId($teamId, $accountId);
        if (! ($resolved['exists'] ?? false)) {
            return $this->replyError($request, 'Negocio no disponible');
        }

        $account = $resolved['account'] ?? null;
        if (! $account) {
            return $this->replyError($request, 'Negocio no disponible');
        }

        if (! $this->tokenHasGbpScope($account->token)) {
            $this->markAccountNeedsReauth($account, 'missing_scope');

            return $this->replyError($request, 'Reautorización requerida para responder reseñas');
        }

        $review = DB::table('analytee_reviews')
            ->where('team_id', $teamId)
            ->where('account_id', $accountId)
            ->where('external_id', $externalId)
            ->first();

        if (! $review && $externalIdRaw !== $externalId) {
            $review = DB::table('analytee_reviews')
                ->where('team_id', $teamId)
                ->where('account_id', $accountId)
                ->where('external_id', $externalIdRaw)
                ->first();
            $externalId = $review ? $externalIdRaw : $externalId;
        }

        if (! $review) {
            return $this->replyError($request, 'Reseña no disponible');
        }

        $replyText = trim((string) ($request->input('reply') ?? $request->input('text') ?? ''));
        if ($replyText === '') {
            return $this->replyError($request, 'La respuesta no puede estar vacía');
        }

        $resp = $this->updateReplyWithRetry($teamId, $accountId, $account, $externalId, $replyText);
        if (($resp['status'] ?? 0) !== 1) {
            return $this->replyError($request, $resp['message'] ?? 'Error al publicar la respuesta');
        }

        Log::info('Analytee GBP reply published', [
            'team_id' => $teamId,
            'account_id' => $accountId,
            'review_name' => $externalId,
            'endpoint' => $resp['endpoint'] ?? null,
            'http_status' => $resp['http_status'] ?? null,
        ]);

        $nowTs = time();
        $nowDt = Carbon::now()->toDateTimeString();
        DB::table('analytee_reviews')
            ->where('id', $review->id)
            ->update([
                'owner_response_text' => $replyText,
                'owner_response_at' => $nowDt,
                'updated' => $nowTs,
            ]);

        if ($request->expectsJson()) {
            return response()->json(['status' => 1]);
        }

        return redirect()->route('app.analytee.reviews.index', ['account_id' => $accountId])->with('success', 'Respuesta publicada');
    }

    public function aiReplyAvailable(Request $request)
    {
        \Access::check('appanalytee', true, true);

        $available = false;
        try {
            $available = AnalyteePrompt::query()
                ->where('key', 'review_reply_default')
                ->where('is_active', 1)
                ->exists();
        } catch (\Throwable) {
            $available = false;
        }

        return response()->json([
            'status' => 1,
            'available' => $available ? 1 : 0,
        ]);
    }

    public function aiReply(Request $request, int $id)
    {
        \Access::check('appanalytee', true, true);

        $teamId = (int) $request->team_id;

        $review = DB::table('analytee_reviews')
            ->where('team_id', $teamId)
            ->where('id', $id)
            ->first();

        if (! $review) {
            return response()->json(['status' => 0, 'message' => 'Reseña no disponible'], 404);
        }

        try {
            $prompt = AnalyteePrompt::query()
                ->where('key', 'review_reply_default')
                ->where('is_active', 1)
                ->first();
        } catch (\Throwable) {
            $prompt = null;
        }

        if (! $prompt) {
            return response()->json(['status' => 0, 'message' => 'Prompt no disponible'], 404);
        }

        $account = Accounts::query()
            ->byTeam($teamId)
            ->where('id', (int) ($review->account_id ?? 0))
            ->first();

        $finalPrompt = (string) ($prompt->prompt ?? '');
        $finalPrompt = str_replace('{{review_text}}', (string) ($review->text ?? ''), $finalPrompt);
        $finalPrompt = str_replace('{{business_name}}', (string) ($account->name ?? ''), $finalPrompt);
        $finalPrompt = str_replace('{{language}}', (string) ($review->language ?? ''), $finalPrompt);

        try {
            $ai = \AI::process($finalPrompt, 'text', [], $teamId);
        } catch (\Throwable $e) {
            return response()->json(['status' => 0, 'message' => 'No se pudo generar la respuesta'], 500);
        }

        $text = trim((string) (($ai['data'][0] ?? '') ?: ''));
        if ($text === '' || ! empty($ai['error'])) {
            return response()->json(['status' => 0, 'message' => 'No se pudo generar la respuesta'], 500);
        }

        return response()->json([
            'status' => 1,
            'text' => $text,
        ]);
    }

    private function replyError(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['status' => 0, 'message' => $message], 400);
        }

        return redirect()->route('app.analytee.reviews.index', ['account_id' => (int) $request->route('account_id')])->with('error', $message);
    }

    private function decodeExternalId(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '';
        }

        $base64 = strtr($raw, '-_', '+/');
        $padLen = (4 - (strlen($base64) % 4)) % 4;
        if ($padLen > 0) {
            $base64 .= str_repeat('=', $padLen);
        }

        $decoded = base64_decode($base64, true);
        if (! is_string($decoded) || $decoded === '') {
            return $raw;
        }

        return $decoded;
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
            try {
                $resp = GbpPost::listReviews($account, $pageToken, $pageSize);
                if (($resp['status'] ?? 0) === 1) {
                    return $resp;
                }

                $httpStatus = (int) ($resp['http_status'] ?? 0);
                if ($httpStatus === 401 || $httpStatus === 403) {
                    $this->markAccountNeedsReauth($account, $httpStatus === 401 ? 'gbp_unauthorized' : 'gbp_forbidden');
                }

                if ($httpStatus > 0 && ($httpStatus < 500 && $httpStatus !== 429)) {
                    Log::warning('Analytee GBP listReviews non-retryable response', [
                        'team_id' => $teamId,
                        'account_id' => $accountId,
                        'attempt' => $attempt + 1,
                        'http_status' => $httpStatus,
                        'message' => $resp['message'] ?? null,
                        'endpoint' => rtrim((string) $account->pid, '/').'/reviews',
                    ]);
                }

                $isRetryable = $httpStatus === 429 || $httpStatus >= 500;
                if (! $isRetryable) {
                    return $resp;
                }
            } catch (\Throwable $e) {
                Log::error('Error during GBP review fetch attempt', [
                    'team_id' => $teamId,
                    'account_id' => $accountId,
                    'attempt' => $attempt + 1,
                    'endpoint' => rtrim((string) $account->pid, '/').'/reviews',
                    'exception' => get_class($e),
                    'error' => $e->getMessage(),
                ]);

                if ($attempt === $maxRetries - 1) {
                    return [
                        'status' => 0,
                        'message' => 'Error al obtener reseñas de GBP después de múltiples intentos',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            $delay = $baseDelayMs * (2 ** $attempt);
            $jitter = (int) round($delay * (mt_rand(-20, 20) / 100));
            $finalDelayMs = max(0, $delay + $jitter);
            usleep($finalDelayMs * 1000);
        }

        try {
            DB::table('analytee_accounts')->updateOrInsert(
                ['team_id' => $teamId, 'account_id' => $accountId],
                [
                    'status' => 'error_rate_limited',
                    'updated' => time(),
                ]
            );
        } catch (\Throwable) {
        }

        Log::warning('Analytee GBP rate limited', [
            'team_id' => $teamId,
            'account_id' => $accountId,
            'endpoint' => rtrim((string) $account->pid, '/').'/reviews',
        ]);

        return [
            'status' => 0,
            'message' => 'Temporalmente limitado por cuota (GBP). Reintenta más tarde.',
            'http_status' => 429,
        ];
    }

    private function updateReplyWithRetry(int $teamId, int $accountId, Accounts $account, string $reviewName, string $comment): array
    {
        $maxRetries = 5;
        $baseDelayMs = 500;

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            $resp = GbpPost::updateReviewReply($account, $reviewName, $comment);
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

        Log::warning('Analytee GBP rate limited (reply)', [
            'team_id' => $teamId,
            'account_id' => $accountId,
            'endpoint' => $reviewName.'/reply',
        ]);

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

    private function filtersFromRequest(Request $request): array
    {
        $period = (string) $request->input('period', $request->query('period', '30d'));
        $rating = $request->input('rating', $request->query('rating'));
        $language = $request->input('language', $request->query('language'));
        $q = trim((string) $request->input('q', $request->query('q', '')));

        $limitRaw = $request->input('limit', $request->query('limit', 10));
        $limit = (int) $limitRaw;
        $allowedLimits = [10, 50, 500, 2000];
        if (! in_array($limit, $allowedLimits, true)) {
            $limit = 10;
        }

        $ratings = $this->normalizeIntList($rating);
        $languages = $this->normalizeStringList($language);

        $periodStart = match ($period) {
            '30d' => Carbon::now()->subDays(30),
            '3m' => Carbon::now()->subMonths(3),
            '6m' => Carbon::now()->subMonths(6),
            '1y' => Carbon::now()->subYear(),
            'all' => null,
            default => Carbon::now()->subDays(30),
        };

        return [
            'period' => $period,
            'periodStart' => $periodStart,
            'ratings' => $ratings,
            'languages' => $languages,
            'q' => $q,
            'limit' => $limit,
        ];
    }

    private function applyFilters($query, array $filters): void
    {
        if (! empty($filters['ratings'])) {
            $query->whereIn('rating', $filters['ratings']);
        }

        if (! empty($filters['languages'])) {
            $query->whereIn('language', $filters['languages']);
        }

        if ($filters['periodStart'] instanceof Carbon) {
            $query->where('published_at', '>=', $filters['periodStart']->toDateTimeString());
        }

        if (! empty($filters['q'])) {
            $like = '%'.$filters['q'].'%';

            $query->where(function ($q) use ($like) {
                $q->where('author_name', 'like', $like)
                    ->orWhere('text', 'like', $like)
                    ->orWhere('external_id', 'like', $like);
            });
        }
    }

    private function applyGbpSourceFilter($query): void
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.source')) = 'gbp'");

            return;
        }

        if ($driver === 'pgsql') {
            $query->whereRaw("(meta->>'source') = 'gbp'");

            return;
        }

        $query->where('meta', 'like', '%"source":"gbp"%');
    }

    private function applyExternalIdFilter($query): void
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $query->where('external_id', 'not regexp', '^[0-9a-f]{64}$');

            return;
        }

        if ($driver === 'pgsql') {
            $query->whereRaw("external_id !~ '^[0-9a-f]{64}$'");
        }
    }

    private function normalizeIntList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_unique(array_filter(array_map('intval', $value), fn ($v) => $v > 0)));
        }

        if ($value === null || $value === '') {
            return [];
        }

        return [(int) $value];
    }

    private function normalizeStringList(mixed $value): array
    {
        if (is_array($value)) {
            $items = array_map('strval', $value);
        } elseif ($value === null || $value === '') {
            $items = [];
        } else {
            $items = [(string) $value];
        }

        $items = array_values(array_unique(array_filter(array_map('trim', $items), fn ($v) => $v !== '')));

        return $items;
    }
}
