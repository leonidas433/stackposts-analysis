<?php

namespace Tests\Unit;

use Modules\AppAnalytee\Services\GoldenJsonV1Serializer;
use Tests\TestCase;

class GoldenJsonV1SerializerContractTest extends TestCase
{
    public function test_contract_snapshot_matches_fixtures(): void
    {
        $teamId = 1;
        $accountId = 101;

        $fixturesDir = base_path('tests/Fixtures/AppAnalytee');
        $accountsRows = $this->readJsonFile($fixturesDir.'/analytee_accounts.json');
        $reviewsRows = $this->readJsonFile($fixturesDir.'/analytee_reviews.json');

        $account = $this->findAccountRow($accountsRows, $teamId, $accountId);
        $this->assertIsArray($account);

        $placeId = (string) ($account['place_id'] ?? '');
        $this->assertNotSame('', trim($placeId));

        $reviews = $this->findReviewRows($reviewsRows, $teamId, $accountId, $placeId);

        $serializer = new GoldenJsonV1Serializer;
        $payload = $serializer->build($account, $reviews);
        $serializer->validate($payload);
        $encoded = $serializer->encode($payload, pretty: true);

        $this->assertLessThan(GoldenJsonV1Serializer::MAX_JSON_BYTES, strlen($encoded));

        $snapshot = (string) file_get_contents($fixturesDir.'/golden-json-v1.snapshot.json');
        $this->assertNotSame('', $snapshot);

        $expected = json_decode($snapshot, true);
        $this->assertIsArray($expected);

        $this->assertSame($expected, $payload);
    }

    private function readJsonFile(string $path): array
    {
        $raw = (string) file_get_contents($path);
        $this->assertNotSame('', $raw);

        $decoded = json_decode($raw, true);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    private function findAccountRow(array $rows, int $teamId, int $accountId): ?array
    {
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if ((int) ($row['team_id'] ?? 0) !== $teamId) {
                continue;
            }
            if ((int) ($row['account_id'] ?? 0) !== $accountId) {
                continue;
            }

            return $row;
        }

        return null;
    }

    private function findReviewRows(array $rows, int $teamId, int $accountId, string $placeId): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if ((int) ($row['team_id'] ?? 0) !== $teamId) {
                continue;
            }
            if ((int) ($row['account_id'] ?? 0) !== $accountId) {
                continue;
            }
            if ((string) ($row['place_id'] ?? '') !== $placeId) {
                continue;
            }

            $out[] = $row;
        }

        return $out;
    }
}
