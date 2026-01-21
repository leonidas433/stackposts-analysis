<div>
    @include('partials.header')
    @include('partials.sidebar')
    
    <div class="main">
        {!! $page_data !!}
    </div>

    @include('partials.footer')
</div>