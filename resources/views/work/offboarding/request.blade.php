<x-app-layout>
    <div class="container">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Solicitar desligamento (coordenador)</h5>
                @can('Gerir desligamentos')
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('work.project', 'offboarding') }}">Quadro de demissões</a>
                @else
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('work.home') }}">Voltar</a>
                @endcan
            </div>
            <div class="card-body">
                <p class="text-muted">Tela do coordenador: pesquise o colaborador e envie demissão ou transferência. O RH recebe o card na fila de atendimento.</p>
                <form method="GET" action="{{ route('work.request') }}" class="row g-2 mb-3">
                    <div class="col-md-8">
                        <input name="q" value="{{ $q }}" class="form-control" placeholder="Nome, CPF, celular, PIX ou cidade" autofocus>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-primary" type="submit">Pesquisar</button>
                    </div>
                </form>
                @if ($q !== '' && $results->isEmpty() && ! $collaborator)
                    <p class="text-muted mb-0">Nenhum colaborador encontrado. Tente nome, CPF ou celular.</p>
                @endif
                @if ($results->count() > 1)
                @foreach ($results as $row)
                    <a class="d-block border rounded p-3 mb-2 text-decoration-none" href="{{ route('work.request', ['collaborator_id' => $row->id]) }}">
                        <strong>{{ $row->name }}</strong>
                        <div class="text-muted">{{ $row->searchHint() ?: ($row->group ?: '') }}</div>
                    </a>
                @endforeach
                @endif
            </div>
        </div>

        @if ($collaborator)
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ $collaborator->name }}</h5></div>
                <div class="card-body">
                    <p class="text-muted">
                        Tel: {{ $collaborator->mobile }} · Função: {{ $collaborator->job_title ?: '—' }}<br>
                        Estabelecimento: {{ $collaborator->homeCompany?->name ?: '—' }} · Grupo WhatsApp: {{ $collaborator->group ?: '—' }} · Admissão: {{ $collaborator->hiredAt()?->format('d/m/Y') ?? 'sem admissão conferida' }}<br>
                        Tempo: {{ $collaborator->tenureDays() }} dias · Diárias WA: {{ $collaborator->waDailyCount() }}<br>
                        Último dia trabalhado: {{ $collaborator->lastDailyAt()?->format('d/m/Y') ?? 'Sem diária lançada' }}
                    </p>
                    <form method="POST" action="{{ route('work.request.store') }}">
                        @csrf
                        <input type="hidden" name="collaborator_id" value="{{ $collaborator->id }}">
                        <div class="mb-3">
                            <label class="form-label">Tipo</label>
                            <select name="kind" class="form-select" required>
                                <option value="dismissal">Desligamento</option>
                                <option value="transfer">Transferência</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Motivo</label>
                            <textarea name="reason" class="form-control" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Observações</label>
                            <textarea name="notes" class="form-control"></textarea>
                        </div>
                        <button class="btn btn-primary" type="submit">Enviar ao RH</button>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
