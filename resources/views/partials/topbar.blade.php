<header class="top"><div class="row"><button class="btn sm mobile" type="button" data-menu aria-label="Open menu" aria-expanded="false">☰</button><strong id="toplabel">{{ config('fixifier.navigation.'.$page)[1] }}</strong></div>
    <div class="topright"><span class="demo">ADMIN PORTAL · Payment integration pending</span><span class="avatar" title="{{ auth()->user()->name }}">{{ mb_strtoupper(mb_substr(auth()->user()->name,0,2)) }}</span>
    <form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="btn sm">Sign out</button></form></div>
</header>
