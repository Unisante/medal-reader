@extends('layouts.app')
@section('content')
  <div class="p-5">
    <livewire:algorithm :$id :$patient_id :$data />
  </div>
@endsection
