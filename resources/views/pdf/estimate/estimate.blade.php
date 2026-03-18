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
            background-image: url('data:image/png;base64,{{ base64_encode(file_get_contents(resource_path('quotation-template/template.png'))) }}');
            background-size: 210mm 297mm;
            background-repeat: no-repeat;
            background-position: center;
            z-index: -10;
        }
        
        tr {
            page-break-inside: avoid;
            break-inside: avoid;
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
                <col style="width: 10%;">
                <col style="width: 60%;">
                <col style="width: 15%;">
                <col style="width: 15%;">
            </colgroup>
            <thead>
                @include('pdf.estimate.partials.header')
                @include('pdf.estimate.partials.intro')
                <tr>
                    <th class="items-th" style="width:10%;">Item</th>
                    <th class="items-th" style="width:60%;">Work Description</th>
                    <th class="items-th" style="width:15%;">Quantity</th>
                    <th class="items-th" style="width:15%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @php $itemIndex = 1; @endphp
                @foreach($document->lineItemGroups as $group)
                    @if($group->name)
                        <tr>
                            <td class="items-td" colspan="4" style="background-color: #f7f1eb; font-weight: bold;">{{ $group->name }}</td>
                        </tr>
                    @endif
                    @foreach($group->items as $item)
                        <tr>
                            <td class="items-td" style="width:10%;">{{ $itemIndex++ }}</td>
                            <td class="items-td" style="width:60%;">
                                {!! nl2br(e($item->description)) !!}
                            </td>
                            <td class="items-td" style="width:15%;">{{ $item->quantity }} {{ $item->unit }}</td>
                            <td class="items-td" style="width:15%;">{{ $item->subtotal }}</td>
                        </tr>
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
                    <td colspan="4" style="height: 100px;"></td>
                </tr>
            </tfoot>
        </table>

        <!-- Materials and Terms Section -->
        <table class="main-table">
            <colgroup>
                <col style="width: 10%;">
                <col style="width: 60%;">
                <col style="width: 15%;">
                <col style="width: 15%;">
            </colgroup>
            <thead>
                @include('pdf.estimate.partials.header')
                @include('pdf.estimate.partials.intro')
            </thead>
            <tbody>
                <tr style="page-break-before: always;">
                    <td colspan="4">
                        <div class="header-title">MATERIALS GUIDE DECLARE</div>
                        <div style="margin-bottom: 15px; font-size:12px;">
                            The following materials shall be used by default for all renovation works, unless otherwise specified in the quotation above
                        </div>

                        <div class="section-title">Plumbing work:</div>
                        <ul>
                            <li>Stainless steel pipes for exposed piping work</li>
                            <li>Copper pipes for concealed piping work</li>
                        </ul>

                        <div class="section-title">Masonry work:</div>
                        <ul>
                            <li>Black cement</li>
                            <li>Chemical cement</li>
                            <li>W1 cement strengthener</li>
                            <li>2 in 1 Prepack floor screed</li>
                            <li>For waterproofing : NS Grout, QS 104, 3 in 1 waterproof screed</li>
                            <li>Tiles price per sqft capped $3.50</li>
                        </ul>

                        <div class="section-title">Painting work:</div>
                        <ul>
                            <li>Ceiling : Nippon Matex White</li>
                            <li>Wall : Nippon Vinilex 5000</li>
                        </ul>

                        <div class="section-title">Carpentry work:</div>
                        <ul>
                            <li>Solid Ply-wood</li>
                            <li>Laminate finish, unless stated otherwise</li>
                            <li>ABS trimming for all doors</li>
                            <li>Internal color pvc carcass</li>
                            <li>Soft close hinges for doors</li>
                            <li>Fully extend soft close for all drawers</li>
                            <li>Drawer base : 15mm strengthen base</li>
                        </ul>
                    </td>
                </tr>
                
                <tr class="terms" style="page-break-before: always;">
                    <td colspan="4">
                        <div class="header-title" style="text-align:left;">TERMS AND CONDITIONS:-</div>
            
                        <div class="section-title">Prices :</div>
                        <div class="terms-grid">
                            <div class="term-num">1</div>
                            <div class="term-desc">All prices and discounts are to be clearly indicated in the contract.</div>
                        </div>

                        <div class="section-title">Downpayments :</div>
                        <div class="terms-grid">
                            <div class="term-num">2</div>
                            <div class="term-desc">We require a downpayment of 10% of Contract Sum , at the time client signed and confirmed the contract.</div>
                        </div>

                        <div class="section-title">Quality :</div>
                        <div class="terms-grid">
                            <div class="term-num">3</div>
                            <div class="term-desc">Our materials used are as specified and received by client,and is delivered in new condition.</div>
                        </div>
                        <div class="terms-grid">
                            <div class="term-num">4</div>
                            <div class="term-desc">We provide quality workmanship according to the specifications agreed and confirmed design/layout &amp; drawing by client.</div>
                        </div>
                        <div class="terms-grid">
                            <div class="term-num">5</div>
                            <div class="term-desc">There will be a planning Works Schedule agreed with client and upon completion, a joint inspection will be made to confirm any rectification as may be required before the client takes over the premises.</div>
                        </div>
                        <div class="terms-grid">
                            <div class="term-num">6</div>
                            <div class="term-desc">The materials provided and works done by third party which are not indicated in contract are exclude from the Company's liability and warranty.</div>
                        </div>

                        <div class="section-title">Payment Terms :</div>
                        <div class="terms-grid">
                            <div class="term-num">7</div>
                            <div class="term-desc">
                                -10% Downpayment of Contract Sum upon confirmation.<br>
                                -45% of Contract Sum upon commencement of work.<br>
                                -40% of Contract Sum upon commencement of measurement for carpentry works<br>
                                -5% of Contract Sum upon Completion (Hand Over Of unit, rectify work under warranty period).<br>
                                <br>
                                Note: A 2% monthly interest fee will be charged for late payments more than 2 weeks
                            </div>
                        </div>
                        <div class="terms-grid">
                            <div class="term-num">8</div>
                            <div class="term-desc">We will ensure accuracy in billings. Any additional items requested in the course of the works are chargeable and will be recorded on Variation Addendums for reckoning upon works completion.</div>
                        </div>
                        <div class="terms-grid">
                            <div class="term-num">9</div>
                            <div class="term-desc">We accepts ONLY Internet Transfer / Scan QR Code Banking / crossed cheque made payable to "MUYI CARPENTERS PTE LTD". Bank Account No: OCBC: 601-071293-001</div>
                        </div>
                        <div class="terms-grid">
                            <div class="term-num">10</div>
                            <div class="term-desc">For safety reason, we do not encourage client to make payment by cash. If client insist to pay by cash, please call account department ( Monday to Friday, 9a.m. - 6 p.m.) to certify for it on that day or next working day when client made payment.</div>
                        </div>
                        <div class="terms-grid">
                            <div class="term-num">11</div>
                            <div class="term-desc">If client fail to do as clause 10 , you will be solely responsible for any loss incurred thereby.</div>
                        </div>
                        <div class="terms-grid">
                            <div class="term-num">12</div>
                            <div class="term-desc">Notwithstanding, we are still entitled to claim against you for the balance of the contract sum due and owed to us and any loss or damage incurred thereby.</div>
                        </div>
                        <div class="terms-grid">
                            <div class="term-num">13</div>
                            <div class="term-desc">We reserve the right to put on hold or cease work if client fail to comply with the payment mode.</div>
                        </div>
                        <div class="terms-grid">
                            <div class="term-num">14</div>
                            <div class="term-desc">Please keep your copy of payment to MUYI CARPENTERS PTE LTD as proof of payment, no receipt is require.<br>
                            Member of DP SME Credit Bureau - Your prompt payment records contributes towards building a positive credit profile for yourself.</div>
                        </div>

                        <div class="acknowledge-text">I acknowledge the payment terms</div>
                        
                        <div class="section-title">Warranty :</div>
                        <div class="terms-grid">
                            <div class="term-num">15</div>
                            <div class="term-desc">
                                We provide Warranty of 24 months of our work from the date of work commencement.<br>
                                Warranty is good as long as damage is not a result of :<br>
                                - Third party involvement in adding or altering the existing standard of finishing works.<br>
                                - Wilful negligence of user.<br>
                                - Wear &amp; Tear.
                            </div>
                        </div>

                        <div class="section-title">Confidentiality of information :</div>
                        <div class="terms-grid">
                            <div class="term-num">16</div>
                            <div class="term-desc">We treat all transactions and client's particulars as strictly confidential. Client's data are collected solely for completing transactions and will not be used for other purposes.</div>
                        </div>

                        <div class="section-title">Mediation :</div>
                        <div class="terms-grid">
                            <div class="term-num">17</div>
                            <div class="term-desc">Only F.O.C. items stated in the contract signed will not be chargeable. All misc.orders authorised/verbally agreed by owners will be justified as an agreement &amp; subject to charges.</div>
                        </div>
                        <div class="terms-grid">
                            <div class="term-num">18</div>
                            <div class="term-desc">Any additional item or variation will be issued with a separate contract / invoice.</div>
                        </div>
                        <div class="terms-grid">
                            <div class="term-num">19</div>
                            <div class="term-desc">Under mutual agreement, within reasonable grounds, either party can bring out withdrawal/revised contract. (reasonable charges may be implied)</div>
                        </div>
                        <div class="terms-grid">
                            <div class="term-num">20</div>
                            <div class="term-desc">The company shall act upon our professionalism for construction details base on standard market practice unless specified by client. Drawing is subjected to modification of its design and measurement from time to time to suit construction purposes.</div>
                        </div>
                        <div class="terms-grid">
                            <div class="term-num">21</div>
                            <div class="term-desc">One copy of this contract will be given to client for reference.</div>
                        </div>
                        <div class="terms-grid">
                            <div class="term-num">22</div>
                            <div class="term-desc">This contract will be terminated after 6 months if client is already unreachable. Necessary fees will be charged accordingly.</div>
                        </div>
                        
                        <div class="acknowledge-text">I acknowledge the copy of contract</div>
                        
                        <!-- <div class="signatures">
                            <div class="signature-box">
                                <div class="signature-line">
                                    Agreed and Confirmed By:<br>
                                    Customer Name/NRIC/Date
                                </div>
                            </div>
                        </div> -->
                    </td>
                </tr>

            </tbody>
        </table>

    </div>
</body>
</html>
