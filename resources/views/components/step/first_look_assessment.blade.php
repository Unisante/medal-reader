@props(['nodes', 'cache_key'])

@php
  $full_nodes = Cache::get($cache_key)['full_nodes'];
@endphp

@dump($nodes['first_look_nodes_id'])
@dump($nodes['basic_measurements_nodes_id'])

{{-- Vitals --}}
{{-- todo fix that not being checked if changing step --}}
@foreach ($nodes['first_look_nodes_id'] ?? [] as $node_id => $node_value)
  <div wire:key="{{ 'first-look-' . $node_id }}">
    <x-inputs.checkbox step="first_look_assessment.first_look_nodes_id" :node_id="$node_id" :cache_key="$cache_key" />
  </div>
@endforeach

{{-- Complaint categories --}}
@foreach ($nodes['complaint_categories_nodes_id'] as $node_id => $node_value)
  <div wire:key="{{ 'cc-' . $node_id }}">
    <x-inputs.checkbox step="complaint_categories_nodes_id" :node_id="$node_id" :cache_key="$cache_key" />
  </div>
@endforeach

{{-- Basic measurement --}}
@foreach ($nodes['basic_measurements_nodes_id'] ?? [] as $node_id => $node_value)
  <div class="m-0" wire:key="{{ 'basic-measurements-' . $node_id }}">
    @switch($full_nodes[$node_id]['display_format'])
      @case('RadioButton')
        <x-inputs.radio step="first_look_assessment.basic_measurements_nodes_id" :node_id="$node_id" :cache_key="$cache_key" />
      @break

      @case('String')
        <x-inputs.text step="first_look_assessment.basic_measurements_nodes_id" :node_id="$node_id" :cache_key="$cache_key" />
      @break

      @case('DropDownList')
        <x-inputs.select step="first_look_assessment.basic_measurements_nodes_id" :node_id="$node_id" :cache_key="$cache_key" />
      @break

      @case('Input')
        <x-inputs.numeric step="first_look_assessment.basic_measurements_nodes_id" :node_id="$node_id" :cache_key="$cache_key" />
      @break

      @case('Formula')
        <x-inputs.text step="first_look_assessment.basic_measurements_nodes_id" :value="$nodes_to_save[$node_id]" :node_id="$node_id" />
      @break

      @default
    @endswitch
  </div>
@endforeach
<button class="btn btn-sm btn-outline-primary m-1" wire:click="goToStep('consultation')">consultation</button>
