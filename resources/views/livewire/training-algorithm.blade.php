@php
  $cache = Cache::get($cache_key);
  $full_nodes = $cache['full_nodes'];
  $final_diagnoses = $cache['final_diagnoses'];
  $health_cares = $cache['health_cares'];
@endphp

<div class="mb-5">
  <div>
    <h2 class="fw-normal">{{ $title }}</h2>
  </div>

  <x-navigation.training-navstep :current_step="$current_step" :saved_step="$saved_step" :completion_per_step="$completion_per_step" />

  <div class="row g-3 mt-3">
    <div class="col-8">

      {{-- Consultation --}}
      @if ($current_step === 'consultation')
        <x-step.questionnaire :nodes="$current_nodes['consultation']['medical_history']" :nodes_to_save="$nodes_to_save" :current_cc="$current_cc" :cache_key="$cache_key" />
      @endif

      {{-- Tests --}}
      @if ($current_step === 'tests')
      @endif

      {{-- Diagnoses --}}
      @if ($current_step === 'diagnoses')
        <x-step.training-results :df_to_display="$df_to_display" :final_diagnoses="$final_diagnoses" :cache_key="$cache_key" />
      @endif
    </div>
  </div>

  <!-- Modal -->
  <div class="modal fade" id="emergencyModal" tabindex="-1" aria-labelledby="emergencyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header border-0 d-flex justify-content-between">
          <div></div>
          <div>
            <h5 class="modal-title text-danger" id="emergencyModalLabel">EMERGENCY ASSISTANCE</h5>
          </div>
          <div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
        </div>
        <div class="modal-body border-0 mx-auto">
          The patient is presenting a severe/emergency symptom or sign. Click on the emergency button if the child needs
          emergency care now.
        </div>
        <div class="modal-footer border-0 mx-auto">
          <button type="button" class="btn btn-danger">GO TO EMERGENCY</button>
        </div>
      </div>
    </div>
  </div>

  @push('scripts')
    <script type="text/javascript">
      document.addEventListener('livewire:init', () => {
        const emergencyModal = document.getElementById('emergencyModal');

        Livewire.on('openEmergencyModal', () => {
          var bootstrapEmergencyModal = new bootstrap.Modal(emergencyModal)
          bootstrapEmergencyModal.show()
        });

        Livewire.on("scrollTop", () => {
          window.scrollTo(0, 0);
        });
      });
    </script>
  @endpush
