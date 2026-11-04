@php
    $trackingBodyCode = \App\Support\AdminSettings::trackingBodyCode();
@endphp
@if($trackingBodyCode !== '')
{!! $trackingBodyCode !!}
@endif
