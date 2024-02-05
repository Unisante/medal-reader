@php
  $cache = Cache::get($cache_key);
  $full_nodes = $cache['full_nodes'];
  $final_diagnoses = $cache['final_diagnoses'];
  $health_cares = $cache['health_cares'];
@endphp

<div class="mb-5">
  <div class="d-flex justify-content-between">
    <div>
      <h2 class="fw-normal">{{ $title }}</h2>
    </div>
    <div class="form-check">
      <input class="form-check-input" type="checkbox" wire:model.live="debug_mode" value="" id="enableBcs" checked>
      <label class="form-check-label" for="enableBcs">
        Enable debug mode
      </label>
    </div>
  </div>

  <x-navigation.training-navsteps :$current_step :$saved_step :$completion_per_step />

  <div class="row g-3 mt-3">
    <div class="col-8">

      {{-- Consultation --}}
      @if ($current_step === 'consultation')
        <x-step.questionnaire :nodes="$current_nodes['consultation']['medical_history']" :$nodes_to_save :$current_cc :$cache_key :$debug_mode />
      @endif

      {{-- Diagnoses --}}
      @if ($current_step === 'diagnoses')
        <x-step.training-results :$df_to_display :$final_diagnoses :$cache_key />
      @endif
    </div>
  </div>

  {{-- Should we have emergency for training ? --}}
  {{-- <x-modals.emergency /> --}}

  @push('scripts')
    <script type="text/javascript">
      document.addEventListener('livewire:init', () => {
        Livewire.on("scrollTop", () => {
          window.scrollTo(0, 0);
        });
      });
    </script>
  @endpush
