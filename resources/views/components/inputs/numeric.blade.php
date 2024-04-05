@props(['step', 'node_id', 'full_nodes', 'label', 'debug_mode'])

<div class="input-container mb-2">
  <div wire:ignore>
    @if ($full_nodes[$node_id]['description']['en'])
      @if (Str::contains($full_nodes[$node_id]['description']['en'], '[i]'))
        <div x-data="{ open: false }" wire:key="{{ 'desc' . $node_id }}">
          <div class="d-flex justify-content-between" style="align-items: flex-start;">
            <label class="form-label">
              {{ $full_nodes[$node_id]['label']['en'] }}
            </label>
            <button class="btn btn-sm btn-outline-primary" @click="open = ! open">
              <i class="bi bi-info-circle"></i>
            </button>
          </div>
          <div x-show="open">
            @markdown(Str::remove('[i]', $full_nodes[$node_id]['description']['en']))
          </div>
        </div>
      @else
        <label class="form-label">
          {{ $full_nodes[$node_id]['label']['en'] }}
        </label>
        @markdown($full_nodes[$node_id]['description']['en'])
      @endif
    @else
      <label class="form-label d-flex justify-content-between">
        {{ $full_nodes[$node_id]['label']['en'] }}
      </label>
    @endif
  </div>
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
