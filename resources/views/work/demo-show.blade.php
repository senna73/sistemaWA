<x-app-layout>
    <div class="container">
        <div class="alert alert-warning py-2 px-3">Demonstração para cliente · dados ilustrativos.</div>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">{{ $card['kind'] }} · {{ $card['name'] }}</h5>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('work.demo', $mode) }}">Voltar ao quadro</a>
            </div>
            <div class="card-body">
                @include('work.partials.demo-card-detail', ['card' => $card])
            </div>
        </div>
    </div>
    @include('work.partials.demo-doc-upload')
</x-app-layout>
