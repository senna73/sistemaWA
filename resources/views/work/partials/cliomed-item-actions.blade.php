@php
    $bucket = $group['bucket'];
    $defaultName = $personName;
@endphp

<p class="small mb-3">
    @if ($bucket === 'only_report')
        Esta pessoa veio no relatório da Cliomed e não tem cadastro correspondente na WA. Crie o colaborador ou registre que ele entra só no relatório de não cadastrados.
    @elseif ($bucket === 'only_system')
        Está ativo na WA com clínica Cliomed e não apareceu no relatório. Confira o nome e, se for o caso, apague o cadastro da lista ativa (soft delete).
    @elseif ($bucket === 'wrong_clinic')
        O nome bateu, mas a clínica na WA não é Cliomed. Confira se é a mesma pessoa e, se for, passe a clínica para Cliomed.
    @elseif ($bucket === 'inactive_in_report')
        A Cliomed ainda lista alguém já inativo na WA. Confira o nome, reative se foi um erro, ou registre a cobrança.
    @else
        Mais de um colaborador pode ser este nome. Veja os candidatos, busque outro cadastro, vincule o certo ou diga que não existe correspondente.
    @endif
</p>

@if ($bucket === 'ambiguous' && ! empty($item['candidates']))
    <div class="mb-3">
        <div class="small text-muted mb-1">Candidatos encontrados na conferência</div>
        @foreach ($item['candidates'] as $candidate)
            <form method="POST" action="{{ route('work.cliomed.resolve') }}" class="d-flex flex-wrap align-items-center gap-2 mb-2">
                @csrf
                <input type="hidden" name="check_id" value="{{ $weekly->id }}">
                <input type="hidden" name="key" value="{{ $item['_key'] }}">
                <input type="hidden" name="action" value="link">
                <input type="hidden" name="collaborator_id" value="{{ $candidate['id'] }}">
                <input type="hidden" name="name" value="{{ $candidate['name'] }}">
                <input type="hidden" name="bucket" value="" class="js-cliomed-bucket">
                <input type="hidden" name="q" value="" class="js-cliomed-q">
                <span>{{ $candidate['name'] }} <span class="text-muted">· {{ $candidate['clinic'] ?: 'sem clínica' }}{{ empty($candidate['active']) ? ' · inativo' : '' }}</span></span>
                <button class="btn btn-sm btn-outline-primary" type="submit">Vincular este</button>
            </form>
        @endforeach
    </div>

    <form method="GET" action="{{ route('work.cliomed') }}" class="row g-2 align-items-end mb-3">
        <input type="hidden" name="bucket" value="ambiguous">
        <input type="hidden" name="lookup_key" value="{{ $item['_key'] }}">
        <input type="hidden" name="open" value="{{ $item['_key'] }}">
        <div class="col-md-8">
            <label class="form-label">Buscar outro colaborador na WA</label>
            <input class="form-control" name="lookup" value="{{ $lookupKey === $item['_key'] ? $lookupTerm : '' }}" placeholder="Nome, CPF ou cidade">
        </div>
        <div class="col-md-4">
            <button class="btn btn-outline-secondary" type="submit">Buscar</button>
        </div>
    </form>

    @if ($lookupKey === $item['_key'])
        @forelse ($lookupPeople as $person)
            <form method="POST" action="{{ route('work.cliomed.resolve') }}" class="d-flex flex-wrap align-items-center gap-2 mb-2">
                @csrf
                <input type="hidden" name="check_id" value="{{ $weekly->id }}">
                <input type="hidden" name="key" value="{{ $item['_key'] }}">
                <input type="hidden" name="action" value="link">
                <input type="hidden" name="collaborator_id" value="{{ $person->id }}">
                <input type="hidden" name="name" value="{{ $person->name }}">
                <input type="hidden" name="bucket" value="" class="js-cliomed-bucket">
                <input type="hidden" name="q" value="" class="js-cliomed-q">
                <span>{{ $person->name }} <span class="text-muted">· {{ $person->clinicSlug() ?: 'sem clínica' }}{{ $person->active ? '' : ' · inativo' }}</span></span>
                <button class="btn btn-sm btn-outline-primary" type="submit">Vincular este</button>
            </form>
        @empty
            <p class="small text-muted">Nenhum colaborador com essa busca.</p>
        @endforelse
    @endif
@endif

<form method="POST" action="{{ route('work.cliomed.resolve') }}">
    @csrf
    <input type="hidden" name="check_id" value="{{ $weekly->id }}">
    <input type="hidden" name="key" value="{{ $item['_key'] }}">
    <input type="hidden" name="bucket" value="" class="js-cliomed-bucket">
    <input type="hidden" name="q" value="" class="js-cliomed-q">

    <div class="mb-3">
        <label class="form-label">Nome</label>
        <input class="form-control" name="name" value="{{ $defaultName }}" required>
    </div>

    @if ($bucket === 'only_report')
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-sm btn-primary" type="submit" name="action" value="create_in_wa">Criar cadastro na WA</button>
            <button class="btn btn-sm btn-outline-secondary" type="submit" name="action" value="report_only">Só no relatório de não cadastrados</button>
        </div>
    @elseif ($bucket === 'only_system')
        <button class="btn btn-sm btn-outline-danger" type="submit" name="action" value="deactivate" onclick="return confirm('Apagar este colaborador da lista ativa? O cadastro permanece no sistema como inativo.');">Apagar da lista ativa</button>
    @elseif ($bucket === 'wrong_clinic')
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-sm btn-primary" type="submit" name="action" value="set_cliomed">Passar clínica para Cliomed</button>
            <button class="btn btn-sm btn-outline-secondary" type="submit" name="action" value="leave_clinic">Manter clínica atual</button>
        </div>
    @elseif ($bucket === 'inactive_in_report')
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-sm btn-outline-secondary" type="submit" name="action" value="acknowledge">Confirmar inativo e cobrar</button>
            <button class="btn btn-sm btn-outline-primary" type="submit" name="action" value="reactivate">Reativar na WA</button>
        </div>
    @else
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-sm btn-outline-secondary" type="submit" name="action" value="no_match">Não existe colaborador correspondente</button>
            <button class="btn btn-sm btn-primary" type="submit" name="action" value="create_in_wa">Criar cadastro na WA</button>
        </div>
    @endif
</form>
