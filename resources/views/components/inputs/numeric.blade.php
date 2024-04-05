@props(['step', 'node_id', 'full_nodes', 'label', 'debug_mode'])

<div class="input-container mb-2">
  <label class="form-label" for="{{ $node_id }}">
    {{ $full_nodes[$node_id]['label']['en'] }}
    @if ($full_nodes[$node_id]['description']['en'])
      @markdown($full_nodes[$node_id]['description']['en'])
    @endif
  </label>
  <input class="form-control @error('value') is-invalid @enderror" type="text"
    wire:model.live.debounce.300ms.number='{{ "current_nodes.$step.$node_id" }}' name="{{ $node_id }}"
    id="{{ $node_id }}">
  {{-- @error("{{ 'current_nodes.registration.' . $node_id }}")
    <div class="invalid-feedback" role="alert">{{ $message }}</div>
  @enderror --}}
  @if ($debug_mode)
    <p style="font-size: smaller;" class="fst-italic">{{ $label }}</p>
  @endif
</div>

{{-- check if better to debounce or blur (user has to get out of input)
wire:model.blur="value" .debounce.600ms --}}
