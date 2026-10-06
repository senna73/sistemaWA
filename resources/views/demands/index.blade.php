<x-app-layout>
    @if ($mode !== 'mine')
        @include('work.partials.board-styles')
    @endif

    <div class="{{ $mode === 'mine' ? 'container' : 'container-fluid' }}">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
            <div>
                <h4 class="mb-1">
                    @if ($mode === 'oversight')
                        Demandas do RH
                    @elseif ($mode === 'queue')
                        Atividades a atender
                    @else
                        Minhas demandas
                    @endif
                </h4>
                <p class="text-muted mb-0">
                    @if ($mode === 'oversight')
                        Espelho do quadro do RH. Aqui o super admin acompanha o que o RH tem para fazer, o que está em atendimento e o que já foi à conferência.
                    @elseif ($mode === 'queue')
                        Quadro operacional do RH. Cada coluna é um tipo de demanda. Atenda os cards em amarelo e envie para conferência os que já estão em andamento.
                    @else
                        Pedidos que você abriu ou pelos quais é responsável.
                    @endif
                </p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                @if ($mode === 'queue')
                    <a class="btn btn-outline-secondary" href="{{ route('work.home') }}">RH Controle</a>
                @elseif ($mode === 'oversight')
                    <a class="btn btn-outline-secondary" href="{{ route('rh.inbox') }}">Acompanhamento RH</a>
                @endif
                @if ($mode !== 'queue' && \App\Support\PopCatalog::canOpenOperationalDemand(auth()->user()))
                    <a class="btn btn-primary" href="{{ route('demands.create') }}">Nova demanda</a>
                @endif
            </div>
        </div>

        @if ($mode === 'mine')
            <div class="card">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Pedido</th>
                                <th>Categoria</th>
                                <th>Status</th>
                                <th>Quem</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($demands as $demand)
                                <tr>
                                    <td><a href="{{ route('demands.show', $demand) }}">{{ $demand->request_text }}</a></td>
                                    <td>{{ $demand->categoryLabel() }}</td>
                                    <td>{{ $demand->statusLabel() }}</td>
                                    <td>{{ $demand->name }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted">Nenhuma demanda.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            {{ $demands->links() }}
        @else
            @php $summary = $board['summary'] ?? []; @endphp
            <div class="row g-3 mb-3">
                <div class="col-md-3 col-6">
                    <div class="card h-100 duty-rh">
                        <div class="card-body">
                            <h4 class="mb-0">{{ $summary['awaiting'] ?? 0 }}</h4>
                            <span class="text-muted">Aguardando atendimento</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h4 class="mb-0">{{ $summary['in_progress'] ?? 0 }}</h4>
                            <span class="text-muted">Em atendimento</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card h-100 duty-gestor">
                        <div class="card-body">
                            <h4 class="mb-0">{{ $summary['review'] ?? 0 }}</h4>
                            <span class="text-muted">Em conferência</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h4 class="mb-0">{{ $board['opening_count'] ?? 0 }}</h4>
                            <span class="text-muted">No quadro</span>
                        </div>
                    </div>
                </div>
            </div>

            @include('work.partials.stage-board', [
                'board' => $board,
                'boardTitle' => $mode === 'oversight' ? 'Demandas por tipo' : 'Fila por tipo',
                'boardHint' => $mode === 'oversight'
                    ? 'Colunas empilhadas por tipo. Amarelo é fila do RH. Vermelho espera conferência. Sem botões de atendimento — o RH opera no RH Controle.'
                    : 'Colunas empilhadas por tipo. Abra o card para atender, registrar nota ou enviar para conferência.',
                'showDutyLegend' => true,
                'boardLayout' => 'stack',
                'columnLabel' => 'tipos',
                'peopleLabel' => 'cards no quadro',
                'cardDetailView' => 'demands.partials.card-detail',
            ])
        @endif
    </div>

    @if ($mode !== 'mine')
        @include('work.partials.stage-board-scripts')
    @endif
</x-app-layout>
