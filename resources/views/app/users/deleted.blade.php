<x-app-layout>
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h4 class="mb-1">Usuários apagados</h4>
                <p class="text-muted mb-0">Contas desativadas. Os relatórios usam os registros de diária que essa conta lançou.</p>
            </div>
            <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">
                Voltar para ativos
            </a>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="role-filter" class="form-label fw-bold">Filtrar por papel</label>
                        <select id="role-filter" class="form-select">
                            <option value="">Todos os papéis</option>
                            @foreach ($roles as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <h5 class="card-header pb-0 fw-bold">Lista de usuários apagados</h5>
            <div class="card-datatable table-responsive">
                <table id="table-users-deleted" class="table border-top" style="width:100%">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>E-mail arquivado</th>
                            <th>Papel</th>
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
        var table = $('#table-users-deleted').DataTable({
            processing: true,
            serverSide: true,
            pagingType: 'simple_numbers',
            responsive: true,
            ajax: {
                url: '{{ route('users.deleted.table') }}',
                data: function (d) {
                    d.role = $('#role-filter').val();
                }
            },
            columns: [
                { data: 'name', name: 'name' },
                { data: 'email', name: 'email' },
                { data: 'role', name: 'role', orderable: false, searchable: false },
                { data: 'updated_at', name: 'updated_at' },
                { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-center' }
            ],
            language: {
                url: 'https://cdn.datatables.net/plug-ins/2.2.2/i18n/pt-BR.json',
            },
        });

        $('#role-filter').on('change', function () {
            table.ajax.reload();
        });
    });
</script>
