@props(['step', 'node_id', 'full_nodes'])

@php
  $model = str_contains($step, 'first_look_assessment') ? "current_nodes.$step.$node_id" : "chosen_complaint_categories.$node_id";
@endphp

<label class="form-check-label" for="{{ $node_id }}">{{ $full_nodes[$node_id]['label']['en'] }}</label>
<label class="custom-control teleport-switch">
  <span class="teleport-switch-control-description">No</span>
  <input type="checkbox" class="teleport-switch-control-input" name="{{ $node_id }}" id="{{ $node_id }}"
    value="{{ $node_id }}" wire:model.live='{{ $model }}'>
  <span class="teleport-switch-control-indicator"></span>
  <span class="teleport-switch-control-description">Yes</span>
</label>
