<link rel="stylesheet" href="{{ asset('css/journey.css') }}">
<div class="journey-tools"><button type="button" class="btn" data-journey-tool="notifications">Notifications</button></div>
<dialog id="journey-dialog" aria-labelledby="journey-title"><header><h2 id="journey-title">Service journey</h2><button type="button" data-journey-close aria-label="Close journey">Close</button></header><div id="journey-body"></div><p id="journey-error" role="alert"></p><p id="journey-status" role="status"></p></dialog>
<script src="{{ asset('js/journey.js') }}" data-journey-base="{{ $journeyBase ?? (isset($page) ? url('/admin/journey-api') : url('/api/v1')) }}" defer></script>
