{{--
    Synchronous Highcharts loader for full-page views with charts.

    Usage (once per view, at the end of the file):

        @pushOnce('vendor_scripts', 'highcharts')
            @include('partials.scripts.highcharts')
        @endPushOnce

    Pass ['maps' => true] to the include when the view draws a 'map' chart.
    Always use the shared 'highcharts' push id so several partials on the
    same page inject a single copy.

    Views rendered as AJAX fragments (dashboard items, statistics) must NOT
    use this partial — stacks are already rendered by then. They need no
    change at all: Main.Chart lazy-loads these same files on demand.
--}}
<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/modules/exporting.js"></script>
@if(!empty($maps))
    <script src="https://code.highcharts.com/maps/modules/map.js"></script>
    <script src="https://code.highcharts.com/mapdata/custom/world.js"></script>
@endif
