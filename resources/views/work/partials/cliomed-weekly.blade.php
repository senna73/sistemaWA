@php
    $recon = $weekly->reconciliation ?? [];
    $openKey = $openKey ?? '';
    $lookupKey = $lookupKey ?? '';
    $lookupTerm = $lookupTerm ?? '';
    $lookupPeople = $lookupPeople ?? collect();
    $hasReport = ($weekly->report_count !== null) || $recon !== [];
@endphp

<p class="text-muted">
    Semana de {{ $weekly->week_of->format('d/m/Y') }}
    · WA: {{ $weekly->wa_count }}
    @if (! is_null($weekly->report_count))
        · Relatório: {{ $weekly->report_count }}
    @endif
    · {{ $state['needs_action'] ? 'amarelo até finalizar' : 'em dia' }}
</p>
<p class="small text-muted">
    Toda semana a conferência fica amarela. Envie a planilha da Cliomed (Nome Unidade, Nome Setor, Nome Cargo, Nome Funcionário).
    Cada pendência abre um card com a correção daquela situação. O card só fica cinza depois que tudo estiver tratado e a conferência for finalizada.
    Para subir um relatório novo, descarte esta conferência — as pendências atuais saem da tela.
</p>

@can('Gerir desligamentos')
    @if ($weekly->isOpen() && ! $hasReport)
        <form method="POST" action="{{ route('work.clinic.weekly') }}" enctype="multipart/form-data" class="mb-3">
            @csrf
            <input type="hidden" name="check_id" value="{{ $weekly->id }}">
            <div class="mb-3">
                <label class="form-label">Relatório Cliomed (.xlsx ou .csv)</label>
                <input type="file" name="attachment" class="form-control" accept=".xlsx,.csv,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required>
            </div>
            <button class="btn btn-primary" type="submit">Comparar com o sistema</button>
        </form>
    @elseif ($weekly->isOpen() && $hasReport)
        <form method="POST" action="{{ route('work.cliomed.discard') }}" class="mb-3" onsubmit="return confirm('Descartar o relatório e todas as pendências desta semana? Você poderá enviar a planilha atualizada em seguida.');">
            @csrf
            <input type="hidden" name="check_id" value="{{ $weekly->id }}">
            <p class="small text-muted mb-2">Já existe um relatório nesta semana. Descarte-o para enviar outro com as pendências atualizadas. Alterações já feitas nos cadastros (criar, apagar, clínica) permanecem.</p>
            <button class="btn btn-outline-danger" type="submit">Descartar conferência da semana</button>
        </form>
    @endif
@endcan

