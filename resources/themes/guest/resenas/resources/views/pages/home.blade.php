{{--
    guest/resenas — home override (only view this child overrides).
    On-page structure follows docs/seo-tema-resenas.md §2:
    Hero (H1 keyword principal) → 4 features de gestión de reseñas (H2 + H3s)
    → valor añadido: programación de redes (H2) → prueba social 87% (H2)
    → CTA final (H2) → 2 FAQs long-tail (H2 + H3s).
    Rendered inside the inherited layout (<main id="main"> + skip-link).
--}}

{{-- 1. Hero — keyword principal: "análisis de reseñas" --}}
<section class="bg-blueGray-50 relative z-50">
    <div class="overflow-hidden pt-32">
        <div class="container px-4 mx-auto">
            <div class="flex flex-wrap -m-8">
                <div class="w-full md:w-1/2 p-8">
                    <div class="inline-block mb-6 px-2 py-1 font-semibold bg-blue-100 rounded-full">
                        <div class="flex flex-wrap items-center -m-1">
                            <div class="w-auto p-1">
                                <a class="text-sm" href="{{ url('auth/signup') }}">⭐ {{ __("Reviews + social media, all in one place") }}</a>
                            </div>
                            <div class="w-auto p-1">
                                <svg width="15" height="15" viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                                    <path d="M8.66667 3.41675L12.75 7.50008M12.75 7.50008L8.66667 11.5834M12.75 7.50008L2.25 7.50008" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <h1 class="mb-6 text-6xl md:text-8xl lg:text-10xl font-bold font-heading md:max-w-xl leading-none">
                        {{ __("AI review analysis to take care of your online reputation") }}
                    </h1>
                    <p class="mb-11 text-lg text-gray-900 font-medium md:max-w-md">
                        {{ __("Monitor, understand and reply to your Google reviews from a single dashboard — and keep your online reputation always under control.") }}
                    </p>
                    <div class="flex flex-wrap -m-2.5 mb-20">
                        <div class="w-full md:w-auto p-2.5">
                            <div class="block">
                                <a href="{{ url('auth/signup') }}" class="block py-4 px-6 w-full text-white font-semibold border border-indigo-700 rounded-xl focus:ring focus:ring-indigo-300 bg-indigo-600 hover:bg-indigo-700 transition ease-in-out duration-200">
                                    {{ __("Start for free") }}
                                </a>
                            </div>
                        </div>
                        <div class="w-full md:w-auto p-2.5">
                            <div class="block">
                                <a href="{{ url('pricing') }}" class="block py-4 px-9 w-full font-semibold border border-gray-300 hover:border-gray-400 rounded-xl focus:ring focus:ring-gray-50 bg-transparent hover:bg-gray-100 transition ease-in-out duration-200">
                                    <div class="flex flex-wrap justify-center items-center -m-1">
                                        <div class="w-auto p-1">
                                            <span>{{ __("See pricing") }} <i class="fa-light fa-chevron-right" aria-hidden="true"></i></span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                    <p class="mb-6 text-sm text-gray-500 font-semibold uppercase">
                        {{ __("Trusted by local businesses and agencies") }}
                    </p>
                    <div class="flex flex-wrap -m-4 md:pb-20">
                        <div class="w-auto p-4">
                            <img class="h-10" src="{{ theme_public_asset('logos/brands/brand_9.png') }}" alt="">
                        </div>
                        <div class="w-auto p-4">
                            <img class="h-10" src="{{ theme_public_asset('logos/brands/brand_2.png') }}" alt="">
                        </div>
                        <div class="w-auto p-4">
                            <img class="h-10" src="{{ theme_public_asset('logos/brands/brand_3.png') }}" alt="">
                        </div>
                    </div>
                </div>
                <div class="w-full md:w-1/2 p-8">
                    <div class="relative mx-auto md:mr-0 max-w-max">
                        <img class="absolute z-10 -left-14 -top-12 w-28 md:w-auto" src="{{ theme_public_asset('images/headers/circle3-yellow.svg') }}" alt="">
                        <img class="absolute z-10 -right-7 -bottom-8 w-28 md:w-auto" src="{{ theme_public_asset('images/headers/dots3-blue.svg') }}" alt="">
                        <img class="relative rounded-7xl rounded-[50]" src="{{ theme_public_asset('images/headers/header.png') }}" alt="{{ __("Review analysis dashboard preview") }}">
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- 2. Features — keyword secundaria: "gestión de reseñas de Google" --}}
<section class="py-24 md:pb-32 bg-white overflow-hidden" id="features" style="background-image: url({{ theme_public_asset('images/features/pattern-white.svg') }}); background-position: center;">
    <div class="container px-4 mx-auto">
        <h2 class="mb-10 text-6xl md:text-7xl xl:text-8xl font-bold font-heading text-center tracking-px-n leading-none">
            {{ __("Google review management without the busywork") }}
        </h2>
        <p class="mb-20 text-xl text-center text-gray-500 font-medium leading-relaxed max-w-2xl mx-auto">
            {{ __("Everything you need to run your business reviews: review management software built for local businesses.") }}
        </p>
        <div class="flex flex-wrap -mx-4">
            {{-- 2.1 Análisis de sentimiento con IA --}}
            <div class="w-full md:w-1/2 lg:w-1/4 px-4 mb-8">
                <div class="h-full p-8 text-center bg-gray-50 rounded-4xl border-f5 border hover:shadow-xl hover:border-xl transition duration-200">
                    <div class="inline-flex h-16 w-16 mb-6 mx-auto items-center justify-center text-indigo-600 bg-indigo-100 rounded-4xl text-3xl">
                        <i class="fal fa-smile" aria-hidden="true"></i>
                    </div>
                    <h3 class="mb-4 text-xl md:text-2xl leading-tight font-bold">{{ __("AI sentiment analysis") }}</h3>
                    <p class="text-coolGray-500 font-medium">{{ __("Understand at a glance what your customers praise and complain about: our AI classifies every review by sentiment and topic.") }}</p>
                </div>
            </div>
            {{-- 2.2 Responder reseñas con IA --}}
            <div class="w-full md:w-1/2 lg:w-1/4 px-4 mb-8">
                <div class="h-full p-8 text-center bg-gray-50 rounded-4xl border-f5 border hover:shadow-xl hover:border-xl transition duration-200">
                    <div class="inline-flex h-16 w-16 mb-6 mx-auto items-center justify-center text-green-500 bg-green-100 rounded-4xl text-3xl">
                        <i class="fal fa-comments" aria-hidden="true"></i>
                    </div>
                    <h3 class="mb-4 text-xl md:text-2xl leading-tight font-bold">{{ __("Reply to reviews with AI") }}</h3>
                    <p class="text-coolGray-500 font-medium">{{ __("Generate replies in your brand tone of voice and answer every review in seconds, always with your approval.") }}</p>
                </div>
            </div>
            {{-- 2.3 Monitorización multiplataforma --}}
            <div class="w-full md:w-1/2 lg:w-1/4 px-4 mb-8">
                <div class="h-full p-8 text-center bg-gray-50 rounded-4xl border-f5 border hover:shadow-xl hover:border-xl transition duration-200">
                    <div class="inline-flex h-16 w-16 mb-6 mx-auto items-center justify-center text-orange-500 bg-orange-100 rounded-4xl text-3xl">
                        <i class="fal fa-bell" aria-hidden="true"></i>
                    </div>
                    <h3 class="mb-4 text-xl md:text-2xl leading-tight font-bold">{{ __("Multi-platform monitoring") }}</h3>
                    <p class="text-coolGray-500 font-medium">{{ __("Follow your Google, Facebook and other platform reviews from a single inbox and never miss a new opinion again.") }}</p>
                </div>
            </div>
            {{-- 2.4 Informes de reputación --}}
            <div class="w-full md:w-1/2 lg:w-1/4 px-4 mb-8">
                <div class="h-full p-8 text-center bg-gray-50 rounded-4xl border-f5 border hover:shadow-xl hover:border-xl transition duration-200">
                    <div class="inline-flex h-16 w-16 mb-6 mx-auto items-center justify-center text-teal-500 bg-teal-100 rounded-4xl text-3xl">
                        <i class="fal fa-chart-line" aria-hidden="true"></i>
                    </div>
                    <h3 class="mb-4 text-xl md:text-2xl leading-tight font-bold">{{ __("Reputation reports") }}</h3>
                    <p class="text-coolGray-500 font-medium">{{ __("Track your average rating, review volume and sentiment trends with clear reports you can share with your team or clients.") }}</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- 3. Valor añadido — "programar publicaciones en redes sociales" / "en un solo lugar" --}}
