<x-app-layout>
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h4 class="mb-1">Colaboradores apagados</h4>
                <p class="text-muted mb-0">Histórico de quem foi desligado ou removido. As diárias continuam disponíveis nos relatórios.</p>
            </div>
            <a href="{{ route('collaborators.index') }}" class="btn btn-outline-secondary">
                Voltar para ativos
            </a>
        </div>

        <div class="card">
            <h5 class="card-header pb-0 fw-bold">Lista de colaboradores apagados</h5>
            <div class="card-datatable table-responsive">
                <table id="table-collaborators-deleted" class="table border-top" style="width:100%">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>CPF</th>
                            <th>Grupo WhatsApp</th>
                            <th>Diárias</th>
                            <th>Atualizado em</th>
                            <th class="text-center" style="width: 90px;">Relatórios</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
    @include('app.partials.deleted-reports-modal')
</x-app-layout>

<script>
    $(document).ready(function() {
        $('#table-collaborators-deleted').DataTable({
            processing: true,
            serverSide: true,
            pagingType: 'simple_numbers',
            responsive: true,
            ajax: {
                url: "{{ route('collaborators.deleted.table') }}"
            },
            columns: [
                { data: 'name', name: 'name' },
                { data: 'document', name: 'document' },
                { data: 'group', name: 'group' },
                { data: 'daily_rates_count', name: 'daily_rates_count' },
                { data: 'updated_at', name: 'updated_at' },
                { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-center' }
            ],
            language: {
                url: 'https://cdn.datatables.net/plug-ins/2.2.2/i18n/pt-BR.json'
            }
        });
    });
</script>
