<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        * { box-sizing: border-box; }
        @page {
            size: A4;
            margin: 0;
        }
        body {
            margin: 0;
            padding: 12mm 15mm 15mm 15mm;
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #000;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        table { border-collapse: collapse; }

        /* ── Header ── */
        .company-name {
            font-size: 22px;
            font-weight: bold;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }
        .company-details {
            font-size: 10px;
            line-height: 1.6;
        }

        /* ── Info boxes ── */
        .info-table {
            width: 100%;
            margin-bottom: 10px;
        }
        .customer-box {
            border: 1px solid #000;
            padding: 6px 8px;
            font-size: 10px;
            line-height: 1.8;
            vertical-align: top;
            width: 55%;
        }
        .invoice-box {
            border: 1px solid #000;
            font-size: 10px;
            vertical-align: top;
            width: 40%;
        }
        .invoice-box-title {
            background-color: #000;
            color: #fff;
            font-weight: bold;
            font-size: 12px;
            text-align: center;
            padding: 4px;
            letter-spacing: 1px;
        }
        .invoice-box-row {
            display: flex;
            padding: 3px 8px;
            border-top: 1px solid #ccc;
        }
        .invoice-box-label { width: 110px; font-weight: bold; }

        /* ── Meta row ── */
        .meta-table {
            width: 100%;
            border: 1px solid #000;
            margin-bottom: 0;
        }
        .meta-th {
            background-color: #d9d9d9;
            font-weight: bold;
            text-align: center;
            padding: 4px 6px;
            border: 1px solid #000;
            font-size: 10px;
        }
        .meta-td {
            text-align: center;
            padding: 4px 6px;
            border: 1px solid #000;
            font-size: 10px;
        }

        /* ── Items table ── */
        .items-table {
            width: 100%;
            border: 1px solid #000;
            margin-top: 0;
        }
        .items-th {
            background-color: #d9d9d9;
            font-weight: bold;
            text-align: center;
            padding: 5px 6px;
            border: 1px solid #000;
            font-size: 10px;
        }
        .items-td {
            padding: 5px 6px;
            border: 1px solid #000;
            font-size: 10px;
            vertical-align: top;
        }
        .items-td-right {
            text-align: right;
        }
        .items-td-center {
            text-align: center;
        }

        /* ── Totals ── */
        .total-row td {
            padding: 3px 6px;
            font-size: 10px;
        }
        .total-label { text-align: right; font-weight: bold; }
        .total-value { text-align: right; width: 120px; border-bottom: 1px solid #000; }
        .grand-total-label { text-align: right; font-weight: bold; font-size: 12px; }
        .grand-total-value {
            text-align: right;
            width: 120px;
            font-weight: bold;
            font-size: 12px;
            background-color: #ffff00;
            padding: 3px 6px;
        }

        /* ── Footer ── */
        .payment-details {
            margin-top: 14px;
            font-size: 10px;
            line-height: 1.7;
        }
        .thank-you {
            text-align: center;
            font-weight: bold;
            font-size: 11px;
            margin-top: 14px;
        }
        .printed-by {
            text-align: right;
            font-size: 10px;
            margin-top: 6px;
            border-top: 1px solid #000;
            padding-top: 4px;
        }
    </style>
</head>
<body>

@php
    use App\Utilities\Currency\CurrencyConverter;
    $profile = $issuingCompany->profile;
    $address = $profile?->address;
    $logoUrl = $profile?->logo_url;
    $taxId = $profile?->tax_id;
    $phone = $profile?->phone_number;

    $client = $invoice->client;
    $billingAddress = $client?->billingAddress;

    $currencyCode = $invoice->currency_code;

    // Collect tax lines
    $taxLines = [];
    if ($invoice->tax_total > 0) {
        $invoice->loadMissing('salesTaxes');
        foreach ($invoice->salesTaxes as $tax) {
            $taxLines[] = [
                'label' => $tax->name . ($tax->rate ? ' @ ' . number_format($tax->rate, 2) . '%' : ''),
                'amount' => null, // computed below
            ];
        }
        // If we have exactly one tax line, assign the full tax_total to it
        if (count($taxLines) === 1) {
            $taxLines[0]['amount'] = CurrencyConverter::formatCentsToMoney($invoice->tax_total, $currencyCode);
        } elseif (count($taxLines) === 0) {
            $taxLines[] = [
                'label' => 'Tax',
                'amount' => CurrencyConverter::formatCentsToMoney($invoice->tax_total, $currencyCode),
            ];
        }
    }
@endphp

{{-- ══════════════════════════════════════════════ --}}
{{-- COMPANY HEADER                                 --}}
{{-- ══════════════════════════════════════════════ --}}
<table style="width:100%; margin-bottom: 10px;">
    <tr>
        <td style="vertical-align: top; width: 60%;">
            <div class="company-name">{{ strtoupper($issuingCompany->name) }}</div>
            <div class="company-details">
                @if($address?->address_line_1)
                    {{ $address->address_line_1 }}<br>
                @endif
                @if($address?->address_line_2)
                    {{ $address->address_line_2 }}<br>
                @endif
                @if($address?->city || $address?->state || $address?->postal_code)
                    {{ implode(' ', array_filter([$address?->city, $address?->state?->name ?? $address?->state, $address?->postal_code])) }}<br>
                @endif
                @if($address?->country)
                    {{ $address->country->name ?? $address->country }}<br>
                @endif
                @if($phone)
                    Tel: {{ $phone }}<br>
                @endif
                Website: www.stylemyspace.com.sg<br>
                Company Regn No: 201731154C | GST Regn No: 201731154C
            </div>
        </td>
        <td style="vertical-align: top; text-align: right; width: 40%;">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="Logo" style="max-height: 80px; max-width: 180px;">
            @endif
        </td>
    </tr>
</table>

{{-- ══════════════════════════════════════════════ --}}
{{-- CUSTOMER BOX + TAX INVOICE BOX                 --}}
{{-- ══════════════════════════════════════════════ --}}
<table class="info-table" style="width:100%; margin-bottom:10px;">
    <tr>
        <td class="customer-box">
            <strong>Customer:</strong> {{ $client?->name ?? '' }}<br>
            <strong>Site Address:</strong> {{ $billingAddress?->address_line_1 ?? '' }}<br>
            @if($billingAddress?->address_line_2)
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ $billingAddress->address_line_2 }}<br>
            @endif
            <strong>Postal code:</strong> {{ $billingAddress?->postal_code ?? '' }}<br>
            <strong>Email:</strong> {{ $client?->email ?? '' }}<br>
            <strong>Interior Designer:</strong> {{ $invoice->createdBy?->name ?? '' }}
        </td>
        <td style="width: 5%;"></td>
        <td style="vertical-align: top; width: 40%;">
            <table style="width: 100%; border: 1px solid #000; border-collapse: collapse;">
                <tr>
                    <td class="invoice-box-title" colspan="2">TAX INVOICE</td>
                </tr>
                <tr>
                    <td style="padding: 4px 8px; border: 1px solid #ccc; font-weight: bold; font-size: 10px;">Invoice No</td>
                    <td style="padding: 4px 8px; border: 1px solid #ccc; font-size: 10px;">{{ $invoice->invoice_number }}</td>
                </tr>
                <tr>
                    <td style="padding: 4px 8px; border: 1px solid #ccc; font-weight: bold; font-size: 10px;">Date</td>
                    <td style="padding: 4px 8px; border: 1px solid #ccc; font-size: 10px;">{{ $invoice->date?->format('d/m/Y') }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- ══════════════════════════════════════════════ --}}
{{-- META ROW: Salesperson | Job | Payment Terms | Currency --}}
{{-- ══════════════════════════════════════════════ --}}
<table class="meta-table">
    <tr>
        <td class="meta-th" style="width:25%;">Salesperson</td>
        <td class="meta-th" style="width:25%;">Job</td>
        <td class="meta-th" style="width:25%;">Payment Terms</td>
        <td class="meta-th" style="width:25%;">Currency</td>
    </tr>
    <tr>
        <td class="meta-td">{{ $invoice->createdBy?->name ?? '' }}</td>
        <td class="meta-td">{{ $invoice->order_number ?? '' }}</td>
        <td class="meta-td">{{ $invoice->due_date ? 'By ' . $invoice->due_date->format('d/m/Y') : '' }}</td>
        <td class="meta-td">
            @php
                try {
                    $currency = \App\Models\Setting\Currency::where('code', $currencyCode)->first();
                    echo $currency?->name ?? $currencyCode;
                } catch (\Throwable $e) {
                    echo $currencyCode;
                }
            @endphp
        </td>
    </tr>
