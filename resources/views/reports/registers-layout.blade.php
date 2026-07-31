<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Diária e Registros</title>
    <style>
        .report-header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #cbd5e1;
            padding-bottom: 12px;
        }

        .report-header h1 {
            color: #1e293b;
            font-size: 22px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 6px 0;
            border-bottom: none;
            padding-bottom: 0;
        }

        .period-badge {
            display: inline-block;
            font-size: 12px;
            color: #475569;
            background-color: #f1f5f9;
            padding: 4px 12px;
            border-radius: 4px;
            border: 1px solid #cbd5e1;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #2b2b2b;
            margin: 15px;
            padding: 0;
            background-color: #ffffff;
        }

        h1 {
            text-align: center;
            color: #1e293b;
            font-size: 22px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 20px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e2e8f0;
        }

        /* Card da Empresa */
        .info {
            margin-bottom: 12px;
            padding: 10px 15px;
            background: #f8fafc;
            border-radius: 4px;
            border-left: 4px solid #334155;
            text-align: left;
            font-size: 16px;
            font-weight: bold;
            color: #0f172a;
        }

        .table-container {
            margin-top: 15px;
        }

        /* Subtítulo do Setor */
        .sector-title {
            font-size: 13px;
            font-weight: bold;
            color: #334155;
            margin-top: 12px;
            padding: 6px 10px;
            background-color: #f1f5f9;
            border-radius: 4px 4px 0 0;
            border: 1px solid #cbd5e1;
            border-bottom: none;
            text-transform: uppercase;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            margin-bottom: 6px;
        }

        th,
        td {
            padding: 8px 10px;
            text-align: center;
            font-size: 12px;
            border: 1px solid #e2e8f0;
        }

        th {
            background-color: #334155;
            color: #ffffff;
            text-transform: uppercase;
            font-weight: 600;
            font-size: 11px;
            letter-spacing: 0.5px;
        }

        tr:nth-child(even) {
            background-color: #f8fafc;
        }

        /* Resumos por Setor e Empresa */
        .sector-summary {
            font-size: 12px;
            font-weight: bold;
            text-align: right;
            padding: 8px 12px;
            background-color: #f1f5f9;
            color: #1e293b;
            border: 1px solid #cbd5e1;
            border-top: none;
            border-radius: 0 0 4px 4px;
        }

        .company-summary {
            font-size: 13px;
            font-weight: bold;
            text-align: right;
            padding: 10px 12px;
            background-color: #e2e8f0;
            color: #0f172a;
            border-radius: 4px;
            margin-top: 10px;
            border: 1px solid #cbd5e1;
        }

        .company-summary p {
            margin: 2px 0;
        }

        /* Seção sobriedade para colaboradores sem registro */
        .no-records-section {
            margin-top: 20px;
            padding: 12px;
            background-color: #fefce8;
            border: 1px solid #fef08a;
            border-radius: 4px;
        }

        .no-records-title {
            font-size: 12px;
            font-weight: bold;
            color: #854d0e;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .no-records-table {
            border: 1px solid #fef08a;
        }

        .no-records-table th {
            background-color: #ca8a04;
            color: #ffffff;
        }

        .no-records-table td {
            color: #713f12;
            background-color: #ffffff;
        }

        .footer {
            margin-top: 20px;
            text-align: right;
            font-size: 13px;
            color: #334155;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
        }

        .company-break {
            page-break-after: always;
        }

        /* Estilos do Rodapé de Comprovante de Ponto */
        .footer-container {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 2px solid #cbd5e1;
            page-break-inside: avoid;
            /* Evita que o rodapé quebre ao meio entre páginas */
        }

        .company-legal-info {
            text-align: center;
            font-size: 10px;
            color: #475569;
            margin-bottom: 12px;
            line-height: 1.4;
        }

        .totals-summary {
            text-align: right;
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
            background-color: #f1f5f9;
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #cbd5e1;
            margin-bottom: 15px;
        }

        .declaration-text {
            font-size: 9px;
            color: #64748b;
            text-align: justify;
            margin-bottom: 25px;
            font-style: italic;
        }

        /* Blocos de Assinatura Side-by-Side para Dompdf */
        .signatures {
            width: 100%;
            margin-top: 20px;
            margin-bottom: 15px;
        }

        .signature-box {
            width: 45%;
            float: left;
            text-align: center;
            font-size: 10px;
            color: #334155;
        }

        .signature-box:last-child {
            float: right;
        }

        .signature-box .line {
            border-bottom: 1px solid #334155;
            margin-bottom: 5px;
            height: 35px;
            /* Espaço para assinatura física */
        }

        .generation-info {
            clear: both;
            text-align: center;
            font-size: 8px;
            color: #94a3b8;
            padding-top: 15px;
        }
    </style>
    <div class="report-header">
        <h1>Relatório de Diária</h1>
        <div class="period-badge">
            @if ($periodoStart && $periodoEnd)
                <strong>Período:</strong> {{ $periodoStart }} até {{ $periodoEnd }}
            @elseif($periodoStart)
                <strong>A partir de:</strong> {{ $periodoStart }}
            @elseif($periodoEnd)
                <strong>Até:</strong> {{ $periodoEnd }}
            @else
                <strong>Período:</strong> Todos os registros
            @endif
        </div>
    </div>

