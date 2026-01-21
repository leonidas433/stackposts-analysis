<?php

namespace Modules\AppAnalyticsInstagram\Services;

use Carbon\Carbon;
use DB;
use JanuSoftware\Facebook\Facebook;
use Modules\AppAnalytics\Contracts\SocialAnalyticsInterface;
use Modules\AppAnalytics\Models\SocialAnalytics;
use Modules\AppAnalytics\Models\SocialAnalyticsPost;
use Modules\AppAnalytics\Models\SocialAnalyticsPostInfo;
use Modules\AppAnalytics\Models\SocialAnalyticsSnapshot;
use Modules\AppChannels\Models\Accounts;

class InstagramAnalytics implements SocialAnalyticsInterface
{
    protected Facebook $fb;

    public function __construct()
    {
        try {
            $this->fb = $this->getInstagramClient();
        } catch (\Exception $e) {
        }
    }

    protected function getInstagramClient(): Facebook
    {
        $appId = get_option('instagram_app_id');
        $appSecret = get_option('instagram_app_secret');
        $appVersion = get_option('instagram_graph_version', 'v21.0');

        if (! $appId || ! $appSecret || ! $appVersion) {
            throw new \Exception(__('Instagram app config is missing. Please check your App ID, App Secret, and Graph Version.'));
        }

        return new Facebook([
            'app_id' => $appId,
            'app_secret' => $appSecret,
            'default_graph_version' => $appVersion,
        ]);
    }

    public function getName(): string
    {
        return 'Instagram';
    }

    public function getAccounts(int $teamId)
    {
        $accounts = Accounts::where('team_id', $teamId)->where('social_network', 'instagram')->where('category', 'profile')->where('login_type', 1)->orderBy('id')->get();

        if ($accounts) {
            foreach ($accounts as $key => $value) {
                $module = \Module::find($value->module);
                $moduleInfo = $module->get('menu');
                $accounts[$key]->module_icon = $moduleInfo['icon'] ?? '';
                $accounts[$key]->module_color = $moduleInfo['color'] ?? '';
                $accounts[$key]->module_name = $moduleInfo['name'] ?? '';

            }
        }

        return $accounts;
    }

    public function getAnalyticsData(int $teamId, ?string $id_secure = null, ?string $since = null, ?string $until = null): array
    {
        $accountInfo = $this->getAccountInfo($teamId, $id_secure);

        if (! $accountInfo) {
            return [
                'status' => 'error',
                'message' => __('Instagram account not found or disconnected.'),
            ];
        }

        $accountId = $accountInfo['id'];
        $instagramId = $accountInfo['pid'];
        $accessToken = $accountInfo['token'];

        // SYNC
        $this->syncProfileInsights($accountId, $instagramId, $accessToken, $since, $until);
        $this->syncPostInsights($accountId, $instagramId, $accessToken, $since, $until);

        return [
            'status' => 'success',
            'account' => $accountInfo,
            'overview' => $this->getInstagramOverview($accountId, $since, $until),
            'dailyViewsChartData' => $this->getDailyViewsChartData($accountId, $since, $until),
            'dailyInteractionsChartData' => $this->getDailyInteractionsChartData($accountId, $since, $until),
            'dailyReachChartData' => $this->getDailyReachChartData($accountId, $since, $until),
            'dailyAccountReachChartData' => $this->getDailyAccountReachChartData($accountId, $since, $until),
            'postHistoryList' => $this->getPostHistoryList($accountId, $since, $until),
            'followerAgeChartData' => $this->getFollowerAgeChartData($accountId, $since, $until),
            'followerGenderChartData' => $this->getFollowerGenderChartData($accountId, $since, $until),
            'topFollowerCountriesChartData' => $this->getTopFollowerCountriesChartData($accountId, $since, $until),
            'dailyFollowersCountChartData' => $this->getDailyFollowersCountChartData($accountId, $since, $until),
            'followerAgeChartData' => $this->getFollowerAgeChartData($accountId, $since, $until),
            'topFollowerCitiesChartData' => $this->getTopFollowerCitiesChartData($accountId, $since, $until),
            'reachByFollowTypeData' => $this->getReachByFollowTypeData($accountId, $since, $until),
            'dailyEngagementRateChartData' => $this->getDailyEngagementRateChartData($accountId, $since, $until),
        ];
    }

