<!DOCTYPE html>
<html>
<head>
    <title>Relatório de Inatividade</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; color: #333; }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #4f46e5; padding-bottom: 10px; }
        .header h1 { font-size: 18px; margin: 0 0 5px 0; color: #1e1b4b; }
        .header p { margin: 2px 0; color: #64748b; }
        .filters { font-size: 9px; color: #64748b; margin-bottom: 15px; background: #f8fafc; padding: 8px; border-radius: 4px; border: 1px solid #e2e8f0; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #f8fafc; color: #64748b; text-align: left; padding: 6px 8px; border-bottom: 2px solid #e2e8f0; text-transform: uppercase; font-size: 9px; }
        td { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        
        /* Destaques de Licença / Afastamento */
        .row-future { background-color: #f8d7da !important; color: #721c24 !important; }
        .row-recent { background-color: #fff3cd !important; color: #856404 !important; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $title }}</h1>
        <p>Relatório Gerencial | Gerado em: {{ $date }}</p>
    </div>

    <div class="filters">
        <strong>Filtros aplicados:</strong> <br>
        Grupos: {{ $filterGroup }} | Cidades: {{ $filterCity }} | Clínicas: {{ $filterClinic }}
    </div>

    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr>
                <th style="width: 22%;">Colaborador</th>
                <th style="width: 15%;">Grupo</th>
                <th style="width: 15%;">WhatsApp/Celular</th>
                <th style="width: 13%;">Cidade(s)</th>
                <th style="width: 10%;">Cadastro</th>
                <th style="width: 12%;">Última Atividade</th>
                <th style="width: 8%;">Inativo</th>
                <th style="width: 10%;">Fim Licença</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $item)
            @php
                $rowClass = '';
                if ($item['leave_status'] === 'future') {
                    $rowClass = 'row-future';
                } elseif ($item['leave_status'] === 'recent_expired') {
                    $rowClass = 'row-recent';
                }
            @endphp
            <tr class="{{ $rowClass }}">
                <td>{{ $item['name'] }}</td>
                <td class="bold" style="color: #4f46e5;">{{ $item['group'] }}</td>
                <td>{{ $item['mobile'] }}</td>
                <td>{{ $item['city'] }}</td>
                <td>{{ $item['created_at_fmt'] }}</td>
                <td>{{ $item['last_date'] }}</td>
                <td class="bold" style="color: #dc3545;">
                    {{ is_numeric($item['days_count']) ? $item['days_count'] . ' dias' : $item['days_count'] }}
                </td>
                <td class="bold">
                    {{ $item['leave_end_date_fmt'] }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>