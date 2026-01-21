<?php

namespace Modules\AppAnalytee\Services;

class GoldenJsonV1Reader
{
    public function read(int $teamId, int $accountId): array
    {
        if ($teamId <= 0 || $accountId <= 0) {
            return [
                'status' => 0,
                'reason' => 'invalid_ids',
            ];
        }

        $relative = 'analytee/exports/'.$teamId.'/'.$accountId.'/input.json';
        $absolute = storage_path('app/'.str_replace('/', DIRECTORY_SEPARATOR, $relative));

        if (! is_file($absolute)) {
            return [
                'status' => 0,
                'reason' => 'not_executed',
                'path' => $relative,
            ];
        }

        $raw = (string) @file_get_contents($absolute);
        if ($raw === '') {
            return [
                'status' => 0,
                'reason' => 'unreadable',
                'path' => $relative,
            ];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [
                'status' => 0,
                'reason' => 'invalid_json',
                'path' => $relative,
            ];
        }

        $relativeDir = 'analytee/exports/'.$teamId.'/'.$accountId;
        $relativeLastRun = $relativeDir.'/last_run.json';
        $absoluteLastRun = storage_path('app/'.str_replace('/', DIRECTORY_SEPARATOR, $relativeLastRun));

        $engineMode = null;
        if (is_file($absoluteLastRun)) {
            $lastRunRaw = (string) @file_get_contents($absoluteLastRun);
            if ($lastRunRaw !== '') {
                $lastRun = json_decode($lastRunRaw, true);
                if (is_array($lastRun)) {
                    $engine = $lastRun['engine'] ?? null;
                    if (is_array($engine) && array_key_exists('mode', $engine)) {
                        $engineMode = is_string($engine['mode']) ? $engine['mode'] : (is_null($engine['mode']) ? null : (string) $engine['mode']);
                    }
                }
            }
        }

        $website = $decoded['website'] ?? null;
        $websiteStr = is_string($website) ? $website : (is_null($website) ? '' : (string) $website);

        if ($engineMode === 'fixture' || str_contains($websiteStr, 'fixture.example')) {
            return [
                'status' => 0,
                'reason' => 'not_executed',
                'path' => $relative,
                'mock' => 1,
                'mock_reason' => $engineMode === 'fixture' ? 'last_run_fixture' : 'website_fixture_example',
                'mock_paths' => [
                    'input' => $relative,
                    'last_run' => $relativeLastRun,
                ],
            ];
        }

        return [
            'status' => 1,
            'path' => $relative,
            'data' => $decoded,
        ];
    }
}
