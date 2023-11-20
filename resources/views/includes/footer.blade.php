<footer class="container-xxl px-0 px-md-5 text-lg-start mt-3 d-print-none">
  <section>
    <div class="text-md-start">
      <div class="row">
        <div class="col-12 col-xl-8 border-end">
          <div class="row">
            <div class="col-4">
              <h6 class="text-uppercase fw-bold mb-4">
                {{ trans('footer.unisante') }}
              </h6>
              <p class="text-muted">{!! trans('footer.unisante_addr') !!}</p>
            </div>
            <div class="col-4">
              <h6 class="text-uppercase fw-bold mb-4">
                {{ trans('footer.dmf') }}
              </h6>
              <p class="text-muted">{!! trans('footer.dmf_addr') !!}</p>
            </div>
            <div class="col-4">
              <h6 class="text-uppercase fw-bold mb-4">{{ trans('footer.links') }}</h6>
              <p>
                <a class="text-reset" href="https://unisante.ch">{{ trans('footer.about') }}
                </a>
              </p>
              <p>
                {{-- <a class="text-reset" target="_blank"
                  href="{{ route('home.doc', 'CGU et Privacy Policy_Site Emprunte Carbone Cabinets Médicaux_Archipel comments_13.10.2022_ym_vf.pdf') }}">
                  {{ trans('footer.cgr') }}
                </a> --}}
              </p>
              <p>
                {{-- <a class="text-reset"
                  href="{{ route('home.doc', 'Liste des documents et informations à préparer pour évaluation.docx') }}">
                  {{ trans('footer.for_prep') }}
                </a> --}}
              </p>
            </div>
          </div>
        </div>
        <div class="col-12 col-xl-4">
          <div class="text-end d-flex justify-content-end">
            <p style="font-size: 0.7rem !important; margin-bottom:7px;">
              {{ trans('footer.dev_by') }}
              <br />
              {{ trans('footer.vd') }}
            </p>
          </div>
          <div class="d-flex justify-content-between">
            <a class="navbar-brand ps-3 align-self-center" href="{{ url('https://www.vd.ch/') }}">
              {{-- <img src="{{ mix('images/vd.png') }}" alt="logo_vd" width="160"> --}}
            </a>
            <a class="navbar-brand align-self-center" href="{{ url('https://www.e-a.earth/') }}">
              {{-- <img src="{{ mix('images/ea.png') }}" alt="logo_ea" width="70"> --}}
            </a>
            <a class="navbar-brand" href="{{ url('https://www.unisante.ch/') }}">
              {{-- <img src="{{ mix('images/logo-unisante-footer.svg') }}" alt="logo_unisante" width="140"> --}}
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
        {{ trans('mail.copyright') }}
      </p>
    </div>
  </section>
</footer>
