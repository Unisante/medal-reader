@props([
    'gender_node',
    'diagnoses_per_cc',
    'df_to_display',
    'chosen_complaint_categories',
    'final_diagnoses',
    'cache_key',
])

{{-- In theory the view shouldn't do any logic BUT that would actually reduce the cache usage --}}
@php
  $cache = Cache::get($cache_key);
  $full_nodes = $cache['full_nodes'];
  $final_diagnoses = $cache['final_diagnoses'];
  $health_cares = $cache['health_cares'];
  $female_gender_answer_id = $cache['female_gender_answer_id'];
  $gender = $gender_node === $female_gender_answer_id ? 'f' : 'm';

  $high_dfs = collect($df_to_display)->filter(function ($df, $k) use ($final_diagnoses, $gender) {
      $df = $final_diagnoses[$k];
      $dd_id = $df['diagnosis_id'];
      $gender_is_ok = false;

      if (
          isset($df['label']['en']) &&
          ((!Str::contains($df['label']['en'], '[M]') && $gender === 'f') ||
              (!Str::contains($df['label']['en'], '[F]') && $gender === 'm'))
      ) {
          $gender_is_ok = true;
      }
      return $df['level_of_urgency'] === 10 && $gender_is_ok;
  });

  $moderate_dfs = collect($df_to_display)->filter(function ($df, $k) use ($final_diagnoses, $gender) {
      $df = $final_diagnoses[$k];
      $dd_id = $df['diagnosis_id'];
      $gender_is_ok = false;
      if (
          isset($df['label']['en']) &&
          ((!Str::contains($df['label']['en'], '[M]') && $gender === 'f') ||
              (!Str::contains($df['label']['en'], '[F]') && $gender === 'm'))
      ) {
          $gender_is_ok = true;
      }
      return $df['level_of_urgency'] === 9 && $gender_is_ok;
  });
  $light_dfs = collect($df_to_display)->filter(function ($df, $k) use ($final_diagnoses, $gender) {
      $df = $final_diagnoses[$k];
      $dd_id = $df['diagnosis_id'];
      $gender_is_ok = false;
      if (
          isset($df['label']['en']) &&
          ((!Str::contains($df['label']['en'], '[M]') && $gender === 'f') ||
              (!Str::contains($df['label']['en'], '[F]') && $gender === 'm'))
      ) {
          $gender_is_ok = true;
      }
      return $df['level_of_urgency'] === 8 && $gender_is_ok;
  });
  $other_dfs = collect($df_to_display)->filter(function ($df, $k) use ($final_diagnoses, $gender) {
      $df = $final_diagnoses[$k];
      $dd_id = $df['diagnosis_id'];
      $gender_is_ok = false;
      if (
          isset($df['label']['en']) &&
          ((!Str::contains($df['label']['en'], '[M]') && $gender === 'f') ||
              (!Str::contains($df['label']['en'], '[F]') && $gender === 'm'))
      ) {
          $gender_is_ok = true;
      }
      return $df['level_of_urgency'] < 8 && $gender_is_ok;
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
              @if (isset($final_diagnoses[$diagnosis_id]['description']['en']) && $final_diagnoses[$diagnosis_id]['description']['en'])
                @markdown($final_diagnoses[$diagnosis_id]['description']['en'])
              @else
                {{ $final_diagnoses[$diagnosis_id]['label']['en'] }}
              @endif
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
              @if (isset($final_diagnoses[$diagnosis_id]['description']['en']) && $final_diagnoses[$diagnosis_id]['description']['en'])
                @markdown($final_diagnoses[$diagnosis_id]['description']['en'])
              @else
                {{ $final_diagnoses[$diagnosis_id]['label']['en'] }}
              @endif
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
              @if (isset($final_diagnoses[$diagnosis_id]['description']['en']) && $final_diagnoses[$diagnosis_id]['description']['en'])
                @markdown($final_diagnoses[$diagnosis_id]['description']['en'])
              @else
                {{ $final_diagnoses[$diagnosis_id]['label']['en'] }}
              @endif
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
              @if (isset($final_diagnoses[$diagnosis_id]['description']['en']) && $final_diagnoses[$diagnosis_id]['description']['en'])
                @markdown($final_diagnoses[$diagnosis_id]['description']['en'])
              @else
                {{ $final_diagnoses[$diagnosis_id]['label']['en'] }}
              @endif
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
