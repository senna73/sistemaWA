<!DOCTYPE html>

<style>
  #layout-menu {
    height: 100vh !important;
    overflow-y: auto !important;
    -webkit-overflow-scrolling: touch;

  #layout-menu .menu-inner {
    overflow-y: visible !important;
    height: auto !important;
    padding-bottom: 80px !important; 
  }

  #layout-menu::-webkit-scrollbar {
    width: 5px;
  }
  #layout-menu::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.15);
    border-radius: 4px;
  }
  #layout-menu::-webkit-scrollbar-track {
    background: transparent;
  }
</style>

<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{asset('img/favicon.ico')}}" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
      rel="stylesheet"
    />

    <!-- Icons. Uncomment required icon fonts -->
    <link rel="stylesheet" href="{{asset('thema/assets/vendor/fonts/boxicons.css')}}" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="{{asset('thema/assets/vendor/css/core.css')}}" class="template-customizer-core-css" />
    <link rel="stylesheet" href="{{asset('thema/assets/vendor/css/theme-default.css')}}" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="{{asset('thema/assets/css/demo.css')}}" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="{{asset('thema/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css')}}" />

    <link rel="stylesheet" href="{{asset('thema/assets/vendor/libs/apex-charts/apex-charts.css')}}" />

    <!-- Page CSS -->
    
    <!-- Helpers -->
    <script src="{{asset('thema/assets/vendor/js/helpers.js')}}"></script>

    <!-- Select2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

    <link rel="stylesheet" href="{{asset('vendor\rappasoft\livewire-tables\css\laravel-livewire-tables-thirdparty.min.css')}}" />
    <link rel="stylesheet" href="{{asset('vendor\rappasoft\livewire-tables\css\laravel-livewire-tables.min.css')}}" />
    

    @livewireStyles

    <script src="{{asset('thema/assets/js/config.js')}}"></script>    
  </head>

  <body>
    <!-- Layout wrapper -->
  <div class="layout-wrapper layout-content-navbar layout-menu-fixed">
      <div class="layout-container">
        <!-- Menu -->

        <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
          <div class="app-brand demo">
            <a href="javascript:void(0);" class="app-brand-link">
              <img src="{{ asset('img/logo-wa.png') }}" alt="logo-wa" style="object-fit: cover; max-width: 260px; max-height: 64px;">
            </a>

            <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
              <i class="bx bx-chevron-left bx-sm align-middle"></i>
            </a>
          </div>

          <div class="menu-inner-shadow"></div>

          <ul class="menu-inner py-1">
            <li class="menu-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
              <a href="{{ route('dashboard') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-home-circle"></i>
                <div data-i18n="Analytics">Menu Principal</div>
              </a>
            </li>

            @if (auth()->user()?->seesCollaboratorPortal())
            <li class="menu-item {{ request()->routeIs('portal.show') ? 'active' : '' }}">
                <a href="{{ route('portal.show') }}" class="menu-link">
                  <i class="menu-icon tf-icons bx bx-id-card"></i>
                  <div>{{ auth()->user()->isSuperAdmin() ? 'Cadastro do colaborador' : 'Meu cadastro' }}</div>
                </a>
            </li>
            @if (auth()->user()?->seesPortalEarnings())
            <li class="menu-item {{ request()->routeIs('portal.earnings') ? 'active' : '' }}">
                <a href="{{ route('portal.earnings') }}" class="menu-link">
                  <i class="menu-icon tf-icons bx bx-wallet"></i>
                  <div>{{ auth()->user()->isSuperAdmin() ? 'Saldo do colaborador' : 'Meu saldo' }}</div>
                </a>
            </li>
            @endif
            @if (auth()->user()?->seesPortalDailyRates())
            <li class="menu-item {{ request()->routeIs('portal.daily-rates') ? 'active' : '' }}">
                <a href="{{ route('portal.daily-rates') }}" class="menu-link">
                  <i class="menu-icon tf-icons bx bx-calendar"></i>
                  <div>{{ auth()->user()->isSuperAdmin() ? 'Diárias do colaborador' : 'Diárias' }}</div>
                </a>
            </li>
            @endif
            <li class="menu-item {{ request()->routeIs('portal.requests*') ? 'active' : '' }}">
                <a href="{{ route('portal.requests') }}" class="menu-link">
                  <i class="menu-icon tf-icons bx bx-send"></i>
                  <div>{{ auth()->user()->isSuperAdmin() ? 'Solicitações do colaborador' : 'Solicitações' }}</div>
                </a>
            </li>
            @endif

            @can('Acesso Work')
            <li class="menu-item {{ request()->routeIs('work.home') || request()->routeIs('work.project') || request()->routeIs('work.cliomed') || request()->routeIs('work.accounting') || request()->routeIs('work.releases*') || request()->routeIs('work.demo*') ? 'active' : '' }}">
                <a href="{{ route('work.home') }}" class="menu-link">
                  <i class="menu-icon tf-icons bx bx-briefcase"></i>
                  <div>RH Controle</div>
                </a>
            </li>
            @endcan
            @canany(['Abrir demanda', 'Atender demanda', 'Conferir demanda'])
            <li class="menu-item {{ request()->routeIs('demands.*') ? 'active' : '' }}">
                <a href="{{ route('demands.index') }}" class="menu-link">
                  <i class="menu-icon tf-icons bx bx-message-square-dots"></i>
                  <div>Demandas</div>
                </a>
            </li>
            @endcanany
            @can('Agenda RH')
            <li class="menu-item {{ request()->routeIs('agenda.*') ? 'active' : '' }}">
                <a href="{{ route('agenda.index') }}" class="menu-link">
                  <i class="menu-icon tf-icons bx bx-calendar-event"></i>
                  <div>Agenda</div>
                </a>
            </li>
            @endcan
            @can('Acesso Work')
            @can('Solicitar desligamento')
            <li class="menu-item {{ request()->routeIs('work.request') || request()->routeIs('work.request.store') ? 'active' : '' }}">
                <a href="{{ route('work.request') }}" class="menu-link">
                  <i class="menu-icon tf-icons bx bx-user-minus"></i>
                  <div>Solicitar desligamento</div>
                </a>
            </li>
            @endcan
            @endcan
            @can('Inbox RH')
            @if (auth()->user() && app(\App\Services\Work\WorkHubService::class)->seesGestorDuty(auth()->user()))
            <li class="menu-item {{ request()->routeIs('rh.inbox') ? 'active' : '' }}">
                <a href="{{ route('rh.inbox') }}" class="menu-link">
                  <i class="menu-icon tf-icons bx bx-task"></i>
                  <div>Acompanhamento RH</div>
                </a>
            </li>
            @endif
            @endcan

            @php
                $adminMenuVisible = auth()->user() && (
                    auth()->user()->canany([
                        'Lista de usuários',
                        'Lista de estabelecimentos',
                        'Lista de colaboradores',
                        'Lista de diárias',
                        'Visualizar e inserir informações financeiras nas diárias',
                        'Processar boletos e confirmar recebimento',
                        'Gerir pagamento de colaboradores e custos',
                        'Gestão dos centros de custo',
                        'Acesso aos dados de diárias',
                        'Visualizar livro razão',
                    ]) || ! auth()->user()->isCollaboratorRole()
                );
            @endphp
            @if ($adminMenuVisible)
            <li class="menu-header small text-uppercase"><span class="menu-header-text">Administração</span></li>

            @can('Lista de usuários')
            <li class="menu-item {{ request()->routeIs('users.index') ? 'active' : '' }}">
                <a href="{{ route('users.index') }}" class="menu-link">
                  <i class="menu-icon tf-icons bx bx-user"></i>
                  <div data-i18n="Basic">Usuários</div>
                </a>
            </li>
            <li class="menu-item {{ request()->routeIs('users.deleted') ? 'active' : '' }}">
                <a href="{{ route('users.deleted') }}" class="menu-link">
                  <i class="menu-icon tf-icons bx bx-user-x"></i>
                  <div data-i18n="Basic">Usuários apagados</div>
                </a>
            </li>
            @endcan

            @can('Lista de estabelecimentos')
            <li class="menu-item {{ request()->routeIs('companies.index') ? 'active' : '' }}">
              <a href="{{ route('companies.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-store"></i>
                <div data-i18n="Basic">Estabelecimentos</div>
              </a>
            </li>
            @endcan

            @can('Lista de colaboradores')
            <li class="menu-item {{ request()->routeIs('collaborators.index') ? 'active' : '' }}">
                <a href="{{ route('collaborators.index') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-group"></i>
                    <div data-i18n="Basic">Colaboradores</div>
                </a>
            </li>
            <li class="menu-item {{ request()->routeIs('collaborators.deleted') ? 'active' : '' }}">
                <a href="{{ route('collaborators.deleted') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-user-x"></i>
                    <div data-i18n="Basic">Colaboradores apagados</div>
                </a>
            </li>
            @endcan

            @can('Lista de diárias')
            <li class="menu-item {{ request()->routeIs('daily-rate.index') ? 'active' : '' }}">
                <a href="{{ route('daily-rate.index') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-calendar-event"></i>
                    <div data-i18n="Basic">Diárias</div>
                </a>
            </li>
            @endcan

            @can('Visualizar e inserir informações financeiras nas diárias')
            <li class="menu-item {{ request()->routeIs('finantial-results') ? 'active' : '' }}">
                <a href="{{ route('finantial-results') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-pie-chart-alt-2"></i>
                    <div data-i18n="Basic">Analytics Financeiro</div>
                </a>
            </li>
            @endcan

            @can('Processar boletos e confirmar recebimento')
            <li class="menu-item {{ request()->routeIs('admin.batches.index') ? 'active' : '' }}">
                <a href="{{ route('admin.batches.index') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-cog"></i>
                    <div data-i18n="Basic">Processamento</div>
                </a>
            </li>
            @endcan

            @if (auth()->user()?->isSuperAdmin())
            <li class="menu-item {{ request()->routeIs('portal.earnings') || request()->routeIs('admin.collaborator.earnings') ? 'active' : '' }}">
                <a href="{{ route('portal.earnings') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-wallet"></i>
                    <div data-i18n="Basic">Ganhos de Colaborador</div>
                </a>
            </li>
            <li class="menu-item {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                <a href="{{ route('settings.index') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-cog"></i>
                    <div data-i18n="Basic">Configurações</div>
                </a>
            </li>
            @endif

            @can('Gestão dos centros de custo')
            <li class="menu-item {{ request()->routeIs('admin.leader.cost-center.index') ? 'active' : '' }}">
                <a href="{{ route('admin.leader.cost-center.index') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-list-check"></i>
                    <div data-i18n="Basic">Centro de Custo</div>
                </a>
            </li>
            @endcan

            @can('Gerir pagamento de colaboradores e custos')
            <li class="menu-item {{ request()->routeIs('admin.finance.processor.index') ? 'active' : '' }}">
                <a href="{{ route('admin.finance.processor.index') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-credit-card"></i>
                    <div data-i18n="Basic">Centro de Pagamentos</div>
                </a>
            </li>
            @endcan

            @can('Gestão dos centros de custo')
            <li class="menu-item {{ request()->routeIs('cost-centers.index') ? 'active' : '' }}">
                <a href="{{ route('cost-centers.index') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-buildings"></i>
                    <div data-i18n="Basic">Gestão de Centros</div>
                </a>
            </li>
            @endcan

            @can('Acesso aos dados de diárias')
            <li class="menu-item {{ request()->routeIs('analytics.*') ? 'active' : '' }}">
                <a href="{{ route('analytics.index') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-bar-chart-alt-2"></i>
                    <div data-i18n="Basic">Análise de Dados</div>
                </a>
            </li>
            @endcan

            @can('Visualizar livro razão')
            <li class="menu-item {{ request()->routeIs('admin.finance.ledger.index') ? 'active' : '' }}">
                <a href="{{ route('admin.finance.ledger.index') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-book-content"></i>
                    <div data-i18n="Basic">Capital Empresarial</div>
                </a>
            </li>
            @endcan
            @if (!auth()->user()->isCollaboratorRole())
            <li class="menu-item {{ request()->routeIs('admin.uniforms.*') ? 'active' : '' }}">
                <a href="{{ route('admin.uniforms.index') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-closet"></i>
                    <div data-i18n="Basic">Uniformes</div>
                </a>
            </li>
            @endif
            @endif
          </ul>
        </aside>
        <!-- / Menu -->

        <!-- Layout container -->
        <div class="layout-page">
          <!-- Navbar -->

          <nav
            class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme mb-3"
            id="layout-navbar"
          >
            <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
              <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                <i class="bx bx-menu bx-sm"></i>
              </a>
            </div>

            <div class="nav-item d-flex align-items-center">
              <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                <i class='bx bx-arrow-back fs-4 lh-0' onclick="window.history.back();"></i>
              </a>
            </div>

            <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
              {{-- <!-- Search -->
              <div class="navbar-nav align-items-center">
                <div class="nav-item d-flex align-items-center">
                  <i class="bx bx-search fs-4 lh-0"></i>
                  <input
                    type="text"
                    class="form-control border-0 shadow-none"
                    placeholder="Search..."
                    aria-label="Search..."
                  />
                </div>
              </div>
              <!-- /Search --> --}}

              <ul class="navbar-nav flex-row align-items-center ms-auto">
                <div class="nav-item d-flex align-items-center">
                  <a class="nav-item nav-link px-0 me-xl-4" href="{{ route('logout') }}">
                    <i class='bx bx-log-out fs-4 lh-0'></i>
                  </a>
                </div>
                <!-- User -->
                  {{-- <li class="nav-item navbar-dropdown dropdown-user dropdown">
                    <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                      <div class="avatar avatar-online">
                        <img src="{{asset('thema/assets/img/avatars/1.png')}}" alt class="w-px-40 h-auto rounded-circle" />
                      </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                      <li>
                        <a class="dropdown-item" href="#">
                          <div class="d-flex">
                            <div class="flex-shrink-0 me-3">
                              <div class="avatar avatar-online">
                                <img src="{{asset('thema/assets/img/avatars/1.png')}}" alt class="w-px-40 h-auto rounded-circle" />
                              </div>
                            </div>
                            <div class="flex-grow-1">
                              <span class="fw-semibold d-block">{{ Auth::user()?->name ?? 'Desconhecido' }}</span>
                              <small class="text-muted">{{ Auth::user()?->role ?? 'Sem permissão' }}</small>
                            </div>
                          </div>
                        </a>
                      </li>
                      <li>
                        <div class="dropdown-divider"></div>
                      </li>
                      <li>
                        <a class="dropdown-item" href="{{ route('logout') }}">
                          <i class="bx bx-power-off me-2"></i>
                          <span class="align-middle">Desconectar</span>
                        </a>
                      </li>
                    </ul>
                  </li> --}}
                <!--/ User -->
              </ul>
            </div>
          </nav>

          <!-- / Navbar -->

          <!-- Content wrapper -->
          <div class="content-wrapper">
            <!-- Content -->
            <div class="container-xxl flex-grow-1 container-p-y">
              {{$slot}}
            </div>
            <!-- / Content -->

            <!-- Footer -->
            <footer class="content-footer footer bg-footer-theme mt-5">
              <div class="container-xxl d-flex flex-wrap justify-content-between py-2 flex-md-row flex-column">
                <div class="mb-2 mb-md-0">
                  ©
                  <script>
                    document.write(new Date().getFullYear());
                  </script>
                  , feito por
                  <a href="javascript:void(0)" class="footer-link fw-bolder">Vinícius de Senna & Fhillype Leal</a>
                </div>
                <div>
                  <a
                    href="https://wa.me/{{env('SUPORT_PHONE')}}?text=Olá,%20preciso%20de%20suporte%20para%20o%20sistema%20{{env('APP_NAME')}}!" 
                    target="_blank"
                    class="footer-link me-4"
                    >Suporte</a
                  >
                </div>
              </div>
            </footer>
            <!-- / Footer -->

            <div class="content-backdrop fade"></div>
          </div>
          <!-- Content wrapper -->
        </div>
        <!-- / Layout page -->
      </div>

      <!-- Overlay -->
      <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    <!-- / Layout wrapper -->

    <!-- Core JS -->
    <!-- build:js assets/vendor/js/core.js -->
    <script src="{{asset('thema/assets/vendor/libs/jquery/jquery.js')}}"></script>
    <script src="{{asset('thema/assets/vendor/libs/popper/popper.js')}}"></script>
    <script src="{{asset('thema/assets/vendor/js/bootstrap.js')}}"></script>
    <script src="{{asset('thema/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js')}}"></script>

    <script src="{{asset('thema/assets/vendor/js/menu.js')}}"></script>
    <!-- endbuild -->

    <!-- Vendors JS -->
    <script src="{{asset('thema/assets/vendor/libs/apex-charts/apexcharts.js')}}"></script>

    <!-- Main JS -->
    <script src="{{asset('thema/assets/js/main.js')}}"></script>

    <!-- Page JS -->
    <script src="{{asset('thema/assets/js/dashboards-analytics.js')}}"></script>

    <!-- Place this tag in your head or just before your close body tag. -->
    <script async defer src="https://buttons.github.io/buttons.js"></script>

    <!-- Sweet Alert -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Datatable -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/rowreorder/1.4.1/css/rowReorder.bootstrap5.min.css">

    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/rowreorder/1.4.1/js/dataTables.rowReorder.min.js"></script>

    <!-- Select2 -->
    {{-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script> --}}
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- Input Mask -->
    <script src="https://cdn.jsdelivr.net/npm/inputmask@5.0.6/dist/inputmask.min.js"></script>

    <script src="{{ asset('vendor\rappasoft\livewire-tables\js\laravel-livewire-tables.min.js') }}"></script>
    <script src="{{ asset('vendor\rappasoft\livewire-tables\js\laravel-livewire-tables-thirdparty.min.js') }}"></script>
	  @livewireScripts

  </body>

</html>
