<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <div class="container-fluid py-5 bg-light min-vh-100">
        <div class="container">
            
            <!-- Header -->
            <div class="row align-items-end mb-5">
                <div class="col-md-6">
                    <p class="text-primary fw-bold text-uppercase small mb-1" style="letter-spacing: 1px;">Financeiro</p>
                    <h2 class="fw-black text-dark m-0">Performance & Analytics</h2>
                </div>
                
                <div class="col-md-6 mt-4 mt-md-0">
                    <form id="filterForm" method="GET" action="{{ route('analytics.index') }}" class="row g-2 justify-content-md-end">
                        <input type="hidden" name="months" value="{{ request('months', 1) }}">
                        
                        <div class="col-sm-8">
                            <label class="small fw-bold text-muted mb-1">Filtrar por Unidades (Cidades)</label>
                            <select name="city_ids[]" id="citySelect" class="form-select select2" multiple>
                                <option value="null" {{ in_array('null', (array)request('city_ids')) ? 'selected' : '' }}>
                                    (Sem cidade registrada)
                                </option>
                                @foreach($cities as $city)
                                    <option value="{{ $city->id }}" {{ in_array($city->id, (array)request('city_ids')) ? 'selected' : '' }}>
                                        {{ $city->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-4 d-grid">
                            <label class="d-none d-sm-block mb-1">&nbsp;</label>
                            <button type="submit" class="btn btn-primary fw-bold shadow-sm">
                                Aplicar Filtros
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Seção do Gráfico -->
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">

                    <div class="bg-light p-1 rounded-3 d-inline-flex border">
                        {{-- Botão All Time --}}
                        <a href="{{ route('analytics.index', array_merge(request()->query(), ['months' => -1])) }}" 
                        class="btn btn-sm {{ (request('months', 1) == -1) ? 'btn-white shadow-sm fw-bold' : 'text-muted' }} px-3 py-1 border-0" 
                        style="font-size: 11px; min-width: 45px;">
                            ALL
                        </a>

                        @foreach([1, 2, 3, 6, 12] as $m)
                            <a href="{{ route('analytics.index', array_merge(request()->query(), ['months' => $m])) }}" 
                            class="btn btn-sm {{ (request('months', 1) == $m) ? 'btn-white shadow-sm fw-bold' : 'text-muted' }} px-3 py-1 border-0" 
                            style="font-size: 11px; min-width: 45px;">
                                {{ $m }}M
                            </a>
                        @endforeach
                    </div>
                </div>

                <div id="engagementChart"></div>
                
                <!-- Legenda do Gráfico -->
                <div class="d-flex justify-content-center gap-4 mt-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-primary" style="width: 10px; height: 10px;"></div>
                        <small class="text-muted fw-semibold">Trabalhando</small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-danger" style="width: 10px; height: 10px;"></div>
                        <small class="text-muted fw-semibold">Ociosos</small>
                    </div>
                </div>
            </div>

            <!-- Cards de Métricas -->
            <div class="row g-4 mb-5">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                        <div class="card-body p-4 text-center">
                            <p class="text-muted fw-bold text-uppercase small mb-2">Universo Filtrado</p>
                            <h2 class="fw-black m-0 text-dark">{{ $totalCollaborators }}</h2>
                            <small class="text-muted">Colaboradores Totais</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-white border-bottom border-success border-4">
                        <div class="card-body p-4 text-center">
                            <p class="text-success fw-bold text-uppercase small mb-2">Ativos (Últimos 45 dias)</p>
                            <h2 class="fw-black m-0">{{ $countAtivos45 }}</h2>
                            <span class="text-muted small fw-semibold">{{ number_format($percentAtivos45, 1) }}% do grupo</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-white border-bottom border-danger border-4">
                        <div class="card-body p-4 text-center">
                            <p class="text-danger fw-bold text-uppercase small mb-2">Inativos (+45 dias)</p>
                            <h2 class="fw-black m-0">{{ $countInativos45 }}</h2>
                            <span class="text-muted small fw-semibold">{{ number_format($percentInativos45, 1) }}% do grupo</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Seção de Exportação -->
            <div class="row">
                <div class="col-12">
                    <div class="card border-0 bg-dark rounded-4 p-4 shadow-lg">
                        <div class="row align-items-center">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <h5 class="text-white fw-bold m-0">Relatórios de Gestão</h5>
                                <p class="text-white-50 small m-0">Os PDFs respeitarão os filtros de cidade aplicados acima.</p>
                            </div>
                            <div class="col-md-8">
                                <div class="d-grid d-md-flex justify-content-md-end gap-3">
                                    @php $currentFilters = request()->all(); @endphp
                                    
                                    <a href="{{ route('analytics.pdf', array_merge($currentFilters, ['type' => 'long_term'])) }}" 
                                       class="btn btn-outline-light px-4 py-2 rounded-3 fw-semibold btn-sm d-flex align-items-center justify-content-center gap-2">
                                        Inativos (+45 dias)
                                    </a>
                                    <a href="{{ route('analytics.pdf', array_merge($currentFilters, ['type' => 'new_inactive'])) }}" 
                                       class="btn btn-outline-light px-4 py-2 rounded-3 fw-semibold btn-sm d-flex align-items-center justify-content-center gap-2">
                                        Novos Inativos
                                    </a>
                                    <a href="{{ route('analytics.pdf', array_merge($currentFilters, ['type' => 'warning'])) }}" 
                                       class="btn btn-outline-light px-4 py-2 rounded-3 fw-semibold btn-sm d-flex align-items-center justify-content-center gap-2">
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
        .select2-container--bootstrap-5 .select2-selection { border-radius: 0.5rem; padding: 0.25rem; }
    </style>

    <!-- Scripts: jQuery + Select2 + ApexCharts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <script>
        $(document).ready(function() {
            // Inicializa Select2
            $('#citySelect').select2({
                theme: 'bootstrap-5',
                placeholder: 'Selecione as cidades',
                allowClear: true
            });

            // Configuração do Gráfico
            var options = {
                series: [
                    { name: 'Trabalhando', data: @json($chartActive) }, 
                    { name: 'Ociosos', data: @json($chartInactive) }
                ],
                chart: { 
                    type: 'area', height: 380, toolbar: { show: false },
                    fontFamily: 'Inter, sans-serif', zoom: { enabled: false }
                },
                colors: ['#0d6efd', '#dc3545'],
                fill: {
                    type: 'gradient',
                    gradient: { opacityFrom: 0.3, opacityTo: 0.05, stops: [0, 90, 100] }
                },
                stroke: { curve: 'smooth', width: 2.5 },
                xaxis: { 
                    categories: @json($chartLabels),
                    labels: { style: { colors: '#94a3b8' } }
                },
                yaxis: { labels: { style: { colors: '#94a3b8' } } },
                grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
                dataLabels: { enabled: false },
                tooltip: { theme: 'light' }
            };
            
            new ApexCharts(document.querySelector("#engagementChart"), options).render();
        });
    </script>
</x-app-layout>