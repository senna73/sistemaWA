<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <div class="container-fluid py-5 bg-light min-vh-100">
        <div class="container">
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4 gap-3">
                <div>
                    <p class="text-primary fw-bold text-uppercase small mb-1" style="letter-spacing: 1px;">Performance</p>
                    <h2 class="fw-black text-dark m-0">Analytics & Insights</h2>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <div class="bg-white p-1 rounded-3 shadow-sm border d-flex">
                        @foreach([1, 3, 6, 12] as $m)
                            <a href="{{ route('analytics.index', ['months' => $m]) }}" 
                               class="btn btn-sm {{ $months == $m ? 'btn-primary' : 'btn-light text-muted' }} fw-bold px-3 py-1 uppercase" 
                               style="font-size: 10px;">
                                {{ $m }}M
                            </a>
                        @endforeach
                    </div>

                    <a href="{{ route('analytics.pdf', ['months' => $months]) }}" 
                       class="btn btn-dark d-inline-flex align-items-center gap-2 px-3 py-2 rounded-3 shadow-sm border-0">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" class="text-info">
                            <path d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span class="fw-bold" style="font-size: 12px;">Exportar PDF</span>
                    </a>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 p-3">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted fw-bold text-uppercase mb-1" style="font-size: 10px; letter-spacing: 1px;">Média Ativos</p>
                                <h3 class="fw-black text-primary m-0">{{ number_format($mediaAtivos, 1) }}</h3>
                            </div>
                            <div class="bg-primary bg-opacity-10 p-3 rounded-4 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-primary">
                                    <path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 p-3">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted fw-bold text-uppercase mb-1" style="font-size: 10px; letter-spacing: 1px;">Média Ociosos</p>
                                <h3 class="fw-black text-danger m-0">{{ number_format($mediaOciosos, 1) }}</h3>
                            </div>
                            <div class="bg-danger bg-opacity-10 p-3 rounded-4 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-danger">
                                    <path d="M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold text-dark m-0">Atividade Diária</h5>
                    <div class="d-flex gap-3">
                        <div class="d-flex align-items-center gap-1">
                            <div class="rounded-circle bg-primary" style="width: 8px; height: 8px;"></div>
                            <small class="fw-bold text-muted text-uppercase" style="font-size: 9px;">Ativos</small>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <div class="rounded-circle bg-danger" style="width: 8px; height: 8px;"></div>
                            <small class="fw-bold text-muted text-uppercase" style="font-size: 9px;">Inativos</small>
                        </div>
                    </div>
                </div>
                <div id="engagementChart"></div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var options = {
                series: [{ name: 'Trabalhando', data: @json($chartActive) }, 
                         { name: 'Ociosos', data: @json($chartInactive) }],
                chart: { type: 'area', height: 350, toolbar: { show: false } },
                colors: ['#0d6efd', '#dc3545'],
                stroke: { curve: 'smooth', width: 3 },
                xaxis: { categories: @json($chartLabels) },
                dataLabels: { enabled: false },
                tooltip: { theme: 'light' }
            };
            new ApexCharts(document.querySelector("#engagementChart"), options).render();
        });
    </script>
</x-app-layout>