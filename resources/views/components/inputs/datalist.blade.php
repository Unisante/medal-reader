<div>
  <label class="form-label" for="{{ $node['id'] }}">
    {{ $node['label'] }}
  </label>
  <input class="form-control" list="village_list" type="text" name="{{ $node['id'] }}" id="{{ $node['id'] }}">
  <datalist id="village_list">
    {{-- @foreach ($villages as $key => $village)
      <option data-value="{{ $key }}" value="{{ $village }}" />
    @endforeach --}}
  </datalist>
</div>
