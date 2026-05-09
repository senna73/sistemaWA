<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <div class="container-fluid py-5 bg-light min-vh-100">
        <div class="container">
            
            <!-- Header -->
            <div class="mb-5">
                <p class="text-primary fw-bold text-uppercase small mb-1" style="letter-spacing: 1px;">Financeiro</p>
                <h2 class="fw-black text-dark m-0">Performance & Analytics</h2>
            </div>

            <!-- Seção do Gráfico -->
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                    <h5 class="fw-bold text-dark m-0">Atividade Diária</h5>
                    
                    <!-- Seleção de Meses -->
                    <div class="bg-light p-1 rounded-3 d-inline-flex border">
                        @foreach([1, 2, 3, 6, 12, 'all'] as $m)
                            <a href="{{ route('analytics.index', ['months' => $m]) }}" 
                               class="btn btn-sm {{ (request('months', 1) == $m) ? 'btn-white shadow-sm fw-bold' : 'text-muted' }} px-3 py-1 border-0" 
                               style="font-size: 11px; min-width: 45px;">
                                {{ strtoupper($m) }}{{ is_numeric($m) ? 'M' : '' }}
                            </a>
                        @endforeach
                    </div>
                </div>

                <div id="engagementChart"></div>
                
                <div class="d-flex justify-content-center gap-4 mt-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-primary" style="width: 10px; height: 10px;"></div>
                        <small class="text-muted fw-semibold">Colaboradores Trabalhando</small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-danger" style="width: 10px; height: 10px;"></div>
                        <small class="text-muted fw-semibold">Colaboradores Ociosos</small>
                    </div>
                </div>
            </div>

            <!-- Cards de Métricas de Retenção -->
            <div class="row g-4 mb-5">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                        <div class="card-body p-4 text-center">
                            <p class="text-muted fw-bold text-uppercase small mb-2">Total de Colaboradores</p>
                            <h2 class="fw-black m-0 text-dark">{{ $totalCollaborators }}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                        <div class="card-body p-4 text-center border-bottom border-success border-4 rounded-bottom-4">
                            <p class="text-success fw-bold text-uppercase small mb-2">Ativos (Últimos 45 dias)</p>
                            <h2 class="fw-black m-0">{{ $countAtivos45 }}</h2>
                            <span class="text-muted small fw-semibold">{{ number_format($percentAtivos45, 1) }}% do total</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                        <div class="card-body p-4 text-center border-bottom border-danger border-4 rounded-bottom-4">
                            <p class="text-danger fw-bold text-uppercase small mb-2">Inativos (+45 dias)</p>
                            <h2 class="fw-black m-0">{{ $countInativos45 }}</h2>
                            <span class="text-muted small fw-semibold">{{ number_format($percentInativos45, 1) }}% do total</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botões de Exportação -->
            <div class="row">
                <div class="col-12">
                    <div class="card border-0 bg-dark rounded-4 p-4 shadow-lg">
                        <div class="row align-items-center">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <h5 class="text-white fw-bold m-0">Relatórios de Gestão</h5>
                                <p class="text-white-50 small m-0">Selecione o segmento para exportação em PDF</p>
                            </div>
                            <div class="col-md-8">
                                <div class="d-grid d-md-flex justify-content-md-end gap-3">
                                    <a href="{{ route('analytics.pdf', ['type' => 'long_term']) }}" 
                                       class="btn btn-outline-light px-4 py-2 rounded-3 fw-semibold btn-sm d-flex align-items-center justify-content-center gap-2">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        Inativos (+45 dias)
                                    </a>
                                    <a href="{{ route('analytics.pdf', ['type' => 'new_inactive']) }}" 
                                       class="btn btn-outline-light px-4 py-2 rounded-3 fw-semibold btn-sm d-flex align-items-center justify-content-center gap-2">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                                        Novos Inativos
                                    </a>
                                    <a href="{{ route('analytics.pdf', ['type' => 'warning']) }}" 
                                       class="btn btn-outline-light px-4 py-2 rounded-3 fw-semibold btn-sm d-flex align-items-center justify-content-center gap-2">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        Alerta (15-45 dias)
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <style>
        .fw-black { font-weight: 900; }
        .btn-white { background-color: #fff; border-color: #dee2e6; color: #212529; }
        .btn-outline-light:hover { background-color: rgba(255,255,255,0.1); color: #fff; }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var options = {
                series: [
                    { name: 'Trabalhando', data: @json($chartActive) }, 
                    { name: 'Ociosos', data: @json($chartInactive) }
                ],
                chart: { 
                    type: 'area', 
                    height: 380, 
                    toolbar: { show: false },
                    fontFamily: 'Inter, sans-serif',
                    zoom: { enabled: false }
                },
                colors: ['#0d6efd', '#dc3545'],
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.3,
                        opacityTo: 0.05,
                        stops: [0, 90, 100]
                    }
                },
                stroke: { curve: 'smooth', width: 2.5 },
                xaxis: { 
                    categories: @json($chartLabels),
                    axisBorder: { show: false },
                    labels: { style: { colors: '#94a3b8' } }
                },
                yaxis: {
                    labels: { style: { colors: '#94a3b8' } }
                },
                grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
                dataLabels: { enabled: false },
                tooltip: { theme: 'light', x: { show: true } }
            };
            new ApexCharts(document.querySelector("#engagementChart"), options).render();
        });
    </script>
</x-app-layout>