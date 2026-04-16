<div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-gray-100 mb-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h3 class="text-xl font-bold text-gray-800">Frequência de Engajamento</h3>
            <p class="text-sm text-gray-500">Quantidade de colaboradores por volume de diárias</p>
        </div>
        <div class="px-3 py-1 bg-indigo-50 text-indigo-600 text-[10px] font-bold rounded-full uppercase tracking-wider">
            Visão Geral
        </div>
    </div>
    
    <div id="distributionChartMain" style="min-height: 350px; width: 100%;"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const rawData = {!! json_encode($distribution) !!};
        
        const categories = Object.keys(rawData).map(key => key + (key == 1 ? " Diária" : " Diárias"));
        const seriesValues = Object.values(rawData);

        if(seriesValues.length === 0) {
            document.querySelector("#distributionChartMain").innerHTML = 
                '<div class="flex items-center justify-center h-[300px] text-gray-400 font-medium">Nenhum dado encontrado para o período selecionado.</div>';
            return;
        }

        const barColors = Object.keys(rawData).map(key => key == 0 ? '#FDA4AF' : '#6366F1');

        const options = {
            series: [{
                name: 'Colaboradores',
                data: seriesValues
            }],
            chart: {
                type: 'bar',
                height: 350,
                toolbar: { show: false },
                fontFamily: 'Inter, ui-sans-serif, system-ui',
                animations: { enabled: true }
            },
            plotOptions: {
                bar: {
                    borderRadius: 6,
                    columnWidth: '45%',
                    distributed: true,
                    dataLabels: { position: 'top' }
                }
            },
            colors: barColors,
            dataLabels: {
                enabled: true,
                offsetY: -20,
                style: { fontSize: '13px', colors: ["#475569"], fontWeight: 700 }
            },
            xaxis: {
                categories: categories,
                axisBorder: { show: false },
                axisTicks: { show: false },
                labels: { style: { colors: '#94A3B8', fontWeight: 600 } }
            },
            yaxis: { show: false },
            grid: { show: false },
            legend: { show: false },
            tooltip: {
                theme: 'light',
                y: { formatter: (val) => val + (val == 1 ? " pessoa" : " pessoas") }
            }
        };

        const chart = new ApexCharts(document.querySelector("#distributionChartMain"), options);
        chart.render();
    });
</script>