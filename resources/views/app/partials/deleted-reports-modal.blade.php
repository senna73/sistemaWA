<div class="modal fade" id="deleted-reports-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Relatórios de <span id="deleted-reports-name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">Lifetime usa todos os registros. O período usa os mesmos relatórios de Diárias.</p>
                <div class="d-flex flex-column gap-2 mb-4">
                    <button type="button" class="btn btn-outline-primary" onclick="openPersonReport('registers')">
                        Lifetime — Relatório de registros
                    </button>
                    <button type="button" class="btn btn-outline-primary" onclick="openPersonReport('daily-rates')">
                        Lifetime — Relatório de diárias
                    </button>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="deleted-report-start">Início</label>
                        <input type="datetime-local" class="form-control" id="deleted-report-start">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="deleted-report-end">Fim</label>
                        <input type="datetime-local" class="form-control" id="deleted-report-end">
                    </div>
                </div>
                <div class="d-flex flex-column gap-2 mt-3">
                    <button type="button" class="btn btn-info" onclick="openPersonReport('registers', true)">
                        Período — Relatório de registros
                    </button>
                    <button type="button" class="btn btn-info" onclick="openPersonReport('daily-rates', true)">
                        Período — Relatório de diárias
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    var deletedReportUrlTemplate = '';

    function openDeletedReports(id, name, urlTemplate) {
        deletedReportUrlTemplate = urlTemplate;
        document.getElementById('deleted-reports-name').textContent = name;
        var modal = new bootstrap.Modal(document.getElementById('deleted-reports-modal'));
        modal.show();
    }

    function openPersonReport(type, withPeriod) {
        var url = deletedReportUrlTemplate.replace('__TYPE__', type);
        if (withPeriod) {
            var start = document.getElementById('deleted-report-start').value;
            var end = document.getElementById('deleted-report-end').value;
            if (!start || !end) {
                Swal.fire({
                    title: 'Informe o período',
                    text: 'Preencha início e fim para o relatório por período.',
                    icon: 'warning'
                });
                return;
            }
            url += '?' + new URLSearchParams({ start: start, end: end }).toString();
        }
        window.open(url, '_blank');
    }
</script>
