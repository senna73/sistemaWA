@php
    $recon = $weekly->reconciliation ?? [];
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
    As inconsistências ficam aqui para resolver. O card só fica cinza depois que tudo estiver tratado e a conferência for finalizada.
</p>

@can('Gerir desligamentos')
    @if ($weekly->isOpen())
        <form method="POST" action="{{ route('work.clinic.weekly') }}" enctype="multipart/form-data" class="mb-3">
            @csrf
            <input type="hidden" name="check_id" value="{{ $weekly->id }}">
            <div class="mb-3">
                <label class="form-label">Relatório Cliomed (.xlsx)</label>
                <input type="file" name="attachment" class="form-control" accept=".xlsx,.csv" required>
            </div>
            <button class="btn btn-primary" type="submit">Comparar com o sistema</button>
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
        tr.cliomed-person-warning > td { background: #fff8e6; }
        tr.cliomed-person-ok > td { background: #eefbe3; }
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
            <h6 class="mb-1">{{ $group['title'] }} ({{ count($group['items']) }})</h6>
            <p class="small text-muted mb-2">{{ $group['hint'] }}</p>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Detalhe</th>
                            @if ($weekly->isOpen())
                                <th></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($group['items'] as $item)
                            @php
                                $personName = $item['name'] ?? $item['report_name'] ?? '';
                                $personSearch = mb_strtolower(trim($personName.' '.collect($item['candidates'] ?? [])->pluck('name')->join(' ')));
                            @endphp
                            <tr class="cliomed-person-warning" data-cliomed-person="{{ $personSearch }}">
                                <td>{{ $personName !== '' ? $personName : '—' }}</td>
                                <td class="text-muted">
                                    @if ($group['bucket'] === 'only_report')
                                        {{ $item['sector'] ?? '' }} {{ ! empty($item['role']) ? '· '.$item['role'] : '' }}
                                    @elseif ($group['bucket'] === 'ambiguous')
                                        {{ collect($item['candidates'] ?? [])->pluck('name')->join(', ') }}
                                    @else
                                        {{ $item['clinic'] ?? '' }} {{ isset($item['active']) && ! $item['active'] ? '· inativo' : '' }}
                                        {{ ! empty($item['report_name']) && ($item['report_name'] ?? '') !== ($item['name'] ?? '') ? '· relatório: '.$item['report_name'] : '' }}
                                    @endif
                                </td>
                                @if ($weekly->isOpen())
                                    <td class="text-end">
                                        @can('Gerir desligamentos')
                                            <form method="POST" action="{{ route('work.cliomed.resolve') }}">
                                                @csrf
                                                <input type="hidden" name="check_id" value="{{ $weekly->id }}">
                                                <input type="hidden" name="key" value="{{ $item['_key'] }}">
                                                <input type="hidden" name="bucket" value="" class="js-cliomed-bucket">
                                                <input type="hidden" name="q" value="" class="js-cliomed-q">
                                                <button class="btn btn-sm btn-outline-warning" type="submit">Resolvida</button>
                                            </form>
                                        @endcan
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
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

                    const bareNote = section.querySelector('p');
                    const hasRows = section.querySelector('[data-cliomed-person]');
                    section.hidden = !sectionOn || (hasRows && rowsOn === 0);
                    if (!hasRows && sectionOn) {
                        section.hidden = q !== '';
                    }
                    visible += rowsOn;
                    if (!hasRows && sectionOn && q === '') visible += 1;
                    if (bareNote && !hasRows) bareNote.hidden = false;
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
