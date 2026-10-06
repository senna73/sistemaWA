@php
    $boardTitle = $boardTitle ?? 'Por etapa';
    $boardHint = $boardHint ?? 'Colunas na ordem do POP. Clique no card para abrir o dossiê sem sair do quadro.';
    $showOpenings = $showOpenings ?? false;
    $showExcess = $showExcess ?? false;
    $showDutyLegend = $showDutyLegend ?? false;
    $boardLayout = $boardLayout ?? 'kanban';
    $columnLabel = $columnLabel ?? 'etapas';
    $peopleLabel = $peopleLabel ?? 'pessoas no fluxo';
    $hideEmptyColumns = $hideEmptyColumns ?? false;
    $visibleColumns = collect($board['columns'] ?? [])
        ->when($hideEmptyColumns, fn ($columns) => $columns->filter(fn (array $column) => ($column['cards'] ?? []) !== []))
        ->values();
@endphp

@if ($showDutyLegend)
    <div class="duty-legend">
        <span><i class="duty-swatch rh"></i> Contorno amarelo: o RH precisa agir</span>
        <span><i class="duty-swatch gestor"></i> Contorno vermelho: aguarda aprovação do Super admin</span>
    </div>
@endif

<div class="store-board-wrap">
    <div class="store-board-toolbar">
        <div>
            <h5 class="mb-1">{{ $boardTitle }}</h5>
            <p class="text-muted small mb-2">{{ $boardHint }}</p>
            <div class="store-board-stats">
                <span><strong>{{ $visibleColumns->count() }}</strong> {{ $columnLabel }}</span>
                <span><strong>{{ $board['opening_count'] }}</strong> {{ $peopleLabel }}</span>
                @if ($showOpenings)
                    <span><strong>{{ $board['openings'] }}</strong> vagas abertas</span>
                @endif
                @if ($showExcess)
                    <span><strong>{{ $board['excess_count'] }}</strong> excessos de cota</span>
                @endif
            </div>
        </div>
        <input class="form-control form-control-sm store-filter" type="search" placeholder="Filtrar etapa ou pessoa..." data-store-filter>
    </div>

    <div class="store-board store-board-{{ $boardLayout }}">
        @forelse ($visibleColumns as $column)
            <section class="store-col" data-store="{{ mb_strtolower($column['name']) }}" data-stage="{{ $column['id'] }}">
                <header class="store-col-head">
                    <h3>{{ $column['name'] }}</h3>
                    <div class="store-col-meta">
                        <span class="store-chip occupied">{{ count($column['cards']) }} {{ count($column['cards']) === 1 ? 'card' : 'cards' }}</span>
                    </div>
                </header>
                <div class="store-col-body">
                    @foreach ($column['cards'] as $card)
                        @php
                            $search = mb_strtolower(($card['title'] ?? '').' '.($card['store'] ?? '').' '.($card['whatsapp_group'] ?? '').' '.($card['tag'] ?? '').' '.$column['name']);
                        @endphp
                        @if (! empty($card['slug']))
                            <button type="button"
                                class="store-card {{ $card['style'] }} {{ ! empty($card['duty']) ? 'duty-'.$card['duty'] : '' }}"
                                data-card="{{ $search }}"
                                data-board-card="{{ $card['slug'] }}"
                                data-open-demo-modal="demo-card-{{ $card['slug'] }}">
                                <div class="store-tag">{{ $card['tag'] }}</div>
                                <h4>{{ $card['title'] }}</h4>
                                <p>
                                    @if (! empty($card['store']) && $card['store'] !== '—')Estabelecimento {{ $card['store'] }} · @endif
                                    @if (! empty($card['whatsapp_group']) && $card['whatsapp_group'] !== '—')Grupo WhatsApp {{ $card['whatsapp_group'] }} · @endif
                                    {{ $card['body'] }}
                                </p>
                                @if (! empty($card['duty_label']))
                                    <div class="store-duty">{{ $card['duty_label'] }}</div>
                                @endif
                                <div class="follow">Abrir dossiê</div>
                            </button>
                        @else
                            <article class="store-card {{ $card['style'] }}" data-card="{{ $search }}">
                                <div class="store-tag">{{ $card['tag'] }}</div>
                                <h4>{{ $card['title'] }}</h4>
                                <p>{{ ($card['store'] ?? '') ? $card['store'].' · ' : '' }}{{ $card['body'] }}</p>
                            </article>
                        @endif
                    @endforeach
                </div>
            </section>
        @empty
            <div class="text-muted px-2 py-3">Nenhum card neste quadro agora.</div>
        @endforelse
    </div>
</div>

@foreach ($visibleColumns as $column)
    @foreach ($column['cards'] as $card)
        @continue(empty($card['slug']))
        <div class="demo-overlay" id="demo-card-{{ $card['slug'] }}" role="dialog" aria-modal="true">
            <div class="demo-overlay-panel">
                <div class="demo-overlay-head">
                    <h5>{{ $card['kind'] ?? $card['tag'] }} · {{ $card['name'] ?? $card['title'] }}</h5>
                    <button type="button" class="demo-overlay-close" data-close-demo-modal aria-label="Fechar">&times;</button>
                </div>
                <div class="demo-overlay-body">
                    @include($cardDetailView ?? 'work.partials.demo-card-detail', ['card' => $card])
                </div>
            </div>
        </div>
    @endforeach
@endforeach
