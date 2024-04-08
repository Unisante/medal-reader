<footer class="container-xxl px-0 px-md-5 text-lg-start mt-3 d-print-none">
  <section>
    <div class="text-md-start">
      <div class="row">
        <div class="col-12 col-xl-9 border-end">
          <div class="row">
            <div class="col-4">
              <h6 class="text-uppercase fw-bold mb-4">
                {{ trans('footer.unisante') }}
              </h6>
              <p class="text-muted">{!! trans('footer.unisante_addr') !!}</p>
            </div>
            <div class="col-4">
              <h6 class="text-uppercase fw-bold mb-4">
                {{ trans('footer.swisstph') }}
              </h6>
              <p class="text-muted">{!! trans('footer.swisstph_addr') !!}</p>
            </div>
            <div class="col-4">
              <h6 class="text-uppercase fw-bold mb-4">{{ trans('footer.links') }}</h6>
              <p>
                <a class="text-reset" href="https://unisante.ch">{{ trans('footer.about') }}</a>
              </p>
              <p>
                <a class="text-reset" href="https://www.swisstph.ch/">{{ trans('footer.about2') }}</a>
              </p>
            </div>
          </div>
        </div>
        <div class="col-12 col-xl-3">
          <div class="text-end d-flex justify-content-end">
            <p style="font-size: 0.7rem !important; margin-bottom:7px;">
              {{ trans('footer.dev_by') }}
            </p>
          </div>
          <div class="d-flex justify-content-between">
            <a class="navbar-brand" href="{{ url('https://www.unisante.ch/') }}">
              <img src="{{ mix('images/logo-unisante-footer.svg') }}" alt="logo_unisante" width="140">
            </a>
            <a class="navbar-brand ps-3 align-self-center" href="{{ url('https://www.swisstph.ch/') }}">
              <img src="{{ mix('images/swisstph-logo.svg') }}" alt="swisstph-logo" width="160">
            </a>
          </div>
        </div>
      </div>
    </div>
    <div class="d-flex justify-content-end">
      @php($metadata = json_decode(file_get_contents(base_path('package.json'))))
      <p style="font-size: 0.7rem !important; margin-bottom:7px;">
        Laravel v{{ Illuminate\Foundation\Application::VERSION }} (PHP v{{ PHP_VERSION }})
      </p>
      <p style="font-size: 0.7rem !important; margin-bottom:7px;">V.{{ $metadata->version }}&nbsp;</p>
      <p style="font-size: 0.7rem !important; margin-bottom:7px;">Unisanté © {{ date('Y') }},
        {{ trans('footer.copyright') }}
      </p>
    </div>
  </section>
</footer>
