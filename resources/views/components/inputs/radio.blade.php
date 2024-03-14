@props(['step', 'node_id', 'full_nodes'])

<div class="mb-2">
  {{-- @php
    $cache_data = Cache::get($cache_key);
    $full_nodes = $cache_data['full_nodes'];
    $villages = $cache_data['villages'];
  @endphp --}}
  {{-- @dd(
$node_id,$full_nodes,$step
    ) --}}
  <label class="form-label" for="{{ $node_id }}">
    {{ $full_nodes[$node_id]['label']['en'] }}
    @if ($full_nodes[$node_id]['description']['en'])
      <div x-data="{ open: false }">
        <button class="btn btn-sm btn-outline-secondary m-1" x-on:click="open = !open">
          <i class="bi bi-info-circle"> Description</i>
        </button>
        <div x-show="open">
          <p>{{ $full_nodes[$node_id]['description']['en'] }}</p>
        </div>
      </div>
    @endif
  </label>
  @foreach ($full_nodes[$node_id]['answers'] as $answer)
    <div wire:key="{{ 'answer-' . $answer['id'] }}" class="form-check">
      <input class="form-check-input" type="radio"
        wire:model.live.number="current_nodes.{{ $step }}.{{ $node_id }}" value={{ $answer['id'] }}
        name="{{ $node_id }}" id="{{ $answer['id'] }}">
      <label class="form-check-label" for="{{ $answer['id'] }}">
        {{ $answer['label']['en'] }}
      </label>
      @if ($loop->last)
        @error('{{ "current_nodes.$step.$node_id" }}')
          <div class="invalid-feedback" role="alert">{{ $message }}</div>
        @enderror
      @endif
    </div>
  @endforeach
</div>
