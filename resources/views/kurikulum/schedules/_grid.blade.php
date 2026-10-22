{{-- Partial: Grid jadwal untuk satu kelas/guru --}}
{{-- Variables: $grid (array), $periods (array), $days (array), $viewType (string) --}}
<div class="table-responsive">
    <table class="table table-bordered table-vcenter text-center align-middle m-0" style="font-size: 0.82rem;">
        <thead class="bg-light">
            <tr>
                <th style="width: 100px; min-width: 100px;" class="fw-bold text-dark">Hari</th>
                @for($p = 1; $p <= 10; $p++)
                    @if($p == 4)
                        <th class="bg-yellow-lt fw-bold text-dark px-1 py-2" style="width: 45px; writing-mode: vertical-rl; transform: rotate(180deg); font-size: 0.72rem; letter-spacing: 0.05rem;">
                            ISTIRAHAT 1
                        </th>
                    @endif
                    @if($p == 8)
                        <th class="bg-yellow-lt fw-bold text-dark px-1 py-2" style="width: 45px; writing-mode: vertical-rl; transform: rotate(180deg); font-size: 0.72rem; letter-spacing: 0.05rem;">
                            ISTIRAHAT 2
                        </th>
                    @endif
                    <th style="min-width: 105px;" class="py-2">
                        <div class="fw-bold text-primary fs-3">Jam {{ $p }}</div>
                        <div class="text-muted" style="font-size: 0.72rem;">{{ $periods[$p]['start'] }} - {{ $periods[$p]['end'] }}</div>
                    </th>
                @endfor
            </tr>
        </thead>
        <tbody>
            @foreach($days as $day)
                <tr>
                    <td class="fw-bold bg-light text-dark fs-3 text-start ps-3 border-end">
                        {{ $day }}
                    </td>

                    @php $skipCount = 0; @endphp
                    @for($p = 1; $p <= 10; $p++)
                        @if($p == 4)
                            <td class="bg-yellow-lt text-muted small p-0" style="background-color: #fefce8 !important;"></td>
                        @endif
                        @if($p == 8)
                            <td class="bg-yellow-lt text-muted small p-0" style="background-color: #fefce8 !important;"></td>
                        @endif

                        @if($skipCount > 0)
                            @php $skipCount--; continue; @endphp
                        @endif

                        @php
                            $slot = $grid[$day][$p] ?? null;
                        @endphp

                        @if($slot && $slot['is_first'])
                            @php
                                $colSpan = $slot['span'];
                                $skipCount = $colSpan - 1;
                                $raw = $slot['raw'];
                                $isMySlot = ($raw->teacher_id === auth()->id());
                            @endphp
                            <td colspan="{{ $colSpan }}" class="p-1 border position-relative" style="background-color: #ffffff;">
                                <div class="card p-2 text-start {{ $isMySlot && $viewType === 'class' ? 'border-primary' : 'border-0' }} shadow-sm h-100 rounded-3 d-flex flex-column justify-content-between position-relative group-hover"
                                     style="min-height: 75px; {{ $isMySlot && $viewType === 'class' ? 'border: 2px solid #206bc4 !important; background: #ffffff;' : 'background: linear-gradient(135deg, rgba(241, 245, 249, 0.9) 0%, rgba(226, 232, 240, 0.5) 100%);' }}">
                                    <div>
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <div class="d-flex align-items-center gap-1">
                                                <span class="badge {{ $slot['color_badge'] }} px-2 py-1 fw-bold fs-4">
                                                    {{ $slot['title'] }}
                                                </span>
                                            </div>
                                            <span class="text-muted small fw-semibold" style="font-size: 0.7rem;">
                                                @if($slot['start'] === $slot['end'])
                                                    Jam {{ $slot['start'] }}
                                                @else
                                                    Jam {{ $slot['start'] }}-{{ $slot['end'] }}
                                                @endif
                                            </span>
                                        </div>
                                        <div class="fw-bold text-dark text-truncate" title="{{ $slot['subtitle'] }}" style="font-size: 0.8rem;">
                                            <i class="ti {{ $viewType === 'class' ? 'ti-user' : 'ti-school' }} me-1 {{ $isMySlot && $viewType === 'class' ? 'text-primary fw-bold' : 'text-primary' }}"></i>
                                            {{ $slot['subtitle'] }}
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center justify-content-between pt-1 border-top mt-1" style="font-size: 0.68rem;">
                                        <span class="text-muted">
                                            {{ $slot['start_time'] ?? $raw->start_time }} - {{ $slot['end_time'] ?? $raw->end_time }}
                                        </span>
                                        @if(auth()->user()->canViewAllSchedules())
                                            <div class="d-flex gap-1">
                                                <button type="button" class="btn btn-sm btn-icon btn-ghost-primary p-0" title="Edit Jadwal"
                                                        onclick="openEditModal({{ $raw->id }}, {{ $raw->teacher_id }}, {{ $raw->subject_id }}, '{{ $raw->day }}', {{ $raw->start_period }}, {{ $raw->end_period }})">
                                                    <i class="ti ti-edit fs-4"></i>
                                                </button>
                                                <form action="{{ route('kurikulum.schedules.destroy', $raw->id) }}" method="POST" onsubmit="return confirm('Hapus slot jadwal ini?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-icon btn-ghost-danger p-0" title="Hapus Slot">
                                                        <i class="ti ti-trash fs-4"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        @elseif(!$slot)
                            <td class="p-1 border bg-white" style="height: 80px;">
                                <span class="text-muted opacity-25">&bull;</span>
                            </td>
                        @endif
                    @endfor
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
