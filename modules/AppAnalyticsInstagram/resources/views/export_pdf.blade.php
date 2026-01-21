<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Instagram Analytics Report') }}</title>
    <style>
        @font-face {
            font-family: 'NotoSans';
            src: url("{{ base_path('resources/fonts/NotoSans-Regular.ttf') }}") format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        @font-face {
            font-family: 'NotoSans';
            src: url("{{ base_path('resources/fonts/NotoSans-Bold.ttf') }}") format('truetype');
            font-weight: bold;
            font-style: normal;
        }
        body {
            font-family: 'NotoSans', sans-serif;
            font-size: 12px;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            color: #222;
        }
        h1 {
            font-size: 20px;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e1306c;
        }
        h3 {
            font-size: 15px;
            margin-bottom: 8px;
            margin-top: 28px;
            color: #333;
        }
        .section {
            margin-bottom: 32px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 12px;
        }
        .table th, .table td {
            border: 1px solid #ddd;
            padding: 6px 10px;
            text-align: left;
        }
        .table th {
            background-color: #f0f0f0;
        }
        .table tbody tr:nth-child(even) {
            background-color: #fafafa;
        }
        img.chart {
            display: block;
            margin: 0 auto;
            max-width: 95%;
            margin-top: 12px;
            margin-bottom: 30px;
        }
        .highlight {
            font-weight: bold;
            color: #e1306c;
        }
        .subtext {
            color: #888;
            font-size: 11px;
            margin-top: 3px;
        }
    </style>
</head>
<body>

    <h1>{{ __('Instagram Analytics Report') }}</h1>

    <div class="section">
        <strong>{{ __('Account Name') }}:</strong> <span class="highlight">{{ $analytics['account']['name'] ?? '-' }}</span><br>
        <strong>{{ __('Username') }}:</strong> <span class="highlight">{{ $analytics['account']['username'] ?? '-' }}</span><br>
        <strong>{{ __('Followers') }}:</strong> <span class="highlight">{{ number_format($analytics['account']['followers_count'] ?? 0) }}</span><br>
        <strong>{{ __('Posts') }}:</strong> <span class="highlight">{{ number_format($analytics['account']['media_count'] ?? 0) }}</span><br>
        @if (!empty($startDate) && !empty($endDate))
            <br>
            <strong>{{ __('From') }}:</strong> {{ $startDate }}<br>
            <strong>{{ __('To') }}:</strong> {{ $endDate }}
        @endif
    </div>

    @if (!empty($charts) && is_array($charts))
        <div class="section">
            <h3>{{ __('Charts') }}</h3>
            @foreach ($charts as $chart)
                @if(isset($chart['base64']))
                    <img class="chart" src="{{ $chart['base64'] }}" alt="Chart">
                @endif
            @endforeach
        </div>
    @endif

    <div class="section">
        <h3>{{ __('Profile Overview') }}</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('Metric') }}</th>
                    <th>{{ __('Total') }}</th>
                    <th>{{ __('Change (%)') }}</th>
                </tr>
            </thead>
            <tbody>
                @php $overview = $analytics['overview'] ?? [] @endphp
                <tr>
                    <td>{{ __('Reach') }}</td>
                    <td class="highlight">{{ number_format($overview['reach']['value'] ?? 0) }}</td>
                    <td>{{ ($overview['reach']['change'] ?? 0) . '%' }}</td>
                </tr>
                <tr>
                    <td>{{ __('Likes') }}</td>
                    <td class="highlight">{{ number_format($overview['likes']['value'] ?? 0) }}</td>
                    <td>{{ ($overview['likes']['change'] ?? 0) . '%' }}</td>
                </tr>
                <tr>
                    <td>{{ __('Comments') }}</td>
                    <td class="highlight">{{ number_format($overview['comments']['value'] ?? 0) }}</td>
                    <td>{{ ($overview['comments']['change'] ?? 0) . '%' }}</td>
                </tr>
                <tr>
                    <td>{{ __('Shares') }}</td>
                    <td class="highlight">{{ number_format($overview['shares']['value'] ?? 0) }}</td>
                    <td>{{ ($overview['shares']['change'] ?? 0) . '%' }}</td>
                </tr>
                <tr>
                    <td>{{ __('Views') }}</td>
                    <td class="highlight">{{ number_format($overview['views']['value'] ?? 0) }}</td>
                    <td>{{ ($overview['views']['change'] ?? 0) . '%' }}</td>
                </tr>
                <tr>
                    <td>{{ __('Published Posts') }}</td>
                    <td class="highlight">{{ number_format($overview['published_videos']['value'] ?? 0) }}</td>
                    <td>{{ ($overview['published_videos']['change'] ?? 0) . '%' }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h3>{{ __('Top Countries') }}</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('Country') }}</th>
                    <th>{{ __('Followers') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($analytics['topFollowerCountriesChartData']['top_countries'] ?? [] as $row)
                    <tr>
                        <td>{{ $row['country'] }}</td>
                        <td class="highlight">{{ number_format($row['followers']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <h3>{{ __('Top Cities') }}</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('City') }}</th>
                    <th>{{ __('Followers') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($analytics['topFollowerCitiesChartData']['top_cities'] ?? [] as $row)
                    <tr>
                        <td>{{ $row['city'] }}</td>
                        <td class="highlight">{{ number_format($row['followers']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <h3>{{ __('Followers by Age Group') }}</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('Age Group') }}</th>
                    <th>{{ __('Followers') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach (($analytics['followerAgeChartData']['summary'] ?? []) as $age => $count)
                    @if($age !== 'total' && $age !== 'latest_date')
                        <tr>
                            <td>{{ $age }}</td>
                            <td class="highlight">{{ number_format($count) }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <h3>{{ __('Followers by Gender') }}</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('Gender') }}</th>
                    <th>{{ __('Followers') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach (($analytics['followerGenderChartData']['summary'] ?? []) as $gender => $count)
                    @if($gender !== 'total' && $gender !== 'latest_date')
                        <tr>
                            <td>{{ ucfirst($gender) }}</td>
                            <td class="highlight">{{ number_format($count) }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <h3>{{ __('Reach Overview') }}</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Reach') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach (($analytics['dailyReachChartData']['categories'] ?? []) as $i => $day)
                    <tr>
                        <td>{{ $day }}</td>
                        <td class="highlight">{{ number_format($analytics['dailyReachChartData']['series'][0]['data'][$i] ?? 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="subtext">
            {{ __('Total Reach:') }} <strong>{{ number_format($analytics['dailyReachChartData']['summary']['total'] ?? 0) }}</strong>
        </div>
    </div>

    <div class="section">
        <h3>{{ __('Engagement Rate by Day') }}</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Engagement Rate (%)') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach (($analytics['dailyEngagementRateChartData']['categories'] ?? []) as $i => $day)
                    <tr>
                        <td>{{ $day }}</td>
                        <td class="highlight">{{ number_format($analytics['dailyEngagementRateChartData']['series'][0]['data'][$i] ?? 0, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="subtext">
            {{ __('Average Engagement Rate:') }} <strong>{{ number_format($analytics['dailyEngagementRateChartData']['summary']['avg_rate'] ?? 0, 2) }}%</strong>
        </div>
    </div>

    <div class="section">
        <h3>{{ __('Interactions by Day') }}</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Total Interactions') }}</th>
                    <th>{{ __('Likes') }}</th>
                    <th>{{ __('Comments') }}</th>
                    <th>{{ __('Shares') }}</th>
                    <th>{{ __('Saved') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach (($analytics['dailyInteractionsChartData']['categories'] ?? []) as $i => $day)
                    <tr>
                        <td>{{ $day }}</td>
                        <td class="highlight">{{ number_format($analytics['dailyInteractionsChartData']['series'][0]['data'][$i] ?? 0) }}</td>
                        <td>{{ number_format($analytics['dailyInteractionsChartData']['series'][1]['data'][$i] ?? 0) }}</td>
                        <td>{{ number_format($analytics['dailyInteractionsChartData']['series'][2]['data'][$i] ?? 0) }}</td>
                        <td>{{ number_format($analytics['dailyInteractionsChartData']['series'][3]['data'][$i] ?? 0) }}</td>
                        <td>{{ number_format($analytics['dailyInteractionsChartData']['series'][4]['data'][$i] ?? 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="subtext">
            {{ __('Total Interactions:') }} <strong>{{ number_format($analytics['dailyInteractionsChartData']['summary']['total_interactions'] ?? 0) }}</strong>
        </div>
    </div>

    <div class="section">
        <h3>{{ __('Followers by Day') }}</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Followers') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach (($analytics['dailyFollowersCountChartData']['categories'] ?? []) as $i => $day)
                    <tr>
                        <td>{{ $day }}</td>
                        <td class="highlight">{{ number_format($analytics['dailyFollowersCountChartData']['series'][0]['data'][$i] ?? 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="subtext">
            {{ __('Followers Growth:') }} <strong>{{ number_format($analytics['dailyFollowersCountChartData']['summary']['change'] ?? 0) }}</strong>,
            {{ __('Start:') }} <strong>{{ number_format($analytics['dailyFollowersCountChartData']['summary']['start'] ?? 0) }}</strong>,
            {{ __('End:') }} <strong>{{ number_format($analytics['dailyFollowersCountChartData']['summary']['end'] ?? 0) }}</strong>
        </div>
    </div>

    <div class="section">
        <h3>{{ __('Reach by Followers Type') }}</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('Reach') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($analytics['reachByFollowTypeData']['series'] ?? [] as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td class="highlight">{{ number_format($row['y']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="subtext">
            {{ __('Total:') }} <strong>{{ number_format($analytics['reachByFollowTypeData']['summary']['total'] ?? 0) }}</strong>
        </div>
    </div>

    <div class="section">
        <h3>{{ __('Post History') }}</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('Post ID') }}</th>
                    <th>{{ __('Created') }}</th>
                    <th>{{ __('Caption') }}</th>
                    <th>{{ __('Reach') }}</th>
                    <th>{{ __('Likes') }}</th>
                    <th>{{ __('Comments') }}</th>
                    <th>{{ __('Shares') }}</th>
                    <th>{{ __('Saved') }}</th>
                    <th>{{ __('Views') }}</th>
                    <th>{{ __('Interactions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($analytics['postHistoryList'] ?? [] as $post)
                    <tr>
                        <td>{{ $post['post_id'] ?? '-' }}</td>
                        <td>{{ $post['created_time'] ?? '-' }}</td>
                        <td>{{ Str::limit($post['caption'] ?? '', 50) }}</td>
                        <td>{{ number_format($post['metrics']['reach'] ?? 0) }}</td>
                        <td>{{ number_format($post['metrics']['likes'] ?? 0) }}</td>
                        <td>{{ number_format($post['metrics']['comments'] ?? 0) }}</td>
                        <td>{{ number_format($post['metrics']['shares'] ?? 0) }}</td>
                        <td>{{ number_format($post['metrics']['saved'] ?? 0) }}</td>
                        <td>{{ number_format($post['metrics']['views'] ?? 0) }}</td>
                        <td>{{ number_format($post['metrics']['total_interactions'] ?? 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</body>
</html>
