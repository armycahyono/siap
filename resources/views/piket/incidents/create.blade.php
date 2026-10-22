@extends('layouts.app')

@section('title', 'Catat Kasus / Pelanggaran Siswa')

@section('content')
<div class="container-xl">
    <div class="page-header d-print-none mb-3">
        <div class="row align-items-center">
            <div class="col">
                <div class="page-pretitle text-muted text-uppercase">Piket Harian &bull; Kasus Siswa</div>
                <h2 class="page-title text-dark fw-bold d-flex align-items-center">
                    <i class="ti ti-alert-triangle text-danger me-2 fs-1"></i>
                    Catat Kejadian / Kasus Siswa
                </h2>
                <div class="text-secondary mt-1">
                    Dokumentasikan pelanggaran tata tertib, konflik, atau kejadian darurat siswa.
                </div>
            </div>
            <div class="col-auto ms-auto">
                <a href="{{ route('piket.incidents.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="ti ti-arrow-left me-1"></i> Kembali ke Buku Kasus
                </a>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <form action="{{ route('piket.incidents.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="card-body p-4">
                        <!-- Data Siswa (Autocomplete) -->
                        <div class="mb-4 p-3 bg-light rounded border position-relative">
                            <label class="form-label required fw-bold text-dark">
                                <i class="ti ti-user-search text-primary me-1"></i> Siswa yang Terlibat
                            </label>
                            <div class="position-relative">
                                <div class="input-group">
                                    <span class="input-group-text bg-white text-muted border-end-0">
                                        <i class="ti ti-search"></i>
                                    </span>
                                    <input type="text" id="incident_student_search" class="form-control border-start-0" placeholder="Ketik nama atau NIS siswa untuk mencari..." autocomplete="off">
                                    <button type="button" class="btn btn-outline-secondary" id="btn_clear_inc_search" style="display: none;" title="Hapus">
                                        <i class="ti ti-x"></i>
                                    </button>
                                </div>
                                <div id="incident_student_auto_list" class="list-group position-absolute w-100 shadow-lg mt-1 bg-white border rounded-3" style="z-index: 1050; display: none; max-height: 280px; overflow-y: auto; background-color: #ffffff !important;"></div>
                            </div>

                            <input type="hidden" name="student_id" id="inc_student_id" value="">

                            <div class="row g-2 mt-2">
                                <div class="col-md-7">
                                    <label class="form-label small text-muted">Nama Siswa Terpilih / Manual:</label>
                                    <input type="text" name="student_name" id="inc_student_name" class="form-control fw-bold" placeholder="Nama Lengkap Siswa" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small text-muted">Kelas / Rombel:</label>
                                    <select name="classroom_id" id="inc_classroom_id" class="form-select fw-bold">
                                        <option value="">-- Pilih Kelas --</option>
                                        @foreach($classrooms as $c)
                                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Waktu & Kategori -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label required">Tanggal Kejadian</label>
                                <input type="date" name="incident_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Waktu Kejadian (WIB)</label>
                                <input type="time" name="incident_time" class="form-control" value="{{ date('H:i') }}" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label required">Kategori Kejadian / Kasus</label>
                            <select name="category" class="form-select" required>
                                <option value="kedisiplinan">Kedisiplinan Umum</option>
                                <option value="seragam">Pelanggaran Seragam / Atribut / Rambut</option>
                                <option value="gadget_terlarang">HP / Penggunaan Gadget Terlarang</option>
                                <option value="perkelahian">Pertikaian / Perkelahian Antar Siswa</option>
                                <option value="rokok">Merokok / Vape di Area Sekolah</option>
                                <option value="kesehatan_pingsan">Kesehatan / Sakit / Pingsan saat KBM</option>
                                <option value="lainnya">Lain-lain</option>
                            </select>
                        </div>

                        <!-- Uraian Kejadian -->
                        <div class="mb-3">
                            <label class="form-label required">Uraian / Kronologi Kejadian</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Jelaskan secara singkat apa yang terjadi, lokasi kejadian, dan pihak yang terlibat..." required></textarea>
                        </div>

                        <!-- Tindakan Piket -->
                        <div class="mb-3">
                            <label class="form-label required">Tindakan Awal Guru Piket</label>
                            <textarea name="action_taken" class="form-control" rows="2" placeholder="Tindakan pembinaan atau pertolongan pertama yang dilakukan di meja piket..." required></textarea>
                        </div>

                        <!-- Barang Sitaan -->
                        <div class="mb-3">
                            <label class="form-label">Barang yang Disita Sementara (Jika Ada)</label>
                            <input type="text" name="confiscated_items" class="form-control" placeholder="Contoh: 1 unit Smartphone Samsung, 1 bungkus rokok, sabuk non-standar...">
                        </div>

                        <!-- Alur Rujukan / Tindak Lanjut -->
                        <div class="mb-4">
                            <label class="form-label required fw-bold text-dark">Alur Tindak Lanjut Penanganan</label>
                            <div class="row g-2">
                                <div class="col-sm-6 col-md-3">
                                    <label class="form-selectgroup-item w-100">
                                        <input type="radio" name="follow_up_target" value="selesai_di_piket" class="form-selectgroup-input" checked>
                                        <span class="form-selectgroup-label text-center p-2">
                                            <i class="ti ti-check text-success d-block fs-2"></i>
                                            <span class="small fw-bold">Selesai di Piket</span>
                                        </span>
                                    </label>
                                </div>
                                <div class="col-sm-6 col-md-3">
                                    <label class="form-selectgroup-item w-100">
                                        <input type="radio" name="follow_up_target" value="rujuk_wali_kelas" class="form-selectgroup-input">
                                        <span class="form-selectgroup-label text-center p-2">
                                            <i class="ti ti-user-check text-blue d-block fs-2"></i>
                                            <span class="small fw-bold">Rujuk Wali Kelas</span>
                                        </span>
                                    </label>
                                </div>
                                <div class="col-sm-6 col-md-3">
                                    <label class="form-selectgroup-item w-100">
                                        <input type="radio" name="follow_up_target" value="rujuk_bk" class="form-selectgroup-input">
                                        <span class="form-selectgroup-label text-center p-2">
                                            <i class="ti ti-heart-handshake text-purple d-block fs-2"></i>
                                            <span class="small fw-bold">Rujuk Guru BK</span>
                                        </span>
                                    </label>
                                </div>
                                <div class="col-sm-6 col-md-3">
                                    <label class="form-selectgroup-item w-100">
                                        <input type="radio" name="follow_up_target" value="rujuk_kesiswaan" class="form-selectgroup-input">
                                        <span class="form-selectgroup-label text-center p-2">
                                            <i class="ti ti-shield-alert text-danger d-block fs-2"></i>
                                            <span class="small fw-bold">Tim Kesiswaan</span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Bukti Foto Kejadian -->
                        <div class="mb-4 p-3 bg-light rounded border">
                            <label class="form-label fw-bold text-dark d-flex align-items-center justify-content-between">
                                <span><i class="ti ti-camera text-primary me-1"></i> Bukti Berupa Foto (Opsional)</span>
                                <span class="badge bg-secondary-lt">Format: JPG, PNG, WEBP &bull; Maks. 5MB</span>
                            </label>
                            <input type="file" name="evidence_photo" id="evidence_photo_input" class="form-control @error('evidence_photo') is-invalid @enderror" accept="image/*">
                            @error('evidence_photo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text text-muted">
                                Lampirkan foto kondisi kejadian, barang bukti / sitaan, atau dokumentasi pembinaan siswa.
                            </div>
                            <div id="photo_preview_container" class="mt-3 text-center" style="display: none;">
                                <div class="card p-2 d-inline-block border shadow-sm position-relative">
                                    <img id="photo_preview_img" src="#" alt="Preview Foto" class="img-fluid rounded" style="max-height: 250px; object-fit: contain;">
                                    <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 rounded-circle" id="btn_clear_photo" title="Hapus Foto">
                                        <i class="ti ti-x"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Info Telegram Bot -->
                        <div class="alert alert-info border-0 d-flex align-items-center">
                            <i class="ti ti-brand-telegram fs-1 me-3 text-primary"></i>
                            <div>
                                <div class="fw-bold">Distribusi Notifikasi Telegram Otomatis:</div>
                                <div class="small">
                                    Laporan kasus beserta foto bukti akan otomatis diteruskan secara real-time ke:
                                    <ul class="mb-0 ps-3 mt-1">
                                        <li><strong>Grup Tim Piket Harian</strong> &amp; <strong>Petugas Piket Hari Ini</strong></li>
                                        <li><strong>Wali Kelas Siswa</strong> (japri Telegram)</li>
                                        <li><strong>Grup Kedisiplinan &amp; BK Sekolah</strong></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer bg-white text-end py-3">
                        <a href="{{ route('piket.incidents.index') }}" class="btn btn-outline-secondary me-2">Batal</a>
                        <button type="submit" class="btn btn-danger shadow-sm">
                            <i class="ti ti-device-floppy me-1"></i> Simpan & Kirim Laporan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('incident_student_search');
    const autoList = document.getElementById('incident_student_auto_list');
    const btnClearSearch = document.getElementById('btn_clear_inc_search');
    const studentIdField = document.getElementById('inc_student_id');
    const studentNameField = document.getElementById('inc_student_name');
    const classroomSelect = document.getElementById('inc_classroom_id');

    let debounceTimer;

    if (btnClearSearch) {
        btnClearSearch.addEventListener('click', function () {
            searchInput.value = '';
            studentIdField.value = '';
            studentNameField.value = '';
            autoList.style.display = 'none';
            btnClearSearch.style.display = 'none';
            searchInput.focus();
        });
    }

    searchInput.addEventListener('input', function () {
        const query = this.value.trim();
        clearTimeout(debounceTimer);

        if (btnClearSearch) {
            btnClearSearch.style.display = query.length > 0 ? 'block' : 'none';
        }

        if (query.length < 2) {
            autoList.style.display = 'none';
            autoList.innerHTML = '';
            return;
        }

        debounceTimer = setTimeout(() => {
            fetch(`{{ route('piket.students.search') }}?q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    autoList.innerHTML = '';
                    if (data.length === 0) {
                        autoList.innerHTML = `<div class="list-group-item bg-white text-muted small p-3 text-center border-0"><i class="ti ti-info-circle me-1"></i> Tidak ada siswa ditemukan. Silakan ketik manual di bawah.</div>`;
                        autoList.style.display = 'block';
                        return;
                    }

                    data.forEach(item => {
                        const div = document.createElement('a');
                        div.href = 'javascript:void(0)';
                        div.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 px-3 bg-white border-bottom';
                        div.innerHTML = `
                            <div>
                                <div class="fw-bold text-dark">${item.name}</div>
                                <div class="text-muted small">NIS: ${item.nis || '-'} &bull; NISN: ${item.nisn || '-'}</div>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-blue-lt">${item.classroom_name}</span>
                                <div class="text-secondary small" style="font-size: 0.72rem;">Wali: ${item.homeroom_teacher || '-'}</div>
                            </div>
                        `;
                        div.addEventListener('click', function () {
                            studentIdField.value = item.id;
                            studentNameField.value = item.name;
                            if (item.classroom_id) {
                                classroomSelect.value = item.classroom_id;
                            }
                            autoList.style.display = 'none';
                            searchInput.value = item.name;
                            if (btnClearSearch) btnClearSearch.style.display = 'block';
                        });
                        autoList.appendChild(div);
                    });
                    autoList.style.display = 'block';
                })
                .catch(() => {
                    autoList.style.display = 'none';
                });
        }, 250);
    });

    document.addEventListener('click', function (e) {
        if (!searchInput.contains(e.target) && !autoList.contains(e.target)) {
            autoList.style.display = 'none';
        }
    });

    // Preview Foto Bukti
    const photoInput = document.getElementById('evidence_photo_input');
    const photoPreviewContainer = document.getElementById('photo_preview_container');
    const photoPreviewImg = document.getElementById('photo_preview_img');
    const btnClearPhoto = document.getElementById('btn_clear_photo');

    if (photoInput && photoPreviewContainer && photoPreviewImg) {
        photoInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    photoPreviewImg.src = e.target.result;
                    photoPreviewContainer.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                photoPreviewContainer.style.display = 'none';
            }
        });

        if (btnClearPhoto) {
            btnClearPhoto.addEventListener('click', function () {
                photoInput.value = '';
                photoPreviewImg.src = '#';
                photoPreviewContainer.style.display = 'none';
            });
        }
    }
});
</script>
@endpush
