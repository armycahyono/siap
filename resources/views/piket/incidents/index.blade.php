@extends('layouts.app')

@section('title', 'Catatan Kejadian & Kasus Siswa')

@section('content')
<div class="container-xl">
    <!-- Header (Model 2: Hero Context Banner) -->
    @php
        $incActions = '';
        if (auth()->user()->canRecordPiketIncident() || ($canManageAllCases ?? false)) {
            $incActions = '<a href="' . route('piket.incidents.create') . '" class="btn btn-sm btn-danger shadow-xs fw-bold"><i class="ti ti-plus me-1"></i> Catat Kasus Baru</a>';
        }
    @endphp
    @include('layouts._hero_header', [
        'title' => 'Catatan Kejadian & Pelanggaran Siswa',
        'subtitle' => 'Pencatatan penanganan awal kasus tata tertib di meja piket dan rujukan ke Wali Kelas / Guru BK / Kesiswaan.',
        'icon' => 'ti ti-alert-triangle',
        'category' => 'BUKU KASUS & TATA TERTIB',
        'actions' => $incActions
    ])

    <!-- Ringkasan Statistik Kasus & Kedisiplinan -->
    @if(!empty($stats))
    <div class="row row-deck row-cards mb-3">
        <div class="col-6 col-sm-4 col-lg-2">
            <div class="card card-sm border-0 shadow-sm">
                <div class="card-body p-2 px-3">
                    <div class="d-flex align-items-center">
                        <span class="bg-primary-lt text-primary avatar avatar-sm me-2">
                            <i class="ti ti-files fs-2"></i>
                        </span>
                        <div>
                            <div class="text-muted small" style="font-size: 0.7rem;">Total Kasus</div>
                            <div class="h3 mb-0 fw-bold">{{ $stats['total'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-sm-4 col-lg-2">
            <div class="card card-sm border-0 shadow-sm">
                <div class="card-body p-2 px-3">
                    <div class="d-flex align-items-center">
                        <span class="bg-warning-lt text-warning avatar avatar-sm me-2">
                            <i class="ti ti-clock fs-2"></i>
                        </span>
                        <div>
                            <div class="text-muted small" style="font-size: 0.7rem;">Tercatat Piket</div>
                            <div class="h3 mb-0 fw-bold text-warning">{{ $stats['tercatat'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-sm-4 col-lg-2">
            <div class="card card-sm border-0 shadow-sm">
                <div class="card-body p-2 px-3">
                    <div class="d-flex align-items-center">
                        <span class="bg-info-lt text-info avatar avatar-sm me-2">
                            <i class="ti ti-arrow-forward fs-2"></i>
                        </span>
                        <div>
                            <div class="text-muted small" style="font-size: 0.7rem;">Ditindaklanjuti</div>
                            <div class="h3 mb-0 fw-bold text-info">{{ $stats['ditindaklanjuti'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-sm-4 col-lg-2">
            <div class="card card-sm border-0 shadow-sm">
                <div class="card-body p-2 px-3">
                    <div class="d-flex align-items-center">
                        <span class="bg-success-lt text-success avatar avatar-sm me-2">
                            <i class="ti ti-circle-check fs-2"></i>
                        </span>
                        <div>
                            <div class="text-muted small" style="font-size: 0.7rem;">Selesai Dibina</div>
                            <div class="h3 mb-0 fw-bold text-success">{{ $stats['selesai'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-sm-4 col-lg-2">
            <div class="card card-sm border-0 shadow-sm">
                <div class="card-body p-2 px-3">
                    <div class="d-flex align-items-center">
                        <span class="bg-danger-lt text-danger avatar avatar-sm me-2">
                            <i class="ti ti-shield-check fs-2"></i>
                        </span>
                        <div>
                            <div class="text-muted small" style="font-size: 0.7rem;">Terverifikasi</div>
                            <div class="h3 mb-0 fw-bold text-danger">{{ $stats['terverifikasi'] ?? 0 }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-sm-4 col-lg-2">
            <div class="card card-sm border-0 shadow-sm">
                <div class="card-body p-2 px-3">
                    <div class="d-flex align-items-center">
                        <span class="bg-secondary-lt text-secondary avatar avatar-sm me-2">
                            <i class="ti ti-hourglass fs-2"></i>
                        </span>
                        <div>
                            <div class="text-muted small" style="font-size: 0.7rem;">Perlu Verifikasi</div>
                            <div class="h3 mb-0 fw-bold text-secondary">{{ $stats['menunggu_verifikasi'] ?? 0 }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Filter Form Terpadu -->
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body py-2 px-3">
            <form action="{{ route('piket.incidents.index') }}" method="GET" class="row g-2 align-items-end">
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small text-muted mb-1">Periode</label>
                    <select name="period" class="form-select form-select-sm" onchange="if(this.value !== 'range') this.form.submit()">
                        <option value="today" {{ $period === 'today' ? 'selected' : '' }}>Hari Ini</option>
                        <option value="week" {{ $period === 'week' ? 'selected' : '' }}>Pekan Ini</option>
                        <option value="month" {{ $period === 'month' ? 'selected' : '' }}>Bulan Ini</option>
                        <option value="range" {{ $period === 'range' ? 'selected' : '' }}>Rentang Tanggal</option>
                    </select>
                </div>
                @if($period === 'range')
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small text-muted mb-1">Rentang Tanggal</label>
                    <div class="input-group input-group-sm">
                        <input type="date" name="start_date" class="form-control" value="{{ $startDate }}">
                        <input type="date" name="end_date" class="form-control" value="{{ $endDate }}">
                    </div>
                </div>
                @endif
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small text-muted mb-1">Kelas</label>
                    <select name="classroom_id" class="form-select form-select-sm">
                        <option value="">Semua Kelas</option>
                        @foreach($classrooms as $c)
                            <option value="{{ $c->id }}" {{ (string)$classroomId === (string)$c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small text-muted mb-1">Kategori</label>
                    <select name="category" class="form-select form-select-sm">
                        <option value="">Semua Kategori</option>
                        <option value="kedisiplinan" {{ $category === 'kedisiplinan' ? 'selected' : '' }}>Kedisiplinan</option>
                        <option value="seragam" {{ $category === 'seragam' ? 'selected' : '' }}>Seragam / Kerapian</option>
                        <option value="gadget_terlarang" {{ $category === 'gadget_terlarang' ? 'selected' : '' }}>Gadget / HP</option>
                        <option value="perkelahian" {{ $category === 'perkelahian' ? 'selected' : '' }}>Pertikaian</option>
                        <option value="rokok" {{ $category === 'rokok' ? 'selected' : '' }}>Rokok / Vape</option>
                        <option value="kesehatan_pingsan" {{ $category === 'kesehatan_pingsan' ? 'selected' : '' }}>Kesehatan / Pingsan</option>
                        <option value="lainnya" {{ $category === 'lainnya' ? 'selected' : '' }}>Lain-lain</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small text-muted mb-1">Verifikasi</label>
                    <select name="verification_status" class="form-select form-select-sm">
                        <option value="">Semua Verifikasi</option>
                        <option value="menunggu_verifikasi" {{ ($verificationStatus ?? '') === 'menunggu_verifikasi' ? 'selected' : '' }}>Menunggu Verifikasi</option>
                        <option value="terverifikasi" {{ ($verificationStatus ?? '') === 'terverifikasi' ? 'selected' : '' }}>Terverifikasi (Sanksi)</option>
                        <option value="bukan_pelanggaran" {{ ($verificationStatus ?? '') === 'bukan_pelanggaran' ? 'selected' : '' }}>Bukan Pelanggaran</option>
                        <option value="dibatalkan" {{ ($verificationStatus ?? '') === 'dibatalkan' ? 'selected' : '' }}>Dibatalkan</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small text-muted mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Semua Status</option>
                        <option value="tercatat" {{ $status === 'tercatat' ? 'selected' : '' }}>Tercatat</option>
                        <option value="ditindaklanjuti" {{ $status === 'ditindaklanjuti' ? 'selected' : '' }}>Ditindaklanjuti</option>
                        <option value="selesai" {{ $status === 'selesai' ? 'selected' : '' }}>Selesai</option>
                    </select>
                </div>
                <div class="col-md-3 col-sm-12">
                    <label class="form-label small text-muted mb-1">Pencarian</label>
                    <input type="text" name="search" class="form-control form-control-sm" value="{{ $search }}" placeholder="Cari nama siswa / uraian kasus...">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                    @if($category || $status || $classroomId || !empty($verificationStatus) || $search || $period !== 'today')
                        <a href="{{ route('piket.incidents.index') }}" class="btn btn-sm btn-ghost-secondary">Reset</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Table Incidents -->
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-vcenter table-hover card-table">
                <thead class="table-light">
                    <tr>
                        <th>Waktu Kejadian</th>
                        <th>Siswa & Kelas</th>
                        <th>Kategori</th>
                        <th>Uraian Kejadian & Tindakan</th>
                        <th>Tindak Lanjut & Sitaan</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($incidents as $inc)
                        <tr>
                            <td>
                                <div class="fw-bold text-dark">{{ $inc->incident_date?->format('d/m/Y') }}</div>
                                <div class="text-muted small"><i class="ti ti-clock me-1"></i>{{ substr($inc->incident_time, 0, 5) }} WIB</div>
                            </td>
                            <td>
                                <div class="fw-bold text-dark fs-3">{{ $inc->student_name }}</div>
                                <span class="badge bg-blue-lt">{{ $inc->classroom?->name ?? 'Umum' }}</span>
                                @if($inc->classroom?->homeroomTeacher)
                                    <div class="text-muted small">Wali: {{ $inc->classroom->homeroomTeacher->formatted_short_name }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-danger-lt fw-bold">{{ $inc->category_label }}</span>
                            </td>
                            <td>
                                <div class="text-dark small fw-bold">{{ $inc->description }}</div>
                                <div class="text-secondary small mt-1"><strong>Tindakan Piket:</strong> {{ $inc->action_taken }}</div>
                                @if($inc->evidence_photo)
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-xs btn-outline-primary d-inline-flex align-items-center gap-1 py-1 px-2" onclick="showIncidentPhoto('{{ $inc->evidence_photo_url }}', '{{ addslashes($inc->student_name) }}', '{{ addslashes($inc->category_label) }}', '{{ addslashes($inc->description) }}')">
                                            <i class="ti ti-camera fs-3"></i>
                                            <span>Lihat Bukti Foto</span>
                                        </button>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-purple-lt">{{ $inc->follow_up_label }}</span>
                                @if($inc->confiscated_items)
                                    <div class="text-danger small mt-1"><i class="ti ti-box me-1"></i> Sitaan: {{ $inc->confiscated_items }}</div>
                                @endif
                            </td>
                            <td class="text-center">
                                <div>
                                    <span class="badge {{ $inc->verification_badge_class }}">{{ $inc->verification_status_label }}</span>
                                </div>
                                <div class="mt-1">
                                    @if($inc->status === 'selesai')
                                        <span class="badge bg-success-lt text-success"><i class="ti ti-check me-1"></i> Selesai</span>
                                    @elseif($inc->status === 'ditindaklanjuti')
                                        <span class="badge bg-info-lt text-info"><i class="ti ti-arrow-forward me-1"></i> Ditindaklanjuti</span>
                                    @else
                                        <span class="badge bg-warning-lt text-warning"><i class="ti ti-clock me-1"></i> Tercatat Piket</span>
                                    @endif
                                </div>
                                @if($inc->disciplinary_sanction)
                                    <div class="text-danger small mt-1 fw-bold" title="{{ $inc->disciplinary_sanction }}">
                                        <i class="ti ti-gavel me-1"></i>{{ Str::limit($inc->disciplinary_sanction, 25) }}
                                    </div>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#modal-detail-{{ $inc->id }}" title="Lihat Detail Lengkap Kasus">
                                    <i class="ti ti-eye me-1"></i> Detail
                                </button>
                                @if(auth()->user()->canVerifyDiscipline($academicYear))
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modal-verify-{{ $inc->id }}" title="Verifikasi & Sanksi Pelanggaran">
                                    <i class="ti ti-shield-check me-1"></i> Verifikasi
                                </button>
                                @endif
                                @if(auth()->user()->canManageDiscipline($academicYear) || auth()->user()->isSuperAdmin() || ($canManageAllCases ?? false))
                                <a href="{{ route('piket.incidents.surat_panggilan', $inc) }}" class="btn btn-sm btn-outline-danger" title="Cetak Surat Panggilan Orang Tua (PDF)">
                                    <i class="ti ti-mail me-1"></i> Surat Panggilan
                                </a>
                                @endif
                                @if($inc->student_id && auth()->user()->canCreateVisitation($inc->student, $academicYear))
                                <a href="{{ route('visitations.create', ['student_id' => $inc->student_id, 'source_type' => 'kasus_kedisiplinan', 'source_id' => $inc->id]) }}" class="btn btn-sm btn-outline-teal" title="Rujuk Visitasi / Pemantauan">
                                    <i class="ti ti-home-heart"></i>
                                </a>
                                @endif
                                @if($inc->follow_up_target === 'rujuk_bk' && auth()->user()->canOperateBk())
                                <a href="{{ route('bk.referrals.show', $inc) }}" class="btn btn-sm btn-outline-primary" title="Tindak Lanjut BK">
                                    <i class="ti ti-heart-handshake me-1"></i> BK
                                </a>
                                @endif
                                @if($canManageAllCases ?? false)
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modal-status-{{ $inc->id }}" title="Perbarui Status Kasus">
                                    <i class="ti ti-edit me-1"></i> Status
                                </button>
                                @endif
                                @if(auth()->user()->canDeletePiketIncident($inc, $academicYear))
                                <form action="{{ route('piket.incidents.destroy', $inc) }}" method="POST" onsubmit="return confirm('Hapus data catatan kasus siswa ini? Tindakan ini tidak dapat dibatalkan.')" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-ghost-danger" title="Hapus Kasus">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </form>
                                @endif

                                <!-- Modal Detail Kasus Siswa -->
                                <div class="modal fade" id="modal-detail-{{ $inc->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-lg">
                                        <div class="modal-content shadow-lg border-0 text-start">
                                            <div class="modal-header bg-light">
                                                <div>
                                                    <h5 class="modal-title fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                                                        <i class="ti ti-file-text text-primary fs-2"></i>
                                                        <span>Detail Kasus Kedisiplinan Siswa</span>
                                                    </h5>
                                                    <div class="text-muted small mt-1">
                                                        No. Kasus #{{ $inc->id }} &bull; Tanggal: {{ $inc->incident_date?->format('d/m/Y') }} &bull; {{ substr($inc->incident_time, 0, 5) }} WIB
                                                    </div>
                                                </div>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <!-- Identitas Siswa -->
                                                <div class="p-3 bg-light rounded border mb-3">
                                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                                        <div>
                                                            <div class="fw-bold fs-2 text-dark">{{ $inc->student_name }}</div>
                                                            <div class="text-muted small">
                                                                Kelas: <span class="badge bg-blue-lt fw-bold">{{ $inc->classroom?->name ?? 'Umum' }}</span>
                                                                @if($inc->classroom?->homeroomTeacher)
                                                                    &bull; Wali Kelas: <strong>{{ $inc->classroom->homeroomTeacher->formatted_short_name }}</strong>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        <span class="badge bg-danger-lt fw-bold fs-4">{{ $inc->category_label }}</span>
                                                    </div>
                                                    <div class="small text-secondary">
                                                        <span><i class="ti ti-map-pin me-1"></i> Lokasi: {{ $inc->location ?: 'Lingkungan Sekolah' }}</span>
                                                        <span class="ms-3"><i class="ti ti-user me-1"></i> Dicatat oleh: {{ $inc->recordedBy?->name ?? 'Petugas Piket' }}</span>
                                                    </div>
                                                </div>

                                                <!-- Uraian Kasus & Tindakan Piket -->
                                                <div class="row g-3 mb-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label fw-bold text-dark mb-1">Kronologi / Uraian Kejadian:</label>
                                                        <div class="p-3 bg-white border rounded small text-dark" style="min-height: 80px;">
                                                            {{ $inc->description }}
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label fw-bold text-dark mb-1">Tindakan Awal Meja Piket:</label>
                                                        <div class="p-3 bg-white border rounded small text-secondary" style="min-height: 80px;">
                                                            {{ $inc->action_taken }}
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Sitaan & Bukti Foto -->
                                                <div class="row g-3 mb-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label fw-bold text-dark mb-1">Barang Sitaan Sementara:</label>
                                                        <div class="p-2 px-3 bg-white border rounded small">
                                                            @if($inc->confiscated_items)
                                                                <span class="text-danger fw-bold"><i class="ti ti-box me-1"></i>{{ $inc->confiscated_items }}</span>
                                                            @else
                                                                <span class="text-muted">Tidak ada barang yang disita</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label fw-bold text-dark mb-1">Bukti Foto Dokumentasi:</label>
                                                        <div>
                                                            @if($inc->evidence_photo)
                                                                <button type="button" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1" onclick="showIncidentPhoto('{{ $inc->evidence_photo_url }}', '{{ addslashes($inc->student_name) }}', '{{ addslashes($inc->category_label) }}', '{{ addslashes($inc->description) }}')">
                                                                    <i class="ti ti-camera fs-3"></i>
                                                                    <span>Buka Foto Dokumentasi</span>
                                                                </button>
                                                            @else
                                                                <span class="badge bg-light text-muted border py-1.5 px-2">Tidak ada lampiran foto</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Hasil Verifikasi Tim Kedisiplinan -->
                                                <div class="card border mb-3">
                                                    <div class="card-header bg-light py-2 d-flex align-items-center justify-content-between">
                                                        <h6 class="card-title fw-bold text-dark mb-0 d-flex align-items-center gap-1">
                                                            <i class="ti ti-shield-check text-danger"></i>
                                                            <span>Verifikasi Tim Kedisiplinan &amp; Sanksi</span>
                                                        </h6>
                                                        <span class="badge {{ $inc->verification_badge_class }}">{{ $inc->verification_status_label }}</span>
                                                    </div>
                                                    <div class="card-body p-3">
                                                        <div class="row g-2 mb-2">
                                                            <div class="col-md-6 small text-muted">
                                                                Verifikator: <strong>{{ $inc->verifier?->name ?? 'Belum diverifikasi' }}</strong>
                                                            </div>
                                                            <div class="col-md-6 small text-muted text-md-end">
                                                                Waktu Verifikasi: <strong>{{ $inc->verified_at ? $inc->verified_at->format('d/m/Y H:i') . ' WIB' : '-' }}</strong>
                                                            </div>
                                                        </div>
                                                        <div class="mb-2">
                                                            <label class="form-label small fw-bold text-dark mb-1">Sanksi / Pembinaan Kedisiplinan:</label>
                                                            <div class="p-2 px-3 bg-light rounded text-danger small fw-bold">
                                                                {{ $inc->disciplinary_sanction ?: 'Belum ada sanksi yang ditetapkan.' }}
                                                            </div>
                                                        </div>
                                                        @if($inc->verification_notes)
                                                        <div class="mb-2">
                                                            <label class="form-label small fw-bold text-dark mb-1">Catatan Verifikasi / Klarifikasi:</label>
                                                            <div class="p-2 px-3 bg-light rounded small text-secondary">
                                                                {{ $inc->verification_notes }}
                                                            </div>
                                                        </div>
                                                        @endif
                                                        <div class="small text-muted mt-2">
                                                            <i class="ti ti-users me-1"></i> Keterlibatan Orang Tua:
                                                            @if($inc->parental_involvement)
                                                                <span class="badge bg-warning-lt text-dark fw-bold">Ya (Melibatkan Orang Tua)</span>
                                                                @if($inc->parental_involvement_notes)
                                                                    <span class="ms-1 text-secondary">({{ $inc->parental_involvement_notes }})</span>
                                                                @endif
                                                            @else
                                                                <span class="badge bg-secondary-lt text-muted">Tidak</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Status Akhir & Rujukan -->
                                                <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded border">
                                                    <div>
                                                        <span class="text-muted small">Alur Tindak Lanjut:</span>
                                                        <div class="fw-bold text-purple">{{ $inc->follow_up_label }}</div>
                                                    </div>
                                                    <div class="text-end">
                                                        <span class="text-muted small">Status Kasus:</span>
                                                        <div>
                                                            @if($inc->status === 'selesai')
                                                                <span class="badge bg-success text-white fw-bold"><i class="ti ti-check me-1"></i> Selesai Ditangani</span>
                                                            @elseif($inc->status === 'ditindaklanjuti')
                                                                <span class="badge bg-info text-white fw-bold"><i class="ti ti-arrow-forward me-1"></i> Sedang Ditindaklanjuti</span>
                                                            @else
                                                                <span class="badge bg-warning text-dark fw-bold"><i class="ti ti-clock me-1"></i> Tercatat di Meja Piket</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Info Keamanan Data BK -->
                                                <div class="alert alert-light border mt-3 mb-0 py-2 px-3 text-muted small d-flex align-items-center gap-2">
                                                    <i class="ti ti-shield-lock text-primary fs-3"></i>
                                                    <span>Catatan konseling dan sesi bimbingan psikologis (BK) bersifat rahasia dan hanya dapat diakses secara mandiri oleh Guru BK yang berwenang melalui Modul BK.</span>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-light py-2">
                                                @if(auth()->user()->canManageDiscipline($academicYear) || auth()->user()->isSuperAdmin())
                                                <a href="{{ route('piket.incidents.surat_panggilan', $inc) }}" class="btn btn-sm btn-outline-danger">
                                                    <i class="ti ti-mail me-1"></i> Unduh Surat Panggilan (PDF)
                                                </a>
                                                @endif
                                                @if(auth()->user()->canVerifyDiscipline($academicYear))
                                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#modal-verify-{{ $inc->id }}">
                                                    <i class="ti ti-shield-check me-1"></i> Ubah Verifikasi &amp; Sanksi
                                                </button>
                                                @endif
                                                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                @if(auth()->user()->canVerifyDiscipline($academicYear))
                                <!-- Modal Verifikasi Pelanggaran & Sanksi -->
                                <div class="modal fade" id="modal-verify-{{ $inc->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content shadow-lg border-0 text-start">
                                            <form action="{{ route('piket.incidents.verify', $inc) }}" method="POST">
                                                @csrf
                                                <div class="modal-header bg-light">
                                                    <h5 class="modal-title fw-bold text-dark mb-0">
                                                        <i class="ti ti-shield-check text-danger me-2"></i> Verifikasi &amp; Sanksi Pelanggaran
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body p-4">
                                                    <div class="mb-3 p-3 bg-light rounded border">
                                                        <div class="fw-bold fs-3 text-dark">{{ $inc->student_name }}</div>
                                                        <div class="text-muted small">
                                                            {{ $inc->classroom?->name ?? 'Umum' }} &bull; Kategori: <span class="badge bg-danger-lt fw-bold">{{ $inc->category_label }}</span>
                                                        </div>
                                                        <div class="text-secondary small mt-2">
                                                            <strong>Uraian Kejadian:</strong> {{ $inc->description }}
                                                        </div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold text-dark required">Status Verifikasi Pelanggaran:</label>
                                                        <select name="verification_status" class="form-select" required>
                                                            <option value="menunggu_verifikasi" {{ $inc->verification_status === 'menunggu_verifikasi' ? 'selected' : '' }}>Menunggu Verifikasi</option>
                                                            <option value="terverifikasi" {{ $inc->verification_status === 'terverifikasi' ? 'selected' : '' }}>Pelanggaran Terverifikasi (Terbukti)</option>
                                                            <option value="bukan_pelanggaran" {{ $inc->verification_status === 'bukan_pelanggaran' ? 'selected' : '' }}>Bukan Pelanggaran / Selesai Tanpa Sanksi</option>
                                                            <option value="dibatalkan" {{ $inc->verification_status === 'dibatalkan' ? 'selected' : '' }}>Dibatalkan</option>
                                                        </select>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold text-dark">Sanksi / Tindakan Kedisiplinan:</label>
                                                        <textarea name="disciplinary_sanction" rows="2" class="form-control" placeholder="Tindakan pembinaan kedisiplinan, surat peringatan, tugas edukatif, skorsing, dll...">{{ $inc->disciplinary_sanction }}</textarea>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-check form-switch mb-1">
                                                            <input class="form-check-input" type="checkbox" name="parental_involvement" value="1" {{ $inc->parental_involvement ? 'checked' : '' }}>
                                                            <span class="form-check-label fw-semibold">Melibatkan Orang Tua / Wali Siswa</span>
                                                        </label>
                                                        <input type="text" name="parental_involvement_notes" class="form-control form-control-sm" placeholder="Catatan keterlibatan orang tua (panggilan / kehadiran)..." value="{{ $inc->parental_involvement_notes }}">
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold text-dark">Tindak Lanjut:</label>
                                                        <select name="follow_up_target" class="form-select">
                                                            <option value="selesai_di_piket" {{ $inc->follow_up_target === 'selesai_di_piket' ? 'selected' : '' }}>Selesai di Meja Piket / Tim Kedisiplinan</option>
                                                            <option value="rujuk_bk" {{ $inc->follow_up_target === 'rujuk_bk' ? 'selected' : '' }}>Rujuk ke Guru BK (Bimbingan Konseling)</option>
                                                            <option value="rujuk_wali_kelas" {{ $inc->follow_up_target === 'rujuk_wali_kelas' ? 'selected' : '' }}>Rujuk ke Wali Kelas</option>
                                                            <option value="rujuk_kesiswaan" {{ $inc->follow_up_target === 'rujuk_kesiswaan' ? 'selected' : '' }}>Rujuk ke Waka Kesiswaan</option>
                                                        </select>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold text-dark">Catatan Pemeriksaan / Verifikasi:</label>
                                                        <textarea name="verification_notes" rows="2" class="form-control" placeholder="Catatan hasil klarifikasi dengan siswa / saksi...">{{ $inc->verification_notes }}</textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-light py-2">
                                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-danger btn-sm">Simpan Verifikasi</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                @endif

                                <!-- Modal Update Status Kasus -->
                                <div class="modal fade" id="modal-status-{{ $inc->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content shadow-lg border-0 text-start">
                                            <form action="{{ route('piket.incidents.status', $inc) }}" method="POST">
                                                @csrf
                                                <div class="modal-header bg-light">
                                                    <h5 class="modal-title fw-bold text-dark mb-0">
                                                        <i class="ti ti-adjustments-horizontal text-primary me-2"></i> Update Status Kasus
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body p-4">
                                                    <div class="mb-3 p-3 bg-light rounded border">
                                                        <div class="fw-bold fs-3 text-dark">{{ $inc->student_name }}</div>
                                                        <div class="text-muted small">
                                                            {{ $inc->classroom?->name ?? 'Umum' }} &bull; Kategori: <span class="badge bg-danger-lt fw-bold">{{ $inc->category_label }}</span>
                                                        </div>
                                                        <div class="text-secondary small mt-2">
                                                            <strong>Uraian:</strong> {{ $inc->description }}
                                                        </div>
                                                    </div>

                                                    @if($inc->status === 'selesai' && !auth()->user()->canManageDiscipline($academicYear))
                                                    <div class="alert alert-warning mb-3 d-flex align-items-center gap-2">
                                                        <i class="ti ti-lock fs-2"></i>
                                                        <div class="small">
                                                            Kasus ini telah berstatus <strong>Selesai</strong>. Pembukaan kembali kasus hanya berwenang dilakukan oleh Tim Kedisiplinan, Waka Kesiswaan, atau Kepala Sekolah.
                                                        </div>
                                                    </div>
                                                    @endif

                                                    <label class="form-label fw-bold text-dark required mb-2">Pilih Status Penanganan Kasus:</label>
                                                    <div class="form-selectgroup form-selectgroup-boxes d-flex flex-column gap-2">
                                                        <label class="form-selectgroup-item flex-fill">
                                                            <input type="radio" name="status" value="tercatat" class="form-selectgroup-input" {{ $inc->status === 'tercatat' ? 'checked' : '' }} {{ ($inc->status === 'selesai' && !auth()->user()->canManageDiscipline($academicYear)) ? 'disabled' : '' }}>
                                                            <div class="form-selectgroup-label d-flex align-items-center p-3">
                                                                <div class="me-3">
                                                                    <span class="form-selectgroup-check"></span>
                                                                </div>
                                                                <div>
                                                                    <div class="font-weight-medium text-warning fw-bold"><i class="ti ti-clock me-1"></i> Tercatat</div>
                                                                    <div class="text-muted small">Kasus baru dicatat di meja piket, menunggu tindak lanjut.</div>
                                                                </div>
                                                            </div>
                                                        </label>
                                                        <label class="form-selectgroup-item flex-fill">
                                                            <input type="radio" name="status" value="ditindaklanjuti" class="form-selectgroup-input" {{ $inc->status === 'ditindaklanjuti' ? 'checked' : '' }} {{ ($inc->status === 'selesai' && !auth()->user()->canManageDiscipline($academicYear)) ? 'disabled' : '' }}>
                                                            <div class="form-selectgroup-label d-flex align-items-center p-3">
                                                                <div class="me-3">
                                                                    <span class="form-selectgroup-check"></span>
                                                                </div>
                                                                <div>
                                                                    <div class="font-weight-medium text-info fw-bold"><i class="ti ti-arrow-forward me-1"></i> Ditindaklanjuti</div>
                                                                    <div class="text-muted small">Sedang dalam penanganan / telah dirujuk ke Wali Kelas / BK / Kesiswaan.</div>
                                                                </div>
                                                            </div>
                                                        </label>
                                                        <label class="form-selectgroup-item flex-fill">
                                                            <input type="radio" name="status" value="selesai" class="form-selectgroup-input" {{ $inc->status === 'selesai' ? 'checked' : '' }}>
                                                            <div class="form-selectgroup-label d-flex align-items-center p-3">
                                                                <div class="me-3">
                                                                    <span class="form-selectgroup-check"></span>
                                                                </div>
                                                                <div>
                                                                    <div class="font-weight-medium text-success fw-bold"><i class="ti ti-circle-check me-1"></i> Selesai</div>
                                                                    <div class="text-muted small">Pembinaan telah tuntas dan selesai.</div>
                                                                </div>
                                                            </div>
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-light py-2">
                                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                                                    @if($inc->status !== 'selesai' || auth()->user()->canManageDiscipline($academicYear))
                                                    <button type="submit" class="btn btn-primary shadow-sm">
                                                        <i class="ti ti-device-floppy me-1"></i> Simpan Status
                                                    </button>
                                                    @endif
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="ti ti-shield-check fs-1 d-block mb-1 text-success"></i>
                                Tidak ada catatan kasus atau pelanggaran siswa untuk filter yang dipilih.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($incidents->hasPages())
            <div class="card-footer py-2">
                {{ $incidents->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Modal Bukti Foto Kasus -->
<div class="modal fade" id="modalIncidentPhoto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light">
                <div>
                    <h5 class="modal-title fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                        <i class="ti ti-camera text-primary"></i>
                        <span>Bukti Foto Kejadian: <span id="modalStudentName" class="text-primary"></span></span>
                    </h5>
                    <div class="text-muted small mt-1">
                        Kategori: <span id="modalCategoryBadge" class="badge bg-danger-lt fw-bold"></span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-3 bg-dark-subtle">
                <img id="modalPhotoImage" src="#" alt="Bukti Foto" class="img-fluid rounded shadow-sm" style="max-height: 500px; width: auto; object-fit: contain;">
                <div class="p-3 bg-white text-start rounded mt-3 shadow-sm">
                    <label class="fw-bold small text-muted text-uppercase mb-1">Kronologi / Uraian:</label>
                    <p id="modalDescription" class="text-dark mb-0 small"></p>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <a id="modalDownloadBtn" href="#" target="_blank" download class="btn btn-outline-secondary btn-sm">
                    <i class="ti ti-download me-1"></i> Unduh Foto Asli
                </a>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function showIncidentPhoto(photoUrl, studentName, category, description) {
    document.getElementById('modalPhotoImage').src = photoUrl;
    document.getElementById('modalStudentName').textContent = studentName;
    document.getElementById('modalCategoryBadge').textContent = category;
    document.getElementById('modalDescription').textContent = description;
    document.getElementById('modalDownloadBtn').href = photoUrl;

    const modal = new bootstrap.Modal(document.getElementById('modalIncidentPhoto'));
    modal.show();
}
</script>
@endpush
@endsection
