<div class="row g-3 mb-3">
    <div class="col"><div class="card"><div class="card-body"><div class="text-muted">Programadas</div><h4 data-agenda-indicator="scheduled">{{ $indicators['scheduled'] }}</h4><div class="small text-muted">Ainda vão ser criadas</div></div></div></div>
    <div class="col"><div class="card"><div class="card-body"><div class="text-muted">Pendentes</div><h4 data-agenda-indicator="pending">{{ $indicators['pending'] }}</h4></div></div></div>
    <div class="col"><div class="card"><div class="card-body"><div class="text-muted">Em andamento</div><h4 data-agenda-indicator="in_progress">{{ $indicators['in_progress'] }}</h4></div></div></div>
    <div class="col"><div class="card"><div class="card-body"><div class="text-muted">Finalizadas</div><h4 data-agenda-indicator="finished">{{ $indicators['finished'] }}</h4></div></div></div>
    <div class="col"><div class="card"><div class="card-body"><div class="text-muted">Atrasadas</div><h4 data-agenda-indicator="late">{{ $indicators['late'] }}</h4></div></div></div>
</div>