    public function getInstagramOverview(int $accountId, string $since, string $until): array
    {
        $metrics = [
            'reach',
            'likes',
            'comments',
            'shares',
            'views',
        ];

        $data = SocialAnalyticsPostInfo::where('account_id', $accountId)
            ->whereIn('metric', $metrics)
            ->whereBetween('date', [$since, $until])
            ->get()
            ->groupBy('metric')
            ->map(fn ($group) => $group->sum('value'))
            ->all();

        $days = Carbon::parse($since)->diffInDays(Carbon::parse($until)) + 1;
        $sinceCompare = Carbon::parse($since)->subDays($days)->toDateString();
        $untilCompare = Carbon::parse($since)->subDay()->toDateString();

        $dataCompare = SocialAnalyticsPostInfo::where('account_id', $accountId)
            ->whereIn('metric', $metrics)
            ->whereBetween('date', [$sinceCompare, $untilCompare])
            ->get()
            ->groupBy('metric')
            ->map(fn ($group) => $group->sum('value'))
            ->all();

        $currentPosts = SocialAnalyticsPost::where('account_id', $accountId)
            ->whereBetween('date', [$since, $until])
            ->count();

        $previousPosts = SocialAnalyticsPost::where('account_id', $accountId)
            ->whereBetween('date', [$sinceCompare, $untilCompare])
            ->count();

        $calculateChange = function ($current, $previous) {
            if ($previous == 0 && $current == 0) {
                return 0;
            }
            if ($previous == 0) {
                return 100;
            }

            return round((($current - $previous) / $previous) * 100, 2);
        };

        return [
            'reach' => [
                'value' => (int) ($data['reach'] ?? 0),
                'change' => $calculateChange($data['reach'] ?? 0, $dataCompare['reach'] ?? 0),
            ],
            'likes' => [
                'value' => (int) ($data['likes'] ?? 0),
                'change' => $calculateChange($data['likes'] ?? 0, $dataCompare['likes'] ?? 0),
            ],
            'comments' => [
                'value' => (int) ($data['comments'] ?? 0),
                'change' => $calculateChange($data['comments'] ?? 0, $dataCompare['comments'] ?? 0),
            ],
            'shares' => [
                'value' => (int) ($data['shares'] ?? 0),
                'change' => $calculateChange($data['shares'] ?? 0, $dataCompare['shares'] ?? 0),
            ],
            'views' => [
                'value' => (int) ($data['views'] ?? 0),
                'change' => $calculateChange($data['views'] ?? 0, $dataCompare['views'] ?? 0),
            ],
            'published_videos' => [
                'value' => $currentPosts,
                'change' => $calculateChange($currentPosts, $previousPosts),
            ],
        ];
    }

    public function getDailyViewsChartData(int $accountId, string $since, string $until): array
    {
        $dateRange = collect();
        $start = Carbon::parse($since);
        $end = Carbon::parse($until);
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateRange->put($date->format('M d'), 0);
        }

        $raw = SocialAnalyticsPostInfo::select(
            DB::raw('DATE_FORMAT(date, "%b %d") as day'),
            DB::raw('SUM(value) as total')
        )
            ->where('account_id', $accountId)
            ->where('metric', 'views')
            ->whereBetween('date', [$since, $until])
            ->groupBy('day')
            ->orderByRaw('STR_TO_DATE(day, "%b %d")')
            ->get()
            ->keyBy('day');

        $values = $dateRange->map(fn ($v, $day) => isset($raw[$day]) ? (int) $raw[$day]->total : 0
        );

