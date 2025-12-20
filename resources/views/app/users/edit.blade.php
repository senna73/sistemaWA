<x-app-layout>
    <div class="container">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Cadastrando usuário</h5>
            </div>
            <div class="card-body">
                <form id="form-edit-user">
                    <div class="mb-3">
                        <label class="form-label" for="basic-default-fullname">Nome</label>
                        <input type="text" class="form-control" id="basic-default-fullname" name="name" placeholder="João Doe" value="{{ $user?->name ?? ''}}" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="basic-default-company">Email</label>
                        <input type="email" class="form-control" id="basic-default-company" name="email" placeholder="exemplo@exemplo.com" value="{{ $user?->email ?? ''}}" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="collaborator_id">Colaborador</label>
                        <select class="form-control" id="collaborator_id" name="collaborator_id" >
                            <option value="" disabled selected>Selecione um colaborador</option>
                            @foreach ($collaborators as $colaborator)
                                <option value="{{ $colaborator->id }}" {{ ($user?->collaborator_id ?? 0) == $colaborator->id ? 'selected' : '' }}>
                                    {{ $colaborator->name }}
                                </option>                            
                            @endforeach

                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="allowed_companies">Estabelecimentos</label>
                        <select multiple name="allowed_companies[]" id="allowed_companies" class="form-control">
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}"
                                    {{ in_array($company->id, $selectedCompanies ?? []) ? 'selected' : '' }}>
                                    {{ $company->name }}
                                </option>
                            @endforeach
                        </select>

                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="basic-default-company">Senha</label>
                        <input type="password" class="form-control" id="basic-default-company" name="password" />
                    </div>
                    <div class="">
                        <label class="form-label" for="basic-default-company">Confirmar Senha</label>
                        <input type="password" class="form-control" id="basic-default-company" name="password_confirmation" />
                        <div class="form-text">A confirmação de senha deve ser igual a senha!</div>
                    </div>
                </form>
            </div>
            <hr class="m-0">
            <div class="card-body">
                <form id="form-permissions-user">
                    @foreach ($permissions as $permission)
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="permission-{{ $permission->id }}" name="permissions[{{ $permission->id }}]" @checked($user?->can($permission->name) ?? false) >
                            <label class="form-check-label" for="flexSwitchCheckDefault">{{ $permission->name }}</label>
                        </div>
                    @endforeach
                </form>
            </div>
            <div class="card-footer">
                @if ($user?->id ?? false)
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-primary right" onclick="update({{ $user?->id ?? null }})">Salvar</button>
                    </div>
                @else
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-primary right" onclick="post()">Salvar</button>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

<script>
    $(document).ready(function() {
    $('#allowed_companies').select2({
            placeholder: "Selecione os estabelecimentos",
            allowClear: true
        });
    });
    function post() {
        $.ajax({
            url: '{{ route('users.store') }}',
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: $('#form-edit-user').serialize() + '&' + $('#form-permissions-user').serialize(),
            success: function(response) {
                Swal.fire({
                    title: response?.title ?? 'Sucesso!',
                    text: response?.message ?? 'Sucesso na ação!',
                    icon: response?.type ?? 'success'
                }).then((result) => {
                    $('#form-edit-user')[0].reset();
                    $('#form-permissions-user')[0].reset();

                    window.location.reload();
                });
            },
            error: function(response) {
                response = JSON.parse(response.responseText);
                Swal.fire({
                    title: response?.title ?? 'Oops!',
                    html: response?.message?.replace(/\n/g, '<br>') ?? 'Erro na ação!',
                    icon: response?.type ?? 'error'
                });
            }
        });
    }

    function update(id) {
        let formData = $('#form-edit-user').serialize();
        
        let permissionsData = $('#form-permissions-user').serialize();
        
        if (!permissionsData) {
            permissionsData = "permissions="; 
        }

        $.ajax({
            url: "{{ route('users.update', '') }}" + '/' + id,
            type: 'PUT',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            // Concatenamos os dois
            data: formData + '&' + permissionsData,
            success: function(response) {
                Swal.fire({
                    title: response?.title ?? 'Sucesso!',
                    text: response?.message ?? 'Usuário atualizado com sucesso!',
                    icon: response?.type ?? 'success'
                }).then(() => {
                    window.location.reload();
                });
            },
            error: function(response) {
                let errorData = JSON.parse(response.responseText);
                Swal.fire({
                    title: errorData?.title ?? 'Oops!',
                    html: errorData?.message?.replace(/\n/g, '<br>') ?? 'Erro na ação!',
                    icon: 'error'
                });
            }
        });
    }
</script>