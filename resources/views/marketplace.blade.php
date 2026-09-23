<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="api-base" content="{{ url('/api/v1') }}">
    <meta name="booking-intent" content="{{ request()->routeIs('booking.create') ? 'true' : 'false' }}">
    <meta name="admin-url" content="{{ url('/admin') }}">
    <meta name="technician-url" content="{{ url('/technician') }}">
    <title>Fixifier Plus — Service Marketplace</title>
    <meta name="description" content="Book repairs, manage quotations and review before-and-after evidence with Fixifier Plus.">
    <link rel="stylesheet" href="{{ asset('css/marketplace.css') }}">
    <script src="{{ asset('js/marketplace.js') }}" defer></script>
<meta name="verification-url" content="{{ route('job.verification') }}"></head>
<body>
<section id="login" class="login">
    <div class="login-art"><div class="brand"><div class="mark">F+</div> Fixifier Plus</div><h1>Repairs managed with proof, trust and clarity.</h1><p>One connected workspace for customers, technicians and service administrators.</p><div class="trust"><div><b>Book</b>Find repair support</div><div><b>Track</b>Follow every step</div><div><b>Review</b>See the evidence</div></div></div>
    <div class="login-panel">
        <h2 id="authTitle">Welcome back</h2><p>Sign in to your Fixifier Plus account.</p>
        <form id="authForm" class="form">
            <div class="field register-only hidden"><label for="name">Full name</label><input id="name" name="name" autocomplete="name" maxlength="120"></div>
            <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" autocomplete="username" required></div>
            <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
            <div class="field register-only hidden"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"></div>
            <div class="field register-only hidden"><label for="role">Account type</label><select id="role" name="role"><option value="customer">Customer</option><option value="technician">Technician</option></select><p class="meta">Use at least 10 characters with letters and numbers.</p></div>
            <p id="authError" class="notice hidden" role="alert"></p>
            <button id="authSubmit" class="btn">Sign in</button>
            <button id="toggleAuth" type="button" class="ghost">Create an account</button>
        </form>
    </div>
</section>
<section id="app" class="app hidden"><header class="top"><div class="brand"><div class="mark">F+</div><span class="brand-name">Fixifier Plus</span></div><div class="top-right"><span id="rolePill" class="role-pill"></span><button class="ghost" id="logout">Sign out</button></div></header><div class="shell"><aside><nav id="nav" class="nav" aria-label="Main navigation"></nav><a href="{{ route('job.verification') }}" style="display:block;margin:12px 0;padding:12px 14px;border-radius:10px;color:inherit;text-decoration:none;font-weight:650">Job Verification</a></aside><main id="main" aria-live="polite"></main></div></section>
<dialog id="modal" class="dialog" aria-labelledby="modalTitle"><div class="dialog-head"><h2 id="modalTitle"></h2><button class="x" id="closeModal" aria-label="Close dialog">×</button></div><div id="modalBody"></div></dialog>
<div id="toast" class="toast hidden" role="status"></div>
<noscript><p>Please enable JavaScript to use the marketplace.</p></noscript>
</body>
</html>
