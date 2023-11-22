<div class="mb-2">
  <label class="form-label" for="{{ $node_id }}">
    {{ $label }}
    <div x-data="{ open: false }">
      <button class="btn btn-sm btn-outline-secondary m-1" @click="open = ! open">Description</button>
      <div x-show="open">
        <p>{{ $description }}</p>
      </div>
    </div>
  </label>
  <input class="form-control @error('value') is-invalid @enderror" type="text" wire:model.live="value"
    name="{{ $node_id }}" id="{{ $node_id }}">
  @error('value')
    <div class="invalid-feedback" role="alert">{{ $message }}</div>
  @enderror
</div>

{{-- check if better to debounce or blur (user has to get out of input)
wire:model.blur="value" .debounce.600ms --}}
