@extends('layouts.app')

@section('title', 'Buku Pribadi Siswa Digital: ' . $student->name)

@section('content')
<div class="container-xl">
    <!-- Top Action Bar -->
    <div class="mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <a href="{{ url()->previous() ?: route('bk.referrals.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i> Kembali
        </a>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('bk.students.history.pdf', ['student' => $student, 'academic_year' => $academicYear]) }}" class="btn btn-sm btn-outline-danger shadow-xs">
                <i class="ti ti-file-text me-1"></i> Cetak Buku Pribadi (PDF)
            </a>
            <a href="{{ route('bk.services.create', ['student_id' => $student->id, 'academic_year' => $academicYear]) }}" class="btn btn-sm btn-primary shadow-xs">
                <i class="ti ti-plus me-1"></i> Catat Layanan BK untuk Siswa Ini
            </a>
        </div>
    </div>

    <!-- Header Profil Siswa (Hero Card) -->
    <div class="card shadow-sm border-0 mb-4 overflow-hidden">
        <div class="card-body p-4 position-relative" style="background: linear-gradient(135deg, #f8fafc 0%, #edf2f7 100%);">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <span class="avatar avatar-xl rounded-circle bg-primary text-white fw-bold fs-1 shadow-sm flex-shrink-0">
                        {{ strtoupper(substr($student->name, 0, 2)) }}
                    </span>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                            <span class="badge bg-primary text-white fw-semibold" style="letter-spacing: 0.05rem;">
                                BUKU PRIBADI SISWA DIGITAL
                            </span>
                            @if($student->status === 'aktif')
                                <span class="badge bg-success-lt text-success"><i class="ti ti-check me-1"></i>Siswa Aktif</span>
                            @elseif($student->status === 'lulus')
                                <span class="badge bg-info-lt text-info"><i class="ti ti-school me-1"></i>Alumni / Lulus</span>
                            @elseif(in_array($student->status, ['mutasi', 'keluar']))
                                <span class="badge bg-warning-lt text-warning"><i class="ti ti-arrow-right me-1"></i>Mutasi / Keluar</span>
                            @else
                                <span class="badge bg-secondary-lt text-secondary">{{ ucfirst($student->status) }}</span>
                            @endif
                        </div>
                        <h1 class="h2 fw-bold mb-1 text-dark">{{ $student->name }}</h1>
                        <div class="text-muted small d-flex flex-wrap align-items-center gap-3">
                            <span><i class="ti ti-id me-1"></i>NISN: <strong>{{ $student->nisn ?? '-' }}</strong></span>
                            <span><i class="ti ti-barcode me-1"></i>NIS: <strong>{{ $student->nis ?? '-' }}</strong></span>
                            <span>
                                <i class="ti ti-chalkboard me-1"></i>Kelas (TP {{ $academicYear }}): 
                                <strong>{{ $currentClassroom?->name ?? ($student->classroom?->name ?? 'Belum terdaftar di rombel') }}</strong>
                                @if($student->classroom && $currentClassroom && $currentClassroom->id !== $student->classroom->id)
                                    <span class="badge bg-secondary-lt text-muted ms-1" title="Kelas terkini siswa di rombel aktif">(Terkini: {{ $student->classroom->name }})</span>
                                @endif
                            </span>
                            <span><i class="ti ti-gender-bigender me-1"></i>{{ $student->gender_label }}</span>
                        </div>
                    </div>
                </div>
                <div class="text-md-end">
                    <div class="text-muted small">Tahun Ajaran Aktif</div>
                    <span class="badge bg-blue text-white fs-3 px-3 py-1 shadow-xs">TP {{ $academicYear }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Antarmuka Berbasis Tab Buku Pribadi Siswa -->
    <div class="card shadow-sm border-0">
        <div class="card-header border-bottom-0 pb-0">
            <ul class="nav nav-tabs card-header-tabs overflow-x-auto flex-nowrap" data-bs-toggle="tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <a href="#tab-biodata" class="nav-link active" data-bs-toggle="tab" aria-selected="true" role="tab">
                        <i class="ti ti-user me-2"></i> 1. Biodata &amp; Keluarga
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a href="#tab-akademik" class="nav-link" data-bs-toggle="tab" aria-selected="false" role="tab">
                        <i class="ti ti-school me-2"></i> 2. Riwayat Kelas &amp; Mutasi
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a href="#tab-minat" class="nav-link" data-bs-toggle="tab" aria-selected="false" role="tab">
                        <i class="ti ti-compass me-2"></i> 3. Minat &amp; Karier
                        @if($careerProfile && $careerProfile->post_graduation_plan !== 'belum_memutuskan')
                            <span class="badge {{ $careerProfile->post_graduation_plan_badge_lt }} ms-1">{{ $careerProfile->post_graduation_plan_label }}</span>
                        @endif
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a href="#tab-prestasi" class="nav-link" data-bs-toggle="tab" aria-selected="false" role="tab">
                        <i class="ti ti-trophy me-2"></i> 4. Prestasi &amp; Kegiatan <span class="badge bg-warning-lt ms-1">{{ $achievements->count() }}</span>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a href="#tab-perkembangan" class="nav-link" data-bs-toggle="tab" aria-selected="false" role="tab">
                        <i class="ti ti-chart-line me-2"></i> 5. Perkembangan &amp; Visitasi <span class="badge bg-teal-lt ms-1">{{ $developmentNotes->count() + $visitations->count() }}</span>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a href="#tab-layanan" class="nav-link" data-bs-toggle="tab" aria-selected="false" role="tab">
                        <i class="ti ti-notes me-2"></i> 6. Layanan BK <span class="badge bg-primary-lt ms-1">{{ $services->count() }}</span>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a href="#tab-disiplin" class="nav-link" data-bs-toggle="tab" aria-selected="false" role="tab">
                        <i class="ti ti-shield-alert me-2"></i> 7. Kedisiplinan &amp; Perizinan <span class="badge bg-danger-lt ms-1">{{ $allIncidents->count() + $permits->count() }}</span>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a href="#tab-dokumen" class="nav-link" data-bs-toggle="tab" aria-selected="false" role="tab">
                        <i class="ti ti-files me-2"></i> 8. Dokumen Pendukung <span class="badge bg-purple-lt ms-1">{{ $documents->count() }}</span>
                    </a>
                </li>
            </ul>
        </div>
        <div class="card-body p-4">
            <div class="tab-content">
                <!-- ======================================================== -->
                <!-- TAB 1: BIODATA DAN KELUARGA (Sumber: students)          -->
                <!-- ======================================================== -->
                <div class="tab-pane active show" id="tab-biodata" role="tabpanel">
                    <div class="row g-4">
                        <!-- Sub-Card 1: Data Identitas Pribadi Siswa -->
                        <div class="col-lg-6">
                            <div class="card border shadow-none h-100">
                                <div class="card-header bg-light py-2">
                                    <h4 class="card-title text-dark mb-0 fw-bold">
                                        <i class="ti ti-id me-2 text-primary"></i> Data Pribadi Siswa
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm table-borderless mb-0">
                                        <tbody>
                                            <tr>
                                                <td class="text-muted" style="width: 38%;">Nama Lengkap</td>
                                                <td class="fw-bold text-dark">: {{ $student->name }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">NISN / NIS</td>
                                                <td class="fw-semibold text-dark">: {{ $student->nisn ?? '-' }} / {{ $student->nis ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">NIK Siswa</td>
                                                <td class="text-dark">: {{ $student->nik ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">No. Kartu Keluarga (KK)</td>
                                                <td class="text-dark">: {{ $student->family_card_no ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">No. Akta Kelahiran</td>
                                                <td class="text-dark">: {{ $student->birth_certificate_no ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Tempat, Tanggal Lahir</td>
                                                <td class="text-dark">: {{ $student->formatted_birth ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Jenis Kelamin</td>
                                                <td class="text-dark">: {{ $student->gender_label }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Agama</td>
                                                <td class="text-dark">: {{ $student->religion ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Anak Ke / Jumlah Sdr</td>
                                                <td class="text-dark">: Anak ke-{{ $student->child_order ?? '-' }} dari {{ $student->siblings_count ?? '-' }} bersaudara</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Sub-Card 2: Kondisi Fisik, Kontak & Domisili -->
                        <div class="col-lg-6">
                            <div class="card border shadow-none h-100">
                                <div class="card-header bg-light py-2">
                                    <h4 class="card-title text-dark mb-0 fw-bold">
                                        <i class="ti ti-map-pin me-2 text-primary"></i> Kondisi Fisik &amp; Domisili
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm table-borderless mb-0">
                                        <tbody>
                                            <tr>
                                                <td class="text-muted" style="width: 38%;">Berat / Tinggi Badan</td>
                                                <td class="text-dark">: {{ $student->weight_kg ? $student->weight_kg . ' kg' : '-' }} / {{ $student->height_cm ? $student->height_cm . ' cm' : '-' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Lingkar Kepala</td>
                                                <td class="text-dark">: {{ $student->head_circumference_cm ? $student->head_circumference_cm . ' cm' : '-' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Kebutuhan Khusus</td>
                                                <td class="text-dark">: {{ $student->special_needs ?? 'Tidak ada' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">No. Telepon Siswa</td>
                                                <td class="text-dark">: {{ $student->phone_student ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Email Siswa</td>
                                                <td class="text-dark">: {{ $student->email ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Alamat Tempat Tinggal</td>
                                                <td class="text-dark">: {{ $student->address ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Kode Pos / Jenis Tinggal</td>
                                                <td class="text-dark">: {{ $student->postal_code ?? '-' }} / {{ $student->residence_type ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Alat Transportasi</td>
                                                <td class="text-dark">: {{ $student->transportation ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Jarak ke Sekolah</td>
                                                <td class="text-dark">: {{ $student->distance_to_school_km ? $student->distance_to_school_km . ' km' : '-' }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Sub-Card 3: Data Orang Tua & Wali Siswa -->
                        <div class="col-12">
                            <div class="card border shadow-none">
                                <div class="card-header bg-light py-2">
                                    <h4 class="card-title text-dark mb-0 fw-bold">
                                        <i class="ti ti-users me-2 text-primary"></i> Data Orang Tua &amp; Wali
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="row g-4">
                                        <!-- Data Ayah -->
                                        <div class="col-md-4">
                                            <div class="p-3 bg-light rounded border h-100">
                                                <div class="fw-bold text-primary mb-2 pb-1 border-bottom">
                                                    <i class="ti ti-user me-1"></i> Data Ayah Kandung
                                                </div>
                                                <div class="small mb-1"><span class="text-muted">Nama:</span> <strong class="text-dark">{{ $student->father_name ?? '-' }}</strong></div>
                                                <div class="small mb-1"><span class="text-muted">Tahun Lahir:</span> {{ $student->father_birth_year ?? '-' }}</div>
                                                <div class="small mb-1"><span class="text-muted">NIK Ayah:</span> {{ $student->father_nik ?? '-' }}</div>
                                                <div class="small mb-1"><span class="text-muted">Pendidikan:</span> {{ $student->father_education ?? '-' }}</div>
                                                <div class="small mb-1"><span class="text-muted">Pekerjaan:</span> {{ $student->father_job ?? '-' }}</div>
                                                <div class="small mb-1"><span class="text-muted">Penghasilan:</span> {{ $student->father_income ?? '-' }}</div>
                                                <div class="small"><span class="text-muted">No. Telepon:</span> {{ $student->father_phone ?? '-' }}</div>
                                            </div>
                                        </div>

                                        <!-- Data Ibu -->
                                        <div class="col-md-4">
                                            <div class="p-3 bg-light rounded border h-100">
                                                <div class="fw-bold text-pink mb-2 pb-1 border-bottom">
                                                    <i class="ti ti-user me-1"></i> Data Ibu Kandung
                                                </div>
                                                <div class="small mb-1"><span class="text-muted">Nama:</span> <strong class="text-dark">{{ $student->mother_name ?? '-' }}</strong></div>
                                                <div class="small mb-1"><span class="text-muted">Tahun Lahir:</span> {{ $student->mother_birth_year ?? '-' }}</div>
                                                <div class="small mb-1"><span class="text-muted">NIK Ibu:</span> {{ $student->mother_nik ?? '-' }}</div>
                                                <div class="small mb-1"><span class="text-muted">Pendidikan:</span> {{ $student->mother_education ?? '-' }}</div>
                                                <div class="small mb-1"><span class="text-muted">Pekerjaan:</span> {{ $student->mother_job ?? '-' }}</div>
                                                <div class="small mb-1"><span class="text-muted">Penghasilan:</span> {{ $student->mother_income ?? '-' }}</div>
                                                <div class="small"><span class="text-muted">No. Telepon:</span> {{ $student->mother_phone ?? '-' }}</div>
                                            </div>
                                        </div>

                                        <!-- Data Wali -->
                                        <div class="col-md-4">
                                            <div class="p-3 bg-light rounded border h-100">
                                                <div class="fw-bold text-teal mb-2 pb-1 border-bottom">
                                                    <i class="ti ti-user-check me-1"></i> Data Wali (Jika Ada)
                                                </div>
                                                <div class="small mb-1"><span class="text-muted">Nama:</span> <strong class="text-dark">{{ $student->guardian_name ?? '-' }}</strong></div>
                                                <div class="small mb-1"><span class="text-muted">Tahun Lahir:</span> {{ $student->guardian_birth_year ?? '-' }}</div>
                                                <div class="small mb-1"><span class="text-muted">NIK Wali:</span> {{ $student->guardian_nik ?? '-' }}</div>
                                                <div class="small mb-1"><span class="text-muted">Pendidikan:</span> {{ $student->guardian_education ?? '-' }}</div>
                                                <div class="small mb-1"><span class="text-muted">Pekerjaan:</span> {{ $student->guardian_job ?? '-' }}</div>
                                                <div class="small mb-1"><span class="text-muted">Penghasilan:</span> {{ $student->guardian_income ?? '-' }}</div>
                                                <div class="small"><span class="text-muted">No. Telepon:</span> {{ $student->guardian_phone ?? '-' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sub-Card 4: Jaminan Sosial & Bantuan Kesejahteraan -->
                        <div class="col-12">
                            <div class="card border shadow-none">
                                <div class="card-header bg-light py-2">
                                    <h4 class="card-title text-dark mb-0 fw-bold">
                                        <i class="ti ti-heart-handshake me-2 text-primary"></i> Data Kesejahteraan &amp; Bantuan Sosial
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-sm-6 col-md-3">
                                            <span class="text-muted d-block small">Penerima KIP / PIP</span>
                                            <span class="badge {{ $student->kip_recipient || $student->is_pip_eligible ? 'bg-success-lt text-success' : 'bg-secondary-lt text-muted' }}">
                                                {{ $student->kip_recipient || $student->is_pip_eligible ? 'Penerima Bantuan' : 'Bukan Penerima' }}
                                            </span>
                                            @if($student->kip_number)
                                                <div class="small mt-1 text-dark">No. KIP: <strong>{{ $student->kip_number }}</strong></div>
                                            @endif
                                        </div>
                                        <div class="col-sm-6 col-md-3">
                                            <span class="text-muted d-block small">Penerima KKS / KPS</span>
                                            <span class="badge {{ $student->kps_recipient || $student->kks_number ? 'bg-info-lt text-info' : 'bg-secondary-lt text-muted' }}">
                                                {{ $student->kps_recipient || $student->kks_number ? 'Ya' : 'Tidak' }}
                                            </span>
                                            @if($student->kks_number || $student->kps_number)
                                                <div class="small mt-1 text-dark">No: {{ $student->kks_number ?: $student->kps_number }}</div>
                                            @endif
                                        </div>
                                        <div class="col-sm-6 col-md-3">
                                            <span class="text-muted d-block small">Bank Penyalur</span>
                                            <span class="fw-semibold text-dark">{{ $student->bank_name ?? '-' }}</span>
                                        </div>
                                        <div class="col-sm-6 col-md-3">
                                            <span class="text-muted d-block small">Nomor Rekening Bank</span>
                                            <span class="fw-semibold text-dark">{{ $student->bank_account_no ?? '-' }}</span>
                                            @if($student->bank_account_name)
                                                <div class="small text-muted">a.n. {{ $student->bank_account_name }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ======================================================== -->
                <!-- TAB 2: RIWAYAT KELAS & PENDIDIKAN                       -->
                <!-- ======================================================== -->
                <div class="tab-pane" id="tab-akademik" role="tabpanel">
                    <!-- Pendidikan Sebelumnya -->
                    <div class="card border shadow-none mb-4">
                        <div class="card-header bg-light py-2">
                            <h4 class="card-title text-dark mb-0 fw-bold">
                                <i class="ti ti-school me-2 text-primary"></i> Data Sekolah Asal &amp; Kelulusan Sebelumnya
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-sm-6 col-md-3">
                                    <span class="text-muted d-block small">Sekolah Asal (SMP/MTs)</span>
                                    <span class="fw-bold text-dark">{{ $student->school_origin ?? '-' }}</span>
                                </div>
                                <div class="col-sm-6 col-md-2">
                                    <span class="text-muted d-block small">Tahun Masuk</span>
                                    <span class="fw-semibold text-dark">{{ $student->entry_year ?? '-' }}</span>
                                </div>
                                <div class="col-sm-6 col-md-2">
                                    <span class="text-muted d-block small">No. Peserta UN</span>
                                    <span class="text-dark">{{ $student->national_exam_no ?? '-' }}</span>
                                </div>
                                <div class="col-sm-6 col-md-3">
                                    <span class="text-muted d-block small">No. Seri Ijazah SMP</span>
                                    <span class="text-dark">{{ $student->diploma_serial_no ?? '-' }}</span>
                                </div>
                                <div class="col-sm-6 col-md-2">
                                    <span class="text-muted d-block small">No. SKHUN</span>
                                    <span class="text-dark">{{ $student->skhun_no ?? '-' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Riwayat Penempatan Rombel per Tahun Ajaran (student_classrooms) -->
                    <div class="card border shadow-none mb-4">
                        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                            <h4 class="card-title text-dark mb-0 fw-bold">
                                <i class="ti ti-history me-2 text-primary"></i> Riwayat Penempatan Rombel Lintas Tahun Pelajaran
                            </h4>
                            <span class="badge bg-secondary-lt">{{ $student->studentClassrooms->count() }} Tahun Terdata</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table table-hover">
                                <thead>
                                    <tr>
                                        <th>Tahun Pelajaran</th>
                                        <th>Rombel / Kelas</th>
                                        <th>Konsentrasi Keahlian / Jurusan</th>
                                        <th>Status Kenaikan</th>
                                        <th>Mengulang</th>
                                        <th>Catatan Penempatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($student->studentClassrooms as $sc)
                                        <tr>
                                            <td class="fw-bold text-primary">
                                                TP {{ $sc->academicYear?->name ?? ($sc->academic_year_name ?? '-') }}
                                            </td>
                                            <td class="fw-bold text-dark">
                                                {{ $sc->classroom?->name ?? ($sc->classroom_name ?? '-') }}
                                            </td>
                                            <td>
                                                {{ $sc->classroom?->department?->name ?? '-' }}
                                            </td>
                                            <td>
                                                @if($sc->status === 'aktif')
                                                    <span class="badge bg-success-lt text-success">Aktif</span>
                                                @elseif($sc->status === 'naik_kelas')
                                                    <span class="badge bg-primary-lt text-primary">Naik Kelas</span>
                                                @elseif($sc->status === 'tinggal_kelas')
                                                    <span class="badge bg-danger-lt text-danger">Tinggal Kelas</span>
                                                @elseif($sc->status === 'lulus')
                                                    <span class="badge bg-info-lt text-info">Lulus</span>
                                                @else
                                                    <span class="badge bg-secondary-lt text-secondary">{{ ucfirst($sc->status) }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($sc->is_repeating)
                                                    <span class="badge bg-danger-lt text-danger">Ya (Mengulang)</span>
                                                @else
                                                    <span class="text-muted small">Tidak</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="small text-muted">{{ $sc->notes ?? '-' }}</span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">
                                                Belum ada arsip riwayat rombel terdata pada sistem.
                                                @if($student->classroom)
                                                    <div class="small mt-1 text-dark">Kelas saat ini: <strong>{{ $student->classroom->name }}</strong></div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Riwayat Mutasi Siswa (student_mutations) -->
                    @if($student->mutations->isNotEmpty())
                        <div class="card border border-warning-subtle shadow-none">
                            <div class="card-header bg-warning-lt py-2">
                                <h4 class="card-title text-warning fw-bold mb-0">
                                    <i class="ti ti-arrow-forward me-2"></i> Riwayat Mutasi / Kepindahan Siswa
                                </h4>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-vcenter card-table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Jenis Mutasi</th>
                                            <th>Alasan / Kategori</th>
                                            <th>Sekolah Tujuan</th>
                                            <th>No. Surat</th>
                                            <th>Petugas Pencatat</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($student->mutations as $mut)
                                            <tr>
                                                <td class="fw-bold">{{ $mut->mutation_date?->format('d/m/Y') }}</td>
                                                <td><span class="badge bg-warning text-dark">{{ $mut->type_label }}</span></td>
                                                <td>
                                                    <div class="fw-semibold">{{ ucfirst(str_replace('_', ' ', $mut->reason_category)) }}</div>
                                                    <div class="small text-muted">{{ $mut->reason_description }}</div>
                                                </td>
                                                <td>{{ $mut->target_school ?? '-' }}</td>
                                                <td>{{ $mut->letter_number ?? '-' }}</td>
                                                <td>{{ $mut->recordedBy?->name ?? '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- ======================================================== -->
                <!-- TAB 3: MINAT, BAKAT & RENCANA KARIER SISWA              -->
                <!-- ======================================================== -->
                <div class="tab-pane" id="tab-minat" role="tabpanel">
                    <!-- Header Action Card -->
                    <div class="card border shadow-none mb-3 bg-light-subtle">
                        <div class="card-body p-3">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        <h4 class="card-title text-dark mb-0 fw-bold">
                                            <i class="ti ti-compass text-primary me-1"></i> Peminatan &amp; Rencana Karier Siswa
                                        </h4>
                                        <span class="badge bg-blue text-white">TP {{ $academicYear }}</span>
                                    </div>
                                    <p class="text-muted small mb-0 mt-1">
                                        Pemetaan potensi diri, preferensi studi, serta target karier setelah lulus (Bekerja, Melanjutkan Pendidikan, atau Wirausaha).
                                    </p>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modal-career-profile">
                                        <i class="ti ti-edit me-1"></i> {{ $careerProfile ? 'Perbarui Profil Karier' : 'Isi Profil Karier' }}
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#modal-assessment">
                                        <i class="ti ti-plus me-1"></i> Tambah Hasil Asesmen
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Summary & Status BMW Card -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <div class="card border shadow-none h-100">
                                <div class="card-body p-3">
                                    <div class="text-muted small mb-1">Rencana Pasca Lulus (BMW)</div>
                                    <div class="d-flex align-items-center gap-2">
                                        @if($careerProfile && $careerProfile->post_graduation_plan === 'bekerja')
                                            <span class="avatar avatar-sm bg-success text-white rounded"><i class="ti ti-briefcase"></i></span>
                                            <div>
                                                <div class="fw-bold text-success fs-3">Bekerja</div>
                                                <div class="text-muted small">Target DUDI / Industri</div>
                                            </div>
                                        @elseif($careerProfile && $careerProfile->post_graduation_plan === 'melanjutkan')
                                            <span class="avatar avatar-sm bg-azure text-white rounded"><i class="ti ti-school"></i></span>
                                            <div>
                                                <div class="fw-bold text-azure fs-3">Melanjutkan</div>
                                                <div class="text-muted small">Perguruan Tinggi / Politeknik</div>
                                            </div>
                                        @elseif($careerProfile && $careerProfile->post_graduation_plan === 'wirausaha')
                                            <span class="avatar avatar-sm bg-warning text-dark rounded"><i class="ti ti-building-store"></i></span>
                                            <div>
                                                <div class="fw-bold text-warning fs-3">Wirausaha</div>
                                                <div class="text-muted small">Rintisan Usaha Mandiri</div>
                                            </div>
                                        @else
                                            <span class="avatar avatar-sm bg-secondary text-white rounded"><i class="ti ti-help"></i></span>
                                            <div>
                                                <div class="fw-bold text-secondary fs-3">Belum Memutuskan</div>
                                                <div class="text-muted small">Dalam Tahap Eksplorasi</div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border shadow-none h-100">
                                <div class="card-body p-3">
                                    <div class="text-muted small mb-1">Cita-cita / Impian Profesi</div>
                                    <div class="fw-bold text-dark fs-3">
                                        {{ $careerProfile?->career_aspirations ?: '-' }}
                                    </div>
                                    <div class="text-muted small">Aspirasi profesi siswa</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border shadow-none h-100">
                                <div class="card-body p-3">
                                    <div class="text-muted small mb-1">Riwayat Asesmen Minat Bakat</div>
                                    <div class="fw-bold text-dark fs-3">
                                        {{ $assessments->count() }} Tes
                                    </div>
                                    <div class="text-muted small">Tercatat pada TP {{ $academicYear }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detail Profil Minat, Bakat & Rincian Rencana -->
                    <div class="row g-3 mb-3">
                        <!-- Sub-Card 1: Minat & Bakat -->
                        <div class="col-lg-6">
                            <div class="card border shadow-none h-100">
                                <div class="card-header bg-light py-2">
                                    <h4 class="card-title text-dark mb-0 fw-bold">
                                        <i class="ti ti-palette me-2 text-primary"></i> Potensi Diri, Minat &amp; Bakat
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm table-borderless mb-0">
                                        <tbody>
                                            <tr>
                                                <td class="text-muted" style="width: 38%;">Hobi &amp; Kegemaran</td>
                                                <td class="text-dark fw-semibold">: {{ $careerProfile?->hobbies ?: '-' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Bakat / Potensi Khusus</td>
                                                <td class="text-dark fw-semibold">: {{ $careerProfile?->talents ?: '-' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Keahlian Ingin Diperdalam</td>
                                                <td class="text-dark">: {{ $careerProfile?->skills_to_develop ?: '-' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Mata Pelajaran Disukai</td>
                                                <td class="text-dark">: {{ $careerProfile?->favorite_subjects ?: '-' }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Sub-Card 2: Target Spesifik Pasca Lulus -->
                        <div class="col-lg-6">
                            <div class="card border shadow-none h-100">
                                <div class="card-header bg-light py-2">
                                    <h4 class="card-title text-dark mb-0 fw-bold">
                                        <i class="ti ti-target me-2 text-success"></i> Rencana Spesifik Pasca Lulus
                                    </h4>
                                </div>
                                <div class="card-body">
                                    @if($careerProfile && $careerProfile->post_graduation_plan === 'bekerja')
                                        <div class="alert alert-success-lt p-2 mb-2">
                                            <i class="ti ti-briefcase me-1"></i> Rencana Jalur: <strong>Bekerja di Industri / DUDI</strong>
                                        </div>
                                        <table class="table table-sm table-borderless mb-0">
                                            <tbody>
                                                <tr>
                                                    <td class="text-muted" style="width: 38%;">Target Industri / DUDI</td>
                                                    <td class="text-dark fw-semibold">: {{ $careerProfile->target_industry ?: '-' }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Target Posisi / Pekerjaan</td>
                                                    <td class="text-dark fw-semibold">: {{ $careerProfile->target_job_position ?: '-' }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    @elseif($careerProfile && $careerProfile->post_graduation_plan === 'melanjutkan')
                                        <div class="alert alert-info-lt p-2 mb-2">
                                            <i class="ti ti-school me-1"></i> Rencana Jalur: <strong>Melanjutkan Pendidikan (Kuliah)</strong>
                                        </div>
                                        <table class="table table-sm table-borderless mb-0">
                                            <tbody>
                                                <tr>
                                                    <td class="text-muted" style="width: 38%;">Target Kampus / PT</td>
                                                    <td class="text-dark fw-semibold">: {{ $careerProfile->target_university ?: '-' }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Target Program Studi</td>
                                                    <td class="text-dark fw-semibold">: {{ $careerProfile->target_study_program ?: '-' }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    @elseif($careerProfile && $careerProfile->post_graduation_plan === 'wirausaha')
                                        <div class="alert alert-warning-lt p-2 mb-2">
                                            <i class="ti ti-building-store me-1"></i> Rencana Jalur: <strong>Merintis Usaha Mandiri (Wirausaha)</strong>
                                        </div>
                                        <table class="table table-sm table-borderless mb-0">
                                            <tbody>
                                                <tr>
                                                    <td class="text-muted" style="width: 38%;">Bidang / Jenis Usaha</td>
                                                    <td class="text-dark fw-semibold">: {{ $careerProfile->entrepreneurship_type ?: '-' }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Rencana Ide Bisnis</td>
                                                    <td class="text-dark">: {{ $careerProfile->business_plan_summary ?: '-' }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    @else
                                        <div class="text-center py-4 text-muted">
                                            <i class="ti ti-compass fs-1 d-block mb-2 text-muted"></i>
                                            Belum ada rincian rencana spesifik. Silakan klik tombol <strong>{{ $careerProfile ? 'Perbarui Profil Karier' : 'Isi Profil Karier' }}</strong> di atas untuk memetakan arah minat siswa.
                                        </div>
                                    @endif

                                    @if($careerProfile?->notes)
                                        <div class="mt-3 pt-2 border-top">
                                            <div class="text-muted small">Catatan Pembimbingan:</div>
                                            <div class="small text-dark mt-1" style="white-space: pre-line;">{{ $careerProfile->notes }}</div>
                                        </div>
                                    @endif

                                    @if($careerProfile?->updater || $careerProfile?->recorder)
                                        <div class="mt-2 text-muted" style="font-size: 0.72rem;">
                                            <i class="ti ti-user me-1"></i> Terakhir diperbarui oleh {{ $careerProfile->updater?->name ?? $careerProfile->recorder?->name }} ({{ $careerProfile->updated_at->format('d/m/Y H:i') }})
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Riwayat Asesmen Minat dan Bakat -->
                    <div class="card border shadow-none mb-3">
                        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                            <h4 class="card-title text-dark mb-0 fw-bold">
                                <i class="ti ti-clipboard-check me-2 text-indigo"></i> Riwayat Tes &amp; Asesmen Minat-Bakat ({{ $assessments->count() }})
                            </h4>
                            <button type="button" class="btn btn-xs btn-outline-success" data-bs-toggle="modal" data-bs-target="#modal-assessment">
                                <i class="ti ti-plus me-1"></i> Tambah Asesmen
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table table-hover">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Nama Asesmen / Tes</th>
                                        <th>Asesor / Lembaga</th>
                                        <th>Ringkasan Hasil</th>
                                        <th>Rekomendasi Jalur Karier</th>
                                        <th>Pencatat</th>
                                        <th class="w-1">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($assessments as $as)
                                        <tr>
                                            <td class="text-nowrap fw-bold">{{ $as->assessment_date?->format('d/m/Y') }}</td>
                                            <td class="fw-bold text-dark">{{ $as->assessment_name }}</td>
                                            <td class="text-muted small">{{ $as->assessor_name ?: '-' }}</td>
                                            <td>
                                                <div class="small text-dark" style="white-space: pre-line;">{{ $as->result_summary }}</div>
                                            </td>
                                            <td>
                                                @if($as->recommended_career_paths)
                                                    <span class="badge bg-teal-lt text-teal text-wrap text-start">{{ $as->recommended_career_paths }}</span>
                                                @else
                                                    <span class="text-muted small">-</span>
                                                @endif
                                            </td>
                                            <td class="small text-muted">{{ $as->recorder?->name ?? '-' }}</td>
                                            <td>
                                                <form action="{{ route('bk.students.assessments.destroy', ['student' => $student, 'assessment' => $as]) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data asesmen ini?');" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-xs btn-outline-danger" title="Hapus Asesmen">
                                                        <i class="ti ti-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">
                                                <i class="ti ti-file-info fs-1 d-block mb-1 text-muted"></i>
                                                Belum ada catatan riwayat asesmen minat dan bakat pada tahun ajaran {{ $academicYear }}.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ======================================================== -->
                <!-- TAB 4: PRESTASI & KEGIATAN SISWA                        -->
                <!-- ======================================================== -->
                <div class="tab-pane" id="tab-prestasi" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h4 class="fw-bold text-dark mb-1">
                                <i class="ti ti-trophy me-2 text-warning"></i> Rekam Jejak Prestasi &amp; Kegiatan Siswa ({{ $achievements->count() }})
                            </h4>
                            <p class="text-muted small mb-0">Perolehan prestasi lomba, piagam penghargaan, kepengurusan organisasi, dan ekstrakurikuler TP {{ $academicYear }}.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modal-add-achievement">
                            <i class="ti ti-plus me-1"></i> Catat Prestasi / Kegiatan
                        </button>
                    </div>

                    @if($achievements->isEmpty())
                        <div class="card border border-dashed shadow-none text-center py-5">
                            <div class="card-body">
                                <div class="avatar avatar-xl rounded-circle bg-warning-lt text-warning mx-auto mb-3">
                                    <i class="ti ti-trophy fs-1"></i>
                                </div>
                                <h4 class="fw-bold text-dark mb-1">Belum Ada Rekam Jejak Prestasi atau Kegiatan</h4>
                                <p class="text-muted mx-auto mb-3" style="max-width: 520px;">
                                    Catat prestasi akademik/non-akademik, keikutsertaan lomba, atau keaktifan organisasi siswa untuk melengkapi profil rekam jejak pada Buku Pribadi.
                                </p>
                                <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modal-add-achievement">
                                    <i class="ti ti-plus me-1"></i> Tambah Prestasi / Kegiatan Pertama
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="table-responsive border rounded">
                            <table class="table table-vcenter table-hover card-table">
                                <thead>
                                    <tr>
                                        <th style="width: 110px;">Tanggal</th>
                                        <th style="width: 140px;">Tipe / Kategori</th>
                                        <th>Nama Prestasi / Kegiatan</th>
                                        <th style="width: 150px;">Tingkat</th>
                                        <th style="width: 140px;">Hasil / Peran</th>
                                        <th>Penyelenggara / No. Sertifikat</th>
                                        <th style="width: 130px;">Pencatat</th>
                                        <th style="width: 80px;" class="text-end">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($achievements as $ach)
                                        <tr>
                                            <td class="fw-bold text-dark">{{ $ach->event_date->format('d/m/Y') }}</td>
                                            <td>
                                                <span class="badge {{ $ach->type === 'prestasi' ? 'bg-warning-lt text-warning' : 'bg-azure-lt text-azure' }} mb-1">
                                                    {{ $ach->type_label }}
                                                </span>
                                                <div class="text-muted small">{{ $ach->category_label }}</div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark">{{ $ach->title }}</div>
                                                @if($ach->notes)
                                                    <div class="text-muted small mt-1">{{ $ach->notes }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge {{ $ach->level_badge_class }}">{{ $ach->level_label }}</span>
                                            </td>
                                            <td>
                                                <span class="fw-bold text-dark">{{ $ach->achievement_result ?: '-' }}</span>
                                            </td>
                                            <td>
                                                <div class="text-dark small">{{ $ach->organizer ?: '-' }}</div>
                                                @if($ach->certificate_number)
                                                    <div class="text-muted small mt-1"><i class="ti ti-certificate me-1"></i>{{ $ach->certificate_number }}</div>
                                                @endif
                                            </td>
                                            <td class="text-muted small">{{ $ach->recorder?->name ?? '-' }}</td>
                                            <td class="text-end">
                                                <form action="{{ route('bk.students.achievements.destroy', [$student, $ach]) }}" method="POST" onsubmit="return confirm('Hapus data prestasi ini?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-xs btn-outline-danger" title="Hapus Prestasi">
                                                        <i class="ti ti-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <!-- ======================================================== -->
                <!-- TAB 5: CATATAN OBSERVASI PERKEMBANGAN SISWA NON-KASUS   -->
                <!-- ======================================================== -->
                <div class="tab-pane" id="tab-perkembangan" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h4 class="fw-bold text-dark mb-1">
                                <i class="ti ti-chart-line me-2 text-teal"></i> Catatan Observasi Perkembangan Siswa Non-Kasus ({{ $developmentNotes->count() }})
                            </h4>
                            <p class="text-muted small mb-0">Observasi kepribadian positif, kebiasaan belajar, kemandirian, dan tindak lanjut pembinaan TP {{ $academicYear }}.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-teal text-white" data-bs-toggle="modal" data-bs-target="#modal-add-development-note">
                            <i class="ti ti-plus me-1"></i> Tambah Catatan Perkembangan
                        </button>
                    </div>

                    <div class="alert alert-info bg-azure-lt border-0 d-flex align-items-center py-2 px-3 mb-3" role="alert">
                        <i class="ti ti-info-circle fs-3 me-2 text-primary"></i>
                        <div class="small text-dark">
                            <strong>Pemisahan Akses Catatan:</strong> Bagian ini dikhususkan untuk observasi perkembangan positif dan rekomendasi pembinaan umum non-kasus. Catatan konseling rahasia (confidential) tersimpan secara terpisah dan terlindungi pada Tab Layanan BK.
                        </div>
                    </div>

                    @if($developmentNotes->isEmpty())
                        <div class="card border border-dashed shadow-none text-center py-5">
                            <div class="card-body">
                                <div class="avatar avatar-xl rounded-circle bg-teal-lt text-teal mx-auto mb-3">
                                    <i class="ti ti-chart-line fs-1"></i>
                                </div>
                                <h4 class="fw-bold text-dark mb-1">Belum Ada Catatan Observasi Perkembangan</h4>
                                <p class="text-muted mx-auto mb-3" style="max-width: 520px;">
                                    Catat observasi berkala mengenai sikap belajar, kedisiplinan positif, interaksi pertemanan, dan arahan perkembangan siswa di luar kejadian pelanggaran.
                                </p>
                                <button type="button" class="btn btn-sm btn-outline-teal" data-bs-toggle="modal" data-bs-target="#modal-add-development-note">
                                    <i class="ti ti-plus me-1"></i> Tambah Catatan Pertama
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="row g-3">
                            @foreach($developmentNotes as $note)
                                <div class="col-12">
                                    <div class="card border shadow-xs">
                                        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge {{ $note->category_badge_class }}">{{ $note->category_label }}</span>
                                                <span class="text-dark fw-bold">{{ $note->title }}</span>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="text-muted small"><i class="ti ti-calendar me-1"></i>{{ $note->note_date->format('d/m/Y') }}</span>
                                                <form action="{{ route('bk.students.development-notes.destroy', [$student, $note]) }}" method="POST" onsubmit="return confirm('Hapus catatan perkembangan ini?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-xs btn-outline-danger" title="Hapus Catatan">
                                                        <i class="ti ti-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        <div class="card-body py-3">
                                            <p class="text-dark mb-2" style="white-space: pre-line;">{{ $note->description }}</p>
                                            @if($note->follow_up_recommendation)
                                                <div class="p-2 bg-light rounded border-start border-3 border-teal mt-2">
                                                    <div class="fw-bold text-teal small"><i class="ti ti-bulb me-1"></i>Rekomendasi Tindak Lanjut:</div>
                                                    <div class="text-dark small" style="white-space: pre-line;">{{ $note->follow_up_recommendation }}</div>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="card-footer bg-white py-1 px-3 d-flex justify-content-between align-items-center">
                                            <span class="text-muted small"><i class="ti ti-user me-1"></i>Dicatat oleh: {{ $note->recorder?->name ?? 'Guru BK' }}</span>
                                            <span class="text-muted small">{{ $note->created_at->format('d/m/Y H:i') }} WIB</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <!-- Sub-Card: Riwayat Visitasi & Pemantauan Siswa (Home Visit & Monitoring) -->
                    <div class="card border shadow-none mt-4">
                        <div class="card-header bg-teal-lt py-2 d-flex justify-content-between align-items-center">
                            <h4 class="card-title text-teal mb-0 fw-bold">
                                <i class="ti ti-home-heart me-2"></i> Riwayat Visitasi &amp; Pemantauan Siswa ({{ $visitations->count() }})
                            </h4>
                            <a href="{{ route('visitations.create', ['student_id' => $student->id, 'academic_year' => $academicYear]) }}" class="btn btn-xs btn-teal text-white">
                                <i class="ti ti-plus me-1"></i> Jadwalkan Visitasi / Pemantauan
                            </a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th style="width: 120px;">Tanggal</th>
                                        <th>Jenis Kegiatan</th>
                                        <th>Petugas Ditugaskan</th>
                                        <th>Alasan &amp; Ringkasan Hasil</th>
                                        <th style="width: 120px;" class="text-center">Status</th>
                                        <th style="width: 90px;" class="text-end">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($visitations as $visit)
                                        <tr>
                                            <td>
                                                <div class="fw-bold">{{ $visit->planned_date?->format('d/m/Y') }}</div>
                                                @if($visit->executed_date)
                                                    <div class="text-success small"><i class="ti ti-check me-1"></i>{{ $visit->executed_date->format('d/m/Y') }}</div>
                                                @else
                                                    <div class="text-muted small">Rencana</div>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-teal-lt text-teal fw-bold">{{ $visit->visit_type_label }}</span>
                                                <div class="text-muted small">Sumber: {{ $visit->source_type_label }}</div>
                                            </td>
                                            <td>
                                                <div class="small fw-semibold text-dark">{{ $visit->primaryOfficer?->name }}</div>
                                                @if($visit->secondaryOfficer)
                                                    <div class="small text-muted">+ {{ $visit->secondaryOfficer->name }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="text-dark small fw-semibold">{{ Str::limit($visit->reason, 75) }}</div>
                                                @if($visit->result_summary)
                                                    <div class="text-muted small mt-1"><strong>Hasil:</strong> {{ Str::limit($visit->result_summary, 85) }}</div>
                                                @endif
                                                @if($canAccessConfidential && $visit->confidential_notes)
                                                    <div class="text-danger small mt-1"><i class="ti ti-lock me-1"></i><em>Ada catatan rahasia keluarga</em></div>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <span class="badge {{ $visit->status_badge_class }}">{{ $visit->status_label }}</span>
                                            </td>
                                            <td class="text-end">
                                                <a href="{{ route('visitations.show', $visit) }}" class="btn btn-xs btn-outline-teal">
                                                    <i class="ti ti-eye me-1"></i> Detail
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">
                                                Belum ada agenda kunjungan rumah (home visit) atau pemantauan khusus untuk siswa ini di TP {{ $academicYear }}.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ======================================================== -->
                <!-- TAB 6: LAYANAN BIMBINGAN & KONSELING (bk_services)      -->
                <!-- ======================================================== -->
                <div class="tab-pane" id="tab-layanan" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="fw-bold text-dark mb-0">
                            <i class="ti ti-notes me-2 text-primary"></i> Rekam Jejak Sesi Layanan Bimbingan &amp; Konseling ({{ $services->count() }})
                        </h4>
                        <a href="{{ route('bk.services.create', ['student_id' => $student->id, 'academic_year' => $academicYear]) }}" class="btn btn-sm btn-primary">
                            <i class="ti ti-plus me-1"></i> Tambah Sesi Layanan
                        </a>
                    </div>
                    <div class="table-responsive border rounded">
                        <table class="table table-vcenter table-hover card-table">
                            <thead>
                                <tr>
                                    <th style="width: 110px;">Tanggal</th>
                                    <th style="width: 140px;">Bidang Layanan</th>
                                    <th>Ringkasan Masalah</th>
                                    <th>Hasil &amp; Tindak Lanjut</th>
                                    @if($canAccessConfidential)
                                        <th style="width: 220px;" class="bg-danger-lt text-danger">Catatan Rahasia (Confidential)</th>
                                    @endif
                                    <th style="width: 140px;">Guru BK</th>
                                    <th style="width: 90px;" class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($services as $service)
                                    <tr>
                                        <td class="fw-bold text-dark">{{ $service->service_date->format('d/m/Y') }}</td>
                                        <td>
                                            <span class="badge {{ $service->service_type_badge_class }}">{{ $service->service_type_label }}</span>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark">{{ $service->summary }}</div>
                                            @if($service->incident)
                                                <div class="small mt-1">
                                                    <a href="{{ route('bk.referrals.show', $service->incident) }}" class="text-decoration-none text-danger">
                                                        <i class="ti ti-link me-1"></i> Kasus Piket ({{ $service->incident->category_label }})
                                                    </a>
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="text-muted small">{{ $service->result }}</div>
                                            @if($service->follow_up_plan)
                                                <div class="mt-1">
                                                    <span class="badge {{ $service->follow_up_status_badge_class }} me-1" style="font-size: 0.7rem;">
                                                        {{ $service->follow_up_status_label }}
                                                    </span>
                                                    @if($service->follow_up_target_date)
                                                        <span class="text-muted small">Target: {{ $service->follow_up_target_date->format('d/m/Y') }}</span>
                                                    @endif
                                                </div>
                                                <div class="text-primary small mt-1"><em>RTL:</em> {{ $service->follow_up_plan }}</div>
                                                @if($service->follow_up_evaluation_notes)
                                                    <div class="text-secondary small mt-1" style="font-size: 0.72rem;">
                                                        <i class="ti ti-check me-1"></i><strong>Evaluasi:</strong> {{ Str::limit($service->follow_up_evaluation_notes, 60) }}
                                                    </div>
                                                @endif
                                            @endif
                                        </td>
                                        @if($canAccessConfidential)
                                            <td class="bg-danger-subtle">
                                                @if($service->confidentialNote && !empty($service->confidentialNote->notes))
                                                    <div class="small text-danger fw-semibold" style="white-space: pre-line;">{{ $service->confidentialNote->notes }}</div>
                                                @else
                                                    <span class="text-muted small italic">- Tidak ada catatan rahasia -</span>
                                                @endif
                                            </td>
                                        @endif
                                        <td>
                                            <div class="small fw-semibold">{{ $service->counselor?->name ?? 'Guru BK' }}</div>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('bk.services.show', $service) }}" class="btn btn-xs btn-outline-primary" title="Buka Detail">
                                                <i class="ti ti-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $canAccessConfidential ? 7 : 6 }}" class="text-center py-5 text-muted">
                                            <i class="ti ti-notes-off fs-1 d-block mb-2 text-muted"></i>
                                            Belum ada rekaman sesi layanan BK untuk siswa ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ======================================================== -->
                <!-- TAB 7: KEDISIPLINAN & PERIZINAN                         -->
                <!-- ======================================================== -->
                <div class="tab-pane" id="tab-disiplin" role="tabpanel">
                    <!-- Catatan Kejadian & Rekaman Pelanggaran Kedisiplinan -->
                    <div class="card border shadow-none mb-4">
                        <div class="card-header bg-danger-lt py-2 d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="card-title text-danger mb-0 fw-bold">
                                    <i class="ti ti-shield-alert me-2"></i> Rekaman Kejadian &amp; Pelanggaran Kedisiplinan Siswa ({{ $allIncidents->count() }})
                                </h4>
                                <div class="text-muted small mt-1">Riwayat Kasus Piket Dirujuk ke BK &amp; Penanganan Kedisiplinan</div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <a href="{{ route('visitations.create', ['student_id' => $student->id, 'source_type' => 'kasus_kedisiplinan']) }}" class="btn btn-xs btn-outline-danger">
                                    <i class="ti ti-home-heart me-1"></i> Tindak Lanjut Visitasi
                                </a>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th style="width: 120px;">Waktu &amp; Lokasi</th>
                                        <th>Kategori Kejadian</th>
                                        <th>Uraian Kejadian di Meja Piket</th>
                                        <th>Status Verifikasi</th>
                                        <th>Sanksi / Tindak Lanjut</th>
                                        <th style="width: 130px;" class="text-end">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($allIncidents as $inc)
                                        <tr>
                                            <td>
                                                <div class="fw-bold">{{ $inc->incident_date?->format('d/m/Y') }}</div>
                                                <div class="text-muted small">{{ substr($inc->incident_time, 0, 5) }} WIB</div>
                                                @if($inc->location)
                                                    <div class="text-secondary small"><i class="ti ti-map-pin me-1"></i>{{ $inc->location }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-danger-lt text-danger">{{ $inc->category_label }}</span>
                                                <div class="text-muted small mt-1">Oleh: {{ $inc->recordedBy?->name ?? 'Piket' }}</div>
                                            </td>
                                            <td>
                                                <div class="text-dark fw-semibold small">{{ $inc->description }}</div>
                                                <div class="text-muted small mt-1"><strong>Tindakan Piket:</strong> {{ $inc->action_taken }}</div>
                                                @if($inc->confiscated_items)
                                                    <div class="text-danger small mt-1"><i class="ti ti-box me-1"></i> Sitaan: {{ $inc->confiscated_items }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge {{ $inc->verification_badge_class }}">
                                                    {{ $inc->verification_status_label }}
                                                </span>
                                                @if($inc->verifier)
                                                    <div class="text-muted small mt-1">Verifikator: {{ $inc->verifier->name }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                @if($inc->disciplinary_sanction)
                                                    <div class="text-dark small fw-bold"><i class="ti ti-gavel me-1 text-danger"></i>{{ $inc->disciplinary_sanction }}</div>
                                                @else
                                                    <div class="text-muted small">- Belum ada sanksi formal -</div>
                                                @endif
                                                @if($inc->parental_involvement)
                                                    <span class="badge bg-purple-lt text-purple small mt-1"><i class="ti ti-users me-1"></i>Ortu Dilibatkan</span>
                                                @endif
                                                <div class="text-secondary small mt-1"><strong>Target:</strong> {{ $inc->follow_up_label }}</div>
                                            </td>
                                            <td class="text-end text-nowrap">
                                                @if(auth()->user()->canManageDiscipline($academicYear))
                                                    <button type="button" class="btn btn-xs btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modal-verify-{{ $inc->id }}">
                                                        <i class="ti ti-check-double me-1"></i> Verifikasi
                                                    </button>
                                                @endif
                                                @if($inc->follow_up_target === 'rujuk_bk')
                                                    <a href="{{ route('bk.referrals.show', $inc) }}" class="btn btn-xs btn-outline-primary" title="Buka Rujukan BK">
                                                        <i class="ti ti-eye me-1"></i> Rujukan
                                                    </a>
                                                @endif
                                                @if(auth()->user()->canManageDiscipline($academicYear) || auth()->user()->isSuperAdmin())
                                                    <a href="{{ route('piket.incidents.surat_panggilan', $inc) }}" class="btn btn-xs btn-outline-danger" title="Cetak Surat Panggilan Orang Tua (PDF)">
                                                        <i class="ti ti-mail me-1"></i> Surat
                                                    </a>
                                                @endif
                                            </td>
                                        </tr>

                                        @if(auth()->user()->canManageDiscipline($academicYear))
                                            <!-- Modal Verifikasi Pelanggaran & Sanksi -->
                                            <div class="modal fade" id="modal-verify-{{ $inc->id }}" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content shadow-lg border-0 text-start">
                                                        <form action="{{ route('piket.incidents.verify', $inc) }}" method="POST">
                                                            @csrf
                                                            <div class="modal-header bg-light">
                                                                <h5 class="modal-title fw-bold text-dark mb-0">
                                                                    <i class="ti ti-shield-check text-danger me-2"></i> Verifikasi &amp; Sanksi Kedisiplinan
                                                                </h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body p-4">
                                                                <div class="mb-3 p-3 bg-light rounded border">
                                                                    <div class="fw-bold fs-3 text-dark">{{ $inc->student_name }}</div>
                                                                    <div class="text-muted small">
                                                                        Kategori: <span class="badge bg-danger-lt fw-bold">{{ $inc->category_label }}</span> &bull; {{ $inc->incident_date?->format('d/m/Y') }}
                                                                    </div>
                                                                    <div class="text-secondary small mt-2">
                                                                        <strong>Fakta Kejadian:</strong> {{ $inc->description }}
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
                                                                    <input type="text" name="parental_involvement_notes" class="form-control form-control-sm" placeholder="Catatan keterlibatan ortu (surat panggilan / hadir ke sekolah)..." value="{{ $inc->parental_involvement_notes }}">
                                                                </div>

                                                                <div class="mb-3">
                                                                    <label class="form-label fw-bold text-dark">Tindak Lanjut Lintas Modul:</label>
                                                                    <select name="follow_up_target" class="form-select">
                                                                        <option value="selesai_di_piket" {{ $inc->follow_up_target === 'selesai_di_piket' ? 'selected' : '' }}>Selesai / Ditangani Tim Kedisiplinan</option>
                                                                        <option value="rujuk_bk" {{ $inc->follow_up_target === 'rujuk_bk' ? 'selected' : '' }}>Rujuk ke Guru BK (Layanan Bimbingan Konseling)</option>
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
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">
                                                Tidak ada catatan kejadian piket atau rekaman pelanggaran untuk siswa ini.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Perizinan & Keterlambatan Meja Piket (piket_student_permits) -->
                    <div class="card border shadow-none">
                        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                            <h4 class="card-title text-dark mb-0 fw-bold">
                                <i class="ti ti-door-exit me-2 text-primary"></i> Rekapitulasi Perizinan &amp; Keterlambatan Meja Piket ({{ $permits->count() }})
                            </h4>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th style="width: 110px;">Tanggal</th>
                                        <th>No. Izin</th>
                                        <th>Jenis Perizinan</th>
                                        <th>Waktu</th>
                                        <th>Alasan / Keterangan</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($permits as $permit)
                                        <tr>
                                            <td class="fw-bold">{{ $permit->permit_date->format('d/m/Y') }}</td>
                                            <td><code>{{ $permit->permit_number }}</code></td>
                                            <td>
                                                @if($permit->permit_type === 'terlambat')
                                                    <span class="badge bg-warning-lt text-warning">Terlambat</span>
                                                @elseif($permit->permit_type === 'izin_keluar_sementara')
                                                    <span class="badge bg-info-lt text-info">Izin Keluar Sementara</span>
                                                @elseif($permit->permit_type === 'izin_pulang_cepat')
                                                    <span class="badge bg-danger-lt text-danger">Izin Pulang Cepat</span>
                                                @else
                                                    <span class="badge bg-secondary-lt text-secondary">{{ ucfirst(str_replace('_', ' ', $permit->permit_type)) }}</span>
                                                @endif
                                            </td>
                                            <td class="small">
                                                @if($permit->permit_type === 'terlambat')
                                                    Tiba: {{ substr($permit->entry_time ?? '', 0, 5) }}
                                                @elseif($permit->permit_type === 'izin_keluar_sementara')
                                                    Keluar: {{ substr($permit->departure_time ?? '', 0, 5) }} - Kembali: {{ substr($permit->return_time ?? '', 0, 5) }}
                                                @elseif($permit->permit_type === 'izin_pulang_cepat')
                                                    Pulang: {{ substr($permit->departure_time ?? '', 0, 5) }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                <div class="small text-dark">{{ $permit->reason }}</div>
                                            </td>
                                            <td>
                                                @if($permit->status === 'diberikan')
                                                    <span class="badge bg-success-lt text-success">Diberikan</span>
                                                @elseif($permit->status === 'ditolak')
                                                    <span class="badge bg-danger-lt text-danger">Ditolak</span>
                                                @else
                                                    <span class="badge bg-secondary-lt text-secondary">{{ ucfirst($permit->status) }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">
                                                Belum ada catatan perizinan meja piket untuk siswa ini.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ======================================================== -->
                <!-- TAB 8: DOKUMEN PENDUKUNG & ARSIP PRIVAT BK             -->
                <!-- ======================================================== -->
                <div class="tab-pane" id="tab-dokumen" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h4 class="fw-bold text-dark mb-1">
                                <i class="ti ti-files me-2 text-purple"></i> Arsip Dokumen Pendukung &amp; Berkas Privat ({{ $documents->count() }})
                            </h4>
                            <p class="text-muted small mb-0">Hasil tes psikologi/IQ, surat komitmen, perjanjian pembinaan, berkas medis, dan piagam siswa TP {{ $academicYear }}.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-purple text-white" data-bs-toggle="modal" data-bs-target="#modal-add-document">
                            <i class="ti ti-upload me-1"></i> Unggah Dokumen Baru
                        </button>
                    </div>

                    <div class="alert alert-warning bg-warning-lt border-0 d-flex align-items-center py-2 px-3 mb-3" role="alert">
                        <i class="ti ti-shield-lock fs-3 me-2 text-warning"></i>
                        <div class="small text-dark">
                            <strong>Penyimpanan Aman (Private Storage):</strong> Seluruh berkas digital siswa disimpan dalam direktori privat non-publik server. Akses unduh diautentikasi dan diotorisasi khusus untuk Guru BK dan administrator berwenang.
                        </div>
                    </div>

                    @if($documents->isEmpty())
                        <div class="card border border-dashed shadow-none text-center py-5">
                            <div class="card-body">
                                <div class="avatar avatar-xl rounded-circle bg-purple-lt text-purple mx-auto mb-3">
                                    <i class="ti ti-files fs-1"></i>
                                </div>
                                <h4 class="fw-bold text-dark mb-1">Belum Ada Dokumen Pendukung Tersimpan</h4>
                                <p class="text-muted mx-auto mb-3" style="max-width: 520px;">
                                    Unggah berkas digital individual siswa seperti hasil psikotes, surat komitmen pembinaan, atau berkas medis khusus untuk arsip digital yang rapi dan aman.
                                </p>
                                <button type="button" class="btn btn-sm btn-outline-purple" data-bs-toggle="modal" data-bs-target="#modal-add-document">
                                    <i class="ti ti-upload me-1"></i> Unggah Dokumen Pertama
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="table-responsive border rounded">
                            <table class="table table-vcenter table-hover card-table">
                                <thead>
                                    <tr>
                                        <th>Nama &amp; Uraian Dokumen</th>
                                        <th style="width: 170px;">Kategori Dokumen</th>
                                        <th style="width: 110px;">Tanggal Dok.</th>
                                        <th style="width: 150px;">Informasi Berkas</th>
                                        <th style="width: 130px;">Pengunggah</th>
                                        <th style="width: 110px;" class="text-end">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($documents as $doc)
                                        @php
                                            $isSensitive = $doc->isSensitive();
                                            $canAccessDoc = ! $isSensitive || $canAccessConfidential;
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-dark d-flex align-items-center gap-1">
                                                    {{ $doc->title }}
                                                    @if($isSensitive)
                                                        <span class="badge bg-danger-lt text-danger ms-1" title="Dokumen Kategori Sensitif / Rahasia">
                                                            <i class="ti ti-lock me-1"></i>Sensitif
                                                        </span>
                                                    @endif
                                                </div>
                                                @if($doc->description)
                                                    <div class="text-muted small mt-1">{{ $doc->description }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge {{ $doc->category_badge_class }}">{{ $doc->category_label }}</span>
                                            </td>
                                            <td class="text-dark small">
                                                {{ $doc->document_date ? $doc->document_date->format('d/m/Y') : '-' }}
                                            </td>
                                            <td>
                                                <div class="text-truncate text-dark small" style="max-width: 180px;" title="{{ $doc->file_name }}">
                                                    <i class="ti ti-file-text me-1 text-muted"></i>{{ $doc->file_name }}
                                                </div>
                                                <div class="text-muted small">{{ $doc->formatted_file_size }}</div>
                                            </td>
                                            <td class="text-muted small">{{ $doc->recorder?->name ?? 'Guru BK' }}</td>
                                            <td class="text-end">
                                                <div class="d-inline-flex gap-1">
                                                    @if($canAccessDoc)
                                                        <a href="{{ route('bk.students.documents.download', [$student, $doc]) }}" class="btn btn-xs btn-outline-primary" title="Unduh Berkas">
                                                            <i class="ti ti-download"></i>
                                                        </a>
                                                        <form action="{{ route('bk.students.documents.destroy', [$student, $doc]) }}" method="POST" onsubmit="return confirm('Hapus berkas dokumen ini secara permanen?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-xs btn-outline-danger" title="Hapus Dokumen">
                                                                <i class="ti ti-trash"></i>
                                                            </button>
                                                        </form>
                                                    @else
                                                        <button type="button" class="btn btn-xs btn-outline-secondary disabled" title="Dokumen ini berstatus sensitif. Akses unduh memerlukan kewenangan catatan konseling rahasia.">
                                                            <i class="ti ti-lock"></i>
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ================================================================= -->
<!-- MODAL: ISI / PERBARUI PROFIL MINAT, BAKAT & KARIER SISWA          -->
<!-- ================================================================= -->
<div class="modal modal-blur fade" id="modal-career-profile" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <form action="{{ route('bk.students.career.store', $student) }}" method="POST" class="modal-content">
            @csrf
            <input type="hidden" name="academic_year" value="{{ $academicYear }}">

            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="ti ti-compass text-primary me-1"></i> Profil Minat &amp; Rencana Karier (TP {{ $academicYear }})
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="alert alert-info-lt py-2 mb-3">
                    <i class="ti ti-info-circle me-1"></i> Data peminatan dan rencana masa depan dapat diisi bertahap sesuai proses pembimbingan. Seluruh kolom bersifat opsional.
                </div>

                <div class="row g-3">
                    <!-- Rencana Pasca Lulus (BMW) -->
                    <div class="col-md-6">
                        <label class="form-label required fw-bold">Rencana Pasca Lulus (BMW)</label>
                        <select name="post_graduation_plan" id="select-post-graduation-plan" class="form-select">
                            <option value="belum_memutuskan" {{ old('post_graduation_plan', $careerProfile?->post_graduation_plan) === 'belum_memutuskan' ? 'selected' : '' }}>-- Belum Memutuskan / Eksplorasi --</option>
                            <option value="bekerja" {{ old('post_graduation_plan', $careerProfile?->post_graduation_plan) === 'bekerja' ? 'selected' : '' }}>Bekerja (DUDI / Industri)</option>
                            <option value="melanjutkan" {{ old('post_graduation_plan', $careerProfile?->post_graduation_plan) === 'melanjutkan' ? 'selected' : '' }}>Melanjutkan Pendidikan (Kuliah)</option>
                            <option value="wirausaha" {{ old('post_graduation_plan', $careerProfile?->post_graduation_plan) === 'wirausaha' ? 'selected' : '' }}>Berwirausaha (Usaha Mandiri)</option>
                        </select>
                    </div>

                    <!-- Cita-cita Profesi -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Cita-cita / Impian Profesi</label>
                        <input type="text" name="career_aspirations" class="form-control" placeholder="Contoh: Web Developer, Teknisi Mesin, Barista" value="{{ old('career_aspirations', $careerProfile?->career_aspirations) }}">
                    </div>

                    <!-- Dynamic Section: Bekerja -->
                    <div id="section-plan-bekerja" class="col-12 border rounded p-3 bg-light-subtle d-none">
                        <div class="fw-bold text-success mb-2 pb-1 border-bottom">
                            <i class="ti ti-briefcase me-1"></i> Rincian Target Bekerja
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small">Target Industri / Perusahaan DUDI</label>
                                <input type="text" name="target_industry" class="form-control form-control-sm" placeholder="Contoh: PT Astra Honda Motor, Industri Manufaktur" value="{{ old('target_industry', $careerProfile?->target_industry) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Target Posisi / Bidang Pekerjaan</label>
                                <input type="text" name="target_job_position" class="form-control form-control-sm" placeholder="Contoh: Maintenance Technician, Quality Control" value="{{ old('target_job_position', $careerProfile?->target_job_position) }}">
                            </div>
                        </div>
                    </div>

                    <!-- Dynamic Section: Melanjutkan Kuliah -->
                    <div id="section-plan-melanjutkan" class="col-12 border rounded p-3 bg-light-subtle d-none">
                        <div class="fw-bold text-azure mb-2 pb-1 border-bottom">
                            <i class="ti ti-school me-1"></i> Rincian Target Melanjutkan Pendidikan
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small">Target Perguruan Tinggi / Politeknik</label>
                                <input type="text" name="target_university" class="form-control form-control-sm" placeholder="Contoh: Politeknik Negeri Malang, Universitas Brawijaya" value="{{ old('target_university', $careerProfile?->target_university) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Target Program Studi</label>
                                <input type="text" name="target_study_program" class="form-control form-control-sm" placeholder="Contoh: Teknik Informatika, D4 Mesin Otomotif" value="{{ old('target_study_program', $careerProfile?->target_study_program) }}">
                            </div>
                        </div>
                    </div>

                    <!-- Dynamic Section: Wirausaha -->
                    <div id="section-plan-wirausaha" class="col-12 border rounded p-3 bg-light-subtle d-none">
                        <div class="fw-bold text-warning mb-2 pb-1 border-bottom">
                            <i class="ti ti-building-store me-1"></i> Rincian Rencana Wirausaha
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small">Jenis / Bidang Usaha yang Diminati</label>
                                <input type="text" name="entrepreneurship_type" class="form-control form-control-sm" placeholder="Contoh: Bengkel Sepeda Motor, Bengkel Las, Kuliner" value="{{ old('entrepreneurship_type', $careerProfile?->entrepreneurship_type) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Ringkasan Ide / Rencana Bisnis</label>
                                <textarea name="business_plan_summary" class="form-control form-control-sm" rows="2" placeholder="Gambaran kesiapan modal, keterampilan, dan pasar">{{ old('business_plan_summary', $careerProfile?->business_plan_summary) }}</textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Potensi Diri & Minat -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Hobi &amp; Kegemaran</label>
                        <textarea name="hobbies" class="form-control" rows="2" placeholder="Hobi atau aktivitas waktu luang siswa">{{ old('hobbies', $careerProfile?->hobbies) }}</textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Bakat / Potensi Khusus</label>
                        <textarea name="talents" class="form-control" rows="2" placeholder="Bakat atau keahlian menonjol">{{ old('talents', $careerProfile?->talents) }}</textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Kemampuan Ingin Dikembangkan</label>
                        <textarea name="skills_to_develop" class="form-control" rows="2" placeholder="Keterampilan teknis / non-teknis yang ingin diperdalam">{{ old('skills_to_develop', $careerProfile?->skills_to_develop) }}</textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Mata Pelajaran / Bidang Kejuruan Favorit</label>
                        <textarea name="favorite_subjects" class="form-control" rows="2" placeholder="Mata pelajaran produktif atau umum yang paling disukai">{{ old('favorite_subjects', $careerProfile?->favorite_subjects) }}</textarea>
                    </div>

                    <!-- Catatan Pembimbingan -->
                    <div class="col-12">
                        <label class="form-label fw-bold">Catatan Pembimbingan Karier</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Catatan konselor mengenai arah peminatan dan komitmen siswa">{{ old('notes', $careerProfile?->notes) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="ti ti-device-floppy me-1"></i> Simpan Profil Karier
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================= -->
<!-- MODAL: CATAT HASIL ASESMEN MINAT & BAKAT SISWA                    -->
<!-- ================================================================= -->
<div class="modal modal-blur fade" id="modal-assessment" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form action="{{ route('bk.students.assessments.store', $student) }}" method="POST" class="modal-content">
            @csrf
            <input type="hidden" name="academic_year" value="{{ $academicYear }}">

            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="ti ti-clipboard-check text-success me-1"></i> Catat Hasil Asesmen Minat-Bakat
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label required fw-bold">Tanggal Pelaksanaan</label>
                        <input type="date" name="assessment_date" class="form-control" required value="{{ old('assessment_date', now()->toDateString()) }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Asesor / Lembaga Penyelenggara</label>
                        <input type="text" name="assessor_name" class="form-control" placeholder="Contoh: Tim BK Sekolah / Biro Psikologi" value="{{ old('assessor_name') }}">
                    </div>

                    <div class="col-12">
                        <label class="form-label required fw-bold">Nama Instrumen / Tes Asesmen</label>
                        <input type="text" name="assessment_name" class="form-control" required placeholder="Contoh: Tes Minat Bakat RIASEC, Tes Kepribadian MBTI, Asesmen Diagnostik" value="{{ old('assessment_name') }}">
                    </div>

                    <div class="col-12">
                        <label class="form-label required fw-bold">Ringkasan Hasil Tes</label>
                        <textarea name="result_summary" class="form-control" rows="3" required placeholder="Hasil profil kepribadian, tipe kecenderungan minat, atau skor tes">{{ old('result_summary') }}</textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold">Rekomendasi Jalur Karier</label>
                        <textarea name="recommended_career_paths" class="form-control" rows="2" placeholder="Rekomendasi jurusan atau bidang profesi yang sesuai hasil tes">{{ old('recommended_career_paths') }}</textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold">Catatan Tambahan</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Catatan tindak lanjut atau keterangan tambahan">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success">
                    <i class="ti ti-device-floppy me-1"></i> Simpan Hasil Asesmen
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================= -->
<!-- MODAL: CATAT PRESTASI / KEGIATAN SISWA (TAB 4)                    -->
<!-- ================================================================= -->
<div class="modal modal-blur fade" id="modal-add-achievement" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <form action="{{ route('bk.students.achievements.store', $student) }}" method="POST" class="modal-content">
            @csrf
            <input type="hidden" name="academic_year" value="{{ $academicYear }}">

            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="ti ti-trophy text-warning me-1"></i> Catat Prestasi &amp; Kegiatan Siswa
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label required fw-bold">Tanggal Kegiatan / Perolehan</label>
                        <input type="date" name="event_date" class="form-control" required value="{{ old('event_date', now()->toDateString()) }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label required fw-bold">Tipe Rekam Jejak</label>
                        <select name="type" class="form-select" required>
                            <option value="prestasi" {{ old('type') === 'prestasi' ? 'selected' : '' }}>Prestasi / Kejuaraan Lomba</option>
                            <option value="kegiatan" {{ old('type') === 'kegiatan' ? 'selected' : '' }}>Kegiatan / Kepengurusan Organisasi</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label required fw-bold">Kategori Bidang</label>
                        <select name="category" class="form-select" required>
                            <option value="akademik" {{ old('category') === 'akademik' ? 'selected' : '' }}>Akademik</option>
                            <option value="non_akademik" {{ old('category') === 'non_akademik' ? 'selected' : '' }}>Non-Akademik</option>
                            <option value="seni" {{ old('category') === 'seni' ? 'selected' : '' }}>Seni &amp; Budaya</option>
                            <option value="olahraga" {{ old('category') === 'olahraga' ? 'selected' : '' }}>Olahraga</option>
                            <option value="keagamaan" {{ old('category') === 'keagamaan' ? 'selected' : '' }}>Keagamaan</option>
                            <option value="organisasi" {{ old('category') === 'organisasi' ? 'selected' : '' }}>Organisasi (OSIS / MPK)</option>
                            <option value="ekskul" {{ old('category') === 'ekskul' ? 'selected' : '' }}>Ekstrakurikuler</option>
                            <option value="lainnya" {{ old('category') === 'lainnya' ? 'selected' : '' }}>Lainnya</option>
                        </select>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label required fw-bold">Nama Prestasi / Kegiatan / Kejuaraan</label>
                        <input type="text" name="title" class="form-control" required placeholder="Contoh: Juara 1 Lomba Web Design Tingkat Provinsi" value="{{ old('title') }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label required fw-bold">Tingkat Penyelenggaraan</label>
                        <select name="level" class="form-select" required>
                            <option value="sekolah" {{ old('level') === 'sekolah' ? 'selected' : '' }}>Tingkat Sekolah</option>
                            <option value="kecamatan" {{ old('level') === 'kecamatan' ? 'selected' : '' }}>Tingkat Kecamatan</option>
                            <option value="kabupaten" {{ old('level') === 'kabupaten' ? 'selected' : '' }}>Tingkat Kota / Kabupaten</option>
                            <option value="provinsi" {{ old('level') === 'provinsi' ? 'selected' : '' }}>Tingkat Provinsi</option>
                            <option value="nasional" {{ old('level') === 'nasional' ? 'selected' : '' }}>Tingkat Nasional</option>
                            <option value="internasional" {{ old('level') === 'internasional' ? 'selected' : '' }}>Tingkat Internasional</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">Hasil / Capaian / Peran</label>
                        <input type="text" name="achievement_result" class="form-control" placeholder="Contoh: Juara 1 / Medali Emas / Ketua" value="{{ old('achievement_result') }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">Lembaga Penyelenggara</label>
                        <input type="text" name="organizer" class="form-control" placeholder="Contoh: Dinas Pendidikan / Kemendikbud" value="{{ old('organizer') }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">No. Piagam / Sertifikat (Jika Ada)</label>
                        <input type="text" name="certificate_number" class="form-control" placeholder="Contoh: 421.3/089/SERT/2026" value="{{ old('certificate_number') }}">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold">Catatan Keterangan Tambahan</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Keterangan tambahan mengenai keikutsertaan atau capaian siswa">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-warning">
                    <i class="ti ti-device-floppy me-1"></i> Simpan Rekam Jejak
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================= -->
<!-- MODAL: CATAT OBSERVASI PERKEMBANGAN SISWA NON-KASUS (TAB 5)       -->
<!-- ================================================================= -->
<div class="modal modal-blur fade" id="modal-add-development-note" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <form action="{{ route('bk.students.development-notes.store', $student) }}" method="POST" class="modal-content">
            @csrf
            <input type="hidden" name="academic_year" value="{{ $academicYear }}">

            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="ti ti-chart-line text-teal me-1"></i> Tambah Catatan Observasi Perkembangan
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label required fw-bold">Tanggal Observasi</label>
                        <input type="date" name="note_date" class="form-control" required value="{{ old('note_date', now()->toDateString()) }}">
                    </div>

                    <div class="col-md-8">
                        <label class="form-label required fw-bold">Aspek Perkembangan Siswa</label>
                        <select name="category" class="form-select" required>
                            <option value="sikap_belajar" {{ old('category') === 'sikap_belajar' ? 'selected' : '' }}>Sikap &amp; Kebiasaan Belajar</option>
                            <option value="kedisiplinan_positif" {{ old('category') === 'kedisiplinan_positif' ? 'selected' : '' }}>Kedisiplinan &amp; Ketertiban Diri</option>
                            <option value="kemandirian" {{ old('category') === 'kemandirian' ? 'selected' : '' }}>Kemandirian &amp; Tanggung Jawab</option>
                            <option value="interaksi_sosial" {{ old('category') === 'interaksi_sosial' ? 'selected' : '' }}>Interaksi Sosial &amp; Pertemanan</option>
                            <option value="tanggung_jawab" {{ old('category') === 'tanggung_jawab' ? 'selected' : '' }}>Partisipasi &amp; Kepedulian Kelas</option>
                            <option value="rekomendasi_pembinaan" {{ old('category') === 'rekomendasi_pembinaan' ? 'selected' : '' }}>Rekomendasi Tindak Lanjut Pembinaan</option>
                            <option value="lainnya" {{ old('category') === 'lainnya' ? 'selected' : '' }}>Lainnya</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label required fw-bold">Judul Observasi / Fokus Perkembangan</label>
                        <input type="text" name="title" class="form-control" required placeholder="Contoh: Peningkatan antusiasme belajar praktikum kejuruan" value="{{ old('title') }}">
                    </div>

                    <div class="col-12">
                        <label class="form-label required fw-bold">Uraian Observasi Perkembangan Siswa</label>
                        <textarea name="description" class="form-control" rows="3" required placeholder="Deskripsikan perkembangan positif atau hal yang diamati">{{ old('description') }}</textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold">Saran / Rekomendasi Tindak Lanjut Positif</label>
                        <textarea name="follow_up_recommendation" class="form-control" rows="2" placeholder="Rekomendasi arahan pembinaan atau penguatan motivasi ke depan (opsional)">{{ old('follow_up_recommendation') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-teal text-white">
                    <i class="ti ti-device-floppy me-1"></i> Simpan Catatan Observasi
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================= -->
<!-- MODAL: UNGGAH DOKUMEN PENDUKUNG & ARSIP PRIVAT SISWA (TAB 8)      -->
<!-- ================================================================= -->
<div class="modal modal-blur fade" id="modal-add-document" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <form action="{{ route('bk.students.documents.store', $student) }}" method="POST" enctype="multipart/form-data" class="modal-content">
            @csrf
            <input type="hidden" name="academic_year" value="{{ $academicYear }}">

            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="ti ti-files text-purple me-1"></i> Unggah Dokumen Pendukung Siswa
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label required fw-bold">Kategori Dokumen</label>
                        <select name="document_category" class="form-select" required>
                            <optgroup label="Dokumen Umum">
                                <option value="sertifikat_prestasi" {{ old('document_category') === 'sertifikat_prestasi' ? 'selected' : '' }}>Sertifikat / Piagam Prestasi</option>
                                <option value="administrasi_bk" {{ old('document_category') === 'administrasi_bk' ? 'selected' : '' }}>Administrasi Layanan BK</option>
                                <option value="lainnya" {{ old('document_category') === 'lainnya' ? 'selected' : '' }}>Dokumen Pendukung Lainnya</option>
                            </optgroup>
                            <optgroup label="Dokumen Sensitif (Wajib Akses Catatan Konseling Rahasia)">
                                <option value="hasil_psikotes" {{ old('document_category') === 'hasil_psikotes' ? 'selected' : '' }} {{ ! $canAccessConfidential ? 'disabled' : '' }}>Hasil Tes Bakat / IQ / Psikotes {{ ! $canAccessConfidential ? '(Terkunci)' : '' }}</option>
                                <option value="surat_komitmen" {{ old('document_category') === 'surat_komitmen' ? 'selected' : '' }} {{ ! $canAccessConfidential ? 'disabled' : '' }}>Surat Pernyataan / Komitmen Siswa &amp; Ortu {{ ! $canAccessConfidential ? '(Terkunci)' : '' }}</option>
                                <option value="surat_perjanjian" {{ old('document_category') === 'surat_perjanjian' ? 'selected' : '' }} {{ ! $canAccessConfidential ? 'disabled' : '' }}>Surat Perjanjian Pembinaan Khusus {{ ! $canAccessConfidential ? '(Terkunci)' : '' }}</option>
                                <option value="berkas_kesehatan" {{ old('document_category') === 'berkas_kesehatan' ? 'selected' : '' }} {{ ! $canAccessConfidential ? 'disabled' : '' }}>Berkas / Catatan Medis Khusus {{ ! $canAccessConfidential ? '(Terkunci)' : '' }}</option>
                            </optgroup>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Tanggal Dokumen (Jika Ada)</label>
                        <input type="date" name="document_date" class="form-control" value="{{ old('document_date') }}">
                    </div>

                    <div class="col-12">
                        <label class="form-label required fw-bold">Nama / Judul Dokumen</label>
                        <input type="text" name="title" class="form-control" required placeholder="Contoh: Surat Komitmen Bersama Siswa dan Wali Murid" value="{{ old('title') }}">
                    </div>

                    <div class="col-12">
                        <label class="form-label required fw-bold">Pilih Berkas Dokumen</label>
                        <input type="file" name="document_file" class="form-control" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <div class="form-text text-muted">
                            Format berkas yang didukung: PDF, JPG, PNG, DOC, DOCX. Maksimal ukuran 10 MB. Berkas disimpan aman secara privat.
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold">Keterangan / Ringkasan Isi Dokumen</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Catatan atau ringkasan isi berkas dokumen">{{ old('description') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-purple text-white">
                    <i class="ti ti-upload me-1"></i> Unggah &amp; Simpan Berkas
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Dynamic Toggle untuk Rincian Target Pasca Lulus (BMW)
    const selectPlan = document.getElementById('select-post-graduation-plan');
    const secBekerja = document.getElementById('section-plan-bekerja');
    const secMelanjutkan = document.getElementById('section-plan-melanjutkan');
    const secWirausaha = document.getElementById('section-plan-wirausaha');

    function syncPlanSections() {
        if (!selectPlan) return;
        const val = selectPlan.value;
        if (secBekerja) secBekerja.classList.toggle('d-none', val !== 'bekerja');
        if (secMelanjutkan) secMelanjutkan.classList.toggle('d-none', val !== 'melanjutkan');
        if (secWirausaha) secWirausaha.classList.toggle('d-none', val !== 'wirausaha');
    }

    if (selectPlan) {
        selectPlan.addEventListener('change', syncPlanSections);
        syncPlanSections();
    }

    // 2. Tab Activator via URL Hash (misal kembali dari submit form #tab-minat)
    if (window.location.hash) {
        const targetTabLink = document.querySelector(`.nav-tabs a[href="${window.location.hash}"]`);
        if (targetTabLink) {
            const tabInstance = new bootstrap.Tab(targetTabLink);
            tabInstance.show();
        }
    }
});
</script>
@endpush
@endsection
