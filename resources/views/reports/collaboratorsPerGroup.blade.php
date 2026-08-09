<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Colaboradores</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; }
        h2 { text-align: center; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .footer { margin-top: 30px; font-size: 10px; text-align: right; color: #777; }
    </style>
</head>
<body>
    <h2>Relatório de Colaboradores</h2>

    <table>
        <thead>
            <tr>
                <th>Nome</th>
                <th>Grupo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($collaborators as $collaborator)
                <tr>
                    <td>{{ $collaborator->name }}</td>
                    <td>{{ $collaborator->group ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" style="text-align: center;">Nenhum colaborador encontrado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Gerado em {{ date('d/m/Y H:i') }}
    </div>
</body>
</html>