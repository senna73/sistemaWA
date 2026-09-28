<x-app-layout>
    @php
        $user = auth()->user();
        $areasDue = collect($projects)->contains(fn (array $project) => $project['needs_action']);
        $roleHint = match (true) {
            $user->isRh() => 'Sua parte é a fila do RH: atendimento, documentos, exame e clínicas.',
            $user->isOwner() => 'Você aprova o que o RH já conferiu e acompanha a fila de atendimento.',
            $user->can(\App\Support\AccessControl::PERMISSION_DIRECTION) && $user->can(\App\Support\AccessControl::PERMISSION_MANAGE_OFFBOARDING) => 'Você atende a fila do RH e aprova o que já foi conferido.',
            $user->can(\App\Support\AccessControl::PERMISSION_DIRECTION) => 'Sua parte é a aprovação, depois que o RH já conferiu o processo.',
            $user->can(\App\Support\AccessControl::PERMISSION_MANAGE_OFFBOARDING) => 'Sua parte é a fila do RH: atendimento, documentos, exame e clínicas.',
            $user->isCoordinator() => 'Sua parte é acompanhar a equipe e abrir o pedido quando alguém precisa sair ou ser transferido.',
            $user->isAccounting() => 'Sua parte é a conferência do INSS e a baixa contábil.',
            default => 'Abaixo estão as atividades da sua função.',
        };
    @endphp
    <div class="container work-home">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="work-hero mb-4">
            <div>
                <div class="text-muted">WA Serviços · {{ $user->roleLabel() }}</div>
                <h4 class="mb-1">RH Controle</h4>
                <p class="mb-2">{{ $user->name }}, {{ lcfirst($roleHint) }}</p>
                <p class="mb-0 text-muted">
                    @if ($areasDue)
                        As áreas abaixo têm pendências. Comece pelas que pedem Resolver.
                    @else
                        Nada pede a sua ação agora. As áreas abaixo são o caminho de cada processo.
                    @endif
                </p>
            </div>
            <div class="work-stats">
                <div><strong>{{ $open_tasks }}</strong><span>Tarefas abertas</span></div>
                <div><strong>{{ $open_processes }}</strong><span>Processos em andamento</span></div>
                <div><strong>{{ $done_month }}</strong><span>Concluídos no mês</span></div>
            </div>
        </div>

        @include('work.partials.notifications')
        @include('work.partials.board-styles')

        @can('Solicitar desligamento')
        <div class="mb-4">
            <h5 class="work-section-title">Pedido do coordenador</h5>
            <div class="work-areas">
                <a class="work-area is-due" href="{{ route('work.request') }}">
                    <div class="work-kicker">Abrir para o RH</div>
                    <strong>Solicitar desligamento</strong>
                    <p>Pesquise o colaborador, escolha demissão ou transferência e envie o card para o atendimento do RH.</p>
                    <div class="work-area-foot">
                        <span class="badge bg-label-warning">Tela do coordenador</span>
                        <span class="btn btn-sm btn-primary">Solicitar</span>
                    </div>
                </a>
            </div>
        </div>
        @endcan

        <div class="mb-4">
            <h5 class="work-section-title">Suas áreas</h5>
            <div class="work-areas">
                @foreach ($projects as $project)
                    <a class="work-area {{ $project['tone'] ?? ($project['needs_action'] ? 'is-due' : '') }}" href="{{ $project['url'] }}">
                        <div class="work-kicker">{{ $project['kicker'] }}</div>
                        <strong>{{ $project['title'] }}</strong>
                        <p>{{ $project['subtitle'] }}</p>
                        <div class="work-area-foot">
                            <span class="badge {{ $project['needs_action'] ? 'bg-label-warning' : 'bg-label-secondary' }}">{{ $project['badge'] }}</span>
                            <span class="btn btn-sm {{ $project['needs_action'] ? 'btn-primary' : 'btn-outline-secondary' }}">
                                {{ $project['needs_action'] ? 'Resolver' : 'Abrir' }}
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

        @if (app()->environment('testing'))
            <div class="card mb-4 border-warning">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Apresentação ao cliente</h5>
                    <span class="badge bg-label-warning">Dados mock</span>
                </div>
                <div class="list-group list-group-flush">
                    @if (config('rh.show_hiring'))
                        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="{{ route('work.demo', 'contratacoes') }}">
                            <div>
                                <strong>Quadro de contratações</strong>
                                <div class="text-muted small">Vagas livres da cota e pessoas em processo de entrada, por loja.</div>
                            </div>
                            <span class="badge bg-label-primary">Demo</span>
                        </a>
                    @endif
                    <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="{{ route('work.demo', 'demissoes') }}">
                        <div>
                            <strong>Quadro de demissões</strong>
                            <div class="text-muted small">Quem está saindo, vaga em abertura e excesso de cota.</div>
                        </div>
                        <span class="badge bg-label-primary">Demo</span>
                    </a>
                </div>
            </div>
        @endif
    </div>

    <style>
        .work-hero {
            display: flex;
            justify-content: space-between;
            gap: 1.5rem;
            flex-wrap: wrap;
            padding: 1.25rem 1.35rem;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
        }
        .work-hero h4 { color: #111827; }
        .work-hero p { max-width: 38rem; color: #374151; }
        .work-stats {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            align-items: stretch;
        }
        .work-stats div {
            min-width: 7.5rem;
            padding: 0.7rem 0.85rem;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
        }
        .work-stats strong { display: block; font-size: 1.35rem; color: #111827; line-height: 1.1; }
        .work-stats span { display: block; margin-top: 0.2rem; font-size: 0.75rem; color: #6b7280; }
        .work-section-title { font-size: 1rem; font-weight: 700; margin: 0 0 0.75rem; color: #111827; }
        .work-kicker {
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: 0.2rem;
        }
        .work-areas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr));
            gap: 0.85rem;
        }
        .work-area {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
            padding: 1rem 1.05rem 0.95rem;
            min-height: 100%;
            min-width: 0;
            overflow: hidden;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            text-decoration: none;
            color: inherit;
        }
        .work-area strong { display: block; color: #111827; overflow-wrap: anywhere; }
        .work-area p { margin: 0.2rem 0 0; color: #6b7280; font-size: 0.86rem; overflow-wrap: anywhere; }
        .work-area.is-due { box-shadow: inset 3px 0 0 #4f46e5; }
        .work-area.is-week {
            background: #fffbeb;
            border-color: #facc15;
            box-shadow: inset 3px 0 0 #eab308;
        }
        .work-area.is-quiet {
            background: #f3f4f6;
            border-color: #e5e7eb;
        }
        .work-area.is-quiet strong,
        .work-area.is-quiet .work-kicker { color: #6b7280; }
        .work-area.is-week:hover {
            border-color: #eab308;
            box-shadow: inset 3px 0 0 #eab308, 0 8px 18px rgba(15, 23, 42, 0.06);
        }
        .work-area-foot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem 0.75rem;
            margin-top: auto;
            padding-top: 0.85rem;
            flex-wrap: wrap;
            min-width: 0;
        }
        .work-area-foot .badge {
            flex: 1 1 8rem;
            min-width: 0;
            max-width: 100%;
            white-space: normal;
            overflow-wrap: anywhere;
            line-height: 1.25;
        }
        .work-area-foot .btn {
            flex: 0 0 auto;
            max-width: 100%;
        }
        .work-area:hover {
            border-color: #c7d2fe;
            color: inherit;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
        }
    </style>
</x-app-layout>
