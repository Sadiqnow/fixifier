@extends('layouts.admin')
@section('content')
<x-head eyebrow="Step 09 · Finance operations" title="Earnings and payouts" subtitle="Set the fee formula and preview technician net, provider costs and payout eligibility."></x-head>

<div class="grid split"><div class="stack"><section class="card pad"><h2 class="fx-layout-3">Earnings algorithm</h2><x-form :action="route('admin.settings.update','finance')" :version="$settings->version"><div class="formgrid">
<x-input name="feePercent" label="Platform fee %" type="number" min="0" max="100" step="0.1" :value="$s['feePercent']"/>
<x-input name="feeFixed" label="Platform fixed fee (₦)" type="number" min="0" step="1" :value="$s['feeFixed']"/>
<x-input name="providerFeePercent" label="Provider fee %" type="number" min="0" max="100" step="0.1" :value="$s['providerFeePercent']"/>
<x-input name="providerFeeFixed" label="Provider fixed fee (₦)" type="number" min="0" step="1" :value="$s['providerFeeFixed']"/>
<x-select name="providerFeePayer" label="Provider fee paid by" :value="$s['providerFeePayer']" :options="['platform'=>'Platform','technician'=>'Technician']"/>
<x-input name="reservePercent" label="Temporary reserve %" type="number" min="0" max="100" step="0.1" :value="$s['reservePercent']"/>
<x-select name="payoutBatch" label="Batch schedule" :value="$s['payoutBatch']" :options="['daily'=>'Daily','weekly'=>'Weekly','manual'=>'Manual review']"/>
<x-input name="minPayout" label="Minimum payout (₦)" type="number" min="0" step="1" :value="$s['minPayout']"/>
</div><div class="actions"><button class="btn primary">Save earnings rules</button></div></x-form></section>
<div class="notice blue"><strong>Formula</strong><br>Platform fee = gross × platform % + fixed fee. Provider fee = gross × provider % + fixed fee. Reserve = gross × reserve %. Technician net = gross − platform fee − reserve − provider fee when charged to technician. A live payout requires verified funds, release approval, elapsed hold, minimum balance and no open dispute. This page previews the rule.</div></div>
<div class="stack"><section class="card pad"><h2>Live calculation</h2><div class="fx-layout-4 group"><label for="exampleAmount">Job amount (₦)</label><input id="exampleAmount" class="field" type="number" min="0" step="0.01" max="100000000" value="22000" data-url="{{ route('admin.calculation') }}"></div><div id="calculation">@include('partials.calculation',['c'=>$calculator->calculate(2200000,$s)])</div></section>
<section class="card pad"><h2 class="fx-layout-5">Payout queue</h2>
@forelse($bookings->whereIn('status',['release_pending','refund_pending']) as $j)<div class="item"><div class="row between"><div><strong>#{{ $j->number }} · {{ \App\Support\Portal::money($j->amount) }}</strong><p>{{ \App\Support\Portal::label($j->status) }} · {{ $j->technician?->name ?? 'Unassigned' }}</p></div></div></div>@empty<div class="empty">No pending settlements.</div>@endforelse
</section></div></div>
<section class="fx-layout-1 card pad"><div class="sectionhead"><h2>Job settlement preview</h2></div><div class="tablewrap"><table class="table"><thead><tr><th>Booking</th><th>Gross</th><th>Platform fee</th><th>Provider fee</th><th>Reserve</th><th>Technician net</th><th>State</th></tr></thead><tbody>
@foreach($bookings->where('amount_minor','>',0) as $j)@php($c=$calculator->calculate($j->amount_minor,$s))<tr><td>#{{ $j->number }}</td>@foreach(['gross','platform','provider','reserve','net'] as $key)<td>{{ \App\Support\Portal::money($c[$key]/100) }}</td>@endforeach<td>{{ \App\Support\Portal::label($j->status) }}</td></tr>@endforeach
</tbody></table></div></section>

@endsection
