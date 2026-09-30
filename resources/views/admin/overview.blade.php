@extends('layouts.admin')
@section('content')
<x-head eyebrow="Platform operations" title="Admin command center" subtitle="Configure marketplace rules and work through the queues that need a decision."><a class="btn primary" href="{{ route('admin.setup') }}">Set up marketplace</a></x-head>

<div class="grid stats">
<x-metric title="Pending verification" :value="$technicians->where('kyc','pending_review')->count()" description="Identity decisions"/>
<x-metric title="Unassigned requests" :value="$metrics['unassigned']" description="Routing needed"/>
<x-metric title="Open disputes" :value="$metrics['disputed']" description="Reasoned decisions"/>
<x-metric title="Pending settlements" :value="$metrics['settlementPending']" description="Release or refund pending"/>
</div>
<div class="wide">
<section class="card pad"><div class="sectionhead"><h2>Marketplace</h2><span class="badge green">{{ $s['city'] }}</span></div><div class="detail"><span>Active categories</span><strong>{{ $categories->where('active',true)->count() }}</strong></div><div class="detail"><span>Active areas</span><strong>{{ $areas->where('active',true)->count() }}</strong></div><div class="detail"><span>Provider visibility</span><strong>Approved + available</strong></div><div class="actions"><a class="btn sm" href="{{ route('admin.setup') }}">Configure</a></div></section>
<section class="card pad"><div class="sectionhead"><h2>Requests &amp; jobs</h2></div><div class="detail"><span>Total jobs</span><strong>{{ $metrics['total'] }}</strong></div><div class="detail"><span>Customer review</span><strong>{{ $metrics['review'] }}</strong></div><div class="detail"><span>Rework rounds</span><strong>{{ $metrics['rework'] }}</strong></div><div class="actions"><a class="btn sm" href="{{ route('admin.requests') }}">Open routing</a></div></section>
<section class="card pad"><div class="sectionhead"><h2>Settlement policy</h2></div><div class="detail"><span>Custody model</span><strong>{{ $s['custody'] === 'provider_hold' ? 'Provider-managed hold' : 'Direct collection' }}</strong></div><div class="detail"><span>Platform fee</span><strong>{{ $s['feePercent'] }}%</strong></div><div class="detail"><span>Payout batch</span><strong>{{ $s['payoutBatch'] }}</strong></div><div class="actions"><a class="btn sm" href="{{ route('admin.payouts') }}">View calculator</a></div></section>
</div>
<div class="fx-layout-1 notice">Admin records are saved to the existing database. This portal does not hold funds, charge customers or pay technicians. Demo-provider payment records remain simulations.</div>

@endsection
