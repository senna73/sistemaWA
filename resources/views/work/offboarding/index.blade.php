<x-app-layout>
    @include('work.partials.board-styles')
    <div class="container-fluid">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        @include('work.partials.notifications')

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">Gestão RH Demissional</h5>
                <div class="d-flex gap-2 flex-wrap">
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('work.home') }}">RH Controle</a>
                    @can('Gerir desligamentos')
                        <form method="POST" action="{{ route('work.inactivity.review') }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-warning" type="submit">Revisar 25 dias sem diária</button>
                        </form>
                    @endcan
                    @can('Solicitar desligamento')
                        <a class="btn btn-sm btn-primary" href="{{ route('work.request') }}">Nova solicitação</a>
                    @endcan
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @php
                        $stageCards = [
                            ['offboarding', 'atendimento_rh', $summary['stages']['atendimento_rh'] ?? 0, 'Aguardando atendimento do RH', 'rh'],
                            ['offboarding', 'analise_direcao', $summary['stages']['analise_direcao'] ?? 0, 'Minhas Análises', 'gestor'],
                            ['offboarding', 'marcacao_exame', $summary['stages']['marcacao_exame'] ?? 0, 'Marcação de exame', 'rh'],
                            ['offboarding', 'aguardando_contabilidade', ($summary['stages']['aguardando_contabilidade'] ?? 0) + ($summary['stages']['aguardando_documentacao'] ?? 0), 'Contabilidade', ''],
                            ['offboarding', 'demissao_correio', $summary['stages']['demissao_correio'] ?? 0, 'Via correio', 'rh'],
                            ['offboarding', 'transferencia_analise', $summary['stages']['transferencia_analise'] ?? 0, 'Transferência', 'gestor'],
                        ];
                    @endphp
                    @foreach ($stageCards as [$project, $stageKey, $count, $label, $tone])
                        @php $dutyClass = ((int) $count > 0 && $tone !== '') ? 'duty-'.$tone : ''; @endphp
                        <div class="col-md-3 col-6">
                            <a class="card h-100 text-decoration-none {{ $dutyClass }}" href="{{ route('work.project', array_filter(['project' => $project, 'stage' => $stageKey])) }}">
                                <div class="card-body">
                                    <h4 class="mb-0">{{ $count }}</h4>
                                    <span class="text-muted">{{ $label }}</span>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        @include('work.partials.stage-board', [
            'board' => $board,
            'boardTitle' => 'Desligamentos por etapa',
            'boardHint' => 'Amarelo é fila do RH. Vermelho só anda com a aprovação do Super admin. Cada card de demissão fica ligado a um colaborador.',
            'showDutyLegend' => true,
        ])
    </div>
    @include('work.partials.stage-board-scripts')
    @include('work.partials.demo-doc-upload')
</x-app-layout>
