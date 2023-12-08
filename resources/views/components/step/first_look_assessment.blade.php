@props(['nodes', 'cache_key'])

@php
  $full_nodes = Cache::get($cache_key)['full_nodes'];
@endphp

@dump($nodes)
@dump($nodes['first_look_nodes_id'])
@dump($nodes['basic_measurements_nodes_id'])

{{-- Vitals --}}
{{-- todo fix that not being checked if changing step --}}
@foreach ($nodes['first_look_nodes_id'] ?? [] as $node_id => $node_value)
  <div class="m-0" wire:key="{{ 'first-look-' . $node_id }}">
    <label class="form-check-label" for="{{ $node_id }}">{{ $full_nodes[$node_id]['label']['en'] }}</label>
    <label class="custom-control teleport-switch">
      <span class="teleport-switch-control-description">No</span>
      <input type="checkbox" class="teleport-switch-control-input" name="{{ $node_id }}" id="{{ $node_id }}"
        value="{{ $node_id }}"
        wire:model.live='{{ "current_nodes.first_look_assessment.first_look_nodes_id.$node_id" }}'>
      <span class="teleport-switch-control-indicator"></span>
      <span class="teleport-switch-control-description">Yes</span>
    </label>
  </div>
@endforeach

{{-- Complaint categories --}}
@foreach ($nodes['complaint_categories_nodes_id'] as $node_id => $node_value)
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
@endforeach

{{-- Basic measurement --}}
@foreach ($nodes['basic_measurements_nodes_id'] ?? [] as $node_id => $node_value)
  <div class="m-0" wire:key="{{ 'cc-' . $node_id }}">
    @switch($full_nodes[$node_id]['display_format'])
      @case('RadioButton')
        <div>
          <x-inputs.radio step="first_look_assessment.basic_measurements_nodes_id" :node_id="$node_id" />
        </div>
      @break

      @case('String')
        <div>
          <x-inputs.text step="first_look_assessment.basic_measurements_nodes_id" :node_id="$node_id" />
        </div>
      @break

      @case('DropDownList')
        <div>
          <x-inputs.select step="first_look_assessment.basic_measurements_nodes_id" :node_id="$node_id" />
        </div>
      @break

      @case('Input')
        <div>
          <x-inputs.numeric step="first_look_assessment.basic_measurements_nodes_id" :node_id="$node_id" :cache_key="$cache_key" />
        </div>
      @break

      @case('Formula')
        <div>
          <x-inputs.text step="first_look_assessment.basic_measurements_nodes_id" :value="$nodes_to_save[$node_id]" :node_id="$node_id" />
        </div>
      @break

      @default
    @endswitch
  </div>
@endforeach
<button class="btn btn-sm btn-outline-primary m-1" wire:click="goToStep('consultation')">consultation</button>
