<x-app-layout>
    <div class="container-xxl flex-grow-1 container-p-y">
        <h4 class="fw-bold py-3 mb-4">
            <span class="text-muted fw-light">Administração /</span> Centros de Custo
        </h4>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Listagem de Estabelecimentos</h5>
                <small class="text-muted float-end">Atribuição de Líderes</small>
            </div>
            
            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <div class="table-responsive text-nowrap">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Companhia</th>
                                <th>Status</th>
                                <th class="w-px-300">Líder Responsável</th>
                            </tr>
                        </thead>
                        <tbody class="table-border-bottom-0">
                            @foreach($companies as $company)
                            <tr>
                                <td>
                                    <div class="d-flex justify-content-start align-items-center">
                                        <div class="avatar-wrapper">
                                            <div class="avatar avatar-sm me-3">
                                                <span class="avatar-initial rounded-circle bg-label-primary">
                                                    <i class="bx bx-building"></i>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-column">
                                            <span class="fw-semibold">{{ $company->name }}</span>
                                            <small class="text-muted">ID: #{{ $company->id }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if($company->leader)
                                        <span class="badge bg-label-success">Atribuído</span>
                                    @else
                                        <span class="badge bg-label-warning">Pendente</span>
                                    @endif
                                </td>
                                <td>
                                    <form action="{{ route('companies.update-leader', $company->id) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        
                                            <select name="leader_id" onchange="this.form.submit()" class="form-select">
                                                <option value="">-- Sem Líder --</option>
                                                @foreach($leaders as $leader)
                                                    <option value="{{ $leader->id }}" 
                                                        {{ ($company->costCenter && $company->costCenter->leader_id == $leader->id) ? 'selected' : '' }}>
                                                        {{ $leader->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>