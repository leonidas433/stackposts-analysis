@extends('layouts.app')

@section('content')
@php
    $module = Module::find($account->module);
    $moduleInfo = $module->get('menu');
@endphp
<div class="border-bottom mb-5 pt-5 pb-2 bg-polygon">
    <div class="container">
        <div class="d-flex justify-content-center text-center">
            <div class="mb-5">
                <div class="size-80 size-chill position-relative mx-auto mb-3">
                    <img class="rounded-circle border mb-3 wp-100 hp-100" src="{{ Media::url($account->avatar) }}">
                    <div class="fs-12 position-absolute b-0 r-0 size-22 bg-white border b-r-100"><i class="{{ $moduleInfo['icon'] }}" style="color: {{ $moduleInfo['color'] }};"></i></div>
                </div>
                <div class="flex-fill mb-3">
                    <h4 class="mb-1 fs-20 fw-bold">{{ $account->name }}</h4>
                    <div class="text-muted small mb-1">{{ $analytics['account']['username'] ?? 'Unknown Category' }}</div>
                    <a href="{{ $account->url }}" class="small text-gray-600" target="_blank">{{ $account->url }}</a>
                </div>
                <div class="d-flex justify-content-center align-items-center gap-16">
                    <div class="fw-bold fs-16">{{ number_format($analytics['account']['followers_count'] ?? 0) }} {{ __('Followers') }}</div>
                    <div class="px-1 text-gray-500">|</div>
                    <div class="fw-bold fs-16">{{ number_format($analytics['account']['media_count'] ?? 0) }} {{ __('Posts') }}</div>
                </div>
            </div>
        </div>
    </div>

</div>

