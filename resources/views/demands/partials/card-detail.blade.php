@php
    /** @var \App\Models\OperationalDemand $demand */
    $demand = $card['demand'];
    $canOperate = ! empty($card['can_operate']);
@endphp

<div class="dossier">
    <div class="dossier-layout solo">
        <div>
            <div class="dossier-stage-row">
                <div>
                    <span class="dossier-kicker">Tipo</span>
                    <p class="dossier-stage">{{ $demand->categoryLabel() }}</p>
                </div>
                @if (! empty($card['duty_label']))
                    <span class="dossier-duty {{ $card['duty'] ?? '' }}">{{ $card['duty_label'] }}</span>
                @endif
            </div>

            <div class="dossier-chips">
                <span class="dossier-chip">Status <strong>{{ $demand->statusLabel() }}</strong></span>
                @if ($demand->collaborator)
                    <span class="dossier-chip">Colaborador <strong>{{ $demand->collaborator->name }}</strong></span>
                @endif
                @if ($demand->assignee)
                    <span class="dossier-chip">Responsável <strong>{{ $demand->assignee->name }}</strong></span>
                @endif
                @if ($demand->mobile)
                    <span class="dossier-chip">Telefone <strong>{{ $demand->mobile }}</strong></span>
                @endif
            </div>

            <p class="dossier-copy">{{ $demand->request_text }}</p>

            @if ($demand->collaborator)
                <p class="dossier-note">
                    Estabelecimento: {{ $demand->collaborator->homeCompany?->name ?: '—' }}
                    · Grupo WhatsApp: {{ $demand->collaborator->group ?: '—' }}
                </p>
            @endif

            @include('demands.partials.apply-form', ['demand' => $demand, 'canOperate' => $canOperate])

            @if ($demand->agendaItem)
                <p class="dossier-note">Atividade #{{ $demand->agendaItem->id }} · {{ $demand->agendaItem->statusLabel() }}</p>
            @endif

            <div class="dossier-actions">
                @if ($canOperate && $demand->status === 'awaiting')
                    <form method="POST" action="{{ route('demands.start', $demand) }}">
                        @csrf
                        <button class="btn btn-primary btn-sm" type="submit">Atender</button>
                    </form>
                @endif
                @if ($canOperate && $demand->status === 'in_progress' && (! $demand->needsCadastroApply() || $demand->wasApplied()))
                    <form method="POST" action="{{ route('demands.review', $demand) }}">
                        @csrf
                        <button class="btn btn-warning btn-sm" type="submit">Enviar para conferência</button>
                    </form>
                @endif
                @if ($canOperate && $demand->status === 'review')
                    <form method="POST" action="{{ route('demands.finish', $demand) }}">
                        @csrf
                        <button class="btn btn-success btn-sm" type="submit">Finalizar</button>
                    </form>
                    <form method="POST" action="{{ route('demands.return', $demand) }}">
                        @csrf
                        <button class="btn btn-outline-secondary btn-sm" type="submit">Voltar para atendimento</button>
                    </form>
                @endif
                @if (! $canOperate)
                    <span class="text-muted small">Somente visualização. O RH atende estas atividades.</span>
                @endif
                <a class="dossier-link" href="{{ route('demands.show', $demand) }}">Abrir ficha completa</a>
            </div>

            <div class="dossier-section">
                <h6>Anexos</h6>
                @forelse ($demand->attachments as $attachment)
                    <div><a href="{{ route('demands.attachment', [$demand, $attachment]) }}">{{ $attachment->original_name }}</a></div>
                @empty
                    <p class="mb-0 text-muted">Sem anexos.</p>
                @endforelse
            </div>

            <div class="dossier-section">
                <h6>Histórico</h6>
                @forelse ($demand->events as $event)
                    <div class="mb-2">{{ $event->created_at->format('d/m/Y H:i') }} · {{ $event->event }} · {{ $event->user?->name }} @if($event->notes)— {{ $event->notes }}@endif</div>
                @empty
                    <p class="mb-0 text-muted">Sem histórico.</p>
                @endforelse
                @if ($canOperate)
                    <form method="POST" action="{{ route('demands.note', $demand) }}" enctype="multipart/form-data" class="mt-3">
                        @csrf
                        <textarea name="notes" class="form-control mb-2" required placeholder="Nota"></textarea>
                        <input type="file" name="attachment" class="form-control mb-2">
                        <button class="btn btn-sm btn-outline-primary" type="submit">Adicionar nota</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
