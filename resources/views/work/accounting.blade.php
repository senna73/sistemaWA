<x-app-layout>
    <div class="container">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0">Conferência da contabilidade</h5>
                    <div class="text-muted small">A lista importada é a regra. O sistema só mostra o desvio; nada altera sozinho.</div>
                </div>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('work.home') }}">RH Controle</a>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('work.accounting.store') }}" enctype="multipart/form-data" class="mb-4">
                    @csrf
                    <label class="form-label">Lista da contabilidade (PDF SCI, xlsx ou csv)</label>
                    <div class="d-flex gap-2 flex-wrap">
                        <input type="file" name="attachment" class="form-control" accept=".pdf,.xlsx,.csv,.txt" required>
                        <button class="btn btn-primary" type="submit">Importar e comparar</button>
                    </div>
                </form>

                @if ($check)
                    <p class="text-muted">
                        {{ $check->original_name }} · {{ $check->row_count }} na lista ·
                        {{ $ok }} conferidos · {{ $pending->count() }} fora do padrão
                    </p>
                @endif

                @if ($current)
                    <div class="border rounded p-3 mb-3">
                        <div class="text-muted small">{{ $current->bucketLabel() }} · item {{ $current->id }}</div>
                        <h5 class="mb-2">{{ $current->name }}</h5>
                        <p class="mb-2">
                            Código lista: <strong>{{ $current->code ?: '—' }}</strong>
                            · Admissão lista: <strong>{{ $current->admission_on?->format('d/m/Y') ?? '—' }}</strong><br>
                            Admissão WA: <strong>{{ $current->wa_hired_on?->format('d/m/Y') ?? 'sem admissão conferida' }}</strong>
                            · Código WA: <strong>{{ $current->wa_code ?: '—' }}</strong>
                        </p>

                        @if ($current->bucket === 'hired_at_mismatch')
                            <form method="POST" action="{{ route('work.accounting.apply', $current) }}">
                                @csrf
                                <button class="btn btn-primary" type="submit">Aplicar data de admissão da lista</button>
                            </form>
                        @elseif ($current->bucket === 'only_wa')
                            <form method="POST" action="{{ route('work.accounting.apply', $current) }}">
                                @csrf
                                <button class="btn btn-danger" type="submit">Inativar cadastro (não está na lista)</button>
                            </form>
                        @elseif ($current->bucket === 'only_list')
                            <a class="btn btn-primary" href="{{ route('collaborators.create', [
                                'name' => $current->name,
                                'hired_at' => $current->admission_on?->format('d/m/Y'),
                                'accounting_code' => $current->code,
                                'accounting_row_id' => $current->id,
                            ]) }}">Cadastrar com os dados da lista</a>
                        @elseif ($current->bucket === 'ambiguous')
                            <form method="POST" action="{{ route('work.accounting.apply', $current) }}">
                                @csrf
                                <label class="form-label">Qual cadastro permanece (o outro é inativado)</label>
                                <select name="collaborator_id" class="form-select mb-2" required>
                                    @foreach ($candidates as $candidate)
                                        <option value="{{ $candidate->id }}">
                                            {{ $candidate->name }} · última diária {{ $candidate->lastDailyAt()?->format('d/m/Y') ?? 'sem diária' }}
                                        </option>
                                    @endforeach
                                </select>
                                <button class="btn btn-primary" type="submit">Manter este cadastro</button>
                            </form>
                        @endif
                    </div>

                    <h6>Fila ({{ $pending->count() }})</h6>
                    <ul class="list-unstyled mb-0">
                        @foreach ($pending as $row)
                            <li class="border-bottom py-2 {{ $row->id === $current->id ? 'fw-bold' : '' }}">
                                <a href="{{ route('work.accounting', ['row' => $row->id]) }}">{{ $row->name }}</a>
                                · {{ $row->bucketLabel() }}
                            </li>
                        @endforeach
                    </ul>
                @elseif ($check)
                    <p class="mb-0 text-success">Nenhum desvio aberto nesta lista.</p>
                @else
                    <p class="mb-0 text-muted">Importe a lista oficial da contabilidade para começar o batimento.</p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
