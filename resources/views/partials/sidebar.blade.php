<aside class="side" id="side">
    <div class="brand"><b>F</b>fixifier <small>PLUS</small></div>
    <div><div class="sidehead">Platform administration</div><nav class="nav" id="nav" aria-label="Admin navigation">
        @foreach(config('fixifier.navigation') as $id => [$icon, $name])
            <a href="{{ route('admin.'.$id) }}" class="{{ $id === $page ? 'active' : '' }}" @if($id === $page) aria-current="page" @endif><span class="icon" aria-hidden="true">{{ $icon }}</span>{{ $name }}</a>
        @endforeach
    </nav></div>
    <div class="sidefoot">Marketplace operations portal<br>Database-backed records · No live money movement</div>
</aside>
