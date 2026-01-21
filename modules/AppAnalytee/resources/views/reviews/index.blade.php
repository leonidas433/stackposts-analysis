@extends('layouts.app')

@section('sub_header')
    <x-sub-header
        title="{{ __('Gestión de reseñas') }}"
        description="{{ __('Visualiza y sincroniza reseñas de Google Business Profile') }}"
        :count="(int) ($totalFiltered ?? 0)"
    >
        @php
            $canSync = ! empty($placeId);
            $reportFiles = is_array($reportFiles ?? null) ? $reportFiles : [];
            $canExportPdf = ! empty($reportFiles['pdf'] ?? '');
            $canExportDocx = ! empty($reportFiles['docx'] ?? '');
            $datasetPrepared = (bool) ($datasetPrepared ?? false);
            $analysisNotExecuted = (bool) ($analysisNotExecuted ?? true);
            $canPrepareDataset = $canSync;
            $canRunAnalysis = $canSync && $datasetPrepared;
            $analysisStatus = (string) ($analysisStatus ?? '');
            $analysisArtifactsExist = is_array($analysisArtifactsExist ?? null) ? $analysisArtifactsExist : [];
        @endphp
        <div class="d-flex gap-8 flex-wrap">
            <form method="post" action="{{ route('app.analytee.reviews.sync', ['account_id' => $accountId]) }}">
                @csrf
                <button class="btn btn-primary btn-sm {{ $canSync ? '' : 'disabled' }}" type="submit" {{ $canSync ? '' : 'disabled' }} title="{{ $canSync ? '' : 'Este negocio necesita place_id para sincronizar' }}">
                    <i class="fa-solid fa-rotate me-2"></i>
                    {{ __('Sincronizar (GBP)') }}
                </button>
            </form>
            <form method="post" action="{{ route('app.analytee.reviews.prepare-input', ['account_id' => $accountId]) }}">
                @csrf
                <button class="btn btn-outline btn-primary btn-sm {{ $canPrepareDataset ? '' : 'disabled' }}" type="submit" {{ $canPrepareDataset ? '' : 'disabled' }}>
                    <i class="fa-solid fa-database me-2"></i>
                    {{ __('Preparar dataset') }}
                </button>
            </form>
            <form method="post" action="{{ route('app.analytee.reviews.run-engine', ['account_id' => $accountId]) }}">
                @csrf
                <button class="btn btn-outline btn-primary btn-sm {{ $canRunAnalysis ? '' : 'disabled' }}" type="submit" {{ $canRunAnalysis ? '' : 'disabled' }}>
                    <i class="fa-solid fa-play me-2"></i>
                    {{ $analysisNotExecuted ? __('Ejecutar análisis') : __('Re-ejecutar análisis') }}
                </button>
            </form>
            <a class="btn btn-outline btn-light btn-sm {{ $canExportPdf ? '' : 'disabled' }}" href="{{ $canExportPdf ? route('app.analytee.reviews.report', ['account_id' => $accountId, 'kind' => 'pdf']) : '#' }}">
                <i class="fa-solid fa-download me-2"></i>
                {{ __('Exportar PDF') }}
            </a>
            <a class="btn btn-outline btn-light btn-sm {{ $canExportDocx ? '' : 'disabled' }}" href="{{ $canExportDocx ? route('app.analytee.reviews.report', ['account_id' => $accountId, 'kind' => 'docx']) : '#' }}">
                <i class="fa-solid fa-file-word me-2"></i>
                {{ __('Exportar DOCX') }}
            </a>
            <button class="btn btn-outline btn-primary btn-sm" type="button">
                <i class="fa-solid fa-filter me-2"></i>
                {{ __('Filtros avanzados') }}
            </button>
        </div>
    </x-sub-header>
@endsection

