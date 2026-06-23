@php
    $headerTopMargin = '5mm';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        * { box-sizing: border-box; }
        @page {
            size: A4;
            margin: 0 0 32mm 0;
        }
        body {
            margin: 0;
            padding: 0 15mm 15mm 15mm;
            font-family: 'Open Sans', 'Aileron', Arial, sans-serif;
            font-size: 13px;
            color: {{ $document->colorText }};
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
            color: {{ $document->accentColor }};
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
            border: 1px solid {{ $document->accentColor }};
            padding: 8px;
        }
        .items-th {
            background-color: {{ $document->colorSecondary }};
            color: {{ $document->colorSecondaryText }};
            font-weight: bold;
        }
        .items-td {
            font-size: 12px;
            border-top: none;
        }

        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
        }
        .signature-box { width: 45%; }
        .signature-line {
            margin-top: 40px;
            border-top: 1px solid {{ $document->accentColor }};
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
            color: {{ $document->accentColor }};
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
        tfoot { display: table-row-group; }

        .acknowledge-text {
            margin-top: 20px;
            margin-bottom: 10px;
            font-weight: bold;
            font-size: 12px;
            border-top: 1px solid {{ $document->colorText }};
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
                <tr>@include('pdf.variation-order.partials.header')</tr>
                <tr><td colspan="4" style="height: {{ $headerTopMargin }};"></td></tr>
                @include('pdf.variation-order.partials.intro')
                <tr>
                    <th class="items-th" style="width:6%; text-align: center;">#</th>
                    <th class="items-th" style="width:66%; text-align: center;">Work Description</th>
                    <th class="items-th" style="width:13%; text-align: center;">Qty/Unit</th>
                    <th class="items-th" style="width:15%; text-align: center;">Amount</th>
                </tr>
            </thead>
            {{-- Removed Items Section --}}
            @php
                $hasAnyRemoved = collect($document->lineItemGroups)->contains(fn($g) => collect($g->items)->contains(fn($i) => $i->isNegative));
            @endphp
            @if($hasAnyRemoved)
                <tbody>
                    <tr>
                        <th class="items-td" colspan="4" style="background-color: {{ $document->colorSectionBg }}; color: {{ $document->colorSectionBgText }}; font-weight: bold;">Removed Items</th>
                    </tr>
                </tbody>
                @php $itemIndex = 1; $currentParent = null; $parentShown = false; @endphp
                @foreach($document->lineItemGroups as $group)
                    @php
                        $removedItems = collect($group->items)->filter(fn($i) => $i->isNegative)->values();
                    @endphp
                    
                    @if($group->isMain)
                        @php $currentParent = $group->name; $parentShown = false; @endphp
                        @if($removedItems->isNotEmpty())
                            <tbody>
                                <tr class="header-row">
                                    <th class="items-td" colspan="4" style="background-color: {{ $document->colorGroupBg }}; color: {{ $document->colorGroupBgText }}; font-weight: bold;">{{ $group->name }}</th>
                                </tr>
                                @php $parentShown = true; @endphp
                                @foreach($removedItems as $item)
                                    <tr>
                                        <td class="items-td" style="width:6%; text-align: center;">{{ $itemIndex++ }}</td>
                                        <td class="items-td" style="width:66%;">
                                            @if(!config('erp.hide_item_name', false) && empty(trim($item->description)))
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
                                        <td class="items-td" style="width:15%; text-align: {{ in_array(trim($item->subtotal), ['INCLUDED', 'KIV']) ? 'center' : 'right' }};">{{ $item->subtotal }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        @endif
                    @else
                        {{-- Subgroup --}}
                        @if($removedItems->isNotEmpty())
                            <tbody>
                                @if($currentParent && !$parentShown)
                                    <tr class="header-row">
                                        <th class="items-td" colspan="4" style="background-color: {{ $document->colorGroupBg }}; color: {{ $document->colorGroupBgText }}; font-weight: bold;">{{ $currentParent }}</th>
                                    </tr>
                                    @php $parentShown = true; @endphp
                                @endif
                                @if($group->name)
                                    <tr class="header-row">
                                        <th class="items-td" colspan="4" style="background-color: {{ $document->colorSubgroupBg }}; color: {{ $document->colorSubgroupText }}; font-weight: bold; padding-left: 16px;">{{ $group->name }}</th>
                                    </tr>
                                @endif
                                @foreach($removedItems as $item)
                                    <tr>
                                        <td class="items-td" style="width:6%; text-align: center;">{{ $itemIndex++ }}</td>
                                        <td class="items-td" style="width:66%;">
                                            @if(!config('erp.hide_item_name', false) && empty(trim($item->description)))
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
                                        <td class="items-td" style="width:15%; text-align: {{ in_array(trim($item->subtotal), ['INCLUDED', 'KIV']) ? 'center' : 'right' }};">{{ $item->subtotal }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        @endif
                    @endif
                @endforeach
            @endif

            {{-- Added Items Section --}}
            @php
                $hasAnyAdded = collect($document->lineItemGroups)->contains(fn($g) => collect($g->items)->contains(fn($i) => !$i->isNegative));
            @endphp
            @if($hasAnyAdded)
                <tbody>
                    <tr>
                        <th class="items-td" colspan="4" style="background-color: {{ $document->colorSectionBg }}; font-weight: bold;">Added Items</th>
                    </tr>
                </tbody>
                @php $itemIndex = 1; $currentParent = null; $parentShown = false; @endphp
                @foreach($document->lineItemGroups as $group)
                    @php
                        $addedItems = collect($group->items)->filter(fn($i) => !$i->isNegative)->values();
                    @endphp
                    
                    @if($group->isMain)
                        @php $currentParent = $group->name; $parentShown = false; @endphp
                        @if($addedItems->isNotEmpty())
                            <tbody>
                                <tr class="header-row">
                                    <th class="items-td" colspan="4" style="background-color: {{ $document->colorGroupBg }}; color: {{ $document->colorGroupBgText }}; font-weight: bold;">{{ $group->name }}</th>
                                </tr>
                                @php $parentShown = true; @endphp
                                @foreach($addedItems as $item)
                                    <tr>
                                        <td class="items-td" style="width:6%;">{{ $itemIndex++ }}</td>
                                        <td class="items-td" style="width:66%;">
                                            @if(!config('erp.hide_item_name', false) && empty(trim($item->description)))
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
                                        <td class="items-td" style="width:15%; text-align: {{ in_array(trim($item->subtotal), ['INCLUDED', 'KIV']) ? 'center' : 'right' }};">{{ $item->subtotal }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        @endif
                    @else
                        {{-- Subgroup --}}
                        @if($addedItems->isNotEmpty())
                            <tbody>
                                @if($currentParent && !$parentShown)
                                    <tr class="header-row">
                                        <th class="items-td" colspan="4" style="background-color: {{ $document->colorGroupBg }}; color: {{ $document->colorGroupBgText }}; font-weight: bold;">{{ $currentParent }}</th>
                                    </tr>
                                    @php $parentShown = true; @endphp
                                @endif
                                @if($group->name)
                                    <tr class="header-row">
                                        <th class="items-td" colspan="4" style="background-color: {{ $document->colorSubgroupBg }}; color: {{ $document->colorSubgroupText }}; font-weight: bold; padding-left: 16px;">{{ $group->name }}</th>
                                    </tr>
                                @endif
                                @foreach($addedItems as $item)
                                    <tr>
                                        <td class="items-td" style="width:6%;">{{ $itemIndex++ }}</td>
                                        <td class="items-td" style="width:66%;">
                                            @if(!config('erp.hide_item_name', false) && empty(trim($item->description)))
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
                                        <td class="items-td" style="width:15%; text-align: {{ in_array(trim($item->subtotal), ['INCLUDED', 'KIV']) ? 'center' : 'right' }};">{{ $item->subtotal }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        @endif
                    @endif
                @endforeach
            @endif

            {{-- Tax and Total --}}
            <tbody>
                @if($document->subtotal || $document->discount || $document->tax)
                    @if($document->subtotal)
                        <tr>
                            <td colspan="3" class="items-td" style="text-align: right; font-weight: bold;">Subtotal</td>
                            <td class="items-td" style="text-align: right;">{{ $document->subtotal }}</td>
                        </tr>
                    @endif
                    @if($document->discount)
                        <tr>
                            <td colspan="3" class="items-td" style="text-align: right; font-weight: bold;">Discount</td>
                            <td class="items-td" style="text-align: right;">{{ $document->discount }}</td>
                        </tr>
                    @endif
                    @if($document->tax)
                        <tr>
                            <td colspan="3" class="items-td" style="text-align: right; font-weight: bold;">Tax</td>
                            <td class="items-td" style="text-align: right;">{{ $document->tax }}</td>
                        </tr>
                    @endif
                @endif
                <tr>
                    <td colspan="3" class="items-td" style="text-align: right; font-weight: bold; background-color: {{ $document->colorSecondary }}; color: {{ $document->colorSecondaryText }};">Total</td>
                    <td class="items-td" style="font-weight: bold; background-color: {{ $document->colorSecondary }}; color: {{ $document->colorSecondaryText }}; text-align: right;">{{ $document->total }}</td>
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
                <tr>@include('pdf.variation-order.partials.header')</tr>
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
