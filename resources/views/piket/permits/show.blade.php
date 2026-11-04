@extends('layouts.app')

@section('title', 'Detail Surat Izin Siswa')

@section('content')
<div class="container-xl">
    <div class="page-header d-print-none mb-4">
        <div class="row align-items-center">
            <div class="col">
                <a href="{{ route('piket.permits.index') }}" class="btn btn-link text-secondary p-0 mb-1">
                    <i class="ti ti-arrow-left me-1"></i> Kembali ke Daftar Perizinan
                </a>
                <h2 class="page-title text-dark fw-bold d-flex align-items-center">
                    <i class="ti ti-file-certificate text-primary me-2 fs-1"></i>
                    Surat Izin: {{ $permit->permit_number }}
                </h2>
                <div class="text-secondary mt-1">
                    Diterbitkan pada {{ $permit->permit_date?->translatedFormat('d F Y') }} oleh {{ $permit->givenBy?->name ?? 'Petugas Piket' }}
                </div>
            </div>
            <div class="col-auto ms-auto d-flex gap-2">
                @if($permit->batch_id)
                    <a href="{{ route('piket.permits.slip', ['permit' => $permit->id, 'batch' => 1]) }}" target="_blank" class="btn btn-sm btn-indigo shadow-sm">
                        <i class="ti ti-printer me-1"></i> Cetak Slip Rombongan (Per Rombel)
                    </a>
                @endif
                <a href="{{ route('piket.permits.slip', $permit) }}" target="_blank" class="btn btn-sm btn-primary shadow-sm">
                    <i class="ti ti-printer me-1"></i> Cetak Slip Izin (Individu)
                </a>
                <a href="{{ route('piket.permits.create') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="ti ti-plus me-1"></i> Buat Izin Baru
                </a>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h3 class="card-title fw-bold text-dark mb-0">Informasi Perizinan Siswa</h3>
                    <span class="badge {{ $permit->permit_type_badge_class }} fs-4 px-3 py-2">
                        {{ $permit->permit_type_label }}
                    </span>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tbody>
                            <tr>
                                <th style="width: 30%;" class="text-secondary">Nomor Surat Izin</th>
                                <td class="fw-bold fs-3 text-dark">{{ $permit->permit_number }}</td>
                            </tr>
                            <tr>
                                <th class="text-secondary">Nama Lengkap Siswa</th>
                                <td class="fw-bold fs-3 text-primary">{{ $permit->student_name }}</td>
                            </tr>
                            <tr>
                                <th class="text-secondary">Kelas / Rombel</th>
                                <td>
                                    <span class="badge bg-blue-lt fs-4">{{ $permit->classroom?->name ?? '-' }}</span>
                                </td>
                            </tr>
                            <tr>
                                <th class="text-secondary">Wali Kelas</th>
                                <td class="fw-bold text-dark">{{ $permit->classroom?->homeroomTeacher?->name ?? 'Belum Ditugaskan' }}</td>
                            </tr>
                            <tr>
                                <th class="text-secondary">Waktu / Jam</th>
                                <td class="fw-bold">
                                    {{ substr($permit->entry_time ?: $permit->departure_time ?: '', 0, 5) }} WIB
                                    @if($permit->period_assigned)
                                        <span class="text-muted">(Diizinkan masuk pada jam ke-{{ $permit->period_assigned }})</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th class="text-secondary">Alasan Perizinan</th>
                                <td class="text-dark">{{ $permit->reason }}</td>
                            </tr>
                            <tr>
                                <th class="text-secondary">Tindakan / Sanksi Edukatif</th>
                                <td>
                                    @if($permit->action_taken)
                                        <span class="badge bg-warning-lt text-dark fs-4">{{ $permit->action_taken }}</span>
                                    @else
                                        <span class="text-muted">Tidak ada sanksi khusus</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th class="text-secondary">Petugas Piket</th>
                                <td>{{ $permit->givenBy?->name ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="border-top pt-3 mt-3">
                        <div class="fw-bold text-dark mb-2"><i class="ti ti-brand-telegram text-primary me-1"></i> Status Pengiriman Notifikasi Telegram:</div>
                        <div class="d-flex gap-2">
                            <span class="badge {{ $permit->notified_homeroom ? 'bg-success text-white' : 'bg-secondary-lt' }} p-2">
                                <i class="ti {{ $permit->notified_homeroom ? 'ti-check' : 'ti-clock' }} me-1"></i>
                                Notifikasi Wali Kelas: {{ $permit->notified_homeroom ? 'Terkirim' : 'Belum / Tanpa Chat ID' }}
                            </span>
                            <span class="badge {{ $permit->notified_subject_teacher ? 'bg-success text-white' : 'bg-secondary-lt' }} p-2">
                                <i class="ti {{ $permit->notified_subject_teacher ? 'ti-check' : 'ti-clock' }} me-1"></i>
                                Notifikasi Guru Mapel: {{ $permit->notified_subject_teacher ? 'Terkirim' : 'Belum / Tanpa Chat ID' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            @if($permit->batch_id)
                @php
                    $batchPermits = \App\Models\PiketStudentPermit::where('batch_id', $permit->batch_id)->with(['classroom', 'student'])->get();
                @endphp
                @if($batchPermits->count() > 1)
                    <div class="card shadow-sm border-indigo mt-3">
                        <div class="card-header bg-indigo-lt d-flex justify-content-between align-items-center py-2 px-3">
                            <div>
                                <h4 class="card-title text-indigo mb-0 fw-bold">
                                    <i class="ti ti-users me-1"></i> Anggota Izin Rombongan ({{ $batchPermits->count() }} Siswa)
                                </h4>
                                <span class="text-secondary small">Kode Rombongan: <strong>{{ $permit->batch_id }}</strong></span>
                            </div>
                            <a href="{{ route('piket.permits.slip', ['permit' => $permit->id, 'batch' => 1]) }}" target="_blank" class="btn btn-sm btn-indigo">
                                <i class="ti ti-printer me-1"></i> Cetak Slip Rombongan (Per Rombel)
                            </a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-vcenter table-hover card-table">
                                <thead class="table-light">
                                    <tr>
                                        <th>No. Izin</th>
                                        <th>Nama Siswa</th>
                                        <th>Kelas / Rombel</th>
                                        <th>Waktu</th>
                                        <th class="text-end">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($batchPermits as $bp)
                                        <tr class="{{ $bp->id === $permit->id ? 'table-primary' : '' }}">
                                            <td>
                                                <span class="fw-bold">{{ $bp->permit_number }}</span>
                                                @if($bp->id === $permit->id)
                                                    <span class="badge bg-primary text-white ms-1">Sedang Dibuka</span>
                                                @endif
                                            </td>
                                            <td class="fw-bold text-dark">{{ $bp->student_name }}</td>
                                            <td><span class="badge bg-blue-lt">{{ $bp->classroom?->name ?? '-' }}</span></td>
                                            <td class="small text-muted">{{ substr($bp->entry_time ?: $bp->departure_time ?: '', 0, 5) }} WIB</td>
                                            <td class="text-end">
                                                @if($bp->id !== $permit->id)
                                                    <a href="{{ route('piket.permits.show', $bp) }}" class="btn btn-xs btn-outline-secondary">
                                                        <i class="ti ti-eye me-1"></i> Buka
                                                    </a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
@endsection
