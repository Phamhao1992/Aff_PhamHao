@php
    $trackingHeadCode = \App\Support\AdminSettings::trackingHeadCode();
@endphp
@if($trackingHeadCode !== '')
{!! $trackingHeadCode !!}
@endif
