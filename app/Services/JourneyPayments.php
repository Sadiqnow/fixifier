<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\{Booking, MarketplaceSetting, Payment, Quotation};
use Illuminate\Support\Facades\{Crypt, DB};
use Illuminate\Support\Str;

class JourneyPayments
{
    public function __construct(private PaystackGateway $gateway) {}

    public function requireFunding(Booking $b): void
    {
        $p = $b->payment()->first();
        abort_unless($p && $p->authorized_at && $p->status === 'authorized', 422, 'Verified funding is required before starting work.');

        $q = $b->accepted_quotation_id ? Quotation::find($b->accepted_quotation_id) : null;
        if ($q) {
            abort_unless($q->accepted_at && $p->quotation_id === $q->id
                && $p->amount_minor === $q->amount_minor && $p->currency === $q->currency,
                422, 'Verified funding for the accepted quotation is required before starting work.');
        }
    }

    public function initialize(Booking $bound): array
    {
        $this->gateway->ready();
        [$p, $op, $fresh] = DB::transaction(function () use ($bound) {
            $b = Booking::whereKey($bound->id)->lockForUpdate()->firstOrFail();
            abort_unless($b->status === BookingStatus::Confirmed && $b->accepted_quotation_id, 409, 'Accept a quotation first.');
            $q = Quotation::findOrFail($b->accepted_quotation_id);
            abort_unless($q->accepted_at, 409);
            $settings = MarketplaceSetting::findOrFail(1);
            abort_unless(config('journey.fees_approved'), 503, 'Administrator must approve the existing finance rules before collecting payments.');
            $fees = app(EarningsCalculator::class)->calculate($q->amount_minor, $settings->values);
            abort_unless($fees['valid'], 422, 'Fee deductions exceed this quote.');
            $p = Payment::firstOrCreate(['booking_id' => $b->id], ['provider' => 'paystack', 'provider_reference' => 'FX-'.Str::uuid(),
                'quotation_id' => $q->id, 'amount_minor' => $q->amount_minor, 'currency' => $q->currency, 'status' => 'pending',
                'platform_fee_minor' => $fees['platform'], 'technician_net_minor' => $fees['net'],
                'fee_snapshot' => ['version' => $settings->version, 'rules' => $settings->values, 'calculation' => $fees]]);
            abort_unless($p->provider === 'paystack' && $p->quotation_id === $q->id, 409, 'Existing payment requires reconciliation.');
            $op = DB::table('payment_operations')->where('payment_id', $p->id)->where('kind', 'funding')->first();
            $fresh = !$op;
            if (!$op) {
                DB::table('payment_operations')->insert(['payment_id' => $p->id, 'kind' => 'funding', 'reference' => $p->provider_reference,
                    'amount_minor' => $p->amount_minor, 'created_at' => now(), 'updated_at' => now()]);
                $op = DB::table('payment_operations')->where('payment_id', $p->id)->where('kind', 'funding')->first();
            }
            return [$p, $op, $fresh];
        });
        if ($fresh) {
            // Durable operation precedes provider call. Unknown outcomes are never automatically repeated.
            $data = $this->gateway->call('POST', 'transaction/initialize', ['email' => $bound->customer->email, 'amount' => $p->amount_minor,
                'currency' => $p->currency, 'reference' => $p->provider_reference, 'metadata' => ['booking_id' => $p->booking_id, 'quotation_id' => $p->quotation_id]]);
            abort_unless(($data['reference'] ?? null) === $p->provider_reference && str_starts_with($data['authorization_url'] ?? '', 'https://checkout.paystack.com/'), 502);
            DB::table('payment_operations')->where('id', $op->id)->update(['checkout_url' => $data['authorization_url'], 'updated_at' => now()]);
            $op->checkout_url = $data['authorization_url'];
        }
        return ['payment' => $p->fresh(), 'checkout_url' => $op->checkout_url,
            'message' => $op->checkout_url ? 'Test checkout ready.' : 'Initialization outcome unknown. Reconcile this reference; no duplicate attempt was sent.'];
    }

    public function reconcile(Payment $p): Payment
    {
        abort_unless($p->provider === 'paystack', 409, 'Legacy payment requires administrator reconciliation.');
        $data = $this->gateway->call('GET', 'transaction/verify/'.rawurlencode($p->provider_reference));
        return DB::transaction(function () use ($p, $data) {
            $b = Booking::whereKey($p->booking_id)->lockForUpdate()->firstOrFail();
            $p = Payment::whereKey($p->id)->lockForUpdate()->firstOrFail();
            abort_unless(($data['reference'] ?? null) === $p->provider_reference && (int) ($data['amount'] ?? -1) === $p->amount_minor
                && ($data['currency'] ?? null) === $p->currency && ($data['domain'] ?? null) === 'test'
                && $b->accepted_quotation_id === $p->quotation_id, 422, 'Provider payment does not match the accepted quote.');
            if (($data['status'] ?? null) === 'success' && in_array($p->status, ['pending', 'failed'])) {
                abort_unless($b->status === BookingStatus::Confirmed, 409, 'Unexpected funding requires reconciliation.');
                $p->update(['status' => 'authorized', 'authorized_at' => now()]);
                DB::table('payment_events')->insertOrIgnore(['payment_id' => $p->id, 'provider_event_id' => 'charge-'.$p->provider_reference,
                    'type' => 'funding', 'payload' => json_encode(['reference' => $p->provider_reference, 'amount' => $p->amount_minor]), 'created_at' => now()]);
                Journey::notify($b, 'payment.funded', 'Provider verified test funding. Funds remain subject to settlement review.');
            } elseif (in_array($data['status'] ?? '', ['failed', 'abandoned']) && $p->status === 'pending') $p->update(['status' => 'failed']);
            return $p;
        });
    }

