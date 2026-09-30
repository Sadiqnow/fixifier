<div class="detail"><span>Gross</span><strong>{{ \App\Support\Portal::money($c['gross']/100) }}</strong></div>
<div class="detail"><span>Platform fee</span><strong>− {{ \App\Support\Portal::money($c['platform']/100) }}</strong></div>
<div class="detail"><span>Provider cost</span><strong>{{ \App\Support\Portal::money($c['provider']/100) }} · {{ $s['providerFeePayer'] }} pays</strong></div>
<div class="detail"><span>Reserve held</span><strong>− {{ \App\Support\Portal::money($c['reserve']/100) }}</strong></div>
<div class="detail"><span>Technician net</span><strong class="fx-layout-2">{{ \App\Support\Portal::money($c['net']/100) }}</strong></div>
<div class="detail"><span>Platform net after provider cost</span><strong>{{ \App\Support\Portal::money($c['platformNet']/100) }}</strong></div>
@if(!$c['valid'])<div class="notice">Invalid rules: deductions exceed gross. Reduce fees before publishing.</div>@endif
