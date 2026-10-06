<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#112239">
    <meta name="api-base" content="{{ url('/api/v1') }}">
    <meta name="login-url" content="{{ url('/technician/login') }}">
    <meta name="verification-url" content="{{ route('job.verification') }}">
    <title>Fixifier Professional OS · Technician portal</title>
    <link rel="stylesheet" href="{{ asset('css/technician.css') }}?v=journey-1">
    <link rel="stylesheet" href="{{ asset('css/technician-professional.css') }}?v=os-1">
    <script src="{{ asset('js/technician-views.js') }}?v=os-1" defer></script>
    <script src="{{ asset('js/technician-schedule.js') }}?v=schedule-1" defer></script>
    <script src="{{ asset('js/technician.js') }}?v=os-1" defer></script>
</head>
<body>
<div class="shell">
    <aside class="side" id="sidebar" aria-label="Technician navigation">
        <a class="brand" href="{{ url('/') }}"><b>F</b><span class="brandtext">fixifier<small>PROFESSIONAL OS</small></span></a>
        <nav class="nav" id="nav" aria-label="Workspace sections">
            @foreach ([
                'Work' => [['overview','◈','Command Centre'],['requests','◫','Incoming requests'],['schedule','◷','Schedule & availability'],['jobs','⚒','Job workspace']],
                'Business' => [['quotes','▤','Quotations'],['earnings','⇄','Earnings & payouts']],
                'Identity' => [['profile','◌','Professional profile'],['verification','✓','Verification & documents']],
                'Control' => [['evidence','◉','Work evidence'],['disputes','⚑','Disputes & rework'],['flow','≡','Process flow']],
            ] as $group => $items)
                <div class="sidehead">{{ $group }}</div>
                @foreach ($items as [$page, $icon, $label])
                    <button type="button" data-page="{{ $page }}"><span class="icon" aria-hidden="true">{{ $icon }}</span>{{ $label }}</button>
                @endforeach
            @endforeach
        </nav>
        <div class="sidefoot">Your technician workspace<br>Requests, quotes and private work evidence.
            <a class="verification-link" href="{{ route('job.verification') }}">Open job verification →</a>
        </div>
    </aside>
    <div class="main">
        <header class="top">
            <div class="row">
                <button class="btn sm mobile" id="menu" aria-label="Toggle navigation" aria-controls="sidebar" aria-expanded="false">☰</button>
                <div><strong id="pageTitle">Overview</strong><br><small class="muted" id="today"></small></div>
            </div>
            <div class="topright">
                <div class="identity"><strong id="topName">Technician</strong><br><small class="muted" id="topTrade"></small></div>
                <span class="avatar" id="avatar" aria-hidden="true">F</span>
                <button class="btn sm" id="logout">Sign out</button>
            </div>
        </header>
        <main class="content" id="content" aria-live="polite" aria-busy="true"><div class="card pad">Loading your workspace…</div></main>
    </div>
</div>
<div class="overlay" id="overlay">
    <div class="dialog" role="dialog" aria-modal="true" aria-labelledby="modalTitle" tabindex="-1">
        <div class="sectionhead"><h2 id="modalTitle"></h2><button class="btn sm" id="closeModal" aria-label="Close dialog">×</button></div>
        <div id="modalBody"></div>
    </div>
</div>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
@include('partials.journey', ['journeyBase' => url('/api/v1')])
<noscript><p class="notice">Enable JavaScript to sign in and manage your technician jobs.</p></noscript>
</body>
</html>
