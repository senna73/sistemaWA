@php
    $applyFields = \App\Support\PopCatalog::demandApplyFields()[$demand->category] ?? [];
    $payload = $demand->payload ?? [];
    $applied = ! empty($payload['applied']);
    $collaborator = $demand->collaborator;
    $groups = $groups ?? \App\Models\Collaborator::query()
        ->whereNotNull('group')
        ->where('group', '!=', '')
        ->distinct()
        ->orderBy('group')
        ->pluck('group');
@endphp

@if ($applyFields !== [])
    <div class="dossier-section">
        <h6>Pedido no cadastro</h6>
        @if ($collaborator)
            <p class="mb-2 small text-muted">
                Estabelecimento: {{ $collaborator->homeCompany?->name ?: '—' }}
                · Grupo WhatsApp atual: {{ $collaborator->group ?: '—' }}
                @if (in_array('pix_key', $applyFields, true))
                    · Pix atual: {{ $collaborator->pix_key ?: '—' }}
                @endif
            </p>
        @endif
        @if ($applied)
            <p class="mb-0 text-success small">Cadastro já atualizado neste card.</p>
        @elseif (! empty($canOperate) && $demand->status === 'in_progress')
            <form method="POST" action="{{ route('demands.apply', $demand) }}">
                @csrf
                @if (in_array('pix_key', $applyFields, true))
                    <label class="form-label">Nova chave Pix</label>
                    <input name="payload[pix_key]" class="form-control mb-2" value="{{ $payload['pix_key'] ?? $collaborator?->pix_key }}">
                @endif
                @if (in_array('group', $applyFields, true))
                    <label class="form-label">Grupo WhatsApp de destino</label>
                    <input name="payload[group]" class="form-control mb-2" list="demand-whatsapp-groups-{{ $demand->id }}" value="{{ $payload['group'] ?? $collaborator?->group }}">
                    <datalist id="demand-whatsapp-groups-{{ $demand->id }}">
                        @foreach ($groups as $group)
                            <option value="{{ $group }}"></option>
                        @endforeach
                    </datalist>
                @endif
                @if (in_array('name', $applyFields, true))
                    <label class="form-label">Nome</label>
                    <input name="payload[name]" class="form-control mb-2" value="{{ $payload['name'] ?? $collaborator?->name }}">
                    <label class="form-label">Celular</label>
                    <input name="payload[mobile]" class="form-control mb-2" value="{{ $payload['mobile'] ?? $collaborator?->mobile }}">
                    <label class="form-label">Documento</label>
                    <input name="payload[document]" class="form-control mb-2" value="{{ $payload['document'] ?? $collaborator?->document }}">
                @endif
                <button class="btn btn-sm btn-primary" type="submit">Confirmar no cadastro</button>
            </form>
        @else
            <ul class="mb-0 small">
                @foreach ($applyFields as $field)
                    @if (filled($payload[$field] ?? null))
                        <li>{{ $field }}: {{ $payload[$field] }}</li>
                    @endif
                @endforeach
            </ul>
            @if (! empty($canOperate) && $demand->status === 'awaiting')
                <p class="small text-muted mb-0 mt-2">Atenda o card para gravar o cadastro daqui.</p>
            @endif
        @endif
    </div>
@endif
