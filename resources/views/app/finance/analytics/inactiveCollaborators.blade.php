<!DOCTYPE html>
<html>
<head>
    <title>Relatório de Inatividade</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #4f46e5; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background-color: #f8fafc; color: #64748b; text-align: left; padding: 10px; border-bottom: 1px solid #e2e8f0; }
        td { padding: 10px; border-bottom: 1px solid #f1f5f9; }
        .badge { color: #ef4444; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $title }}</h1>
        <p>Período de Análise: Últimos {{ $months }} {{ $months > 1 ? 'Meses' : 'Mês' }} | Gerado em: {{ $date }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Colaborador</th>
                <th>Último Trabalho</th>
                <th style="text-align: right;">Inatividade</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $collab)
                <tr>
                    <td><strong>{{ $collab['name'] }}</strong></td>
                    <td>{{ $collab['last_date'] }}</td>
                    <td class="badge" style="text-align: right;">
                        {{ $collab['days_count'] }} {{ is_numeric($collab['days_count']) ? 'dias' : '' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>