    public function settle(Booking $bound): array
    {
        $this->gateway->ready();
        [$p, $op, $fresh] = DB::transaction(function () use ($bound) {
            $b = Booking::whereKey($bound->id)->lockForUpdate()->firstOrFail();
            abort_if($b->status === BookingStatus::Disputed || $b->disputes()->where('status', 'open')->exists(), 409, 'Settlement is held for dispute review.');
            abort_unless(in_array($b->settlement_status, ['release_pending', 'refund_pending']), 409, 'No settlement is approved.');
            $p = $b->payment()->lockForUpdate()->firstOrFail();
            abort_unless($p->provider === 'paystack' && $p->authorized_at && $p->quotation_id === $b->accepted_quotation_id && $p->fee_snapshot, 409, 'Verified, snapshotted funding is required.');
            $kind = $b->settlement_status === 'release_pending' ? 'release' : 'refund';
            abort_unless($b->status === ($kind === 'release' ? BookingStatus::Completed : BookingStatus::Cancelled), 409);
            $destination = null;
            if ($kind === 'release') {
                $d = DB::table('payout_destinations')->where('user_id', $b->technician_id)->first();
                abort_unless($d && $d->verified_at, 422, 'Technician must verify a payout destination first.');
                $destination = $d->recipient_code;
                abort_unless($p->technician_net_minor > 0, 422, 'No positive net amount is available.');
            }
            $op = DB::table('payment_operations')->where('payment_id', $p->id)->where('kind', $kind)->first();
            $fresh = !$op;
            if (!$op) {
                abort_if(DB::table('payment_operations')->where('payment_id', $p->id)->whereIn('kind', ['release', 'refund'])->exists(), 409, 'A conflicting settlement already exists.');
                $id = DB::table('payment_operations')->insertGetId(['payment_id' => $p->id, 'kind' => $kind, 'reference' => 'FXS-'.Str::uuid(),
                    'amount_minor' => $kind === 'release' ? $p->technician_net_minor : $p->amount_minor, 'destination' => $destination,
                    'created_at' => now(), 'updated_at' => now()]);
                $op = DB::table('payment_operations')->find($id);
                $p->update(['settlement_reference' => $op->reference]);
            }
            return [$p, $op, $fresh];
        });
        if ($fresh) {
            $data = $op->kind === 'release'
                ? $this->gateway->call('POST', 'transfer', ['source' => 'balance', 'amount' => $op->amount_minor, 'currency' => $p->currency,
                    'reference' => $op->reference, 'recipient' => Crypt::decryptString($op->destination)])
                : $this->gateway->call('POST', 'refund', ['transaction' => $p->provider_reference, 'amount' => $op->amount_minor, 'currency' => $p->currency]);
            DB::table('payment_operations')->where('id', $op->id)->update(['provider_id' => (string) ($data['id'] ?? ''), 'updated_at' => now()]);
        }
        return ['message' => 'Settlement requested; reconcile to verify its outcome.', 'reference' => $op->reference];
    }

    public function reconcileSettlement(Payment $p): Payment
    {
        $op = DB::table('payment_operations')->where('payment_id', $p->id)->whereIn('kind', ['release', 'refund'])->first();
        abort_unless($op, 409, 'No settlement request exists.');
        abort_if($op->kind === 'refund' && !$op->provider_id, 409, 'Refund outcome unknown. Operator must recover its provider ID; automatic retry is disabled.');
        $data = $this->gateway->call('GET', $op->kind === 'release' ? 'transfer/verify/'.rawurlencode($op->reference) : 'refund/'.rawurlencode($op->provider_id));
        return DB::transaction(function () use ($p, $op, $data) {
            $b = Booking::whereKey($p->booking_id)->lockForUpdate()->firstOrFail();
            $p = Payment::whereKey($p->id)->lockForUpdate()->firstOrFail();
            abort_if($b->status === BookingStatus::Disputed || $b->disputes()->where('status', 'open')->exists(), 409);
            abort_unless((int) ($data['amount'] ?? -1) === (int) $op->amount_minor && ($data['currency'] ?? null) === $p->currency, 422);
            if ($op->kind === 'release') {
                abort_unless(($data['reference'] ?? '') === $op->reference && ($data['recipient']['recipient_code'] ?? '') === Crypt::decryptString($op->destination), 422);
            } else abort_unless(($data['transaction']['reference'] ?? '') === $p->provider_reference, 422);
            $success = ($data['status'] ?? '') === ($op->kind === 'release' ? 'success' : 'processed');
            if ($success && !in_array($p->status, ['released', 'refunded'])) {
                $state = $op->kind === 'release' ? 'released' : 'refunded';
                $p->update(['status' => $state, $op->kind === 'release' ? 'released_at' : 'refunded_at' => now(), 'refund_minor' => $op->kind === 'refund' ? $p->amount_minor : 0]);
                $b->update(['settlement_status' => $state]);
                DB::table('payment_operations')->where('id', $op->id)->update(['status' => 'success', 'updated_at' => now()]);
                DB::table('payment_events')->insertOrIgnore(['payment_id' => $p->id, 'provider_event_id' => $op->reference, 'type' => $state, 'payload' => json_encode(['amount' => $op->amount_minor]), 'created_at' => now()]);
                Journey::notify($b, 'settlement.'.$state, 'Provider verified test '.$state.'.');
            } elseif (!$success && in_array($data['status'] ?? '', ['failed', 'reversed']) && !in_array($p->status, ['released', 'refunded'])) {
                DB::table('payment_operations')->where('id', $op->id)->update(['status' => 'failed', 'updated_at' => now()]);
                Journey::notify($b, 'settlement.failed', 'Settlement failed; administrator reconciliation is required.');
            }
            return $p;
        });
    }
}
