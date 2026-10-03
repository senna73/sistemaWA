<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Relatorio de Cobranca - Remocao de Inativos da Clinica</title>
</head>
<body>
    <h2>Relatorio de Cobranca - Remocao de Inativos da Clinica</h2>
    @forelse ($names as $name)
        <p>{{ $name }}</p>
    @empty
        <p>Nenhum inativo pendente nesta conferência.</p>
    @endforelse
</body>
</html>
