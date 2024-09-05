@extends('layouts.app')

@section('content')
  <div class="p-5">
    @error('error')
      <div class="m-3 alert alert-danger" role="alert">
        {{ $message }}
      </div>
    @enderror
    <h2 class="fw-normal mb-3">
      Please enter the algorithm ID
    </h2>
    <form action="{{ route('home.store') }}" method="post" enctype="multipart/form-data">
      @csrf
      <div class="row g-3">
        <div class="col-2">
          <input autofocus class="form-control form-control @error('id') is-invalid @enderror" id="id"
            name="id" type="text" placeholder="algorithm id" aria-label="algorithm id">
          @error('id')
            <div class="invalid-feedback" role="alert">{{ $message }}</div>
          @enderror
        </div>
        <div class="col-4">
          <select id="url" name="url" class="form-select">
            @foreach ($urls as $url)
              <option {{ old('url') === $url ? 'selected' : '' }} value="{{ $url }}">{{ $url }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-2">
          <button type="submit" class="btn button-unisante">Submit</button>
        </div>
      </div>
    </form>
    <hr>
    <section>
      <h2 class="fw-normal mb-3">
        or choose an algorithm from the list below
      </h2>
      <div class="row mb-3">
        @forelse ($files as $file)
          <div class="col-6 col-lg-3 mb-2">
            <div class="card h-100 d-flex">
              <div class="card-body">
                <h5 class="card-title">{{ $file['name'] }}</h5>
                <h6 class="card-subtitle mb-2 text-body-secondary">
                  {{ $file['project_name'] }}
                </h6>
                <p class="card-text">
                  Last updated {{ $file['updated_at'] }}
                </p>
              </div>
              <div class="d-flex justify-content-end align-content-end mb-2 me-2">
                @if ($file['type'] === 'dynamic')
                  <button class="btn button-unisante" type="button" data-bs-toggle="modal" data-bs-target="#start"
                    data-bs-algorithm_id="{{ $file['id'] }}">
                    Start
                  </button>
                @else
                  <a href="{{ route('home.process', ['id' => $file['id']]) }}" class="btn button-unisante">
                    Start
                  </a>
                @endif

              </div>
            </div>
          </div>
        @empty
          <p>No jsons found</p>
        @endforelse
      </div>
    </section>
  </div>

  {{-- New consultation modal --}}
  <x-modals.new-consultation />

  {{-- Patients table modal --}}
  <livewire:components.tables.patients />
@endsection

@push('scripts')
  <script>
    var start = document.getElementById('start')
    start.addEventListener('show.bs.modal', function(event) {
      var button = event.relatedTarget
      var algorithm_id = button.getAttribute('data-bs-algorithm_id')
      var url = "{{ route('home.process', '') }}" + "/" + algorithm_id;
      document.getElementById("start_consultation").href = url;
    })
  </script>
@endpush
