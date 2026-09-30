<section class="card pad"><div class="sectionhead"><h2>{{ $title }}</h2><span class="muted small">{{ $items->where('active',true)->count() }} active</span></div>
@foreach($items as $item)<div class="item row between"><div><strong>{{ $item->name }}</strong>@if($kind === 'categories')<p>{{ $item->active ? 'Shown in discovery' : 'Hidden from new requests' }}</p>@endif</div>
<x-form :action="route('admin.catalog.toggle',[$kind,$item->id])"><input type="hidden" name="active" value="{{ $item->active ? 0 : 1 }}"><button class="btn sm">{{ $item->active ? 'Disable' : 'Enable' }}</button></x-form></div>@endforeach
<x-form :action="route('admin.catalog.store',$kind)" class="fx-layout-1 row"><input class="field" name="name" aria-label="{{ $placeholder }}" placeholder="{{ $placeholder }}" required maxlength="50"><button class="btn primary">Add</button></x-form>
</section>