@if ($recon)
    @php
        $okCount = (int) ($recon['ok'] ?? 0);
        $pendingCount = (int) $state['pending'];
        $resolvedCount = count($recon['resolved'] ?? []);
        $issueTotal = $pendingCount + $resolvedCount;
        $progress = $issueTotal === 0 ? 100 : (int) round(($resolvedCount / $issueTotal) * 100);
        $bucketCount = collect($groups)->mapWithKeys(fn ($group) => [$group['bucket'] => count($group['items'])]);
        $filterCards = [
            ['key' => 'ok', 'label' => 'Bateu', 'count' => $okCount, 'tone' => 'success', 'hint' => 'Nomes que bateram'],
            ['key' => 'pending', 'label' => 'Para resolver', 'count' => $pendingCount, 'tone' => 'warning', 'hint' => 'Tudo que ainda está amarelo'],
            ['key' => 'only_report', 'label' => 'Só na Cliomed', 'count' => $bucketCount['only_report'] ?? 0, 'tone' => 'warning', 'hint' => 'Não achei no WA'],
            ['key' => 'only_system', 'label' => 'Só no WA', 'count' => $bucketCount['only_system'] ?? 0, 'tone' => 'warning', 'hint' => 'Cliomed no WA, fora do relatório'],
        ];
        foreach ($groups as $group) {
            if (in_array($group['bucket'], ['only_report', 'only_system'], true)) {
                continue;
            }
            $filterCards[] = [
                'key' => $group['bucket'],
                'label' => $group['title'],
                'count' => count($group['items']),
                'tone' => 'warning',
                'hint' => $group['hint'],
            ];
        }
        $okPeople = $recon['ok_people'] ?? [];
    @endphp

    <style>
        .cliomed-chip { cursor: pointer; background: #fff; text-align: left; }
        .cliomed-chip.is-warning { border-color: #ffab00 !important; background: #fff8e6; }
        .cliomed-chip.is-success { border-color: #71dd37 !important; background: #eefbe3; }
        .cliomed-chip.is-active { box-shadow: 0 0 0 2px currentColor; }
        .cliomed-chip.is-warning.is-active { color: #b76e00; }
        .cliomed-chip.is-success.is-active { color: #3d8c12; }
        tr.cliomed-person-ok > td { background: #eefbe3; }
        .cliomed-person-warning .accordion-button { background: #fff8e6; }
        .cliomed-person-warning .accordion-button:not(.collapsed) { background: #fff1cc; color: inherit; }
    </style>

    <div id="cliomed-board">
        <div class="row g-3 mb-3">
            @foreach ($filterCards as $card)
                <div class="col-6 col-md-3">
                    <button type="button"
                        class="cliomed-chip border rounded p-3 h-100 w-100 is-{{ $card['tone'] }}"
                        data-cliomed-filter="{{ $card['key'] }}">
                        <div class="text-muted small">{{ $card['label'] }}</div>
                        <h4 class="mb-0">{{ $card['count'] }}</h4>
                        @if ($card['key'] === 'ok')
                            <div class="progress mt-2" style="height: 6px;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: {{ $progress }}%" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="small text-success mt-1">
                                @if ($issueTotal === 0)
                                    Base conferida
                                @else
                                    {{ $resolvedCount }} de {{ $issueTotal }} pendências tratadas · {{ $progress }}%
                                @endif
                            </div>
                        @else
                            <div class="small text-muted mt-1">{{ $card['hint'] }}</div>
                        @endif
                    </button>
                </div>
            @endforeach
        </div>

        <div class="mb-3">
            <label class="form-label" for="cliomed-search">Buscar nestes nomes</label>
            <input id="cliomed-search" class="form-control" type="search" placeholder="Digite um nome para ver só essa pessoa" data-cliomed-search>
        </div>

        <div data-cliomed-section="ok" hidden>
            <h6 class="mb-1 text-success">Bateu ({{ count($okPeople) }})</h6>
            <p class="small text-muted mb-2">Nome encontrado na Cliomed e no WA, ativo e com clínica Cliomed.</p>
            @if ($okPeople === [] && $okCount > 0)
                <p class="mb-3">O total está salvo, mas a lista de nomes não. Envie o relatório de novo para acompanhar pessoa a pessoa.</p>
            @else
                <div class="table-responsive mb-3">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Detalhe</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($okPeople as $item)
                                <tr class="cliomed-person-ok" data-cliomed-person="{{ mb_strtolower($item['name'].' '.($item['report_name'] ?? '')) }}">
                                    <td>{{ $item['name'] }}</td>
                                    <td class="text-muted">
                                        {{ ! empty($item['report_name']) && $item['report_name'] !== $item['name'] ? 'relatório: '.$item['report_name'] : 'conferido' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    @forelse ($groups as $group)
        <div class="mb-3" data-cliomed-section="{{ $group['bucket'] }}">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                <div>
                    <h6 class="mb-1">{{ $group['title'] }} ({{ count($group['items']) }})</h6>
                    <p class="small text-muted mb-0">{{ $group['hint'] }} Abra cada pessoa para tratar no cadastro. Nada é aplicado em lote.</p>
                </div>
                @if ($weekly->isOpen() && $group['bucket'] === 'only_report')
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('work.cliomed.unregistered') }}">PDF dos não cadastrados</a>
                @endif
                @if ($weekly->isOpen() && $group['bucket'] === 'inactive_in_report')
                    <a class="btn btn-sm btn-outline-danger" href="{{ route('work.cliomed.charge') }}">PDF cobrança de inativos</a>
                @endif
            </div>
            <div class="accordion" id="cliomed-acc-{{ $group['bucket'] }}">
                @foreach ($group['items'] as $item)
                    @php
                        $personName = $item['name'] ?? $item['report_name'] ?? '';
                        $personSearch = mb_strtolower(trim($personName.' '.collect($item['candidates'] ?? [])->pluck('name')->join(' ')));
                        $itemId = 'cliomed-'.md5($item['_key']);
                        $isOpen = $openKey === $item['_key'];
                        $detail = match ($group['bucket']) {
                            'only_report' => trim(($item['unit'] ?? '').' '.($item['sector'] ?? '').(! empty($item['role']) ? ' · '.$item['role'] : '')),
                            'ambiguous' => 'Candidatos: '.collect($item['candidates'] ?? [])->pluck('name')->join(', '),
                            default => trim(($item['clinic'] ?? '').(isset($item['active']) && ! $item['active'] ? ' · inativo' : '').(! empty($item['report_name']) && ($item['report_name'] ?? '') !== ($item['name'] ?? '') ? ' · relatório: '.$item['report_name'] : '')),
                        };
                    @endphp
                    <div class="accordion-item cliomed-person-warning" data-cliomed-person="{{ $personSearch }}">
                        <h2 class="accordion-header" id="heading-{{ $itemId }}">
                            <button class="accordion-button {{ $isOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $itemId }}" aria-expanded="{{ $isOpen ? 'true' : 'false' }}" aria-controls="{{ $itemId }}">
                                <span>
                                    <strong>{{ $personName !== '' ? $personName : '—' }}</strong>
                                    @if ($detail !== '')
                                        <span class="text-muted small d-block">{{ $detail }}</span>
                                    @endif
                                </span>
                            </button>
                        </h2>
                        <div id="{{ $itemId }}" class="accordion-collapse collapse {{ $isOpen ? 'show' : '' }}" aria-labelledby="heading-{{ $itemId }}" data-bs-parent="#cliomed-acc-{{ $group['bucket'] }}">
                            <div class="accordion-body">
                                @if ($weekly->isOpen())
                                    @can('Gerir desligamentos')
                                        @include('work.partials.cliomed-item-actions', [
                                            'weekly' => $weekly,
                                            'group' => $group,
                                            'item' => $item,
                                            'personName' => $personName,
                                            'lookupKey' => $lookupKey,
                                            'lookupTerm' => $lookupTerm,
                                            'lookupPeople' => $lookupPeople,
                                        ])
                                    @else
                                        <p class="small text-muted mb-0">Sem permissão para tratar esta pendência.</p>
                                    @endcan
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        @if ($weekly->isOpen())
            <p class="mb-3" data-cliomed-clear>Nenhuma inconsistência aberta nesta conferência.</p>
        @endif
    @endforelse

        <p class="mb-3" data-cliomed-empty hidden>Nenhuma pessoa com esse filtro.</p>
    </div>

    <script>
        (function () {
            const root = document.getElementById('cliomed-board');
            if (!root) return;

            const search = root.querySelector('[data-cliomed-search]');
            const sections = Array.from(root.querySelectorAll('[data-cliomed-section]'));
            const cards = Array.from(root.querySelectorAll('[data-cliomed-filter]'));
            const empty = root.querySelector('[data-cliomed-empty]');
            const clearNote = root.querySelector('[data-cliomed-clear]');
            const params = new URLSearchParams(window.location.search);
            let bucket = params.get('bucket') || '';

            if (params.get('q')) {
                search.value = params.get('q');
            }

            function apply() {
                const q = search.value.trim().toLocaleLowerCase();
                let visible = 0;

                sections.forEach(function (section) {
                    const sectionBucket = section.getAttribute('data-cliomed-section');
                    const sectionOn = bucket === '' || bucket === 'pending'
                        ? sectionBucket !== 'ok'
                        : sectionBucket === bucket;
                    let rowsOn = 0;

                    section.querySelectorAll('[data-cliomed-person]').forEach(function (row) {
                        const name = row.getAttribute('data-cliomed-person') || '';
                        const show = sectionOn && (q === '' || name.indexOf(q) !== -1);
                        row.hidden = !show;
                        if (show) rowsOn += 1;
                    });

                    const hasRows = section.querySelector('[data-cliomed-person]');
                    section.hidden = !sectionOn || (hasRows && rowsOn === 0);
                    if (!hasRows && sectionOn) {
                        section.hidden = q !== '';
                    }
                    visible += rowsOn;
                    if (!hasRows && sectionOn && q === '') visible += 1;
                });

                if (empty) empty.hidden = visible !== 0;
                if (clearNote) clearNote.hidden = bucket === 'ok' || q !== '';

                cards.forEach(function (card) {
                    card.classList.toggle('is-active', card.getAttribute('data-cliomed-filter') === bucket);
                });

                const url = new URL(window.location.href);
                if (bucket) url.searchParams.set('bucket', bucket); else url.searchParams.delete('bucket');
                if (q) url.searchParams.set('q', q); else url.searchParams.delete('q');
                window.history.replaceState(null, '', url);
                root.querySelectorAll('.js-cliomed-bucket').forEach(function (el) { el.value = bucket; });
                root.querySelectorAll('.js-cliomed-q').forEach(function (el) { el.value = q; });
            }

            cards.forEach(function (card) {
                card.addEventListener('click', function () {
                    const next = card.getAttribute('data-cliomed-filter');
                    bucket = bucket === next ? '' : next;
                    apply();
                });
            });

            search.addEventListener('input', apply);
            apply();
        })();
    </script>
@endif

@can('Gerir desligamentos')
    @if ($weekly->isOpen())
        @if ($state['pending'] > 0 || $weekly->report_count === null)
            <p class="text-muted mb-0">O card continua amarelo até resolver cada inconsistência e finalizar a conferência.</p>
        @else
            <form method="POST" action="{{ route('work.clinic.weekly') }}" class="mt-3">
                @csrf
                <input type="hidden" name="check_id" value="{{ $weekly->id }}">
                <input type="hidden" name="complete" value="1">
                <div class="mb-3">
                    <label class="form-label">Tratativa da conferência</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="O que foi ajustado">{{ $weekly->notes }}</textarea>
                </div>
                <button class="btn btn-primary" type="submit">Finalizar conferência</button>
            </form>
        @endif
    @elseif ($weekly->notes)
        <p class="mb-0"><strong>Tratativa:</strong> {{ $weekly->notes }}</p>
    @else
        <p class="mb-0 text-muted">Conferência desta semana em dia.</p>
    @endif
@endcan
