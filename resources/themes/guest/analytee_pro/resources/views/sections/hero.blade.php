<section class="bg-blueGray-50 relative z-50">
    <div class="overflow-hidden pt-32">
        <div class="container px-4 mx-auto">
            <div class="flex flex-wrap -m-8">
                <div class="w-full md:w-1/2 p-8">
                    <div class="inline-block mb-6 px-2 py-1 font-semibold bg-green-100 rounded-full">
                        <div class="flex flex-wrap items-center -m-1">
                            <div class="w-auto p-1">
                                <a class="text-sm" href="{{ url('auth/login') }}">✨ {{ __("ORM y Customer Experience") }}</a>
                            </div>
                            <div class="w-auto p-1">
                                <svg width="15" height="15" viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M8.66667 3.41675L12.75 7.50008M12.75 7.50008L8.66667 11.5834M12.75 7.50008L2.25 7.50008" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <h1 class="mb-6 text-6xl md:text-8xl lg:text-10xl font-bold font-heading md:max-w-xl leading-none">
                        {{ __("Análisis profesional de reseñas de Google Maps para mejorar reputación y experiencia de cliente") }}
                    </h1>
                    <p class="mb-11 text-lg text-gray-900 font-medium md:max-w-md">
                        {{ __("Plataforma de analítica ORM y Customer Experience que convierte reseñas en decisiones basadas en datos.") }}
                    </p>
                    <div class="flex flex-wrap -m-2.5 mb-20">
                        <div class="w-full md:w-auto p-2.5">
                            <div class="block">
                                <a href="{{ url('auth/login') }}" class="block py-4 px-6 w-full text-white font-semibold border border-indigo-700 rounded-xl focus:ring focus:ring-indigo-300 bg-indigo-600 hover:bg-indigo-700 transition ease-in-out duration-200" type="button">
                                    {{ __("Solicitar informe de ejemplo") }}
                                </a>
                            </div>
                        </div>
                        <div class="w-full md:w-auto p-2.5">
                            <div class="block">
                                <a href="{{ url('') }}#features" class="block py-4 px-9 w-full font-semibold border border-gray-300 hover:border-gray-400 rounded-xl focus:ring focus:ring-gray-50 bg-transparent hover:bg-gray-100 transition ease-in-out duration-200" type="button">
                                    <div class="flex flex-wrap justify-center items-center -m-1">
                                        <div class="w-auto p-1">
                                            <span>{{ __("Ver cómo funciona") }} <i class="fa-solid fa-circle-play"></i></span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                    <p class="mb-6 text-sm text-gray-500 font-semibold uppercase">
                        {{ __("KPIs auditables, datos seguros y cumplimiento") }}
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
                      <img class="relative rounded-7xl rounded-[50]" src="{{ theme_public_asset('images/headers/header.png') }}" alt="">
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
