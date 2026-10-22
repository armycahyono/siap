@extends('layouts.mobile')

@section('title', 'Surat Izin ' . $permit->permit_number . ' — APK SIAP')
@section('page_title', 'Detail Surat Izin')
@section('page_subtitle', $permit->permit_number)
@section('back_url', route('piket.permits.index'))

@section('content')
<!-- Header Card Status Surat Izin -->
<div class="card border-0 shadow-sm rounded-3 mb-2" style="background: linear-gradient(135deg, #102d52 0%, #195b9e 100%);">
    <div class="card-body p-3 text-white">
        <div class="d-flex align-items-center justify-content-between mb-1.5">
            <span class="badge bg-white-lt text-white" style="font-size: 0.65rem;">
                No: {{ $permit->permit_number }}
            </span>
            <span class="badge {{ $permit->permit_type_badge_class }}" style="font-size: 0.68rem; padding: 3px 8px;">
                {{ $permit->permit_type_label }}
            </span>
        </div>
        <div class="fw-bold fs-2 lh-sm text-white mb-0.5">{{ $permit->student_name }}</div>
        <div class="text-white-50 small mb-2" style="font-size: 0.75rem;">
            {{ $permit->classroom?->name ?? 'Kelas -' }} &bull; 
            Diterbitkan: {{ $permit->permit_date?->translatedFormat('d M Y') }}
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('piket.permits.slip', $permit) }}" target="_blank" class="btn btn-sm btn-warning text-white fw-bold rounded-pill flex-fill py-1.5 shadow-sm d-flex align-items-center justify-content-center gap-1.5" style="font-size: 0.8rem; color: #ffffff !important; height: 34px;">
                <i class="ti ti-printer fs-3 text-white"></i>
                <span class="text-white">Cetak Slip Izin</span>
            </a>
            @php
                $timeInfo = match($permit->permit_type) {
                    'terlambat' => substr($permit->entry_time ?? '', 0, 5) . ' WIB',
                    'izin_keluar_sementara' => substr($permit->departure_time ?? '', 0, 5) . ($permit->return_time ? ' - ' . substr($permit->return_time, 0, 5) : '') . ' WIB',
                    'izin_pulang_cepat' => substr($permit->departure_time ?? '', 0, 5) . ' WIB',
                    'izin_tidak_masuk' => ($permit->end_date && $permit->end_date->gt($permit->permit_date) ? $permit->permit_date?->translatedFormat('d M') . ' s/d ' . $permit->end_date?->translatedFormat('d M Y') : '1 Hari'),
                    default => substr($permit->entry_time ?: $permit->departure_time ?: '', 0, 5) . ' WIB'
                };

                $waText = "Halo, kami informasikan dari Meja Piket Sekolah:\n" .
                    "*Surat Izin:* " . $permit->permit_number . "\n" .
                    "*Nama Siswa:* " . $permit->student_name . " (" . ($permit->classroom?->name ?? '-') . ")\n" .
                    "*Jenis Izin:* " . $permit->permit_type_label . "\n" .
                    "*Waktu/Durasi:* " . $timeInfo . "\n" .
                    "*Alasan:* " . $permit->reason . "\n" .
                    ($permit->action_taken ? "*Tindakan:* " . $permit->action_taken . "\n" : "") .
                    "*Petugas Piket:* " . ($permit->givenBy?->name ?? 'Petugas Piket');
                $waUrl = "https://api.whatsapp.com/send?text=" . urlencode($waText);
            @endphp
            <a href="{{ $waUrl }}" target="_blank" class="d-inline-flex align-items-center justify-content-center gap-1.5 text-decoration-none py-1.5 px-3 flex-shrink-0" style="font-size: 0.8rem; color: #25D366 !important; font-weight: 700; height: 34px;">
                <i class="ti ti-brand-whatsapp fs-3" style="color: #25D366 !important;"></i>
                <span style="color: #25D366 !important;">Kirim WA</span>
            </a>
        </div>
    </div>
