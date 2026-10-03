<x-app-layout>
    <div class="container">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        <div class="d-flex justify-content-between mb-3">
            <h5 class="mb-0">Demandas operacionais</h5>
            @can('Abrir demanda')
                <a class="btn btn-primary" href="{{ route('demands.create') }}">Nova demanda</a>
            @endcan
        </div>
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
    </div>
</x-app-layout>
