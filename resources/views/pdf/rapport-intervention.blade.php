<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Rapport d'intervention #{{ $intervention->id }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1f2937; }
        .header { border-bottom: 3px solid #0f766e; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { font-size: 18px; margin: 0 0 4px 0; color: #0f766e; }
        .header p { margin: 0; color: #6b7280; font-size: 11px; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .meta td { padding: 5px 8px; border: 1px solid #e5e7eb; vertical-align: top; }
        .meta td.label { width: 30%; font-weight: bold; background: #f9fafb; color: #374151; }
        .section-title { font-size: 13px; font-weight: bold; color: #0f766e; margin: 18px 0 8px 0; border-bottom: 1px solid #d1d5db; padding-bottom: 4px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: bold; background: #f3f4f6; color: #374151; }
        table.pieces { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.pieces th { background: #f0fdfa; color: #0f766e; text-align: left; padding: 6px 8px; border: 1px solid #e5e7eb; font-size: 11px; }
        table.pieces td { padding: 6px 8px; border: 1px solid #e5e7eb; font-size: 11px; }
        table.pieces td.num, table.pieces th.num { text-align: right; }
        .totaux { width: 50%; margin-left: auto; margin-top: 10px; border-collapse: collapse; }
        .totaux td { padding: 5px 8px; font-size: 12px; }
        .totaux .label { text-align: right; color: #6b7280; }
        .totaux .valeur { text-align: right; width: 100px; font-weight: bold; }
        .totaux .grand-total td { border-top: 2px solid #0f766e; font-size: 14px; color: #0f766e; }
        .description { white-space: pre-line; padding: 8px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; min-height: 30px; }
        .footer { margin-top: 30px; font-size: 9px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Rapport d'intervention #{{ $intervention->id }}</h1>
        <p>Maintenance Équipement Industriel — généré le {{ now()->format('d/m/Y à H:i') }}</p>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Équipement</td>
            <td>{{ $equipement?->code }} — {{ $equipementLabel }}</td>
            <td class="label">Type</td>
            <td><span class="badge">{{ $typeLabel }}</span></td>
        </tr>
        <tr>
            <td class="label">Statut</td>
            <td><span class="badge">{{ $statutLabel }}</span></td>
            <td class="label">Priorité</td>
            <td>{{ $prioriteLabel }}</td>
        </tr>
        <tr>
            <td class="label">Date planifiée</td>
            <td>{{ optional($intervention->date_planifiee)->format('d/m/Y H:i') ?? '—' }}</td>
            <td class="label">Date de fin</td>
            <td>{{ optional($intervention->date_fin)->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Technicien</td>
            <td>{{ $intervention->technicien?->name ?? '—' }}</td>
            <td class="label">Durée (h)</td>
            <td>{{ $intervention->duree_heures ?? '—' }}</td>
        </tr>
        @if($intervention->planMaintenance)
        <tr>
            <td class="label">Plan préventif d'origine</td>
            <td colspan="3">{{ $intervention->planMaintenance->operation }}</td>
        </tr>
        @endif
    </table>

    <div class="section-title">Description</div>
    <div class="description">{{ $intervention->description ?: '—' }}</div>

    @if($intervention->notes)
    <div class="section-title">Notes</div>
    <div class="description">{{ $intervention->notes }}</div>
    @endif

    <div class="section-title">Pièces utilisées</div>
    @if($intervention->pieces->isEmpty())
        <p style="color:#6b7280;">Aucune pièce consommée pour cette intervention.</p>
    @else
        <table class="pieces">
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Désignation</th>
                    <th class="num">Quantité</th>
                    <th class="num">Prix unitaire</th>
                    <th class="num">Sous-total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($intervention->pieces as $piece)
                <tr>
                    <td>{{ $piece->reference }}</td>
                    <td>{{ $piece->designation }}</td>
                    <td class="num">{{ $piece->pivot->quantite }}</td>
                    <td class="num">{{ number_format($piece->pivot->prix_unitaire, 2, ',', ' ') }} {{ $devise }}</td>
                    <td class="num">{{ number_format($piece->pivot->quantite * $piece->pivot->prix_unitaire, 2, ',', ' ') }} {{ $devise }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <table class="totaux">
        <tr>
            <td class="label">Main d'œuvre</td>
            <td class="valeur">{{ number_format($intervention->cout_main_oeuvre ?? 0, 2, ',', ' ') }} {{ $devise }}</td>
        </tr>
        <tr>
            <td class="label">Pièces</td>
            <td class="valeur">{{ number_format($coutPieces, 2, ',', ' ') }} {{ $devise }}</td>
        </tr>
        <tr class="grand-total">
            <td class="label">Total</td>
            <td class="valeur">{{ number_format($coutTotal, 2, ',', ' ') }} {{ $devise }}</td>
        </tr>
    </table>

    <div class="footer">
        Document généré automatiquement — Maintenance Équipement Industriel
    </div>
</body>
</html>
