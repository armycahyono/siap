@extends('layouts.app')

@section('title', 'Buku Tamu Piket')

@section('content')
<div class="container-xl">
    <!-- Header (Model 2: Hero Context Banner) -->
    @include('layouts._hero_header', [
        'title' => 'Buku Tamu Piket',
        'subtitle' => 'Pencatatan kunjungan tamu dinas, orang tua siswa, instansi mitra, dan masyarakat umum.',
        'icon' => 'ti ti-address-book',
        'category' => 'LAYANAN HUMAS & TAMU',
        'actions' => '<button type="button" class="btn btn-sm btn-success text-white fw-bold shadow-xs" data-bs-toggle="modal" data-bs-target="#modal-add-guest"><i class="ti ti-user-plus me-1"></i> Catat Tamu Baru</button>'
    ])

    <!-- Filter Form -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body py-2">
            <form action="{{ route('piket.guests.index') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <input type="date" name="date" class="form-control form-control-sm" value="{{ $date }}">
                </div>
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control form-control-sm" value="{{ $search }}" placeholder="Cari nama tamu, instansi, keperluan...">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-secondary">Filter</button>
                    @if($date || $search)
                        <a href="{{ route('piket.guests.index') }}" class="btn btn-sm btn-ghost-secondary">Reset</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Table Guests -->
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-vcenter table-hover card-table">
                <thead class="table-light">
                    <tr>
                        <th>Waktu Kunjungan</th>
                        <th>Nama Tamu & Kontak</th>
                        <th>Instansi / Lembaga</th>
                        <th>Keperluan & Bertemu</th>
                        <th class="text-center">No. Badge</th>
                        <th class="text-center">Status Kunjungan</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($guests as $g)
                        <tr>
                            <td>
                                <div class="fw-bold text-dark">{{ $g->visit_date?->format('d/m/Y') }}</div>
                                <div class="text-muted small">
                                    <i class="ti ti-login me-1 text-success"></i>Masuk: {{ substr($g->check_in_time, 0, 5) }} WIB
                                </div>
                                @if($g->check_out_time)
                                    <div class="text-muted small">
                                        <i class="ti ti-logout me-1 text-danger"></i>Keluar: {{ substr($g->check_out_time, 0, 5) }} WIB
                                    </div>
                                @endif
                            </td>
                            <td>
                                <div class="fw-bold text-dark fs-3">{{ $g->guest_name }}</div>
                                @if($g->phone)
                                    <span class="text-muted small"><i class="ti ti-phone me-1"></i>{{ $g->phone }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $g->institution ?: 'Masyarakat / Orang Tua' }}</span>
                            </td>
                            <td>
                                <div class="text-dark fw-bold small">{{ $g->purpose }}</div>
                                <div class="text-secondary small mt-1"><strong>Menemui:</strong> {{ $g->meet_with }}</div>
                            </td>
                            <td class="text-center">
                                @if($g->badge_number)
                                    <span class="badge bg-purple-lt fw-bold">{{ $g->badge_number }}</span>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($g->check_out_time)
                                    <span class="badge bg-secondary-lt fw-bold"><i class="ti ti-check me-1"></i> Selesai</span>
                                @else
                                    <span class="badge bg-success text-white fw-bold"><i class="ti ti-clock me-1"></i> Sedang Berkunjung</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if(!$g->check_out_time)
                                    <form action="{{ route('piket.guests.checkout', $g) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Catat Selesai Berkunjung">
                                            <i class="ti ti-logout me-1"></i> Check Out
                                        </button>
                                    </form>
                                @endif
                                <form action="{{ route('piket.guests.destroy', $g) }}" method="POST" onsubmit="return confirm('Hapus data tamu ini?')" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-ghost-danger">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="ti ti-users fs-1 d-block mb-2"></i>
                                Belum ada data buku tamu untuk tanggal atau pencarian ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($guests->hasPages())
            <div class="card-footer py-2">
                {{ $guests->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Modal Catat Tamu -->
<div class="modal fade" id="modal-add-guest" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('piket.guests.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Catat Buku Tamu Piket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label required">Tanggal</label>
                            <input type="date" name="visit_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label required">Jam Masuk (WIB)</label>
                            <input type="time" name="check_in_time" class="form-control" value="{{ date('H:i') }}" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Nama Tamu</label>
                        <input type="text" name="guest_name" class="form-control" placeholder="Nama lengkap tamu" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Instansi / Asal Lembaga / Hubungan</label>
                        <input type="text" name="institution" class="form-control" placeholder="Contoh: Dinas Pendidikan / Wali dari Siswa X / PT. ABC">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Nomor HP / WhatsApp</label>
                            <input type="text" name="phone" class="form-control" placeholder="08xxxxxxxxxx">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Nomor Kalung / Badge Tamu</label>
                            <input type="text" name="badge_number" class="form-control" placeholder="Contoh: T-05">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Keperluan Kunjungan</label>
                        <textarea name="purpose" class="form-control" rows="2" placeholder="Tuliskan tujuan kunjungan tamu..." required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Menemui Siapa</label>
                        <input type="text" name="meet_with" class="form-control" placeholder="Contoh: Kepala Sekolah / Waka Kurikulum / Siswa..." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Catatan Tambahan</label>
                        <input type="text" name="notes" class="form-control" placeholder="Catatan pos satpam / barang bawaan...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Simpan Buku Tamu</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
