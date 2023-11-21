<div class="mb-3">
  @foreach ($nodes[$age_key] as $node)
    <div class="m-0" wire:key="{{ 'cc-' . $node['id'] }}">
      <label class="form-check-label" for="{{ $node['id'] }}">{{ $node['label'] }}</label>
      <label class="custom-control teleport-switch">
        <span class="teleport-switch-control-description">No</span>
        <input type="checkbox" class="teleport-switch-control-input" name="{{ $node['id'] }}" id="{{ $node['id'] }}"
          value="{{ $node['id'] }}" wire:model.live="chosen_complaint_categories">
        <span class="teleport-switch-control-indicator"></span>
        <span class="teleport-switch-control-description">Yes</span>
      </label>
    </div>
  @endforeach
</div>
