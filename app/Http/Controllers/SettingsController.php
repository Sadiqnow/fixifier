<?php

namespace App\Http\Controllers;

use App\Http\Requests\SettingsRequest;
use App\Models\MarketplaceSetting;
use App\Models\Quotation;
use App\Services\AuditService;
use App\Services\EarningsCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    public function update(SettingsRequest $request, string $section, AuditService $audit, EarningsCalculator $calculator)
    {
        $data = $request->validated();
        DB::transaction(function () use ($data, $section, $audit, $calculator) {
            $record = MarketplaceSetting::whereKey(1)->lockForUpdate()->firstOrFail();
            abort_unless($record->version === (int) $data['version'], 409, 'Settings changed. Refresh before saving.');
            $values = $data;
            unset($values['version']);
            foreach (['autoApprove'] as $key) {
                if (isset($values[$key])) {
                    $values[$key] = (bool) $values[$key];
                }
            }
            $next = array_replace($record->values, $values);
            if ($section === 'ranking' && array_sum(array_intersect_key($next, array_flip(['ratingWeight', 'distanceWeight', 'availabilityWeight', 'completionWeight']))) !== 100) {
                // Form numbers arrive as strings: numeric addition remains authoritative.
                if ((float) ($next['ratingWeight'] + $next['distanceWeight'] + $next['availabilityWeight'] + $next['completionWeight']) !== 100.0) {
                    throw ValidationException::withMessages(['ratingWeight' => 'Weights must total 100%.']);
                }
            }
            if ($section === 'finance') {
                foreach (Quotation::where('amount_minor', '>', 0)->cursor() as $j) {
                    if (! $calculator->calculate($j->amount_minor, $next)['valid']) {
                        throw ValidationException::withMessages(['feePercent' => 'Deductions exceed at least one job amount. Rules were not saved.']);
                    }
                }
            }
            $previous = $record->values;
            $record->values = $next;
            $record->version++;
            $record->save();
            $audit->record('Updated '.$section.' rules', 'Marketplace', ['previous' => $previous, 'current' => $next, 'version' => $record->version]);
        }, 3);

        return back()->with('status','Rules saved.');
    }
}
