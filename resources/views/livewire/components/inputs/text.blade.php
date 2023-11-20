<div class="mb-2">
  <label class="form-label" for="{{ $node_id }}">
    {{ $node_id }} : {{ $label }}
    <br>
    {{ $description }}
  </label>
  <input class="form-control" type="text" disabled wire:model.live="value" name="{{ $node_id }}"
    id="{{ $node_id }}">
</div>