</div>

<!-- Informasi Siswa & Perwalian -->
<div class="card border-0 shadow-sm rounded-3 mb-2">
    <div class="card-body p-2 px-3">
        <div class="fw-bold text-dark fs-4 mb-2 pb-1 border-bottom d-flex align-items-center gap-1.5">
            <i class="ti ti-user text-primary"></i> Data Siswa &amp; Perwalian
        </div>
        <div class="d-flex flex-column gap-1.5" style="font-size: 0.78rem;">
            <div class="d-flex justify-content-between">
                <span class="text-muted">Nama Lengkap</span>
                <span class="fw-bold text-dark">{{ $permit->student_name }}</span>
            </div>
            <div class="d-flex justify-content-between">
                <span class="text-muted">Kelas / Rombel</span>
                <span class="badge bg-blue-lt fw-bold">{{ $permit->classroom?->name ?? '-' }}</span>
            </div>
            <div class="d-flex justify-content-between">
                <span class="text-muted">Wali Kelas</span>
                <span class="fw-semibold text-dark">{{ $permit->classroom?->homeroomTeacher?->name ?? 'Belum Ditugaskan' }}</span>
            </div>
            @if($permit->student?->nis)
                <div class="d-flex justify-content-between">
                    <span class="text-muted">NIS Siswa</span>
                    <span class="text-secondary">{{ $permit->student->nis }}</span>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Rincian Waktu & Alasan Izin -->
<div class="card border-0 shadow-sm rounded-3 mb-2">
    <div class="card-body p-2 px-3">
        <div class="fw-bold text-dark fs-4 mb-2 pb-1 border-bottom d-flex align-items-center gap-1.5">
            <i class="ti ti-clock text-warning"></i> Waktu &amp; Alasan
        </div>
        <div class="d-flex flex-column gap-1.5" style="font-size: 0.78rem;">
            @if($permit->permit_type === 'terlambat')
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Waktu Tiba</span>
                    <span class="fw-bold text-dark">{{ substr($permit->entry_time, 0, 5) }} WIB</span>
                </div>
                @if($permit->period_assigned)
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Diizinkan Masuk</span>
                        <span class="badge bg-warning-lt text-dark fw-bold">Jam ke-{{ $permit->period_assigned }}</span>
                    </div>
                @endif
            @elseif($permit->permit_type === 'izin_keluar_sementara')
                @if($permit->departure_time)
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Jam Keluar</span>
                        <span class="fw-bold text-dark">{{ substr($permit->departure_time, 0, 5) }} WIB</span>
                    </div>
                @endif
                @if($permit->return_time)
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Perkiraan Kembali</span>
                        <span class="fw-bold text-primary">{{ substr($permit->return_time, 0, 5) }} WIB</span>
                    </div>
                @endif
            @elseif($permit->permit_type === 'izin_pulang_cepat')
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Jam Pulang Cepat</span>
                    <span class="fw-bold text-danger">{{ substr($permit->departure_time, 0, 5) }} WIB</span>
                </div>
            @elseif($permit->permit_type === 'izin_tidak_masuk')
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Kategori Surat</span>
                    <span class="badge bg-purple-lt fw-bold">{{ ucfirst($permit->permit_category ?? 'Izin') }}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Periode Izin</span>
                    <span class="fw-bold text-dark">
                        {{ $permit->permit_date?->translatedFormat('d M Y') }}
                        @if($permit->end_date && $permit->end_date->gt($permit->permit_date))
                            s/d {{ $permit->end_date?->translatedFormat('d M Y') }}
                        @endif
                    </span>
                </div>
                @if($permit->attachment_path)
                    <div class="d-flex justify-content-between align-items-center mt-1 pt-1 border-top">
                        <span class="text-muted">Foto / Bukti Surat</span>
                        <a href="{{ asset('storage/' . $permit->attachment_path) }}" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.7rem;">
                            <i class="ti ti-file me-1"></i> Lihat Dokumen
                        </a>
                    </div>
                @endif
            @endif

            <div class="p-2 bg-light rounded-2 mt-1">
                <div class="text-muted small mb-0.5" style="font-size: 0.7rem;">Alasan:</div>
                <div class="fw-semibold text-dark">{{ $permit->reason }}</div>
            </div>

            @if($permit->action_taken)
                <div class="p-2 rounded-2 mt-0.5" style="background: #fefce8; border: 1px solid #fef08a;">
                    <div class="text-warning small mb-0.5" style="font-size: 0.7rem; font-weight: 600;">Tindakan / Sanksi Edukatif:</div>
                    <div class="fw-semibold text-dark">{{ $permit->action_taken }}</div>
                </div>
            @endif

            <div class="d-flex justify-content-between pt-1 border-top mt-1" style="font-size: 0.72rem;">
                <span class="text-muted">Petugas Piket</span>
                <span class="text-secondary fw-semibold">{{ $permit->givenBy?->name ?? 'Petugas Piket' }}</span>
            </div>
        </div>
    </div>
