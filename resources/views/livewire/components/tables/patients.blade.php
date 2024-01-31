<div>
  <div class="modal fade {{ $class }}" style="{{ $style }}" id="patientsTable" data-bs-backdrop="static"
    data-bs-keyboard="false" tabindex="-1" aria-labelledby="patientsTableLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
      <div class="modal-content">
        <div class="modal-header pb-0">
          <h5 class="modal-title" id="patientsTableLabel">Patients</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="d-flex justify-content-end pe-5 pt-2">
          <div class="input-group" style="width:250px;">
            <input class="form-control border rounded-pill" type="search" placeholder="Search...">
          </div>
          {{-- x-on:blur="$wire.search()" wire:model.blur="search"  --}}
        </div>
        <div class="modal-body pt-0 pb-0" Id="myTable">
          @forelse ($sliced_patients as $patient)
            <div wire:key="patient-{{ $patient['id'] }}" class="patients-grid" wire:click="start({{ $patient['id'] }})">
              <div class="patient-id">
                {{ $patient['id'] }}
              </div>

              <div class="patient-avatar">
                <img src="{{ $patient['avatar'] }}" alt="avatar" width="54" height="54">
              </div>

              <div class="patient-infos">
                <div>
                  {{ $patient['name'] }}
                </div>

                @if ($patient['deceased'])
                  <div>
                    <span class="badge bg-danger">Deceased</span>
                  </div>
                @endif

                <div>
                  <p class="text-muted mb-0">
                    {{ $patient['age'] }} years old {{ $patient['gender'] }}
                  </p>
                </div>
                <div>
                  DOB: {{ $patient['date_of_birth'] }}
                </div>
              </div>

              <div class="patient-more-infos">
                <div>
                  <i class="bi bi-telephone"></i>&nbsp{{ $patient['phone'] }}
                </div>
                <div>
                  <i class="bi bi-house"></i>&nbsp{{ $patient['line'] }}
                </div>
                <div>
                  {{ $patient['city'] }}
                </div>
              </div>

              <div class="patient-mrn">
                <i class="bi bi-person-vcard"></i>&nbsp{{ $patient['mrn'] }}
              </div>

              <div class="patient-arrow">
                <i class="bi bi-caret-right"></i>
              </div>
            </div>

          @empty
            <div>
              <h2>No patient found</h2>
            </div>
          @endforelse

          <div class="pt-3">
            <nav class="d-flex justify-items-center justify-content-between">
              <div class="d-flex justify-content-between flex-fill d-sm-none">
                <ul class="pagination mb-0">
                  {{-- Previous Page Link --}}
                  @if ($first_page)
                    <li class="page-item disabled" aria-disabled="true">
                      <span class="page-link">@lang('pagination.previous')</span>
                    </li>
                  @else
                    <li class="page-item">
                      <a class="page-link" style="cursor:pointer;" wire:click.prevent="previousPage"
                        rel="prev">@lang('pagination.previous')</a>
                    </li>
                  @endif

                  {{-- Next Page Link --}}
                  @if ($last_page)
                    <li class="page-item">
                      <a class="page-link" style="cursor:pointer;" wire:click.prevent="nextPage">@lang('pagination.next')</a>
                    </li>
                  @else
                    <li class="page-item disabled" aria-disabled="true">
                      <span class="page-link">@lang('pagination.next')</span>
                    </li>
                  @endif
                </ul>
              </div>

              <div class="d-none flex-sm-fill d-sm-flex align-items-sm-center justify-content-sm-between">
                <div>
                  <p class="small text-muted">
                    {!! __('Showing') !!}
                    <span class="fw-semibold">{{ $first_item }}</span>
                    {!! __('to') !!}
                    <span class="fw-semibold">{{ $last_item }}</span>
                    {!! __('of') !!}
                    <span class="fw-semibold">{{ $total }}</span>
                    {!! __('results') !!}
                  </p>
                </div>

                <div>
                  <ul class="pagination mb-0">
                    {{-- Previous Page Link --}}
                    @if ($first_page)
                      <li class="page-item disabled" aria-disabled="true" aria-label="@lang('pagination.previous')">
                        <span class="page-link" aria-hidden="true">&lsaquo;</span>
                      </li>
                    @else
                      <li class="page-item">
                        <a class="page-link" style="cursor:pointer;" wire:click.prevent="previousPage" rel="prev"
                          aria-label="@lang('pagination.previous')">&lsaquo;</a>
                      </li>
                    @endif

                    {{-- Pagination Elements --}}
                    @foreach ($pagination_buttons as $element)
                      {{-- "Three Dots" Separator --}}
                      @if (is_string($element))
                        <li class="page-item disabled" aria-disabled="true"><span
                            class="page-link">{{ $element }}</span></li>
                      @endif

                      {{-- Array Of Links --}}
                      @if (is_array($element))
                        @foreach ($element as $page)
                          @if ($page == $current_page)
                            <li class="page-item active" aria-current="page"><span
                                class="page-link">{{ $page }}</span></li>
                          @else
                            <li class="page-item"><a class="page-link" style="cursor:pointer;"
                                wire:click.prevent="gotoPage({{ $page }})">{{ $page }}</a></li>
                          @endif
                        @endforeach
                      @endif
                    @endforeach

                    {{-- Next Page Link --}}
                    @if ($last_page)
                      <li class="page-item">
                        <a class="page-link" style="cursor:pointer;" wire:click.prevent="nextPage"
                          aria-label="@lang('pagination.next')">&rsaquo;</a>
                      </li>
                    @else
                      <li class="page-item disabled" aria-disabled="true" aria-label="@lang('pagination.next')">
                        <span class="page-link" aria-hidden="true">&rsaquo;</span>
                      </li>
                    @endif
                  </ul>
                </div>
              </div>
            </nav>

          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
</div>