<section class="py-24 md:pb-32 bg-white overflow-hidden" style="background-image: url({{ theme_public_asset('images/features/pattern-white.svg') }}); background-position: center;">
    <div class="container px-4 mx-auto">
        <div class="flex flex-wrap items-center -m-8">
            <div class="w-full md:w-1/2 p-8">
                <h2 class="mb-9 text-6xl md:text-7xl font-bold font-heading tracking-px-n leading-tight">
                    {{ __("Plus: schedule every social network's posts in one place") }}
                </h2>
                <p class="mb-10 text-lg text-gray-900 font-medium leading-relaxed md:max-w-md">
                    {{ __("Reputation brings customers in; content keeps them engaged. Schedule your social media posts and manage every network in one place: content calendar, campaigns, drafts and automatic RSS publishing.") }}
                </p>
                <ul class="mb-11 space-y-4">
                    <li class="flex items-start">
                        <svg class="mt-1 mr-3 flex-shrink-0" width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
                            <rect x="4" y="5" width="16" height="15" rx="4" stroke="#1D4ED8" stroke-width="2"/>
                            <path d="M8 2v4M16 2v4M4 10h16" stroke="#1D4ED8" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <p class="text-gray-600 font-medium"><strong class="text-gray-900">{{ __("Content calendar") }}</strong> — {{ __("plan and drag your posts on a single calendar for every network.") }}</p>
                    </li>
                    <li class="flex items-start">
                        <svg class="mt-1 mr-3 flex-shrink-0" width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
                            <rect x="3" y="4" width="18" height="4" rx="2" stroke="#1D4ED8" stroke-width="2"/>
                            <rect x="3" y="10" width="18" height="4" rx="2" stroke="#1D4ED8" stroke-width="2"/>
                            <rect x="3" y="16" width="18" height="4" rx="2" stroke="#1D4ED8" stroke-width="2"/>
                        </svg>
                        <p class="text-gray-600 font-medium"><strong class="text-gray-900">{{ __("Campaigns") }}</strong> — {{ __("group posts into campaigns and launch them everywhere at once.") }}</p>
                    </li>
                    <li class="flex items-start">
                        <svg class="mt-1 mr-3 flex-shrink-0" width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
                            <path d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" stroke="#1D4ED8" stroke-width="2" stroke-linejoin="round"/>
                            <path d="M14 3v6h6" stroke="#1D4ED8" stroke-width="2" stroke-linejoin="round"/>
                        </svg>
                        <p class="text-gray-600 font-medium"><strong class="text-gray-900">{{ __("Drafts and approvals") }}</strong> — {{ __("prepare content in advance and publish only what you approve.") }}</p>
                    </li>
                    <li class="flex items-start">
                        <svg class="mt-1 mr-3 flex-shrink-0" width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
                            <path d="M5 5a14 14 0 0 1 14 14M5 11a8 8 0 0 1 8 8" stroke="#1D4ED8" stroke-width="2" stroke-linecap="round"/>
                            <circle cx="6" cy="18" r="1.5" fill="#1D4ED8"/>
                        </svg>
                        <p class="text-gray-600 font-medium"><strong class="text-gray-900">{{ __("RSS auto-posting") }}</strong> — {{ __("turn your blog or news feed into fresh social content automatically.") }}</p>
                    </li>
                </ul>
                <div class="md:inline-block rounded-xl md:shadow-4xl">
                    <a href="{{ url('auth/signup') }}" class="block py-4 px-6 w-full text-white text-center font-semibold border border-indigo-700 rounded-xl focus:ring focus:ring-indigo-300 bg-indigo-600 hover:bg-indigo-700 transition ease-in-out duration-200">
                        {{ __("Schedule your first post") }}
                    </a>
                </div>
            </div>
            <div class="w-full md:w-1/2 p-8">
                <div class="relative mx-auto md:mr-0 max-w-max">
                    <img class="absolute z-10 -left-14 -top-12 w-28 md:w-auto" src="{{ theme_public_asset('images/headers/circle3-yellow.svg') }}" alt="">
                    <img class="absolute z-10 -right-7 -bottom-8 w-28 md:w-auto" src="{{ theme_public_asset('images/headers/dots3-blue.svg') }}" alt="">
                    <img class="relative rounded-7xl rounded-[50]" src="{{ theme_public_asset('images/features/feature-demo-1.png') }}" alt="{{ __("Social media scheduling calendar preview") }}">
                </div>
            </div>
        </div>
    </div>
