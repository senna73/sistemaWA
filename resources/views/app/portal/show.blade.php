<x-app-layout>
    <div class="container">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        @include('work.partials.notifications')
        @include('app.portal.partials.lookup')

        @if ($collaborator)
        <div class="card mb-4">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <div class="text-muted">Saldo da carteira</div>
                    <h3 class="text-success mb-0">R$ {{ number_format($wallet->balance, 2, ',', '.') }}</h3>
                </div>
                <a class="btn btn-outline-primary" href="{{ route('portal.earnings', $canSearch ? ['collaborator_id' => $collaborator->id] : []) }}">Ver saldo</a>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ $canSearch ? 'Cadastro' : 'Meu cadastro' }}</h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">Nome</dt>
                    <dd class="col-sm-9">{{ $collaborator->name }}</dd>
                    <dt class="col-sm-3">Celular / WhatsApp</dt>
                    <dd class="col-sm-9">{{ $collaborator->mobile ?: '—' }}</dd>
                    <dt class="col-sm-3">Chave PIX</dt>
                    <dd class="col-sm-9">{{ $collaborator->pix_key ?: '—' }}</dd>
                    <dt class="col-sm-3">Cidade</dt>
                    <dd class="col-sm-9">{{ $collaborator->city ?: '—' }}</dd>
                    <dt class="col-sm-3">Tamanho do uniforme</dt>
                    <dd class="col-sm-9">{{ $collaborator->uniform_size ?: '—' }}</dd>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Demissão</h5>
            </div>
            <div class="card-body">
                @if ($process)
                    <p>Fase atual: <strong>{{ $process->label() }}</strong></p>
                    <ul>
                        @foreach ($process->events()->latest()->get() as $event)
                            <li>{{ $event->created_at->format('d/m/Y H:i') }} — {{ \App\Models\OffboardingProcess::LABELS[$event->to_status] ?? $event->to_status }}</li>
                        @endforeach
                    </ul>
                @elseif ($canSearch)
                    <p class="mb-0 text-muted">Este colaborador não tem pedido de demissão em andamento.</p>
                @else
                    <p>Você pode pedir demissão. Isso abre um card no RH, vinculado ao seu cadastro. O coordenador é notificado.</p>
                    <form method="POST" action="{{ route('portal.dismissal') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Motivo (opcional)</label>
                            <textarea name="notes" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Carta de demissão</label>
                            <select name="letter_status" class="form-select">
                                <option value="pendente">Pendente (envio depois)</option>
                                <option value="anexada">Anexada agora</option>
                                <option value="erro">Arquivo com erro / ilegível</option>
                            </select>
                            <input type="file" name="letter" class="form-control mt-2">
                        </div>
                        <button class="btn btn-danger" type="submit">Pedir demissão</button>
                    </form>
                @endif
            </div>
        </div>
        @endif
    </div>
</x-app-layout>
