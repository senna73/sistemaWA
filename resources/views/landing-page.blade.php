<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WA Merchandising e Serviços - Promoção de Vendas e Serviços</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-800 flex flex-col min-h-screen">

    <header class="bg-white shadow-sm py-4 px-6 flex justify-between items-center">
        <div class="flex items-center">
            <a href="javascript:void(0);">
                <img src="{{ asset('img/logo-wa.png') }}" alt="WA Merchandising" style="object-fit: cover; max-width: 200px; max-height: 48px;">
            </a>
        </div>
        <!--<div>
            @auth
                <a href="{{ route('dashboard') }}" class="text-sm font-medium text-blue-600 hover:underline">Painel / Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="text-sm font-medium text-gray-600 hover:text-blue-600 mr-4">Entrar</a>
            @endauth
        </div> -->
    </header>

    <main class="flex-grow">
        <section class="max-w-4xl mx-auto px-6 py-16 text-center">
            <h1 class="text-4xl font-extrabold text-gray-900 mb-6">Soluções Especializadas em Promoção de Vendas e Serviços</h1>
            <p class="text-lg text-gray-600 mb-8">
                Atuação focada em promoção de vendas e prestação de serviços especializados para empresas, garantindo eficiência, qualidade e suporte estratégico para o seu negócio.
            </p>
            <div class="flex justify-center gap-4">
                <a href="mailto:cm.contabilidade2050@gmail.com" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-medium shadow hover:bg-blue-700 transition">Entre em Contato</a>
            </div>
        </section>

        <section class="bg-white py-12 border-t border-gray-100">
            <div class="max-w-4xl mx-auto px-6">
                <h2 class="text-2xl font-bold text-center mb-8">Nossos Serviços</h2>
                <div class="grid md:grid-cols-2 gap-6 justify-center">
                    <div class="p-4 border rounded-lg">
                        <h3 class="font-semibold text-lg mb-2">Promoção de Vendas</h3>
                        <p class="text-sm text-gray-600">Atividade principal voltada a estratégias e ações diretas de promoção de vendas[cite: 1].</p>
                    </div>
                    <div class="p-4 border rounded-lg">
                        <h3 class="font-semibold text-lg mb-2">Serviços Especializados</h3>
                        <p class="text-sm text-gray-600">Outras atividades de serviços prestados principalmente às empresas[cite: 1].</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="bg-gray-900 text-gray-400 py-8 px-6 text-sm">
        <div class="max-w-4xl mx-auto flex flex-col md:flex-row justify-between items-center gap-4 text-center md:text-left">
            <div>
                <p class="font-semibold text-white">WA MERCHANDISING E SERVICOS LTDA</p>
                <p>CNPJ: 53.659.646/0001-41</p>
                <p class="text-xs text-gray-500 mt-1">Rua João Deola, 150, Apt 101 Bloco 13, Progresso, Blumenau - SC</p>
            </div>
            <div class="flex flex-col items-center md:items-end gap-1">
                <p>E-mail: cm.contabilidade2050@gmail.com</p>
                <p>Telefone: (47) 9213-3429</p>
                <div class="flex items-center gap-1 text-xs text-green-400 mt-1">
                    <span>🔒 Site Seguro com SSL Ativo</span>
                </div>
            </div>
        </div>
    </footer>

</body>
</html>