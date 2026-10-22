@extends('layouts.app')

@section('title', 'Edit Data Guru')

@section('content')
<div class="row g-3 mb-4 align-items-center">
    <div class="col">
        <h2 class="page-title text-navy fw-bold" style="color: #102d52;">
            <i class="ti ti-pencil me-2"></i> Edit Data Guru: {{ $teacher->name }}
        </h2>
        <div class="text-muted small">Perbarui data profil atau status aktif guru.</div>
    </div>
    <div class="col-auto">
        <a href="{{ route('teachers.index') }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i> Kembali ke Data Guru
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm" style="max-width: 850px;">
    <div class="card-body p-4">
        <form action="{{ route('teachers.update', $teacher) }}" method="POST">
            @csrf
            @method('PUT')

            <h4 class="fw-bold text-dark mb-3">1. Data Profil & Biodata Guru</h4>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label required">Nama Lengkap & Gelar</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $teacher->name) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold text-primary">Singkatan Nama (Tanpa Gelar)</label>
                    <input type="text" name="short_name" class="form-control border-primary" value="{{ old('short_name', $teacher->short_name ?: $teacher->formatted_short_name) }}" placeholder="Contoh: Yoga Anggorina K. K.">
                    <div class="form-text small">Digunakan pada jadwal pelajaran & tampilan ringkas.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold text-dark">NIK (Nomor Induk Kependudukan)</label>
                    <input type="text" name="nik" class="form-control" value="{{ old('nik', $teacher->nik) }}" placeholder="16 digit NIK KTP..." maxlength="16">
                    <div class="form-text small">Nomor KTP kependudukan (16 digit).</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold text-teal">NIGS (Nomor Induk Guru Sekolah)</label>
                    <input type="text" name="nigs_fix" class="form-control" value="{{ old('nigs_fix', $teacher->nigs_fix ?? $teacher->nigs) }}" placeholder="Contoh: 48902...">
                    <div class="form-text small">Nomor induk resmi guru di sekolah.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Nomor WhatsApp / HP</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone', $teacher->phone) }}" placeholder="08xxxxxxxxxx">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tempat, Tanggal Lahir</label>
                    <input type="text" name="birth_info" class="form-control" value="{{ old('birth_info', $teacher->birth_info) }}" placeholder="Contoh: Kediri, 18 Maret 1980">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Pendidikan Terakhir</label>
                    <input type="text" name="education_level" list="educationList" class="form-control" value="{{ old('education_level', $teacher->education_level) }}" placeholder="Contoh: Sarjana (S1), Magister (S2)...">
                    <datalist id="educationList">
                        <option value="D3 Politeknik">
                        <option value="Sarjana">
                        <option value="Magister Pendidikan">
                        <option value="Magister">
                        <option value="Doktor">
                        <option value="SMA / SMK">
                    </datalist>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Program Studi / Jurusan</label>
                    <input type="text" name="major" class="form-control" value="{{ old('major', $teacher->major) }}" placeholder="Contoh: Pendidikan Teknik Mesin">
                </div>

                <div class="col-12">
                    <label class="form-label">Alamat Rumah Lengkap</label>
                    <textarea name="address" class="form-control" rows="2" placeholder="Dusun, RT/RW, Desa, Kecamatan, Kabupaten/Kota, Provinsi, Kode Pos...">{{ old('address', $teacher->address) }}</textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label text-muted">NIP <small>(Opsional, khusus PNS/PPPK)</small></label>
                    <input type="text" name="nip" class="form-control" value="{{ old('nip', $teacher->nip) }}" placeholder="18 digit NIP jika berstatus PNS...">
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted">NUPTK <small>(Opsional, jika sudah memiliki)</small></label>
                    <input type="text" name="nuptk" class="form-control" value="{{ old('nuptk', $teacher->nuptk) }}" placeholder="16 digit NUPTK Kemendikbud...">
                </div>
            </div>

            <h4 class="fw-bold text-dark mb-3">2. Akun Login & Akses</h4>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label required">Username</label>
                    <input type="text" name="username" class="form-control" value="{{ old('username', $teacher->username) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $teacher->email) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ganti Password <span class="text-muted">(Kosongkan jika tidak diubah)</span></label>
                    <input type="password" name="password" class="form-control" placeholder="Biarkan kosong jika tidak diubah">
                </div>
                <div class="col-md-6 d-flex align-items-center">
                    <label class="form-check mt-3">
                        <input type="checkbox" name="is_active" class="form-check-input" value="1" {{ $teacher->is_active ? 'checked' : '' }}>
                        <span class="form-check-label fw-bold">Akun Aktif (Dapat Login)</span>
                    </label>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('teachers.index') }}" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">
                    <i class="ti ti-device-floppy me-1"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
