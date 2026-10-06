<x-app-layout>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Dados do colaborador</h5>
            <a class="btn btn-outline-primary" href="{{ route('work.offboarding.show', $process) }}">Voltar para o dossiê</a>
        </div>
        <div class="card mb-3">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Nome completo</dt><dd class="col-sm-8">{{ $collaborator->name }}</dd>
                    <dt class="col-sm-4">Telefone</dt><dd class="col-sm-8">{{ $collaborator->mobile ?: '—' }}</dd>
                    <dt class="col-sm-4">CPF</dt><dd class="col-sm-8">{{ $collaborator->document ?: '—' }}</dd>
                    <dt class="col-sm-4">Cidade</dt><dd class="col-sm-8">{{ $collaborator->city ?: '—' }}</dd>
                    <dt class="col-sm-4">Estabelecimento</dt><dd class="col-sm-8">{{ $collaborator->homeCompany?->name ?: '—' }}</dd>
                    <dt class="col-sm-4">Grupo WhatsApp</dt><dd class="col-sm-8">{{ $collaborator->group ?: '—' }}</dd>
                    <dt class="col-sm-4">Função</dt><dd class="col-sm-8">{{ $collaborator->job_title ?: '—' }}</dd>
                    <dt class="col-sm-4">Coordenador responsável</dt><dd class="col-sm-8">{{ $collaborator->homeCompany?->coordinator?->name ?: '—' }}</dd>
                    <dt class="col-sm-4">Status no sistema WA</dt><dd class="col-sm-8">{{ $collaborator->active ? 'Ativo' : 'Inativo' }}</dd>
                    <dt class="col-sm-4">Data da última diária</dt><dd class="col-sm-8">{{ $collaborator->lastDailyAt()?->format('d/m/Y') ?? '—' }}</dd>
                    <dt class="col-sm-4">Dias sem diária</dt><dd class="col-sm-8">{{ $collaborator->daysWithoutDaily() }}</dd>
                    <dt class="col-sm-4">Situação na contabilidade</dt><dd class="col-sm-8">{{ $collaborator->accounting_code ? 'Código '.$collaborator->accounting_code : 'Sem código conferido' }} · admissão {{ $collaborator->hiredAt()?->format('d/m/Y') ?? 'não conferida' }}</dd>
                    <dt class="col-sm-4">Clínica cadastrada</dt><dd class="col-sm-8">{{ $collaborator->medicalClinic?->name ?: '—' }}</dd>
                    <dt class="col-sm-4">Observações</dt><dd class="col-sm-8">{{ $collaborator->observation ?: '—' }}</dd>
                </dl>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header">Histórico de diárias</div>
            <div class="card-body">
                @forelse ($dailies as $daily)
                    <div>{{ $daily->start?->format('d/m/Y') }} · {{ $daily->company?->name }} · R$ {{ number_format((float) $daily->pay_amount, 2, ',', '.') }}</div>
                @empty
                    <p class="mb-0 text-muted">Sem diárias.</p>
                @endforelse
            </div>
        </div>
        <div class="card">
            <div class="card-header">Histórico de solicitações</div>
            <div class="card-body">
                @forelse ($audits as $audit)
                    <div>{{ $audit->created_at?->format('d/m/Y') }} · {{ $audit->status }} · {{ $audit->days_without_daily }} dias</div>
                @empty
                    <p class="mb-0 text-muted">Sem solicitações de inatividade.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
