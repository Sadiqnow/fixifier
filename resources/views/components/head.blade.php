@props(['eyebrow','title','subtitle'])
<div class="head"><div><div class="eyebrow">{{ $eyebrow }}</div><h1>{{ $title }}</h1><p>{{ $subtitle }}</p></div>{{ $slot }}</div>
