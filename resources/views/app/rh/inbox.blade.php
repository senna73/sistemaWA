<x-app-layout>
    <div class="container work-home">
        <div class="work-hero mb-4">
            <div>
                <div class="text-muted">WA Serviços · visão do super admin</div>
                <h4 class="mb-1">Acompanhamento RH</h4>
                <p class="mb-0">Como o trabalho do RH está andando: etapa de cada processo, quem está com a fila e o que já fechou neste mês.</p>
            </div>
            <div class="work-stats">
                <div><strong>{{ $open_count }}</strong><span>Em andamento</span></div>
                <div><strong>{{ $with_rh }}</strong><span>Com o RH</span></div>
                <div><strong>{{ $with_gestor }}</strong><span>Na sua análise</span></div>
                <div><strong>{{ $waiting }}</strong><span>Aguardando outra área</span></div>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
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
            $canDecideAnalysis = auth()->user()->isOwner()
                || auth()->user()->can('Minhas Análises Direção')
                || in_array(auth()->user()->role, ['admin', 'dev'], true);
            $visible = collect($stages)->filter(fn ($stage) => $stage['count'] > 0);
        @endphp

        @if ($analysisQueue->isNotEmpty())
            <div class="card mb-4 analysis-queue">
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
                <div class="card-body text-muted mb-0">Nenhum processo aberto com o RH agora.</div>
            </div>
        @elseif ($visible->isNotEmpty())
            @foreach ($visible as $stage)
                <div class="card mb-3">
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
        .work-stats div {
            min-width: 7.5rem;
            padding: 0.7rem 0.85rem;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
        }
        .work-stats strong { display: block; font-size: 1.35rem; color: #111827; line-height: 1.1; }
        .work-stats span { display: block; margin-top: 0.2rem; font-size: 0.75rem; color: #6b7280; }
        .analysis-queue { border-color: #fecaca; }
        .analysis-queue .card-header { background: #fef2f2; }
        .analysis-actions .form-label { color: #6b7280; }
    </style>
</x-app-layout>
