<?php

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Modules\AdminUsers\Models\Teams;

test('analytee reviews index paginates full filtered set even with limit param', function () {
    Queue::fake();

    $user = User::factory()->create(['expiration_date' => 0]);
    $team = Teams::where('owner', $user->id)->firstOrFail();
    $team->update([
        'permissions' => ['appanalytee' => 1],
    ]);

    $teamId = (int) $team->id;
    $accountId = 123;
    $placeId = 'ChIJ_TEST_PLACE_ID';

    DB::table('analytee_accounts')->insert([
        'team_id' => $teamId,
        'account_id' => $accountId,
        'place_id' => $placeId,
        'status' => 'connected',
        'last_sync_at' => null,
        'created' => time(),
        'updated' => time(),
    ]);

    $publishedAt = Carbon::now()->subDay()->toDateTimeString();
    $publishedAtMostRecent = Carbon::now()->toDateTimeString();
    $authorPhotoUrl = 'https://example.com/avatar.png';
    $meta = json_encode(['source' => 'gbp'], JSON_UNESCAPED_UNICODE);
    $metaWithAuthorPhoto = json_encode(['source' => 'gbp', 'author_photo_url' => $authorPhotoUrl], JSON_UNESCAPED_UNICODE);

    $rows = [];
    for ($i = 1; $i <= 25; $i++) {
        $rows[] = [
            'team_id' => $teamId,
            'account_id' => $accountId,
            'place_id' => $placeId,
            'external_id' => 'review-'.$i,
            'author_name' => 'Author '.$i,
            'author_url' => null,
            'rating' => 5,
            'text' => 'Text '.$i,
            'owner_response_text' => null,
            'owner_response_at' => null,
            'language' => 'en',
            'meta' => $i === 1 ? $metaWithAuthorPhoto : $meta,
            'published_at' => $i === 1 ? $publishedAtMostRecent : $publishedAt,
            'created' => time(),
            'updated' => time(),
        ];
    }
    DB::table('analytee_reviews')->insert($rows);

    $response = $this
        ->actingAs($user)
        ->get("/app/analytee/reviews/{$accountId}?limit=10");

    $response->assertOk();
    $response->assertViewHas('totalFiltered', 25);
    $response->assertViewHas('reviews', function ($paginator) use ($authorPhotoUrl) {
        $items = $paginator->items();
        $first = is_array($items[0] ?? null) ? $items[0] : [];

        return $paginator->total() === 25
            && $paginator->currentPage() === 1
            && count($items) === 10
            && ($first['avatar_url'] ?? null) === $authorPhotoUrl;
    });

    $responsePage2 = $this
        ->actingAs($user)
        ->get("/app/analytee/reviews/{$accountId}?limit=10&page=2");

    $responsePage2->assertOk();
    $responsePage2->assertViewHas('reviews', function ($paginator) {
        return $paginator->total() === 25
            && $paginator->currentPage() === 2
            && count($paginator->items()) === 10;
    });

    $responsePage3 = $this
        ->actingAs($user)
        ->get("/app/analytee/reviews/{$accountId}?limit=10&page=3");

    $responsePage3->assertOk();
    $responsePage3->assertViewHas('reviews', function ($paginator) {
        return $paginator->total() === 25
            && $paginator->currentPage() === 3
            && count($paginator->items()) === 5;
    });

    Queue::assertNothingPushed();
})->skip(fn () => ! databaseReady(), 'Database not available');

