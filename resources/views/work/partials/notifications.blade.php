@php
    $notes = auth()->user()?->unreadNotifications()->latest()->limit(8)->get() ?? collect();
@endphp
@if ($notes->isNotEmpty())
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Notificações</h5></div>
        <div class="card-body">
            @foreach ($notes as $note)
                <form method="POST" action="{{ route('notifications.read', $note->id) }}" class="border rounded p-3 mb-2">
                    @csrf
                    <strong class="d-block">{{ $note->data['title'] ?? 'Aviso' }}</strong>
                    <p class="mb-2 text-muted">{{ $note->data['body'] ?? '' }}</p>
                    <button class="btn btn-sm btn-primary" type="submit">Abrir</button>
                </form>
            @endforeach
        </div>
    </div>
@endif
