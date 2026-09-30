@extends('layouts.admin')
@section('content')
<x-head eyebrow="Step 04 · Operations" title="Job listings" subtitle="Inspect state, ownership, evidence and event history across every booking."></x-head>

@php($shown=$bookings)
<div class="segment">@foreach(['all'=>'All','requested'=>'Unassigned','awaiting_verification'=>'Review','disputed'=>'Disputed','completed'=>'Completed'] as $key=>$label)<a class="{{ $section === $key ? 'active' : '' }}" href="{{ route('admin.jobs',['status'=>$key,'booking'=>$current?->number]) }}">{{ $label }}</a>@endforeach</div>
<div class="grid split"><section class="card pad"><div class="sectionhead"><h2>Bookings</h2><span class="muted small">{{ $shown->count() }} shown</span></div>
@forelse($shown as $j)<div class="item"><div class="row between"><div><strong>#{{ $j->number }} · {{ $j->title }}</strong><p>{{ $j->customer_name }} · {{ $j->category->name }} · {{ $j->area->name }}</p></div><x-badge :status="$j->status"/></div><div class="fx-layout-7 row between"><span class="muted small">{{ $j->technician?->name ?? 'Unassigned' }} · {{ $j->amount_minor ? \App\Support\Portal::money($j->amount) : 'No quote' }}</span><a class="btn sm" href="{{ route('admin.jobs',['booking'=>$j->number,'status'=>$section]) }}">Inspect</a></div></div>@empty<div class="empty">No jobs in this state.</div>@endforelse
</section><aside class="stack">@if($current)
<section class="card pad"><div class="sectionhead"><h2>Booking #{{ $current->number }}</h2><x-badge :status="$current->status"/></div><div class="detail"><span>Reference</span><strong>{{ $current->reference }}</strong></div><p class="muted small">{{ $current->description }} · {{ $current->address }}</p><div class="detail"><span>Customer</span><strong>{{ $current->customer_name }}</strong></div><div class="detail"><span>Technician</span><strong>{{ $current->technician?->name ?? 'Unassigned' }}</strong></div><div class="detail"><span>Quote</span><strong>{{ $current->amount_minor ? \App\Support\Portal::money($current->amount) : 'Pending' }}</strong></div><div class="detail"><span>Work round</span><strong>{{ $current->current_round }}</strong></div><div class="actions">@if($current->status === 'requested')<button class="btn primary sm" data-dialog="assign-{{ $current->number }}">Assign</button>@endif<a class="btn sm" href="{{ route('admin.evidence',['booking'=>$current->number]) }}">Evidence</a>@if($current->status === 'disputed')<a class="btn sm" href="{{ route('admin.disputes',['booking'=>$current->number]) }}">Resolve dispute</a>@endif</div></section>
<section class="card pad"><h2 class="fx-layout-8">Event history</h2><div class="timeline">@foreach($current->events as $event)<div class="step"><strong>{{ $event->description }}</strong></div>@endforeach</div></section>
@include('partials.booking-actions',['j'=>$current])
@endif</aside></div>

@endsection
