@php
    $activeSetting = \App\Models\SchoolSetting::getSetting();
    $currentAcademicYear = $activeSetting->academic_year;
    $currentSemester = $activeSetting->semester;
    $isArchive = \App\Models\SchoolSetting::isViewingArchive();
    $masterYear = \App\Models\SchoolSetting::getMasterYear();
    $masterSemester = \App\Models\SchoolSetting::getMasterSemester();
    $availableYears = \App\Models\SchoolSetting::getAvailableAcademicYears();
@endphp

<div class="nav-item dropdown me-2 me-lg-3">
    <button type="button" class="btn {{ $isArchive ? 'btn-warning text-dark fw-bold' : 'btn-outline-primary bg-white' }} shadow-sm dropdown-toggle py-1 px-2 px-md-3 d-flex align-items-center" data-bs-toggle="dropdown" aria-expanded="false" title="Klik untuk mengganti Tahun Pelajaran aktif">
        <i class="ti ti-calendar-time me-1 me-md-2 fs-2 {{ $isArchive ? 'text-dark' : 'text-primary' }}"></i>
        <div class="text-start me-1 me-md-2 d-none d-sm-block">
            <div class="{{ $isArchive ? 'text-dark fw-bold' : 'text-muted' }} text-uppercase" style="font-size: 0.64rem; letter-spacing: 0.05rem;">
                Tahun Pelajaran
                @if($isArchive)
                    <span class="badge bg-dark text-warning ms-1" style="font-size: 0.58rem;">ARSIP</span>
                @endif
            </div>
            <div class="fw-bold {{ $isArchive ? 'text-dark' : 'text-dark' }} lh-1" style="font-size: 0.84rem;">
                TP. {{ $currentAcademicYear }}
            </div>
        </div>
        <span class="d-inline d-sm-none fw-bold small">
            TP. {{ $currentAcademicYear }}
        </span>
    </button>
    <div class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-3" style="min-width: 320px; z-index: 1050;">
        <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
            <div>
                <h6 class="mb-0 fw-bold text-dark"><i class="ti ti-calendar-stats me-1 text-primary"></i> Ganti Tahun Pelajaran</h6>
                <div class="text-muted small" style="font-size: 0.72rem;">Konteks aktif data operasional sekolah</div>
            </div>
            @if($isArchive)
                <span class="badge bg-warning text-dark">Mode Arsip</span>
            @else
                <span class="badge bg-success-lt text-success">Tahun Berjalan</span>
            @endif
        </div>

        <form action="{{ route('academic-year.switch') }}" method="POST">
            @csrf
            <div class="mb-3">
                <label class="form-label small fw-bold text-dark mb-1">
                    <i class="ti ti-calendar me-1 text-primary"></i> Tahun Pelajaran
                </label>
                <select name="academic_year" class="form-select form-select-sm fw-semibold" required>
                    @foreach($availableYears as $year)
                        <option value="{{ $year }}" {{ $currentAcademicYear === $year ? 'selected' : '' }}>
                            TP. {{ $year }} {{ $year === $masterYear ? '(Default Master)' : '' }}
                        </option>
                    @endforeach
                </select>
                <div class="text-muted small mt-1" style="font-size: 0.70rem;">
                    Basis utama seluruh data siswa, kelas, guru, jadwal, dan kegiatan operasional sekolah.
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-sm fw-bold">
                    <i class="ti ti-check me-1"></i> Terapkan Tahun Pelajaran
                </button>
                @if($isArchive)
                    <a href="{{ route('academic-year.reset') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="ti ti-rotate-2 me-1"></i> Reset ke Master ({{ $masterYear }})
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>
