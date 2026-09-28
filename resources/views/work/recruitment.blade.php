<x-app-layout>
    @include('work.partials.board-styles')

    <div class="container-fluid px-0">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        @include('work.partials.notifications')

        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3 px-1">
            <div>
                <h4 class="mb-1">Contratações por etapa</h4>
                <p class="text-muted mb-0">Colunas na ordem do POP. Clique no card para abrir o dossiê sem sair do quadro.</p>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('work.home') }}">RH Controle</a>
                @if (config('rh.show_hiring'))
                    @can('Recrutamento')
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#quota-form">Definir cota</button>
                        <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#hire-form">Nova contratação</button>
                    @endcan
                @endif
            </div>
        </div>

        @if (config('rh.show_hiring'))
            @can('Recrutamento')
            <div class="collapse mb-3" id="hire-form">
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="{{ route('work.hiring.store') }}" class="row g-2 align-items-end">
                            @csrf
                            <div class="col-md-4">
                                <label class="form-label">Estabelecimento</label>
                                <select name="company_id" class="form-select" required>
                                    <option value="">Selecione a loja</option>
                                    @foreach ($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Nome</label>
                                <input name="name" class="form-control" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Função</label>
                                <input name="job_title" class="form-control">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Admissão</label>
                                <input type="date" name="admission_on" class="form-control">
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-primary" type="submit">Abrir card</button>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Observação</label>
                                <input name="notes" class="form-control" placeholder="Ex.: Exame admissional · Cliomed 26/09 09h">
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="collapse mb-3" id="quota-form">
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="{{ route('work.quotas.update') }}" class="row g-2 align-items-end">
                            @csrf
                            <div class="col-md-6">
                                <label class="form-label">Estabelecimento</label>
                                <select name="company_id" class="form-select" required>
                                    <option value="">Selecione a loja</option>
                                    @foreach ($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->name }}@if ($company->headcount_quota !== null) (cota {{ $company->headcount_quota }})@endif</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Cota de vagas</label>
                                <input type="number" name="headcount_quota" class="form-control" min="0" max="500" required>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-primary" type="submit">Salvar cota</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endcan
        @endif

        @include('work.partials.stage-board', [
            'board' => $board,
            'boardTitle' => 'Fluxo de contratação',
            'boardHint' => 'Mesmo quadro da demonstração, com as contratações reais. Anexe os documentos na ordem para avançar o card.',
            'showOpenings' => true,
        ])
    </div>

    @include('work.partials.stage-board-scripts')
    @include('work.partials.demo-doc-upload')
</x-app-layout>
