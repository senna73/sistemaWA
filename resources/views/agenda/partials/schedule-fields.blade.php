@php($current = $schedule ?? null)
<div class="col-md-4"><input name="title" class="form-control" placeholder="Título" value="{{ old('title', $current?->title ?? '') }}" required></div>
<div class="col-md-3">
    <select name="work_function" class="form-select" required>
        <option value="">Setor</option>
        @foreach ($workFunctions as $key => $label)
            <option value="{{ $key }}" @selected(old('work_function', $current?->work_function ?? '') === $key)>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-2">
    <select name="recurrence" class="form-select" required>
        @foreach ($scheduleRecurrences as $key => $label)
            <option value="{{ $key }}" @selected(old('recurrence', $current?->recurrence ?? 'monthly') === $key)>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-2">
    <select name="anchor_weekday" class="form-select">
        <option value="">Dia da semana</option>
        @foreach ($weekdays as $key => $label)
            <option value="{{ $key }}" @selected((int) old('anchor_weekday', $current?->anchor_weekday ?? 0) === (int) $key)>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-1">
    <input type="number" name="anchor_day" class="form-control" min="1" max="31" placeholder="Dia" value="{{ old('anchor_day', $current?->anchor_day ?? '') }}">
</div>
<div class="col-md-2"><input type="time" name="due_time" class="form-control" value="{{ old('due_time', isset($current) ? substr((string) $current->due_time, 0, 5) : '18:00') }}" required></div>
<div class="col-md-2">
    <select name="priority" class="form-select">
        @foreach ($priorities as $key => $label)
            <option value="{{ $key }}" @selected(old('priority', $current?->priority ?? 'normal') === $key)>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-2"><input type="number" step="0.5" min="0" name="effort_hours" class="form-control" placeholder="Horas" value="{{ old('effort_hours', $current?->effort_hours ?? '') }}"></div>
<div class="col-md-4"><input name="description" class="form-control" placeholder="Descrição" value="{{ old('description', $current?->description ?? '') }}"></div>
<div class="col-md-2 form-check ms-2">
    <input type="hidden" name="active" value="0">
    <input class="form-check-input" type="checkbox" name="active" value="1" id="active-{{ $current?->id ?? 'new' }}" @checked((string) old('active', ($current?->active ?? true) ? '1' : '0') !== '0')>
    <label class="form-check-label" for="active-{{ $current?->id ?? 'new' }}">Ativa</label>
</div>
