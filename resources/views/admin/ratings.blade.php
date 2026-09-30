@extends('layouts.admin')
@section('content')
<x-head eyebrow="Step 07 · Marketplace trust" title="Technician ratings and ranking" subtitle="Control which reviews count and how verified professionals are ordered."></x-head>

<div class="grid split"><div class="stack"><section class="card pad"><h2 class="fx-layout-8">Verified job reviews</h2>
@forelse($reviews as $r)<div class="item"><div class="row between"><strong>Job #{{ $r->booking_id }} · {{ $r->technician->name }}</strong><x-badge :status="$r->status"/></div><div class="rating">{{ str_repeat('★',$r->stars) }}{{ str_repeat('☆',5-$r->stars) }}</div><p>{{ $r->comment }}</p><div class="actions"><button class="btn sm" data-dialog="moderate-{{ $r->id }}">{{ $r->status === 'published' ? 'Hide review' : 'Publish review' }}</button></div></div>
<x-modal :id="'moderate-'.$r->id" :title="'Moderate review · job #'.$r->booking_id"><p class="muted small">{{ $r->comment }}</p><x-form :action="route('admin.reviews.moderate',$r->id)" :version="$r->version"><x-reason placeholder="Explain publication or moderation"/><div class="actions"><button class="btn primary">{{ $r->status === 'published' ? 'Hide' : 'Publish' }} review</button></div></x-form></x-modal>
@empty<div class="empty">No ratings yet.</div>@endforelse


</section><div class="notice blue"><strong>Rating rule</strong><br>Only a customer who owns a completed booking can submit one 1–5 star rating for that booking. Reject duplicate or unrelated reviews. Hide abusive content with a recorded reason; preserve the original record.</div></div>
<div class="stack"><section class="card pad"><h2 class="fx-layout-8">Ranking weights</h2><x-form :action="route('admin.settings.update','ranking')" :version="$settings->version"><div class="formgrid">
@foreach(['ratingWeight'=>'Rating %','distanceWeight'=>'Same area %','availabilityWeight'=>'Availability %','completionWeight'=>'Completed jobs %'] as $key=>$label)<x-input :name="$key" :label="$label" type="number" min="0" max="100" :value="$s[$key]"/>@endforeach
</div><p class="muted small">Weights must total 100%. New providers receive a neutral 50/100 rating score until rated.</p><div class="actions"><button class="btn primary">Save ranking weights</button></div></x-form></section>
<section class="card pad"><h2 class="fx-layout-8">Example eligible order</h2>
@php($example=$bookings->first())
@forelse($example ? $ranking->eligible($example,$s) : collect() as $t)<div class="detail"><span>{{ $t->name }} · ★ {{ $t->rating ?: 'New' }}</span><strong>{{ $ranking->score($t,$example->service_area_id,$s) }}/100</strong></div>@empty<div class="empty">No eligible professionals.</div>@endforelse
</section></div></div>

@endsection
