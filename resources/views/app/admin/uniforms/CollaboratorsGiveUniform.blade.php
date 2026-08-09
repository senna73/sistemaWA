<x-app-layout>
    <div class="container-xxl flex-grow-1 container-p-y">
        
        <!-- Cards de Métricas -->
        <div class="row g-4 mb-4">
            <div class="col-sm-6 col-xl-4">
                <div class="card">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <span class="fw-semibold d-block text-muted">Uniformes Entregues</span>
                            <h3 class="card-title mb-0 text-primary">{{ $totalEntregues }}</h3>
                        </div>
                        <div class="avatar avatar-md bg-label-primary p-2 rounded">
                            <i class="bx bx-closet fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-4">
                <div class="card">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <span class="fw-semibold d-block text-muted">Pendentes de Entrega</span>
                            <h3 class="card-title mb-0 text-warning">{{ $totalPendentes }}</h3>
                        </div>
                        <div class="avatar avatar-md bg-label-warning p-2 rounded">
                            <i class="bx bx-time-five fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-4">
                <div class="card">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <span class="fw-semibold d-block text-muted">Colaboradores Em Dia</span>
                            <h3 class="card-title mb-0 text-success">{{ $totalRegularizados }}</h3>
                        </div>
                        <div class="avatar avatar-md bg-label-success p-2 rounded">
                            <i class="bx bx-check-double fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Abas -->
        <div class="nav-align-top mb-4">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#navs-pending">
                        <i class="bx bx-error me-1"></i> Pendentes
                        <span class="badge rounded-pill bg-danger ms-1">{{ $totalPendentes }}</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-delivered">
                        <i class="bx bx-check me-1"></i> Entregues / Em Dia
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <!-- ABA PENDENTES -->
                <div class="tab-pane fade show active" id="navs-pending" role="tabpanel">
                    
                    <!-- Barra entrega em Lote -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <button type="button" id="btn-deliver-selected" class="btn btn-success" disabled onclick="deliverBatch()">
                            <i class="bx bx-check-double me-1"></i> Entregar em Lote (<span id="selected-count">0</span>)
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table id="table-pending" class="table table-hover border-top">
                            <thead>
                                <tr>
                                    <th style="width: 40px;" class="text-center">
                                        <input type="checkbox" class="form-check-input" id="check-all">
                                    </th>
                                    <th>Colaborador</th>
                                    <th class="text-center">Diárias</th>
                                    <th class="text-center">Direito</th>
                                    <th class="text-center">Já Recebidos</th>
                                    <th class="text-center" style="width: 220px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pending as $collab)
                                    <tr>
                                        <td class="text-center">
                                            <input type="checkbox" class="form-check-input select-collab" value="{{ $collab->id }}">
                                        </td>
                                        <td><strong>{{ $collab->name }}</strong></td>
                                        <td class="text-center"><span class="badge bg-label-info">{{ $collab->daily_rates_count }}</span></td>
                                        <td class="text-center"><span class="badge bg-label-primary">{{ $collab->uniforms_entitled }} Uniforme(s)</span></td>
                                        <td class="text-center">
                                            <span class="badge bg-label-warning">{{ $collab->uniforms_delivered }} / {{ $collab->uniforms_entitled }}</span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex gap-1 justify-content-center align-items-center">
                                                <!-- Select rápido da quantidade entregue -->
                                                <select class="form-select form-select-sm" id="qty-{{ $collab->id }}" style="width: 75px;">
                                                    @for ($i = 1; $i <= $collab->uniforms_entitled; $i++)
                                                        <option value="{{ $i }}" {{ $collab->uniforms_entitled == $i ? 'selected' : '' }}>
                                                            {{ $i }}
                                                        </option>
                                                    @endfor
                                                </select>
                                                <button type="button" class="btn btn-sm btn-primary text-nowrap" onclick="updateUniform({{ $collab->id }})">
                                                    Salvar
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ABA ENTREGUES -->
                <div class="tab-pane fade" id="navs-delivered" role="tabpanel">
                    <div class="table-responsive">
                        <table id="table-delivered" class="table table-hover border-top">
                            <thead>
                                <tr>
                                    <th>Colaborador</th>
                                    <th class="text-center">Diárias</th>
                                    <th class="text-center">Direito Atual</th>
                                    <th class="text-center">Uniformes Entregues</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($delivered as $collab)
                                    <tr>
                                        <td><strong>{{ $collab->name }}</strong></td>
                                        <td class="text-center"><span class="badge bg-label-info">{{ $collab->daily_rates_count }}</span></td>
                                        <td class="text-center"><span class="badge bg-label-primary">{{ $collab->uniforms_entitled }} Uniforme(s)</span></td>
                                        <td class="text-center"><span class="badge bg-label-success">{{ $collab->uniforms_delivered }}</span></td>
                                        <td class="text-center"><span class="badge bg-success">Em Dia</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>

<script>
    $(document).ready(function() {
        $('#table-pending, #table-delivered').DataTable({
            pageLength: 25,
            responsive: true,
            language: {
                url: 'https://cdn.datatables.net/plug-ins/2.2.2/i18n/pt-BR.json'
            }
        });

        // Lógica do Checkbox 'Marcar Todos'
        $('#check-all').on('change', function() {
            $('.select-collab').prop('checked', this.checked);
            updateBatchButton();
        });

        $(document).on('change', '.select-collab', function() {
            updateBatchButton();
        });
    });

    function updateBatchButton() {
        var count = $('.select-collab:checked').length;
        $('#selected-count').text(count);
        $('#btn-deliver-selected').prop('disabled', count === 0);
    }

    function updateUniform(id) {
        var qty = $('#qty-' + id).val();

        $.ajax({
            url: "{{ route('admin.uniforms.deliver', '') }}" + '/' + id,
            type: 'POST',
            data: { quantity: qty },
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                Swal.fire({
                    title: response.title,
                    text: response.message,
                    icon: response.type,
                    timer: 1200,
                    showConfirmButton: false
                }).then(() => {
                    window.location.reload();
                });
            },
            error: function() {
                Swal.fire('Oops!', 'Erro ao atualizar a entrega.', 'error');
            }
        });
    }

    function deliverBatch() {
        var selectedIds = [];
        $('.select-collab:checked').each(function() {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) return;

        Swal.fire({
            title: 'Confirmar entrega em lote?',
            text: `Deseja regularizar os uniformes para os ${selectedIds.length} colaboradores selecionados?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            confirmButtonText: 'Sim, regularizar!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: "{{ route('admin.uniforms.deliver-batch') }}",
                    type: 'POST',
                    data: { collaborators: selectedIds },
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        Swal.fire({
                            title: response.title,
                            text: response.message,
                            icon: response.type
                        }).then(() => {
                            window.location.reload();
                        });
                    },
                    error: function() {
                        Swal.fire('Oops!', 'Erro ao processar a entrega em lote.', 'error');
                    }
                });
            }
        });
    }
</script>