<x-app-layout>
    <div class="container">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        <a href="{{ route('demands.index') }}" class="btn btn-sm btn-outline-secondary mb-3">Voltar</a>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between">
                <h5 class="mb-0">{{ $demand->categoryLabel() }} · {{ $demand->statusLabel() }}</h5>
            </div>
            <div class="card-body">
                <p><strong>{{ $demand->name }}</strong> · {{ $demand->mobile }}</p>
                @if ($demand->collaborator)
                    <p class="mb-1">Colaborador vinculado: <a href="{{ route('collaborators.edit', $demand->collaborator_id) }}">{{ $demand->collaborator->name }}</a></p>
                @endif
                @if ($demand->agendaItem)
                    <p class="mb-1">Atividade #{{ $demand->agendaItem->id }} · {{ $demand->agendaItem->statusLabel() }}</p>
                @endif
                <p>{{ $demand->request_text }}</p>
                <div class="d-flex gap-2 flex-wrap">
                    @can('Atender demanda')
                        @if ($demand->status === 'awaiting')
                            <form method="POST" action="{{ route('demands.start', $demand) }}">@csrf<button class="btn btn-primary btn-sm" type="submit">Atender</button></form>
                        @endif
                        @if ($demand->status === 'in_progress')
                            <form method="POST" action="{{ route('demands.review', $demand) }}">@csrf<button class="btn btn-warning btn-sm" type="submit">Enviar para conferência</button></form>
                        @endif
                    @endcan
                    @can('Conferir demanda')
                        @if ($demand->status === 'review')
                            <form method="POST" action="{{ route('demands.finish', $demand) }}">@csrf<button class="btn btn-success btn-sm" type="submit">Finalizar</button></form>
                            <form method="POST" action="{{ route('demands.return', $demand) }}">@csrf<button class="btn btn-outline-secondary btn-sm" type="submit">Voltar para atendimento</button></form>
                        @endif
                    @endcan
                </div>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header">Anexos</div>
            <div class="card-body">
                @forelse ($demand->attachments as $attachment)
                    <div><a href="{{ route('demands.attachment', [$demand, $attachment]) }}">{{ $attachment->original_name }}</a></div>
                @empty
                    <p class="mb-0 text-muted">Sem anexos.</p>
                @endforelse
            </div>
        </div>
        <div class="card">
            <div class="card-header">Histórico</div>
            <div class="card-body">
                @foreach ($demand->events as $event)
                    <div class="mb-2">{{ $event->created_at->format('d/m/Y H:i') }} · {{ $event->event }} · {{ $event->user?->name }} @if($event->notes)— {{ $event->notes }}@endif</div>
                @endforeach
                @canany(['Atender demanda', 'Conferir demanda'])
                    <form method="POST" action="{{ route('demands.note', $demand) }}" enctype="multipart/form-data" class="mt-3">
                        @csrf
                        <textarea name="notes" class="form-control mb-2" required placeholder="Nota"></textarea>
                        <input type="file" name="attachment" class="form-control mb-2">
                        <button class="btn btn-sm btn-outline-primary" type="submit">Adicionar nota</button>
                    </form>
                @endcanany
            </div>
        </div>
    </div>
</x-app-layout>
