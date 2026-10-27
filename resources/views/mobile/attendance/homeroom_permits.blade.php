@extends('layouts.mobile')

@section('title', 'Izin Siswa Perwalian — APK SIAP')
@section('page_title', 'Izin Siswa Perwalian')
@section('back_url', route('dashboard'))

@section('content')
@if($myClassrooms->isEmpty())
    <div class="card border-0 shadow-sm rounded-3 py-4 text-center text-muted">
        <i class="ti ti-users-minus fs-1 d-block mb-1 opacity-50"></i>
        <div class="fw-bold text-dark fs-3">Bukan Wali Kelas</div>
        <div class="small px-3 mt-1">Menu ini khusus diperuntukkan bagi Bapak/Ibu Guru yang mengemban tugas sebagai Wali Kelas aktif.</div>
    </div>
@else
    <!-- Top Action Card: Catat Izin Baru -->
    <div class="card border-0 shadow-sm rounded-3 mb-2" style="background: linear-gradient(135deg, #102d52 0%, #195b9e 100%);">
        <div class="card-body p-2 px-3 text-white d-flex align-items-center justify-content-between">
            <div>
                <div class="fw-bold fs-4 lh-sm">Lapor Izin Wali Murid</div>
                <div class="text-white-50 small" style="font-size: 0.72rem;">Otomatis sinkron ke Piket &amp; Guru Mapel</div>
            </div>
            <a href="{{ route('attendance.homeroom-permits.create', ['classroom_id' => $selectedClassroomId]) }}" 
               class="text-decoration-none fw-bold" 
               style="font-size: 0.95rem; color: #ef4444 !important;">
                + Catat Izin
            </a>
        </div>
    </div>

    <!-- Filter Kelas, Tanggal & Cari -->
    <div class="card border-0 shadow-sm rounded-3 mb-2">
        <div class="card-body p-2 px-3">
            <form action="{{ route('attendance.homeroom-permits.index') }}" method="GET" class="d-flex flex-column gap-2">
                <!-- Baris 1: Kelas & Tanggal -->
                <div class="d-flex gap-2">
                    <select name="classroom_id" class="form-select form-select-sm flex-fill" style="font-size: 0.78rem;" onchange="this.form.submit()">
                        @foreach($myClassrooms as $c)
                            <option value="{{ $c->id }}" {{ $selectedClassroomId == $c->id ? 'selected' : '' }}>
                                Kelas {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                    <input type="date" name="date" class="form-control form-control-sm flex-shrink-0" style="font-size: 0.78rem; width: 135px;" value="{{ $date }}" onchange="this.form.submit()">
                </div>

                <!-- Baris 2: Form Pencarian (lebar sama dengan Kelas) & Tombol Aksi (lebar sama dengan Tanggal) -->
                <div class="d-flex gap-2 align-items-center">
                    <!-- Input Pencarian: Lebar sama persis dengan select kelas diatasnya -->
                    <div class="flex-fill">
                        <input type="text" name="search" class="form-control form-control-sm w-100" 
                               style="font-size: 0.78rem;" 
                               value="{{ $search }}" placeholder="Cari nama siswa / alasan...">
                    </div>

                    <!-- Area Aksi: Lebar sama persis dengan tanggal (135px), tata letak rata kanan -->
                    <div class="d-flex align-items-center justify-content-end gap-1.5 flex-shrink-0" style="width: 135px;">
                        @if($date || $search)
                            <!-- Tombol X reset muncul di antara input dan tombol cari: mereset tanggal dan pencarian sehingga menampilkan semua rekap tahun ajaran ini -->
                            <a href="{{ route('attendance.homeroom-permits.index', ['classroom_id' => $selectedClassroomId, 'date' => '']) }}" 
                               class="btn btn-sm btn-icon btn-white border rounded-2 text-danger shadow-xs flex-shrink-0 d-flex align-items-center justify-content-center" 
                               style="height: 31px; width: 31px; padding: 0; background: #fff1f2; border-color: #fecdd3 !important;" 
                               title="Tampilkan Semua Tanggal / Reset Filter">
                                <i class="ti ti-x fs-3 text-danger"></i>
                            </a>
                        @endif

                        <!-- Tombol Cari: Berada di posisi rata kanan (lebar separuh tanggal / proporsional) -->
                        <button type="submit" 
                                class="btn btn-sm btn-primary rounded-2 fw-semibold shadow-xs d-flex align-items-center justify-content-center gap-1 text-white {{ ($date || $search) ? 'flex-fill' : 'w-100' }}" 
                                style="height: 31px; font-size: 0.76rem; background: linear-gradient(135deg, #102d52 0%, #195b9e 100%); border: 1px solid #195b9e; min-width: 60px;">
                            <i class="ti ti-search fs-3 text-white"></i>
                            <span>Cari</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Daftar Izin Siswa (Card Feed) -->
    <div class="mb-3">
        @forelse($permits as $p)
            @php
                $badgeStyle = match($p->permit_category) {
                    'sakit' => 'background: #fef3c7; color: #92400e; border: 1px solid #fcd34d;',
                    'dispen' => 'background: #f3e8ff; color: #6b21a8; border: 1px solid #d8b4fe;',
                    default => 'background: #0284c7; color: #ffffff; border: 1px solid #0369a1;',
                };
            @endphp
            <div class="card border-0 shadow-sm rounded-3 mb-1.5">
                <div class="card-body p-2 px-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="badge fw-bold" style="{{ $badgeStyle }} font-size: 0.68rem; padding: 3px 8px; border-radius: 6px;">
                            {{ $p->permit_type_label }}
                        </span>
                        <span class="text-muted small" style="font-size: 0.7rem;">
                            <i class="ti ti-calendar me-0.5"></i>
                            @if($p->end_date && $p->end_date->ne($p->permit_date))
                                {{ $p->permit_date?->format('d/m') }} - {{ $p->end_date?->format('d/m/Y') }}
                            @else
                                {{ $p->permit_date?->format('d/m/Y') }}
                            @endif
                        </span>
                    </div>

                    <div class="fw-bold text-dark fs-4 lh-sm">{{ $p->student_name }}</div>
                    <div class="text-muted small mb-1" style="font-size: 0.68rem;">
                        NIS: {{ $p->student?->nisn ?? $p->student?->nis ?? '-' }} &bull; Kelas: {{ $p->classroom?->name }}
                    </div>

                    <div class="p-1.5 bg-light rounded-2 small text-secondary mb-2" style="font-size: 0.75rem;">
                        <strong>Alasan:</strong> {{ $p->reason }}
                    </div>

                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            @if($p->attachment_path)
                                <a href="{{ $p->attachment_url }}" target="_blank" class="badge bg-blue-lt text-decoration-none py-1 px-2" style="font-size: 0.68rem;">
                                    <i class="ti ti-paperclip me-0.5"></i> Lihat Bukti
                                </a>
                            @else
                                <span class="text-muted small" style="font-size: 0.68rem;">Tanpa lampiran</span>
                            @endif
                        </div>
                        <form action="{{ route('attendance.homeroom-permits.destroy', $p) }}" method="POST" onsubmit="return confirm('Hapus izin ini?')" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-link text-danger p-0 text-decoration-none" style="font-size: 0.72rem;">
                                <i class="ti ti-trash me-0.5"></i> Hapus
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="card border-0 shadow-sm rounded-3 py-4 text-center text-muted">
                <i class="ti ti-clipboard-check fs-1 text-success d-block mb-1"></i>
                <div class="small fw-semibold">Belum ada catatan izin siswa pada kelas ini.</div>
            </div>
        @endforelse

        @if($permits->hasPages())
            <div class="d-flex justify-content-center mt-2">
                {{ $permits->links() }}
            </div>
        @endif
    </div>
@endif
@endsection