</section>

{{-- 4. Prueba social — dato del 87% (BrightLocal) + testimonios placeholder --}}
<section class="relative pt-24 pb-32 bg-white overflow-hidden">
    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
        <img class="max-w-full max-h-full" src="{{ theme_public_asset('images/testimonials/gradient3.svg') }}" alt="">
    </div>
    <div class="relative z-10 container px-4 mx-auto">
        <div class="md:max-w-4xl mb-16 mx-auto text-center">
            <h2 class="mb-9 text-6xl md:text-7xl font-bold font-heading tracking-px-n leading-tight">
                {{ __("Your online reputation is your best salesperson") }}
            </h2>
            <p class="mb-2 text-8xl md:text-9xl font-bold font-heading text-indigo-600 leading-none">87%</p>
            <p class="text-lg md:text-xl text-coolGray-500 font-medium max-w-2xl mx-auto">
                {{ __("of consumers read Google reviews before visiting a local business (source: BrightLocal).") }}
            </p>
        </div>
        <div class="flex flex-wrap justify-center -m-2">
            <div class="w-full md:w-1/2 lg:w-1/3 p-2">
                <div class="px-8 py-6 h-full bg-white bg-opacity-80 rounded-3xl">
                    <div class="flex flex-col justify-between h-full">
                        <div class="mb-7 block">
                            <div class="flex flex-wrap -m-0.5 mb-6">
                                <span class="sr-only">{{ __("Rated 5 out of 5 stars") }}</span>
                                @for ($i = 0; $i < 5; $i++)
                                    <div class="w-auto p-0.5">
                                        <svg width="19" height="18" viewBox="0 0 19 18" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                                            <path d="M9.30769 0L12.1838 5.82662L18.6154 6.76111L13.9615 11.2977L15.0598 17.7032L9.30769 14.6801L3.55554 17.7032L4.65385 11.2977L0 6.76111L6.43162 5.82662L9.30769 0Z" fill="#F59E0B"></path>
                                        </svg>
                                    </div>
                                @endfor
                            </div>
                            <h3 class="mb-6 text-lg font-bold font-heading">
                                {{ __("“We went from ignoring reviews to replying to all of them in minutes.”") }}
                            </h3>
                            <p class="text-lg font-medium">
                                {{ __("The AI drafts the reply in our tone and we just review and send. Our average rating went up in the very first month.") }}
                            </p>
                        </div>
                        <div class="block">
                            <p class="font-bold">{{ __("María G. — Restaurant owner") }}</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="w-full md:w-1/2 lg:w-1/3 p-2">
                <div class="px-8 py-6 h-full bg-white bg-opacity-80 rounded-3xl">
                    <div class="flex flex-col justify-between h-full">
                        <div class="mb-7 block">
                            <div class="flex flex-wrap -m-0.5 mb-6">
                                <span class="sr-only">{{ __("Rated 5 out of 5 stars") }}</span>
                                @for ($i = 0; $i < 5; $i++)
                                    <div class="w-auto p-0.5">
                                        <svg width="19" height="18" viewBox="0 0 19 18" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                                            <path d="M9.30769 0L12.1838 5.82662L18.6154 6.76111L13.9615 11.2977L15.0598 17.7032L9.30769 14.6801L3.55554 17.7032L4.65385 11.2977L0 6.76111L6.43162 5.82662L9.30769 0Z" fill="#F59E0B"></path>
                                        </svg>
                                    </div>
                                @endfor
                            </div>
                            <h3 class="mb-6 text-lg font-bold font-heading">
                                {{ __("“Reviews and social posts, finally in the same tool.”") }}
                            </h3>
                            <p class="text-lg font-medium">
                                {{ __("We manage our clients' reputation and their content calendar without switching tabs. It saves us hours every week.") }}
                            </p>
                        </div>
                        <div class="block">
                            <p class="font-bold">{{ __("Carlos R. — Local marketing agency") }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- 5a. CTA final --}}
