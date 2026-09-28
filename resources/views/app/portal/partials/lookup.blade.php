@if ($canSearch)
    <form method="GET" action="{{ url()->current() }}" class="mb-3">
        <div class="input-group" style="max-width: 520px;">
            <input type="text" name="q" class="form-control" value="{{ $q }}" placeholder="Nome, CPF, celular, PIX ou cidade" autofocus>
            <button class="btn btn-primary" type="submit">Buscar colaborador</button>
        </div>
    </form>
    @if ($q !== '' && $searchResults->isEmpty() && ! $collaborator)
        <p class="text-muted">Nenhum colaborador encontrado. Tente nome, CPF ou celular.</p>
    @endif
    @if ($searchResults->count() > 1)
        <ul class="list-unstyled mb-4">
            @foreach ($searchResults as $result)
                <li class="mb-2">
                    <a href="{{ url()->current() }}?collaborator_id={{ $result->id }}">
                        <strong>{{ $result->name }}</strong>
                        @if ($result->searchHint() !== '')
                            <div class="text-muted small">{{ $result->searchHint() }}</div>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
    @if ($collaborator)
        <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>Vendo como <strong>{{ $collaborator->name }}</strong></span>
            <span class="d-flex flex-wrap gap-2">
                <a class="btn btn-sm btn-outline-primary" href="{{ route('portal.show', ['collaborator_id' => $collaborator->id]) }}">Cadastro</a>
                <a class="btn btn-sm btn-outline-primary" href="{{ route('portal.earnings', ['collaborator_id' => $collaborator->id]) }}">Saldo</a>
                <a class="btn btn-sm btn-outline-primary" href="{{ route('portal.daily-rates', ['collaborator_id' => $collaborator->id]) }}">Diárias</a>
            </span>
        </div>
    @elseif ($q === '')
        <p class="text-muted">Busque um colaborador para ver a tela como se fosse ele.</p>
    @endif
@endif
