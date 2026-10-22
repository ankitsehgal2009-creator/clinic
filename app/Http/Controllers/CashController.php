<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\CashClosing;
use App\Models\CashPayout;
use App\Models\Payment;
use App\Support\Fmt;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Daily cash closing: what came in by each mode, what left the drawer, and the counted cash. */
class CashController extends Controller
{
    public function show(Request $r)
    {
        $me = $this->me();
        $date = Fmt::isDate($r->query('date')) && $r->query('date') <= Fmt::today() ? $r->query('date') : Fmt::today();
        $closing = CashClosing::with('closer:id,name')->where(['clinic_id' => $me->clinic_id, 'date' => $date])->first();
        // Every bill still owed money, across all days, so reception can chase it while closing
        $pending = \App\Models\Invoice::with('patient:id,name,code,mobile')
            ->where('clinic_id', $me->clinic_id)->whereNull('cancelled_at')->whereColumn('paid', '<', 'total')
            ->orderBy('date')->orderBy('id')->limit(300)->get();
        return view('cash', ['date' => $date, 'closing' => $closing, 'pending' => $pending,
            'pendingTotal' => (int) \App\Models\Invoice::where('clinic_id', $me->clinic_id)->whereNull('cancelled_at')->whereColumn('paid', '<', 'total')->sum(\Illuminate\Support\Facades\DB::raw('total - paid')),
        ] + $this->day($me->clinic_id, $date));
    }

    /** Everything the closing needs for one day, worked out from payments and payouts. */
    private function day(int $clinicId, string $date): array
    {
        $payments = Payment::with('patient:id,name,code', 'invoice:id,number', 'receiver:id,name')
            ->where(['clinic_id' => $clinicId, 'date' => $date])->orderBy('id')->get();
        $byMode = [];
        foreach (Money::MODES as $k => $l) {
            $byMode[$k] = ['label' => $l, 'sum' => (int) $payments->where('mode', $k)->sum('amount'), 'n' => $payments->where('mode', $k)->count()];
        }
        $payouts = CashPayout::with('user:id,name')->where(['clinic_id' => $clinicId, 'date' => $date])->orderBy('id')->get();
        $prev = CashClosing::where('clinic_id', $clinicId)->where('date', '<', $date)->orderByDesc('date')->first();
        $opening = (int) ($prev?->kept ?? 0);
        $cashIn = $byMode['CASH']['sum'];
        $paidOut = (int) $payouts->sum('amount');
        return [
            'payments' => $payments, 'byMode' => $byMode, 'payouts' => $payouts, 'prev' => $prev,
            'opening' => $opening, 'cashIn' => $cashIn, 'paidOut' => $paidOut,
            'expected' => $opening + $cashIn - $paidOut,
            'collected' => (int) $payments->sum('amount'),
        ];
    }

    public function payout(Request $r)
    {
        $me = $this->me();
        $v = $r->validate(['description' => 'required|string|min:2|max:150', 'amount' => 'required'], ['description.required' => 'Write what the money was for.']);
        $amount = Money::parse($v['amount']);
        $today = Fmt::today();
        if (! $amount) {
            return back()->withInput()->with('error', 'Enter the amount, like 350.');
        }
        if (CashClosing::where(['clinic_id' => $me->clinic_id, 'date' => $today])->exists()) {
            return back()->with('error', 'Today is already closed. Ask the admin to reopen it first.');
        }
        CashPayout::create(['clinic_id' => $me->clinic_id, 'date' => $today, 'description' => $v['description'], 'amount' => $amount, 'user_id' => $me->id]);
        Activity::log($me, 'Paid out cash', $v['description'].' · '.Money::inr($amount));
        return back()->with('ok', 'Paid out '.Money::inr($amount).'.');
    }

    public function removePayout(CashPayout $payout)
    {
        $this->own($payout);
        abort_unless($this->me()->isAdmin(), 403, 'Only the admin can delete permanently.');
        $payout->delete();
        Activity::log($this->me(), 'Deleted cash payout', $payout->description.' · '.Money::inr($payout->amount));
        return back()->with('ok', 'Payout removed.');
    }

    public function close(Request $r)
    {
        $me = $this->me();
        $v = $r->validate([
            'date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'notes' => 'nullable|array',
            'notes.*' => 'nullable|integer|min:0|max:100000',
            'coins' => 'nullable|integer|min:0|max:1000000',
            'kept' => 'nullable',
            'reason' => 'nullable|string|max:250',
        ]);
        $notes = [];
        $counted = 0;
        foreach (Money::NOTES as $n) {
            $notes[$n] = (int) ($v['notes'][$n] ?? 0);
            $counted += $n * $notes[$n] * 100;
        }
        $notes['coins'] = (int) ($v['coins'] ?? 0);
        $counted += $notes['coins'] * 100;

        return DB::transaction(function () use ($me, $v, $notes, $counted) {
            if (CashClosing::where(['clinic_id' => $me->clinic_id, 'date' => $v['date']])->lockForUpdate()->exists()) {
                return back()->with('error', 'This day is already closed.');
            }
            $d = $this->day($me->clinic_id, $v['date']);
            $diff = $counted - $d['expected'];
            if ($diff !== 0 && blank($v['reason'] ?? null)) {
                return back()->withInput()->with('error', 'The counted cash is '.($diff > 0 ? 'more' : 'less').' than expected by '.Money::inr(abs($diff)).'. Write the reason before closing.');
            }
            $kept = ($v['kept'] ?? '') === '' ? $counted : Money::parse($v['kept']);
            if ($kept === null || $kept > $counted) {
                return back()->withInput()->with('error', 'Cash left in the drawer cannot be more than the cash counted.');
            }
            CashClosing::create([
                'clinic_id' => $me->clinic_id, 'date' => $v['date'], 'opening' => $d['opening'], 'cash_in' => $d['cashIn'], 'paid_out' => $d['paidOut'],
                'expected' => $d['expected'], 'counted' => $counted, 'kept' => $kept, 'notes_count' => $notes, 'reason' => $diff ? $v['reason'] : null,
                'upi' => $d['byMode']['UPI']['sum'], 'card' => $d['byMode']['CARD']['sum'],
                'other' => $d['byMode']['BANK']['sum'] + $d['byMode']['CHEQUE']['sum'], 'closed_by' => $me->id,
            ]);
            Activity::log($me, 'Closed the day', Fmt::date($v['date']).' · counted '.Money::inr($counted).($diff ? ' · '.($diff > 0 ? 'extra ' : 'short ').Money::inr(abs($diff)) : ' · matched'));
            return redirect()->route('cash.show', ['date' => $v['date']])->with('ok', 'Day closed.');
        });
    }

    public function reopen(Request $r)
    {
        $me = $this->me();
        abort_unless($me->isAdmin(), 403, 'Only the admin can reopen a closed day.');
        $date = (string) $r->input('date');
        $c = CashClosing::where(['clinic_id' => $me->clinic_id, 'date' => $date])->firstOrFail();
        if (CashClosing::where('clinic_id', $me->clinic_id)->where('date', '>', $date)->exists()) {
            return back()->with('error', 'A later day is already closed. Reopen the latest day first.');
        }
        $c->delete();
        Activity::log($me, 'Reopened the day', Fmt::date($date));
        return back()->with('ok', 'Day reopened.');
    }
}
