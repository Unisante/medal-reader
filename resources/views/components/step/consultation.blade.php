@props(['substep', 'nodes', 'cache_key', 'medical_case', 'full_nodes', 'villages', 'debug_mode'])

<h2 class="fw-normal pb-3">Consultation</h2>

@foreach ($nodes as $title => $system)
  {{-- System container --}}
  <div wire:key="{{ 'system-' . $substep . '-' . $title }}">
    @if (count($system))
      <h4>{{ trans("systems.$title") }}</h4>
      @foreach ($system as $node_id => $answer_id)
        <div wire:key="{{ 'nodes-' . $node_id }}">
          @if (isset($full_nodes[$node_id]['display_format']))
            @switch($full_nodes[$node_id]['display_format'])
              @case('RadioButton')
                <x-inputs.radio step='{{ "consultation.$substep.$title" }}' :$node_id :$full_nodes />
              @break

              @case('String')
                <x-inputs.text step='{{ "consultation.$substep.$title" }}' :$node_id :$full_nodes :is_background_calc="false" />
              @break

              @case('DropDownList')
                <x-inputs.select step='{{ "consultation.$substep.$title" }}' :$node_id :$full_nodes />
              @break

              @case('Input')
                <x-inputs.numeric step='{{ "consultation.$substep.$title" }}' :$node_id :$full_nodes :label="$medical_case['nodes'][$node_id]['label']"
                  :$debug_mode />
              @break

              @case('Formula')
                @if ($debug_mode)
                  <x-inputs.text step='{{ "consultation.$substep.$title" }}' :$node_id :value="$medical_case['nodes'][$node_id]" :$full_nodes
                    :is_background_calc="true" />
                @endif
              @break

              @case('Reference')
                @if ($debug_mode)
                  <x-inputs.text step='{{ "consultation.$substep.$title" }}' :$node_id :value="$medical_case['nodes'][$node_id]" :$full_nodes
                    :is_background_calc="true" />
                @endif
              @break

              @default
            @endswitch
          @endif
        </div>
      @endforeach
    @endif
  </div>
@endforeach
