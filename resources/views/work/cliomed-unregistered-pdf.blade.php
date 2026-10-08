<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Relatorio Cliomed - Nao cadastrados na WA</title>
</head>
<body>
    <h2>Relatorio Cliomed - Enviados pela clinica e sem cadastro na WA</h2>
    @forelse ($names as $name)
    <p>{{ $name }}</p>
    @empty
        <p>Nenhum nome só na Cliomed nesta conferência.</p>
    @endforelse
</body>
</html>
