@props(['nodes', 'nodes_to_save', 'full_nodes', 'debug_mode'])

<h2 class="fw-normal pb-3">Tests</h2>

@foreach ($nodes as $node_id => $value)
  <P>{{ $node_id }}</P>
  <div wire:key="{{ 'nodes-' . $node_id }}">
    @if (isset($full_nodes[$node_id]['display_format']))
      @switch($full_nodes[$node_id]['display_format'])
        @case('RadioButton')
          <x-inputs.radio step="tests" :node_id="$node_id" :full_nodes="$full_nodes" />
        @break

        @case('DropDownList')
          <x-inputs.select step="tests" :node_id="$node_id" :full_nodes="$full_nodes" />
        @break

        @case('Input')
          <x-inputs.numeric step="tests" :node_id="$node_id" :full_nodes="$full_nodes" :label="$nodes_to_save[$node_id]['label']":$debug_mode />
        @break

        @case('Formula')
          <x-inputs.text step="tests" :node_id="$node_id" :value="$nodes_to_save[$node_id]" :full_nodes="$full_nodes" :is_background_calc="true" />
        @break

        @case('Reference')
          <x-inputs.text step="tests" :node_id="$node_id" :value="$nodes_to_save[$node_id]" :full_nodes="$full_nodes" :is_background_calc="true" />
        @break

        @default
      @endswitch
    @endif
  </div>
@endforeach
<div class="d-flex justify-content-end">
  <button class="btn button-unisante m-1" wire:click="goToStep('diagnoses')">diagnoses</button>
</div>
