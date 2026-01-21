@extends('layouts.app')

@section('css')
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#2563eb',
                        secondary: '#1e40af',
                    }
                }
            }
        }
    </script>
    <style>
        ::-webkit-scrollbar { display: none; }
        .checkbox-custom:checked { background-color: #2563eb; border-color: #2563eb; }
    </style>
@endsection

@section('content')
    <div class="bg-gray-50">
        <div id="header" class="bg-white border-b border-gray-200 px-8 py-5 sticky top-0 z-30">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Select Business Locations</h1>
                    <p class="text-sm text-gray-500 mt-1">Choose which Google Business locations you want to analyze</p>
                </div>
                <div class="flex items-center space-x-3">
                    <button class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition flex items-center space-x-2">
                        <i class="fa-solid fa-sync-alt"></i>
                        <span class="font-medium">Refresh</span>
                    </button>
                    <button class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition flex items-center space-x-2">
                        <i class="fa-solid fa-filter"></i>
                        <span class="font-medium">Filters</span>
                    </button>
                </div>
            </div>
        </div>

        <div id="content-area" class="p-8">
            @php
                $profiles = $profiles ?? collect();
                $profilesCount = $profiles->count();
            @endphp

            @if ($profilesCount === 0)
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6 flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-yellow-500 rounded-full flex items-center justify-center">
                            <i class="fa-solid fa-plug text-white"></i>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-800">Google Business Profile no conectado</p>
                            <p class="text-sm text-gray-600">Datos disponibles tras conectar ubicaciones de GBP.</p>
                        </div>
                    </div>
                    <a class="text-sm text-yellow-700 hover:text-yellow-800 font-medium" href="{{ url('app/channels') }}">
                        Conectar
                    </a>
                </div>
            @else
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6 flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-green-600 rounded-full flex items-center justify-center">
                            <i class="fa-solid fa-check text-white"></i>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-800">Google Business Profile conectado</p>
                            <p class="text-sm text-gray-600">{{ $profilesCount }} ubicaciones detectadas en Channels</p>
                        </div>
                    </div>
                    <a class="text-sm text-green-700 hover:text-green-800 font-medium" href="{{ url('app/channels') }}">
                        Administrar
                    </a>
                </div>
            @endif

            @php
                $missingPlaceIdCount = (int) ($missingPlaceIdCount ?? 0);
                $missingPidCount = (int) ($missingPidCount ?? 0);
            @endphp
            @if ($profilesCount > 0 && ($missingPlaceIdCount > 0 || $missingPidCount > 0))
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6 flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-red-600 rounded-full flex items-center justify-content-center">
                            <i class="fa-solid fa-triangle-exclamation text-white"></i>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-800">Perfiles incompletos</p>
                            <p class="text-sm text-gray-600">
                                @if ($missingPlaceIdCount > 0)
                                    {{ $missingPlaceIdCount }} sin place_id.
                                @endif
                                @if ($missingPidCount > 0)
                                    {{ $missingPidCount }} sin PID.
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="text-sm text-red-700 font-medium">La sincronización puede fallar en estos perfiles</div>
                </div>
            @endif

            <div id="account-info" class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center">
                        <i class="fa-brands fa-google text-white"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">Connected Account</p>
                        <p class="text-sm text-gray-600">
                            {{ $hasData ? ($profilesCount.' profiles available') : 'No account connected' }}
                        </p>
                    </div>
                </div>
                <button class="text-sm text-blue-600 hover:text-blue-700 font-medium" type="button">
                    Change Account
                </button>
            </div>

            @if ($profilesCount > 0)
                <div id="search-filter-section" class="bg-white rounded-lg border border-gray-200 p-6 mb-6">
                    <div class="flex items-center space-x-4">
                        <div class="flex-1 relative">
                            <i class="fa-solid fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            <input type="text" placeholder="Search by business name, address, or city..." class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <select class="px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-gray-700">
                            <option>All Categories</option>
                            <option>Location</option>
                        </select>
                    </div>
                </div>

                <div id="selection-bar" class="bg-white rounded-lg border border-gray-200 p-4 mb-6 flex items-center justify-between">
                    <div class="flex items-center space-x-6">
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" class="w-5 h-5 checkbox-custom rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
                            <span class="text-sm font-medium text-gray-700">Select All ({{ $profilesCount }})</span>
                        </label>
                        <div class="h-6 w-px bg-gray-300"></div>
                        <p class="text-sm text-gray-600">
                            <span class="font-semibold text-gray-800" id="selected-count">0</span> locations selected
                        </p>
                    </div>
                    <button class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium flex items-center space-x-2" type="button">
                        <i class="fa-solid fa-plus"></i>
                        <span>Add Selected Profiles</span>
                    </button>
                </div>

                <div id="locations-grid" class="grid grid-cols-1 gap-4">
                    @foreach ($profiles as $profile)
                        <div class="location-card bg-white border-2 border-gray-200 rounded-lg p-5 hover:shadow-lg transition cursor-pointer">
                            <div class="flex items-start space-x-4">
                                <input type="checkbox" class="w-5 h-5 checkbox-custom rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer mt-1">
                                <div class="flex-1">
                                    <div class="flex items-start justify-between mb-3">
                                        <div class="flex items-start gap-3">
                                            @if (!empty($profile->avatar))
                                                <img src="{{ Media::url($profile->avatar) }}" class="rounded-full w-10 h-10 mt-0.5" style="object-fit:cover;" alt="">
                                            @endif
                                            <div>
                                                <h3 class="text-lg font-semibold text-gray-800 mb-1">{{ $profile->name ?? '-' }}</h3>
                                                <p class="text-sm text-gray-600 flex items-center">
                                                    <i class="fa-solid fa-link mr-2 text-gray-400"></i>
                                                    @if (!empty($profile->url))
                                                        <a href="{{ $profile->url }}" target="_blank" class="text-blue-600 hover:text-blue-700">
                                                            Open on Google Maps
                                                        </a>
                                                    @else
                                                        —
                                                    @endif
                                                </p>
                                                <p class="text-sm text-gray-600 flex items-center mt-1">
                                                    <i class="fa-solid fa-star mr-2 text-gray-400"></i>
                                                    @php
                                                        $analyteeAccountId = (int) ($profile->analytee_account_id ?? 0);
                                                    @endphp
                                                    @if ($analyteeAccountId > 0)
                                                        <a href="{{ url('app/analytee/reviews/'.$analyteeAccountId) }}" class="text-blue-600 hover:text-blue-700">
                                                            Gestión de reseñas
                                                        </a>
                                                    @else
                                                        <form method="post" action="{{ route('app.analytee.link', ['profile_id' => (int) ($profile->id ?? 0)]) }}">
                                                            @csrf
                                                            <button class="text-blue-600 hover:text-blue-700" type="submit">
                                                                {{ __('Preparar Analytee') }}
                                                            </button>
                                                        </form>
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2 bg-blue-50 px-3 py-1.5 rounded-lg hidden">
                                            <i class="fa-solid fa-check-circle text-blue-600"></i>
                                            <span class="text-sm font-medium text-blue-600">Selected</span>
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ __('Datos de reseñas disponibles tras sincronización en una fase posterior.') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div id="pagination" class="mt-8 flex items-center justify-between">
                    <p class="text-sm text-gray-600">Showing {{ $profilesCount }} of {{ $profilesCount }} locations</p>
                    <div class="flex items-center space-x-2">
                        <button class="px-4 py-2 border border-gray-300 rounded-lg text-gray-400 cursor-not-allowed" type="button">
                            <i class="fa-solid fa-chevron-left"></i>
                        </button>
                        <button class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium" type="button">1</button>
                        <button class="px-4 py-2 border border-gray-300 rounded-lg text-gray-400 cursor-not-allowed" type="button">
                            <i class="fa-solid fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@section('script')
    <script>
        window.addEventListener('load', function() {
            const locationCards = document.querySelectorAll('.location-card');
            const checkboxes = document.querySelectorAll('.location-card input[type="checkbox"]');
            const selectAllCheckbox = document.querySelector('#selection-bar input[type="checkbox"]');
            const selectedCountElement = document.getElementById('selected-count');

            function updateSelectedCount() {
                const checkedCount = document.querySelectorAll('.location-card input[type="checkbox"]:checked').length;
                selectedCountElement.textContent = checkedCount;
            }

            locationCards.forEach((card) => {
                const checkbox = card.querySelector('input[type="checkbox"]');

                card.addEventListener('click', function(e) {
                    if (e.target.tagName !== 'INPUT') {
                        checkbox.checked = !checkbox.checked;
                        updateCardStyle(card, checkbox.checked);
                        updateSelectedCount();
                    }
                });

                checkbox.addEventListener('change', function() {
                    updateCardStyle(card, this.checked);
                    updateSelectedCount();
                });
            });

            function updateCardStyle(card, isChecked) {
                const selectedBadge = card.querySelector('.bg-blue-50');
                if (isChecked) {
                    card.classList.add('border-blue-500');
                    card.classList.remove('border-gray-200');
                    if (selectedBadge) selectedBadge.classList.remove('hidden');
                } else {
                    card.classList.remove('border-blue-500');
                    card.classList.add('border-gray-200');
                    if (selectedBadge) selectedBadge.classList.add('hidden');
                }
            }

            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                        const card = checkbox.closest('.location-card');
                        updateCardStyle(card, this.checked);
                    });
                    updateSelectedCount();
                });
            }

            updateSelectedCount();
        });
    </script>
@endsection
