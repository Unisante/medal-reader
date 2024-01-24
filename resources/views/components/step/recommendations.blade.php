@props(['nodes', 'cache_key', 'current_cc', 'nodes_to_save'])

@php
  $cache = Cache::get($cache_key);
  $full_nodes = $cache['full_nodes'];
@endphp
@foreach ($nodes as $cc_id => $cc)
  <div wire:key="{{ 'chosen-cc-' . $cc_id }}">
    @if ($current_cc == $cc_id)
      @foreach ($cc as $node_id => $answer_id)
        <div wire:key="{{ 'nodes-' . $node_id }}">
          @switch($full_nodes[$node_id]['display_format'])
            @case('RadioButton')
              <x-inputs.radio step="consultation.medical_history.{{ $cc_id }}" :node_id="$node_id" :full_nodes="$full_nodes"
                :cache_key="$cache_key" />
            @break

            @case('String')
              <x-inputs.text step="consultation.medical_history.{{ $cc_id }}" :node_id="$node_id" :full_nodes="$full_nodes"
                :cache_key="$cache_key" :is_background_calc="false" />
            @break

            @case('DropDownList')
              <x-inputs.select step="consultation.medical_history.{{ $cc_id }}" :node_id="$node_id" :full_nodes="$full_nodes"
                :cache_key="$cache_key" />
            @break

            @case('Input')
              <x-inputs.numeric step="consultation.medical_history.{{ $cc_id }}" :node_id="$node_id" :full_nodes="$full_nodes"
                :cache_key="$cache_key" />
            @break

            @case('Formula')
              <x-inputs.text step="consultation.medical_history.{{ $cc_id }}" :value="$nodes_to_save[$node_id]" :node_id="$node_id"
                :full_nodes="$full_nodes" :cache_key="$cache_key" :is_background_calc="true" />
            @break

            @case('Reference')
              <x-inputs.text step="consultation.medical_history.{{ $cc_id }}" :node_id="$node_id" :value="$nodes_to_save[$node_id]"
                :full_nodes="$full_nodes" :cache_key="$cache_key" :is_background_calc="true" />
            @break

            @default
          @endswitch

        </div>
      @endforeach
      @if (!$loop->first)
        <button class="btn btn-sm btn-outline-primary m-1" wire:click="goToPreviousCc()">Previous CC</button>
      @endif
      @if (!$loop->last)
        <button class="btn btn-sm btn-outline-primary m-1" wire:click="goToNextCc()">Next CC</button>
      @endif
      @if ($loop->last)
        <button class="btn btn-sm btn-outline-primary m-1" wire:click="goToStep('diagnoses')">diagnoses</button>
      @endif
    @endif
  </div>
@endforeach
