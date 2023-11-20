<div class="mb-2">
  <label class="form-label" for="{{ $node_id }}">{{ $label }} {{ $answer }}</label>
  <input class="form-control" type="text" disabled wire:model.live="value" name="{{ $node_id }}"
    id="{{ $node_id }}">
</div>
