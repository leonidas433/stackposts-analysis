<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ Language::getCurrent('dir') }}">
<head>
    <title>
        @hasSection('pagetitle')
            @yield('pagetitle')
        @else
            {{ get_option('website_title', config('site.title')) }}
        @endif
    </title>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="keywords" content="{{ get_option('website_keyword', config('site.keywords')) }}">
    <meta name="description" content="{{ get_option('website_description', config('site.description')) }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="icon" type="image/x-icon" href="{{ url(get_option('website_favicon', asset('public/img/favicon.png'))) }}">

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.cdnfonts.com/css/general-sans?styles=135312,135310,135313,135303" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ theme_public_asset('css/flags/flag-icon.css') }}">
    <link rel="stylesheet" href="{{ theme_public_asset('css/fontawesome/css/all.min.css') }}">

    {{-- CSS DEL TEMA ACTIVO (PRODUCCIÓN) --}}
    <link rel="stylesheet" href="{{ theme_public_asset('css/app.css') }}">

    {!! Script::globals() !!}

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js" defer></script>
</head>

<body class="antialiased bg-body text-body font-body sm:overflow-x-hidden">

    @if(request()->segment(1) !== 'auth')
        @include('partials.header')
    @endif

    @yield('content')

    @if(request()->segment(1) !== 'auth')
        @include('partials.footer')
    @endif

    {{-- COOKIE BAR --}}
    <div class="fixed bottom-0 z-50 w-full cookie-policy-bar">
        <div class="p-10 md:px-20 lg:px-36 bg-white border border-coolGray-100 shadow-md">
            <div class="container mx-auto">
                <div class="flex flex-wrap items-center -mx-4">
                    <div class="w-full md:w-1/2 px-4 mb-8 md:mb-0">
                        <h3 class="mb-4 text-lg md:text-xl text-coolGray-900 font-semibold">{{ __('Cookie Policy') }}</h3>
                        <p class="mb-2 text-coolGray-500 font-medium">
                            {{ __('We use third-party cookies in order to personalise your experience') }}
                        </p>
                        <a class="flex items-center font-medium text-indigo-500 hover:text-indigo-600" href="{{ url('privacy-policy') }}">
                            <span class="mr-2">{{ __('Read our cookie policy') }}</span>
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <path d="M15.71 12.71L12.71 15.71C12.32 16.1 11.68 16.1 11.29 15.71C10.9 15.32 10.9 14.68 11.29 14.29L12.59 13H9C8.45 13 8 12.55 8 12C8 11.45 8.45 11 9 11H12.59L11.29 9.71C10.9 9.32 10.9 8.68 11.29 8.29C11.68 7.9 12.32 7.9 12.71 8.29L15.71 11.29C16.1 11.68 16.1 12.32 15.71 12.71Z" fill="currentColor"/>
                            </svg>
                        </a>
                    </div>

                    <div class="w-full md:w-1/2 px-4">
                        <div class="flex flex-wrap justify-end">
                            <div class="w-full md:w-auto py-1 md:mr-4">
                                <a class="btn-decline inline-block py-3 px-5 w-full border border-coolGray-200 rounded-md text-coolGray-800 bg-white hover:bg-coolGray-100 text-center font-medium">
                                    {{ __('Decline') }}
                                </a>
                            </div>
                            <div class="w-full md:w-auto py-1">
                                <a class="btn-accept inline-block py-3 px-5 w-full rounded-md bg-indigo-500 hover:bg-indigo-600 text-white text-center font-medium">
                                    {{ __('Allow') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- JS DEL TEMA ACTIVO --}}
    <script src="{{ theme_public_asset('js/app.js') }}" defer></script>
    <script src="{{ theme_public_asset('js/jquery.min.js') }}"></script>
    <script src="{{ theme_public_asset('js/main.js') }}"></script>

</body>
</html>
