<div>
  Chosen : {{ var_export($chosen) }}
  <div class="form-check">
    <label class="form-check-label" for="{{ $node['id'] }}">{{ $node->label->en }}</label>
    <label class="custom-control teleport-switch">
      <span class="teleport-switch-control-description">No</span>
      <input type="checkbox" class="teleport-switch-control-input" wire:key="{{ $node['id'] }}" name="{{ $node['id'] }}"
        id="{{ $node['id'] }}" wire:model="chosen">
      <span class="teleport-switch-control-indicator"></span>
      <span class="teleport-switch-control-description">Yes</span>
    </label>
  </div>
</div>
