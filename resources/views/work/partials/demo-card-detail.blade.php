<style>
    .dossier { color: #0f172a; }
    .dossier-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.15fr) minmax(260px, 0.85fr);
        gap: 1.25rem;
        align-items: start;
    }
    .dossier-layout.solo { grid-template-columns: 1fr; }
    .dossier-stage-row {
        display: flex;
        justify-content: space-between;
        gap: 0.75rem;
        align-items: flex-start;
        margin-bottom: 0.85rem;
    }
    .dossier-kicker {
        display: block;
        font-size: 0.68rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #64748b;
        margin-bottom: 0.2rem;
    }
    .dossier-stage {
        font-size: 1.15rem;
        font-weight: 700;
        line-height: 1.25;
        margin: 0;
    }
    .dossier-duty {
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
    .dossier-duty.rh { background: #fefce8; border-color: #fde047; color: #854d0e; }
    .dossier-duty.gestor { background: #fef2f2; border-color: #fecaca; color: #b91c1c; }
    .dossier-chips { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 1rem; }
    .dossier-chip {
        font-size: 0.78rem;
        color: #334155;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 999px;
        padding: 0.28rem 0.65rem;
    }
    .dossier-chip strong { font-weight: 700; }
    .dossier-stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.65rem;
        margin-bottom: 0.75rem;
    }
    .dossier-stat {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 0.75rem 0.8rem;
    }
    .dossier-stat strong {
        display: block;
        font-size: 1.15rem;
        line-height: 1.15;
        color: #0f172a;
    }
    .dossier-stat span {
        display: block;
        margin-top: 0.25rem;
        font-size: 0.72rem;
        color: #64748b;
        line-height: 1.3;
    }
    .dossier-stat.warn { background: #fffbeb; border-color: #fde68a; }
    .dossier-stat.warn strong { color: #b45309; }
    .dossier-stat.hot { background: #fef2f2; border-color: #fecaca; }
    .dossier-stat.hot strong { color: #b91c1c; }
    .dossier-note {
        margin: 0 0 1rem;
        padding: 0.7rem 0.85rem;
        border-radius: 12px;
        background: #eef2ff;
        border: 1px solid #c7d2fe;
        color: #312e81;
        font-size: 0.86rem;
        font-weight: 600;
    }
    .dossier-copy {
        margin: 0 0 1rem;
        color: #475569;
        font-size: 0.92rem;
        line-height: 1.45;
    }
    .dossier-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
    .dossier-link {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        text-decoration: none;
        font-size: 0.84rem;
        font-weight: 700;
        color: #3730a3;
        background: #fff;
        border: 1px solid #c7d2fe;
        border-radius: 999px;
        padding: 0.45rem 0.85rem;
    }
    .dossier-link:hover { background: #eef2ff; color: #312e81; }
    .dossier-steps {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 0.9rem 1rem 0.35rem;
    }
    .dossier-steps-head {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 0.5rem;
        margin-bottom: 0.65rem;
    }
    .dossier-steps-head h6 { margin: 0; font-size: 0.92rem; font-weight: 700; }
    .dossier-progress { font-size: 0.75rem; color: #64748b; font-weight: 700; }
    .dossier-step {
        display: flex;
        gap: 0.65rem;
        align-items: flex-start;
        padding: 0.45rem 0 0.55rem;
        border-top: 1px solid #e2e8f0;
        font-size: 0.84rem;
    }
    .dossier-mark {
        flex: 0 0 auto;
        width: 1.15rem;
        height: 1.15rem;
        margin-top: 0.05rem;
        border-radius: 999px;
        border: 2px solid #cbd5e1;
        background: #fff;
        position: relative;
    }
    .dossier-step.is-ok .dossier-mark { border-color: #16a34a; background: #16a34a; }
    .dossier-step.is-ok .dossier-mark::after {
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
    .dossier-step.is-ok { color: #166534; }
    .dossier-step.is-wait { color: #334155; }
    .dossier-step small {
        display: block;
        font-size: 0.68rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #94a3b8;
    }
    .dossier-step.is-ok small { color: #16a34a; }
    .dossier-section { margin-top: 1.25rem; }
    .dossier-section h6 { margin: 0 0 0.35rem; font-size: 0.92rem; }
    .demo-doc {
        min-height: 160px;
        border: 2px dashed #cbd5e1;
        border-radius: 12px;
        background: #f8fafc;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 1rem;
        color: #64748b;
        width: 100%;
    }
    .demo-doc.anexado { border-color: #86efac; background: #f0fdf4; color: #166534; cursor: zoom-in; }
    .demo-doc.pendente.atual { border-color: #818cf8; background: #eef2ff; color: #3730a3; cursor: pointer; }
    .demo-doc.pendente.bloqueado { border-color: #e5e7eb; background: #f8fafc; color: #94a3b8; cursor: not-allowed; }
    .demo-doc strong { display: block; font-size: 0.82rem; margin-bottom: 0.35rem; color: inherit; }
    .demo-doc span { font-size: 0.78rem; }
    .demo-doc img { max-height: 180px; width: auto; max-width: 100%; object-fit: contain; }
    button.demo-doc { font: inherit; }
    @media (max-width: 860px) {
        .dossier-layout { grid-template-columns: 1fr; }
        .dossier-stats { grid-template-columns: 1fr; }
    }
</style>

@php
    $steps = $card['steps'] ?? [];
    $doneSteps = collect($steps)->where('ok', true)->count();
    $hasFacts = array_key_exists('tenure_days', $card);
    $daysWithout = (int) ($card['days_without_daily'] ?? 0);
    $daysTone = $daysWithout >= 90 ? 'hot' : ($daysWithout >= 25 ? 'warn' : '');
    $store = trim((string) ($card['store'] ?? ''));
    $storeLabel = ($store === '' || $store === '—') ? 'Sem loja vinculada' : $store;
    $duty = $card['duty'] ?? null;
@endphp

<div class="dossier">
    <div class="dossier-layout {{ empty($steps) ? 'solo' : '' }}">
        <div>
            <div class="dossier-stage-row">
                <div>
                    <span class="dossier-kicker">Fase</span>
                    <p class="dossier-stage">{{ $card['stage'] ?? $card['tag'] ?? '—' }}</p>
                </div>
                @if (! empty($card['duty_label']))
                    <span class="dossier-duty {{ $duty }}">{{ $card['duty_label'] }}</span>
                @endif
            </div>

            <div class="dossier-chips">
                <span class="dossier-chip">Loja <strong>{{ $storeLabel }}</strong></span>
                @if (! empty($card['role']))
                    <span class="dossier-chip">Função <strong>{{ $card['role'] }}</strong></span>
                @endif
            </div>

            @if ($hasFacts)
                <div class="dossier-stats">
                    <div class="dossier-stat">
                        <strong>{{ $card['tenure_days'] }}</strong>
                        <span>dias de empresa</span>
                    </div>
                    <div class="dossier-stat {{ $daysTone }}">
                        <strong>{{ $daysWithout }}</strong>
                        <span>dias sem diária</span>
                    </div>
                    <div class="dossier-stat">
                        <strong>{{ $card['last_work_on'] ?? '—' }}</strong>
                        <span>{{ empty($card['last_work_on']) ? 'Sem diária lançada' : 'último dia trabalhado' }}</span>
                    </div>
                </div>
                @if (! empty($card['exam_label']))
                    <p class="dossier-note">{{ $card['exam_label'] }}</p>
                @endif
            @elseif (! empty($card['body']))
                <p class="dossier-copy">{{ $card['body'] }}</p>
            @endif

            <div class="dossier-actions">
                @if (! empty($card['process_id']))
                    <a class="dossier-link" href="{{ route('work.offboarding.show', $card['process_id']) }}">Abrir ficha completa</a>
                @endif

                @if (! empty($card['awaiting']) && ! empty($card['start_url']))
                    @can('Gerir desligamentos')
                        <form method="POST" action="{{ $card['start_url'] }}" class="m-0">
                            @csrf
                            <button class="btn btn-primary btn-sm" type="submit">Iniciar verificação do dossiê</button>
                        </form>
                    @endcan
                @endif

                @if (! empty($card['needs_inss_verification']) && ! empty($card['verify_url']))
                    @can('Atendimentos da contabilidade')
                        <form method="POST" action="{{ $card['verify_url'] }}" class="m-0">
                            @csrf
                            <button class="btn btn-primary btn-sm" type="submit">Marcar registrado no INSS</button>
                        </form>
                    @endcan
                @endif
            </div>
        </div>

        @if (! empty($steps))
            <div class="dossier-steps">
                <div class="dossier-steps-head">
                    <h6>Etapas do POP</h6>
                    <span class="dossier-progress">{{ $doneSteps }} de {{ count($steps) }}</span>
                </div>
                <ul class="list-unstyled mb-2" data-demo-steps>
                    @foreach ($steps as $step)
                        <li class="dossier-step {{ $step['ok'] ? 'is-ok' : 'is-wait' }}" data-ok="{{ $step['ok'] ? '1' : '0' }}">
                            <span class="dossier-mark" aria-hidden="true"></span>
                            <span>
                                <small>{{ $step['ok'] ? 'OK' : 'Pendente' }}</small>
                                {{ $step['label'] }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    @if (! empty($card['documents']))
        @php
            $documents = $card['documents'];
            $nextPending = collect($documents)->search(fn ($document) => ($document['status'] ?? '') !== 'anexado');
        @endphp
        <div class="dossier-section">
            <h6>Documentos</h6>
            <p class="text-muted small mb-3">Anexe na ordem. Só o documento da vez aceita imagem.</p>
            <div class="row g-3" data-demo-docs>
                @foreach ($documents as $index => $document)
                    @php
                        $isDone = ($document['status'] ?? '') === 'anexado';
                        $isCurrent = ! $isDone && $nextPending === $index;
                        $state = $isDone ? 'anexado' : ($isCurrent ? 'pendente atual' : 'pendente bloqueado');
                        $previous = $index > 0 ? ($documents[$index - 1]['label'] ?? 'o documento anterior') : null;
                    @endphp
                    <div class="col-md-4">
                        <button type="button"
                            class="demo-doc {{ $state }}"
                            data-demo-doc
                            data-index="{{ $index }}"
                            data-status="{{ $isDone ? 'anexado' : 'pendente' }}"
                            data-label="{{ $document['label'] }}"
                            data-src="{{ $document['src'] ?? '' }}"
                            data-stage="{{ $document['stage'] ?? '' }}"
                            data-kind="{{ $document['kind'] ?? '' }}"
                            @if (! empty($document['upload_url'])) data-upload-url="{{ $document['upload_url'] }}" @endif
                            @if ($previous) data-previous="{{ $previous }}" @endif>
                            <strong>{{ $document['label'] }}</strong>
                            @if ($isDone && ! empty($document['src']))
                                @php
                                    $src = $document['src'];
                                    $imgSrc = str_starts_with($src, '/') || str_starts_with($src, 'http') || str_starts_with($src, 'data:')
                                        ? $src
                                        : asset($src);
                                @endphp
                                <img src="{{ $imgSrc }}" alt="{{ $document['label'] }}">
                            @elseif ($isDone)
                                <span>Anexo ilustrativo sem arquivo de amostra.</span>
                            @elseif ($isCurrent)
                                <span>Clique para enviar a imagem agora.</span>
                            @else
                                <span>Bloqueado até anexar {{ $previous }}.</span>
                            @endif
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
