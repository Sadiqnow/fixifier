@extends('layouts.admin')
@section('content')
<x-head eyebrow="Step 05 · Quality control" title="Work evidence" subtitle="Review before and after records by booking and work round."></x-head>

<div class="grid split"><section class="card pad"><div class="sectionhead"><h2>Evidence queue</h2><span class="muted small">Authorized admin access</span></div>
@forelse($bookings->filter(fn($j)=>$j->rounds->flatMap->evidence->isNotEmpty() || $j->status === 'rework_required') as $j)
@php($round=$j->currentRound())
<div class="item row between"><div><strong>#{{ $j->number }} · {{ $j->title }}</strong><p>Round {{ $j->current_round }} · Before {{ $round?->evidence->where('phase','before')->count() ?? 0 }} · After {{ $round?->evidence->where('phase','after')->count() ?? 0 }}</p></div><a class="btn sm" href="{{ route('admin.evidence',['booking'=>$j->number]) }}">Inspect</a></div>
@empty<div class="empty">No evidence records yet.</div>@endforelse
</section><aside class="stack">@if($current)
@foreach($current->rounds->sortByDesc('number') as $round)<section class="card pad"><h2>Job #{{ $current->number }} · Round {{ $round->number }}</h2>
@foreach(['before'=>'Before','after'=>'After'] as $phase=>$label)<div class="detail"><span>{{ $label }}</span><strong>@forelse($round->evidence->where('phase',$phase) as $file)@if($file->path && !$file->is_sample)<a href="{{ route('admin.private.evidence',$file->id) }}">{{ $file->filename }}</a>@else{{ $file->filename }}@endif{{ !$loop->last ? ', ' : '' }}@empty Missing @endforelse</strong></div>@endforeach
@if($round->number === $current->current_round)<div class="detail"><span>Stage</span><strong>{{ \App\Support\Portal::label($current->status) }}</strong></div><div class="actions"><button class="btn sm" data-dialog="flag-{{ $current->number }}">Flag for review</button></div>@endif
@foreach($round->flags as $flag)<div class="item"><strong>Review note</strong><p>{{ $flag->reason }}</p></div>@endforeach
</section>@endforeach
<x-modal :id="'flag-'.$current->number" :title="'Flag evidence · job #'.$current->number"><x-form :action="route('admin.evidence.flag',$current->id)" :version="$current->version"><x-reason label="Review note" placeholder="Describe the issue with the evidence"/><div class="actions"><button class="btn primary">Record flag</button></div></x-form></x-modal>

@endif<div class="notice">Evidence files are private and grouped by work round. Earlier rounds and review notes are retained.</div></aside></div>

@endsection
