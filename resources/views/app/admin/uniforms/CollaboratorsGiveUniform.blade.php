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
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <button type="button" id="btn-deliver-selected" class="btn btn-success" disabled onclick="deliverBatch()">
                            <i class="bx bx-check-double me-1"></i> Entregar em Lote (<span id="selected-count">0</span>)
                        </button>

                        <a href="{{ route('admin.uniforms.report-pdf') }}" target="_blank" class="btn btn-danger">
                            <i class="bx bxs-file-pdf me-1"></i> Exportar Relatório PDF
                        </a>
                    </div>
                    
                    @php
                        $groupedPending = $pending->groupBy(function($item) {
                            return $item->collab->id;
                        });
                    @endphp

                    <div class="table-responsive">
                        <table id="table-pending" class="table table-hover border-top">
                            <thead>
                                <tr>
                                    <th style="width: 40px;" class="text-center">
                                        <input type="checkbox" class="form-check-input" id="check-all">
                                    </th>
                                    <th>Colaborador</th>
                                    <th class="text-center">Total Diárias</th>
                                    <th class="text-center">Total Direito</th>
                                    <th class="text-center">Total Recebido</th>
                                    <th class="text-center" style="width: 100px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($groupedPending as $collabId => $items)
                                    @php
                                        $collab = $items->first()->collab;
                                        $isLeader = $collab->is_leader ?? false;
                                        $allSectionIds = [];

                                        $kits = [
                                            'açougue' => ['title' => 'Kit Açougue', 'diarias' => 0, 'direito' => 0, 'recebidos' => 0, 'sections' => []],
                                            'padaria' => ['title' => 'Kit Padaria', 'diarias' => 0, 'direito' => 0, 'recebidos' => 0, 'sections' => []],
                                            'resto'   => ['title' => $isLeader ? 'Kit Líder' : 'Kit Padrão (Geral)', 'diarias' => 0, 'direito' => 0, 'recebidos' => 0, 'sections' => []],
                                        ];

                                        foreach($items as $item) {
                                            $secName = mb_strtolower($item->section->name ?? '');
                                            $secId = $item->section->id ?? null;
                                            if($secId) $allSectionIds[] = $secId;

                                            if (str_contains($secName, 'açougue')) {
                                                $k = 'açougue';
                                            } elseif (str_contains($secName, 'padaria')) {
                                                $k = 'padaria';
                                            } else {
                                                $k = 'resto';
                                            }

                                            $kits[$k]['diarias'] += $item->daily_rates_count;
                                            $kits[$k]['direito'] += $item->uniforms_entitled;
                                            $kits[$k]['recebidos'] += $item->uniforms_delivered;
                                            
                                            if (!in_array($item->section->name ?? 'Geral', $kits[$k]['sections'])) {
                                                $kits[$k]['sections'][] = $item->section->name ?? 'Geral';
                                            }
                                        }

                                        $activeKits = array_filter($kits, fn($kit) => $kit['direito'] > 0 || $kit['diarias'] > 0);

                                        $totalDiarias = array_sum(array_column($activeKits, 'diarias'));
                                        $totalDireito = array_sum(array_column($activeKits, 'direito'));
                                        $totalRecebidos = array_sum(array_column($activeKits, 'recebidos'));
                                    @endphp

                                    <tr>
                                        <td class="text-center">
                                            <input type="checkbox" class="form-check-input select-collab" value="{{ $collab->id }}" data-sections="{{ implode(',', $allSectionIds) }}">
                                        </td>
                                        <td>
                                            <strong>{{ $collab->name }}</strong>
                                            @if($isLeader)
                                                <span class="badge bg-label-warning ms-2"><i class="bx bx-star me-1"></i>Líder</span>
                                            @endif
                                        </td>
                                        <td class="text-center"><span class="badge bg-label-secondary">{{ $totalDiarias }}</span></td>
                                        <td class="text-center"><span class="badge bg-label-primary">{{ $totalDireito }}</span></td>
                                        <td class="text-center"><span class="badge bg-label-success">{{ $totalRecebidos }} / {{ $totalDireito }}</span></td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-icon btn-secondary toggle-details" title="Expandir Kits">
                                                <i class="bx bx-chevron-down"></i>
                                            </button>
                                            
                                            <div class="kit-details-html d-none">
                                                <div class="p-3 bg-lighter rounded border border-light mt-2">
                                                    <h6 class="mb-2 text-muted">Separação por Kits</h6>
                                                    <table class="table table-sm table-bordered bg-white mb-0">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th>Agrupamento</th>
                                                                <th class="text-center">Diárias</th>
                                                                <th class="text-center">Direito</th>
                                                                <th class="text-center">Entregues</th>
                                                                <th class="text-center">Ações</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($activeKits as $kitKey => $kit)
                                                                <tr>
                                                                    <td>
                                                                        <strong class="text-primary">{{ $kit['title'] }}</strong><br>
                                                                        <small class="text-muted">{{ implode(', ', $kit['sections']) }}</small>
                                                                    </td>
                                                                    <td class="text-center align-middle">{{ $kit['diarias'] }}</td>
                                                                    <td class="text-center align-middle">{{ $kit['direito'] }}</td>
                                                                    <td class="text-center align-middle">{{ $kit['recebidos'] }}</td>
                                                                    <td class="text-center align-middle">
                                                                        <div class="d-flex gap-1 justify-content-center align-items-center">
                                                                            <select class="form-select form-select-sm" id="qty-{{ $collab->id }}-{{ $kitKey }}" style="width: 70px;">
                                                                                @for ($i = 1; $i <= $kit['direito']; $i++)
                                                                                    <option value="{{ $i }}">{{ $i }}</option>
                                                                                @endfor
                                                                            </select>
                                                                            <button type="button" class="btn btn-sm btn-primary text-nowrap" onclick="updateUniformKit({{ $collab->id }}, '{{ $kitKey }}')">
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
                                      \      <small class="text-muted">{{ $item->observation ?? '-' }}</small>
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
            language: { url: 'https://cdn.datatables.net/plug-ins/2.2.2/i18n/pt-BR.json' },
            order: [[1, 'asc']], // Ordena pelo nome por padrão
            columnDefs: [
                { orderable: false, targets: [0, 5] } // Remove ordenação do checkbox e botões
            ]
        });

        // Expandir child rows do Datatables
        $('#table-pending tbody').on('click', '.toggle-details', function () {
            var tr = $(this).closest('tr');
            var row = tablePending.row(tr);
            var icon = $(this).find('i');

            if (row.child.isShown()) {
                row.child.hide();
                tr.removeClass('shown');
                icon.removeClass('bx-chevron-up').addClass('bx-chevron-down');
            } else {
                // Pega o HTML invisível e joga na child row
                var html = tr.find('.kit-details-html').html();
                row.child(html).show();
                tr.addClass('shown');
                icon.removeClass('bx-chevron-down').addClass('bx-chevron-up');
            }
        });

        $('#check-all').on('change', function() {
            let isChecked = this.checked;
            let rows = tablePending.rows({ search: 'applied' }).nodes();
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

    // Atualizado para enviar o kitKey no lugar do section_id estrito
    function updateUniformKit(collabId, kitKey) {
        var qty = $('#qty-' + collabId + '-' + kitKey).val();

        $.ajax({
            url: "{{ route('admin.uniforms.deliver', '') }}" + '/' + collabId,
            type: 'POST',
            data: {
                quantity: qty,
                kit_type: kitKey // Envia a string 'açougue', 'padaria' ou 'resto' pro controller se virar
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
                // Pega todos os IDs de seção daquele usuário pra abater no lote
                section_ids: $(this).data('sections').toString().split(',') 
            });
        });

        if (selectedItems.length === 0) return;

        Swal.fire({
            title: 'Confirmar entrega em lote?',
            text: `Deseja regularizar os uniformes para os ${selectedItems.length} colaboradores selecionados?`,
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
                    data: { items: selectedItems },
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    success: function(response) {
                        Swal.fire({
                            title: response.title,
                            text: response.message,
                            icon: response.type
                        }).then(() => window.location.reload());
                    },
                    error: function() {
                        Swal.fire('Oops!', 'Erro ao processar a entrega em lote.', 'error');
                    }
                });
            }
        });
    }
</script>