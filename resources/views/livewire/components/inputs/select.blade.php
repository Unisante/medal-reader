<div class="mb-2">
  <label class="form-label" for="{{ $node_id }}">
    {{ $label }}
    <div x-data="{ open: false }">
      <button @click="open = ! open">Description</button>
      <div x-show="open">
        <p>{{ $description }}</p>
      </div>
    </div>
  </label>
  <select wire:model.live="answer" id="{{ $node_id }}" class="form-select">
    <option selected>Select an answer</option>
    @foreach ($answers as $answer)
      <option value="{{ $answer['id'] }}">{{ $answer['label'] }}</option>
    @endforeach
  </select>
</div>
