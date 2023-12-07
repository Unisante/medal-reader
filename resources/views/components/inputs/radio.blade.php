@props(['step', 'node_id', 'cache_key'])

<div class="mb-2">
  @php
    $cache_data = Cache::get($cache_key);
    $full_nodes = $cache_data['full_nodes'];
    $villages = $cache_data['villages'];
  @endphp
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
  @foreach ($full_nodes[$node_id]['answers'] as $answer)
    <div class="form-check">
      <input class="form-check-input" type="radio" wire:model.live='{{ "current_nodes.$step.$node_id" }}'
        value={{ intval($answer['id']) }} name="{{ $node_id }}" id="{{ $answer['id'] }}"
        wire:key="{{ $answer['id'] }}">
      <label class="form-check-label" for="{{ $answer['id'] }}">
        {{ $answer['label']['en'] }}
      </label>
      @if ($loop->last)
        @error("{{ 'current_nodes.registration.' . $node_id }}")
          <div class="invalid-feedback" role="alert">{{ $message }}</div>
        @enderror
      @endif
    </div>
  @endforeach
</div>
