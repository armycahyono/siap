@extends('layouts.app')

@section('title', 'Tambah Guru Baru')

@section('content')
<div class="row g-3 mb-4 align-items-center">
    <div class="col">
        <h2 class="page-title text-navy fw-bold" style="color: #102d52;">
            <i class="ti ti-user-plus me-2"></i> Tambah Data Guru Baru
        </h2>
        <div class="text-muted small">Tambahkan profil guru serta akun login untuk akses aplikasi.</div>
    </div>
    <div class="col-auto">
        <a href="{{ route('teachers.index') }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i> Kembali ke Data Guru
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm" style="max-width: 850px;">
    <div class="card-body p-4">
        <form action="{{ route('teachers.store') }}" method="POST">
            @csrf

            <h4 class="fw-bold text-dark mb-3">1. Data Profil & Biodata Guru</h4>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label required">Nama Lengkap & Gelar</label>
                    <input type="text" name="name" class="form-control" placeholder="Contoh: Siti Rahmawati, S.Kom." value="{{ old('name') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold text-primary">Singkatan Nama (Tanpa Gelar)</label>
                    <input type="text" name="short_name" class="form-control border-primary" placeholder="Contoh: Siti Rahmawati" value="{{ old('short_name') }}">
                    <div class="form-text small">Kosongkan jika ingin disingkat otomatis tanpa gelar.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold text-dark">NIK (Nomor Induk Kependudukan)</label>
                    <input type="text" name="nik" class="form-control" placeholder="16 digit NIK KTP..." value="{{ old('nik') }}" maxlength="16">
                    <div class="form-text small">Nomor KTP kependudukan (16 digit).</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold text-teal">NIGS (Nomor Induk Guru Sekolah)</label>
                    <input type="text" name="nigs_fix" class="form-control" placeholder="Contoh: 48902..." value="{{ old('nigs_fix') }}">
                    <div class="form-text small">Nomor induk resmi guru di sekolah.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Nomor WhatsApp / HP</label>
                    <input type="text" name="phone" class="form-control" placeholder="08xxxxxxxxxx" value="{{ old('phone') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tempat, Tanggal Lahir</label>
                    <input type="text" name="birth_info" class="form-control" placeholder="Contoh: Kediri, 18 Maret 1980" value="{{ old('birth_info') }}">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Pendidikan Terakhir</label>
                    <input type="text" name="education_level" list="educationList" class="form-control" placeholder="Contoh: Sarjana (S1), Magister (S2)..." value="{{ old('education_level') }}">
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
                    <input type="text" name="major" class="form-control" placeholder="Contoh: Pendidikan Teknik Mesin" value="{{ old('major') }}">
                </div>

                <div class="col-12">
                    <label class="form-label">Alamat Rumah Lengkap</label>
                    <textarea name="address" class="form-control" rows="2" placeholder="Dusun, RT/RW, Desa, Kecamatan, Kabupaten/Kota, Provinsi, Kode Pos...">{{ old('address') }}</textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label text-muted">NIP <small>(Opsional, khusus PNS/PPPK)</small></label>
                    <input type="text" name="nip" class="form-control" placeholder="18 digit NIP jika berstatus PNS..." value="{{ old('nip') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted">NUPTK <small>(Opsional, jika sudah memiliki)</small></label>
                    <input type="text" name="nuptk" class="form-control" placeholder="16 digit NUPTK Kemendikbud..." value="{{ old('nuptk') }}">
                </div>
            </div>

            <h4 class="fw-bold text-dark mb-3">2. Akun Login Aplikasi</h4>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label required">Username</label>
                    <input type="text" name="username" class="form-control" placeholder="Contoh: sitirahma" value="{{ old('username') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="guru@smkpgrikras.sch.id" value="{{ old('email') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Password Awal</label>
                    <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('teachers.index') }}" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">
                    <i class="ti ti-device-floppy me-1"></i> Simpan Data Guru
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
