<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#112239">
    <meta name="csrf-token" content="{{ csrf_token() }}"><title>Fixifier Plus · {{ config('fixifier.navigation.'.$page)[1] }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/admin.css') }}"><link rel="stylesheet" href="{{ asset('assets/css/laravel.css') }}">
    <script src="{{ asset('assets/js/admin.js') }}" defer></script>
</head>
<body data-reopen="{{ $errors->any() ? old('_modal','') : '' }}">
<div class="shell">
    @include('partials.sidebar')
    <div class="main">
        @include('partials.topbar')
        <main class="content" id="content">
            @include('partials.feedback')
            @yield('content')
            @if(in_array($page,['requests','jobs','evidence','disputes','payouts']))
                <div class="actions" aria-label="Booking pages">
                    @if($bookingPage->previousPageUrl())<a class="btn sm" href="{{ $bookingPage->previousPageUrl() }}">Previous</a>@endif
                    <span class="muted small">Page {{ $bookingPage->currentPage() }} of {{ $bookingPage->lastPage() }} · {{ $bookingPage->total() }} bookings</span>
                    @if($bookingPage->nextPageUrl())<a class="btn sm" href="{{ $bookingPage->nextPageUrl() }}">Next</a>@endif
                </div>
            @endif
        </main>
    </div>
</div>
<div class="overlay" id="overlay"><div class="dialog" id="dialog" role="dialog" aria-modal="true" aria-labelledby="dialogTitle"></div></div>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
@stack('modals')
</body>
</html>
