<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
  @include('includes.head')
</head>

<body class="d-flex flex-column min-vh-100">
  <div class="container-xxl px-2 px-md-5 flex-grow-1">
    <header class="row d-print-none" style="position: relative;">
      @include('includes.navbar')
    </header>
    <div id="app">
      <div id="content">
        @if (session('status'))
          <div class="m-3 alert alert-success" role="alert">
            {{ session('status') }}
          </div>
        @endif
        @yield('content')
      </div>
    </div>
  </div>
  @include('includes.footer')
  <!-- Scripts -->
  <script type="text/javascript" src="{{ mix('js/manifest.js') }}"></script>
  <script type="text/javascript" src="{{ mix('js/vendor.js') }}"></script>
  <script type="text/javascript" src="{{ mix('js/app.js') }}"></script>
  @env('local')
  <script>
    window.addEventListener("livewire-debug", e => console.log(JSON.stringify((e.detail))));
  </script>
  @endenv
  @stack('scripts')
</body>

</html>
