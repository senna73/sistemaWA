<x-app-layout>
    <div class="container">
        @include('app.portal.partials.lookup')

        @if ($wallet && $collaborator)
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="mb-1">{{ $canSearch ? $collaborator->name : 'Quanto você vai receber' }}</h5>
                    <h2 class="text-success mb-0">R$ {{ number_format($wallet->balance, 2, ',', '.') }}</h2>
                    <small class="text-muted">PIX: {{ $collaborator->pix_key ?: 'não informado' }}</small>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Movimentações</h5>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Descrição</th>
                                <th>Tipo</th>
                                <th>Valor</th>
                                <th>Saldo após</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($transactions as $tx)
                                <tr>
                                    <td>{{ $tx->created_at?->format('d/m/Y H:i') }}</td>
                                    <td>{{ $tx->description }}</td>
                                    <td>
                                        <span class="badge {{ $tx->type == 'credit' ? 'bg-success' : 'bg-danger' }}">
                                            {{ $tx->type == 'credit' ? 'Crédito' : 'Débito' }}
                                        </span>
                                    </td>
                                    <td>R$ {{ number_format($tx->amount, 2, ',', '.') }}</td>
                                    <td>R$ {{ number_format($tx->balance_after, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-muted">Nenhuma movimentação ainda.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-body">{{ $transactions->links() }}</div>
            </div>
        @endif
    </div>
</x-app-layout>
