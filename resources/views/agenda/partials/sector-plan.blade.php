<div class="row g-3 mb-4">
    @foreach ($planned as $sector)
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between">
                    <span>{{ $sector['label'] }}</span>
                    <span class="text-muted">{{ $sector['items']->count() + $sector['upcoming']->count() }}</span>
                </div>
                <div class="card-body">
                    @forelse ($sector['items'] as $item)
                        <div class="border rounded p-2 mb-2 {{ $item->effectiveStatus() === 'late' ? 'border-danger' : '' }}">
                            <div class="d-flex justify-content-between gap-2">
                                <strong>{{ $item->title }}</strong>
                                <span class="small text-muted">{{ $item->due_at?->format('d/m H:i') }}</span>
                            </div>
                            <div class="small text-muted">
                                {{ $item->statusLabel() }}
                                · {{ $item->assignee?->name ?? 'Fila do setor' }}
                            </div>
                        </div>
                    @empty
                        @if ($sector['upcoming']->isEmpty())
                            <p class="text-muted mb-0">Nada programado neste período.</p>
                        @endif
                    @endforelse
                    @foreach ($sector['upcoming'] as $row)
                        <div class="border rounded p-2 mb-2 border-dashed">
                            <div class="d-flex justify-content-between gap-2">
                                <strong>{{ $row['title'] }}</strong>
                                <span class="small text-muted">{{ $row['due_at']->format('d/m H:i') }}</span>
                            </div>
                            <div class="small text-muted">Programada · entra sozinha quando o prazo se aproxima</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
</div>
