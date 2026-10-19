<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Clinic;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\TreatmentPrice;
use App\Support\Fmt;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillingController extends Controller
{
    public const SHOW = ['today' => 'Today', 'month' => 'This month', 'due' => 'Unpaid', 'all' => 'All'];

    public function index(Request $r)
    {
        $me = $this->me();
        $today = Fmt::today();
        $show = array_key_exists($r->query('show'), self::SHOW) ? $r->query('show') : 'today';
        $term = trim((string) $r->query('q', ''));
        $q = Invoice::with('patient:id,name,code')->where('clinic_id', $me->clinic_id);
        match ($show) {
            'today' => $q->where('date', $today),
            'month' => $q->where('date', 'like', substr($today, 0, 7).'-%'),
            'due' => $q->whereNull('cancelled_at')->whereColumn('paid', '<', 'total'),
            default => null,
        };
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_').'%';
            $q->where(fn ($w) => $w->where('number', 'like', $like)
                ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('mobile', 'like', $like)));
        }
        $live = Invoice::where('clinic_id', $me->clinic_id)->whereNull('cancelled_at');
        return view('billing.index', [
            'rows' => $q->orderByDesc('date')->orderByDesc('id')->limit(200)->get(),
            'show' => $show, 'term' => $term,
            'billedToday' => (int) (clone $live)->where('date', $today)->sum('total'),
            'collectedToday' => (int) Payment::where('clinic_id', $me->clinic_id)->where('date', $today)->sum('amount'),
            'billedMonth' => (int) (clone $live)->where('date', 'like', substr($today, 0, 7).'-%')->sum('total'),
            'outstanding' => (int) (clone $live)->sum(DB::raw('total - paid')),
        ]);
    }

    public function create(Request $r)
    {
        $me = $this->me();
        $patient = $r->query('patient') ? $this->own(Patient::findOrFail((int) $r->query('patient'))) : null;
        $prices = TreatmentPrice::where('clinic_id', $me->clinic_id)->where('active', true)->orderBy('name')->get();
        return view('billing.create', [
            'patient' => $patient,
            'prices' => $prices,
            'priceData' => $prices->map(fn ($p) => ['name' => $p->name, 'rate' => Money::plain($p->price), 'gst' => $p->gst_rate, 'sac' => $p->sac])->values(),
            'gst' => (bool) $me->clinic->gstin,
            'today' => Fmt::today(),
        ]);
    }

    public function store(Request $r)
    {
        $me = $this->me();
        $clinic = $me->clinic;
        $v = $r->validate([
            'patient_id' => 'required|integer',
            'date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'items' => 'required|array|min:1|max:30',
            'items.*.name' => 'required|string|max:150',
            'items.*.tooth' => 'nullable|string|max:40',
            'items.*.sac' => 'nullable|string|max:8',
            'items.*.qty' => 'required|integer|min:1|max:99',
            'items.*.rate' => 'required',
            'items.*.discount' => 'nullable',
            'items.*.gst' => 'nullable|integer|in:'.implode(',', Money::GST_RATES),
            'notes' => 'nullable|string|max:500',
            'pay_amount' => 'nullable',
            'pay_mode' => 'nullable|in:'.implode(',', array_keys(Money::MODES)),
            'pay_ref' => 'nullable|string|max:100',
        ], [
            'patient_id.required' => 'Pick the patient first.',
            'items.required' => 'Add at least one treatment.',
            'items.*.name.required' => 'Every line needs a treatment name.',
            'date.before_or_equal' => 'The bill date cannot be in the future.',
        ]);
        $patient = $this->own(Patient::findOrFail((int) $v['patient_id']));

        $lines = [];
        foreach (array_values($v['items']) as $i => $it) {
            $rate = Money::parse($it['rate']);
            $disc = ($it['discount'] ?? '') === '' ? 0 : Money::parse($it['discount']);
            if ($rate === null || $rate === 0) {
                throw ValidationException::withMessages(["items.$i.rate" => 'Line '.($i + 1).': enter the price.']);
            }
            if ($disc === null || $disc > $rate * $it['qty']) {
                throw ValidationException::withMessages(["items.$i.discount" => 'Line '.($i + 1).': the discount is more than the price.']);
            }
            $gst = $clinic->gstin ? (int) ($it['gst'] ?? 0) : 0;
            $lines[] = ['name' => $it['name'], 'tooth' => $it['tooth'] ?? null, 'sac' => $it['sac'] ?: '999313', 'qty' => $it['qty'], 'rate' => $rate, 'gst_rate' => $gst]
                + Money::line($it['qty'], $rate, $disc, $gst);
        }
        $total = array_sum(array_column($lines, 'amount'));

        $pay = ($v['pay_amount'] ?? '') === '' ? 0 : Money::parse($v['pay_amount']);
        if ($pay === null || $pay > $total) {
            throw ValidationException::withMessages(['pay_amount' => 'The amount received cannot be more than the bill total ('.Money::inr($total).').']);
        }
        if ($pay > 0 && empty($v['pay_mode'])) {
            throw ValidationException::withMessages(['pay_mode' => 'Choose how the patient paid.']);
        }

        $inv = DB::transaction(function () use ($me, $clinic, $patient, $v, $lines, $total, $pay) {
            Clinic::whereKey($clinic->id)->lockForUpdate()->first(); // one bill number at a time
            $fy = Money::fy($v['date']);
            $seq = (int) Invoice::where('clinic_id', $clinic->id)->where('fy', $fy)->max('seq') + 1;
            $inv = Invoice::create([
                'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'date' => $v['date'],
                'number' => $clinic->invoice_prefix.'/'.$fy.'/'.str_pad($seq, 4, '0', STR_PAD_LEFT), 'fy' => $fy, 'seq' => $seq,
                'taxable' => array_sum(array_column($lines, 'taxable')), 'discount' => array_sum(array_column($lines, 'discount')),
                'cgst' => array_sum(array_column($lines, 'cgst')), 'sgst' => array_sum(array_column($lines, 'sgst')),
                'total' => $total, 'paid' => 0, 'notes' => $v['notes'] ?? null, 'created_by' => $me->id,
            ]);
            $inv->items()->createMany($lines);
            if ($pay > 0) {
                Payment::create([
                    'clinic_id' => $clinic->id, 'invoice_id' => $inv->id, 'patient_id' => $patient->id, 'date' => Fmt::today(),
                    'mode' => $v['pay_mode'], 'amount' => $pay, 'reference' => $v['pay_ref'] ?? null, 'received_by' => $me->id,
                ]);
                $inv->syncPaid();
            }
            return $inv;
        });

        Activity::log($me, 'Created bill', "$inv->number · $patient->name · ".Money::inr($total));
        if ($pay > 0) {
            Activity::log($me, 'Recorded payment', Money::inr($pay).' by '.Money::MODES[$v['pay_mode']]." · $patient->name · $inv->number");
        }
        return redirect()->route('billing.show', $inv)->with('ok', "Bill $inv->number saved.");
    }

    public function show(Invoice $invoice)
    {
        $this->own($invoice);
        $invoice->load('patient', 'items', 'payments.receiver', 'creator');
        return view('billing.show', ['inv' => $invoice, 'c' => $this->me()->clinic]);
    }

    /** Staff never delete a bill: they cancel it, and the number stays used. */
    public function cancel(Request $r, Invoice $invoice)
    {
        $this->own($invoice);
        $v = $r->validate(['reason' => 'required|string|min:3|max:200'], ['reason.required' => 'Write why the bill is being cancelled.']);
        if ($invoice->cancelled_at) {
            return back();
        }
        if ($invoice->paid > 0) {
            return back()->with('error', 'This bill has payments recorded. The admin must remove them before it can be cancelled.');
        }
        $invoice->update(['cancelled_at' => now(), 'cancel_reason' => $v['reason']]);
        Activity::log($this->me(), 'Cancelled bill', "$invoice->number · ".$v['reason']);
        return back()->with('ok', "Bill $invoice->number was cancelled.");
    }

    public function destroy(Invoice $invoice)
    {
        $this->own($invoice);
        abort_unless($this->me()->isAdmin(), 403, 'Only the admin can delete permanently.');
        $invoice->delete();
        Activity::log($this->me(), 'Deleted bill permanently', "$invoice->number · ".Money::inr($invoice->total));
        return redirect()->route('billing.index')->with('ok', "Bill $invoice->number was deleted.");
    }

    // ---------- Payments ----------

    public function pay(Request $r, Invoice $invoice)
    {
        $this->own($invoice);
        $v = $r->validate([
            'amount' => 'required',
            'mode' => 'required|in:'.implode(',', array_keys(Money::MODES)),
            'reference' => 'nullable|string|max:100',
        ], ['mode.required' => 'Choose how the patient paid.']);
        $amount = Money::parse($v['amount']);
        $me = $this->me();
        $ok = DB::transaction(function () use ($invoice, $amount, $v, $me) {
            $inv = Invoice::whereKey($invoice->id)->lockForUpdate()->first();
            if ($inv->cancelled_at || ! $amount || $amount > $inv->due()) {
                return false;
            }
            Payment::create([
                'clinic_id' => $inv->clinic_id, 'invoice_id' => $inv->id, 'patient_id' => $inv->patient_id, 'date' => Fmt::today(),
                'mode' => $v['mode'], 'amount' => $amount, 'reference' => $v['reference'] ?? null, 'received_by' => $me->id,
            ]);
            $inv->syncPaid();
            return true;
        });
        if (! $ok) {
            return back()->withInput()->with('error', 'Enter an amount up to the balance due, '.Money::inr($invoice->due()).'.');
        }
        Activity::log($me, 'Recorded payment', Money::inr($amount).' by '.Money::MODES[$v['mode']].' · '.$invoice->patient->name." · $invoice->number");
        return back()->with('ok', Money::inr($amount).' received.');
    }

    public function unpay(Payment $payment)
    {
        $this->own($payment);
        abort_unless($this->me()->isAdmin(), 403, 'Only the admin can remove a payment.');
        $inv = $payment->invoice;
        DB::transaction(function () use ($payment, $inv) {
            $payment->delete();
            $inv->syncPaid();
        });
        Activity::log($this->me(), 'Removed payment', Money::inr($payment->amount).' · '.$inv->number);
        return back()->with('ok', 'Payment removed.');
    }

    // ---------- Price list ----------

    public function prices()
    {
        $me = $this->me();
        return view('billing.prices', [
            'rows' => TreatmentPrice::where('clinic_id', $me->clinic_id)->orderByDesc('active')->orderBy('name')->get(),
            'gst' => (bool) $me->clinic->gstin,
        ]);
    }

    public function savePrice(Request $r, ?TreatmentPrice $price = null)
    {
        $me = $this->me();
        if ($price?->exists) {
            $this->own($price);
        }
        $v = $r->validate([
            'name' => 'required|string|min:2|max:150',
            'price' => 'required',
            'gst_rate' => 'required|integer|in:'.implode(',', Money::GST_RATES),
            'sac' => 'nullable|string|max:8',
        ]);
        $paise = Money::parse($v['price']);
        if ($paise === null) {
            return back()->withInput()->with('error', 'Enter the price as a number, like 1500.');
        }
        $data = ['name' => $v['name'], 'price' => $paise, 'gst_rate' => (int) $v['gst_rate'], 'sac' => $v['sac'] ?: '999313'];
        if ($price?->exists) {
            $price->update($data);
            Activity::log($me, 'Changed price', $price->name.' · '.Money::inr($paise));
        } else {
            $price = TreatmentPrice::create($data + ['clinic_id' => $me->clinic_id]);
            Activity::log($me, 'Added to price list', $price->name.' · '.Money::inr($paise));
        }
        return back()->with('ok', "$price->name saved.");
    }

    public function priceActive(Request $r, TreatmentPrice $price)
    {
        $this->own($price);
        $price->update(['active' => $r->boolean('active')]);
        Activity::log($this->me(), $price->active ? 'Enabled price' : 'Disabled price', $price->name);
        return $r->expectsJson() ? response()->json(['ok' => true]) : back();
    }
}
