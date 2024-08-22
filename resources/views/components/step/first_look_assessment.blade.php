@props(['nodes', 'full_nodes', 'nodes_to_save', 'debug_mode'])

<h2 class="fw-normal pb-3">First Look Assessment</h2>

{{-- Vitals --}}
{{-- todo fix that not being checked if changing step --}}
@foreach ($nodes['first_look_nodes_id'] ?? [] as $node_id => $node_value)
  <div wire:key="{{ 'first-look-' . $node_id }}">
    <x-inputs.checkbox step="first_look_assessment.first_look_nodes_id" :$node_id :$full_nodes />
  </div>
@endforeach

{{-- Complaint categories --}}
@foreach ($nodes['complaint_categories_nodes_id'] as $node_id => $node_value)
  <div wire:key="{{ 'cc-' . $node_id }}">
    <x-inputs.checkbox step="complaint_categories_nodes_id" :$node_id :$full_nodes />
  </div>
@endforeach

{{-- Basic measurement --}}
@foreach ($nodes['basic_measurements_nodes_id'] ?? [] as $node_id => $node_value)
  <div class="m-0" wire:key="{{ 'basic-measurements-' . $node_id }}">
    @switch($full_nodes[$node_id]['display_format'])
      @case('RadioButton')
        <x-inputs.radio step="first_look_assessment.basic_measurements_nodes_id" :$node_id :$full_nodes />
      @break

      @case('String')
        <x-inputs.text step="first_look_assessment.basic_measurements_nodes_id" :$node_id :$full_nodes />
      @break

      @case('DropDownList')
        <x-inputs.select step="first_look_assessment.basic_measurements_nodes_id" :$node_id :$full_nodes />
      @break

      @case('Input')
        <x-inputs.numeric step="first_look_assessment.basic_measurements_nodes_id" :$node_id :$full_nodes :label="$nodes_to_save[$node_id]['label']"
          :$debug_mode />
      @break

      @case('Formula')
        @if ($debug_mode)
          <x-inputs.text step="first_look_assessment.basic_measurements_nodes_id" :$node_id :$full_nodes
            :is_background_calc="true" />
        @endif
      @break

      @default
    @endswitch
  </div>
@endforeach
<div class="d-flex justify-content-end">
  <button class="btn button-unisante m-1"
    wire:click="goToSubStep('consultation', 'medical_history')">consultation</button>
</div>
