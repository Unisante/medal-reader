<div class="mb-2">
  <label class="form-label" for="{{ $node_id }}">
    {{ $node_id }} : {{ Cache::get($cache_key)['full_nodes'][$node_id]['label']['en'] }}
    @if (Cache::get($cache_key)['full_nodes'][$node_id]['description']['en'])
      <div x-data="{ open: false }">
        <button class="btn btn-sm btn-outline-secondary m-1" @click="open = ! open">Description</button>
        <div x-show="open">
          <p>{{ Cache::get($cache_key)['full_nodes'][$node_id]['description']['en'] }}</p>
        </div>
      </div>
    @endif
  </label>
  <input class="form-control" type="text" disabled wire:model.live="value" name="{{ $node_id }}"
    id="{{ $node_id }}">
</div>
