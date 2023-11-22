<div class="mb-2">
  <label class="form-label" for="{{ $node_id }}">
    {{ $label }}
    <div x-data="{ open: false }">
      <button class="btn btn-sm btn-outline-secondary m-1" @click="open = ! open">Description</button>
      <div x-show="open">
        <p>{{ $description }}</p>
      </div>
    </div>
  </label>
  @foreach ($answers as $answer)
    <div class="form-check">
      <input class="form-check-input" type="radio" wire:model.live="answer" value="{{ $answer['id'] }}"
        name="{{ $node_id }}" id="{{ $answer['id'] }}" wire:key="{{ $answer['id'] }}">
      <label class="form-check-label" for="{{ $answer['id'] }}">{{ $answer['label'] }}</label>
    </div>
  @endforeach
</div>
