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

<table style="width: 100%; border-collapse: collapse; font-size: 12px;">
    <thead>
        <tr style="background-color: #f2f2f2;">
            <th style="padding: 8px; border: 1px solid #ddd;">Colaborador</th>
            <th style="padding: 8px; border: 1px solid #ddd;">WhatsApp/Celular</th>
            <th style="padding: 8px; border: 1px solid #ddd;">Cidade(s)</th>
            <th style="padding: 8px; border: 1px solid #ddd;">Cadastro</th>
            <th style="padding: 8px; border: 1px solid #ddd;">Última Atividade</th>
            <th style="padding: 8px; border: 1px solid #ddd;">Dias Inativo</th>
        </tr>
    </thead>
    <tbody>
        @foreach($data as $item)
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd;">{{ $item['name'] }}</td>
            <td style="padding: 8px; border: 1px solid #ddd;">{{ $item['mobile'] }}</td>
            <td style="padding: 8px; border: 1px solid #ddd;">{{ $item['city'] }}</td>
            <td style="padding: 8px; border: 1px solid #ddd;">{{ $item['created_at_fmt'] }}</td>
            <td style="padding: 8px; border: 1px solid #ddd;">{{ $item['last_date'] }}</td>
            <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;">{{ $item['days_count'] }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>