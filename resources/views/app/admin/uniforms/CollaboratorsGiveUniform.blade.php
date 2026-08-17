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
                    <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab"
                        data-bs-target="#navs-pending">
                        <i class="bx bx-error me-1"></i> Pendentes
                        <span class="badge rounded-pill bg-danger ms-1">{{ $totalPendentes }}</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button type="button" class="nav-link" role="tab" data-bs-toggle="tab"
                        data-bs-target="#navs-delivered">
                        <i class="bx bx-check me-1"></i> Entregues / Em Dia
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <!-- ABA PENDENTES -->
                <div class="tab-pane fade show active" id="navs-pending" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <button type="button" id="btn-deliver-selected" class="btn btn-success" disabled
                            onclick="deliverBatch()">
                            <i class="bx bx-check-double me-1"></i> Entregar em Lote (<span
                                id="selected-count">0</span>)
                        </button>

                        <a href="{{ route('admin.uniforms.report-pdf') }}" target="_blank" class="btn btn-danger">
                            <i class="bx bxs-file-pdf me-1"></i> Exportar Relatório PDF
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table id="table-pending" class="table table-hover border-top">
                            <thead>
                                <tr>
                                    <th style="width: 40px;" class="text-center">
                                        <input type="checkbox" class="form-check-input" id="check-all">
                                    </th>
                                    <th>Colaborador</th>
                                    <th>Setor / Tipo</th>
                                    <th class="text-center">Diárias</th>
                                    <th class="text-center">Direito</th>
                                    <th class="text-center">Já Recebidos</th>
                                    <th class="text-center" style="width: 220px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pending as $item)
                                    @php
                                        $collab = $item->collab;
                                        $section = $item->section;
                                        $sectionId = $section->id ?? 'null';
                                        $sectionName = $section->name ?? 'Geral';
                                        $rowKey = $collab->id . '_' . ($section->id ?? 'default');
                                    @endphp
                                    <tr>
                                        <td class="text-center">
                                            <input type="checkbox" class="form-check-input select-collab"
                                                value="{{ $collab->id }}" data-section-id="{{ $section->id ?? '' }}">
                                        </td>
                                        <td>
                                            <div class="text-truncate" style="max-width: 220px;"
                                                title="{{ $collab->name }}">
                                                <strong>{{ $collab->name }}</strong>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-label-info text-truncate d-inline-block align-bottom"
                                                style="max-width: 180px;" title="{{ $sectionName }}">
                                                <i class="bx bx-store-alt me-1"></i>{{ $sectionName }}
                                            </span>
                                            <small class="d-block text-muted text-truncate" style="max-width: 180px;"
                                                title="{{ $item->kit_type }}">
                                                {{ $item->kit_type }}
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-label-secondary">
                                                {{ $item->daily_rates_count }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-label-primary">{{ $item->uniforms_entitled }}
                                                Uniforme(s)</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-label-warning">{{ $item->uniforms_delivered }} /
                                                {{ $item->uniforms_entitled }}</span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex gap-1 justify-content-center align-items-center">
                                                <select class="form-select form-select-sm" id="qty-{{ $rowKey }}"
                                                    style="width: 75px;">
                                                    @for ($i = 1; $i <= $item->uniforms_entitled; $i++)
                                                        <option value="{{ $i }}"
                                                            {{ $collab->uniforms_delivered == $i ? 'selected' : '' }}>
                                                            {{ $i }}
                                                        </option>
                                                    @endfor
                                                </select>
                                                <button type="button" class="btn btn-sm btn-primary text-nowrap"
                                                    onclick="updateUniform({{ $collab->id }}, {{ $sectionId }})">
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
                                    <th>Tipo de Uniforme</th>
                                    <th class="text-center">Tamanho</th>
                                    <th class="text-center">Qtd Entregue</th>
                                    <th class="text-center">Data da Entrega</th>
                                    <th class="text-center">Observação</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($delivered as $item)
                                    @php
                                        $collab = $item->collaborator;
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="text-truncate" style="max-width: 200px;"
                                                title="{{ $collab->name ?? 'N/A' }}">
                                                <strong>{{ $collab->name ?? 'N/A' }}</strong>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-label-info">
                                                <i class="bx bx-closet me-1"></i>{{ $item->type->name ?? 'Padrão' }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-label-secondary">
                                                {{ $item->size->name ?? ($collab->uniform_size ?? '-') }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-label-success">{{ $item->quantity }}</span>
                                        </td>
                                        <td class="text-center">
                                            <small class="text-muted">
                                                {{ $item->delivered_at ? \Illuminate\Support\Carbon::parse($item->delivered_at)->format('d/m/Y H:i') : '-' }}
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <small class="text-muted">{{ $item->observation ?? '-' }}</small>
                                        </td>
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
    let tablePending;

    $(document).ready(function() {
        tablePending = $('#table-pending').DataTable({
            pageLength: 25,
            responsive: true,
            language: {
                url: 'https://cdn.datatables.net/plug-ins/2.2.2/i18n/pt-BR.json'
            }
        });

        $('#table-delivered').DataTable({
            pageLength: 25,
            responsive: true,
            language: {
                url: 'https://cdn.datatables.net/plug-ins/2.2.2/i18n/pt-BR.json'
            }
        });

        $('#check-all').on('change', function() {
            let isChecked = this.checked;
            let rows = tablePending.rows().nodes();
            $('.select-collab', rows).prop('checked', isChecked);
            updateBatchButton();
        });

        $(document).on('change', '.select-collab', function() {
            updateBatchButton();
        });
    });

    function updateBatchButton() {
        let rows = tablePending.rows().nodes();
        let count = $('.select-collab:checked', rows).length;

        $('#selected-count').text(count);
        $('#btn-deliver-selected').prop('disabled', count === 0);
    }

    function updateUniform(collabId, sectionId) {
        var cleanSectionId = (sectionId && sectionId !== 'null') ? sectionId : '';
        var rowKey = cleanSectionId ? collabId + '_' + cleanSectionId : collabId + '_default';
        var qty = $('#qty-' + rowKey).val();

        $.ajax({
            url: "{{ route('admin.uniforms.deliver', '') }}" + '/' + collabId,
            type: 'POST',
            data: {
                quantity: qty,
                section_id: cleanSectionId
            },
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
            error: function(xhr) {
                var msg = xhr.responseJSON?.message || 'Erro ao atualizar a entrega.';
                Swal.fire('Erro 422', msg, 'error');
            }
        });
    }

    function deliverBatch() {
        var selectedItems = [];
        let rows = tablePending.rows().nodes();

        $('.select-collab:checked', rows).each(function() {
            selectedItems.push({
                collaborator_id: $(this).val(),
                section_id: $(this).data('section-id') || null
            });
        });

        if (selectedItems.length === 0) return;

        Swal.fire({
            title: 'Confirmar entrega em lote?',
            text: `Deseja regularizar os uniformes para os ${selectedItems.length} registros selecionados?`,
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
                    data: {
                        items: selectedItems
                    },
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