</div>

<!-- Status Pengiriman Notifikasi Telegram -->
<div class="card border-0 shadow-sm rounded-3 mb-3">
    <div class="card-body p-2 px-3">
        <div class="fw-bold text-dark fs-4 mb-2 pb-1 border-bottom d-flex align-items-center gap-1.5">
            <i class="ti ti-brand-telegram text-primary"></i> Notifikasi Otomatis
        </div>
        <div class="d-flex flex-column gap-1.5" style="font-size: 0.75rem;">
            <div class="d-flex align-items-center justify-content-between">
                <span>Notifikasi Wali Kelas:</span>
                <span class="badge {{ $permit->notified_homeroom ? 'bg-success text-white' : 'bg-secondary-lt' }}" style="font-size: 0.65rem;">
                    <i class="ti {{ $permit->notified_homeroom ? 'ti-check' : 'ti-clock' }} me-0.5"></i>
                    {{ $permit->notified_homeroom ? 'Terkirim' : 'Belum / Tanpa Chat ID' }}
                </span>
            </div>
            <div class="d-flex align-items-center justify-content-between">
                <span>Notifikasi Guru Mapel:</span>
                <span class="badge {{ $permit->notified_subject_teacher ? 'bg-success text-white' : 'bg-secondary-lt' }}" style="font-size: 0.65rem;">
                    <i class="ti {{ $permit->notified_subject_teacher ? 'ti-check' : 'ti-clock' }} me-0.5"></i>
                    {{ $permit->notified_subject_teacher ? 'Terkirim' : 'Belum / Tanpa Chat ID' }}
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Tombol Navigasi Bawah -->
<div class="d-flex flex-column gap-2 mb-4">
    <a href="{{ route('piket.permits.create') }}" class="btn btn-primary w-100 py-2 fw-bold rounded-pill shadow-sm d-flex align-items-center justify-content-center gap-2" style="font-size: 0.88rem;">
        <i class="ti ti-plus fs-2"></i>
        <span>+ Terbitkan Izin Lainnya</span>
    </a>
    <div class="d-flex gap-2">
        <a href="{{ route('piket.permits.index') }}" class="btn btn-outline-secondary flex-fill py-1.5 rounded-pill fw-semibold" style="font-size: 0.8rem;">
            <i class="ti ti-list me-1"></i> Rekap Izin
        </a>
        <a href="{{ route('piket.dashboard') }}" class="btn btn-outline-secondary flex-fill py-1.5 rounded-pill fw-semibold" style="font-size: 0.8rem;">
            <i class="ti ti-layout-dashboard me-1"></i> Dashboard Piket
        </a>
    </div>
</div>
@endsection
