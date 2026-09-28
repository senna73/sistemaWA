<x-app-layout>
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h4 class="mb-1">Usuários</h4>
                <p class="text-muted mb-0">Filtre a equipe por papel e gerencie o acesso de cada conta.</p>
            </div>
            <a href="{{ route('users.create') }}" class="btn btn-primary">
                <i class="bx bx-plus me-1"></i> Cadastrar usuário
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
                    @if ($canManageRoles)
                        <div class="col-md-8">
                            <div class="alert alert-primary mb-0 py-2">
                                Super admin: altere o papel direto na tabela. As permissões do papel são aplicadas automaticamente.
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card">
            <h5 class="card-header pb-0 fw-bold">Lista de usuários</h5>
            <div class="card-datatable table-responsive">
                <table id="table-users" class="table border-top" style="width:100%">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Papel</th>
                            <th class="text-center" style="width: 140px;">Ações</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>

<script>
    var table;
    var roleUrl = "{{ route('users.role', ['id' => '__ID__']) }}";

    $(document).ready(function() {
        table = $('#table-users').DataTable({
            processing: true,
            serverSide: true,
            pagingType: 'simple_numbers',
            responsive: true,
            ajax: {
                url: '{{ route('users.table') }}',
                data: function (d) {
                    d.role = $('#role-filter').val();
                }
            },
            columns: [
                { data: 'name', name: 'name' },
                { data: 'email', name: 'email' },
                { data: 'role', name: 'role', orderable: false, searchable: false },
                { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-center' }
            ],
            language: {
                url: 'https://cdn.datatables.net/plug-ins/2.2.2/i18n/pt-BR.json',
            },
        });

        $('#role-filter').on('change', function() {
            table.ajax.reload();
        });

        $('#table-users').on('change', '.user-role-select', function() {
            var select = $(this);
            var userId = select.data('user-id');
            var role = select.val();

            $.ajax({
                url: roleUrl.replace('__ID__', userId),
                type: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: { role: role },
                success: function(response) {
                    Swal.fire({
                        title: response?.title ?? 'Sucesso!',
                        text: response?.message ?? 'Papel atualizado.',
                        icon: response?.type ?? 'success',
                        timer: 1800,
                        showConfirmButton: false
                    });
                },
                error: function(xhr) {
                    table.ajax.reload(null, false);
                    var response = xhr.responseJSON || {};
                    Swal.fire({
                        title: response?.title ?? 'Oops!',
                        html: (response?.message ?? 'Não foi possível alterar o papel.').replace(/\n/g, '<br>'),
                        icon: response?.type ?? 'error'
                    });
                }
            });
        });
    });

    function remove(id) {
        Swal.fire({
            title: 'Você tem certeza?',
            text: "Esta ação não pode ser desfeita!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sim, remover!',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: "{{ route('users.destroy', '') }}" + '/' + id,
                    type: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        Swal.fire({
                            title: response?.title ?? 'Sucesso!',
                            text: response?.message ?? 'Sucesso na ação!',
                            icon: response?.type ?? 'success'
                        });
                        if (table) {
                            table.ajax.reload(null, false);
                        }
                    },
                    error: function(response) {
                        response = JSON.parse(response.responseText);
                        Swal.fire({
                            title: response?.title ?? 'Oops!',
                            html: response?.message?.replace(/\n/g, '<br>') ?? 'Erro na ação!',
                            icon: response?.type ?? 'error'
                        });
                    }
                });
            }
        });
    }
</script>
