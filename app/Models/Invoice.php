<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['cancelled_at' => 'datetime'];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function due(): int
    {
        return $this->cancelled_at ? 0 : max(0, $this->total - $this->paid);
    }

    /** PAID, PART, DUE or CANCELLED. */
    public function state(): string
    {
        if ($this->cancelled_at) {
            return 'CANCELLED';
        }
        if ($this->paid >= $this->total) {
            return 'PAID';
        }
        return $this->paid > 0 ? 'PART' : 'DUE';
    }

    public const STATE = [
        'PAID' => ['Paid', 'c-ok'], 'PART' => ['Part paid', 'c-warn'], 'DUE' => ['Unpaid', 'c-bad'], 'CANCELLED' => ['Cancelled', ''],
    ];

    /** Heading printed on the bill, by GST rules: exempt-only bills are a "Bill of Supply". */
    public function title(?string $gstin): string
    {
        if (! $gstin) {
            return 'INVOICE';
        }
        return $this->cgst + $this->sgst > 0 ? 'TAX INVOICE' : 'BILL OF SUPPLY';
    }

    /** Recalculate the paid total from the payments table. */
    public function syncPaid(): void
    {
        $this->update(['paid' => (int) $this->payments()->sum('amount')]);
    }
}