@section('content')
    @php
        $filters = $filters ?? ['period' => '30d', 'ratings' => [], 'languages' => [], 'q' => '', 'limit' => 10];
        $ratingsFilter = $filters['ratings'] ?? [];
        $languagesFilter = $filters['languages'] ?? [];
        $allRatingsChecked = empty($ratingsFilter);
        $allLanguagesChecked = empty($languagesFilter);

        $ratingCounts = $ratingCounts ?? collect();
        $languageCounts = $languageCounts ?? collect();

        $avgRating = $avgRating ?? null;
        $totalAll = $totalAll ?? null;
        $totalFiltered = $totalFiltered ?? null;

        $golden = is_array($golden ?? null) ? $golden : [];
        $goldenAddress = (string) (($address ?? null) ?? '');
        $goldenCategory = (string) (($category ?? null) ?? '');
    @endphp

    <div class="container-fluid pb-5">
        <div class="row g-4">
            <div class="col-12 col-lg-4 col-xl-3">
                <div class="card shadow-none border-gray-300">
                    <div class="card-header">
                        <div class="fw-6">{{ __('Negocio actual') }}</div>
                    </div>
                    <div class="card-body">
                        <div class="bg-gray-100 border-gray-200 border rounded p-3">
                            <div class="fw-6 text-gray-900 mb-1">{{ $account->name ?? 'Negocio no disponible' }}</div>
                            @if ($goldenAddress !== '' || $goldenCategory !== '')
                                <div class="fs-12 text-gray-600 mb-2">
                                    @if ($goldenAddress !== '')
                                        <div class="d-flex align-items-start gap-6">
                                            <i class="fa-solid fa-location-dot mt-1"></i>
                                            <span>{{ $goldenAddress }}</span>
                                        </div>
                                    @endif
                                    @if ($goldenCategory !== '')
                                        <div class="d-flex align-items-start gap-6 mt-1">
                                            <i class="fa-solid fa-tag mt-1"></i>
                                            <span>{{ $goldenCategory }}</span>
                                        </div>
                                    @endif
                                </div>
                            @endif
                            <div class="d-flex align-items-center gap-8 fs-14 text-gray-600 mb-2">
                                <span class="d-flex align-items-center gap-6">
                                    <i class="fa-solid fa-star text-yellow-400"></i>
                                    <span class="fw-6">{{ ! is_numeric($avgRating) ? '—' : number_format((float) $avgRating, 1) }}</span>
                                </span>
                                <span class="text-gray-500">·</span>
                                <span>{{ ! is_numeric($totalAll) ? '—' : number_format((float) $totalAll, 0, ',', '.') }} {{ __('reseñas') }}</span>
                            </div>
                            <div class="d-flex align-items-center gap-6 fs-12 text-gray-600">
                                <i class="fa-solid fa-chart-line"></i>
                                <span>{{ __('Tendencia: pendiente') }}</span>
                            </div>
                            <div class="d-flex align-items-center gap-6 fs-12 text-gray-600 mt-2">
                                <i class="fa-solid fa-microchip"></i>
                                <span>
                                    {{ __('Análisis:') }}
                                    @if ($analysisStatus !== '')
                                        <span class="fw-6">{{ $analysisStatus }}</span>
                                    @else
                                        —
                                    @endif
                                </span>
                            </div>
                            <div class="d-flex flex-wrap gap-6 mt-2">
                                <a class="btn btn-light btn-xs {{ ! ($analysisArtifactsExist['input'] ?? false) ? 'disabled' : '' }}" href="{{ ($analysisArtifactsExist['input'] ?? false) ? route('app.analytee.reviews.report', ['account_id' => $accountId, 'kind' => 'input']) : '#' }}">
                                    {{ __('input.json') }}
                                </a>
                                <a class="btn btn-light btn-xs {{ ! ($analysisArtifactsExist['last_run'] ?? false) ? 'disabled' : '' }}" href="{{ ($analysisArtifactsExist['last_run'] ?? false) ? route('app.analytee.reviews.report', ['account_id' => $accountId, 'kind' => 'last-run']) : '#' }}">
                                    {{ __('last_run.json') }}
                                </a>
                                <a class="btn btn-light btn-xs {{ ! ($analysisArtifactsExist['engine_stdout'] ?? false) ? 'disabled' : '' }}" href="{{ ($analysisArtifactsExist['engine_stdout'] ?? false) ? route('app.analytee.reviews.report', ['account_id' => $accountId, 'kind' => 'engine-stdout']) : '#' }}">
                                    {{ __('stdout') }}
                                </a>
                                <a class="btn btn-light btn-xs {{ ! ($analysisArtifactsExist['engine_stderr'] ?? false) ? 'disabled' : '' }}" href="{{ ($analysisArtifactsExist['engine_stderr'] ?? false) ? route('app.analytee.reviews.report', ['account_id' => $accountId, 'kind' => 'engine-stderr']) : '#' }}">
                                    {{ __('stderr') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-none border-gray-300 mt-4">
                    <div class="card-header">
                        <div class="fw-6">{{ __('Filtros') }}</div>
                    </div>
                    <div class="card-body">
                        <form method="get" action="{{ url()->current() }}">
                            <div class="mb-4">
                                <label class="form-label fw-6">{{ __('Valoración') }}</label>
                                <div class="d-flex flex-column gap-8">
                                    @for($r = 5; $r >= 1; $r--)
                                        @php
                                            $checked = $allRatingsChecked || in_array($r, $ratingsFilter, true);
                                            $count = (int) ($ratingCounts[$r] ?? 0);
                                        @endphp
                                        <label class="d-flex align-items-center gap-8">
                                            <input type="checkbox" name="rating[]" value="{{ $r }}" class="form-check-input m-0" {{ $checked ? 'checked' : '' }}/>
                                            <span class="d-flex align-items-center gap-6 flex-grow-1 fs-14 text-gray-700">
                                                <i class="fa-solid fa-star text-yellow-400"></i>
                                                {{ $r }} {{ $r === 1 ? __('estrella') : __('estrellas') }}
                                                <span class="ms-auto fs-12 text-gray-600">({{ number_format($count, 0, ',', '.') }})</span>
                                            </span>
                                        </label>
                                    @endfor
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-6">{{ __('Sentimiento') }}</label>
                                <div class="d-flex flex-column gap-10" id="reviews-stats" data-stats-url="{{ route('app.analytee.reviews.stats', ['account_id' => $accountId]) }}">
                                    <div class="d-flex align-items-start justify-content-between gap-8">
                                        <div class="d-flex align-items-center gap-8 flex-grow-1">
                                            <i class="fa-solid fa-circle text-green-500 mt-1"></i>
                                            <span class="fs-14 text-gray-700">{{ __('Positivo') }}</span>
                                            <span
                                                class="text-gray-500"
                                                data-bs-toggle="tooltip"
                                                data-bs-placement="top"
                                                data-bs-title="{{ __('Reseñas de 4–5 estrellas.') }}"
                                            >
                                                <i class="fa-solid fa-circle-info"></i>
                                            </span>
                                        </div>
                                        <div class="text-end">
                                            <div class="fs-14 text-gray-700">
                                                <span id="stats-sentiment-positive-count">(—)</span>
                                                <span class="ms-1 fs-12 text-gray-600" id="stats-sentiment-positive-percent">—%</span>
                                            </div>
                                            <div class="progress mt-1" style="height: 6px; min-width: 120px;">
                                                <div id="stats-sentiment-positive-bar" class="progress-bar bg-success" style="width: 0%"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-start justify-content-between gap-8">
                                        <div class="d-flex align-items-center gap-8 flex-grow-1">
                                            <i class="fa-solid fa-circle text-gray-400 mt-1"></i>
                                            <span class="fs-14 text-gray-700">{{ __('Neutral') }}</span>
                                            <span
                                                class="text-gray-500"
                                                data-bs-toggle="tooltip"
                                                data-bs-placement="top"
                                                data-bs-title="{{ __('Reseñas de 3 estrellas.') }}"
                                            >
                                                <i class="fa-solid fa-circle-info"></i>
                                            </span>
                                        </div>
                                        <div class="text-end">
                                            <div class="fs-14 text-gray-700">
                                                <span id="stats-sentiment-neutral-count">(—)</span>
                                                <span class="ms-1 fs-12 text-gray-600" id="stats-sentiment-neutral-percent">—%</span>
                                            </div>
                                            <div class="progress mt-1" style="height: 6px; min-width: 120px;">
                                                <div id="stats-sentiment-neutral-bar" class="progress-bar bg-secondary" style="width: 0%"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-start justify-content-between gap-8">
                                        <div class="d-flex align-items-center gap-8 flex-grow-1">
                                            <i class="fa-solid fa-circle text-red-500 mt-1"></i>
                                            <span class="fs-14 text-gray-700">{{ __('Negativo') }}</span>
                                            <span
                                                class="text-gray-500"
                                                data-bs-toggle="tooltip"
                                                data-bs-placement="top"
                                                data-bs-title="{{ __('Reseñas de 1–2 estrellas.') }}"
                                            >
                                                <i class="fa-solid fa-circle-info"></i>
                                            </span>
                                        </div>
                                        <div class="text-end">
                                            <div class="fs-14 text-gray-700">
                                                <span id="stats-sentiment-negative-count">(—)</span>
                                                <span class="ms-1 fs-12 text-gray-600" id="stats-sentiment-negative-percent">—%</span>
                                            </div>
                                            <div class="progress mt-1" style="height: 6px; min-width: 120px;">
                                                <div id="stats-sentiment-negative-bar" class="progress-bar bg-danger" style="width: 0%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-6">{{ __('Estado de respuesta') }}</label>
                                <div class="d-flex flex-column gap-10">
                                    <div class="d-flex align-items-start justify-content-between gap-8">
                                        <div class="d-flex align-items-center gap-8 flex-grow-1">
                                            <i class="fa-solid fa-reply text-primary mt-1"></i>
                                            <span class="fs-14 text-gray-700">{{ __('Con respuesta') }}</span>
                                            <span
                                                class="text-gray-500"
                                                data-bs-toggle="tooltip"
                                                data-bs-placement="top"
                                                data-bs-title="{{ __('Reseñas que ya tienen respuesta del propietario.') }}"
                                            >
                                                <i class="fa-solid fa-circle-info"></i>
                                            </span>
                                        </div>
                                        <div class="text-end">
                                            <div class="fs-14 text-gray-700">
                                                <span id="stats-reply-with-count">(—)</span>
                                                <span class="ms-1 fs-12 text-gray-600" id="stats-reply-with-percent">—%</span>
                                            </div>
                                            <div class="progress mt-1" style="height: 6px; min-width: 120px;">
                                                <div id="stats-reply-with-bar" class="progress-bar bg-primary" style="width: 0%"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-start justify-content-between gap-8">
                                        <div class="d-flex align-items-center gap-8 flex-grow-1">
                                            <i class="fa-regular fa-circle text-gray-400 mt-1"></i>
                                            <span class="fs-14 text-gray-700">{{ __('Sin respuesta') }}</span>
                                            <span
                                                class="text-gray-500"
                                                data-bs-toggle="tooltip"
                                                data-bs-placement="top"
                                                data-bs-title="{{ __('Reseñas que están pendientes de respuesta.') }}"
                                            >
                                                <i class="fa-solid fa-circle-info"></i>
                                            </span>
                                        </div>
                                        <div class="text-end">
                                            <div class="fs-14 text-gray-700">
                                                <span id="stats-reply-without-count">(—)</span>
                                                <span class="ms-1 fs-12 text-gray-600" id="stats-reply-without-percent">—%</span>
                                            </div>
                                            <div class="progress mt-1" style="height: 6px; min-width: 120px;">
                                                <div id="stats-reply-without-bar" class="progress-bar bg-warning" style="width: 0%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-6">{{ __('Cantidad') }}</label>
                                <select name="limit" class="form-select">
                                    @php $limit = (int) ($filters['limit'] ?? 10); @endphp
                                    <option value="10" {{ $limit === 10 ? 'selected' : '' }}>10</option>
                                    <option value="50" {{ $limit === 50 ? 'selected' : '' }}>50</option>
                                    <option value="500" {{ $limit === 500 ? 'selected' : '' }}>500</option>
                                    <option value="2000" {{ $limit === 2000 ? 'selected' : '' }}>2000</option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-6">{{ __('Periodo') }}</label>
                                <select name="period" class="form-select">
                                    <option value="30d" {{ ($filters['period'] ?? '30d') === '30d' ? 'selected' : '' }}>{{ __('Últimos 30 días') }}</option>
                                    <option value="3m" {{ ($filters['period'] ?? '30d') === '3m' ? 'selected' : '' }}>{{ __('Últimos 3 meses') }}</option>
                                    <option value="6m" {{ ($filters['period'] ?? '30d') === '6m' ? 'selected' : '' }}>{{ __('Últimos 6 meses') }}</option>
                                    <option value="1y" {{ ($filters['period'] ?? '30d') === '1y' ? 'selected' : '' }}>{{ __('Último año') }}</option>
                                    <option value="all" {{ ($filters['period'] ?? '30d') === 'all' ? 'selected' : '' }}>{{ __('Todo el tiempo') }}</option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-6">{{ __('Idioma') }}</label>
                                <div class="d-flex flex-column gap-8 max-h-150 overflow-auto pe-1">
                                    @php
                                        $languages = $languageCounts->keys()->sort()->values();
                                    @endphp
                                    @forelse($languages as $lang)
                                        @php
                                            $checked = $allLanguagesChecked || in_array($lang, $languagesFilter, true);
                                            $count = (int) ($languageCounts[$lang] ?? 0);
                                        @endphp
                                        <label class="d-flex align-items-center gap-8">
                                            <input type="checkbox" name="language[]" value="{{ $lang }}" class="form-check-input m-0" {{ $checked ? 'checked' : '' }}/>
                                            <span class="fs-14 text-gray-700">{{ $lang }} ({{ number_format($count, 0, ',', '.') }})</span>
                                        </label>
                                    @empty
                                        <div class="fs-14 text-gray-600">{{ __('Sin datos de idioma') }}</div>
                                    @endforelse
                                </div>
                            </div>

                            <button class="btn btn-primary w-100" type="submit">
                                <i class="fa-solid fa-filter me-2"></i>
                                {{ __('Aplicar filtros') }}
                            </button>
                        </form>
                    </div>
                    <div class="card-footer bg-white">
                        <a class="btn btn-light w-100" href="{{ url()->current() }}">
                            <i class="fa-solid fa-rotate-right me-2"></i>
                            {{ __('Limpiar filtros') }}
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-9">
                <div class="d-flex flex-column gap-12 mb-3">
                    <div class="text-gray-700 fs-14">
                        {{ ! is_numeric($totalFiltered) ? '—' : number_format((float) $totalFiltered, 0, ',', '.') }} {{ __('reseñas encontradas') }}
                        <span class="text-gray-500">({{ __('filtradas de') }} {{ ! is_numeric($totalAll) ? '—' : number_format((float) $totalAll, 0, ',', '.') }})</span>
                    </div>
                    <div class="d-flex flex-wrap gap-8">
                        <button class="btn btn-light btn-sm" type="button">
                            <i class="fa-solid fa-sort me-2"></i>
                            {{ __('Más recientes') }}
                            <i class="fa-solid fa-chevron-down ms-2 fs-10"></i>
                        </button>
                        <button class="btn btn-light btn-sm" type="button">
                            <i class="fa-solid fa-star me-2"></i>
                            {{ __('Valoración') }}
                        </button>
                        <button class="btn btn-light btn-sm" type="button">
                            <i class="fa-solid fa-heart me-2"></i>
                            {{ __('Sentimiento') }}
                        </button>
                    </div>
                </div>

                @if ($accessDenied ?? false)
                    <div class="alert alert-warning d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-12">
                            <div class="size-40 bg-warning rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fa-solid fa-lock text-white"></i>
                            </div>
                            <div>
                                <div class="fw-6">{{ __('Acceso denegado o negocio no disponible') }}</div>
                                <div class="fs-12 text-gray-700">{{ __('El negocio solicitado no pertenece al team actual.') }}</div>
                            </div>
                        </div>
                        <a class="btn btn-light btn-sm" href="{{ url('app/analytee') }}">{{ __('Volver') }}</a>
                    </div>
                @endif

                @if (session('success'))
                    <div class="alert alert-success d-flex align-items-center gap-12">
                        <div class="size-40 bg-success rounded-circle d-flex align-items-center justify-content-center">
                            <i class="fa-solid fa-check text-white"></i>
                        </div>
                        <div class="fw-6">{{ session('success') }}</div>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger d-flex align-items-center gap-12">
                        <div class="size-40 bg-danger rounded-circle d-flex align-items-center justify-content-center">
                            <i class="fa-solid fa-triangle-exclamation text-white"></i>
                        </div>
                        <div>
                            <div class="fw-6">{{ session('error') }}</div>
                            @php
                                $err = (string) session('error');
                                $showPlaceIdHint = $err !== '' && (str_contains(mb_strtolower($err), 'place_id') || str_contains(mb_strtolower($err), 'metadata'));
                            @endphp
                            @if ($showPlaceIdHint)
                                <div class="fs-12 text-gray-700">{{ __('Verifica que el perfil tenga place_id en su metadata.') }}</div>
                            @endif
                        </div>
                    </div>
                @endif

                @if (($accessDenied ?? false) === false && $account && (int) ($account->status ?? 0) === 2)
                    @php
                        $accountData = is_array($account->data ?? null) ? $account->data : (json_decode((string) ($account->data ?? ''), true) ?: []);
                        $needsReauth = (int) ($accountData['needs_reauth'] ?? 0) === 1;
                    @endphp
                    @if ($needsReauth)
                        <div class="alert alert-danger d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-12">
                                <div class="size-40 bg-danger rounded-circle d-flex align-items-center justify-content-center">
                                    <i class="fa-solid fa-lock text-white"></i>
                                </div>
                                <div>
                                    <div class="fw-6">{{ __('Cuenta requiere reautenticación') }}</div>
                                    <div class="fs-12 text-gray-700">{{ __('Falta el permiso necesario para gestionar reseñas (business.manage).') }}</div>
                                </div>
                            </div>
                            @if (! empty($account->reconnect_url))
                                <a class="btn btn-primary btn-sm" href="{{ $account->reconnect_url }}">{{ __('Reautenticar') }}</a>
                            @endif
                        </div>
                    @endif
                @endif

                @if (! ($accessDenied ?? false) && empty($placeId))
                    <div class="alert alert-warning d-flex align-items-center gap-12">
                        <div class="size-40 bg-warning rounded-circle d-flex align-items-center justify-content-center">
                            <i class="fa-solid fa-circle-info text-white"></i>
                        </div>
                        <div>
                            <div class="fw-6">{{ __('Este negocio no tiene place_id configurado') }}</div>
                            <div class="fs-12 text-gray-700">{{ __('Sin place_id no se puede leer reseñas con Place Details (New).') }}</div>
                        </div>
                    </div>
                @endif

                <div id="reviews-list">
                    @forelse ($reviews as $review)
                        @php
                            $authorName = (string) ($review['author'] ?? 'Autor');
                            $avatarUrl = trim((string) ($review['avatar_url'] ?? ''));
                            $parts = array_values(array_filter(preg_split('/\s+/', trim($authorName)) ?: []));
                            $initials = '';
                            foreach (array_slice($parts, 0, 2) as $p) {
                                $initials .= mb_strtoupper(mb_substr($p, 0, 1));
                            }

                            $publishedAtRaw = (string) ($review['date'] ?? '');
                        $publishedAt = null;
                        if ($publishedAtRaw !== '') {
                            try {
                                $publishedAt = \Carbon\Carbon::parse($publishedAtRaw);
                            } catch (\Throwable) {
                                $publishedAt = null;
                            }
                        }
                            $publishedHuman = $publishedAt ? $publishedAt->locale('es')->diffForHumans() : '—';
                            $publishedDate = $publishedAt ? $publishedAt->locale('es')->translatedFormat('d M Y') : '—';

                            $rating = (int) ($review['rating'] ?? 0);
                            $ownerResponseText = $review['responseFromOwnerText'] ?? null;
                            $isPendingReply = $ownerResponseText === null || trim((string) $ownerResponseText) === '';
                            $isPriority = $isPendingReply && $rating > 0 && $rating <= 3;
                            $canReply = false;

                            $drawerPayload = [
                                'id' => null,
                                'author_name' => $authorName,
                                'author_url' => '',
                                'rating' => $rating,
                                'text' => (string) ($review['text'] ?? ''),
                                'owner_response_text' => $ownerResponseText === null ? '' : (string) $ownerResponseText,
                                'owner_response_at' => '',
                                'language' => (string) ($review['language'] ?? ''),
                                'published_at' => $publishedAtRaw,
                                'published_human' => $publishedHuman,
                                'published_date' => $publishedDate,
                                'external_id' => '',
                                'external_id_encoded' => '',
                                'is_pending_reply' => 0,
                                'is_priority' => $isPriority ? 1 : 0,
                                'source' => 'database',
                            ];
                        @endphp

                        <div class="card shadow-none {{ $isPriority ? 'border-red-200' : 'border-gray-200' }} mb-4 review-row pointer" data-review='@json($drawerPayload)'>
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between gap-12 mb-3 flex-wrap">
                                    <div class="d-flex align-items-center">
                                        @if ($avatarUrl !== '')
                                            <img
                                                src="{{ $avatarUrl }}"
                                                class="size-40 rounded-circle border align-self-center"
                                                style="object-fit: cover;"
                                                alt="{{ $authorName }}"
                                                loading="lazy"
                                                decoding="async"
                                                onerror="this.onerror=null;this.src='{{ theme_public_asset('img/default.png') }}';"
                                            >
                                        @else
                                            <img
                                                src="{{ theme_public_asset('img/default.png') }}"
                                                class="size-40 rounded-circle border align-self-center"
                                                style="object-fit: cover;"
                                                alt="{{ $authorName }}"
                                                loading="lazy"
                                                decoding="async"
                                                onerror="this.onerror=null;this.style.display='none';"
                                            >
                                        @endif
                                        <div class="ms-3">
                                            <div class="fw-6 text-gray-900">{{ $authorName }}</div>
                                            <div class="fs-12 text-gray-600">{{ $publishedHuman }} · Google Maps</div>
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center flex-wrap gap-8">
                                        <div class="d-flex align-items-center gap-6">
                                            @for ($i = 1; $i <= 5; $i++)
                                                    <i class="{{ $i <= $rating ? 'fa-solid fa-star text-yellow-400' : 'fa-regular fa-star text-gray-300' }}"></i>
                                            @endfor
                                        </div>
                                        <span class="bg-gray-100 text-gray-700 fs-12 fw-5 px-2 py-1 rounded">{{ __('Sentimiento: pendiente') }}</span>
                                        <span class="{{ $isPendingReply ? ($isPriority ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700') : 'bg-green-100 text-green-700' }} fs-12 fw-5 px-2 py-1 rounded">
                                            {{ __('Respuesta:') }} {{ $isPendingReply ? __('Pendiente') : __('Respondida') }}
                                        </span>
                                        @if ($canReply)
                                            <button class="btn btn-primary btn-sm reply-btn" type="button">
                                                <i class="fa-solid fa-reply me-1"></i>
                                                {{ __('Responder') }}
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                <div class="text-gray-700 fs-14 mb-3">
                                    {{ \Illuminate\Support\Str::limit((string) ($review['text'] ?? ''), 300) }}
                                </div>

                                <div class="d-flex flex-wrap gap-12 fs-12 text-gray-600">
                                    <span class="d-flex align-items-center gap-6">
                                        <i class="fa-solid fa-language"></i>
                                        {{ (string) ($review['language'] ?? '') !== '' ? (string) $review['language'] : '—' }}
                                    </span>
                                    <span class="d-flex align-items-center gap-6">
                                        <i class="fa-solid fa-tag"></i>
                                        {{ __('Temas: pendiente') }}
                                    </span>
                                    <span class="d-flex align-items-center gap-6">
                                        <i class="fa-solid fa-calendar"></i>
                                        {{ $publishedDate }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="card shadow-none border-gray-200">
                            <div class="card-body d-flex align-items-center gap-12">
                                <div class="size-40 bg-gray-100 rounded-circle d-flex align-items-center justify-content-center">
                                    <i class="fa-solid fa-inbox text-gray-600"></i>
                                </div>
                                <div>
                                    <div class="fw-6">{{ __('Sin reseñas') }}</div>
                                    <div class="text-gray-700">{{ __('No hay reseñas para este negocio con los filtros actuales.') }}</div>
                                </div>
                            </div>
                        </div>
                    @endforelse
                </div>

                @if (method_exists($reviews, 'lastPage') && $reviews->lastPage() > 1)
                    @php
                        $current = $reviews->currentPage();
                        $last = $reviews->lastPage();
                        $pages = [];

                        if ($last <= 7) {
                            $pages = range(1, $last);
                        } else {
                            $pages = [1];
                            if ($current > 3) { $pages[] = '...'; }
                            foreach (range(max(2, $current - 1), min($last - 1, $current + 1)) as $p) { $pages[] = $p; }
                            if ($current < $last - 2) { $pages[] = '...'; }
                            $pages[] = $last;
                        }
                    @endphp

                    <div class="d-flex align-items-center justify-content-center gap-8 mt-4">
                        <a href="{{ $reviews->previousPageUrl() ?? '#' }}"
                           class="btn btn-light btn-sm {{ $reviews->onFirstPage() ? 'disabled pointer-events-none' : '' }}">
                            <i class="fa-solid fa-chevron-left"></i>
                        </a>
                        @foreach ($pages as $p)
                            @if ($p === '...')
                                <span class="px-2 text-gray-600">...</span>
                            @else
                                <a href="{{ $reviews->url($p) }}"
                                   class="btn btn-sm {{ $p === $current ? 'btn-primary' : 'btn-light' }}">
                                    {{ $p }}
                                </a>
                            @endif
                        @endforeach
                        <a href="{{ $reviews->nextPageUrl() ?? '#' }}"
                           class="btn btn-light btn-sm {{ $current >= $last ? 'disabled pointer-events-none' : '' }}">
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="offcanvas offcanvas-end" tabindex="-1" id="reviewDrawer" aria-labelledby="reviewDrawerLabel" style="--bs-offcanvas-width: 600px;">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title fw-6" id="reviewDrawerLabel">{{ __('Detalle de reseña') }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('Cerrar') }}"></button>
        </div>
        <div class="offcanvas-body">
            <div id="drawer-content">
                <div class="d-flex align-items-start gap-12 mb-4">
                    <div id="drawer-avatar" class="rounded-circle bg-gray-200 d-flex align-items-center justify-content-center text-gray-700 fw-6" style="width:64px;height:64px;">U</div>
                    <div class="flex-grow-1">
                        <div id="drawer-author" class="fw-7 fs-18 text-gray-900">—</div>
                        <div class="fs-14 text-gray-600 mb-2">Google Maps</div>
                        <div class="d-flex align-items-center gap-8 mb-2">
                            <div id="drawer-stars" class="d-flex align-items-center gap-6"></div>
                            <span id="drawer-rating" class="fs-14 fw-6 text-gray-700">—</span>
                        </div>
                        <div class="d-flex align-items-center flex-wrap gap-12 fs-12 text-gray-600">
                            <span class="d-flex align-items-center gap-6">
                                <i class="fa-solid fa-calendar"></i>
                                <span id="drawer-date">—</span>
                            </span>
                            <span class="d-flex align-items-center gap-6">
                                <i class="fa-solid fa-clock"></i>
                                <span id="drawer-human">—</span>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center flex-wrap gap-8 mb-4">
                    <span class="bg-gray-100 text-gray-700 fs-12 fw-5 px-2 py-1 rounded">{{ __('Sentimiento: pendiente') }}</span>
                    <span class="bg-blue-100 text-blue-700 fs-12 fw-5 px-2 py-1 rounded d-flex align-items-center gap-6">
                        <i class="fa-solid fa-language"></i>
                        <span id="drawer-language">—</span>
                    </span>
                </div>

                <div class="mb-4">
                    <div class="fw-6 text-gray-900 mb-2">{{ __('Texto de la reseña') }}</div>
                    <div id="drawer-text" class="text-gray-700">—</div>
                </div>

                <div class="mb-4">
                    <div class="fw-6 text-gray-900 mb-2">{{ __('Análisis de temas') }}</div>
                    <div class="text-gray-700">{{ __('Disponible próximamente') }}</div>
                </div>

                <div class="mb-4">
                    <div class="fw-6 text-gray-900 mb-2">{{ __('Puntuaciones por aspecto') }}</div>
                    <div class="text-gray-700">{{ __('Disponible próximamente') }}</div>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded p-3 mb-4">
                    <div class="d-flex align-items-start gap-12 mb-3">
                        <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:40px;height:40px;">
                            <i class="fa-solid fa-store text-white"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center justify-content-between gap-8">
                                <div class="fw-6 text-gray-900">{{ __('Respuesta del propietario') }}</div>
                                <div id="drawer-owner-date" class="fs-12 text-gray-600">—</div>
                            </div>
                        </div>
                    </div>
                    <div id="drawer-owner-text" class="text-gray-700 fs-14">—</div>
                </div>

                <div class="mb-4">
                    <div class="fw-6 text-gray-900 mb-2">{{ __('Información adicional') }}</div>
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="bg-gray-100 rounded p-3">
                                <div class="fs-12 text-gray-600 mb-1">{{ __('ID de reseña') }}</div>
                                <div id="drawer-external-id" class="fw-6 text-gray-900">—</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-gray-100 rounded p-3">
                                <div class="fs-12 text-gray-600 mb-1">{{ __('Plataforma') }}</div>
                                <div class="fw-6 text-gray-900">Google Maps</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-gray-100 rounded p-3">
                                <div class="fs-12 text-gray-600 mb-1">{{ __('Idioma detectado') }}</div>
                                <div id="drawer-language-2" class="fw-6 text-gray-900">—</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-gray-100 rounded p-3">
                                <div class="fs-12 text-gray-600 mb-1">{{ __('Longitud') }}</div>
                                <div id="drawer-length" class="fw-6 text-gray-900">—</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-top pt-4">
                    <div class="fw-6 text-gray-900 mb-3">{{ __('Acciones') }}</div>
                    <div class="d-flex flex-column gap-8">
                        <button id="drawer-reply-btn" class="btn btn-primary w-100" type="button" disabled>
                            <i class="fa-solid fa-reply me-2"></i>
                            {{ __('Responder a esta reseña') }}
                        </button>
                        <button class="btn btn-light w-100 opacity-60" type="button" disabled>
                            <i class="fa-solid fa-flag me-2"></i>
                            {{ __('Marcar para revisión') }}
                        </button>
                        <button class="btn btn-light w-100 opacity-60" type="button" disabled>
                            <i class="fa-solid fa-share-nodes me-2"></i>
                            {{ __('Compartir reseña') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="offcanvas offcanvas-end" tabindex="-1" id="replyDrawer" aria-labelledby="replyDrawerLabel" style="--bs-offcanvas-width: 600px;">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title fw-6" id="replyDrawerLabel">{{ __('Responder reseña') }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('Cerrar') }}"></button>
        </div>
        <div class="offcanvas-body">
            <div class="mb-4 d-flex align-items-start justify-content-between gap-12">
                <div>
                    <div id="reply-author" class="fw-6 text-gray-900">—</div>
                    <div id="reply-meta" class="fs-12 text-gray-600">—</div>
                </div>
                <div id="reply-stars" class="d-flex align-items-center gap-6"></div>
            </div>
            <div class="mb-4">
                <div class="fw-6 text-gray-900 mb-2">{{ __('Reseña') }}</div>
                <div id="reply-text" class="text-gray-700">—</div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-6">{{ __('Tu respuesta') }}</label>
                <textarea id="reply-textarea" class="form-control" rows="7" placeholder="{{ __('Escribe tu respuesta...') }}"></textarea>
            </div>
            <div class="d-flex align-items-center justify-content-between gap-12">
                <div id="reply-status" class="fs-12 text-gray-600"></div>
                <div class="d-flex align-items-center gap-8">
                    <button
                        id="reply-ai-btn"
                        type="button"
                        class="btn btn-light btn-sm ai-suggest-btn"
                        title="{{ __('Generar sugerencia con IA') }}"
                        onclick="generateAiSuggestion()"
                    >
                        <i class="fa-solid fa-wand-magic-sparkles me-2"></i>
                        {{ __('Sugerir respuesta') }}
                    </button>
                    <button class="btn btn-light btn-sm" type="button" onclick="saveReplyDraft()">{{ __('Guardar borrador') }}</button>
                    <button id="reply-publish-btn" class="btn btn-primary btn-sm" type="button" onclick="publishReply()">{{ __('Publicar') }}</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const replyUrlTemplate = @json(route('app.analytee.reviews.reply', ['account_id' => $accountId, 'external_id' => '__EXTERNAL__']));
        const aiPreviewUrlTemplate = @json(route('app.analytee.reviews.ai-preview', ['review_id' => '__ID__']));
        const csrfToken = @json(csrf_token());
        const statsUrlBase = document.getElementById('reviews-stats')?.dataset?.statsUrl || null;
        const statsNumber = new Intl.NumberFormat('es-ES');

        function setText(id, value) {
            const el = document.getElementById(id);
            if (!el) return;
            el.textContent = value;
        }

        function setBar(id, percent) {
            const el = document.getElementById(id);
            if (!el) return;
            const safe = Math.max(0, Math.min(100, Number(percent || 0)));
            el.style.width = `${safe}%`;
        }

        function applyReviewStats(payload) {
            const data = payload?.data || payload || {};
            const total = Number(data?.total || 0);

            const formatCount = (n) => `(${statsNumber.format(Number(n || 0))})`;
            const formatPct = (n) => `${statsNumber.format(Number(n || 0))}%`;

            setText('stats-sentiment-positive-count', formatCount(data?.sentiment?.positive?.count));
            setText('stats-sentiment-positive-percent', formatPct(data?.sentiment?.positive?.percent));
            setBar('stats-sentiment-positive-bar', data?.sentiment?.positive?.percent);

            setText('stats-sentiment-neutral-count', formatCount(data?.sentiment?.neutral?.count));
            setText('stats-sentiment-neutral-percent', formatPct(data?.sentiment?.neutral?.percent));
            setBar('stats-sentiment-neutral-bar', data?.sentiment?.neutral?.percent);

            setText('stats-sentiment-negative-count', formatCount(data?.sentiment?.negative?.count));
            setText('stats-sentiment-negative-percent', formatPct(data?.sentiment?.negative?.percent));
            setBar('stats-sentiment-negative-bar', data?.sentiment?.negative?.percent);

            setText('stats-reply-with-count', formatCount(data?.reply?.with?.count));
            setText('stats-reply-with-percent', formatPct(data?.reply?.with?.percent));
            setBar('stats-reply-with-bar', data?.reply?.with?.percent);

            setText('stats-reply-without-count', formatCount(data?.reply?.without?.count));
            setText('stats-reply-without-percent', formatPct(data?.reply?.without?.percent));
            setBar('stats-reply-without-bar', data?.reply?.without?.percent);

            if (total === 0) {
                setBar('stats-sentiment-positive-bar', 0);
                setBar('stats-sentiment-neutral-bar', 0);
                setBar('stats-sentiment-negative-bar', 0);
                setBar('stats-reply-with-bar', 0);
                setBar('stats-reply-without-bar', 0);
            }
        }

        let statsRefreshTimer = null;

        async function refreshReviewStats() {
            if (!statsUrlBase) return;

            try {
                const params = new URLSearchParams(window.location.search || '');
                params.set('_ts', String(Date.now()));
                const url = `${statsUrlBase}?${params.toString()}`;

                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const json = await res.json().catch(() => ({}));
                if (!res.ok || json?.status !== 1) return;
                applyReviewStats(json);
            } catch (e) {
            }
        }

        function openReviewDrawerFromPayload(payload) {
            const drawerEl = document.getElementById('reviewDrawer');

            const author = payload.author_name || '—';
            const initials = author.split(/\s+/).filter(Boolean).slice(0, 2).map(p => (p[0] || '').toUpperCase()).join('') || 'U';

            document.getElementById('drawer-avatar').textContent = initials;
            document.getElementById('drawer-author').textContent = author;
            document.getElementById('drawer-language').textContent = payload.language || '—';
            document.getElementById('drawer-language-2').textContent = payload.language || '—';
            document.getElementById('drawer-date').textContent = payload.published_date || '—';
            document.getElementById('drawer-human').textContent = payload.published_human || '—';
            document.getElementById('drawer-text').textContent = payload.text || '—';
            document.getElementById('drawer-owner-text').textContent = payload.owner_response_text || '—';
            document.getElementById('drawer-owner-date').textContent = payload.owner_response_at ? String(payload.owner_response_at) : '—';
            document.getElementById('drawer-external-id').textContent = payload.external_id || '—';
            document.getElementById('drawer-length').textContent = String((payload.text || '').length) + ' caracteres';

            const replyBtn = document.getElementById('drawer-reply-btn');
            if (payload.is_pending_reply) {
                replyBtn.disabled = false;
                replyBtn.classList.remove('opacity-60');
            } else {
                replyBtn.disabled = true;
                replyBtn.classList.add('opacity-60');
            }
            replyBtn.onclick = (e) => {
                e?.preventDefault?.();
                openReplyModal(payload);
            };

            const rating = Number(payload.rating || 0);
            document.getElementById('drawer-rating').textContent = rating ? rating.toFixed(1) : '—';

            const starsEl = document.getElementById('drawer-stars');
            starsEl.innerHTML = '';
            for (let i = 1; i <= 5; i++) {
                const icon = document.createElement('i');
                icon.className = (i <= rating ? 'fa-solid fa-star text-yellow-400' : 'fa-regular fa-star text-gray-300');
                starsEl.appendChild(icon);
            }
            bootstrap.Offcanvas.getOrCreateInstance(drawerEl).show();
        }

        let currentReplyPayload = null;

        function openReplyModal(payload) {
            currentReplyPayload = payload || null;
            const drawerEl = document.getElementById('replyDrawer');
            document.getElementById('reply-status').textContent = '';

            const author = payload?.author_name || '—';
            const date = payload?.published_date || payload?.published_at || '—';
            document.getElementById('reply-author').textContent = author;
            document.getElementById('reply-meta').textContent = `${date} · Google Maps`;
            document.getElementById('reply-text').textContent = payload?.text || '—';

            const rating = Number(payload?.rating || 0);
            const starsEl = document.getElementById('reply-stars');
            starsEl.innerHTML = '';
            for (let i = 1; i <= 5; i++) {
                const icon = document.createElement('i');
                icon.className = (i <= rating ? 'fa-solid fa-star text-yellow-400' : 'fa-regular fa-star text-gray-300');
                starsEl.appendChild(icon);
            }

            const draftKey = payload?.external_id_encoded ? `analytee_reply_draft:${payload.external_id_encoded}` : null;
            const savedDraft = draftKey ? (localStorage.getItem(draftKey) || '') : '';
            document.getElementById('reply-textarea').value = savedDraft;

            bootstrap.Offcanvas.getOrCreateInstance(drawerEl).show();
        }

        async function generateAiSuggestion() {
            if (!currentReplyPayload?.id) return;
            const statusEl = document.getElementById('reply-status');
            const btn = document.getElementById('reply-ai-btn');
            const textarea = document.getElementById('reply-textarea');

            const currentText = String(textarea?.value || '').trim();
            if (currentText.length > 0) {
                const confirmed = window.confirm('¿Reemplazar el contenido actual por la sugerencia IA?');
                if (!confirmed) return;
            }

            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Generando...';
            statusEl.textContent = 'Generando...';

            try {
                const url = aiPreviewUrlTemplate.replace('__ID__', encodeURIComponent(String(currentReplyPayload.id)));
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({}),
                });

                const data = await res.json().catch(() => ({}));
                if (!res.ok || data?.status === 0) {
                    statusEl.textContent = data?.message || 'No se pudo generar la sugerencia';
                    return;
                }

                textarea.value = String(data?.suggestion || '');
                statusEl.textContent = 'Sugerencia generada';
            } catch (e) {
                statusEl.textContent = 'No se pudo generar la sugerencia';
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }

        function closeReplyModal() {
            document.getElementById('reply-status').textContent = '';
            currentReplyPayload = null;
            const drawerEl = document.getElementById('replyDrawer');
            bootstrap.Offcanvas.getOrCreateInstance(drawerEl).hide();
        }

        function saveReplyDraft() {
            if (!currentReplyPayload?.external_id_encoded) return;
            const draftKey = `analytee_reply_draft:${currentReplyPayload.external_id_encoded}`;
            const text = document.getElementById('reply-textarea').value || '';
            localStorage.setItem(draftKey, text);
        }

        async function publishReply() {
            const statusEl = document.getElementById('reply-status');
            const publishBtn = document.getElementById('reply-publish-btn');
            const textarea = document.getElementById('reply-textarea');

            if (!currentReplyPayload?.external_id_encoded) {
                statusEl.textContent = 'No se pudo publicar la respuesta';
                return;
            }

            const text = String(textarea?.value || '').trim();
            if (!text) {
                statusEl.textContent = 'La respuesta no puede estar vacía';
                return;
            }

            const url = replyUrlTemplate.replace('__EXTERNAL__', encodeURIComponent(currentReplyPayload.external_id_encoded));
            statusEl.textContent = 'Publicando...';
            if (publishBtn) publishBtn.disabled = true;

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ reply: text }),
                });

                const data = await res.json().catch(() => ({}));
                if (!res.ok || data?.status === 0) {
                    statusEl.textContent = data?.message || 'No se pudo publicar la respuesta';
                    return;
                }

                localStorage.removeItem(`analytee_reply_draft:${currentReplyPayload.external_id_encoded}`);
                statusEl.textContent = 'Respuesta publicada';
                closeReplyModal();
                window.location.reload();
            } catch (e) {
                statusEl.textContent = 'No se pudo publicar la respuesta';
            } finally {
                if (publishBtn) publishBtn.disabled = false;
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.review-row').forEach((row) => {
                row.addEventListener('click', () => {
                    try {
                        const payload = JSON.parse(row.getAttribute('data-review') || '{}');
                        openReviewDrawerFromPayload(payload);
                    } catch (e) {
                        openReviewDrawerFromPayload({});
                    }
                });
            });

            refreshReviewStats();
            if (statsUrlBase) {
                statsRefreshTimer = window.setInterval(() => {
                    if (document.visibilityState === 'visible') {
                        refreshReviewStats();
                    }
                }, 10000);

                window.addEventListener('focus', refreshReviewStats);
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') {
                        refreshReviewStats();
                    }
                });
            }

            document.querySelectorAll('.reply-btn').forEach((btn) => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const row = e.target.closest('.review-row');
                    try {
                        const payload = JSON.parse(row.getAttribute('data-review') || '{}');
                        openReplyModal(payload);
                    } catch (err) {
                        openReplyModal({});
                    }
                });
            });
        });
    </script>
@endsection
