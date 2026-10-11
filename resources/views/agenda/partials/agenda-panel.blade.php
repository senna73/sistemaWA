@include('work.partials.notifications')
@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

@include('agenda.partials.period-nav')
@include('agenda.partials.sector-plan')
@include('agenda.partials.schedules')
@include('agenda.partials.manual-form')
