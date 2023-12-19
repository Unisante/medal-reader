@props(['step', 'node_id', 'full_nodes'])

<div class="mb-2">
  <label class="form-label" for="{{ $node_id }}">
    {{ $full_nodes[$node_id]['label']['en'] }}
    @if ($full_nodes[$node_id]['description']['en'])
      <div x-data="{ open: false }">
        <button class="btn btn-sm btn-outline-secondary m-1" @click="open = !open">
          <i class="bi bi-info-circle"> Description</i>
        </button>
        <div x-show="open">
          <p>{{ $full_nodes[$node_id]['description']['en'] }}</p>
        </div>
      </div>
    @endif
  </label>
  <input class="form-control @error('value') is-invalid @enderror" type="text"
    wire:model.live='{{ "current_nodes.$step.$node_id" }}' name="{{ $node_id }}" id="{{ $node_id }}">
  @error("{{ 'current_nodes.registration.' . $node_id }}")
    <div class="invalid-feedback" role="alert">{{ $message }}</div>
  @enderror
</div>

{{-- check if better to debounce or blur (user has to get out of input)
wire:model.blur="value" .debounce.600ms --}}
