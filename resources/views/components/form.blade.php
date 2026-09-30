@props(['action','version'=>null])
<form method="POST" action="{{ $action }}" {{ $attributes }}>@csrf
@if($version !== null)<input type="hidden" name="version" value="{{ $version }}">@endif
{{ $slot }}</form>
