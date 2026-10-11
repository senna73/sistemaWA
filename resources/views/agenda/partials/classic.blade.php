@if ($mode === 'classic')
<div class="row g-3 mb-3">
    <div class="col"><div class="card"><div class="card-body"><div class="text-muted">Feitas no dia</div><h4>{{ $indicators['done_today'] }}</h4></div></div></div>
    <div class="col"><div class="card"><div class="card-body"><div class="text-muted">Em andamento</div><h4>{{ $indicators['in_progress'] }}</h4></div></div></div>
    <div class="col"><div class="card"><div class="card-body"><div class="text-muted">Pendentes</div><h4>{{ $indicators['pending'] }}</h4></div></div></div>
    <div class="col"><div class="card"><div class="card-body"><div class="text-muted">Atrasadas</div><h4>{{ $indicators['late'] }}</h4></div></div></div>
</div>
<form method="GET" class="row g-2 mb-3">
    <div class="col-md-4">
        <select name="assignee_id" class="form-select">
            <option value="">Indicadores: todas as pessoas</option>
            @foreach ($assignees as $user)
                <option value="{{ $user->id }}" @selected((int) ($personId ?? 0) === (int) $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <select name="period" class="form-select">
            <option value="today" @selected(($period ?? 'today') === 'today')>Hoje</option>
            <option value="week" @selected(($period ?? '') === 'week')>Próximos 7 dias</option>
            <option value="month" @selected(($period ?? '') === 'month')>Mês</option>
        </select>
    </div>
    <div class="col-md-2"><button class="btn btn-outline-secondary" type="submit">Filtrar indicadores</button></div>
</form>
@endif

@include('agenda.partials.manual-form')

<div class="row g-3 mb-4">
    @foreach (['today' => 'Hoje', 'week' => 'Próximos 7 dias', 'later' => 'Acima de 7 dias'] as $key => $label)
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header">{{ $mode === 'director' ? 'Operacional · '.$label : $label }}</div>
                <div class="card-body" data-agenda-classic-column>
                    @foreach ($board[$key] as $item)
                        <div class="border rounded p-2 mb-2 {{ $item->effectiveStatus() === 'late' ? 'border-danger' : '' }}" data-agenda-card="{{ $item->id }}" data-status="{{ $item->status }}" data-assignee-id="{{ $item->assignee_id }}" data-late="{{ $item->effectiveStatus() === 'late' ? '1' : '0' }}">
                            <strong>{{ $item->title }}</strong>
                            <div class="small text-muted">{{ $item->typeLabel() }} · {{ $item->assignee?->name }} · <span data-agenda-status-label>{{ $item->statusLabel() }}</span> · {{ $item->due_at?->format('d/m H:i') }}</div>
                            @if ($item->linkedSummary())
                                <div class="small">Vinculado: {{ $item->linkedSummary() }}</div>
                            @endif
                            <form method="POST" action="{{ route('agenda.status', $item) }}" class="mt-1" data-agenda-form="card">
                                @csrf
                                <select name="status" class="form-select form-select-sm">
                                    @foreach ($statuses as $status => $statusLabel)
                                        <option value="{{ $status }}" @selected($item->effectiveStatus() === $status)>{{ $statusLabel }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </div>
                    @endforeach
                    <p class="text-muted mb-0" data-agenda-empty @if ($board[$key]->isNotEmpty()) hidden @endif>Nada nesta faixa.</p>
                </div>
            </div>
        </div>
    @endforeach
</div>