<section class="py-24 bg-blueGray-50 overflow-hidden">
    <div class="container px-4 mx-auto">
        <div class="md:max-w-3xl mx-auto text-center">
            <h2 class="mb-6 text-6xl md:text-7xl font-bold font-heading tracking-px-n leading-tight">
                {{ __("Start taking care of your reviews today") }}
            </h2>
            <p class="mb-11 text-lg text-gray-900 font-medium">
                {{ __("Create your free account and analyze your first Google reviews in minutes. No credit card required.") }}
            </p>
            <div class="flex flex-wrap justify-center -m-2.5">
                <div class="w-full md:w-auto p-2.5">
                    <a href="{{ url('auth/signup') }}" class="block py-4 px-6 w-full text-white font-semibold border border-indigo-700 rounded-xl focus:ring focus:ring-indigo-300 bg-indigo-600 hover:bg-indigo-700 transition ease-in-out duration-200">
                        {{ __("Start for free") }}
                    </a>
                </div>
                <div class="w-full md:w-auto p-2.5">
                    <a href="{{ url('pricing') }}" class="block py-4 px-9 w-full font-semibold border border-gray-300 hover:border-gray-400 rounded-xl focus:ring focus:ring-gray-50 bg-white hover:bg-gray-100 transition ease-in-out duration-200">
                        {{ __("See pricing") }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- 5b. FAQs long-tail (alimentan AI Overviews) — h3 + p semánticos, sin JS --}}
