<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Relatório de Uniformes Pendentes</title>
    <style>
        body {
            font-family: 'Helvetica', Arial, sans-serif;
            font-size: 10pt;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }

        .header {
            background-color: #1e3a8a;
            color: #ffffff;
            padding: 15px 20px;
            margin-bottom: 20px;
        }

        .header h2 {
            margin: 0;
            font-size: 16pt;
        }

        .meta {
            font-size: 8.5pt;
            color: #cbd5e1;
            margin-top: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th,
        td {
            border: 1px solid #e2e8f0;
            padding: 8px 10px;
            text-align: left;
        }

        th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 8.5pt;
            text-transform: uppercase;
        }

        .text-center {
            text-align: center;
        }

        .badge {
            display: inline-block;
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 8pt;
            font-weight: bold;
        }

        .badge-danger {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .badge-info {
            background-color: #e0e7ff;
            color: #3730a3;
        }

        .badge-secondary {
            background-color: #f1f5f9;
            color: #475569;
        }

        /* Destaques de inatividade por diárias */
        tr.bg-warning td {
            background-color: #fef08a !important;
            /* Amarelo: > 7 dias sem diária */
            color: #854d0e;
        }

        tr.bg-danger td {
            background-color: #fca5a5 !important;
            /* Vermelho: > 20 dias sem diária */
            color: #991b1b;
        }
    </style>
</head>

<body>

    <div class="header">
        <h2>Relatório de Uniformes Pendentes</h2>
        <div class="meta">
            Gerado em: {{ $generatedAt }} | Solicitante: {{ $user->name ?? 'Sistema' }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Colaborador</th>
                <th class="text-center">Diárias</th>
                <th class="text-center">Direito</th>
                <th class="text-center">Entregues</th>
                <th class="text-center">Pendente</th>
                <th class="text-center">Tamanho</th>
                <th>Tipo de Uniforme</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pending as $collab)
                @php
                    // Obtém a data da última diária e calcula os dias transcorridos
                    $lastDaily = isset($collab->last_daily_at) ? \Carbon\Carbon::parse($collab->last_daily_at) : null;
                    $daysSinceLastDaily = $lastDaily ? (int) $lastDaily->diffInDays(now()) : null;

                    // Define a classe da linha com base na inatividade
                    $rowClass = '';
                    if (!is_null($daysSinceLastDaily)) {
                        if ($daysSinceLastDaily > 20) {
                            $rowClass = 'bg-danger';
                        } elseif ($daysSinceLastDaily > 7) {
                            $rowClass = 'bg-warning';
                        }
                    }
                @endphp
                <tr class="{{ $rowClass }}">
                    <td><strong>{{ $collab->name }}</strong></td>
                    <td class="text-center">{{ $collab->daily_rates_count }}</td>
                    <td class="text-center">{{ $collab->uniforms_entitled }}</td>
                    <td class="text-center">{{ $collab->uniforms_delivered }}</td>
                    <td class="text-center">
                        <span class="badge badge-danger">{{ $collab->pending_qty }} un</span>
                    </td>
                    <td class="text-center">
                        <span class="badge badge-info">{{ $collab->uniform_size ?? 'N/I' }}</span>
                    </td>
                    <td>
                        <span class="badge badge-secondary">{{ $collab->kit_type ?? 'Kit Padrão' }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">Nenhuma pendência encontrada.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>

</html>
