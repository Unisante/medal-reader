@extends('layouts.app')

@section('content')
  @error('error')
    <div class="m-3 alert alert-danger" role="alert">
      {{ $message }}
    </div>
  @enderror
  <h1>
    Please enter the algorithm ID
  </h1>
  <form action="{{ route('home.store') }}" method="post" enctype="multipart/form-data">
    @csrf
    <div class="row g-3">
      <div class="col-6 col-md-4">
        <input autofocus class="form-control form-control-lg @error('id') is-invalid @enderror" id="id" name="id"
          type="text" placeholder="version id" aria-label="version id">
        @error('id')
          <div class="invalid-feedback" role="alert">{{ $message }}</div>
        @enderror
        <div class="d-flex justify-content-end align-items-end">
          <button type="submit" class="mt-3 btn btn-outline-primary">Submit</button>
        </div>
      </div>
    </div>
  </form>
  <hr>
  <section>
    <h1>
      Existing
    </h1>
    @forelse ($files as $file)
      @if ($loop->index % 5 === 0)
        </div>
        <div class="d-flex flex-row mb-3">
      @endif
      <div class="card m-1">
        <div class="card-body">
          <h5 class="card-title">{{ Storage::json($file)['name'] }}</h5>
          <h6 class="card-subtitle mb-2 text-body-secondary">
            {{ Storage::json($file)['medal_r_json']['algorithm_name'] }}
          </h6>
          <p class="card-text">
            Last updated {{ date_create(Storage::json($file)['updated_at'])->format('d/m/Y h:i:s') }}
          </p>
          <div class="d-flex justify-content-end">
            <a href="{{ route('home.process', Storage::json($file)['id']) }}" class="btn btn-primary">
              Start
            </a>
          </div>
        </div>
      </div>
    @empty
      <p>No jsons found</p>
    @endforelse
    </div>
  </section>
@endsection
