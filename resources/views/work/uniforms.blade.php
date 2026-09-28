<x-app-layout>
    <div class="container">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Operação e uniformes</h5>
                <div class="d-flex gap-2">
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('work.home') }}">RH Controle</a>
                    <a class="btn btn-sm btn-primary" href="{{ route('admin.uniforms.index') }}">Módulo de uniformes</a>
                </div>
            </div>
            <div class="card-body">
                @forelse ($pending as $item)
                    <div class="border rounded p-3 mb-2">
                        <strong>{{ $item->collaborator?->name }}</strong>
                        <div class="text-muted">Qtd {{ $item->quantity }} · sem entrega</div>
                    </div>
                @empty
                    <p class="mb-0">Nenhuma entrega pendente cadastrada.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
