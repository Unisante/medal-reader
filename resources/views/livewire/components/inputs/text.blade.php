<div class="mb-2">
  @php $full_nodes=Cache::get($cache_key)["full_nodes"] @endphp
  <label class="form-label" for="{{ $node_id }}">
    @if ($is_background_calc)
      {{ $node_id }}:
    @endif {{ $full_nodes[$node_id]['label']['en'] }}
    @if ($full_nodes[$node_id]['description']['en'])
      <div x-data="{ open: false }">
        <button class="btn btn-sm btn-outline-secondary m-1" @click="open = ! open">
          <i class="bi bi-info-circle"> Description</i>
        </button>
        <div x-show="open">
          <p>{{ $full_nodes[$node_id]['description']['en'] }}</p>
        </div>
      </div>
    @endif
  </label>
  <input class="form-control" type="text" @if ($is_background_calc) disabled @endif wire:model.live="value"
    name="{{ $node_id }}" id="{{ $node_id }}">
</div>