</head>

<body>
    <h1>Relatório de Diária</h1>

    @php($totalGeralDiarias = 0)
    @php($totalGeralMinutos = 0)

    @foreach ($dailyRate as $collaboratorId => $rates)
        @php($companyName = $rates[0]['company_name'] ?? 'Não informado')

        <div class="info">
            {{ $companyName }}
        </div>

        @php($groupedBySector = $rates->groupBy('section_name'))
        @php($totalDiariasEmpresa = 0)
        @php($totalMinutosEmpresa = 0)

        @foreach ($groupedBySector as $sectorName => $sectorRates)
            @php($isHourly = $sectorRates->whereNotNull('end')->isNotEmpty())

            <div class="table-container">
                <div class="sector-title">{{ $sectorName }}</div>
                <table>
                    <thead>
                        <tr>
                            <th>CPF / Doc.</th>
                            <th>Nome do Colaborador</th>
                            <th>Data de Início</th>
                            @if ($isHourly)
                                <th>Data de Saída</th>
                                <th>Tempo Total</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @php($totalForSectorDiarias = 0)
                        @php($totalForSectorMinutos = 0)

                        @foreach ($sectorRates as $rate)
                            @if ($isHourly)
                                @php($minutos = \Carbon\Carbon::parse($rate->start)->diffInMinutes(\Carbon\Carbon::parse($rate->end)))
                                @php($totalForSectorMinutos += $minutos)
                                <tr>
                                    <td>{{ $rate->document ?? 'N/A' }}</td>
                                    <td>{{ $rate->collaborators_name }}</td>
                                    <td>{{ \Carbon\Carbon::parse($rate->start)->format('d/m/Y H:i:s') }}</td>
                                    <td>{{ \Carbon\Carbon::parse($rate->end)->format('d/m/Y H:i:s') }}</td>
                                    <td>{{ floor($minutos / 60) }}:{{ sprintf('%02d', $minutos % 60) }}</td>
                                </tr>
                            @else
                                @php($totalForSectorDiarias += 1)
                                <tr>
                                    <td>{{ $rate->document ?? 'N/A' }}</td>
                                    <td>{{ $rate->collaborators_name }}</td>
                                    <td>{{ \Carbon\Carbon::parse($rate->start)->format('d/m/Y H:i:s') }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>

                <div class="sector-summary">
                    @if ($isHourly)
                        Total de Horas no Setor {{ $sectorName }}:
                        {{ floor($totalForSectorMinutos / 60) }}:{{ sprintf('%02d', $totalForSectorMinutos % 60) }}
                    @else
                        Total de Diárias no Setor {{ $sectorName }}: {{ $totalForSectorDiarias }}
                    @endif
                </div>

                @php($totalDiariasEmpresa += $totalForSectorDiarias)
                @php($totalMinutosEmpresa += $totalForSectorMinutos)
            </div>
        @endforeach

        <div class="company-summary">
            @if ($totalMinutosEmpresa > 0)
                <p>Total de Horas na Empresa {{ $companyName }}:
                    {{ floor($totalMinutosEmpresa / 60) }}:{{ sprintf('%02d', $totalMinutosEmpresa % 60) }}
                </p>
            @endif
            @if ($totalDiariasEmpresa > 0)
                <p>Total de Diárias na Empresa {{ $companyName }}: {{ $totalDiariasEmpresa }}</p>
            @endif
        </div>

        @php($totalGeralDiarias += $totalDiariasEmpresa)
        @php($totalGeralMinutos += $totalMinutosEmpresa)

        @if (!$loop->last)
            <div class="company-break"></div>
        @endif
    @endforeach

    {{-- Quadro de Colaboradores Selecionados Sem Registro --}}
    @if (isset($collaboratorsWithoutRecords) && $collaboratorsWithoutRecords->isNotEmpty())
        <div class="no-records-section">
            <div class="no-records-title">Colaboradores sem Prestação de Serviços / Diárias no Período</div>
            <table class="no-records-table">
                <thead>
                    <tr>
                        <th>CPF / Doc.</th>
                        <th>Nome do Colaborador</th>
                        <th>Observação / Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($collaboratorsWithoutRecords as $collaborator)
                        <tr>
                            <td>{{ $collaborator->document ?? 'N/A' }}</td>
                            <td>{{ $collaborator->name }}</td>
                            <td style="color: #721c24; font-weight: bold;">Sem convocação ou prestação de serviço
                                registrada no período selecionado.</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="footer">
        @if ($totalGeralMinutos > 0)
            <p><strong>Total Geral de Horas: </strong>
                {{ floor($totalGeralMinutos / 60) }}:{{ sprintf('%02d', $totalGeralMinutos % 60) }}
            </p>
        @endif
        @if ($totalGeralDiarias > 0)
            <p><strong>Total Geral de Diárias: </strong> {{ $totalGeralDiarias }}</p>
        @endif
    </div>
    <!-- Rodapé de Validação e Assinaturas (Comprovante de Ponto) -->
    <div class="footer-container">
        <!-- Dados da Empresa (CNPJ) -->
        <div class="company-legal-info">
            <strong>WA MERCHANDISING E SERVICOS LTDA</strong> &bull; CNPJ: 53.659.646/0001-41<br>
            Rua João Deola, 150, Apt 101 Bloco 13, Progresso &bull; Blumenau/SC &bull; CEP: 89.027-350<br>
            Contato: (47) 9213-3429 &bull; cm.contabilidade2050@gmail.com
        </div>

        <!-- Resumo das Horas / Diárias -->
        <div class="totals-summary">
            @if ($totalGeralMinutos > 0)
                <span><strong>TOTAL GERAL DE HORAS:</strong>
                    {{ floor($totalGeralMinutos / 60) }}:{{ sprintf('%02d', $totalGeralMinutos % 60) }}</span>
            @endif
            @if ($totalGeralDiarias > 0)
                <span style="margin-left: 15px;"><strong>TOTAL GERAL DE DIÁRIAS:</strong>
                    {{ $totalGeralDiarias }}</span>
            @endif
        </div>

        <!-- Termo de Declaração -->
        <p class="declaration-text">
            Declaro para os devidos fins que os registros de horários e diárias descritos neste documento correspondem à
            efetiva prestação de serviços realizada no período informado.
        </p>

        <!-- Campo para Assinaturas
        <div class="signatures">
            <div class="signature-box">
                <div class="line"></div>
                <strong>WA MERCHANDISING E SERVICOS LTDA</strong><br>
                Empregador / Contratante
            </div>
            <div class="signature-box">
                <div class="line"></div>
                <strong>Assinatura do Colaborador</strong><br>
                CPF / Documento Registrado
            </div>
        </div>-->

        <!-- Emissão de Controle -->
        <div class="generation-info">
            Documento emitido em {{ date('d/m/Y \à\s H:i:s') }} pelo usuário {{ $user->name ?? 'Sistema' }}.
        </div>
    </div>
</body>

</html>
