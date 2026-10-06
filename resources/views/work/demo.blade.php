<x-app-layout>
    @include('work.partials.board-styles')

    <div class="container-fluid px-0">
        <div class="alert alert-warning py-2 px-3 mb-3">
            Demonstração para cliente · dados ilustrativos.
        </div>

        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3 px-1">
            <div>
                <h4 class="mb-1">{{ $mode === 'contratacoes' ? 'Contratações por etapa' : 'Demissões por etapa' }}</h4>
                <p class="text-muted mb-0">
                    Colunas na ordem do POP. Clique no card para abrir o dossiê sem sair do quadro.
                </p>
            </div>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('work.home') }}">RH Controle</a>
        </div>

        <div class="d-flex gap-2 mb-3 px-1">
            <a class="btn btn-sm {{ $mode === 'contratacoes' ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('work.demo', 'contratacoes') }}">Contratações</a>
            <a class="btn btn-sm {{ $mode === 'demissoes' ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('work.demo', 'demissoes') }}">Demissões</a>
        </div>

        @include('work.partials.stage-board', [
            'board' => $board,
            'boardTitle' => $mode === 'contratacoes' ? 'Fluxo de contratação' : 'Fluxo de demissão',
            'showOpenings' => true,
            'showExcess' => $mode === 'demissoes',
        ])
    </div>

    @include('work.partials.stage-board-scripts')
    @include('work.partials.demo-doc-upload')
</x-app-layout>
