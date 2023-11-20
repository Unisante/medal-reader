<nav class="navbar navbar-expand-xl navbar-light bg-white">
  <div class="container-xxl">
    <a class="navbar-brand" href="{{ url('/') }}">
      <img src="{{ mix('images/logo.png') }}" alt="logo" width="120">
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"
      aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarSupportedContent">
      <ul class="navbar-nav me-auto ms-xl-4 mb-2 mb-lg-0 ms-0">
        {{-- <li class="nav-item">
          <a class="nav-link {{ Request::route()->getName() === 'home.presentation' ? 'active' : '' }}"
            href="{{ route('home.presentation') }}">{{ mb_strtoupper(trans('navbar.presentation')) }}</a>
        </li>
        <li class="nav-item">
          <a class="nav-link {{ Request::route()->getName() === 'home.about' ? 'active' : '' }}"
            href="{{ route('home.about') }}">{{ mb_strtoupper(trans('navbar.about')) }}</a>
        </li>
        <li class="nav-item">
          <a class="nav-link {{ Request::route()->getName() === 'home.methodology' ? 'active' : '' }}"
            href="{{ route('home.methodology') }}">{{ mb_strtoupper(trans('navbar.methodology')) }}</a>
        </li>
        <li class="nav-item">
          <a class="nav-link {{ Request::route()->getName() === 'home.links' ? 'active' : '' }}"
            href="{{ route('home.links') }}">{{ mb_strtoupper(trans('navbar.links')) }}</a>
        </li>
        <li class="nav-item">
          <a class="nav-link {{ Request::route()->getName() === 'home.contact' ? 'active' : '' }}"
            href="{{ route('home.contact') }}">{{ mb_strtoupper(trans('navbar.contact')) }}</a>
        </li> --}}
        {{-- Do not display faq before it's useful --}}
        {{-- <li class="nav-item">
            <a class="nav-link" href="{{ route('faq.index') }}">{{ mb_strtoupper(trans('navbar.faq')) }}</a>
          </li> --}}
      </ul>

      <ul class="navbar-nav ms-auto me-3" style="padding-right: 140px">
        <div class="d-flex align-items-center">
          <i class="icon icon-doctor icon-bg" style="background-color:#f9fafc;font-size: 30px;"></i>
        </div>
        @guest
          @if (Route::has('login'))
            <li class="nav-item border-end">
              <a class="nav-link link-success" href="{{ route('login') }}">{{ mb_strtoupper(trans('navbar.login')) }}</a>
            </li>
          @endif
        @else
          <li class="nav-item dropdown border-end">
            <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button"
              data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
              {{ Auth::user()->email }}
            </a>

            <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
              <li>
                <div>
                  <a class="dropdown-item" href="{{ route('cabinets.index') }}">
                    {{ trans('navbar.cabinet') }}
                  </a>
                </div>
              </li>
              <li>
                <div>
                  <a class="dropdown-item"
                    onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    {{ __('Logout') }}
                  </a>
                  <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                    @csrf
                  </form>
                </div>
              </li>
            </ul>
          </li>
        @endguest
        <li class="nav-item">
          <a class="nav-link" href="#">{{ trans('lang.' . App::getLocale()) }}</a>
        </li>
      </ul>
      <img class="logo_unisante" alt="logo_unisante" width="150">
    </div>
  </div>
</nav>
