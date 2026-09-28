<x-app-layout>
    <div class="container">
        @include('app.portal.partials.lookup')

        @if ($collaborator && $dailyRates)
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Diárias</h5>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Estabelecimento</th>
                            <th>Valor</th>
                            <th>Situação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dailyRates as $daily)
                            @php
                                $situation = match ($daily->status) {
                                    'processado' => 'Recebida',
                                    'cancelado' => 'Cancelada',
                                    default => 'A receber',
                                };
                            @endphp
                            <tr>
                                <td>{{ $daily->start?->format('d/m/Y') }}</td>
                                <td>{{ $daily->company?->name ?? '—' }}</td>
                                <td>R$ {{ number_format((float) $daily->pay_amount, 2, ',', '.') }}</td>
                                <td>{{ $situation }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-muted">Nenhum registro.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-body">{{ $dailyRates->links() }}</div>
        </div>
        @endif
    </div>
</x-app-layout>
