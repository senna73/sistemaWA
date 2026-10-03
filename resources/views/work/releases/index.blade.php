<x-app-layout>
    <div class="container">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between">
                <h5 class="mb-0">Liberações de diária</h5>
                <a class="btn btn-sm btn-primary" href="{{ route('work.releases.create') }}">Nova solicitação</a>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Colaborador</th>
                            <th>Data</th>
                            <th>Motivo</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr>
                                <td>{{ $item->collaborator?->name }}</td>
                                <td>{{ $item->daily_on?->format('d/m/Y') }}</td>
                                <td>{{ $item->reason }}</td>
                                <td>{{ $item->status }}</td>
                                <td>
                                    @if ($canApprove && $item->status === 'pending')
                                        <form method="POST" action="{{ route('work.releases.decide', $item) }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="decision" value="approve">
                                            <button class="btn btn-sm btn-success" type="submit">Aprovar</button>
                                        </form>
                                        <form method="POST" action="{{ route('work.releases.decide', $item) }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="decision" value="refuse">
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Recusar</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">Nenhum pedido.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
