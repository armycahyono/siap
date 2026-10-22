@extends('layouts.mobile')

@section('title', 'Catat Kasus Siswa — APK SIAP')
@section('page_title', 'Catat Kasus Siswa')
@section('page_subtitle', 'Buku Kedisiplinan Meja Piket')
@section('back_url', route('piket.dashboard'))

@section('content')
<form action="{{ route('piket.incidents.store') }}" method="POST" enctype="multipart/form-data">
    @csrf

    <!-- Identitas Siswa Terlibat -->
    <div class="card border-0 shadow-sm rounded-3 mb-2">
        <div class="card-body p-2 px-3">
            <label class="form-label small fw-bold text-dark mb-1">
                <i class="ti ti-user-search text-danger me-1"></i> Siswa yang Terlibat <span class="text-danger">*</span>
            </label>
            <div class="position-relative mb-2">
                <div class="d-flex align-items-center gap-1.5 w-100 m-0">
                    <!-- Kolom Teks Pencarian Pintar Siswa -->
                    <input type="text" 
                           id="inc_student_search" 
                           class="form-control form-control-sm flex-fill" 
                           style="font-size: 0.8rem; height: 36px; border-radius: 8px;" 
                           placeholder="Ketik nama atau NIS siswa..." 
                           autocomplete="off">

                    <!-- Tombol Cari (Kotak Biru/Merah Proporsional) -->
                    <button type="button" 
                            id="btn_do_inc_search" 
                            class="btn btn-danger d-flex align-items-center justify-content-center flex-shrink-0 shadow-sm" 
                            style="height: 36px; width: 42px; border-radius: 8px; padding: 0;" 
                            title="Cari Siswa">
                        <i class="ti ti-search fs-2"></i>
                    </button>

                    <!-- Tombol Reset (Border) -->
                    <button type="button" 
                            id="btn_clear_inc_search" 
                            class="btn btn-outline-secondary d-flex align-items-center justify-content-center flex-shrink-0" 
                            style="height: 36px; width: 36px; border-radius: 8px; padding: 0; display: none;" 
                            title="Hapus / Reset">
                        <i class="ti ti-x fs-2"></i>
                    </button>
                </div>

                <!-- Dropdown Hasil Pencarian Pintar -->
                <div id="inc_student_auto_list" 
                     class="position-absolute w-100 shadow-sm mt-1 bg-white border rounded-3 p-0" 
                     style="z-index: 1050; display: none; max-height: 220px; overflow-y: auto; box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;">
                </div>
            </div>

            <input type="hidden" name="student_id" id="inc_student_id" value="">

            <div class="mb-2">
                <label class="form-label small text-muted mb-0.5" style="font-size: 0.72rem;">Nama Siswa Terpilih / Manual:</label>
                <input type="text" name="student_name" id="inc_student_name" class="form-control form-control-sm fw-bold" placeholder="Nama Lengkap Siswa" required>
            </div>

            <div class="mb-1">
                <label class="form-label small text-muted mb-0.5" style="font-size: 0.72rem;">Kelas / Rombel:</label>
                <select name="classroom_id" id="inc_classroom_id" class="form-select form-select-sm fw-bold">
                    <option value="">-- Pilih Kelas --</option>
                    @foreach($classrooms as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- Waktu & Kategori Kejadian -->
    <div class="card border-0 shadow-sm rounded-3 mb-2">
        <div class="card-body p-2 px-3">
            <div class="mobile-grid-2 mb-2">
                <div>
                    <label class="form-label small fw-bold mb-1" style="font-size: 0.75rem;">Tanggal <span class="text-danger">*</span></label>
                    <input type="date" name="incident_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
                </div>
                <div>
                    <label class="form-label small fw-bold mb-1" style="font-size: 0.75rem;">Waktu (WIB) <span class="text-danger">*</span></label>
                    <input type="time" name="incident_time" class="form-control form-control-sm" value="{{ date('H:i') }}" required>
                </div>
            </div>

            <div class="mb-1">
                <label class="form-label small fw-bold mb-1" style="font-size: 0.75rem;">Kategori Kejadian <span class="text-danger">*</span></label>
                <select name="category" class="form-select form-select-sm" required style="font-size: 0.82rem;">
                    <option value="kedisiplinan">Kedisiplinan Umum</option>
                    <option value="seragam">Pelanggaran Seragam &amp; Rambut</option>
                    <option value="gadget_terlarang">HP / Gadget Saat KBM</option>
                    <option value="perkelahian">Pertikaian / Konflik</option>
                    <option value="rokok">Merokok / Vape</option>
                    <option value="kesehatan_pingsan">Kesehatan / Sakit di Kelas</option>
                    <option value="lainnya">Lain-lain</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Uraian Kronologi & Penanganan -->
    <div class="card border-0 shadow-sm rounded-3 mb-2">
        <div class="card-body p-2 px-3">
            <div class="mb-2">
                <label class="form-label small fw-bold mb-1" style="font-size: 0.75rem;">Kronologi Kejadian <span class="text-danger">*</span></label>
                <textarea name="description" class="form-control form-control-sm" rows="3" placeholder="Jelaskan ringkas apa yang terjadi di sekolah..." required style="font-size: 0.82rem;"></textarea>
            </div>

            <div class="mb-2">
                <label class="form-label small fw-bold mb-1" style="font-size: 0.75rem;">Tindakan di Meja Piket <span class="text-danger">*</span></label>
                <input type="text" name="action_taken" class="form-control form-control-sm" placeholder="Contoh: Pembinaan lisan, mediasi..." required style="font-size: 0.82rem;">
            </div>

            <div class="mb-2">
                <label class="form-label small fw-bold mb-1" style="font-size: 0.75rem;">Tindak Lanjut / Rujukan <span class="text-danger">*</span></label>
                <select name="follow_up_target" class="form-select form-select-sm" required style="font-size: 0.82rem;">
                    <option value="selesai_di_piket">Selesai di Meja Piket</option>
                    <option value="rujuk_wali_kelas">Diteruskan ke Wali Kelas</option>
                    <option value="rujuk_bk">Dirujuk ke Guru BK</option>
                    <option value="rujuk_kesiswaan">Dirujuk ke Tim Kesiswaan</option>
                </select>
            </div>

            <div class="mb-1">
                <label class="form-label small fw-bold mb-1" style="font-size: 0.75rem;">Barang yang Diamankan (Opsional)</label>
                <input type="text" name="confiscated_items" class="form-control form-control-sm" placeholder="Contoh: HP Merk X, Korek..." style="font-size: 0.82rem;">
            </div>
        </div>
    </div>

    <!-- Bukti Foto -->
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body p-2 px-3">
            <label class="form-label small fw-bold mb-1" style="font-size: 0.75rem;">
                <i class="ti ti-camera me-1"></i> Foto Bukti / Dokumentasi (Opsional)
            </label>
            <input type="file" name="evidence_photo" class="form-control form-control-sm" accept="image/*" capture="environment">
        </div>
    </div>

    <!-- Tombol Simpan -->
    <div class="mb-4">
        <button type="submit" class="btn btn-danger w-100 py-2.5 fw-bold rounded-pill shadow-sm d-flex align-items-center justify-content-center gap-2" style="font-size: 0.95rem;">
            <i class="ti ti-device-floppy fs-2"></i>
            <span>Simpan Catatan Kasus</span>
        </button>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('inc_student_search');
    const autoList = document.getElementById('inc_student_auto_list');
    const searchBtn = document.getElementById('btn_do_inc_search');
    const clearBtn = document.getElementById('btn_clear_inc_search');
    const studentIdInput = document.getElementById('inc_student_id');
    const studentNameInput = document.getElementById('inc_student_name');
    const classroomSelect = document.getElementById('inc_classroom_id');

    let debounceTimer;

    function triggerSearch(immediate = false) {
        const query = searchInput.value.trim();
        clearBtn.style.display = query ? 'flex' : 'none';

        clearTimeout(debounceTimer);
        if (query.length < 2) {
            autoList.style.display = 'none';
            return;
        }

        const runFetch = () => {
            autoList.innerHTML = '<div class="list-group-item text-muted small p-2 text-center"><span class="spinner-border spinner-border-sm me-1"></span> Mencari data siswa...</div>';
            autoList.style.display = 'block';

            fetch(`{{ route('piket.students.search') }}?q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    autoList.innerHTML = '';
                    if (data.length === 0) {
                        autoList.innerHTML = '<div class="list-group-item text-muted small p-2 text-center">Siswa tidak ditemukan</div>';
                        autoList.style.display = 'block';
                        return;
                    }
                    data.forEach(s => {
                        const item = document.createElement('a');
                        item.href = 'javascript:void(0)';
                        item.style.cssText = 'display: block; padding: 6px 12px; text-decoration: none; border-bottom: 1px solid #f1f5f9; background: #ffffff; cursor: pointer; transition: background 0.15s ease;';
                        item.innerHTML = `
                            <div style="font-weight: 700; color: #1e293b; font-size: 0.82rem; line-height: 1.2; margin: 0 0 2px 0;">${s.name}</div>
                            <div style="font-size: 0.68rem; color: #dc2626; font-weight: 600; line-height: 1; margin: 0;">Kelas ${s.classroom_name || '-'}</div>
                        `;
                        item.addEventListener('mouseenter', () => item.style.background = '#fef2f2');
                        item.addEventListener('mouseleave', () => item.style.background = '#ffffff');
                        item.addEventListener('click', function (e) {
                            e.preventDefault();
                            studentIdInput.value = s.id;
                            studentNameInput.value = s.name;
                            if (s.classroom_id) {
                                classroomSelect.value = s.classroom_id;
                            }
                            searchInput.value = s.name;
                            autoList.style.display = 'none';
                        });
                        autoList.appendChild(item);
                    });
                    autoList.style.display = 'block';
                })
                .catch(() => {
                    autoList.style.display = 'none';
                });
        };

        if (immediate) {
            runFetch();
        } else {
            debounceTimer = setTimeout(runFetch, 250);
        }
    }

    searchInput.addEventListener('input', function () {
        triggerSearch(false);
    });

    if (searchBtn) {
        searchBtn.addEventListener('click', function (e) {
            e.preventDefault();
            triggerSearch(true);
        });
    }

    searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            triggerSearch(true);
        }
    });

    clearBtn.addEventListener('click', function () {
        searchInput.value = '';
        clearBtn.style.display = 'none';
        autoList.style.display = 'none';
        searchInput.focus();
    });

    document.addEventListener('click', function (e) {
        if (!searchInput.contains(e.target) && !autoList.contains(e.target)) {
            autoList.style.display = 'none';
        }
    });
});
</script>
@endpush
