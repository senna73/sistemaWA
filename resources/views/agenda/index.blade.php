<x-app-layout>
    <div class="container-fluid">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

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

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Nova atividade</h5></div>
            <div class="card-body">
                <p class="small text-muted mb-3">Rotina, demanda operacional, desligamento (retirar de grupos WhatsApp) e contratação usam a mesma agenda. Estabelecimento é a loja atendida. Grupo WhatsApp é outro dado, no cadastro do colaborador.</p>
                <form method="POST" action="{{ route('agenda.store') }}" class="row g-2">
                    @csrf
                    <div class="col-md-4"><input name="title" class="form-control" placeholder="Título" required></div>
                    <div class="col-md-2">
                        <select name="assignee_id" class="form-select" required>
                            <option value="">Responsável</option>
                            @foreach ($assignees as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2"><input type="datetime-local" name="due_at" class="form-control"></div>
                    <div class="col-md-2">
                        <select name="type" class="form-select" required>
                            @foreach ($types as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="recurrence" class="form-select">
                            @foreach ($recurrences as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12"><input name="description" class="form-control" placeholder="Descrição"></div>
                    <div class="col-md-3">
                        <select name="collaborator_id" class="form-select">
                            <option value="">Colaborador (quando o POP exigir)</option>
                            @foreach ($collaborators as $person)
                                <option value="{{ $person->id }}">{{ $person->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="company_id" class="form-select">
                            <option value="">Estabelecimento</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if (! empty($demandCategories))
                        <div class="col-md-3">
                            <select name="category" class="form-select">
                                <option value="">Categoria da demanda</option>
                                @foreach ($demandCategories as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="col-md-3"><input name="area" class="form-control" placeholder="Projeto/área"></div>
                    <div class="col-md-2"><input name="priority" class="form-control" value="normal" placeholder="Prioridade"></div>
                    <div class="col-md-2"><button class="btn btn-primary" type="submit">Criar</button></div>
                </form>
            </div>
        </div>

        <div class="row g-3">
            @foreach (['today' => 'Hoje', 'week' => 'Próximos 7 dias', 'later' => 'Acima de 7 dias'] as $key => $label)
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header">{{ $label }}</div>
                        <div class="card-body">
                            @forelse ($board[$key] as $item)
                                <div class="border rounded p-2 mb-2">
                                    <strong>{{ $item->title }}</strong>
                                    <div class="small text-muted">{{ $item->typeLabel() }} · {{ $item->assignee?->name }} · {{ $item->statusLabel() }} · {{ $item->due_at?->format('d/m H:i') }}</div>
                                    @if ($item->linkedSummary())
                                        <div class="small">Vinculado: {{ $item->linkedSummary() }}</div>
                                    @endif
                                    <form method="POST" action="{{ route('agenda.status', $item) }}" class="mt-1">
                                        @csrf
                                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                            @foreach ($statuses as $status => $statusLabel)
                                                <option value="{{ $status }}" @selected($item->effectiveStatus() === $status)>{{ $statusLabel }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </div>
                            @empty
                                <p class="text-muted mb-0">Nada nesta faixa.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
