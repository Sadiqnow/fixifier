@extends('layouts.admin')
@section('content')
<x-head eyebrow="Control center" title="Audit and exceptions" subtitle="Review recorded admin actions and payment records."></x-head>

<div class="grid split"><section class="card pad"><div class="sectionhead"><h2>Admin event trail</h2><span class="muted small">Latest 250 records</span></div>@forelse($audit as $entry)<div class="item"><strong>{{ $entry->action }}</strong><p>{{ $entry->target }} · {{ $entry->actor_name }} · {{ $entry->created_at->format('d M Y H:i:s') }}</p></div>@empty<div class="empty">No admin events yet.</div>@endforelse</section>
<aside class="stack"><section class="card pad"><h2 class="fx-layout-8">Recorded payments</h2>@forelse($events as $event)<div class="item"><strong>{{ $event->type }} · #{{ $event->booking->number }}</strong><p>{{ \App\Support\Portal::money($event->amount_minor/100) }} · {{ $event->status }}</p><p>{{ $event->reference }}</p></div>@empty<div class="empty">No provider events.</div>@endforelse</section><div class="notice">Audit entries persist with each business transition. Payment records reflect stored provider information; demo records do not represent money movement.</div></aside></div>

@endsection
