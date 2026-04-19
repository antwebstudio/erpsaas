@php
    $headerTopMargin = '10mm';
@endphp
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
            padding: 0 15mm 15mm 15mm;
            font-family: 'Open Sans', 'Aileron', Arial, sans-serif;
            font-size: 13px;
            color: #293834;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        .print-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 210mm;
            height: 297mm;
            background-image: url('{{ $document->backgroundImage ?: '' }}');
            background-size: 210mm 297mm;
            background-repeat: no-repeat;
            background-position: center;
            z-index: -10;
        }
        
        tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        tr.header-row {
            page-break-after: avoid;
            break-after: avoid;
        }

        th {
            page-break-after: avoid;
            break-after: avoid;
        }
        
        .content-wrapper {
            width: 100%;
        }
        
        .main-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        
        .header-title {
            font-size: 18px;
            font-weight: bold;
            color: #96693C;
            text-align: center;
            margin-bottom: 20px;
            text-transform: uppercase;
        }
        .terms .header-title {
            font-size: 12px;
            margin-bottom: 8px;
        }

        .info-grid {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            font-size: 12px;
        }
        .info-col {
            width: 48%;
        }
        .info-col div { margin-bottom: 5px; }
        
        .items-th, .items-td {
            text-align: left;
            vertical-align: top;
            border: 1px solid #96693C;
            padding: 8px;
        }
        .items-th {
            background-color: #f7f1eb;
            color: #96693C;
            font-weight: bold;
        }
        .items-td {
            font-size: 12px;
        }
        
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
        }
        .signature-box { width: 45%; }
        .signature-line {
            margin-top: 40px;
            border-top: 1px solid #96693C;
            padding-top: 5px;
        }
        .footer-info {
            margin-top: 20px;
            font-size: 11px;
            line-height: 1.4;
        }
        
        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #96693C;
            margin-top: 10px;
            margin-bottom: 5px;
        }
        .terms .section-title {
            font-size: 10px;
        }
        ul { margin: 0; padding-left: 20px; }
        ul li { margin-bottom: 3px; }
        .terms-grid {
            display: flex;
            font-size: 10px;
            margin-bottom: 3px;
        }
        .term-num { width: 20px; font-weight: bold; font-size: 8px; }
        .term-desc { flex: 1; font-size: 8px; }
        
        thead { display: table-header-group; }
        tfoot { display: table-footer-group; }

        .acknowledge-text {
            margin-top: 20px;
            margin-bottom: 10px;
            font-weight: bold;
            font-size: 12px;
            border-top: 1px solid #293834;
            width: 250px;
            margin-right: 0;
            margin-left: auto;
            text-align: right;
        }
        
    </style>
</head>
<body>

    <div class="print-bg"></div>

    <div class="content-wrapper">
        
        <table class="main-table">
            <colgroup>
                <col style="width: 6%;">
                <col style="width: 66%;">
                <col style="width: 13%;">
                <col style="width: 15%;">
            </colgroup>
            <thead>
                @include('pdf.variation-order.partials.header')
                <tr><td colspan="4" style="height: {{ $haederTopMargin }};"></td></tr>
                @include('pdf.variation-order.partials.intro')
                <tr>
                    <th class="items-th" style="width:6%;">#</th>
                    <th class="items-th" style="width:66%;">Work Description</th>
                    <th class="items-th" style="width:13%; text-align: center;">Qty/Unit</th>
                    <th class="items-th" style="width:15%; text-align: center;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @php $itemIndex = 1; @endphp
                @foreach($document->lineItemGroups as $group)
                    @if($group->name)
                        <tbody style="page-break-inside: avoid; break-inside: avoid;">
                        <tr class="header-row">
                            <th class="items-td" colspan="4" style="background-color: #f7f1eb; font-weight: bold;">{{ $group->name }}</th>
                        </tr>
                    @endif
                    @foreach($group->items as $item)
                        <tr>
                            <td class="items-td" style="width:6%;">{{ $itemIndex++ }}</td>
                            <td class="items-td" style="width:66%;">
                                @if(config('erp.hide_item_name', false) && !$item->isLocked)
                                    <strong>{{ $item->name }}</strong><br>
                                @endif
                                {!! nl2br(e($item->description)) !!}
                            </td>
                            <td class="items-td" style="width:13%; text-align: center;">
                                @if($item->unit && $item->quantity == 1)
                                    {{ $item->unit }}
                                @else
                                    {{ $item->quantity }} {{ $item->unit }}
                                @endif
                            </td>
                            <td class="items-td" style="width:15%; {{ trim($item->subtotal) === 'FOC' ? 'text-align: center;' : '' }}">{{ $item->subtotal }}</td>
                        </tr>
                        @if($loop->first)</tbody>@endif
                    @endforeach
                @endforeach

                @if($document->subtotal || $document->discount || $document->tax)
                    @if($document->subtotal)
                        <tr>
                            <td colspan="3" class="items-td" style="text-align: right; font-weight: bold;">Subtotal</td>
                            <td class="items-td">{{ $document->subtotal }}</td>
                        </tr>
                    @endif
                    @if($document->discount)
                        <tr>
                            <td colspan="3" class="items-td" style="text-align: right; font-weight: bold;">Discount</td>
                            <td class="items-td">{{ $document->discount }}</td>
                        </tr>
                    @endif
                    @if($document->tax)
                        <tr>
                            <td colspan="3" class="items-td" style="text-align: right; font-weight: bold;">Tax</td>
                            <td class="items-td">{{ $document->tax }}</td>
                        </tr>
                    @endif
                @endif
                <tr>
                    <td colspan="3" class="items-td" style="text-align: right; font-weight: bold; background-color: #f7f1eb;">Total</td>
                    <td class="items-td" style="font-weight: bold; background-color: #f7f1eb;">{{ $document->total }}</td>
                </tr>
            </tbody>
            
            <tfoot>
                <tr>
                    <td colspan="4" style="height: 120px;"></td>
                </tr>
            </tfoot>
        </table>

        @if($document->materialsGuide || $document->termsAndConditions)
        <!-- Materials and Terms Section -->
        <table class="main-table">
            <colgroup>
                <col style="width: 10%;">
                <col style="width: 60%;">
                <col style="width: 15%;">
                <col style="width: 15%;">
            </colgroup>
            <thead>
                @include('pdf.variation-order.partials.header')
                <tr><td colspan="4" style="height: {{ $headerTopMargin }};"></td></tr>
                @include('pdf.variation-order.partials.intro')
            </thead>
            <tbody>
                @if($document->materialsGuide)
                    <tr style="page-break-before: always;">
                        <td colspan="4">
                            {!! $document->materialsGuide !!}
                        </td>
                    </tr>
                @endif
                
                @if($document->termsAndConditions)
                    <tr class="terms" style="page-break-before: always;">
                        <td colspan="4">
                            {!! $document->termsAndConditions !!}   
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
        @endif

    </div>
</body>
</html>
