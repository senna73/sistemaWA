<x-app-layout>
    <style>
        .ficha { color: #0f172a; }
        .ficha-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .ficha-top h5 { margin: 0; font-size: 1.15rem; font-weight: 700; }
        .ficha-back {
            display: inline-flex;
            align-items: center;
            text-decoration: none;
            font-size: 0.84rem;
            font-weight: 700;
            color: #3730a3;
            background: #fff;
            border: 1px solid #c7d2fe;
            border-radius: 999px;
            padding: 0.4rem 0.85rem;
        }
        .ficha-back:hover { background: #eef2ff; color: #312e81; }
        .ficha-sheet, .ficha-panel {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
            margin-bottom: 1rem;
        }
        .ficha-sheet { padding: 1.15rem 1.25rem 1.25rem; }
        .ficha-panel { padding: 1rem 1.15rem 1.15rem; }
        .ficha-panel h6, .ficha-side h6 {
            margin: 0;
            font-size: 0.92rem;
            font-weight: 700;
        }
        .ficha-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(260px, 0.85fr);
            gap: 1.25rem;
            align-items: start;
        }
        .ficha-kicker {
            display: block;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 0.2rem;
        }
        .ficha-stage-row {
            display: flex;
            justify-content: space-between;
            gap: 0.75rem;
            align-items: flex-start;
            margin-bottom: 0.85rem;
        }
        .ficha-stage { font-size: 1.15rem; font-weight: 700; line-height: 1.25; margin: 0; }
        .ficha-duty {
            flex: 0 0 auto;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            padding: 0.35rem 0.6rem;
            border-radius: 999px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #475569;
        }
        .ficha-duty.rh { background: #fefce8; border-color: #fde047; color: #854d0e; }
        .ficha-duty.gestor { background: #fef2f2; border-color: #fecaca; color: #b91c1c; }
        .ficha-chips { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 1rem; }
        .ficha-chip {
            font-size: 0.78rem;
            color: #334155;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 999px;
            padding: 0.28rem 0.65rem;
        }
        .ficha-chip strong { font-weight: 700; }
        .ficha-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.65rem;
            margin-bottom: 0.75rem;
        }
        .ficha-stats.slim { grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); }
        .ficha-stat {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.75rem 0.8rem;
            min-width: 0;
        }
        .ficha-stat strong {
            display: block;
            font-size: 1.15rem;
            line-height: 1.2;
            color: #0f172a;
            overflow-wrap: anywhere;
        }
        .ficha-stat span { display: block; margin-top: 0.25rem; font-size: 0.72rem; color: #64748b; line-height: 1.3; }
        .ficha-stat.warn { background: #fffbeb; border-color: #fde68a; }
        .ficha-stat.warn strong { color: #b45309; }
        .ficha-stat.hot { background: #fef2f2; border-color: #fecaca; }
        .ficha-stat.hot strong { color: #b91c1c; }
        .ficha-note {
            margin: 0 0 0.85rem;
            padding: 0.7rem 0.85rem;
            border-radius: 12px;
            background: #eef2ff;
            border: 1px solid #c7d2fe;
            color: #312e81;
            font-size: 0.86rem;
            font-weight: 600;
        }
        .ficha-copy { margin: 0 0 0.85rem; color: #475569; font-size: 0.92rem; line-height: 1.45; }
        .ficha-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
        .ficha-side {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 0.9rem 1rem 0.35rem;
        }
        .ficha-side-head {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 0.5rem;
            margin-bottom: 0.65rem;
        }
        .ficha-progress { font-size: 0.75rem; color: #64748b; font-weight: 700; }
        .ficha-step {
            display: flex;
            gap: 0.65rem;
            align-items: flex-start;
            padding: 0.45rem 0 0.55rem;
            border-top: 1px solid #e2e8f0;
            font-size: 0.84rem;
        }
        .ficha-mark {
            flex: 0 0 auto;
            width: 1.15rem;
            height: 1.15rem;
            margin-top: 0.05rem;
            border-radius: 999px;
            border: 2px solid #cbd5e1;
            background: #fff;
            position: relative;
        }
        .ficha-step.is-ok .ficha-mark { border-color: #16a34a; background: #16a34a; }
        .ficha-step.is-ok .ficha-mark::after {
            content: "";
            position: absolute;
            left: 0.28rem;
            top: 0.12rem;
            width: 0.32rem;
            height: 0.55rem;
            border: solid #fff;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        .ficha-step.is-ok { color: #166534; }
        .ficha-step.is-wait { color: #334155; }
        .ficha-step small {
            display: block;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #94a3b8;
        }
        .ficha-step.is-ok small { color: #16a34a; }
        .ficha-doc {
            height: 100%;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #f8fafc;
            padding: 0.9rem;
        }
        .ficha-doc strong { display: block; font-size: 0.86rem; margin-bottom: 0.25rem; }
        .ficha-doc img { max-height: 160px; width: auto; max-width: 100%; object-fit: contain; border-radius: 8px; }
        .ficha-history { list-style: none; margin: 0.75rem 0 0; padding: 0; }
        .ficha-history li {
            padding: 0.55rem 0;
            border-top: 1px solid #e2e8f0;
            font-size: 0.84rem;
            color: #334155;
        }
        .ficha-history time { display: block; font-size: 0.72rem; font-weight: 700; color: #64748b; }
        @media (max-width: 860px) {
            .ficha-layout, .ficha-stats { grid-template-columns: 1fr; }
            .ficha-top { align-items: flex-start; }
        }
    </style>

    <div class="container ficha">
        @php
            $c = $process->collaborator;
            $duty = $process->duty();
            $daysWithout = (int) ($c?->daysWithoutDaily() ?? 0);
            $daysTone = $daysWithout >= 90 ? 'hot' : ($daysWithout >= 25 ? 'warn' : '');
            $store = trim((string) ($c?->homeCompany?->name ?: ($c?->group ?? '')));
            $storeLabel = ($store === '' || $store === '—') ? 'Sem loja vinculada' : $store;
            $lastWork = $process->last_work_day ?? $c?->lastDailyAt();
            $steps = $process->popSteps();
            $doneSteps = collect($steps)->where('ok', true)->count();
            $situation = $process->origin === 'coordinator' && $process->kind === 'dismissal'
                ? 'Coordenador solicitar desligamento'
                : $process->kindLabel();
            $registro = $process->accounting_registered === null ? '—' : ($process->accounting_registered ? 'sim' : 'não');
        @endphp

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="ficha-top">
            <h5>{{ $process->kindLabel() }} · {{ $c?->name }}</h5>
            <div class="d-flex gap-2">
                @if ($c)
                    <a class="ficha-back" href="{{ route('work.offboarding.collaborator', $process) }}">Ver dados do colaborador</a>
                @endif
                <a class="ficha-back" href="{{ route('work.project', 'offboarding') }}">Voltar</a>
            </div>
        </div>

        <div class="ficha-sheet">
            <div class="ficha-layout">
                <div>
                    <div class="ficha-stage-row">
                        <div>
                            <span class="ficha-kicker">Fase</span>
                            <p class="ficha-stage">{{ $process->label() }}</p>
                        </div>
                        @if (in_array($duty, ['rh', 'gestor'], true))
                            <span class="ficha-duty {{ $duty }}">{{ $process->dutyLabel() }}</span>
                        @endif
                    </div>

                    <div class="ficha-chips">
                        <span class="ficha-chip">Loja <strong>{{ $storeLabel }}</strong></span>
                        <span class="ficha-chip">Função <strong>{{ $c?->job_title ?: '—' }}</strong></span>
                        <span class="ficha-chip">Tel <strong>{{ $c?->mobile ?: '—' }}</strong></span>
                        <span class="ficha-chip">Grupo <strong>{{ $c?->group ?: '—' }}</strong></span>
                        <span class="ficha-chip">{{ $situation }}</span>
                    </div>

                    <div class="ficha-stats">
                        <div class="ficha-stat">
                            <strong>{{ $process->tenureDays() }}</strong>
                            <span>dias de empresa</span>
                        </div>
                        <div class="ficha-stat {{ $daysTone }}">
                            <strong>{{ $daysWithout }}</strong>
                            <span>dias sem diária</span>
                        </div>
                        <div class="ficha-stat">
                            <strong>{{ $lastWork?->format('d/m/Y') ?? '—' }}</strong>
                            <span>{{ $lastWork ? 'último dia trabalhado' : 'Sem diária lançada' }}</span>
                        </div>
                    </div>

                    <div class="ficha-stats slim">
                        <div class="ficha-stat">
                            <strong>{{ $c?->hiredAt()?->format('d/m/Y') ?? '—' }}</strong>
                            <span>admissão</span>
                        </div>
                        <div class="ficha-stat">
                            <strong>{{ $process->wa_daily_count ?? '—' }}</strong>
                            <span>diárias WA</span>
                        </div>
                        <div class="ficha-stat">
                            <strong>{{ $process->inss_daily_count ?? '—' }}</strong>
                            <span>INSS{{ $process->inss_daily_count !== null ? ' · diferença '.$process->inssDifference() : '' }}</span>
                        </div>
                        <div class="ficha-stat">
                            <strong>{{ $process->last_exam_clinic ?: '—' }}</strong>
                            <span>clínica · registro {{ $registro }}</span>
                        </div>
                    </div>

                    <p class="ficha-note">{{ $process->examStepLabel() }}</p>

                    @if (filled($process->reason))
                        <p class="ficha-copy">{{ $process->reason }}</p>
                    @endif

                    @if ($process->awaitsRh())
                        <p class="ficha-copy">A verificação do dossiê começa depois deste atendimento. O POP desta situação fica disponível em seguida.</p>
                        <div class="ficha-actions">
                            @can('Gerir desligamentos')
                                <form method="POST" action="{{ route('work.offboarding.start', $process) }}" class="m-0">
                                    @csrf
                                    <button class="btn btn-primary" type="submit">Iniciar verificação do dossiê</button>
                                </form>
                            @endcan
                        </div>
                    @endif
                </div>

                <div class="ficha-side">
                    <div class="ficha-side-head">
                        <h6>Etapas do POP</h6>
                        <span class="ficha-progress">{{ $doneSteps }} de {{ count($steps) }}</span>
                    </div>
                    <ul class="list-unstyled mb-2">
                        @foreach ($steps as $step)
                            <li class="ficha-step {{ $step['ok'] ? 'is-ok' : 'is-wait' }}">
                                <span class="ficha-mark" aria-hidden="true"></span>
                                <span>
                                    <small>{{ $step['ok'] ? 'OK' : 'Pendente' }}</small>
                                    {{ $step['label'] }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        @if ($process->attachments->isNotEmpty())
            <div class="ficha-panel">
                <h6 class="mb-3">Documentos anexados</h6>
                <div class="row g-3">
                    @foreach ($process->attachments as $attachment)
                        <div class="col-md-4">
                            <div class="ficha-doc">
                                <strong>{{ $attachment->kindLabel() }}</strong>
                                <p class="small text-muted mb-2">{{ $attachment->original_name }}</p>
                                @if ($attachment->existsOnDisk() && $attachment->isImage())
                                    <a href="{{ route('work.offboarding.attachment', [$process, $attachment]) }}" target="_blank" rel="noopener">
                                        <img src="{{ route('work.offboarding.attachment', [$process, $attachment]) }}" alt="{{ $attachment->kindLabel() }}" class="mb-2">
                                    </a>
                                @endif
                                @if ($attachment->existsOnDisk())
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('work.offboarding.attachment', [$process, $attachment]) }}" target="_blank" rel="noopener">
                                        {{ $attachment->isPdf() ? 'Abrir PDF' : 'Baixar / visualizar' }}
                                    </a>
                                @else
                                    <span class="text-muted small">Arquivo indisponível no disco.</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @can('Gerir desligamentos')
            @if (! $process->awaitsRh())
            <div class="ficha-panel">
                <h6 class="mb-3">Conferência RH</h6>
                <form method="POST" action="{{ route('work.offboarding.conference', $process) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Diárias no INSS</label>
                            <input type="number" name="inss_daily_count" class="form-control" value="{{ $process->inss_daily_count }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Registro na contabilidade</label>
                            <select name="accounting_registered" class="form-select">
                                <option value="">—</option>
                                <option value="1" @selected($process->accounting_registered === true)>Sim</option>
                                <option value="0" @selected($process->accounting_registered === false)>Não</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Último exame</label>
                            <select name="last_exam_clinic" class="form-select">
                                <option value="">—</option>
                                <option value="cliomed" @selected($process->last_exam_clinic === 'cliomed')>Cliomed</option>
                                <option value="conserta" @selected($process->last_exam_clinic === 'conserta')>Conserta</option>
                            </select>
                        </div>
                        @if ($process->kind === 'collaborator_resignation')
                            <div class="col-md-4">
                                <label class="form-label">Carta</label>
                                <select name="letter_status" class="form-select">
                                    <option value="pendente" @selected($process->letter_status === 'pendente')>Pendente</option>
                                    <option value="erro" @selected($process->letter_status === 'erro')>Com erro</option>
                                    <option value="anexada" @selected($process->letter_status === 'anexada')>Anexada</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Arquivo da carta</label>
                                <input type="file" name="letter" class="form-control">
                            </div>
                        @endif
                        <div class="col-12">
                            <button class="btn btn-primary" type="submit">Salvar conferência e classificar</button>
                        </div>
                    </div>
                </form>
            </div>
            @endif
        @endcan

        @can('Atendimentos da contabilidade')
            @if (in_array($process->status, ['aguardando_contabilidade', 'aguardando_documentacao']))
                <div class="ficha-panel">
                    <h6 class="mb-3">Registro no INSS</h6>
                    <form method="POST" action="{{ route('work.offboarding.register', $process) }}" enctype="multipart/form-data" class="row g-2">
                        @csrf
                        <div class="col-md-4">
                            <label class="form-label">Registrado no INSS</label>
                            <select name="accounting_registered" class="form-select" required>
                                <option value="1" @selected($process->accounting_registered === true)>Sim</option>
                                <option value="0" @selected($process->accounting_registered === false)>Não</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Documento devolvido</label>
                            <input type="file" name="file" class="form-control">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button class="btn btn-primary" type="submit">Salvar registro</button>
                        </div>
                    </form>
                </div>
            @endif
        @endcan

        @if ($process->isDirectionQueue() && (auth()->user()->isOwner() || auth()->user()->can('Minhas Análises Direção') || in_array(auth()->user()->role, ['admin','dev'], true)))
            <div class="ficha-panel">
                <h6 class="mb-3">Minhas Análises - Direção</h6>
                <form method="POST" action="{{ route('work.offboarding.direction', $process) }}">
                    @csrf
                    <div class="mb-3">
                        <select name="decision" class="form-select" required>
                            <option value="authorize">Autorizar correção / liberar</option>
                            <option value="release_pending">Liberar com pendência</option>
                            <option value="return_rh">Devolver ao RH</option>
                            @if ($process->kind === 'inactivity_dismissal')
                                <option value="keep">Manter em análise</option>
                                <option value="allow_return">Liberar retorno / diária</option>
                            @endif
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correção de diárias (qtd)</label>
                        <input type="number" name="correction_qty" class="form-control" min="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Justificativa</label>
                        <textarea name="justification" class="form-control" required></textarea>
                    </div>
                    <button class="btn btn-primary" type="submit">Registrar decisão</button>
                </form>
            </div>
        @endif

        @can('Gerir desligamentos')
            @if (in_array($process->status, ['marcacao_exame','faltou_reagendar']))
                <div class="ficha-panel">
                    <h6 class="mb-3">Exame Cliomed</h6>
                    <form method="POST" action="{{ route('work.offboarding.exam', $process) }}" class="row g-2 mb-3">
                        @csrf
                        <div class="col-md-5"><input type="datetime-local" name="exam_at" class="form-control" required></div>
                        <div class="col-md-4"><input name="exam_location" class="form-control" value="Cliomed"></div>
                        <div class="col-md-3"><button class="btn btn-primary" type="submit">Agendar</button></div>
                    </form>
                    <form method="POST" action="{{ route('work.offboarding.aso', $process) }}" enctype="multipart/form-data" class="d-inline-block me-2 mb-2">
                        @csrf
                        <input type="file" name="aso" class="form-control mb-2">
                        <button class="btn btn-sm btn-success" type="submit">ASO anexado</button>
                    </form>
                    <form method="POST" action="{{ route('work.offboarding.miss', $process) }}" class="d-inline-block me-2 mb-2">
                        @csrf
                        <button class="btn btn-sm btn-outline-warning" type="submit">Faltou ao exame</button>
                    </form>
                    <form method="POST" action="{{ route('work.offboarding.waiver', $process) }}" enctype="multipart/form-data" class="d-inline-block me-2 mb-2">
                        @csrf
                        <input type="file" name="waiver" class="form-control mb-2">
                        <button class="btn btn-sm btn-outline-secondary" type="submit">Declaração de dispensa</button>
                    </form>
                    <form method="POST" action="{{ route('work.offboarding.mail', $process) }}" class="d-inline-block mb-2">
                        @csrf
                        <input type="hidden" name="start_only" value="1">
                        <button class="btn btn-sm btn-outline-danger" type="submit">Sem resposta → Correio</button>
                    </form>
                </div>
            @endif

            @if ($process->status === 'demissao_correio')
                <div class="ficha-panel">
                    <h6 class="mb-3">Demissões via Correio</h6>
                    <form method="POST" action="{{ route('work.offboarding.mail', $process) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <input name="mail_tracking" class="form-control" placeholder="Código de rastreio / AR" required>
                        </div>
                        <div class="mb-3"><input type="file" name="mail" class="form-control"></div>
                        <button class="btn btn-primary" type="submit">Confirmar entrega</button>
                    </form>
                </div>
            @endif

            @if (in_array($process->status, ['aguardando_contabilidade','aguardando_documentacao']))
                <div class="ficha-panel">
                    <h6 class="mb-3">Documentação da contabilidade</h6>
                    <form method="POST" action="{{ route('work.offboarding.docs', $process) }}" class="mb-3">
                        @csrf
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="docs_received" value="1" id="docs_received" @checked($process->docs_received)>
                            <label class="form-check-label" for="docs_received">Documentação recebida</label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="docs_checked" value="1" id="docs_checked" @checked($process->docs_checked)>
                            <label class="form-check-label" for="docs_checked">Documentos conferidos</label>
                        </div>
                        <button class="btn btn-outline-primary" type="submit">Salvar</button>
                    </form>
                    <form method="POST" action="{{ route('work.offboarding.complete', $process) }}">
                        @csrf
                        <button class="btn btn-danger" type="submit">Baixa e concluir</button>
                    </form>
                </div>
            @endif

            @if ($process->kind === 'transfer' && $process->isOpen())
                <div class="ficha-panel">
                    <h6 class="mb-3">Transferência</h6>
                    <form method="POST" action="{{ route('work.offboarding.transfer', $process) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Lojas oferecidas</label>
                            <textarea name="offered_stores" class="form-control">{{ $process->offered_stores }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Resposta do colaborador</label>
                            <textarea name="collaborator_reply" class="form-control">{{ $process->collaborator_reply }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nova loja</label>
                            <select name="new_company_id" class="form-select" required>
                                <option value="">Selecione</option>
                                @foreach ($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <input name="new_role" class="form-control" placeholder="Função" value="{{ $process->new_role }}">
                        </div>
                        <div class="mb-3">
                            <input type="date" name="transfer_start_date" class="form-control">
                        </div>
                        <button class="btn btn-success" type="submit">Concluir transferência</button>
                    </form>
                    <form method="POST" action="{{ route('work.offboarding.transfer', $process) }}" class="mt-2">
                        @csrf
                        <input type="hidden" name="reject" value="1">
                        <button class="btn btn-outline-secondary" type="submit">Não aceitou / sem vaga</button>
                    </form>
                </div>
            @endif

            <form method="POST" action="{{ route('work.offboarding.cancel', $process) }}" class="mb-3">
                @csrf
                <button class="btn btn-outline-secondary" type="submit">Cancelar processo</button>
            </form>
        @endcan

        <div class="ficha-panel">
            <h6>Histórico</h6>
            <ul class="ficha-history">
                @forelse ($process->events->sortByDesc('id') as $event)
                    <li>
                        <time>{{ $event->created_at->format('d/m/Y H:i') }}</time>
                        {{ $event->actor?->name }} · {{ $event->event_type }} · {{ $event->notes }}
                    </li>
                @empty
                    <li>Nenhum evento registrado.</li>
                @endforelse
            </ul>
        </div>
    </div>
</x-app-layout>
