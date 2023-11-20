<div class="mb-2">
  <label class="form-label" for="{{ $node_id }}">
    {{ $label }}
    <br>
    {{ $description }}
  </label>
  <select wire:model.live="answer" id="{{ $node_id }}" class="form-select">
    <option selected>Select an answer</option>
    @foreach ($answers as $answer)
      <option value="{{ $answer['id'] }}">{{ $answer['label'] }}</option>
    @endforeach
  </select>
</div>
