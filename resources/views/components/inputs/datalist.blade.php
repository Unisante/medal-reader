<div>
  @props(['step','label', 'node_id',  'villages'])
  <label class="form-label" for="{{ $node_id }}">
    {{ $label }}
  </label>
  <input wire:model.live='{{ "current_nodes.$step.$node_id" }}' class="form-control" list="village_list" type="text"
    name="{{ $node_id }}" id="{{ $node_id }}">
  <datalist id="village_list">
    @foreach ($villages as $key => $village)
      <option data-value="{{ $key }}" value="{{ $village }}" />
    @endforeach
  </datalist>
</div>