<section class="pt-24 pb-28 bg-white overflow-hidden">
    <div class="container px-4 mx-auto">
        <div class="md:max-w-4xl mx-auto">
            <h2 class="mb-16 text-6xl md:text-7xl text-center font-bold font-heading tracking-px-n leading-none">
                {{ __("Questions about review management") }}
            </h2>
            <div class="mb-11 space-y-4">
                <div class="py-7 px-8 bg-white border-2 border-gray-200 rounded-2xl shadow-10xl">
                    <h3 class="mb-4 text-lg font-semibold leading-normal">
                        {{ __("How should I reply to negative Google reviews?") }}
                    </h3>
                    <p class="text-gray-600 font-medium">
                        {{ __("Reply quickly, thank the feedback, acknowledge the problem and offer a solution or a direct contact channel. With Analytee, the AI drafts an empathetic reply to every negative review in your brand tone: you review it and publish it in one click.") }}
                    </p>
                </div>
                <div class="py-7 px-8 bg-white border-2 border-gray-200 rounded-2xl shadow-10xl">
                    <h3 class="mb-4 text-lg font-semibold leading-normal">
                        {{ __("What does review management software do for a local business?") }}
                    </h3>
                    <p class="text-gray-600 font-medium">
                        {{ __("It centralizes the reviews from every platform, alerts you to each new opinion, analyzes their sentiment with AI and helps you reply to all of them — improving your local ranking and the trust of future customers.") }}
                    </p>
                </div>
            </div>
            <p class="text-gray-600 text-center font-medium">
                <span>{{ __("Still have any questions?") }}</span>
                <a class="font-semibold text-indigo-600 hover:text-indigo-700" href="{{ url('contact') }}">{{ __("Contact us") }}</a>
            </p>
        </div>
    </div>
</section>
