<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 12px; color: #333; }
        .header { margin-bottom: 20px; border-bottom: 2px solid #0d6efd; padding-bottom: 10px; }
        .title { font-size: 18px; font-weight: bold; color: #111; }
        .meta { font-size: 10px; color: #666; margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background-color: #f8f9fa; color: #495057; font-weight: bold; text-align: left; padding: 8px; border-bottom: 1px solid #dee2e6; text-transform: uppercase; font-size: 10px; }
        td { padding: 8px; border-bottom: 1px solid #eee; vertical-align: middle; }
        .badge-count { background-color: #e2f0d9; color: #385723; padding: 3px 8px; border-radius: 4px; font-weight: bold; display: inline-block; }
        .text-right { text-align: right; }
    </style>
</head>
<body>

    <div class="header">
        <div class="title">{{ $title }}</div>
        <div class="meta">
            <strong>Unidades:</strong> {{ $filterCity }} <br>
            <strong>Grupo(s):</strong> {{ $filterGroup }} <br>
            <strong>Gerado em:</strong> {{ $date }} por {{ $user ? $user->name : 'Sistema' }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Colaborador</th>
                <th>Grupo</th>
                <th>Celular</th>
                <th>Cidade(s)</th>
                <th>Data Cadastro</th>
                <th class="text-right">Diárias no Período</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data as $row)
                <tr>
                    <td><strong>{{ $row['name'] }}</strong></td>
                    <td>{{ $row['group'] }}</td>
                    <td>{{ $row['mobile'] }}</td>
                    <td>{{ $row['city'] }}</td>
                    <td>{{ $row['created_at_fmt'] }}</td>
                    <td class="text-right"><span class="badge-count">{{ $row['daily_rates_count'] }}</span></td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; padding: 20px; color: #888;">
                        Nenhum colaborador com atividade registrada neste período.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>