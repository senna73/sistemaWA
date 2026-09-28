@php
    $board = $board ?? ['columns' => collect(), 'openings' => 0, 'opening_count' => 0, 'process_count' => 0, 'store_count' => 0];
    $canEditQuota = $canEditQuota ?? auth()->user()?->can('Recrutamento');
@endphp

<style>
    .store-board-wrap { margin: 0 -0.5rem; }
    .store-board {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        padding: 0.25rem 0.5rem 1rem;
        align-items: flex-start;
    }
    .store-col {
        flex: 1 1 280px;
        max-width: 320px;
        min-width: 260px;
        background: #f4f6f9;
        border: 1px solid #e8ecf1;
        border-radius: 14px;
        display: flex;
        flex-direction: column;
        min-height: 12rem;
    }
    .store-col-head {
        padding: 0.9rem 1rem 0.75rem;
        border-bottom: 1px solid #e5e7eb;
        position: sticky;
        top: 0;
        background: #f4f6f9;
        border-radius: 14px 14px 0 0;
        z-index: 1;
    }
    .store-col-head h3 {
        font-size: 0.92rem;
        font-weight: 700;
        margin: 0;
        color: #111827;
        line-height: 1.3;
    }
    .store-col-head .city { font-size: 0.72rem; color: #6b7280; margin-top: 0.15rem; }
    .store-col-meta { display: flex; flex-direction: column; align-items: flex-start; gap: 0.35rem; margin-top: 0.65rem; }
    .store-chip {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        text-transform: uppercase;
        padding: 0.2rem 0.5rem;
        border-radius: 999px;
        background: #fff;
        border: 1px solid #e5e7eb;
        color: #374151;
    }
    .store-chip.open { background: #ecfdf3; border-color: #bbf7d0; color: #166534; }
    .store-chip.opening { background: #fff7ed; border-color: #fed7aa; color: #c2410c; }
    .store-chip.occupied { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
    .store-chip.over { background: #fef2f2; border-color: #fecaca; color: #b91c1c; }
    .store-col-body { padding: 0.75rem; display: flex; flex-direction: column; gap: 0.65rem; }
    .store-card {
        display: block;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 0.8rem 0.85rem;
        text-decoration: none;
        color: inherit;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
    }
    a.store-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
        border-color: #c7d2fe;
        color: inherit;
    }
    .store-card.open-slot { border-left: 3px solid #22c55e; }
    .store-card.opening-slot { border-left: 3px solid #f59e0b; }
    .store-card.excess-slot { border-left: 3px solid #ef4444; }
    .store-tag {
        font-size: 0.65rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #6b7280;
        margin-bottom: 0.3rem;
    }
    .store-card.open-slot .store-tag { color: #15803d; }
    .store-card.opening-slot .store-tag { color: #c2410c; }
    .store-card.excess-slot .store-tag { color: #b91c1c; }
    .store-card h4 { font-size: 0.9rem; font-weight: 700; margin: 0 0 0.25rem; color: #111827; }
    .store-card p { margin: 0; font-size: 0.78rem; color: #6b7280; line-height: 1.4; }
    .store-card .follow {
        margin-top: 0.5rem;
        font-size: 0.72rem;
        font-weight: 700;
        color: #4f46e5;
    }
    .store-board-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.85rem;
    }
    .store-board-stats { display: flex; gap: 1rem; flex-wrap: wrap; color: #6b7280; font-size: 0.82rem; }
    .store-board-stats strong { color: #111827; }
    .store-filter { max-width: 260px; }
    .store-quota-form { display: flex; gap: 0.35rem; margin-top: 0.65rem; }
    .store-quota-form input { width: 4.2rem; height: 1.8rem; font-size: 0.75rem; padding: 0.15rem 0.4rem; }
    .store-quota-form button { height: 1.8rem; font-size: 0.7rem; padding: 0 0.45rem; }
</style>

<div class="store-board-wrap">
    <div class="store-board-toolbar">
        <div class="store-board-stats">
            <span><strong>{{ $board['store_count'] }}</strong> lojas</span>
            <span><strong>{{ $board['openings'] }}</strong> vagas abertas</span>
            <span><strong>{{ $board['opening_count'] ?? 0 }}</strong> em processo de abertura</span>
            <span><strong>{{ $board['excess_count'] ?? 0 }}</strong> excessos de cota</span>
        </div>
        <input class="form-control form-control-sm store-filter" type="search" placeholder="Filtrar loja..." data-store-filter>
    </div>

    <div class="store-board">
        @forelse ($board['columns'] as $column)
            <section class="store-col" data-store="{{ mb_strtolower($column['name'].' '.($column['city'] ?? '')) }}">
                <header class="store-col-head">
                    <h3>{{ $column['name'] }}</h3>
                    @if ($column['city'])
                        <div class="city">{{ $column['city'] }}</div>
                    @endif
                    <div class="store-col-meta">
                        @if ($column['quota'] === null)
                            <span class="store-chip">Cota não definida</span>
                        @else
                            <span class="store-chip">Cota {{ $column['quota'] }}</span>
                            <span class="store-chip occupied">{{ $column['occupied'] }} ocupadas</span>
                            <span class="store-chip open">{{ $column['open'] }} {{ $column['open'] === 1 ? 'aberta' : 'abertas' }}</span>
                            @if ($column['opening'] > 0)
                                <span class="store-chip opening">{{ $column['opening'] }} em abertura</span>
                            @endif
                            @if ($column['occupied'] > $column['quota'])
                                <span class="store-chip over">Acima da cota</span>
                            @endif
                        @endif
                    </div>
                    @if ($canEditQuota && $column['id'])
                        <form method="POST" action="{{ route('work.quotas.update') }}" class="store-quota-form">
                            @csrf
                            <input type="hidden" name="company_id" value="{{ $column['id'] }}">
                            <input type="number" name="headcount_quota" min="0" max="500" value="{{ $column['quota'] }}" required>
                            <button class="btn btn-outline-secondary" type="submit">Cota</button>
                        </form>
                    @endif
                </header>
                <div class="store-col-body">
                    @if ($column['open'] > 0)
                        @for ($slot = 1; $slot <= $column['open']; $slot++)
                            <article class="store-card open-slot">
                                <div class="store-tag">Vaga em aberto</div>
                                <h4>Vaga livre {{ $slot }} de {{ $column['open'] }}</h4>
                                <p>Cota {{ $column['quota'] }} · {{ $column['occupied'] }} pessoas ativas nesta loja.</p>
                            </article>
                        @endfor
                    @endif

                    @foreach ($column['processes'] as $process)
                        <a class="store-card opening-slot" href="{{ route('work.offboarding.show', $process) }}">
                            <div class="store-tag">
                                {{ $process->freesHeadcountSlot() ? 'Vaga em processo de abertura' : $process->kindLabel() }}
                            </div>
                            <h4>{{ $process->collaborator?->name ?? 'Colaborador' }}</h4>
                            <p>
                                {{ $process->kindLabel() }} · {{ $process->label() }}
                                @if ($process->collaborator?->job_title)
                                    · {{ $process->collaborator->job_title }}
                                @endif
                            </p>
                            <div class="follow">Abrir e acompanhar →</div>
                        </a>
                    @endforeach

                    @php
                        $excessPeople = ($column['quota'] !== null && ($column['excess'] ?? 0) > 0)
                            ? ($column['people'] ?? collect())->slice($column['quota'])->values()
                            : collect();
                    @endphp
                    @foreach ($excessPeople as $person)
                        <article class="store-card excess-slot">
                            <div class="store-tag">Excesso de cota</div>
                            <h4>{{ $person->name }}</h4>
                            <p>
                                Acima da cota de {{ $column['quota'] }}
                                @if ($person->job_title)
                                    · {{ $person->job_title }}
                                @endif
                            </p>
                        </article>
                    @endforeach
                </div>
            </section>
        @empty
            <p class="text-muted px-2">Nenhuma loja cadastrada.</p>
        @endforelse
    </div>
</div>
<script>
    document.querySelectorAll('[data-store-filter]').forEach(function (input) {
        input.addEventListener('input', function () {
            var q = this.value.toLowerCase();
            this.closest('.store-board-wrap').querySelectorAll('.store-col').forEach(function (col) {
                col.style.display = !q || (col.getAttribute('data-store') || '').indexOf(q) !== -1 ? '' : 'none';
            });
        });
    });
</script>
