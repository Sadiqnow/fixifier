@extends('layouts.admin')
@section('content')
<x-head eyebrow="Step 06 · Adjudication" title="Disputes and rework" subtitle="Review the customer concern, evidence and prior rounds, then record a reasoned decision."></x-head>

<div class="grid split"><section class="card pad"><div class="sectionhead"><h2>Case queue</h2><span class="badge red">{{ $bookings->where('status','disputed')->count() }} open</span></div>
@forelse($bookings->filter(fn($j)=>$j->disputes->isNotEmpty() || $j->status === 'rework_required') as $j)
@php($case=$j->disputes->sortByDesc('id')->first())
<div class="item"><div class="row between"><strong>#{{ $j->number }} · {{ $j->title }}</strong><x-badge :status="$j->status"/></div><p>Customer: {{ $case?->concern ?? 'Previous dispute round' }}</p><p>Work round {{ $j->current_round }} · {{ $j->currentRound()?->evidence->where('phase','before')->count() ?? 0 }} before / {{ $j->currentRound()?->evidence->where('phase','after')->count() ?? 0 }} after records</p>
@if($case?->resolution_reason)<p>Decision: {{ $case->resolution_reason }}</p>@endif
<div class="actions"><a class="btn sm" href="{{ route('admin.evidence',['booking'=>$j->number]) }}">Evidence</a>@if($j->status === 'disputed')<button class="btn sm primary" data-dialog="resolve-{{ $j->number }}">Decide</button>@endif</div></div>
@if($j->status === 'disputed')<x-modal :id="'resolve-'.$j->number" :title="'Resolve case #'.$j->number"><p class="muted small">Customer reason: {{ $case?->concern }}</p><div class="detail"><span>Before / after</span><strong>{{ $j->currentRound()?->evidence->where('phase','before')->count() ?? 0 }} / {{ $j->currentRound()?->evidence->where('phase','after')->count() ?? 0 }}</strong></div>
<x-form :action="route('admin.disputes.resolve',$j->id)" :version="$j->version"><x-select name="decision" label="Outcome" value="rework_required" :options="['rework_required'=>'Order rework','release_pending'=>'Approve release request','refund_pending'=>'Approve refund request']"/><x-reason label="Decision reason" placeholder="State the evidence and reason"/><div class="actions"><button class="btn primary">Record decision</button></div></x-form></x-modal>@endif
@empty<div class="empty">No cases.</div>@endforelse
</section><aside class="stack"><div class="card pad"><h2>Decision options</h2><div class="detail"><span>Rework</span><strong>New work round and fresh evidence</strong></div><div class="detail"><span>Release</span><strong>Payout request pending provider</strong></div><div class="detail"><span>Refund</span><strong>Refund request pending provider</strong></div></div><div class="notice">Each dispute is linked to its work round. Prior reasons, resolutions and evidence remain in the audit trail.</div></aside></div>

@endsection
