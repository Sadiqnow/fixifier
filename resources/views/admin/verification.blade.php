@extends('layouts.admin')
@section('content')
<x-head eyebrow="Step 02 · Provider eligibility" title="Technician verification" subtitle="Review documents, approve or request changes, and control new-job eligibility."></x-head>

<div class="grid split"><section class="card pad"><div class="sectionhead"><h2>Verification queue</h2><span class="badge orange">{{ $technicians->where('kyc','pending_review')->count() }} pending</span></div>
@forelse($technicians as $t)<div class="item"><div class="row between"><div><strong>{{ $t->name }}</strong><p>{{ $t->category->name }} · {{ $t->area->name }}</p></div><x-badge :status="$t->kyc"/></div><div class="actions"><button class="btn sm" data-dialog="review-{{ $t->id }}">Review profile</button>@if($t->kyc === 'approved')<button class="btn sm danger" data-dialog="suspend-{{ $t->id }}">Suspend</button>@endif</div></div>
<x-modal :id="'review-'.$t->id" :title="'Verification · '.$t->name">
<div class="detail"><span>Trade</span><strong>{{ $t->category->name }}</strong></div><div class="detail"><span>Service area</span><strong>{{ $t->area->name }}</strong></div><div class="detail"><span>Documents</span><strong>@foreach($t->documents as $document)@if($document->path && !$document->is_sample)<a href="{{ route('admin.private.document',$document->id) }}">{{ $document->label }}</a>@else{{ $document->label }}@endif{{ !$loop->last ? ', ' : '' }}@endforeach</strong></div><div class="detail"><span>Current status</span><strong>{{ \App\Support\Portal::label($t->kyc) }}</strong></div>
<div class="fx-layout-9 notice">Inspect the protected documents before approving. Each decision is saved with your reason and reviewer identity.</div>
<x-form :action="route('admin.technicians.decide',$t->id)" :version="$t->version"><x-select name="decision" label="Decision" value="approved" :options="['approved'=>'Approve','changes_requested'=>'Request changes','rejected'=>'Reject','suspended'=>'Suspend']"/><x-reason/><div class="actions"><button class="btn primary">Record decision</button></div></x-form>
@if($t->decisions->isNotEmpty())<div class="divider"></div><h3>Decision history</h3>@foreach($t->decisions as $decision)<div class="item"><strong>{{ \App\Support\Portal::label($decision->to_status) }}</strong><p>{{ $decision->reason }} · {{ $decision->created_at->format('d M Y H:i') }}</p></div>@endforeach
@endif
</x-modal>
<x-modal :id="'suspend-'.$t->id" :title="'Suspend '.$t->name"><x-form :action="route('admin.technicians.decide',$t->id)" :version="$t->version"><input type="hidden" name="decision" value="suspended"><x-reason label="Reason for suspension"/><div class="actions"><button class="btn primary">Suspend new assignments</button></div></x-form></x-modal>
@empty<div class="empty">No technicians yet.</div>@endforelse
</section><aside class="stack"><div class="card pad"><h2>Eligibility rule</h2><div class="detail"><span>Verified</span><strong>{{ $technicians->where('kyc','approved')->count() }}</strong></div><div class="detail"><span>Available</span><strong>{{ $technicians->where('kyc','approved')->where('available',true)->count() }}</strong></div><div class="notice blue">A technician must be approved, active, available, in an active area and eligible for the job category before appearing in matching or taking new work.</div></div><div class="notice">Approvals require a document record. Profile editing and identity document submission remain outside this admin integration.</div></aside></div>

@endsection
