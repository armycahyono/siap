@extends('layouts.app')

@section('title', 'Notepad & Catatan Pengingat — Sistem Supervisi')

@section('content')
<div class="row g-2 align-items-center mb-3">
    <div class="col">
        <h2 class="page-title fw-bold text-dark">
            <i class="ti ti-notes me-2 text-warning"></i>
            Notepad & Catatan Pengingat
        </h2>
        <div class="text-muted small">
            Ruang catatan pribadi Anda untuk mencatat pengingat tugas, memo harian, atau hal penting yang perlu dituliskan.
        </div>
    </div>
    <div class="col-auto ms-auto d-print-none">
        <button type="button" class="btn btn-sm btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCreateNote">
            <i class="ti ti-plus me-1"></i> Buat Catatan Baru
        </button>
    </div>
</div>

<!-- Statistik Ringkasan -->
<div class="row row-cards mb-3 g-2">
    <div class="col-4">
        <div class="card card-sm border-0 shadow-sm h-100">
            <div class="card-body py-2 px-3">
                <div class="d-flex align-items-center">
                    <span class="avatar avatar-sm bg-blue-lt text-blue rounded-2 me-2">
                        <i class="ti ti-notes fs-2"></i>
                    </span>
                    <div>
                        <div class="text-muted small" style="font-size: 0.75rem;">Total Catatan</div>
                        <div class="h3 mb-0 fw-bold text-dark">{{ $stats['total'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-4">
        <div class="card card-sm border-0 shadow-sm h-100">
            <div class="card-body py-2 px-3">
                <div class="d-flex align-items-center">
                    <span class="avatar avatar-sm bg-warning-lt text-warning rounded-2 me-2">
                        <i class="ti ti-pin fs-2"></i>
                    </span>
                    <div>
                        <div class="text-muted small" style="font-size: 0.75rem;">Disematkan</div>
                        <div class="h3 mb-0 fw-bold text-warning">{{ $stats['pinned'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-4">
        <div class="card card-sm border-0 shadow-sm h-100">
            <div class="card-body py-2 px-3">
                <div class="d-flex align-items-center">
                    <span class="avatar avatar-sm bg-danger-lt text-danger rounded-2 me-2">
                        <i class="ti ti-alarm fs-2"></i>
                    </span>
                    <div>
                        <div class="text-muted small" style="font-size: 0.75rem;">Dengan Pengingat</div>
                        <div class="h3 mb-0 fw-bold text-danger">{{ $stats['reminder'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter & Pencarian -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2 px-3">
        <form action="{{ route('notepads.index') }}" method="GET" class="row g-2 align-items-center">
            <div class="col-12 col-md-5">
                <div class="input-icon">
                    <span class="input-icon-addon">
                        <i class="ti ti-search text-muted"></i>
                    </span>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari judul atau isi catatan..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select name="filter" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="all" {{ request('filter') == 'all' ? 'selected' : '' }}>Semua Catatan</option>
                    <option value="pinned" {{ request('filter') == 'pinned' ? 'selected' : '' }}>📌 Disematkan di Atas</option>
                    <option value="reminder" {{ request('filter') == 'reminder' ? 'selected' : '' }}>⏰ Memiliki Pengingat</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <select name="color" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Semua Warna</option>
                    <option value="yellow" {{ request('color') == 'yellow' ? 'selected' : '' }}>🟡 Kuning (Default)</option>
                    <option value="blue" {{ request('color') == 'blue' ? 'selected' : '' }}>🔵 Biru</option>
                    <option value="green" {{ request('color') == 'green' ? 'selected' : '' }}>🟢 Hijau</option>
                    <option value="red" {{ request('color') == 'red' ? 'selected' : '' }}>🔴 Merah</option>
                    <option value="purple" {{ request('color') == 'purple' ? 'selected' : '' }}>🟣 Ungu</option>
                    <option value="orange" {{ request('color') == 'orange' ? 'selected' : '' }}>🟠 Oranye</option>
                </select>
            </div>
            <div class="col-12 col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100" title="Cari">
                    <i class="ti ti-search"></i>
                </button>
                @if(request()->hasAny(['search', 'filter', 'color']))
                    <a href="{{ route('notepads.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset">
                        <i class="ti ti-x"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- Grid Daftar Catatan Sticky Notes -->
@php
    $colorThemes = [
        'yellow' => ['bg' => '#fffbeb', 'border' => '#fef08a', 'accent' => '#b45309', 'badge' => 'bg-warning-lt'],
        'blue'   => ['bg' => '#eff6ff', 'border' => '#bfdbfe', 'accent' => '#1d4ed8', 'badge' => 'bg-blue-lt'],
        'green'  => ['bg' => '#f0fdf4', 'border' => '#bbf7d0', 'accent' => '#15803d', 'badge' => 'bg-green-lt'],
        'red'    => ['bg' => '#fef2f2', 'border' => '#fecaca', 'accent' => '#b91c1c', 'badge' => 'bg-danger-lt'],
        'purple' => ['bg' => '#faf5ff', 'border' => '#e9d5ff', 'accent' => '#7e22ce', 'badge' => 'bg-purple-lt'],
        'orange' => ['bg' => '#fff7ed', 'border' => '#fed7aa', 'accent' => '#c2410c', 'badge' => 'bg-orange-lt'],
    ];
@endphp

@if($notes->isEmpty())
    <div class="card border-0 shadow-sm py-5 text-center">
        <div class="card-body">
            <div class="avatar avatar-xl bg-yellow-lt text-warning rounded-circle mx-auto mb-3">
                <i class="ti ti-note-off fs-1"></i>
            </div>
            <h3 class="fw-bold text-dark mb-1">Belum Ada Catatan</h3>
            <p class="text-muted small mb-3">
                {{ request()->hasAny(['search', 'filter', 'color']) ? 'Tidak ditemukan catatan yang cocok dengan pencarian Anda.' : 'Tuliskan catatan pengingat, to-do list, atau memo kecil pertama Anda sekarang.' }}
            </p>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateNote">
                <i class="ti ti-plus me-1"></i> Buat Catatan Sekarang
            </button>
        </div>
    </div>
@else
    <div class="row row-cards g-3">
        @foreach($notes as $note)
            @php
                $theme = $colorThemes[$note->color] ?? $colorThemes['yellow'];
            @endphp
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card shadow-sm h-100 position-relative note-card" style="background-color: {{ $theme['bg'] }}; border: 1px solid {{ $theme['border'] }}; border-radius: 12px; transition: transform 0.2s ease, box-shadow 0.2s ease;">
                    <div class="card-body d-flex flex-column p-3">
                        <!-- Top Header Note: Pin & Action -->
                        <div class="d-flex align-items-start justify-content-between mb-2">
                            <div class="d-flex align-items-center flex-wrap gap-1 pe-2">
                                @if($note->is_pinned)
                                    <span class="badge bg-warning text-dark border-0 shadow-xs" title="Disematkan di atas">
                                        <i class="ti ti-pin me-1"></i> Dipin
                                    </span>
                                @endif

                                @if($note->reminder_date)
                                    @php
                                        $isPast = $note->reminder_date->isPast();
                                    @endphp
                                    <span class="badge {{ $isPast ? 'bg-danger text-white' : 'bg-primary text-white' }}" title="Waktu Pengingat">
                                        <i class="ti ti-alarm me-1"></i> {{ $note->reminder_date->translatedFormat('d M Y, H:i') }}
                                        @if($isPast)
                                            <span class="ms-1 small opacity-75">(Lewat)</span>
                                        @endif
                                    </span>
                                @endif
                            </div>

                            <!-- Menu Dropdown Aksi -->
                            <div class="dropdown">
                                <button type="button" class="btn btn-sm btn-icon btn-ghost-secondary p-0 border-0 shadow-none" data-bs-toggle="dropdown" aria-label="Aksi Catatan">
                                    <i class="ti ti-dots-vertical fs-3 text-muted"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <form action="{{ route('notepads.pin', $note) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="dropdown-item">
                                            <i class="ti {{ $note->is_pinned ? 'ti-pinned-off' : 'ti-pin' }} me-2 text-warning"></i>
                                            {{ $note->is_pinned ? 'Lepas Sematan' : 'Sematkan di Atas' }}
                                        </button>
                                    </form>
                                    <a href="#" class="dropdown-item btn-edit-note"
                                       data-id="{{ $note->id }}"
                                       data-title="{{ $note->title }}"
                                       data-content="{{ $note->content }}"
                                       data-color="{{ $note->color }}"
                                       data-pinned="{{ $note->is_pinned ? '1' : '0' }}"
                                       data-reminder="{{ $note->reminder_date ? $note->reminder_date->format('Y-m-d\TH:i') : '' }}"
                                       data-action="{{ route('notepads.update', $note) }}">
                                        <i class="ti ti-edit me-2 text-primary"></i> Edit Catatan
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <form action="{{ route('notepads.destroy', $note) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus catatan ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="dropdown-item text-danger">
                                            <i class="ti ti-trash me-2"></i> Hapus
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Judul Catatan -->
                        <h4 class="fw-bold mb-2" style="color: {{ $theme['accent'] }}; font-size: 1.05rem;">
                            {{ $note->title }}
                        </h4>

                        <!-- Isi Catatan -->
                        <div class="text-dark small flex-grow-1 mb-3 note-content-body" style="white-space: pre-line; word-break: break-word; line-height: 1.55; opacity: 0.9;">{{ $note->content }}</div>

                        <!-- Footer Catatan: Waktu Update -->
                        <div class="d-flex align-items-center justify-content-between pt-2 border-top border-black-subtle mt-auto" style="border-color: rgba(0,0,0,0.08) !important;">
                            <span class="text-muted small" style="font-size: 0.72rem;">
                                <i class="ti ti-clock me-1"></i> {{ $note->updated_at->diffForHumans() }}
                            </span>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-icon btn-ghost-secondary p-1 border-0 btn-copy-note" title="Salin Catatan" data-text="{{ $note->title . "\n\n" . $note->content }}">
                                    <i class="ti ti-copy text-muted fs-3"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-icon btn-ghost-primary p-1 border-0 btn-edit-note"
                                   data-id="{{ $note->id }}"
                                   data-title="{{ $note->title }}"
                                   data-content="{{ $note->content }}"
                                   data-color="{{ $note->color }}"
                                   data-pinned="{{ $note->is_pinned ? '1' : '0' }}"
                                   data-reminder="{{ $note->reminder_date ? $note->reminder_date->format('Y-m-d\TH:i') : '' }}"
                                   data-action="{{ route('notepads.update', $note) }}"
                                   title="Edit">
                                    <i class="ti ti-edit fs-3"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Paginasi -->
    <div class="mt-4 d-flex justify-content-center">
        {{ $notes->links() }}
    </div>
@endif

<!-- Modal Tambah Catatan Baru -->
<div class="modal modal-blur fade" id="modalCreateNote" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
            <form action="{{ route('notepads.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-primary text-white py-3">
                    <h5 class="modal-title fw-bold">
                        <i class="ti ti-notes me-1"></i> Tulis Catatan Baru
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label required fw-semibold small">Judul Catatan</label>
                        <input type="text" name="title" class="form-control form-control-sm" placeholder="Contoh: Rapat Evaluasi Kurikulum..." required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Isi Catatan / Memo</label>
                        <textarea name="content" class="form-control form-control-sm" rows="5" placeholder="Tulis catatan, to-do list, atau hal penting di sini..."></textarea>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small">Warna Kertas Note</label>
                            <select name="color" class="form-select form-select-sm">
                                <option value="yellow">🟡 Kuning (Default)</option>
                                <option value="blue">🔵 Biru</option>
                                <option value="green">🟢 Hijau</option>
                                <option value="red">🔴 Merah</option>
                                <option value="purple">🟣 Ungu</option>
                                <option value="orange">🟠 Oranye</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small">Tanggal & Waktu Pengingat (Opsional)</label>
                            <input type="datetime-local" name="reminder_date" class="form-control form-control-sm">
                        </div>
                    </div>

                    <div class="form-check form-switch mb-1">
                        <input class="form-check-input" type="checkbox" name="is_pinned" value="1" id="createPinnedCheck">
                        <label class="form-check-label small fw-semibold" for="createPinnedCheck">
                            📌 Sematkan di atas (Pin to top)
                        </label>
                    </div>
                </div>
                <div class="modal-footer py-2 px-3 bg-light">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="ti ti-device-floppy me-1"></i> Simpan Catatan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Catatan -->
<div class="modal modal-blur fade" id="modalEditNote" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
            <form id="formEditNote" action="" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header bg-dark text-white py-3">
                    <h5 class="modal-title fw-bold">
                        <i class="ti ti-edit me-1"></i> Edit Catatan
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label required fw-semibold small">Judul Catatan</label>
                        <input type="text" name="title" id="editNoteTitle" class="form-control form-control-sm" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Isi Catatan / Memo</label>
                        <textarea name="content" id="editNoteContent" class="form-control form-control-sm" rows="5"></textarea>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small">Warna Kertas Note</label>
                            <select name="color" id="editNoteColor" class="form-select form-select-sm">
                                <option value="yellow">🟡 Kuning (Default)</option>
                                <option value="blue">🔵 Biru</option>
                                <option value="green">🟢 Hijau</option>
                                <option value="red">🔴 Merah</option>
                                <option value="purple">🟣 Ungu</option>
                                <option value="orange">🟠 Oranye</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small">Tanggal & Waktu Pengingat</label>
                            <input type="datetime-local" name="reminder_date" id="editNoteReminder" class="form-control form-control-sm">
                        </div>
                    </div>

                    <div class="form-check form-switch mb-1">
                        <input class="form-check-input" type="checkbox" name="is_pinned" value="1" id="editNotePinned">
                        <label class="form-check-label small fw-semibold" for="editNotePinned">
                            📌 Sematkan di atas (Pin to top)
                        </label>
                    </div>
                </div>
                <div class="modal-footer py-2 px-3 bg-light">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="ti ti-device-floppy me-1"></i> Perbarui Catatan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Modal Edit Trigger
        var editBtns = document.querySelectorAll('.btn-edit-note');
        var formEdit = document.getElementById('formEditNote');
        var editTitle = document.getElementById('editNoteTitle');
        var editContent = document.getElementById('editNoteContent');
        var editColor = document.getElementById('editNoteColor');
        var editReminder = document.getElementById('editNoteReminder');
        var editPinned = document.getElementById('editNotePinned');
        var modalEdit = new bootstrap.Modal(document.getElementById('modalEditNote'));

        editBtns.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var action = this.getAttribute('data-action');
                var title = this.getAttribute('data-title');
                var content = this.getAttribute('data-content');
                var color = this.getAttribute('data-color');
                var reminder = this.getAttribute('data-reminder');
                var pinned = this.getAttribute('data-pinned') === '1';

                formEdit.setAttribute('action', action);
                editTitle.value = title;
                editContent.value = content || '';
                editColor.value = color || 'yellow';
                editReminder.value = reminder || '';
                editPinned.checked = pinned;

                modalEdit.show();
            });
        });

        // Copy Note Button
        var copyBtns = document.querySelectorAll('.btn-copy-note');
        copyBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var text = this.getAttribute('data-text');
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text).then(function() {
                        var originalHtml = btn.innerHTML;
                        btn.innerHTML = '<i class="ti ti-check text-success fs-3"></i>';
                        setTimeout(function() {
                            btn.innerHTML = originalHtml;
                        }, 1500);
                    });
                }
            });
        });
    });
</script>
@endpush
