@props(['step', 'node_id', 'cache_key'])

@php
  $full_nodes = Cache::get($cache_key)['full_nodes'];
@endphp

<div class="m-0" wire:key="{{ 'cc-' . $node_id }}">
  <label class="form-check-label" for="{{ $node_id }}">{{ $full_nodes[$node_id]['label']['en'] }}</label>
  <label class="custom-control teleport-switch">
    <span class="teleport-switch-control-description">No</span>
    <input type="checkbox" class="teleport-switch-control-input" name="{{ $node_id }}" id="{{ $node_id }}"
      value="{{ $node_id }}" wire:model.live="chosen_complaint_categories.{{ $node_id }}">
    <span class="teleport-switch-control-indicator"></span>
    <span class="teleport-switch-control-description">Yes</span>
  </label>
</div>
