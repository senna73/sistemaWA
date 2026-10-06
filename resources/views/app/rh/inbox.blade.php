<x-app-layout>
    @php
        $focus = $focus ?? 'open';
        $statUrl = function (string $target) use ($focus): string {
            $query = $focus === $target ? [] : ['focus' => $target];

            return route('rh.inbox', $query).'#rh-queues';
        };
    @endphp
    <div class="container work-home">
        <div class="work-hero mb-4">
            <div>
                <div class="text-muted">WA Serviços · visão do super admin</div>
                <h4 class="mb-1">Acompanhamento RH</h4>
                <p class="mb-0">Espelho do RH Controle: o que o RH tem para fazer, o que já está em atendimento e o que espera a sua conferência.</p>
            </div>
            <div class="work-stats">
                <a class="work-stat {{ $focus === 'open' ? 'is-active' : '' }}" href="{{ $statUrl('open') }}" @if ($focus === 'open') aria-current="page" @endif>
                    <strong>{{ $open_count }}</strong><span>Em andamento</span>
                </a>
                <a class="work-stat {{ $focus === 'rh' ? 'is-active' : '' }}" href="{{ $statUrl('rh') }}" @if ($focus === 'rh') aria-current="page" @endif>
                    <strong>{{ $with_rh }}</strong><span>Com o RH</span>
                </a>
                <a class="work-stat {{ $focus === 'gestor' ? 'is-active' : '' }}" href="{{ $statUrl('gestor') }}" @if ($focus === 'gestor') aria-current="page" @endif>
                    <strong>{{ $with_gestor }}</strong><span>Na sua análise</span>
                </a>
                <a class="work-stat {{ $focus === 'wait' ? 'is-active' : '' }}" href="{{ $statUrl('wait') }}" @if ($focus === 'wait') aria-current="page" @endif>
                    <strong>{{ $waiting }}</strong><span>Aguardando outra área</span>
                </a>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        @if (! empty($rh_areas))
            <div class="mb-4">
                <h5 class="mb-1">Quadro do RH</h5>
                <p class="text-muted small mb-3">O mesmo recorte do RH Controle, para revisar sem operar a fila.</p>
                <div class="work-areas">
                    @foreach ($rh_areas as $project)
                        <a class="work-area {{ $project['tone'] ?? ($project['needs_action'] ? 'is-due' : '') }}" href="{{ $project['url'] }}">
                            <div class="work-kicker">{{ $project['kicker'] }}</div>
                            <strong>{{ $project['title'] }}</strong>
                            <p>{{ $project['subtitle'] }}</p>
                            <div class="work-area-foot">
                                <span class="badge {{ $project['needs_action'] ? 'bg-label-warning' : 'bg-label-secondary' }}">{{ $project['badge'] }}</span>
                                <span class="btn btn-sm {{ $project['needs_action'] ? 'btn-primary' : 'btn-outline-secondary' }}">Conferir</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="d-flex flex-wrap gap-2 mb-4">
            <span class="badge {{ ($cliomed['needs_action'] ?? false) ? 'bg-label-warning' : 'bg-label-secondary' }}">
                Cliomed: {{ $cliomed['badge'] ?? 'sem conferência' }}
            </span>
            <span class="badge {{ $inactivity > 0 ? 'bg-label-warning' : 'bg-label-secondary' }}">
                Inatividade em aberto: {{ $inactivity }}
            </span>
        </div>

        @php
            $analysisQueue = collect($analysis_queue ?? []);
            if (in_array($focus, ['rh', 'wait'], true)) {
                $analysisQueue = collect();
            }
            $canDecideAnalysis = auth()->user()->isOwner()
                || auth()->user()->can('Minhas Análises Direção')
                || in_array(auth()->user()->role, ['admin', 'dev'], true);
            $visible = collect($stages)->map(function (array $stage) use ($focus) {
                $items = collect($stage['items']);
                if ($focus === 'rh') {
                    $items = $items->filter(fn ($process) => $process->duty() === \App\Models\OffboardingProcess::DUTY_RH)->values();
                } elseif ($focus === 'wait') {
                    $items = $items->filter(fn ($process) => $process->duty() === \App\Models\OffboardingProcess::DUTY_WAIT)->values();
                } elseif ($focus === 'gestor') {
                    $items = collect();
                }

                return array_merge($stage, [
                    'items' => $items,
                    'count' => $items->count(),
                ]);
            })->filter(fn ($stage) => $stage['count'] > 0);
            $focusLabels = [
                'open' => 'em andamento',
                'rh' => 'com o RH',
                'gestor' => 'na sua análise',
                'wait' => 'aguardando outra área',
            ];
            $focusedCard = $focus !== 'open';
        @endphp

        <div id="rh-queues">

        @if ($analysisQueue->isNotEmpty())
            <div class="card mb-4 analysis-queue {{ $focusedCard ? 'is-focused' : '' }}">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">Na sua análise</h5>
                        <div class="text-muted small">Estes cards só andam com a sua decisão.</div>
                    </div>
                    <span class="badge bg-label-danger">{{ $analysisQueue->count() }}</span>
                </div>
                <div class="list-group list-group-flush">
                    @foreach ($analysisQueue as $process)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                <div>
                                    <strong>{{ $process->collaborator->name }}</strong>
                                    <div class="text-muted small">
                                        {{ $process->kindLabel() }}
                                        @if ($process->collaborator->homeCompany)
                                            · {{ $process->collaborator->homeCompany->name }}
                                        @endif
                                        · {{ $process->label() }}
                                    </div>
                                    @if (filled($process->reason))
                                        <div class="small mt-1">{{ $process->reason }}</div>
                                    @endif
                                </div>
                                <a class="small text-nowrap" href="{{ route('work.offboarding.show', $process) }}">Abrir ficha</a>
                            </div>

                            @if ($canDecideAnalysis)
                                <form method="POST" action="{{ route('work.offboarding.direction', $process) }}" class="analysis-actions mt-3">
                                    @csrf
                                    <label class="form-label small mb-1">Justificativa</label>
                                    <textarea name="justification" class="form-control form-control-sm mb-2" rows="2" placeholder="Obrigatória para liberar com pendência."></textarea>
                                    <div class="d-flex flex-wrap gap-2">
                                        <button class="btn btn-sm btn-primary" type="submit" name="decision" value="authorize">Autorizar</button>
                                        <button class="btn btn-sm btn-outline-primary" type="submit" name="decision" value="release_pending">Liberar com pendência</button>
                                        <button class="btn btn-sm btn-outline-secondary" type="submit" name="decision" value="return_rh">Devolver ao RH</button>
                                        @if ($process->kind === 'inactivity_dismissal')
                                            <button class="btn btn-sm btn-outline-warning" type="submit" name="decision" value="keep">Manter em análise</button>
                                            <button class="btn btn-sm btn-outline-success" type="submit" name="decision" value="allow_return">Liberar retorno</button>
                                        @endif
                                    </div>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($visible->isEmpty() && $analysisQueue->isEmpty())
            <div class="card mb-4">
                <div class="card-body text-muted mb-0">
                    @if ($focus === 'open')
                        Nenhum processo aberto com o RH agora.
                    @else
                        Nenhum processo {{ $focusLabels[$focus] }} agora.
                    @endif
                </div>
            </div>
        @elseif ($visible->isNotEmpty())
            @foreach ($visible as $stage)
                <div class="card mb-3 {{ $focusedCard ? 'is-focused' : '' }}">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ $stage['label'] }}</h5>
                        <span class="badge bg-label-primary">{{ $stage['count'] }}</span>
                    </div>
                    <div class="list-group list-group-flush">
                        @foreach ($stage['items'] as $process)
                            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-3" href="{{ route('work.offboarding.show', $process) }}">
                                <div>
                                    <strong>{{ $process->collaborator->name }}</strong>
                                    <div class="text-muted small">
                                        {{ $process->kindLabel() }}
                                        @if ($process->collaborator->homeCompany)
                                            · {{ $process->collaborator->homeCompany->name }}
                                        @endif
                                        · {{ $process->dutyLabel() }}
                                    </div>
                                </div>
                                <span class="text-muted small text-nowrap">{{ $process->updated_at?->diffForHumans() }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif

        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Concluídos neste mês</h5>
            </div>
            <div class="list-group list-group-flush">
                @forelse ($done_month as $process)
                    <a class="list-group-item list-group-item-action" href="{{ route('work.offboarding.show', $process) }}">
                        <strong>{{ $process->collaborator?->name }}</strong>
                        <span class="text-muted small">· {{ $process->label() }}</span>
                    </a>
                @empty
                    <div class="list-group-item text-muted">Nada concluído neste mês.</div>
                @endforelse
            </div>
        </div>
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
        .work-stats { display: flex; gap: 0.75rem; flex-wrap: wrap; }
        .work-stat {
            min-width: 7.5rem;
            padding: 0.7rem 0.85rem;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            text-decoration: none;
            color: inherit;
            cursor: pointer;
        }
        .work-stat:hover {
            border-color: #c7d2fe;
            color: inherit;
            background: #eef2ff;
        }
        .work-stat.is-active {
            background: #eef2ff;
            border-color: #6366f1;
            box-shadow: inset 0 0 0 1px #6366f1;
        }
        .work-stat.is-active strong { color: #3730a3; }
        .work-stats strong { display: block; font-size: 1.35rem; color: #111827; line-height: 1.1; }
        .work-stats span { display: block; margin-top: 0.2rem; font-size: 0.75rem; color: #6b7280; }
        .analysis-queue { border-color: #fecaca; }
        .analysis-queue .card-header { background: #fef2f2; }
        .analysis-actions .form-label { color: #6b7280; }
        #rh-queues { scroll-margin-top: 5rem; }
        .card.is-focused {
            border-color: #6366f1;
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.18);
        }
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
        .work-area:hover {
            border-color: #c7d2fe;
            color: inherit;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
        }
    </style>
</x-app-layout>
