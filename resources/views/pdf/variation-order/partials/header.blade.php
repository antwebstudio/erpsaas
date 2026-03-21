<th colspan="4" style="height: 180px; vertical-align: top; padding-top: 8mm; padding-bottom: 5mm;">
    <table style="width: 100%; border-collapse: collapse; border: none; font-family: 'DOTFUB+Open Sans Regular', sans-serif; font-size: 10px; color: #000;">
        <tr>
            <!-- Left Column: Company Details -->
            <td style="width: 50%; vertical-align: top; text-align: left; padding-left: 10mm;">
                <!-- Company logo or details can go here -->
            </td>
            <!-- Right Column: Quote info and Customer Details -->
            <td style="width: 50%; vertical-align: top; text-align: left; padding-left: 30mm;">
                <div style="font-size: 11px; margin-top: 10px; margin-bottom: 5px; color: #000;">VARIATION ORDER</div>
                <table style="width: 100%; border-collapse: collapse; border: none; font-size: 8px;">
                    <tr>
                        <td style="width: 50%; padding: 1px 0;">VO No:</td>
                        <td style="padding: 1px 0;">{{ $document->number }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 1px 0;">Customer:</td>
                        <td style="padding: 1px 0;">{{ $document->client->name ?? '' }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 1px 0;">NRIC last 4 digit:</td>
                        <td style="padding: 1px 0;">{{ $document->client->nric ?? '' }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 1px 0;">Contact No:</td>
                        <td style="padding: 1px 0;">{{ $document->client->phone ?? '' }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 1px 0;">Email:</td>
                        <td style="padding: 1px 0;">{{ $document->client->email ?? '' }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 1px 0; vertical-align: top;">Address:</td>
                        <td style="padding: 1px 0;">{{ $document->client->addressLine1 ?? '' }}<br>{{ $document->client->addressLine2 ?? '' }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 1px 0;">Postal Code:</td>
                        <td style="padding: 1px 0;">{{ $document->client->postalCode ?? '' }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 1px 0;">Date:</td>
                        <td style="padding: 1px 0;">{{ $document->date }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 1px 0;">Sale Person:</td>
                        <td style="padding: 1px 0;">{{ $document->createdBy->name }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 1px 0;">Sale Person Email:</td>
                        <td style="padding: 1px 0;">{{ $document->createdBy->email }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</th>
