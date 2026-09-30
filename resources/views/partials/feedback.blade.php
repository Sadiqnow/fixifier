@if(session('status'))<div class="notice blue feedback" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="notice feedback" role="alert"><strong>Please check your entry.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
