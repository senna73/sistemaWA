<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }}</title>
    <link rel="icon" type="image/x-icon" href="{{asset('img/favicon.ico') }}" />
    <link rel="stylesheet" href="{{ asset('thema/assets/vendor/fonts/boxicons.css') }}" />
    <link rel="stylesheet" href="{{ asset('thema/assets/vendor/css/core.css') }}" />
    <link rel="stylesheet" href="{{ asset('thema/assets/vendor/css/theme-default.css') }}" />
    <link rel="stylesheet" href="{{ asset('thema/assets/css/demo.css') }}" />
    <link rel="stylesheet" href="{{ asset('thema/assets/vendor/css/pages/page-auth.css') }}" />
  </head>
  <body>
    <div class="container-xxl">
      <div class="authentication-wrapper authentication-basic container-p-y">
        <div class="authentication-inner">
          <div class="card">
            <div class="card-body">
              <h4 class="mb-2">Primeiro acesso</h4>
              <p class="mb-4">CPF encontrado. Informe e-mail e senha para acessar o sistema e manter seu cadastro.</p>
              @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
              @endif
              <form method="POST" action="{{ route('login.first-access.store') }}">
                @csrf
                <div class="mb-3">
                  <label class="form-label">CPF</label>
                  <input type="text" class="form-control" value="{{ $identifier }}" disabled>
                </div>
                <div class="mb-3">
                  <label class="form-label" for="email">E-mail</label>
                  <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" required>
                </div>
                <div class="mb-3">
                  <label class="form-label" for="password">Senha</label>
                  <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="mb-3">
                  <label class="form-label" for="password_confirmation">Confirmar senha</label>
                  <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
                </div>
                <button class="btn btn-primary d-grid w-100" type="submit">Criar acesso</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>
</html>
