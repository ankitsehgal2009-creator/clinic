<?php

namespace App\Support;

/** Money is kept in paise (₹1 = 100). These helpers parse, total and print it the Indian way. */
class Money
{
    /** Name, price in rupees, GST %. Healthcare is GST-exempt; cosmetic work is 18%. */
    public const DEFAULT_PRICES = [
        ['Consultation', 300, 0], ['IOPA X-ray', 300, 0], ['Scaling & polishing', 1500, 0], ['Composite filling', 1200, 0],
        ['Root canal treatment', 6000, 0], ['Extraction', 1000, 0], ['Surgical extraction', 3500, 0], ['PFM crown', 6000, 0],
        ['Zirconia crown', 11000, 0], ['Braces adjustment', 1200, 0], ['Complete denture', 18000, 0], ['Teeth whitening (cosmetic)', 8000, 18],
    ];

    public const MODES = ['CASH' => 'Cash', 'UPI' => 'UPI', 'CARD' => 'Card', 'BANK' => 'Bank transfer', 'CHEQUE' => 'Cheque'];

    public const GST_RATES = [0, 5, 12, 18, 28];

    public const NOTES = [500, 200, 100, 50, 20, 10];

    public const STATES = [
        '01' => 'Jammu & Kashmir', '02' => 'Himachal Pradesh', '03' => 'Punjab', '04' => 'Chandigarh', '05' => 'Uttarakhand', '06' => 'Haryana',
        '07' => 'Delhi', '08' => 'Rajasthan', '09' => 'Uttar Pradesh', '10' => 'Bihar', '11' => 'Sikkim', '12' => 'Arunachal Pradesh',
        '13' => 'Nagaland', '14' => 'Manipur', '15' => 'Mizoram', '16' => 'Tripura', '17' => 'Meghalaya', '18' => 'Assam', '19' => 'West Bengal',
        '20' => 'Jharkhand', '21' => 'Odisha', '22' => 'Chhattisgarh', '23' => 'Madhya Pradesh', '24' => 'Gujarat', '26' => 'Dadra & Nagar Haveli and Daman & Diu',
        '27' => 'Maharashtra', '29' => 'Karnataka', '30' => 'Goa', '31' => 'Lakshadweep', '32' => 'Kerala', '33' => 'Tamil Nadu', '34' => 'Puducherry',
        '35' => 'Andaman & Nicobar', '36' => 'Telangana', '37' => 'Andhra Pradesh', '38' => 'Ladakh',
    ];

    /** "1,500" or "1500.50" typed by a person, to paise. Null if it isn't a sensible amount. */
    public static function parse($v): ?int
    {
        $s = str_replace([',', '₹', ' '], '', trim((string) $v));
        if ($s === '' || ! preg_match('/^\d{1,9}(\.\d{1,2})?$/', $s)) {
            return null;
        }
        return (int) round(((float) $s) * 100);
    }

    /** ₹1,23,456 (paise shown only when there are some). */
    public static function inr(int $paise): string
    {
        $neg = $paise < 0;
        $paise = abs($paise);
        $r = intdiv($paise, 100);
        $p = $paise % 100;
        $s = (string) $r;
        if (strlen($s) > 3) {
            $head = substr($s, 0, -3);
            $s = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $head).','.substr($s, -3);
        }
        return ($neg ? '−' : '').'₹'.$s.($p ? '.'.str_pad((string) $p, 2, '0', STR_PAD_LEFT) : '');
    }

    /** Plain number for form fields: 1500 or 1500.50. */
    public static function plain(int $paise): string
    {
        return $paise % 100 ? number_format($paise / 100, 2, '.', '') : (string) intdiv($paise, 100);
    }

    /** "Rupees Twenty Six Thousand Nine Hundred Forty Only", printed on invoices. */
    public static function words(int $paise): string
    {
        $r = intdiv($paise, 100);
        $p = $paise % 100;
        $s = $r ? self::spell($r) : 'Zero';
        return 'Rupees '.$s.($p ? ' and '.self::spell($p).' Paise' : '').' Only';
    }

    private static function spell(int $n): string
    {
        $a = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $b = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        $two = fn ($x) => $x < 20 ? $a[$x] : trim($b[intdiv($x, 10)].' '.$a[$x % 10]);
        $out = [];
        foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand'], [100, 'Hundred']] as [$unit, $word]) {
            if ($n >= $unit) {
                $q = intdiv($n, $unit);
                $out[] = ($unit === 10000000 ? self::spell($q) : $two($q)).' '.$word;
                $n %= $unit;
            }
        }
        if ($n) {
            $out[] = $two($n);
        }
        return implode(' ', $out);
    }

    /** Indian financial year of a date, e.g. 2026-10-19 → "26-27". */
    public static function fy(string $date): string
    {
        $y = (int) substr($date, 0, 4);
        $start = (int) substr($date, 5, 2) >= 4 ? $y : $y - 1;
        return sprintf('%02d-%02d', $start % 100, ($start + 1) % 100);
    }

    /** One invoice line. GST is split equally into CGST and SGST (patients are always in the clinic's state). */
    public static function line(int $qty, int $rate, int $discount, int $gst): array
    {
        $gross = $qty * $rate;
        $discount = min($discount, $gross);
        $taxable = $gross - $discount;
        $half = (int) round($taxable * $gst / 200);
        return ['taxable' => $taxable, 'discount' => $discount, 'cgst' => $half, 'sgst' => $half, 'amount' => $taxable + 2 * $half];
    }

    public static function stateOf(?string $gstin): ?string
    {
        return $gstin ? (self::STATES[substr($gstin, 0, 2)] ?? null) : null;
    }
}