</table>

{{-- ══════════════════════════════════════════════ --}}
{{-- LINE ITEMS TABLE                               --}}
{{-- ══════════════════════════════════════════════ --}}
<table class="items-table">
    <thead>
        <tr>
            <th class="items-th" style="width: 8%;">Item</th>
            <th class="items-th" style="width: 57%;">Description</th>
            <th class="items-th" style="width: 17%;">Unit Price</th>
            <th class="items-th" style="width: 18%;">Line Total</th>
        </tr>
    </thead>
    <tbody>
        @php $itemIndex = 1; @endphp
        @forelse($invoice->lineItems as $item)
            <tr>
                <td class="items-td items-td-center">{{ $itemIndex++ }}</td>
                <td class="items-td">{{ $item->description }}</td>
                <td class="items-td items-td-right">
                    @if($item->unit_price == 0)
                        &mdash;
                    @else
                        {{ CurrencyConverter::formatCentsToMoney($item->unit_price, $currencyCode) }}
                    @endif
                </td>
                <td class="items-td items-td-right">
                    @if($item->subtotal == 0)
                        &mdash;
                    @else
                        {{ CurrencyConverter::formatCentsToMoney($item->subtotal, $currencyCode) }}
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td class="items-td items-td-center">&nbsp;</td>
                <td class="items-td">&nbsp;</td>
                <td class="items-td">&nbsp;</td>
                <td class="items-td">&nbsp;</td>
            </tr>
        @endforelse

        {{-- Subtotal row --}}
        @if($invoice->subtotal > 0 && ($invoice->tax_total > 0 || $invoice->discount_total > 0))
            <tr>
                <td colspan="2" class="items-td">&nbsp;</td>
                <td class="items-td" style="text-align: right; font-weight: bold;">Subtotal:</td>
                <td class="items-td items-td-right">{{ CurrencyConverter::formatCentsToMoney($invoice->subtotal, $currencyCode) }}</td>
            </tr>
        @endif

        {{-- Discount row --}}
        @if($invoice->discount_total > 0)
            <tr>
                <td colspan="2" class="items-td">&nbsp;</td>
                <td class="items-td" style="text-align: right; font-weight: bold;">Discount:</td>
                <td class="items-td items-td-right">{{ CurrencyConverter::formatCentsToMoney($invoice->discount_total, $currencyCode) }}</td>
            </tr>
        @endif

        {{-- Tax rows --}}
        @foreach($taxLines as $taxLine)
            <tr>
                <td colspan="2" class="items-td">&nbsp;</td>
                <td class="items-td" style="text-align: right; font-weight: bold;">{{ $taxLine['label'] }}</td>
                <td class="items-td items-td-right">{{ $taxLine['amount'] }}</td>
            </tr>
        @endforeach

        {{-- Total row --}}
        <tr>
            <td colspan="2" class="items-td">&nbsp;</td>
            <td class="items-td" style="text-align: right; font-weight: bold; font-size: 12px; background-color: #d9d9d9;">TOTAL</td>
            <td class="items-td items-td-right" style="font-weight: bold; font-size: 12px; background-color: #ffff00;">
                {{ CurrencyConverter::formatCentsToMoney($invoice->total, $currencyCode) }}
            </td>
        </tr>
    </tbody>
</table>

{{-- ══════════════════════════════════════════════ --}}
{{-- PAYMENT DETAILS                                --}}
{{-- ══════════════════════════════════════════════ --}}
@if($invoice->terms)
    <div class="payment-details">
        <strong>Payment Details:</strong><br>
        {!! nl2br(e($invoice->terms)) !!}
    </div>
@else
    <div class="payment-details">
        <strong>Payment Details:</strong><br>
        All cheques should be crossed and made payable to: {{ $issuingCompany->name }}<br>
        By internet banking: pay to OCBC current acc no: 707 059 127 001<br>
        Paynow UEN code: 201731154C
    </div>
@endif

<div class="thank-you">THANK YOU FOR YOUR BUSINESS!</div>

<div class="printed-by">
    Printed by: {{ $invoice->createdBy?->name ?? '' }}<br>
    {{ $issuingCompany->name }}
</div>

</body>
</html>
