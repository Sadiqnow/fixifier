@extends('layouts.admin')
@section('content')
<x-head eyebrow="Step 08 · Financial design" title="Escrow and custody policy" subtitle="Define the intended holding, release and refund rules before connecting a provider."></x-head>

<div class="grid split"><section class="card pad"><x-form :action="route('admin.settings.update','escrow')" :version="$settings->version"><div class="formgrid">
<div class="group full"><x-select name="custody" label="Intended custody model" :value="$s['custody']" :options="['provider_hold'=>'Provider-managed hold or delayed transfer','direct_collection'=>'Direct provider collection with later payout']"/></div>
<x-select name="capture" label="Payment trigger" :value="$s['capture']" :options="['on_quote_acceptance'=>'After quote acceptance']"/>
<x-select name="release" label="Release trigger" :value="$s['release']" :options="['customer_approval'=>'Customer approval or admin ruling']"/>
<x-input name="holdHours" label="Minimum hold after approval (hours)" type="number" min="0" max="720" :value="$s['holdHours']"/>
<x-input name="reviewHours" label="Customer review window (hours)" type="number" min="1" max="720" :value="$s['reviewHours']"/>
<x-auto-approve :value="$s['autoApprove']"/>
</div><div class="actions"><button class="btn primary">Save policy draft</button></div></x-form></section>
<aside class="stack"><div class="card pad"><h2>Transaction states</h2><div class="detail"><span>Customer pays</span><strong>Payment pending</strong></div><div class="detail"><span>Verified provider callback</span><strong>Funded booking</strong></div><div class="detail"><span>Customer approval</span><strong>Release pending</strong></div><div class="detail"><span>Admin refund ruling</span><strong>Refund pending</strong></div><div class="detail"><span>Verified provider event</span><strong>Paid or refunded</strong></div></div><div class="notice"><strong>Policy draft only</strong><br>Provider capabilities, legal custody arrangements, fee allocation, partial refunds and settlement timing require review before this can be called real escrow. A database status must never imply funds are held or transferred.</div></aside></div>

@endsection
