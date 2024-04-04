@props(['step', 'node_id', 'full_nodes'])
@php
  $answers = collect($full_nodes[$node_id]['answers'])
      ->filter(function ($answer) {
          return $answer['value'] !== 'not_available';
      })
      ->sortBy('reference');
@endphp

<div class="input-container mb-2">
  <label class="form-label" for="{{ $node_id }}">
    {{ $full_nodes[$node_id]['label']['en'] }}
    @if ($full_nodes[$node_id]['description']['en'])
      <div x-data="{ open: false }">
        <button class="btn btn-sm btn-outline-secondary m-1" @click="open= !open">
          <i class="bi bi-info-circle"> Description</i>
        </button>
        <div x-show="open">
          <p>{{ $full_nodes[$node_id]['description']['en'] }}</p>
        </div>
      </div>
    @endif
  </label>
  <select wire:model.live='{{ "current_nodes.$step.$node_id" }}' id="{{ $node_id }}" class="form-select">
    <option selected>Select an answer</option>

    @foreach ($answers as $answer)
      <option wire:key="{{ 'answer-' . $answer['id'] }}" value="{{ $answer['id'] }}">
        {{ $answer['label']['en'] }}
      </option>
    @endforeach
  </select>
  {{-- @error("{{ 'current_nodes.registration.' . $node_id }}")
    <div class="invalid-feedback" role="alert">{{ $message }}</div>
  @enderror --}}
</div>
