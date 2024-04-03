@props(['nodes', 'cache_key', 'current_cc', 'nodes_to_save', 'debug_mode'])

@php
  $cache = Cache::get($cache_key);
  $full_nodes = $cache['full_nodes'];
@endphp

@foreach ($nodes as $cc_id => $cc)
  <div wire:key="{{ 'chosen-cc-' . $cc_id }}">

    @if ($current_cc == $cc_id)
      @if (array_key_exists($cc_id, $full_nodes))
        <h2 class="fw-normal pb-3">{{ $full_nodes[$cc_id]['label']['en'] }}</h2>
      @else
        <h2 class="fw-normal pb-3">Questionnaire</h2>
      @endif
      @foreach ($cc as $node_id => $answer_id)
        <div wire:key="{{ 'nodes-' . $node_id }}">
          @if (isset($full_nodes[$node_id]['display_format']))
            @switch($full_nodes[$node_id]['display_format'])
              @case('RadioButton')
                <x-inputs.radio step="consultation.{{ $cc_id }}" :$node_id :$full_nodes :$cache_key />
              @break

              @case('String')
                <x-inputs.text step="consultation.{{ $cc_id }}" :$node_id :$full_nodes :$cache_key
                  :is_background_calc="false" />
              @break

              @case('DropDownList')
                <x-inputs.select step="consultation.{{ $cc_id }}" :$node_id :$full_nodes :$cache_key />
              @break

              @case('Input')
                <x-inputs.numeric step="consultation.{{ $cc_id }}" :$node_id :$full_nodes :$cache_key
                  :label="$nodes_to_save[$node_id]['label']" :$debug_mode />
              @break

              @case('Formula')
                @if ($debug_mode)
                  <x-inputs.text step="consultation.{{ $cc_id }}" :value="$nodes_to_save[$node_id]" :$node_id :$full_nodes :$cache_key
                    :is_background_calc="true" />
                @endif
              @break

              @case('Reference')
                @if ($debug_mode)
                  <x-inputs.text step="consultation.{{ $cc_id }}" :$node_id :value="$nodes_to_save[$node_id]" :$full_nodes :$cache_key
                    :is_background_calc="true" />
                @endif
              @break

              @default
            @endswitch
          @endif

        </div>
      @endforeach
      <div class="d-flex justify-content-end">
        @if (!$loop->first)
          <button class="btn button-unisante m-1" wire:click="goToPreviousCc()">Précédant</button>
        @endif
        @if (!$loop->last)
          <button class="btn button-unisante m-1" wire:click="goToNextCc()">Suivant</button>
        @endif
        @if ($loop->last)
          <button class="btn button-unisante m-1" wire:click="goToStep('diagnoses')">Results</button>
        @endif
      </div>
    @endif
  </div>
@endforeach
