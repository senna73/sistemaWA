@php
    $columns = [
        'pending' => 'Entrada',
        'in_progress' => 'Em execução',
        'in_review' => 'Revisão',
    ];
    $columnStatuses = [
        'pending' => 'Pendente',
        'in_progress' => 'Em execução',
        'in_review' => 'Revisão',
        'done' => 'Finalizado',
    ];
@endphp
<div class="row g-3 mb-4">
    @foreach ($columns as $key => $label)
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between">
                    <span>{{ $label }}</span>
                    <span class="text-muted" data-agenda-count>{{ $functionBoard[$key]->count() }}</span>
                </div>
                <div class="card-body" data-agenda-column="{{ $key }}" data-status="{{ $key }}">
                    @foreach ($functionBoard[$key] as $item)
                        <div class="border rounded p-2 mb-2 {{ $item->effectiveStatus() === 'late' ? 'border-danger' : '' }}" draggable="true" data-agenda-card="{{ $item->id }}" data-status="{{ $item->status }}" data-assignee-id="{{ $item->assignee_id }}" data-late="{{ $item->effectiveStatus() === 'late' ? '1' : '0' }}">
                            <div class="d-flex justify-content-between gap-2">
                                <strong>{{ $item->title }}</strong>
                                <span class="small text-muted">#{{ $item->id }}</span>
                            </div>
                            <div class="small text-muted">
                                @if ($item->workFunctionLabel())
                                    <span class="badge bg-label-info" data-bs-toggle="tooltip" title="Só o setor {{ $item->workFunctionLabel() }} vê esta atividade na produtividade.">{{ $item->workFunctionLabel() }}</span>
                                @endif
                                · <span data-agenda-assignee>{{ $item->assignee?->name ?? 'Fila do setor' }}</span>
                                · {{ $item->due_at?->format('d/m H:i') }}
                            </div>
                            <div class="mt-1">
                                @if ($item->priority === 'alta')
                                    <span class="badge bg-primary">Alta prioridade</span>
                                @endif
                                <span class="badge bg-danger" data-agenda-late-badge @if ($item->effectiveStatus() !== 'late') hidden @endif>Atrasada</span>
                            </div>
                            <div class="small mt-1" data-agenda-note @if (! $item->correction_note) hidden @endif>Correção: <span data-agenda-note-text>{{ $item->correction_note }}</span></div>
                            <form method="POST" action="{{ route('agenda.status', $item) }}" class="mt-2" data-agenda-form="card">
                                @csrf
                                <select name="status" class="form-select form-select-sm">
                                    @foreach ($columnStatuses as $status => $statusLabel)
                                        <option value="{{ $status }}" @selected($item->status === $status)>{{ $statusLabel }}</option>
                                    @endforeach
                                </select>
                            </form>
                            @if ($mode === 'director')
                                <form method="POST" action="{{ route('agenda.assign', $item) }}" class="mt-1 d-flex gap-1" data-agenda-form="card">
                                    @csrf
                                    <select name="assignee_id" class="form-select form-select-sm">
                                        <option value="">Fila do setor</option>
                                        @foreach ($functionMembers as $member)
                                            @if ($member->work_function === $item->work_function)
                                                <option value="{{ $member->id }}" @selected((int) $item->assignee_id === (int) $member->id)>{{ $member->name }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                    <button class="btn btn-sm btn-outline-secondary" type="submit">Transferir</button>
                                </form>
                                <form method="POST" action="{{ route('agenda.correction', $item) }}" class="mt-1 d-flex gap-1" data-agenda-form="card">
                                    @csrf
                                    <input name="note" class="form-control form-control-sm" placeholder="Pedir correção" required>
                                    <button class="btn btn-sm btn-outline-secondary" type="submit">Enviar</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                    <p class="text-muted mb-0" data-agenda-empty @if ($functionBoard[$key]->isNotEmpty()) hidden @endif>Nada nesta coluna.</p>
                </div>
            </div>
        </div>
    @endforeach
</div>