<div class="container py-4">

    <form class="auto-submit" action="{{ url()->current(); }}" method="GET">
        <div class="d-flex justify-content-end gap-8">
            <a  href="{{ route('analytics.export.pdf', [ 'social' => request()->segment(3), 'id_secure' => request()->segment(4) ]) }}" class="btn btn-dark exportPDF">{{ __("Export PDF") }}</a>
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div></div>
                <div class="d-flex align-items-center justify-content-between gap-8">
                    <div>
                        <div class="daterange d-none bg-white b-r-4 fs-12 border-gray-300 border" data-open="left"></div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div class="row g-3 mb-4">
        @php
            $overview = $analytics['overview'];
            $icons = [
                'likes' => 'fa-light fa-thumbs-up', 
                'reach' => 'fa-light fa-eye',
                'shares' => 'fa-light fa-repeat', 
                'comments' => 'fa-light fa-comment',
                'views' => 'fa-light fa-binoculars', 
                'published_videos' => 'fa-light fa-paper-plane',
            ];
            $colors = [
                'likes' => 'primary', 
                'reach' => 'success',
                'comments' => 'warning', 
                'shares' => 'danger',
                'views' => 'dark', 
                'published_videos' => 'pink',
            ];
        @endphp

        @foreach ($overview as $key => $item)
            @php
                $icon = $icons[$key] ?? '📊';
                $color = $colors[$key] ?? 'muted';
                $change = $item['change'] ?? 0;
                $changeClass = $change > 0 ? 'text-success' : ($change < 0 ? 'text-danger' : 'text-muted');
                $changeLabel = $change > 0 ? '+' . $change . '%' : ($change < 0 ? $change . '%' : '0%');
            @endphp
            <div class="col-6 col-md-4 col-lg-4">
                <div class="card hp-100">
                    <div class="card-body">
                        
                        <div class="d-flex align-items-center gap-8">
                            <div class="size-35 d-flex align-items-center justify-content-center b-r-100 bg-{{ $color }}-100 text-{{ $color }}">
                                <span><i class="{{ $icon }}"></i></span>
                            </div>
                            <span class="text-gray-700">{{ __(ucwords(str_replace('_', ' ', $key))) }}</span>
                        </div>

                        <div class="d-flex flex-column justify-content-center pt-3">
                            <div class="text-muted small"></div>
                            <div class="fw-bold fs-30 mb-3">{{ number_format($item['value']) }}</div>
                            <div class="small {{ $changeClass }}">
                                <i class="{{ $change >= 0 ? 'fa-light fa-arrow-trend-up' : 'fa-light fa-arrow-trend-down' }}"></i> {{ $changeLabel }} {{ __('vs last period') }}
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        @endforeach

        
        @php $summary = $analytics['dailyFollowersCountChartData']['summary']; @endphp
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="fs-5 fs-16">{{ __('Follower by Day') }}</h5>
                </div>
                <div class="card-body border-bottom">
                    <div id="dailyFollowersCountChart" class="export-chart" style="height: 300px;"></div>
                    <div class="text-gray-500 fs-12 text-center">{{ __('Followers by Day statistics are only available for Instagram Business or Creator accounts with at least 100 followers') }}</div>
                </div>
                <div class="d-flex card-body p-0">
                    <div class="flex-fill px-4 py-3 border-end">
                        <div class="text-gray-500 fs-13 mb-2">{{ __('Start') }}</div>
                        <div class="text-gray-800 fs-25 fw-bold">
                            {{ number_format($summary['start']) }}
                        </div>
                    </div>
                    <div class="flex-fill px-4 py-3 border-end">
                        <div class="text-gray-500 fs-13 mb-2">{{ __('End') }}</div>
                        <div class="text-gray-800 fs-25 fw-bold">
                            {{ number_format($summary['end']) }}
                        </div>
                    </div>
                    <div class="flex-fill px-4 py-3">
                        <div class="text-gray-500 fs-14 mb-2">{{ __('Net Followers') }}</div>
                        <div class="fs-25 fw-bold {{ $summary['change'] >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $summary['change'] >= 0 ? '+' : '' }}{{ number_format($summary['change']) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @php $summary = $analytics['dailyViewsChartData']['summary']; @endphp
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="fs-5 fs-16">{{ __('Views') }}</h5>
                </div>
                <div class="card-body border-bottom">
                    <div id="dailyViewsChart" class="export-chart" style="height: 300px;"></div>
                </div>
                <div class="d-flex card-body p-0">
                    <div class="flex-fill px-4 py-3">
                        <div class="text-gray-500 fs-14 mb-2">{{ __('Total Views') }}</div>
                        <div class="text-gray-800 fs-25 fw-bold">{{ number_format($summary['total']) }}</div>
                    </div>
                </div>
            </div>
        </div>

        @php $summary = $analytics['dailyEngagementRateChartData']['summary']; @endphp
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="fs-5 fs-16">{{ __('Engagement Rate') }}</h5>
                </div>
                <div class="card-body border-bottom">
                    <div id="dailyEngagementRateChart" class="export-chart" style="height: 300px;"></div>
                </div>

                <div class="d-flex card-body p-0">
                    <div class="flex-fill px-4 py-3 border-end">
                        <div class="text-gray-500 fs-14 mb-2">{{ __('Avg. Rate') }}</div>
                        <div class="text-gray-800 fs-25 fw-bold">{{ number_format($summary['avg_rate']) }}%</div>
                    </div>
                </div>
            </div>
        </div>

        @php $summary = $analytics['dailyReachChartData']['summary']; @endphp
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="fs-5 fs-16">{{ __('Media Reach by Day') }}</h5>
                </div>
                <div class="card-body border-bottom">
                    <div id="dailyReachChart" class="export-chart" style="height: 340px;"></div>
                </div>

                <div class="d-flex card-body p-0">
                    <div class="flex-fill px-4 py-3 border-end">
                        <div class="text-gray-500 fs-14 mb-2">{{ __('Total Reach') }}</div>
                        <div class="text-gray-800 fs-25 fw-bold">{{ number_format($summary['total']) }}</div>
                    </div>
                </div>
            </div>
        </div>

        @php $summary = $analytics['dailyInteractionsChartData']['summary']; @endphp
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="fs-5 fs-16">{{ __('Interactions') }}</h5>
                </div>
                <div class="card-body border-bottom">
                    <div id="dailyInteractionsChart" class="export-chart" style="height: 300px;"></div>
                </div>
                <div class="d-flex card-body p-0">
                    <div class="flex-fill px-4 py-3 border-end">
                        <div class="text-gray-500 fs-14 mb-2">{{ __('Total Interactions') }}</div>
                        <div class="text-gray-800 fs-25 fw-bold">{{ number_format($summary['total_interactions']) }}</div>
                    </div>
                    <div class="flex-fill px-4 py-3 border-end">
                        <div class="text-gray-500 fs-14 mb-2">{{ __('Likes') }}</div>
                        <div class="text-gray-800 fs-25 fw-bold">{{ number_format($summary['likes']) }}</div>
                    </div>
                    <div class="flex-fill px-4 py-3 border-end">
                        <div class="text-gray-500 fs-14 mb-2">{{ __('Comments') }}</div>
                        <div class="text-gray-800 fs-25 fw-bold">{{ number_format($summary['comments']) }}</div>
                    </div>
                    <div class="flex-fill px-4 py-3 border-end">
                        <div class="text-gray-500 fs-14 mb-2">{{ __('Shares') }}</div>
                        <div class="text-gray-800 fs-25 fw-bold">{{ number_format($summary['shares']) }}</div>
                    </div>
                    <div class="flex-fill px-4 py-3">
                        <div class="text-gray-500 fs-14 mb-2">{{ __('Saved') }}</div>
                        <div class="text-gray-800 fs-25 fw-bold">{{ number_format($summary['saved']) }}</div>
                    </div>
                </div>
            </div>
        </div>

        @php $summary = $analytics['dailyAccountReachChartData']['summary']; @endphp
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="fs-5 fs-16">{{ __('Account Reach by Day') }}</h5>
                </div>
                <div class="card-body border-bottom">
                    <div id="dailyAccountReachChart" class="export-chart" style="height: 340px;"></div>
                </div>

                <div class="d-flex card-body p-0">
                    <div class="flex-fill px-4 py-3 border-end">
                        <div class="text-gray-500 fs-14 mb-2">{{ __('Total Account Reach') }}</div>
                        <div class="text-gray-800 fs-25 fw-bold">{{ number_format($summary['total']) }}</div>
                    </div>
                </div>
            </div>
        </div>

        @php $summary = $analytics['reachByFollowTypeData']['summary']; @endphp
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="fs-5 fs-16 mb-0">{{ __('Reach by Followers Type') }}</h5>
                </div>
                <div class="card-body border-bottom">
                    <div id="reachByFollowTypeChart" style="height: 300px"></div>
                    <div class="text-gray-500 fs-12 text-center">{{ __('The statistics shown are based on the most recent 30 days as of the last update.') }}</div>
                    <div class="text-gray-500 fs-12 text-center">{{ __('Last update:') }} {{ date_show($summary['latest_date']??now()) }}</div>
                </div>
                <div class="d-flex card-body p-0">
                    <div class="flex-fill px-4 py-3 border-end">
                        <div class="text-gray-500 fs-14 mb-2">{{ __('Accounts Reached') }}</div>
                        <div class="text-gray-800 fs-25 fw-bold">{{ number_format($summary['total']) }}</div>
                    </div>
                </div>
            </div>
        </div>

        @php $summary = $analytics['followerAgeChartData']['summary']; @endphp
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="fs-5 fs-16">{{ __('Followers by Age Group') }}</h5>
                </div>
                <div class="card-body">
                    <div id="followerAgeChart" class="export-chart" style="height: 300px;"></div>
                    <div class="text-gray-500 fs-12 text-center">{{ __('The statistics shown are based on the most recent 30 days as of the last update.') }}</div>
                    <div class="text-gray-500 fs-12 text-center">{{ __('Last update:') }} {{ date_show($summary['latest_date']??now()) }}</div>
                </div>
            </div>
        </div>

        @php $summary = $analytics['followerGenderChartData']['summary']; @endphp
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="fs-5 fs-16">{{ __('Followers by Gender') }}</h5>
                </div>
                <div class="card-body">
                    <div id="followerGenderChart" class="export-chart" style="height: 300px;"></div>
                    <div class="text-gray-500 fs-12 text-center">{{ __('The statistics shown are based on the most recent 30 days as of the last update.') }}</div>
                    <div class="text-gray-500 fs-12 text-center">{{ __('Last update:') }} {{ date_show($summary['latest_date']??now()) }}</div>
                </div>
            </div>
        </div>

        @php $summary = $analytics['topFollowerCountriesChartData']['summary']; @endphp
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="fs-5 fs-16">{{ __('Follower Location') }}</h5>
                </div>
                <div class="card-body">
                    <div id="topFollowerCountriesChart" class="export-chart" style="height: 510px;"></div>
                    <div class="text-gray-500 fs-12 text-center">{{ __('The statistics shown are based on the most recent 30 days as of the last update.') }}</div>
                    <div class="text-gray-500 fs-12 text-center">{{ __('Last update:') }} {{ date_show($summary['latest_date']??now()) }}</div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="fs-5 fs-16 mb-0">{{ __('Top coutries') }}</h5>
                </div>
                <div class="card-body">
                    <div id="topCoutriesChart" style="height: 370px"></div>
                    <div class="text-gray-500 fs-12 text-center">{{ __('The statistics shown are based on the most recent 30 days as of the last update.') }}</div>
                    <div class="text-gray-500 fs-12 text-center">{{ __('Last update:') }} {{ date_show($summary['latest_date']??now()) }}</div>
                </div>
            </div>
        </div>

        @php $summary = $analytics['topFollowerCitiesChartData']['summary']; @endphp
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="fs-5 fs-16 mb-0">{{ __('Top Cities') }}</h5>
                </div>
                <div class="card-body">
                    <div id="topCitiesChart" style="height: 370px"></div>
                    <div class="text-gray-500 fs-12 text-center">{{ __('The statistics shown are based on the most recent 30 days as of the last update.') }}</div>
                    <div class="text-gray-500 fs-12 text-center">{{ __('Last update:') }} {{ date_show($summary['latest_date']??now()) }}</div>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            @php $posts = $analytics['postHistoryList']; @endphp
            <div class="card px-0">
                <div class="card-header">
                    <h5 class="fs-5 fs-16">{{ __('Post History') }}</h5>

                    <div class="card-tooltip">
                        <div class="d-flex flex-wrap gap-8">
                            <div class="d-flex">
                                <div class="form-control form-control-sm">
                                    <button class="btn btn-icon">
                                        <i class="fa-duotone fa-solid fa-magnifying-glass"></i>
                                    </button>
                                    <input name="search" placeholder="{{ __('Search...') }}" type="text"/>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle" id="DataTable_Static">
                            <thead class="table-light text-center">
                                <tr>
                                    <th style="width: 60px;">{{ __('Image') }}</th>
                                    <th>{{ __('Message') }}</th>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Reach') }}</th>
                                    <th>{{ __('Total Interactions') }}</th>
                                    <th>{{ __('Likes') }}</th>
                                    <th>{{ __('Comments') }}</th>
                                    <th>{{ __('Shares') }}</th>
                                    <th>{{ __('Saved') }}</th>
                                    <th>{{ __('View') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($posts as $post)
                                    <tr>
                                        {{-- Image or icon --}}
                                        <td class="text-center">
                                            @if (!empty($post['full_picture']))
                                                <img src="{{ Media::url($post['full_picture']) }}" class="rounded" style="width: 48px; height: 48px; object-fit: cover;">
                                            @else
                                                <div class="d-flex align-items-center justify-content-center bg-light border rounded" style="width: 48px; height: 48px;">
                                                    <i class="fa-light fa-message-lines text-gray-600 fs-4"></i>
                                                </div>
                                            @endif
                                        </td>

                                        {{-- Message --}}
                                        <td>{{ \Str::limit($post['caption'], 80) }}</td>

                                        {{-- Date --}}
                                        <td class="text-nowrap text-gray-700 fs-14">{{ \Carbon\Carbon::parse($post['created_time'])->format('M d, Y') }}</td>

                                        {{-- Metrics --}}
                                        <td class="text-center text-info">{{ number_format($post['metrics']['reach'] ?? 0) }}</td>
                                        <td class="text-center text-primary">{{ number_format($post['metrics']['total_interactions'] ?? 0) }}</td>
                                        <td class="text-center text-success">{{ number_format($post['metrics']['likes'] ?? 0) }}</td>
                                        <td class="text-center text-danger">{{ number_format($post['metrics']['comments'] ?? 0) }}</td>
                                        <td class="text-center text-warning">{{ number_format($post['metrics']['shares'] ?? 0) }}</td>
                                        <td class="text-center text-dark">{{ number_format($post['metrics']['saved'] ?? 0) }}</td>

                                        {{-- View button --}}
                                        <td class="text-center">
                                            @if (!empty($post['permalink_url']))
                                                <a href="{{ $post['permalink_url'] }}" target="_blank">
                                                    <i class="fas fa-external-link-alt"></i>
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>


    </div>

</div>
@endsection

@section('script')
<script type="text/javascript">
var dailyViewsChartData = {!! json_encode($analytics['dailyViewsChartData']) !!};
Main.Chart('areaspline', dailyViewsChartData.series, 'dailyViewsChart', {
    xAxis: {
        categories: dailyViewsChartData.categories,
        title: { text: '' },
        crosshair: { width: 2, color: '#ddd', dashStyle: 'Solid' },
        labels: {
            rotation: 0,
            useHTML: true,
            formatter: function () {
                const pos = this.pos;
                const total = this.axis.categories.length;
                if (pos === 0)
                    return `<div style="text-align:left;transform:translateX(60px);width:140px;">${this.value}</div>`;
                else if (pos === total - 1)
                    return `<div style="text-align:right;transform:translateX(-55px);width:140px;">${this.value}</div>`;
                return '';
            },
            style: {
                fontSize: '13px',
                whiteSpace: 'nowrap'
            },
            overflow: 'none',
            crop: false
        }
    },
    yAxis: {
        title: { text: '' },
        gridLineColor: '#f3f4f6',
        gridLineDashStyle: 'Dash',
        gridLineWidth: 1
    },
    title: { text: '{{ __("Page Views") }}' },
    legend: { enabled: false },
    plotOptions: {
        areaspline: {
            fillOpacity: 0.1,
            lineWidth: 3,
            marker: { enabled: false }
        },
        series: {
            color: '#675dff',
            fillColor: {
                linearGradient: [0, 0, 0, 200],
                stops: [
                    [0, 'rgba(103, 93, 255, 0.4)'],
                    [1, 'rgba(255, 255, 255, 0)']
                ]
            }
        }
    }
});

var dailyFollowersCountChartData = {!! json_encode($analytics['dailyFollowersCountChartData']) !!};
Main.Chart('areaspline', dailyFollowersCountChartData.series, 'dailyFollowersCountChart', {
    xAxis: {
        categories: dailyFollowersCountChartData.categories,
        title: { text: '' },
        crosshair: { width: 2, color: '#ddd', dashStyle: 'Solid' },
        labels: {
            rotation: 0,
            useHTML: true,
            formatter: function () {
                const pos = this.pos;
                const total = this.axis.categories.length;
                if (pos === 0)
                    return `<div style="text-align:left;transform:translateX(60px);width:140px;">${this.value}</div>`;
                else if (pos === total - 1)
                    return `<div style="text-align:right;transform:translateX(-55px);width:140px;">${this.value}</div>`;
                return '';
            },
            style: {
                fontSize: '13px',
                whiteSpace: 'nowrap'
            },
            overflow: 'none',
            crop: false
        }
    },
    yAxis: {
        title: { text: '' },
        gridLineColor: '#f3f4f6',
        gridLineDashStyle: 'Dash',
        gridLineWidth: 1
    },
    title: { text: '{{ __("Page Views") }}' },
    legend: { enabled: false },
    plotOptions: {
        areaspline: {
            fillOpacity: 0.1,
            lineWidth: 3,
            marker: { enabled: false }
        },
        series: {
            color: '#675dff',
            fillColor: {
                linearGradient: [0, 0, 0, 200],
                stops: [
                    [0, 'rgba(103, 93, 255, 0.4)'],
                    [1, 'rgba(255, 255, 255, 0)']
                ]
            }
        }
    }
});

var dailyInteractionsChartData = {!! json_encode($analytics['dailyInteractionsChartData']) !!};
dailyInteractionsChartData.series[0].color = '#675dff';
dailyInteractionsChartData.series[1].color = '#13c2c2';
dailyInteractionsChartData.series[2].color = '#ffa940';
dailyInteractionsChartData.series[3].color = '#f5222d';
dailyInteractionsChartData.series[4].color = '#52c41a';
Main.Chart("mix", dailyInteractionsChartData.series, 'dailyInteractionsChart', {
    chart: { zoomType: 'xy' },
    title: { text: '{{ __("Interactions") }}' },
    xAxis: {
        categories: dailyInteractionsChartData.categories,
        labels: {
            rotation: 0,
            useHTML: true,
            formatter: function () {
                const pos = this.pos;
                const total = this.axis.categories.length;
                if (pos === 0)
                    return `<div style="text-align:left;transform:translateX(60px);width:140px;">${this.value}</div>`;
                else if (pos === total - 1)
                    return `<div style="text-align:right;transform:translateX(-55px);width:140px;">${this.value}</div>`;
                return '';
            },
            style: {
                fontSize: '13px',
                whiteSpace: 'nowrap'
            },
            overflow: 'none',
            crop: false
        }
    },
    yAxis: [
        {
            title: { text: '' },
            labels: { format: '{value}' }
        },
        {
            title: { text: '' },
            labels: { format: '{value}%' },
            opposite: true
        }
    ],
    tooltip: {
        shared: true,
        useHTML: true
    },
    plotOptions: {
        column: { dataLabels: { enabled: false } },
        spline: {
            lineWidth: 2,
            marker: { enabled: false }
        }
    }
});

var dailyAccountReachChartData = {!! json_encode($analytics['dailyAccountReachChartData']) !!};
Main.Chart('column', dailyAccountReachChartData.series, 'dailyAccountReachChart', {
    title: { text: '{{ __("Account Reach") }}' },
    xAxis: {
        categories: dailyAccountReachChartData.categories,
        lineColor: '#ddd',
        labels: {
            rotation: 0,
            useHTML: true,
            formatter: function () {
                const pos = this.pos;
                const total = this.axis.categories.length;
                if (pos === 0)
                    return `<div style="text-align:left;transform:translateX(60px);width:140px;">${this.value}</div>`;
                else if (pos === total - 1)
                    return `<div style="text-align:right;transform:translateX(-55px);width:140px;">${this.value}</div>`;
                return '';
            },
            style: {
                fontSize: '13px',
                whiteSpace: 'nowrap'
            },
            overflow: 'none',
            crop: false
        }
    },
    yAxis: {
        title: { text: '' },
        gridLineColor: '#f3f4f6',
        gridLineDashStyle: 'Dash'
    },
    legend: { enabled: false },
    tooltip: {
        shared: true,
        valueSuffix: ' {{ __('reach') }}'
    },
    plotOptions: {
        column: {
            borderRadius: 6,
            colorByPoint: true,
            dataLabels: {
                enabled: false,
                formatter: function () {
                    return this.y.toLocaleString();
                }
            }
        }
    }
});

var dailyReachChartData = {!! json_encode($analytics['dailyReachChartData']) !!};
Main.Chart('column', dailyReachChartData.series, 'dailyReachChart', {
    title: { text: '{{ __("Reach") }}' },
    xAxis: {
        categories: dailyReachChartData.categories,
        lineColor: '#ddd',
        labels: {
            rotation: 0,
            useHTML: true,
            formatter: function () {
                const pos = this.pos;
                const total = this.axis.categories.length;
                if (pos === 0)
                    return `<div style="text-align:left;transform:translateX(60px);width:140px;">${this.value}</div>`;
                else if (pos === total - 1)
                    return `<div style="text-align:right;transform:translateX(-55px);width:140px;">${this.value}</div>`;
                return '';
            },
            style: {
                fontSize: '13px',
                whiteSpace: 'nowrap'
            },
            overflow: 'none',
            crop: false
        }
    },
    yAxis: {
        title: { text: '' },
        gridLineColor: '#f3f4f6',
        gridLineDashStyle: 'Dash'
    },
    legend: { enabled: false },
    tooltip: {
        shared: true,
        valueSuffix: ' {{ __('reach') }}'
    },
    plotOptions: {
        column: {
            borderRadius: 6,
            colorByPoint: true,
            dataLabels: {
                enabled: false,
                formatter: function () {
                    return this.y.toLocaleString();
                }
            }
        }
    }
});

var followerAgeChartData = {!! json_encode($analytics['followerAgeChartData']) !!};
Main.Chart('column', followerAgeChartData.series, 'followerAgeChart', {
    title: { text: '{{ __("Followers by Age Group") }}' },
    xAxis: {
        categories: followerAgeChartData.categories,
        lineColor: '#ddd',
        labels: {
            rotation: 0,
            useHTML: true,
            style: {
                fontSize: '13px',
                whiteSpace: 'nowrap'
            },
            overflow: 'none',
            crop: false
        }
    },
    yAxis: {
        title: { text: '' },
        gridLineColor: '#f3f4f6',
        gridLineDashStyle: 'Dash'
    },
    legend: { enabled: false },
    tooltip: {
        shared: true,
        valueSuffix: ' {{ __('reach') }}'
    },
    plotOptions: {
        column: {
            borderRadius: 6,
            colorByPoint: true,
            dataLabels: {
                enabled: false,
                formatter: function () {
                    return this.y.toLocaleString();
                }
            }
        }
    }
});

var dailyEngagementRateChartData = {!! json_encode($analytics['dailyEngagementRateChartData']) !!};
Main.Chart('areaspline', dailyEngagementRateChartData.series, 'dailyEngagementRateChart', {
    title: { text: '{{ __("Engagement Rate") }}' },
    xAxis: {
        categories: dailyEngagementRateChartData.categories,
        title: { text: '' },
        crosshair: { width: 2, color: '#ddd', dashStyle: 'Solid' },
        labels: {
            rotation: 0,
            useHTML: true,
            formatter: function () {
                const pos = this.pos;
                const total = this.axis.categories.length;
                if (pos === 0)
                    return `<div style="text-align:left;transform:translateX(60px);width:140px;">${this.value}</div>`;
                else if (pos === total - 1)
                    return `<div style="text-align:right;transform:translateX(-55px);width:140px;">${this.value}</div>`;
                return '';
            },
            style: {
                fontSize: '13px',
                whiteSpace: 'nowrap'
            },
            overflow: 'none',
            crop: false
        }
    },
    yAxis: {
        title: { text: '' },
        gridLineColor: '#f3f4f6',
        gridLineDashStyle: 'Dash',
        gridLineWidth: 1
    },
    title: { text: '{{ __("Engagement Rate") }}' },
    legend: { enabled: false },
    plotOptions: {
        areaspline: {
            fillOpacity: 0.1,
            lineWidth: 3,
            marker: { enabled: false }
        },
        series: {
            color: '#675dff',
            fillColor: {
                linearGradient: [0, 0, 0, 200],
                stops: [
                    [0, 'rgba(103, 93, 255, 0.4)'],
                    [1, 'rgba(255, 255, 255, 0)']
                ]
            }
        }
    }
});

const topFollowerCountriesChartData = {!! json_encode($analytics['topFollowerCountriesChartData']['map_data']) !!};
Main.Chart('map', topFollowerCountriesChartData, 'topFollowerCountriesChart', {
    chart: { map: 'custom/world' },
    title: { text: '{{ __('Fan Locations') }}' },
    colorAxis: { min: 0,  minColor: '#ede9fe', maxColor: '#675dff' },
    tooltip: {
        formatter: function () {
            const label = this.point.name || this.key || '{{ __('Unknown') }}';
            const value = this.point.value?.toLocaleString?.() ?? '0';

            return `<div class="d-flex gap-8 justify-content-between align-items-center" style="padding: 4px 12px;">
                <span class="fs-12"><span style="color: ${this.point.color};">●</span> ${label}:</span>
                <span class="fs-12 fw-6">${value}</span>
            </div>`;
        }
    }
});

const followerCountriesChartData = {!! json_encode($analytics['topFollowerCountriesChartData']) !!};
Main.Chart('bar', followerCountriesChartData.series, 'topCoutriesChart', {
    title: { text: '{{ __("Top Countries") }}' },
    xAxis: {
        categories: followerCountriesChartData.categories,
        labels: {
            style: { fontSize: '13px', color: '#333' }
        }
    },
    yAxis: {
        title: { text: '' },
        max: 100,
        labels: {
            formatter: function () { return this.value + '%'; }
        }
    },
    legend: { enabled: false },
    tooltip: {
        pointFormatter: function() {
            return `<b>${this.count} (${this.y}%)</b>`;
        }
    },
    plotOptions: {
        bar: {
            dataLabels: {
                enabled: true,
                formatter: function () {
                    return this.y + '%';
                }
            }
        }
    }
});

var topFollowerCitiesChartData = {!! json_encode($analytics['topFollowerCitiesChartData']) !!};
Main.Chart('bar', topFollowerCitiesChartData.series, 'topCitiesChart', {
    title: { text: '{{ __("Top Cities") }}' },
    xAxis: {
        categories: topFollowerCitiesChartData.categories,
        labels: {
            style: { fontSize: '13px', color: '#333' }
        }
    },
    yAxis: {
        title: { text: '' }, 
        max: 100,
        labels: {
            formatter: function () { return this.value + '%'; }
        }
    },
    legend: { enabled: false },
    tooltip: {
        pointFormatter: function() {
            return `<b>${this.count} (${this.y}%)</b>`;
        }
    },
    plotOptions: {
        bar: {
            dataLabels: {
                enabled: true,
                formatter: function () {
                    return this.y + '%';
                }
            }
        }
    }
});

var reachByFollowTypeData = {!! json_encode($analytics['reachByFollowTypeData']) !!};
Main.Chart('pie', reachByFollowTypeData.series, 'reachByFollowTypeChart', {
    title: { text: '{{ __("Reach by Followers Type") }}' },
    plotOptions: {
        pie: {
            startAngle: -90,
            endAngle: 90,
            center: ['50%', '75%'],
            size: '110%',
            innerSize: '60%',
            dataLabels: { enabled: true }
        }
    },
    legend: { enabled: true }
});

var followerGenderChartData = {!! json_encode($analytics['followerGenderChartData']) !!};
Main.Chart('pie', followerGenderChartData.series, 'followerGenderChart', {
    title: { text: '{{ __("Followers by Gender") }}' },
    plotOptions: {
        pie: {
            startAngle: -90,
            endAngle: 90,
            center: ['50%', '75%'],
            size: '110%',
            innerSize: '60%',
            dataLabels: { enabled: true }
        }
    },
    legend: { enabled: true }
});
</script>
@endsection