<?php

namespace Modules\AppAnalytee\Services;

use Illuminate\Support\Facades\DB;
use Modules\AppChannels\Models\Accounts;

class AnalyteeService
{
    private function ensureAnalyteeAccountsForPlaces(int $teamId, array $placeIdToUrl): array
    {
        $placeIds = [];
        foreach ($placeIdToUrl as $placeId => $url) {
            $placeId = trim((string) $placeId);
            if ($placeId !== '') {
                $placeIds[$placeId] = $placeId;
            }
        }

        if (empty($placeIds)) {
            return [];
        }

        $now = time();

        return DB::transaction(function () use ($teamId, $placeIds, $placeIdToUrl, $now): array {
            $placeIdToAnalyteeAccountId = [];

            $existingRows = DB::table('analytee_accounts')
                ->where('team_id', (int) $teamId)
                ->whereIn('place_id', array_values($placeIds))
                ->get(['place_id', 'account_id']);

            foreach ($existingRows as $row) {
                $pid = trim((string) ($row->place_id ?? ''));
                $aid = (int) ($row->account_id ?? 0);
                if ($pid !== '' && $aid > 0) {
                    $placeIdToAnalyteeAccountId[$pid] = $aid;
                }
            }

            $maxAccountId = (int) (DB::table('analytee_accounts')
                ->where('team_id', (int) $teamId)
                ->max('account_id') ?? 0);

            $nextAccountId = $maxAccountId > 0 ? ($maxAccountId + 1) : 101;

            foreach ($placeIds as $placeId) {
                if (isset($placeIdToAnalyteeAccountId[$placeId])) {
                    continue;
                }

                $url = (string) ($placeIdToUrl[$placeId] ?? '');

                while (true) {
                    $inserted = DB::table('analytee_accounts')->insertOrIgnore([
                        'team_id' => (int) $teamId,
                        'account_id' => (int) $nextAccountId,
                        'place_id' => (string) $placeId,
                        'url' => $url,
                        'status' => 'linked',
                        'last_sync_at' => null,
                        'created' => $now,
                        'updated' => $now,
                    ]);

                    if ((int) $inserted === 1) {
                        break;
                    }

                    $nextAccountId++;
                }

                $accountId = (int) $nextAccountId;

                DB::table('analytee_accounts')
                    ->where('team_id', (int) $teamId)
                    ->where('place_id', (string) $placeId)
                    ->where('account_id', '!=', $accountId)
                    ->update([
                        'place_id' => null,
                        'updated' => $now,
                    ]);

                $placeIdToAnalyteeAccountId[$placeId] = $accountId;
                $nextAccountId++;
            }

            return $placeIdToAnalyteeAccountId;
        });
    }

    public function ensureAnalyteeAccountIdForPlace(int $teamId, string $placeId, string $url = ''): ?int
    {
        $placeId = trim((string) $placeId);
        if ($placeId === '') {
            return null;
        }

        $map = $this->ensureAnalyteeAccountsForPlaces($teamId, [$placeId => (string) $url]);

        $accountId = (int) ($map[$placeId] ?? 0);

        return $accountId > 0 ? $accountId : null;
    }

    public function getOverview($teamId): array
    {
        return [
            'total_reviews' => null,
            'average_rating' => null,
            'positive_rate' => null,
        ];
    }

    public function getRatingsDistribution($teamId): array
    {
        return [
            '1' => 0,
            '2' => 0,
            '3' => 0,
            '4' => 0,
            '5' => 0,
        ];
    }

    public function getTrends($teamId): array
    {
        return [
            'series' => [],
            'categories' => [],
        ];
    }

    public function getConnectedProfiles(int $teamId)
    {
        $profiles = Accounts::query()
            ->byTeam($teamId)
            ->where('social_network', 'google_business_profile')
            ->where('category', 'location')
            ->where('status', '!=', 0)
            ->orderBy('name')
            ->get([
                'id',
                'id_secure',
                'pid',
                'name',
                'avatar',
                'url',
                'data',
                'status',
                'changed',
                'created',
            ]);

        $placeIdToUrl = [];
        foreach ($profiles as $profile) {
            $data = is_array($profile->data ?? null) ? $profile->data : (json_decode((string) ($profile->data ?? ''), true) ?: []);
            $placeId = trim((string) ($data['place_id'] ?? ''));
            if ($placeId !== '') {
                $placeIdToUrl[$placeId] = (string) ($profile->url ?? '');
            }
        }

        $placeIdToAnalyteeAccountId = $this->ensureAnalyteeAccountsForPlaces($teamId, $placeIdToUrl);

        foreach ($profiles as $profile) {
            $data = is_array($profile->data ?? null) ? $profile->data : (json_decode((string) ($profile->data ?? ''), true) ?: []);
            $placeId = trim((string) ($data['place_id'] ?? ''));
            $profile->analytee_account_id = $placeId !== '' ? ($placeIdToAnalyteeAccountId[$placeId] ?? null) : null;
        }

        return $profiles;
    }
}
