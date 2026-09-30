@props(['id','title'])
@push('modals')
<template id="{{ $id }}"><div class="fx-layout-3 row between"><h2 id="dialogTitle">{{ $title }}</h2><button class="btn sm" type="button" data-close aria-label="Close dialog">×</button></div>{{ $slot }}</template>
@endpush
