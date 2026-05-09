<!DOCTYPE html>
<html>
<head>
    <title>Relatório de Inatividade</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #333; }
        .header { text-align: center; margin-bottom: 25px; border-bottom: 2px solid #4f46e5; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #f8fafc; color: #64748b; text-align: left; padding: 8px; border-bottom: 1px solid #e2e8f0; text-transform: uppercase; font-size: 10px; }
        td { padding: 8px; border-bottom: 1px solid #f1f5f9; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $title }}</h1>
        <p>Relatório Gerencial | Gerado em: {{ $date }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Colaborador</th>
                <th>Cadastrado em</th>
                <th>Último Trabalho</th>
                <th class="text-right">Dias sem atividade</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data as $collab)
                <tr>
                    <td class="bold">{{ $collab['name'] }}</td>
                    <td>{{ $collab['created_at_fmt'] }}</td>
                    <td>{{ $collab['last_date'] }}</td>
                    <td class="text-right">
                        <span class="bold">{{ $collab['days_count'] }}</span> 
                        {{ is_numeric($collab['days_count']) ? 'dias' : '' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center; padding: 30px;">
                        Nenhum colaborador encontrado para os critérios selecionados.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>