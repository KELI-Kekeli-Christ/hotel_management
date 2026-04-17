<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture {{ $invoice->invoice_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #1f2937;
            line-height: 1.6;
            background: linear-gradient(to bottom, #f9fafb 0%, #ffffff 100%);
            position: relative;
        }
        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            padding: 25px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            color: white;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .hotel-info {
            flex: 1;
        }
        .hotel-name {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 12px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
            letter-spacing: 0.5px;
        }
        .hotel-info div {
            color: rgba(255,255,255,0.95);
            margin-bottom: 6px;
            font-size: 11px;
        }
        .invoice-info {
            text-align: right;
            background: rgba(255,255,255,0.15);
            padding: 15px 20px;
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }
        .invoice-title {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 12px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .invoice-number {
            font-size: 16px;
            color: rgba(255,255,255,0.9);
            font-weight: 500;
        }
        .section {
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .section-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 3px solid #667eea;
            color: #1f2937;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .client-info, .reservation-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .info-item {
            margin-bottom: 8px;
        }
        .info-label {
            font-weight: bold;
            color: #666;
            display: inline-block;
            width: 120px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        th {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            color: white;
        }
        tbody tr {
            transition: background-color 0.2s;
        }
        tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        tbody tr:hover {
            background-color: #f3f4f6;
        }
        tbody tr:last-child td {
            border-bottom: none;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .total-section {
            margin-top: 30px;
            padding: 25px;
            background: linear-gradient(135deg, #f9fafb 0%, #ffffff 100%);
            border-radius: 12px;
            border: 2px solid #e5e7eb;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-size: 14px;
            color: #4b5563;
        }
        .total-row.grand-total {
            font-size: 22px;
            font-weight: bold;
            padding: 20px 0;
            border-top: 3px solid #667eea;
            margin-top: 15px;
            color: #1f2937;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .status-badge {
            display: inline-block;
            padding: 8px 18px;
            border-radius: 25px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .status-paid {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        .status-unpaid {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }
        .footer {
            margin-top: 50px;
            padding: 25px;
            padding-bottom: 100px; /* Espace pour le tampon PAYÉ */
            border-top: 3px solid #667eea;
            text-align: center;
            font-size: 11px;
            color: #6b7280;
            background: linear-gradient(to bottom, #ffffff 0%, #f9fafb 100%);
            border-radius: 8px;
            position: relative;
        }
        .footer p {
            margin-bottom: 8px;
        }
        .info-item {
            margin-bottom: 10px;
            padding: 8px 0;
        }
        .info-label {
            font-weight: 600;
            color: #4b5563;
            display: inline-block;
            width: 130px;
        }
        .info-value {
            color: #1f2937;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="hotel-info">
            <div class="hotel-name">{{ $hotel['name'] }}</div>
            @if(!empty($hotel['address']))
                <div>{{ $hotel['address'] }}</div>
            @endif
            @if(!empty($hotel['phone']))
                <div>Tél: {{ $hotel['phone'] }}</div>
            @endif
            @if(!empty($hotel['email']))
                <div>Email: {{ $hotel['email'] }}</div>
            @endif
        </div>
        <div class="invoice-info">
            <div class="invoice-title">FACTURE</div>
            <div class="invoice-number">N° {{ $invoice->invoice_number }}</div>
            <div style="margin-top: 10px;">
                <span class="status-badge status-{{ $invoice->status }}">
                    {{ $invoice->status === 'paid' ? 'Payée' : 'Non payée' }}
                </span>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Informations Client</div>
        <div class="client-info">
            <div>
            <div class="info-item">
                <span class="info-label">Nom:</span>
                <span class="info-value">{{ $invoice->reservation->user->name ?? 'N/A' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Email:</span>
                <span class="info-value">{{ $invoice->reservation->user->email ?? 'N/A' }}</span>
            </div>
            </div>
            <div>
                <div class="info-item">
                    <span class="info-label">Date d'émission:</span>
                    <span class="info-value">{{ $invoice->issued_date ? \Carbon\Carbon::parse($invoice->issued_date)->format('d/m/Y') : 'N/A' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Date d'échéance:</span>
                    <span class="info-value">{{ $invoice->due_date ? \Carbon\Carbon::parse($invoice->due_date)->format('d/m/Y') : 'N/A' }}</span>
                </div>
            </div>
        </div>
    </div>

    @if($invoice->reservation && $invoice->reservation->chambre)
    <div class="section">
        <div class="section-title">Détails de la Réservation</div>
        <div class="reservation-info">
            <div>
                <div class="info-item">
                    <span class="info-label">Chambre:</span>
                    <span class="info-value">{{ $invoice->reservation->chambre->numero_chambre }} ({{ $invoice->reservation->chambre->type ?? 'Standard' }})</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Date d'arrivée:</span>
                    <span class="info-value">{{ $invoice->reservation->date_arrive ? \Carbon\Carbon::parse($invoice->reservation->date_arrive)->format('d/m/Y') : 'N/A' }}</span>
                </div>
            </div>
            <div>
                <div class="info-item">
                    <span class="info-label">Date de départ:</span>
                    <span class="info-value">{{ $invoice->reservation->date_depart ? \Carbon\Carbon::parse($invoice->reservation->date_depart)->format('d/m/Y') : 'N/A' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Durée:</span>
                    <span class="info-value">{{ $invoice->reservation->dure_reservation ?? 0 }} nuit(s)</span>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="section">
        <div class="section-title">Détail des Prestations</div>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-center">Quantité</th>
                    <th class="text-right">Prix unitaire</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoice->items ?? [] as $item)
                <tr>
                    <td style="font-weight: 500;">{{ $item->description ?? 'N/A' }}</td>
                    <td class="text-center" style="font-weight: 500;">{{ $item->quantity ?? 0 }}</td>
                    <td class="text-right" style="font-weight: 500;">{{ number_format($item->unit_price ?? 0, 0, ',', ' ') }} XOF</td>
                    <td class="text-right" style="font-weight: 600; color: #667eea;">{{ number_format($item->line_total ?? 0, 0, ',', ' ') }} XOF</td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="text-align: center; padding: 20px; color: #6b7280;">Aucune prestation enregistrée</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="total-section">
        <div class="total-row">
            <span>Sous-total:</span>
            <span>{{ number_format($invoice->total_amount, 0, ',', ' ') }} XOF</span>
        </div>
        <div class="total-row">
            <span>TVA:</span>
            <span>0 XOF</span>
        </div>
        <div class="total-row grand-total" style="display: flex; justify-content: space-between;">
            <span>TOTAL TTC:</span>
            <span style="-webkit-text-fill-color: #1f2937; color: #1f2937; font-size: 24px;">{{ number_format($invoice->total_amount, 0, ',', ' ') }} XOF</span>
        </div>
        @if($invoice->status === 'paid' && $invoice->amount_paid > 0)
        <div class="total-row" style="margin-top: 15px; padding: 15px; background: #d1fae5; border-radius: 8px; color: #065f46; font-weight: 600;">
            <span>Montant payé:</span>
            <span>{{ number_format($invoice->amount_paid, 0, ',', ' ') }} XOF</span>
        </div>
        @endif
    </div>

    <div class="footer" style="position: relative; min-height: 200px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-end;">
            <div style="flex: 1;">
                <p style="font-size: 13px; font-weight: 600; color: #667eea; margin-bottom: 10px;">Merci de votre confiance !</p>
                <p>Cette facture a été générée automatiquement le {{ \Carbon\Carbon::now()->format('d/m/Y à H:i') }}</p>
                <p style="margin-top: 10px; font-style: italic;">Pour toute question, n'hésitez pas à nous contacter.</p>
            </div>
            
            @if($invoice->status === 'paid' && isset($paid_stamp_path) && $paid_stamp_path)
            @php
                $dataUri = null;
                try {
                    if (file_exists($paid_stamp_path)) {
                        $imageData = base64_encode(file_get_contents($paid_stamp_path));
                        $imageInfo = @getimagesize($paid_stamp_path);
                        if ($imageInfo && isset($imageInfo['mime'])) {
                            $mimeType = $imageInfo['mime'];
                        } else {
                            // Détecter le type depuis l'extension si getimagesize échoue
                            $ext = strtolower(pathinfo($paid_stamp_path, PATHINFO_EXTENSION));
                            $mimeType = match($ext) {
                                'jpg', 'jpeg' => 'image/jpeg',
                                'png' => 'image/png',
                                'gif' => 'image/gif',
                                'webp' => 'image/webp',
                                default => 'image/png'
                            };
                        }
                        $dataUri = 'data:' . $mimeType . ';base64,' . $imageData;
                    }
                } catch (\Exception $e) {
                    // En cas d'erreur, ne pas afficher le tampon pour ne pas bloquer la génération du PDF
                    \Log::warning('Erreur lors du chargement du tampon PAYÉ', ['error' => $e->getMessage(), 'path' => $paid_stamp_path]);
                }
            @endphp
            @if($dataUri)
            <div style="flex-shrink: 0; margin-left: 40px; opacity: 0.75; width: 160px; height: 120px;">
                <img src="{{ $dataUri }}" alt="PAYÉ" style="max-width: 160px; max-height: 120px; width: auto; height: auto; display: block;" />
            </div>
            @endif
            @endif
        </div>
    </div>
</body>
</html>

