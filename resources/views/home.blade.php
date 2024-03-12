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
              <option value="{{ $url }}">{{ $url }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-2">
          <button type="submit" class="btn button-unisante">Submit</button>
        </div>
      </div>
    </form>
  </div>
@endsection
