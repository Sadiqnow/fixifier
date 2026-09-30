@extends('layouts.admin')
@section('content')
<x-head eyebrow="Step 03 · Request management" title="Request routing" subtitle="Review incoming jobs, eligibility and assignment before the quote stage."></x-head>

@php($queue=$bookings->whereIn('status',['requested','awaiting_quote']))
<div class="grid split"><section class="card pad"><div class="sectionhead"><h2>Request queue</h2><span class="badge orange">{{ $queue->count() }} open</span></div>
@forelse($queue as $j)<div class="item"><div class="row between"><div><strong>#{{ $j->number }} · {{ $j->title }}</strong><p>{{ $j->customer_name }} · {{ $j->category->name }} · {{ $j->area->name }}</p></div><x-badge :status="$j->status"/></div><div class="actions"><a class="btn sm" href="{{ route('admin.jobs',['booking'=>$j->number]) }}">Inspect</a>
@if($j->status === 'requested')<button class="btn sm primary" data-dialog="assign-{{ $j->number }}">Assign technician</button><button class="btn sm" data-dialog="close-{{ $j->number }}">Close request</button>
@else <button class="btn sm" data-dialog="requeue-{{ $j->number }}">Reassign</button>@endif
</div></div>@include('partials.booking-actions',['j'=>$j])
@empty<div class="empty">No requests waiting.</div>@endforelse
</section><aside class="stack"><div class="card pad"><h2>Routing policy</h2><div class="detail"><span>Mode</span><strong>{{ str_replace('_',' ',$s['routing']) }}</strong></div><div class="detail"><span>Response limit</span><strong>{{ $s['requestTimeout'] }} hours</strong></div><p class="fx-layout-10 muted small">A ranked match uses rating, same-area coverage, availability and completed work. Admin confirms assignment; ranking alone never assigns a job.</p><a class="btn sm" href="{{ route('admin.ratings') }}">Review ranking rule</a></div><div class="notice">Admin reassignment after confirmed payment needs an approved financial policy. Assignment is limited to unassigned, unpaid requests.</div></aside></div>

@endsection
