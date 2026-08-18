<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Colaboradores Ativos</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; }
        h2 { text-align: center; margin-bottom: 20px; }
        
        .group-container { margin-bottom: 25px; }
        .group-header {
            background-color: #1e293b;
            color: #ffffff;
            padding: 8px 10px;
            font-size: 13px;
            font-weight: bold;
            border-radius: 3px;
        }
        .group-total-badge {
            float: right;
            background-color: #3b82f6;
            color: #ffffff;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
        }

        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        th, td { border: 1px solid #ddd; padding: 7px 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        
        .badge {
            display: inline-block;
            padding: 3px 7px;
            font-size: 10px;
            font-weight: bold;
            border-radius: 3px;
            text-align: center;
        }
        .badge-danger  { background-color: #dc2626; color: #ffffff; }
        .badge-warning { background-color: #eab308; color: #000000; }
        .badge-success { background-color: #16a34a; color: #ffffff; }

        .summary-box {
            margin-top: 20px;
            padding: 10px;
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            text-align: right;
            font-size: 13px;
            font-weight: bold;
        }
        .footer { margin-top: 20px; font-size: 10px; text-align: right; color: #777; }
    </style>
</head>
<body>
    <h2>Relatório de Colaboradores Ativos por Grupo</h2>

    @forelse ($groupedCollaborators as $group => $collaborators)
        <div class="group-container">
            <div class="group-header">
                GRUPO: {{ strtoupper($group ?: 'Sem Grupo') }}
                <span class="group-total-badge">Total no Grupo: {{ $collaborators->count() }}</span>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th style="width: 120px;">Última Diária</th>
                        <th style="width: 160px; text-align: center;">Tempo sem Diária</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($collaborators as $collaborator)
                        <tr>
                            <td>{{ $collaborator->name }}</td>
                            <td>
                                {{ $collaborator->last_daily_date ? $collaborator->last_daily_date->format('d/m/Y') : 'Sem registro' }}
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-{{ $collaborator->status_tag }}">
                                    {{ $collaborator->status_label }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <table>
            <tbody>
                <tr>
                    <td style="text-align: center; padding: 15px;">Nenhum colaborador ativo encontrado.</td>
                </tr>
            </tbody>
        </table>
    @endforelse

    @if($groupedCollaborators->isNotEmpty())
        <div class="summary-box">
            TOTAL DE COLABORADORES ATIVOS: {{ $totalGeneral }}
        </div>
    @endif

    <div class="footer">
        Gerado em {{ date('d/m/Y H:i') }}
    </div>
</body>
</html>