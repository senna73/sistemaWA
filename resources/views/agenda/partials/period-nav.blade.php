@php
    $filterRoute = $filterRoute ?? 'agenda.board';
    $periodQuery = fn (?string $periodValue = null, ?string $on = null) => array_filter([
        'period' => $periodValue ?? $period,
        'on' => $on ?? $anchor,
        'work_function' => $workFunction,
        'assignee_id' => $personId ?? null,
    ], fn ($value) => $value !== null && $value !== '');
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h4 class="mb-0">{{ ($screen ?? '') === 'agenda' ? 'Agenda' : ($mode === 'director' ? 'Produtividade' : 'Minhas atividades') }}</h4>
        <div class="text-muted">{{ $periodLabel }}</div>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        @foreach (['month' => 'Mês', 'week' => 'Semana', 'day' => 'Dia'] as $key => $label)
            <a class="btn btn-sm {{ $period === $key ? 'btn-primary' : 'btn-outline-primary' }}"
               href="{{ route($filterRoute, $periodQuery($key, $anchor)) }}">{{ $label }}</a>
        @endforeach
        <a class="btn btn-sm btn-outline-secondary" href="{{ route($filterRoute, $periodQuery($period, $previous)) }}">Anterior</a>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route($filterRoute, $periodQuery($period, $next)) }}">Próximo</a>
    </div>
</div>
@if ($mode === 'director')
    <form method="GET" action="{{ route($filterRoute) }}" class="row g-2 mb-3">
        <input type="hidden" name="period" value="{{ $period }}">
        <input type="hidden" name="on" value="{{ $anchor }}">
        <div class="col-md-4">
            <select name="work_function" class="form-select">
                <option value="">Todos os setores</option>
                @foreach ($workFunctions as $key => $label)
                    <option value="{{ $key }}" @selected($workFunction === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @if (($screen ?? '') === 'board')
            <div class="col-md-4">
                <select name="assignee_id" class="form-select">
                    <option value="">Todas as pessoas</option>
                    @foreach ($functionMembers as $member)
                        <option value="{{ $member->id }}" @selected((int) ($personId ?? 0) === (int) $member->id)>{{ $member->name }} · {{ $workFunctions[$member->work_function] ?? $member->work_function }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="col-md-2"><button class="btn btn-outline-secondary" type="submit">Filtrar</button></div>
    </form>
@endif