test('analytee reviews stats endpoint returns sentiment and reply breakdown', function () {
    Queue::fake();

    $user = User::factory()->create(['expiration_date' => 0]);
    $team = Teams::where('owner', $user->id)->firstOrFail();
    $team->update([
        'permissions' => ['appanalytee' => 1],
    ]);

    $teamId = (int) $team->id;
    $accountId = 456;
    $placeId = 'ChIJ_TEST_PLACE_ID_STATS';

    DB::table('analytee_reviews')->where('team_id', $teamId)->where('account_id', $accountId)->delete();
    DB::table('analytee_accounts')->where('team_id', $teamId)->where('account_id', $accountId)->delete();

    DB::table('analytee_accounts')->insert([
        'team_id' => $teamId,
        'account_id' => $accountId,
        'place_id' => $placeId,
        'status' => 'connected',
        'last_sync_at' => null,
        'created' => time(),
        'updated' => time(),
    ]);

    $publishedAt = Carbon::now()->subDay()->toDateTimeString();
    $meta = json_encode(['source' => 'gbp'], JSON_UNESCAPED_UNICODE);

    DB::table('analytee_reviews')->insert([
        [
            'team_id' => $teamId,
            'account_id' => $accountId,
            'place_id' => $placeId,
            'external_id' => 'review-1',
            'author_name' => 'Author 1',
            'author_url' => null,
            'rating' => 5,
            'text' => 'Text 1',
            'owner_response_text' => 'Gracias',
            'owner_response_at' => $publishedAt,
            'language' => 'es',
            'meta' => $meta,
            'published_at' => $publishedAt,
            'created' => time(),
            'updated' => time(),
        ],
        [
            'team_id' => $teamId,
            'account_id' => $accountId,
            'place_id' => $placeId,
            'external_id' => 'review-2',
            'author_name' => 'Author 2',
            'author_url' => null,
            'rating' => 4,
            'text' => 'Text 2',
            'owner_response_text' => null,
            'owner_response_at' => null,
            'language' => 'es',
            'meta' => $meta,
            'published_at' => $publishedAt,
            'created' => time(),
            'updated' => time(),
        ],
        [
            'team_id' => $teamId,
            'account_id' => $accountId,
            'place_id' => $placeId,
            'external_id' => 'review-3',
            'author_name' => 'Author 3',
            'author_url' => null,
            'rating' => 3,
            'text' => 'Text 3',
            'owner_response_text' => 'Ok',
            'owner_response_at' => $publishedAt,
            'language' => 'es',
            'meta' => $meta,
            'published_at' => $publishedAt,
            'created' => time(),
            'updated' => time(),
        ],
        [
            'team_id' => $teamId,
            'account_id' => $accountId,
            'place_id' => $placeId,
            'external_id' => 'review-4',
            'author_name' => 'Author 4',
            'author_url' => null,
            'rating' => 2,
            'text' => 'Text 4',
            'owner_response_text' => null,
            'owner_response_at' => null,
            'language' => 'es',
            'meta' => $meta,
            'published_at' => $publishedAt,
            'created' => time(),
            'updated' => time(),
        ],
        [
            'team_id' => $teamId,
            'account_id' => $accountId,
            'place_id' => $placeId,
            'external_id' => 'review-5',
            'author_name' => 'Author 5',
            'author_url' => null,
            'rating' => 1,
            'text' => 'Text 5',
            'owner_response_text' => null,
            'owner_response_at' => null,
            'language' => 'es',
            'meta' => $meta,
            'published_at' => $publishedAt,
            'created' => time(),
            'updated' => time(),
        ],
    ]);

    $response = $this
        ->actingAs($user)
        ->getJson("/app/analytee/reviews/{$accountId}/stats?period=all");

    $response->assertOk();
    $response->assertJsonPath('status', 1);
    $response->assertJsonPath('data.total', 5);
    $response->assertJsonPath('data.sentiment.positive.count', 2);
    $response->assertJsonPath('data.sentiment.neutral.count', 1);
    $response->assertJsonPath('data.sentiment.negative.count', 2);
    $response->assertJsonPath('data.reply.with.count', 2);
    $response->assertJsonPath('data.reply.without.count', 3);
    $response->assertJsonPath('data.sentiment.positive.percent', 40);
    $response->assertJsonPath('data.sentiment.neutral.percent', 20);
    $response->assertJsonPath('data.sentiment.negative.percent', 40);
    $response->assertJsonPath('data.reply.with.percent', 40);
    $response->assertJsonPath('data.reply.without.percent', 60);

    Queue::assertNothingPushed();
})->skip(fn () => ! databaseReady(), 'Database not available');
