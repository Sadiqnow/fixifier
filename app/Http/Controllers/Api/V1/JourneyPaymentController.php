<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Booking, Payment};
use App\Services\{Journey, JourneyPayments, PaystackGateway};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Crypt, DB};

class JourneyPaymentController extends Controller
{
    public function initialize(Request $r, Booking $b, JourneyPayments $payments)
    {
        Journey::participant($b, $r->user(), 'customer');
        return response()->json(['data' => $payments->initialize($b)]);
    }

    public function reconcile(Request $r, Booking $b, JourneyPayments $payments)
    {
        Journey::participant($b, $r->user());
        return response()->json(['data' => $payments->reconcile($b->payment()->firstOrFail())]);
    }

    public function settle(Request $r, Booking $b, JourneyPayments $payments)
    {
        Journey::participant($b, $r->user(), 'admin');
        return response()->json(['data' => $payments->settle($b)]);
    }

    public function settlement(Request $r, Booking $b, JourneyPayments $payments)
    {
        Journey::participant($b, $r->user());
        return response()->json(['data' => $payments->reconcileSettlement($b->payment()->firstOrFail())]);
    }

    public function webhook(Request $r, PaystackGateway $gateway, JourneyPayments $payments)
    {
        $gateway->ready();
        abort_unless(hash_equals(hash_hmac('sha512', $r->getContent(), config('journey.paystack_secret')), (string) $r->header('x-paystack-signature')), 401);
        if ($r->input('event') === 'charge.success') {
            $p = Payment::where('provider', 'paystack')->where('provider_reference', $r->input('data.reference'))->firstOrFail();
            $payments->reconcile($p);
        } elseif (str_starts_with((string) $r->input('event'), 'transfer.')) {
            $p = Payment::where('provider', 'paystack')->where('settlement_reference', $r->input('data.reference'))->firstOrFail();
            $payments->reconcileSettlement($p);
        } elseif (str_starts_with((string) $r->input('event'), 'refund.')) {
            $op = DB::table('payment_operations')->where('kind', 'refund')->where('provider_id', (string) $r->input('data.id'))->first();
            abort_unless($op, 404);
            $payments->reconcileSettlement(Payment::findOrFail($op->payment_id));
        }
        return response()->json(['received' => true]);
    }

    public function destination(Request $r, PaystackGateway $gateway)
    {
        abort_unless($r->user()->role->value === 'technician', 403);
        $data = $r->validate(['bank_code' => 'required|string|regex:/^[0-9]{3,10}$/', 'account_number' => 'required|string|regex:/^[0-9]{10}$/']);
        $account = $gateway->call('GET', 'bank/resolve', $data);
        abort_unless(($account['account_number'] ?? null) === $data['account_number'], 422, 'Account verification failed.');
        $recipient = $gateway->call('POST', 'transferrecipient', $data + ['type' => 'nuban', 'currency' => 'NGN', 'name' => $account['account_name']]);
        abort_unless($recipient['active'] ?? false, 422, 'Provider recipient is inactive.');
        DB::table('payout_destinations')->updateOrInsert(['user_id' => $r->user()->id], ['recipient_code' => Crypt::encryptString($recipient['recipient_code']),
            'account_name' => $account['account_name'], 'last_four' => substr($data['account_number'], -4), 'verified_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        return response()->json(['message' => 'Test payout destination verified.', 'account_name' => $account['account_name']]);
    }

    public function earnings(Request $r)
    {
        abort_unless($r->user()->role->value === 'technician', 403);
        $payments = Payment::whereHas('booking', fn ($q) => $q->where('technician_id', $r->user()->id))->get();
        $ids = $payments->modelKeys();
        return response()->json(['data' => $payments, 'pending_minor' => $payments->whereIn('status', ['authorized', 'release_pending'])->sum('technician_net_minor'),
            'paid_minor' => $payments->where('status', 'released')->sum('technician_net_minor'),
            'available_minor' => $payments->where('status', 'release_pending')->sum('technician_net_minor'),
            'operations' => DB::table('payment_operations')->whereIn('payment_id', $ids)->get(['id', 'payment_id', 'kind', 'reference', 'status', 'amount_minor', 'created_at']),
            'destination' => DB::table('payout_destinations')->where('user_id', $r->user()->id)->first(['account_name', 'last_four', 'verified_at'])]);
    }
}
