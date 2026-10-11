<x-app-layout>
    <div class="container-fluid" id="agenda-app" data-status-url="{{ route('agenda.status', ['item' => '__ID__']) }}">
        @include('agenda.partials.agenda-panel')
    </div>
    <div id="agenda-toast" class="alert shadow" role="status" hidden style="position:fixed;right:1.5rem;bottom:1.5rem;z-index:1080;max-width:24rem;"></div>
    @include('agenda.partials.live')
</x-app-layout>
