<div class="dashboard-container">
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <h5>Saldo da Carteira</h5>
                <span id="wallet-balance">Carregando...</span>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <h5>Total Geral</h5>
                <span id="total-general">Carregando...</span>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        fetch('/api/leader-center/stats') 
            .then(response => response.json())
            .then(json => {
                const stats = json.data;
                
                // Popula o HTML
                document.getElementById('wallet-balance').innerText = 'R$ ' + stats.balance.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                document.getElementById('total-general').innerText = 'R$ ' + stats.total.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
            })
            .catch(error => {
                console.error('Erro ao buscar dados:', error);
                document.getElementById('wallet-balance').innerText = 'Erro';
                document.getElementById('total-general').innerText = 'Erro';
            });
    });
</script>