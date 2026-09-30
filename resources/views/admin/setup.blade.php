@extends('layouts.admin')
@section('content')
<x-head eyebrow="Step 01 · Platform configuration" title="Marketplace setup" subtitle="Define services, operating areas, discovery and request behavior."></x-head>

<div class="grid split"><div class="stack">
@include('partials.catalog',['items'=>$categories,'kind'=>'categories','title'=>'Services','placeholder'=>'Add service category'])
@include('partials.catalog',['items'=>$areas,'kind'=>'areas','title'=>'Service areas','placeholder'=>'Add city or area'])
</div><div class="stack"><section class="card pad"><h2 class="fx-layout-3">Operational rules</h2>
<x-form :action="route('admin.settings.update','setup')" :version="$settings->version"><div class="formgrid">
<x-input name="name" label="Marketplace name" :value="$s['name']" maxlength="100"/>
<x-input name="city" label="Primary city" :value="$s['city']" maxlength="100"/>
<x-select name="routing" label="Request routing" :value="$s['routing']" :options="['customer_choice'=>'Customer chooses technician','admin_assignment'=>'Admin assigns technician','ranked_suggestion'=>'Ranked suggestion; admin confirms']"/>
<x-input name="requestTimeout" label="Response window (hours)" type="number" min="1" max="168" :value="$s['requestTimeout']"/>
<x-input name="reviewHours" label="Customer review (hours)" type="number" min="1" max="720" :value="$s['reviewHours']"/>
<x-select name="visibility" label="Provider discovery" value="approved" :options="['approved'=>'Approved, active and available only']"/>
<x-auto-approve :value="$s['autoApprove']"/>
</div><div class="actions"><button class="btn primary">Save marketplace rules</button></div></x-form></section>
<div class="notice blue">Routing preferences and automatic approval remain policy drafts. Assignment eligibility is enforced on the server. Customer dispatch, schedulers and notifications are not activated by saving this form.</div></div></div>

@endsection
