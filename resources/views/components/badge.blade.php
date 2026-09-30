@props(['status'])
<span class="badge {{ \App\Support\Portal::color($status) }}">{{ \App\Support\Portal::label($status) }}</span>
