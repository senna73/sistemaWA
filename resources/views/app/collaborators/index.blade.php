<x-app-layout>
    <div class="container-xxl flex-grow-1 container-p-y">
        <!-- Card de Filtros e Ações -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <!-- Select2 para os Grupos -->
                    <div class="col-md-6">
                        <label for="groups-select" class="form-label fw-bold">Filtrar por Grupos:</label>
                        <select id="groups-select" name="groups[]" class="select2 form-select" multiple="multiple">
                            @foreach ($groups as $group)
                                <option value="{{ $group }}">{{ $group }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <!-- Botões Export pdf -->
                    <div class="col-md-6 d-flex gap-2 justify-content-end">
                        <button type="button" id="btn-export" class="btn btn-outline-secondary w-100 w-md-auto">
                            <i class="bx bx-export me-1"></i> Exportar
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="md-3 mb-3">
            
            <a href="{{ route('collaborators.create')  }}" class="btn btn-outline-primary w-100">Cadastrar</a>
        </div>
        <!-- Tabela DataTables -->
        <div class="card">
            <h5 class="card-header pb-0 fw-bold">Colaboradores</h5>
            <div class="card-datatable table-responsive">
                <table id="table-collaborators" class="table border-top">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th class="text-center" style="width: 150px;">Ações</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>

<script>
    var table;

    $(document).ready(function() {
        $('#groups-select').select2({
            placeholder: "Selecione os grupos...",
            allowClear: true,
            width: '100%'
        });

        table = $('#table-collaborators').DataTable({
            processing: true,
            serverSide: true,
            pagingType: 'simple_numbers',
            responsive: true,
            ajax: {
                url: "{{ route('collaborators.table') }}",
                data: function (d) {
                    d.groups = $('#groups-select').val();
                }
            },
            columns: [
                { data: 'name', name: 'name' },
                { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-center' }
            ],
            language: {
                url: 'https://cdn.datatables.net/plug-ins/2.2.2/i18n/pt-BR.json'
            }
        });

        $('#groups-select').on('change', function() {
            table.ajax.reload();
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
                    url: "{{ route('collaborators.destroy', '') }}" + '/' + id,
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
                        var errorData = response.responseJSON || {};
                        Swal.fire({
                            title: errorData?.title ?? 'Oops!',
                            html: errorData?.message?.replace(/\n/g, '<br>') ?? 'Erro na ação!',
                            icon: errorData?.type ?? 'error'
                        });
                    }
                });
            }
        });
    }

    $('#btn-export').on('click', function() {
        var selectedGroups = $('#groups-select').val();
        var baseUrl = "{{ route('collaborators.export-pdf') }}";
        
        if (selectedGroups && selectedGroups.length > 0) {
            var params = $.param({ groups: selectedGroups });
            window.open(baseUrl + '?' + params, '_blank');
        } else {
            window.open(baseUrl, '_blank');
        }
    });
</script>