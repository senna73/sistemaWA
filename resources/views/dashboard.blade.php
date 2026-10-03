<x-app-layout>
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="row">
          <div class="col-lg-12 mb-4 order-0">
            <div class="card">
              <div class="d-flex align-items-end row">
                <div class="col-sm-7">
                    <div class="card-body">
                      <h5 class="card-title text-primary">
                        Seja bem-vindo {{ Auth::user()?->name ?? 'Visitante' }}! 🎉
                      </h5>
                    </div>
                  </div>
                  
                    <div class="col-sm-5 text-center text-sm-left">
                    <div class="card-body pb-0 px-0 px-md-4">
                        <!--<img
                        src="{{asset('thema/assets/img/illustrations/man-with-laptop-light.png')}}"
                        height="140"
                        alt="View Badge User"
                        data-app-dark-img="illustrations/man-with-laptop-dark.png"
                        data-app-light-img="illustrations/man-with-laptop-light.png"
                        /> -->
                    </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Icon container -->
        <div class="d-flex flex-wrap" id="icons-container">
            @if (auth()->user()?->seesCollaboratorPortal())
            <div class="card icon-card cursor-pointer text-center mb-4 mx-2">
              <a class="card-body" href="{{ route('portal.show') }}">
                <i class="bx bx-id-card mb-2"></i>
                <p class="icon-name text-capitalize text-truncate mb-0">{{ auth()->user()->isSuperAdmin() ? 'Cadastro do colaborador' : 'Meu cadastro' }}</p>
              </a>
            </div>
            @if (auth()->user()?->seesPortalEarningsAndDailyRates())
            <div class="card icon-card cursor-pointer text-center mb-4 mx-2">
              <a class="card-body" href="{{ route('portal.earnings') }}">
                <i class="bx bx-wallet mb-2"></i>
                <p class="icon-name text-capitalize text-truncate mb-0">{{ auth()->user()->isSuperAdmin() ? 'Saldo do colaborador' : 'Meu saldo' }}</p>
              </a>
            </div>
            <div class="card icon-card cursor-pointer text-center mb-4 mx-2">
              <a class="card-body" href="{{ route('portal.daily-rates') }}">
                <i class="bx bx-calendar mb-2"></i>
                <p class="icon-name text-capitalize text-truncate mb-0">{{ auth()->user()->isSuperAdmin() ? 'Diárias do colaborador' : 'Diárias' }}</p>
              </a>
            </div>
            @endif
            @endif
            @can('Acesso Work')
            <div class="card icon-card cursor-pointer text-center mb-4 mx-2">
              <a class="card-body" href="{{ route('work.home') }}">
                <i class="bx bx-briefcase mb-2"></i>
                <p class="icon-name text-capitalize text-truncate mb-0">RH Controle</p>
              </a>
            </div>
            @endcan
            @can('Acesso Work')
            @can('Solicitar desligamento')
            <div class="card icon-card cursor-pointer text-center mb-4 mx-2">
              <a class="card-body" href="{{ route('work.request') }}">
                <i class="bx bx-user-minus mb-2"></i>
                <p class="icon-name text-capitalize text-truncate mb-0">Solicitar desligamento</p>
              </a>
            </div>
            @endcan
            @endcan
            @can('Inbox RH')
            @if (auth()->user() && app(\App\Services\Work\WorkHubService::class)->seesGestorDuty(auth()->user()))
            <div class="card icon-card cursor-pointer text-center mb-4 mx-2">
              <a class="card-body" href="{{ route('rh.inbox') }}">
                <i class="bx bx-task mb-2"></i>
                <p class="icon-name text-capitalize text-truncate mb-0">Acompanhamento RH</p>
              </a>
            </div>
            @endif
            @endcan
            @can('Lista de usuários')
            <div class="card icon-card cursor-pointer text-center mb-4 mx-2">
              <a class="card-body" href="{{ route('users.index') }}">
                <i class="bx bx-collection mb-2"></i>
                <p class="icon-name text-capitalize text-truncate mb-0">Usuários</p>
              </a>
            </div>
            @endcan
            @can('Lista de estabelecimentos')
            <div class="card icon-card cursor-pointer text-center mb-4 mx-2">
              <a class="card-body" href="{{ route('companies.index') }}">
                <i class="bx bx-collection mb-2"></i>
                <p class="icon-name text-capitalize text-truncate mb-0">Estabelecimentos</p>
              </a>
            </div>
            @endcan
            @can('Lista de colaboradores')
            <div class="card icon-card cursor-pointer text-center mb-4 mx-2">
              <a class="card-body" href="{{ route('collaborators.index') }}">
                <i class="bx bx-collection mb-2"></i>
                <p class="icon-name text-capitalize text-truncate mb-0">Colaboradores</p>
              </a>
            </div>
            @endcan
            @can('Lista de diárias')
            <div class="card icon-card cursor-pointer text-center mb-4 mx-2">
              <a class="card-body" href="{{ route('daily-rate.index') }}">
                <i class="bx bx-collection mb-2"></i>
                <p class="icon-name text-capitalize text-truncate mb-0">Diarias</p>
              </a>
            </div>
            @endcan
        </div>
    </div>
</x-app-layout>