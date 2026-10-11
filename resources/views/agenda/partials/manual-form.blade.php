<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Nova atividade</h5></div>
    <div class="card-body">
        <p class="small text-muted mb-3">A atividade entra na fila do setor. O responsável é opcional: quem tem aquele setor consegue ver e puxar o trabalho.</p>
        <form method="POST" action="{{ route('agenda.store') }}" class="row g-2" data-agenda-form="refresh">
            @csrf
            <div class="col-md-4"><input name="title" class="form-control" placeholder="Título" required></div>
            <div class="col-md-3">
                <select name="work_function" class="form-select" required>
                    <option value="">Setor</option>
                    @foreach ($workFunctions as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="assignee_id" class="form-select">
                    <option value="">Fila do setor</option>
                    @foreach ($functionMembers as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} · {{ $workFunctions[$user->work_function] ?? $user->work_function }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><input type="datetime-local" name="due_at" class="form-control"></div>
            <div class="col-md-2">
                <select name="type" class="form-select" required>
                    @foreach ($types as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="recurrence" class="form-select">
                    @foreach ($recurrences as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12"><input name="description" class="form-control" placeholder="Descrição"></div>
            <div class="col-md-3">
                <select name="collaborator_id" class="form-select">
                    <option value="">Colaborador (quando o POP exigir)</option>
                    @foreach ($collaborators as $person)
                        <option value="{{ $person->id }}">{{ $person->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="company_id" class="form-select">
                    <option value="">Estabelecimento</option>
                    @foreach ($companies as $company)
                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                    @endforeach
                </select>
            </div>
            @if (! empty($demandCategories))
                <div class="col-md-3">
                    <select name="category" class="form-select">
                        <option value="">Categoria da demanda</option>
                        @foreach ($demandCategories as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-3"><input name="area" class="form-control" placeholder="Projeto/área"></div>
            <div class="col-md-2"><input name="priority" class="form-control" value="normal" placeholder="Prioridade"></div>
            <div class="col-md-2"><button class="btn btn-primary" type="submit">Criar</button></div>
        </form>
    </div>
</div>
