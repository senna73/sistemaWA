<x-app-layout>
    @include('work.partials.board-styles')
    <div class="container-fluid">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @include('work.partials.notifications')

        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3 px-1">
            <div>
                <h4 class="mb-1">Financeiro e DRE</h4>
                <p class="text-muted mb-0">Cada lote é um atendimento: diárias do período, emissão da nota e pagamento.</p>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('work.home') }}">RH Controle</a>
                <a class="btn btn-sm btn-primary" href="{{ route('finantial-results') }}">Analytics Financeiro</a>
                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.batches.index') }}">Processamento</a>
            </div>
        </div>

        @include('work.partials.stage-board', [
            'board' => $board,
            'boardTitle' => 'Pagamento e nota por etapa',
            'boardHint' => 'A mudança de etapa avisa quem acompanha o atendimento.',
        ])
    </div>
    @include('work.partials.stage-board-scripts')
</x-app-layout>
