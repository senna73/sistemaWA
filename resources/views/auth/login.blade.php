<!DOCTYPE html>
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
    <link rel="stylesheet" href="{{ asset('thema/assets/vendor/fonts/boxicons.css') }}" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="{{ asset('thema/assets/vendor/css/core.css') }}" class="template-customizer-core-css" />
    <link rel="stylesheet" href="{{ asset('thema/assets/vendor/css/theme-default.css') }}" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="{{ asset('thema/assets/css/demo.css') }}" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="{{ asset('thema/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />

    <!-- Page CSS -->
    <!-- Page -->
    <link rel="stylesheet" href="{{ asset('thema/assets/vendor/css/pages/page-auth.css') }}" />
    <!-- Helpers -->
    <script src="{{ asset('thema/assets/vendor/js/helpers.js') }}"></script>

    <script src="{{ asset('thema/assets/js/config.js') }}"></script>
  </head>

  <body>
    <!-- Content -->

    <div class="container-xxl">
      <div class="authentication-wrapper authentication-basic container-p-y">
        <div class="authentication-inner">
          <!-- Register -->
          <div class="card">
            <div class="card-body">

              <h4 class="mb-2">Bem-vindo ao sistema WA! 👋</h4>
              <p class="mb-4">Informe seu CPF ou e-mail para entrar.</p>

              @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
              @endif

              @if (($step ?? 'identifier') === 'password')
                <form method="POST" action="{{ route('login') }}">
                  @csrf
                  <input type="hidden" name="identifier" value="{{ $identifier }}" />
                  <div class="mb-3">
                    <label class="form-label">E-mail / CPF</label>
                    <input type="text" class="form-control" value="{{ $identifier }}" disabled />
                  </div>
                  <div class="mb-3 form-password-toggle">
                    <label class="form-label" for="password">Senha</label>
                    <div class="input-group input-group-merge">
                      <input type="password" id="password" class="form-control" name="password" autofocus />
                      <span class="input-group-text cursor-pointer"><i class="bx bx-hide"></i></span>
                    </div>
                  </div>
                  <div class="mb-3">
                    <button class="btn btn-primary d-grid w-100" type="submit">Entrar</button>
                  </div>
                </form>
                <div class="text-center">
                  <a href="{{ route('login', ['change' => 1]) }}">Usar outro CPF ou e-mail</a>
                </div>
              @else
                <form method="POST" action="{{ route('login.identify') }}">
                  @csrf
                  <div class="mb-3">
                    <label for="identifier" class="form-label">CPF ou e-mail</label>
                    <input
                      type="text"
                      class="form-control"
                      id="identifier"
                      name="identifier"
                      placeholder="CPF ou e-mail"
                      value="{{ old('identifier') }}"
                      autofocus
                    />
                  </div>
                  <div class="mb-3">
                    <button class="btn btn-primary d-grid w-100" type="submit">Continuar</button>
                  </div>
                </form>
              @endif

              {{-- <p class="text-center">
                <span>New on our platform?</span>
                <a href="auth-register-basic.html">
                  <span>Create an account</span>
                </a>
              </p> --}}
            </div>
          </div>
          <!-- /Register -->
        </div>
      </div>
    </div>

    <!-- / Content -->

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

    <script>
      window.addEventListener("pageshow", function (event) {
        if (event.persisted) {
            // A página veio do cache (como em history.back())
            window.location.reload();
        }
      });

    </script>
  </body>
</html>
