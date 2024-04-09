@props(['df_to_display', 'final_diagnoses', 'cache_key'])

{{-- In theory the view shouldn't do any logic BUT that would actually reduce the cache usage --}}
@php
  $high_dfs = collect($df_to_display)->filter(function ($df, $k) use ($final_diagnoses) {
      return $final_diagnoses[$k]['level_of_urgency'] === 10;
  });
  $moderate_dfs = collect($df_to_display)->filter(function ($df, $k) use ($final_diagnoses) {
      return $final_diagnoses[$k]['level_of_urgency'] === 9;
  });
  $light_dfs = collect($df_to_display)->filter(function ($df, $k) use ($final_diagnoses) {
      return $final_diagnoses[$k]['level_of_urgency'] === 8;
  });
  $other_dfs = collect($df_to_display)->filter(function ($df, $k) use ($final_diagnoses) {
      return $final_diagnoses[$k]['level_of_urgency'] < 8;
  });
@endphp

<div>
  <h2 class="mb-5">RECOMMANDATIONS EVIPREV 2024</h2>
  @if (empty($df_to_display))
    <p>No results</p>
  @else
    @if (count($high_dfs))
      <div class="input-container mb-5">
        <h2 class="fw-normal mb-3">Highly recommended</h2>
        @foreach ($high_dfs as $diagnosis_id => $drugs)
          <div wire:key="{{ 'df-' . $diagnosis_id }}">
            <div class="input-container mb-3">
              <label class="form-check-label" for="{{ $diagnosis_id }}">
                @if (isset($final_diagnoses[$diagnosis_id]['description']['en']) && $final_diagnoses[$diagnosis_id]['description']['en'])
                  @markdown($final_diagnoses[$diagnosis_id]['description']['en'])
                @else
                  {{ $final_diagnoses[$diagnosis_id]['label']['en'] }}
                @endif
              </label>
            </div>
          </div>
        @endforeach
      </div>
    @endif

    @if (count($moderate_dfs))
      <div class="input-container mb-5">
        <h2 class="fw-normal mb-3">Recommended</h2>
        @foreach ($moderate_dfs as $diagnosis_id => $drugs)
          <div wire:key="{{ 'df-' . $diagnosis_id }}">
            <div class="input-container mb-3">
              <label class="form-check-label" for="{{ $diagnosis_id }}">
                @if (isset($final_diagnoses[$diagnosis_id]['description']['en']) && $final_diagnoses[$diagnosis_id]['description']['en'])
                  @markdown($final_diagnoses[$diagnosis_id]['description']['en'])
                @else
                  {{ $final_diagnoses[$diagnosis_id]['label']['en'] }}
                @endif
              </label>
            </div>
          </div>
        @endforeach
      </div>
    @endif

    @if (count($light_dfs))
      <div class="input-container mb-5">
        <h2 class="fw-normal mb-3">To be considered</h2>
        @foreach ($light_dfs as $diagnosis_id => $drugs)
          <div wire:key="{{ 'df-' . $diagnosis_id }}">
            <div class="input-container mb-3">
              <label class="form-check-label" for="{{ $diagnosis_id }}">
                @if (isset($final_diagnoses[$diagnosis_id]['description']['en']) && $final_diagnoses[$diagnosis_id]['description']['en'])
                  @markdown($final_diagnoses[$diagnosis_id]['description']['en'])
                @else
                  {{ $final_diagnoses[$diagnosis_id]['label']['en'] }}
                @endif
              </label>
            </div>
          </div>
        @endforeach
      </div>
    @endif

    @if (count($other_dfs))
      <div class="input-container mb-5">
        <h2 class="fw-normal mb-3">Further information</h2>
        @foreach ($other_dfs as $diagnosis_id => $drugs)
          <div wire:key="{{ 'df-' . $diagnosis_id }}">
            <div class="input-container mb-3">
              <label class="form-check-label" for="{{ $diagnosis_id }}">
                @if (isset($final_diagnoses[$diagnosis_id]['description']['en']) && $final_diagnoses[$diagnosis_id]['description']['en'])
                  @markdown($final_diagnoses[$diagnosis_id]['description']['en'])
                @else
                  {{ $final_diagnoses[$diagnosis_id]['label']['en'] }}
                @endif
              </label>
            </div>
          </div>
        @endforeach
      </div>
    @endif
  @endif
  <div>
    <div class="d-flex justify-content-end">
      <button class="btn button-unisante m-1" onclick="window.location.href = window.location.href;">Restart</button>
    </div>
  </div>
</div>