        return [
            'categories' => $values->keys()->toArray(),
            'series' => [
                ['name' => __('Views'), 'data' => $values->values()->toArray()],
            ],
            'summary' => [
                'total' => $values->sum(),
            ],
        ];
    }

    public function getDailyInteractionsChartData(int $accountId, string $since, string $until): array
    {
        $metrics = [
            'total_interactions' => __('Total Interactions'),
            'likes' => __('Likes'),
            'comments' => __('Comments'),
            'shares' => __('Shares'),
            'saved' => __('Saved'),
        ];

        $period = Carbon::parse($since)->toPeriod(Carbon::parse($until));
        $allDays = collect($period)->map(fn ($date) => $date->format('M d'));

        $raw = SocialAnalyticsPostInfo::select(
            DB::raw('DATE_FORMAT(date, "%b %d") as day'),
            'metric',
            DB::raw('SUM(value) as total')
        )
            ->where('account_id', $accountId)
            ->whereIn('metric', array_keys($metrics))
            ->whereBetween('date', [$since, $until])
            ->groupBy('day', 'metric')
            ->orderBy('day')
            ->get()
            ->groupBy('metric');

        $arr = [];
        foreach ($metrics as $key => $label) {
            $arr[$key] = $allDays->map(function ($day) use ($raw, $key) {
                if (! isset($raw[$key])) {
                    return 0;
                }

                return (int) ($raw[$key]->firstWhere('day', $day)?->total ?? 0);
            })->toArray();
        }

        $series = [
            ['name' => $metrics['total_interactions'], 'data' => $arr['total_interactions'], 'type' => 'column'],
            ['name' => $metrics['likes'],   'data' => $arr['likes'],   'type' => 'spline'],
            ['name' => $metrics['comments'], 'data' => $arr['comments'], 'type' => 'spline'],
            ['name' => $metrics['shares'],  'data' => $arr['shares'],  'type' => 'spline'],
            ['name' => $metrics['saved'],   'data' => $arr['saved'],   'type' => 'spline'],
        ];

        return [
            'categories' => $allDays->toArray(),
            'series' => $series,
            'summary' => [
                'total_interactions' => array_sum($arr['total_interactions']),
                'likes' => array_sum($arr['likes']),
                'comments' => array_sum($arr['comments']),
                'shares' => array_sum($arr['shares']),
                'saved' => array_sum($arr['saved']),
            ],
        ];
    }

    public function getDailyAccountReachChartData(int $accountId, string $since, string $until): array
    {
        $period = Carbon::parse($since)->toPeriod(Carbon::parse($until));
        $allDays = collect($period)->map(fn ($date) => $date->format('M d'));

        $raw = SocialAnalytics::select(
            \DB::raw('DATE_FORMAT(date, "%b %d") as day'),
            \DB::raw('MAX(value) as total')
        )
            ->where('account_id', $accountId)
            ->where('social_network', 'instagram')
            ->where('metric', 'reach')
            ->whereBetween('date', [$since, $until])
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $values = $allDays->map(fn ($day) => isset($raw[$day]) ? (int) $raw[$day]->total : 0);

        return [
            'categories' => $allDays->toArray(),
            'series' => [
                [
                    'name' => __('Account Reach'),
                    'data' => $values->toArray(),
                ],
            ],
            'summary' => [
                'total' => $values->sum(),
            ],
        ];
    }

    public function getDailyReachChartData(int $accountId, string $since, string $until): array
    {
        $period = Carbon::parse($since)->toPeriod(Carbon::parse($until));
        $allDays = collect($period)->map(fn ($date) => $date->format('M d'));

        $raw = SocialAnalyticsPostInfo::select(
            DB::raw('DATE_FORMAT(date, "%b %d") as day'),
            DB::raw('SUM(value) as total')
        )
            ->where('account_id', $accountId)
            ->where('metric', 'reach')
            ->whereBetween('date', [$since, $until])
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $values = $allDays->map(fn ($day) => isset($raw[$day]) ? (int) $raw[$day]->total : 0);

        return [
            'categories' => $allDays->toArray(),
            'series' => [
                [
                    'name' => __('Reach'),
                    'data' => $values->toArray(),
                ],
            ],
            'summary' => [
                'total' => $values->sum(),
            ],
        ];
    }

    public function getDailyEngagementRateChartData(int $accountId, string $since, string $until): array
    {
        $dateRange = collect();
        $start = Carbon::parse($since);
        $end = Carbon::parse($until);
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateRange->put($date->format('M d'), 0);
        }

        $interactions = SocialAnalyticsPostInfo::select(
            DB::raw('DATE_FORMAT(date, "%b %d") as day'),
            DB::raw('SUM(value) as total')
        )
            ->where('account_id', $accountId)
            ->where('metric', 'total_interactions')
            ->whereBetween('date', [$since, $until])
            ->groupBy('day')
            ->orderByRaw('STR_TO_DATE(day, "%b %d")')
            ->get()
            ->keyBy('day');

        $reach = SocialAnalyticsPostInfo::select(
            DB::raw('DATE_FORMAT(date, "%b %d") as day'),
            DB::raw('SUM(value) as total')
        )
            ->where('account_id', $accountId)
            ->where('metric', 'reach')
            ->whereBetween('date', [$since, $until])
            ->groupBy('day')
            ->orderByRaw('STR_TO_DATE(day, "%b %d")')
            ->get()
            ->keyBy('day');

        $rates = $dateRange->map(function ($v, $day) use ($interactions, $reach) {
            $interaction = isset($interactions[$day]) ? $interactions[$day]->total : 0;
            $reachVal = isset($reach[$day]) ? $reach[$day]->total : 0;
            if ($reachVal > 0) {
                return round(($interaction / $reachVal) * 100, 2);
            }

            return 0;
        });

        $totalInteractions = $interactions->sum('total');
        $totalReach = $reach->sum('total');
        $avgRate = $totalReach > 0 ? round(($totalInteractions / $totalReach) * 100, 2) : 0;

        return [
            'categories' => $rates->keys()->toArray(),
            'series' => [
                ['name' => __('Engagement Rate (%)'), 'data' => $rates->values()->toArray()],
            ],
            'summary' => [
                'avg_rate' => $avgRate,
                'total_interactions' => $totalInteractions,
                'total_reach' => $totalReach,
            ],
        ];
    }

    public function getFollowerAgeChartData(int $accountId, string $since, string $until): array
    {
        $defaultAgeGroups = ['13-17', '18-24', '25-34', '35-44', '45-54', '55-64', '65+'];

        $latestDate = SocialAnalytics::where('account_id', $accountId)
            ->where('metric', 'like', 'follower_age.%')
            ->whereBetween('date', [$since, $until])
            ->max('date');

        $raw = SocialAnalytics::selectRaw("
	            REPLACE(metric, 'follower_age.', '') as age_group,
	            value as followers
	        ")
            ->where('account_id', $accountId)
            ->where('metric', 'like', 'follower_age.%')
            ->where('date', $latestDate)
            ->get()
            ->keyBy('age_group');

        $categories = [];
        $data = [];
        $summary = [];
        $total = 0;

        foreach ($defaultAgeGroups as $ageGroup) {
            $count = isset($raw[$ageGroup]) ? (int) $raw[$ageGroup]->followers : 0;
            $categories[] = $ageGroup;
            $data[] = [
                'name' => $ageGroup,
                'y' => $count,
            ];
            $summary[$ageGroup] = $count;
            $total += $count;
        }

        return [
            'categories' => $categories,
            'series' => [
                [
                    'name' => __('Followers'),
                    'colorByPoint' => true,
                    'data' => $data,
                ],
            ],
            'summary' => array_merge($summary, [
                'total' => $total,
                'latest_date' => $latestDate,
            ]),
        ];
    }

    public function getFollowerGenderChartData(int $accountId, string $since, string $until): array
    {
        $metrics = [
            'follower_gender.M' => __('Male'),
            'follower_gender.F' => __('Female'),
            'follower_gender.U' => __('Unknown'),
        ];

        $latestDate = SocialAnalytics::where('account_id', $accountId)
            ->whereIn('metric', array_keys($metrics))
            ->whereBetween('date', [$since, $until])
            ->max('date');

        $raw = SocialAnalytics::where('account_id', $accountId)
            ->whereIn('metric', array_keys($metrics))
            ->where('date', $latestDate)
            ->pluck('value', 'metric')
            ->toArray();

        $data = [];
        $summary = [];
        foreach ($metrics as $metricKey => $display) {
            $count = (int) ($raw[$metricKey] ?? 0);
            $data[] = [
                'name' => $display,
                'y' => $count,
            ];
            $summary[strtolower($display)] = $count;
        }

        $total = array_sum(array_column($data, 'y'));

        return [
            'series' => $data,
            'summary' => array_merge($summary, [
                'total' => $total,
                'latest_date' => $latestDate,
            ]),
        ];
    }

    public function getTopFollowerCountriesChartData(int $accountId, string $since, string $until, int $limit = 10): array
    {
        $latestDate = SocialAnalytics::where('account_id', $accountId)
            ->where('metric', 'like', 'follower_country.%')
            ->whereBetween('date', [$since, $until])
            ->max('date');

        $raw = SocialAnalytics::selectRaw("
	            REPLACE(metric, 'follower_country.', '') as country_code,
	            value as followers
	        ")
            ->where('account_id', $accountId)
            ->where('metric', 'like', 'follower_country.%')
            ->where('date', $latestDate)
            ->orderByDesc('followers')
            ->limit($limit)
            ->get();

        $summaryTable = $raw->map(function ($row) {
            $code = strtoupper($row->country_code);

            return [
                'country' => list_countries($code),
                'code' => $code,
                'followers' => (int) $row->followers,
            ];
        })->toArray();

        $mapData = $raw->map(function ($row) {
            return [
                'code' => strtoupper($row->country_code),
                'value' => (int) $row->followers,
            ];
        })->toArray();

        $total = array_sum(array_column($summaryTable, 'followers'));

        $data = array_map(function ($row) use ($total) {
            $percent = $total > 0 ? round($row['followers'] / $total * 100, 2) : 0;

            return [
                'name' => $row['country'],
                'y' => $percent,
                'count' => $row['followers'],
                'code' => $row['code'] ?? null,
            ];
        }, $summaryTable);

        $categories = array_column($summaryTable, 'country');

        return [
            'map_data' => $mapData,
            'top_countries' => $summaryTable,
            'series' => [[
                'name' => __('Followers (%)'),
                'data' => $data,
            ]],
            'categories' => $categories,
            'summary' => [
                'total_top' => $total,
                'limit' => $limit,
                'latest_date' => $latestDate,
            ],
        ];
    }

    public function getTopFollowerCitiesChartData(int $accountId, string $since, string $until, int $limit = 10): array
    {
        $latestDate = SocialAnalytics::where('account_id', $accountId)
            ->where('metric', 'like', 'follower_city.%')
            ->whereBetween('date', [$since, $until])
            ->max('date');

        $raw = SocialAnalytics::selectRaw("
	            REPLACE(metric, 'follower_city.', '') as city_name,
	            value as followers
	        ")
            ->where('account_id', $accountId)
            ->where('metric', 'like', 'follower_city.%')
            ->where('date', $latestDate)
            ->orderByDesc('followers')
            ->limit($limit)
            ->get();

        $summaryTable = $raw->map(function ($row) {
            return [
                'city' => $row->city_name,
                'followers' => (int) $row->followers,
            ];
        })->toArray();

        $total = array_sum(array_column($summaryTable, 'followers'));

        $data = array_map(function ($row) use ($total) {
            $percent = $total > 0 ? round($row['followers'] / $total * 100, 2) : 0;

            return [
                'name' => $row['city'],
                'y' => $percent,
                'count' => $row['followers'],
            ];
        }, $summaryTable);

        $categories = array_column($summaryTable, 'city');

        return [
            'series' => [[
                'name' => __('Followers (%)'),
                'data' => $data,
            ]],
            'categories' => $categories,
            'top_cities' => $summaryTable,
            'summary' => [
                'total_top' => $total,
                'limit' => $limit,
                'latest_date' => $latestDate,
            ],
        ];
    }

    public function getDailyFollowersCountChartData(int $accountId, string $since, string $until): array
    {
        $period = Carbon::parse($since)->toPeriod(Carbon::parse($until));
        $allDays = collect($period)->map(fn ($date) => $date->format('M d'));

        $raw = SocialAnalytics::select(
            DB::raw('DATE_FORMAT(date, "%b %d") as day'),
            DB::raw('MAX(value) as followers')
        )
            ->where('account_id', $accountId)
            ->where('metric', 'follower_count')
            ->whereBetween('date', [$since, $until])
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $values = $allDays->map(fn ($day) => isset($raw[$day]) ? (int) $raw[$day]->followers : 0);

        return [
            'categories' => $allDays->toArray(),
            'series' => [
                [
                    'name' => __('Followers'),
                    'data' => $values->toArray(),
                    'color' => '#675dff',
                ],
            ],
            'summary' => [
                'start' => $values->first(),
                'end' => $values->last(),
                'change' => $values->last() - $values->first(),
                'total' => $values->last(),
            ],
        ];
    }

    public function getReachByFollowTypeData(int $accountId, string $since, string $until): array
    {
        $metrics = [
            'reach.followers' => __('Followers'),
            'reach.non_followers' => __('Non-Followers'),
        ];

        $latestDate = SocialAnalytics::where('account_id', $accountId)
            ->where('social_network', 'instagram')
            ->whereIn('metric', array_keys($metrics))
            ->whereBetween('date', [$since, $until])
            ->max('date');

        $raw = SocialAnalytics::where('account_id', $accountId)
            ->where('social_network', 'instagram')
            ->whereIn('metric', array_keys($metrics))
            ->where('date', $latestDate)
            ->pluck('value', 'metric')
            ->toArray();

        $followers = (int) ($raw['reach.followers'] ?? 0);
        $nonFollowers = (int) ($raw['reach.non_followers'] ?? 0);
        $total = $followers + $nonFollowers;

        return [
            'series' => [
                [
                    'name' => __('Followers'),
                    'y' => $followers,
                ],
                [
                    'name' => __('Non-Followers'),
                    'y' => $nonFollowers,
                ],
            ],
            'summary' => [
                'followers' => $followers,
                'non_followers' => $nonFollowers,
                'total' => $total,
                'latest_date' => $latestDate,
            ],
        ];
    }

    public function getPostHistoryList(int $accountId, string $since, string $until): array
    {
        $meta = SocialAnalyticsPost::query()
            ->where('account_id', $accountId)
            ->whereBetween('date', [$since, $until])
            ->orderByDesc('created_time')
            ->get()
            ->keyBy('post_id');

        $metrics = SocialAnalyticsPostInfo::query()
            ->select('post_id', 'metric', DB::raw('SUM(value) as total'))
            ->where('account_id', $accountId)
            ->whereBetween('date', [$since, $until])
            ->whereIn('metric', [
                'reach',
                'likes',
                'comments',
                'shares',
                'saved',
                'views',
                'total_interactions',
            ])
            ->groupBy('post_id', 'metric')
            ->get()
            ->groupBy('post_id');

        $posts = [];

        foreach ($meta as $postId => $post) {
            $metricData = $metrics[$postId] ?? collect();

            $posts[] = [
                'post_id' => $post->post_id,
                'caption' => $post->message,
                'created_time' => $post->created_time,
                'media_url' => $post->full_picture,
                'permalink_url' => $post->permalink_url,
                'media_type' => $post->type ?? null,
                'metrics' => $metricData->pluck('total', 'metric')
                    ->map(fn ($val) => (int) $val)
                    ->toArray(),
            ];
        }

        return $posts;
    }

    // -------------------------------------
    // SYNCING NEW ANALYTICS DATA
    // -------------------------------------
    protected function getAccountInfo(int $teamId, ?string $id_secure = null): ?array
    {
        $account = Accounts::where('social_network', 'instagram')
            ->where('category', 'profile')
            ->where('login_type', 1)
            ->where('team_id', $teamId)
            ->when($id_secure, fn ($q) => $q->where('id_secure', $id_secure))
            ->first();

        if (! $account) {
            logger()->warning("[InstagramAnalytics] No account found for team_id={$teamId}");

            return null;
        }

        $now = time();

        if (\Analytics::shouldFetchSocialAnalytics($account->id, 'instagram', 'account')) {
            try {
                $response = $this->fb->get("/{$account->pid}?fields=id,username,profile_picture_url,followers_count,media_count,name", $account->token);
                $profile = $response->getDecodedBody();

                $info = [
                    'id' => $account->id,
                    'pid' => $account->pid,
                    'name' => $profile['name'] ?? $account->name,
                    'username' => $profile['username'] ?? $account->username,
                    'url' => "https://instagram.com/{$profile['username']}",
                    'avatar' => $profile['profile_picture_url'] ?? theme_public_asset('img/default.png'),
                    'followers_count' => $profile['followers_count'] ?? 0,
                    'media_count' => $profile['media_count'] ?? 0,
                ];

                SocialAnalyticsSnapshot::updateOrCreate(
                    [
                        'account_id' => $account->id,
                        'social_network' => 'instagram',
                        'date' => now()->toDateString(),
                    ],
                    [
                        'data' => $info,
                        'created' => $now,
                    ]
                );

                $info['token'] = $account->token;

                \Analytics::markSynced($account->id, 'instagram', 'account');

                return $info;

            } catch (\Exception $e) {
                logger()->error('[InstagramAnalytics] getAccountInfo error: '.$e->getMessage());
            }
        }

        $snapshot = SocialAnalyticsSnapshot::where([
            'account_id' => $account->id,
            'social_network' => 'instagram',
            'date' => now()->toDateString(),
        ])->first();

        if ($snapshot && $snapshot->data) {
            logger()->info('[InstagramAnalytics] Using snapshot data from DB.');
            $data = is_array($snapshot->data) ? $snapshot->data : json_decode($snapshot->data, true);
            $data['token'] = $account->token;

            return $data;
        }

        return null;
    }

    protected function syncProfileInsights(int $accountId, string $instagramId, string $token, string $since, string $until): void
    {
        if (! \Analytics::shouldFetchSocialAnalytics($accountId, 'instagram', 'profile')) {
            return;
        }

        $start = Carbon::parse($since);
        $end = Carbon::parse($until);
        $maxRange = 30;

        $insights = [];

        while ($start->lte($end)) {
            $rangeStart = $start->copy();
            $rangeEnd = $start->copy()->addDays($maxRange - 1);
            if ($rangeEnd->gt($end)) {
                $rangeEnd = $end->copy();
            }

            $sinceStr = $rangeStart->toDateString();
            $untilStr = $rangeEnd->toDateString();

            // Request 1
            try {
                $metrics = [
                    'reach',
                    'follower_count',
                ];
                if (! empty($metrics)) {
                    $endpoint = "/{$instagramId}/insights?metric=".implode(',', $metrics)."&period=day&since={$sinceStr}&until={$untilStr}";
                    $response = $this->fb->get($endpoint, $token);
                    $result = $response->getDecodedBody();

                    foreach ($result['data'] ?? [] as $item) {

                        $metric = $item['name'] ?? null;
                        if (! $metric) {
                            continue;
                        }

                        foreach ($item['values'] as $entry) {
                            if (empty($entry['end_time'])) {
                                continue;
                            }
                            $date = Carbon::parse($entry['end_time'])->toDateString();
                            $value = $entry['value'] ?? 0;
                            if (is_numeric($value) && $value > 0) {
                                $insights[$metric][$date] = (float) $value;
                            }
                        }
                    }
                }

            } catch (\Exception $e) {
                logger()->error('[InstagramAnalytics] syncProfileInsights error: '.$e->getMessage());
            }

            // Request 1
            try {
                $metrics = ['reach'];
                if (! empty($metrics)) {
                    $endpoint = "/{$instagramId}/insights?metric=".implode(',', $metrics).'&metric_type=total_value&breakdown=follow_type&period=day&timeframe=this_month';
                    $response = $this->fb->get($endpoint, $token);
                    $result = $response->getDecodedBody();

                    foreach ($result['data'] ?? [] as $item) {
                        $metric = $item['name'] ?? null;
                        if (! $metric) {
                            continue;
                        }

                        if (
                            isset($item['total_value']['breakdowns'][0]['results'])
                            && is_array($item['total_value']['breakdowns'][0]['results'])
                        ) {
                            foreach ($item['total_value']['breakdowns'][0]['results'] as $row) {
                                $dimension = $row['dimension_values'][0] ?? null; // 'followers' or 'non_followers'
                                $date = $item['period'] === 'day'
                                    ? (isset($row['end_time']) ? Carbon::parse($row['end_time'])->toDateString() : $untilStr)
                                    : $untilStr;
                                $value = $row['value'] ?? 0;

                                if ($dimension) {
                                    $insights["{$metric}.{$dimension}"][$date] = (float) $value;
                                }
                            }
                        } else {
                            $value = $item['total_value']['value'] ?? 0;
                            $insights[$metric][$untilStr] = (float) $value;
                        }
                    }
                }
            } catch (\Exception $e) {
                logger()->error('[InstagramAnalytics] syncProfileInsights error: '.$e->getMessage());
            }

            // Request 2
            try {
                $metrics = [
                    'profile_views',
                    'website_clicks',
                    'accounts_engaged',
                    'total_interactions',
                    'likes',
                    'comments',
                    'shares',
                    'replies',
                    'saves',
                ];
                $endpoint = "/{$instagramId}/insights?metric=".implode(',', $metrics)."&metric_type=total_value&period=day&since={$sinceStr}&until={$untilStr}";
                $response = $this->fb->get($endpoint, $token);
                $result = $response->getDecodedBody();
                foreach ($result['data'] ?? [] as $item) {
                    $metric = $item['name'] ?? null;
                    if (! $metric) {
                        continue;
                    }

                    if (isset($item['total_value']['value'])) {
                        $value = $item['total_value']['value'];
                        if (is_numeric($value) && $value > 0) {
                            $insights[$metric][$untilStr]['value'] = (float) $value;
                        }

                        continue;
                    }
                }
            } catch (\Exception $e) {
                logger()->error('[InstagramAnalytics] syncProfileInsights error: '.$e->getMessage());
            }

            // Request 3
            try {
                $metricMap = [
                    'follower_demographics' => 'follower_age',
                    // 'engaged_audience_demographics' => 'engaged_audience_age',
                ];

                $endpoint = "/{$instagramId}/insights?metric=".implode(',', array_keys($metricMap)).'&metric_type=total_value&breakdown=age&period=lifetime&timeframe=last_30_days';
                $response = $this->fb->get($endpoint, $token);
                $result = $response->getDecodedBody();

                foreach ($result['data'] ?? [] as $item) {
                    $metricName = $item['name'] ?? null;
                    if (! $metricName || ! isset($metricMap[$metricName])) {
                        continue;
                    }

                    $saveKey = $metricMap[$metricName];

                    if (
                        isset($item['total_value']['breakdowns']['results'])
                        && isset($item['dimension_keys'])
                        && $item['dimension_keys'] === ['age']
                    ) {
                        foreach ($item['total_value']['breakdowns']['results'] as $row) {
                            $age = $row['dimension_values'][0] ?? null;
                            $value = $row['value'] ?? 0;
                            if ($age && $value > 0) {
                                $insights["{$saveKey}.{$age}"][$untilStr] = (int) $value;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                logger()->error('[InstagramAnalytics] syncProfileInsights error: '.$e->getMessage());
            }

            // Request 4
            try {
                $metricMap = [
                    'follower_demographics' => 'follower_gender',
                    // 'engaged_audience_demographics' => 'engaged_audience_gender',
                ];

                $endpoint = "/{$instagramId}/insights?metric=".implode(',', array_keys($metricMap)).'&metric_type=total_value&breakdown=gender&period=lifetime&timeframe=last_30_days';
                $response = $this->fb->get($endpoint, $token);
                $result = $response->getDecodedBody();

                foreach ($result['data'] ?? [] as $item) {
                    $metric = $item['name'] ?? null;
                    if (! $metric || ! isset($metricMap[$metric])) {
                        continue;
                    }

                    $saveKey = $metricMap[$metric];

                    if (
                        isset($item['total_value']['breakdowns']['results']) &&
                        isset($item['dimension_keys']) &&
                        $item['dimension_keys'] === ['gender']
                    ) {
                        foreach ($item['total_value']['breakdowns']['results'] as $row) {
                            $gender = $row['dimension_values'][0] ?? null;
                            $value = $row['value'] ?? 0;
                            if ($gender) {
                                $insights["{$saveKey}.{$gender}"][$untilStr] = (int) $value;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                logger()->error('[InstagramAnalytics] syncProfileInsights error: '.$e->getMessage());
            }

            // Request 5
            try {
                $metricMap = [
                    'follower_demographics' => 'follower_country',
                    // 'engaged_audience_demographics' => 'engaged_audience_country',
                ];

                $endpoint = "/{$instagramId}/insights?metric=".implode(',', array_keys($metricMap)).'&metric_type=total_value&breakdown=country&period=lifetime&timeframe=last_30_days';
                $response = $this->fb->get($endpoint, $token);
                $result = $response->getDecodedBody();

                foreach ($result['data'] ?? [] as $item) {
                    $metric = $item['name'] ?? null;
                    if (! $metric || ! isset($metricMap[$metric])) {
                        continue;
                    }

                    $saveKey = $metricMap[$metric];

                    if (
                        isset($item['total_value']['breakdowns']['results']) &&
                        isset($item['dimension_keys']) &&
                        $item['dimension_keys'] === ['country']
                    ) {
                        foreach ($item['total_value']['breakdowns']['results'] as $row) {
                            $country = $row['dimension_values'][0] ?? null;
                            $value = $row['value'] ?? 0;
                            if ($country) {
                                $insights["{$saveKey}.{$country}"][$untilStr] = (int) $value;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                logger()->error('[InstagramAnalytics] syncProfileInsights error: '.$e->getMessage());
            }

            // Request 5
            try {
                $metricMap = [
                    'follower_demographics' => 'follower_city',
                    // 'engaged_audience_demographics' => 'engaged_audience_city',
                ];

                $endpoint = "/{$instagramId}/insights?metric=".implode(',', array_keys($metricMap)).'&metric_type=total_value&breakdown=city&period=lifetime&timeframe=last_30_days';
                $response = $this->fb->get($endpoint, $token);
                $result = $response->getDecodedBody();

                foreach ($result['data'] ?? [] as $item) {
                    $metric = $item['name'] ?? null;
                    if (! $metric || ! isset($metricMap[$metric])) {
                        continue;
                    }

                    $saveKey = $metricMap[$metric];

                    if (
                        isset($item['total_value']['breakdowns']['results']) &&
                        isset($item['dimension_keys']) &&
                        $item['dimension_keys'] === ['city']
                    ) {
                        foreach ($item['total_value']['breakdowns']['results'] as $row) {
                            $city = ucwords(strtolower($row['dimension_values'][0] ?? ''));
                            $value = $row['value'] ?? 0;
                            if ($city) {
                                $insights["{$saveKey}.{$city}"][$untilStr] = (int) $value;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                logger()->error('[InstagramAnalytics] syncProfileInsights error: '.$e->getMessage());
            }

            $start->addDays($maxRange);
        }

        if (! empty($insights)) {
            \Analytics::saveInsightsToDatabase($accountId, 'instagram', $insights);
            \Analytics::markSynced($accountId, 'instagram', 'profile');
        }
    }

    protected function syncPostInsights(int $accountId, string $instagramId, string $token, string $since, string $until): void
    {
        if (! \Analytics::shouldFetchSocialAnalytics($accountId, 'instagram', 'post')) {
            return;
        }
        try {
            //
            // replies, video_views, plays, follows, profile_visits, profile_activity, navigation, ig_reels_video_view_total_time, ig_reels_avg_watch_time, clips_replays_count, ig_reels_aggregated_all_plays_count, views
            $url = "/{$instagramId}/media?fields=id,caption,media_type,media_url,permalink,timestamp,insights.metric(reach,likes,comments,shares,views,total_interactions,saved)&since={$since}&until={$until}&limit=100";
            $response = $this->fb->get($url, $token);
            $media = $response->getDecodedBody();

            $now = time();
            $metaInsert = [];
            $metricsInsert = [];

            foreach ($media['data'] ?? [] as $post) {
                $postId = $post['id'];
                $created = Carbon::parse($post['timestamp']);
                $date = $created->toDateString();

                $metaInsert[] = [
                    'account_id' => $accountId,
                    'social_network' => 'instagram',
                    'post_id' => $postId,
                    'date' => $date,
                    'message' => $post['caption'] ?? null,
                    'created_time' => $created->toDateTimeString(),
                    'full_picture' => $post['media_url'] ?? null,
                    'permalink_url' => $post['permalink'] ?? null,
                    'type' => $post['media_type'] ?? null,
                    'created' => $now,
                ];

                // Insights
                $metrics = [
                    'reach', 'likes', 'comments', 'shares', 'views', 'total_interactions', 'saved',
                ];

                $now = time();
                $socialNetwork = 'instagram';
                $metricsInsert = [];

                $start = Carbon::now()->subDays(29);

                foreach (($post['insights']['data'] ?? []) as $insight) {
                    $metric = $insight['name'];
                    $value = $insight['values'][0]['value'] ?? 0;
                    if ((int) $value > 0) {
                        $metricsInsert[] = [
                            'post_id' => $postId,
                            'account_id' => $accountId,
                            'social_network' => 'instagram',
                            'metric' => $metric,
                            'value' => (float) $value,
                            'date' => $date,
                            'created' => $now,
                        ];
                    }
                }
            }

            if (! empty($metaInsert)) {
                SocialAnalyticsPost::upsert($metaInsert, ['account_id', 'social_network', 'post_id', 'date'], ['message', 'created_time', 'full_picture', 'permalink_url', 'type', 'created']);
            }
            if (! empty($metricsInsert)) {
                SocialAnalyticsPostInfo::upsert($metricsInsert, ['post_id', 'metric', 'date'], ['value', 'created']);
                \Analytics::markSynced($accountId, 'instagram', 'post');
            }
        } catch (\Exception $e) {
            logger()->error('[InstagramAnalytics] syncPostInsights error: '.$e->getMessage());
        }
    }
}
