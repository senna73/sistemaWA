<x-app-layout>
    <div class="container">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="card mb-4 {{ $state['needs_action'] ? 'border-warning' : '' }}">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <div class="text-muted small">{{ $state['kicker'] }}</div>
                    <h5 class="mb-0">Conferir base Cliomed</h5>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge {{ $state['needs_action'] ? 'bg-label-warning' : 'bg-label-secondary' }}">{{ $state['badge'] }}</span>
                    @can('Gerir desligamentos')
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('work.cliomed.unregistered') }}">PDF não cadastrados</a>
                        <a class="btn btn-sm btn-outline-danger" href="{{ route('work.cliomed.charge') }}">PDF cobrança de inativos</a>
                    @endcan
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('work.home') }}">RH Controle</a>
                </div>
            </div>
            <div class="card-body">
                @include('work.partials.cliomed-weekly', ['weekly' => $weekly, 'state' => $state, 'groups' => $groups])
            </div>
        </div>
    </div>
</x-app-layout>
