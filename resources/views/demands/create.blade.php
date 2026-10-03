<x-app-layout>
    <div class="container">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Abrir demanda</h5></div>
            <div class="card-body">
                <form method="POST" action="{{ route('demands.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input name="name" class="form-control" value="{{ $collaborator?->name }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Telefone</label>
                        <input name="mobile" class="form-control" value="{{ $collaborator?->mobile }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Categoria</label>
                        <select name="category" class="form-select" required>
                            @foreach ($categories as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Pedido</label>
                        <textarea name="request_text" class="form-control" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Anexos (até 3)</label>
                        <input type="file" name="attachments[]" class="form-control" multiple>
                    </div>
                    <button class="btn btn-primary" type="submit">Abrir</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
