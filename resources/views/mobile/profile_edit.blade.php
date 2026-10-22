@extends('layouts.mobile')

@section('title', 'Edit Data Pribadi — APK SIAP')
@section('page_title', 'Edit Profil GTK')
@section('page_subtitle', 'Perbarui Informasi & Foto Akun')
@section('back_url', route('profile'))

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
<style>
    @keyframes slideUpCropModal {
        from { transform: translateY(100%); }
        to { transform: translateY(0); }
    }
    .cropper-container {
        overflow: hidden !important;
        border-radius: 12px !important;
        max-width: 100% !important;
        margin: 0 auto !important;
    }
    .cropper-view-box {
        border-radius: 16px !important;
        outline: 2.5px solid #38bdf8 !important;
    }
    .cropper-face {
        border-radius: 16px !important;
        background-color: transparent !important;
    }
    .cropper-modal {
        background-color: rgba(15, 23, 42, 0.75) !important;
    }
    .cropper-dashed,
    .cropper-point.point-se,
    .cropper-line {
        display: none !important;
    }
</style>
@endpush

@section('content')
<div class="card border-0 shadow-sm rounded-3 mb-3">
    <div class="card-body p-3">
        <form id="formEditProfile" action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <!-- Fallback Cropped Base64 String jika Browser Tidak Mendukung DataTransfer -->
            <input type="hidden" name="avatar_cropped_base64" id="avatarCroppedBase64" value="">

            <!-- Bagian Unggah & Sesuaikan Letak Foto Profil -->
            <div class="d-flex align-items-center gap-3 p-2.5 mb-3 rounded-3" style="background: #f8fafc; border: 1.5px dashed #cbd5e1;">
                <div class="position-relative flex-shrink-0">
                    @if($user->avatar)
                        <img id="avatarEditPreviewImg" src="{{ asset('storage/' . $user->avatar) }}" alt="{{ $user->name }}" 
                             class="border shadow-sm d-block" 
                             style="width: 72px; height: 72px; border-radius: 12px; object-fit: cover; border-color: #cbd5e1 !important;">
                    @else
                        <div id="avatarEditPreviewPlaceholder" 
                             class="border shadow-sm d-flex align-items-center justify-content-center text-white fw-bold fs-2" 
                             style="width: 72px; height: 72px; border-radius: 12px; background: linear-gradient(135deg, #102d52 0%, #195b9e 100%);">
                            {{ strtoupper(substr($user->name, 0, 2)) }}
                        </div>
                    @endif
                </div>
                <div class="flex-fill">
                    <label class="form-label fw-bold text-dark small mb-1" style="font-size: 0.8rem;">Foto Profil GTK</label>
                    <input type="file" id="editAvatarFileInput" name="avatar" accept="image/jpeg,image/png,image/jpg,image/webp" class="d-none" onclick="this.value=null;" onchange="startAvatarCropper(this)">
                    <button type="button" 
                            onclick="document.getElementById('editAvatarFileInput').click()" 
                            class="btn btn-sm btn-outline-primary rounded-pill py-1 px-2.5 fw-semibold d-inline-flex align-items-center gap-1" 
                            style="font-size: 0.74rem;">
                        <i class="ti ti-crop fs-3"></i>
                        <span id="labelPilihFoto">Pilih &amp; Atur Posisi Foto</span>
                    </button>
                    <div class="text-muted small mt-1" style="font-size: 0.68rem;">Bisa digeser, di-zoom &amp; disesuaikan posisinya</div>
                </div>
            </div>

            <!-- Formulir Isian Data GTK -->
            <div class="mb-2.5">
                <label class="form-label required small fw-bold">Nama Lengkap &amp; Gelar</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
            </div>

            <div class="mb-2.5">
                <label class="form-label required small fw-bold">Alamat Email</label>
                <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
            </div>

            <div class="mb-2.5">
                <label class="form-label small fw-bold">Nomor WhatsApp / HP</label>
                <input type="text" name="phone" class="form-control" value="{{ old('phone', $user->phone) }}" placeholder="08xxxxxxxxxx">
            </div>

            <div class="row g-2 mb-2.5">
                <div class="col-6">
                    <label class="form-label small fw-bold">NIP</label>
                    <input type="text" name="nip" class="form-control" value="{{ old('nip', $user->nip) }}" placeholder="-">
                </div>
                <div class="col-6">
                    <label class="form-label small fw-bold">NUPTK</label>
                    <input type="text" name="nuptk" class="form-control" value="{{ old('nuptk', $user->nuptk) }}" placeholder="-">
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label small fw-bold d-flex justify-content-between align-items-center">
                    <span><i class="ti ti-brand-telegram text-info me-1"></i> Telegram Chat ID</span>
                    <small class="text-muted">Ketik <code>/id</code> di bot</small>
                </label>
                <input type="text" name="telegram_chat_id" class="form-control font-monospace" value="{{ old('telegram_chat_id', $user->telegram_chat_id) }}" placeholder="Contoh: 123456789">
            </div>

            <!-- Tombol Aksi (Satu Baris Sama Lebar 50:50 dengan CSS Grid, Tanpa Garis Pembatas Atas) -->
            <div class="mt-4 mb-2">
                <div style="display: grid !important; grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 10px !important; width: 100% !important;">
                    <!-- Tombol Batal: Warna Lembut Berbeda dengan Border & Icon Jelas -->
                    <a href="{{ route('profile') }}" 
                       class="btn rounded-pill py-2.5 fw-bold external text-decoration-none d-flex align-items-center justify-content-center gap-1.5 shadow-sm" 
                       style="width: 100% !important; min-width: 0 !important; font-size: 0.82rem; min-height: 42px; background: #fff1f2 !important; color: #e11d48 !important; border: 1.5px solid #fecdd3 !important; box-sizing: border-box !important;">
                        <i class="ti ti-x fs-3 flex-shrink-0"></i>
                        <span>Batal</span>
                    </a>

                    <!-- Tombol Simpan Data: Warna Biru Primer SIAP dengan Icon Simpan -->
                    <button type="submit" 
                            class="btn btn-primary rounded-pill py-2.5 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-1.5" 
                            style="width: 100% !important; min-width: 0 !important; font-size: 0.82rem; min-height: 42px; background: linear-gradient(135deg, #102d52 0%, #195b9e 100%) !important; border: 1.5px solid #195b9e !important; box-sizing: border-box !important;">
                        <i class="ti ti-device-floppy fs-3 flex-shrink-0"></i>
                        <span>Simpan Data</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('modals')
<!-- STANDALONE OVERLAY MODAL: ATUR & SESUAIKAN POSISI FOTO (CROP & ZOOM) -->
<div id="modal-crop-avatar" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; height: 100dvh; background: #0b1120; z-index: 9999999 !important; flex-direction: column; justify-content: space-between; padding: 0; margin: 0; overflow: hidden;">
    
    <!-- HEADER ATAS: JUDUL DI ATAS & TOMBOL BATAL/GUNAKAN DI BAWAHNYA -->
    <div class="px-3 pt-2 pb-2.5 flex-shrink-0" style="background: linear-gradient(180deg, rgba(15, 23, 42, 0.98) 0%, rgba(15, 23, 42, 0.92) 100%); border-bottom: 1px solid rgba(255,255,255,0.1); padding-top: calc(0.6rem + env(safe-area-inset-top, 0px)) !important;">
        <!-- Baris 1: Judul Penuh (Lebar 100%, Sama Sekali Tidak Terjepit) -->
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="d-flex align-items-center gap-2">
                <span class="avatar avatar-sm bg-primary text-white rounded-circle flex-shrink-0" style="width: 28px; height: 28px;">
                    <i class="ti ti-crop fs-3"></i>
                </span>
                <div>
                    <div class="fw-bold text-white fs-3 lh-1">Atur Posisi Foto</div>
                    <div class="text-white-50 mt-0.5" style="font-size: 0.68rem;">Sesuaikan wajah agar pas di dalam bingkai</div>
                </div>
            </div>
            <!-- Tombol X Teks Murni Tanpa Border -->
            <a href="javascript:void(0)" onclick="cancelCropAvatar()" class="text-white opacity-75 text-decoration-none d-flex align-items-center justify-content-center p-0" title="Tutup" style="font-size: 1.8rem; line-height: 1; width: 32px; height: 32px; outline: none; border: none; background: transparent;">
                &times;
            </a>
        </div>

        <!-- Baris 2: Tombol Batal & Gunakan Foto Berdampingan di Bawah Judul -->
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-light flex-fill rounded-pill py-2 fw-bold d-flex align-items-center justify-content-center gap-1 shadow-sm" onclick="cancelCropAvatar()" style="background: #ffffff !important; color: #1e293b !important; border: 1.5px solid #cbd5e1 !important;">
                <i class="ti ti-x fs-3" style="color: #64748b !important;"></i>
                <span style="color: #1e293b !important;">Batal</span>
            </button>
            <button type="button" class="btn btn-sm btn-primary flex-fill rounded-pill py-2 fw-bold shadow d-flex align-items-center justify-content-center gap-1.5 text-white" onclick="applyCroppedAvatar()" style="background: #195b9e; border-color: #3b82f6;">
                <i class="ti ti-check fs-3"></i>
                <span>Gunakan Foto Ini</span>
            </button>
        </div>
    </div>

    <!-- AREA KANVAS FOTO (TEPAT DI TENGAH LAYAR & TIDAK TERTUTUP TOMBOL) -->
    <div class="flex-grow-1 position-relative d-flex align-items-center justify-content-center p-3" style="min-height: 0; width: 100%; box-sizing: border-box;">
        <div class="w-100 h-100 position-relative d-flex align-items-center justify-content-center overflow-hidden" style="max-width: 440px; margin: 0 auto;">
            <img id="cropperSourceImg" src="" alt="Foto Sumber" style="display: block; max-width: 100%; max-height: 100%; margin: 0 auto;">
        </div>
    </div>

    <!-- BILAH ALAT KONTROL (BERADA DI BAWAH FOTO & BISA DIGESER JIKA DIBUTUHKAN) -->
    <div class="flex-shrink-0 position-relative d-flex flex-column align-items-center px-3 pt-1 pb-3" style="background: linear-gradient(0deg, rgba(15, 23, 42, 0.98) 0%, rgba(15, 23, 42, 0.85) 100%); border-top: 1px solid rgba(255,255,255,0.08); padding-bottom: calc(1rem + env(safe-area-inset-bottom, 12px)) !important;">
        
        <!-- Bilah Kontrol (Draggable Floating Pill) -->
        <div id="draggableCropToolbar" class="shadow-lg d-flex align-items-center gap-1.5 px-3 py-1.5 rounded-pill mb-1" 
             style="background: rgba(30, 41, 59, 0.92); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); border: 1.5px solid rgba(255,255,255,0.2); touch-action: none; user-select: none;">
            
            <!-- Handle Drag Ikon -->
            <div id="dragHandleIcon" class="text-white-50 px-1 d-flex align-items-center justify-content-center" style="cursor: grab;" title="Geser posisi bilah ini jika menutupi foto">
                <i class="ti ti-grip-vertical fs-3"></i>
            </div>

            <!-- Tombol Zoom In -->
            <button type="button" class="btn btn-sm btn-dark text-white rounded-circle p-0 d-flex align-items-center justify-content-center border-0" style="width: 36px; height: 36px; background: rgba(255,255,255,0.15);" onclick="cropperZoom(0.1)" title="Perbesar">
                <i class="ti ti-zoom-in fs-2 text-info"></i>
            </button>
            <!-- Tombol Zoom Out -->
            <button type="button" class="btn btn-sm btn-dark text-white rounded-circle p-0 d-flex align-items-center justify-content-center border-0" style="width: 36px; height: 36px; background: rgba(255,255,255,0.15);" onclick="cropperZoom(-0.1)" title="Perkecil">
                <i class="ti ti-zoom-out fs-2 text-info"></i>
            </button>
            <!-- Tombol Rotate -->
            <button type="button" class="btn btn-sm btn-dark text-white rounded-circle p-0 d-flex align-items-center justify-content-center border-0" style="width: 36px; height: 36px; background: rgba(255,255,255,0.15);" onclick="cropperRotate(90)" title="Putar 90 Derajat">
                <i class="ti ti-rotate fs-2 text-white"></i>
            </button>
            <!-- Tombol Reset -->
            <button type="button" class="btn btn-sm btn-dark text-white rounded-circle p-0 d-flex align-items-center justify-content-center border-0" style="width: 36px; height: 36px; background: rgba(255,255,255,0.15);" onclick="cropperReset()" title="Reset Posisi">
                <i class="ti ti-refresh fs-2 text-white"></i>
            </button>
        </div>

        <div class="text-center text-white-50" style="font-size: 0.72rem;">
            <i class="ti ti-hand-finger me-1 text-info"></i> Geser/cubit foto untuk mengatur posisi wajah. Bilah kontrol juga dapat digeser bebas.
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
<script>
    let cropperInstance = null;

    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('modal-crop-avatar');
        if (modal && modal.parentNode !== document.body) {
            document.body.appendChild(modal);
        }

        // Setup Drag & Drop untuk Floating Toolbar
        initDraggableCropToolbar();
    });

    function initDraggableCropToolbar() {
        const toolbar = document.getElementById('draggableCropToolbar');
        if (!toolbar) return;

        let isDragging = false;
        let startX, startY, initialLeft, initialTop;

        const onStart = function(e) {
            // Hindari drag saat menekan tombol zoom/rotate/reset di dalam toolbar
            if (e.target.closest('button')) return;

            isDragging = true;
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;

            startX = clientX;
            startY = clientY;

            const rect = toolbar.getBoundingClientRect();
            initialLeft = rect.left;
            initialTop = rect.top;

            // Beralih ke fixed position saat mulai digeser manual oleh user
            toolbar.style.position = 'fixed';
            toolbar.style.transform = 'none';
            toolbar.style.left = initialLeft + 'px';
            toolbar.style.top = initialTop + 'px';
            toolbar.style.zIndex = '99999999';
            toolbar.style.opacity = '0.9';
        };

        const onMove = function(e) {
            if (!isDragging) return;
            e.preventDefault();

            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;

            const deltaX = clientX - startX;
            const deltaY = clientY - startY;

            let newLeft = initialLeft + deltaX;
            let newTop = initialTop + deltaY;

            // Batasi agar tidak keluar layar
            const pad = 8;
            const maxLeft = window.innerWidth - toolbar.offsetWidth - pad;
            const maxTop = window.innerHeight - toolbar.offsetHeight - pad;

            newLeft = Math.max(pad, Math.min(newLeft, maxLeft));
            newTop = Math.max(pad, Math.min(newTop, maxTop));

            toolbar.style.left = newLeft + 'px';
            toolbar.style.top = newTop + 'px';
        };

        const onEnd = function() {
            if (isDragging) {
                isDragging = false;
                toolbar.style.opacity = '1';
            }
        };

        toolbar.addEventListener('touchstart', onStart, { passive: false });
        window.addEventListener('touchmove', onMove, { passive: false });
        window.addEventListener('touchend', onEnd);
        window.addEventListener('touchcancel', onEnd);

        toolbar.addEventListener('mousedown', onStart);
        window.addEventListener('mousemove', onMove);
        window.addEventListener('mouseup', onEnd);
    }

    function startAvatarCropper(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const reader = new FileReader();

            reader.onload = function(e) {
                const modal = document.getElementById('modal-crop-avatar');
                const img = document.getElementById('cropperSourceImg');

                if (modal && modal.parentNode !== document.body) {
                    document.body.appendChild(modal);
                }

                img.src = e.target.result;
                modal.style.display = 'flex';

                // Kembalikan posisi floating toolbar ke posisi default di dock bawah
                const toolbar = document.getElementById('draggableCropToolbar');
                if (toolbar) {
                    toolbar.style.position = '';
                    toolbar.style.top = '';
                    toolbar.style.left = '';
                    toolbar.style.transform = '';
                    toolbar.style.opacity = '1';
                }

                if (cropperInstance) {
                    cropperInstance.destroy();
                    cropperInstance = null;
                }

                setTimeout(function() {
                    cropperInstance = new Cropper(img, {
                        aspectRatio: 1,
                        viewMode: 1,
                        autoCropArea: 0.88,
                        responsive: true,
                        restore: false,
                        checkOrientation: true,
                        guides: false,
                        center: true,
                        highlight: false,
                        cropBoxMovable: true,
                        cropBoxResizable: true,
                        toggleDragModeOnDblclick: false,
                        dragMode: 'move',
                    });
                }, 120);
            };

            reader.readAsDataURL(file);
        }
    }

    function cropperZoom(ratio) {
        if (cropperInstance) cropperInstance.zoom(ratio);
    }
    function cropperRotate(deg) {
        if (cropperInstance) cropperInstance.rotate(deg);
    }
    function cropperReset() {
        if (cropperInstance) cropperInstance.reset();
    }

    function cancelCropAvatar() {
        if (cropperInstance) {
            cropperInstance.destroy();
            cropperInstance = null;
        }
        const modal = document.getElementById('modal-crop-avatar');
        if (modal) modal.style.display = 'none';
    }

    function applyCroppedAvatar() {
        if (!cropperInstance) return;

        const canvas = cropperInstance.getCroppedCanvas({
            width: 480,
            height: 480,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
        });

        if (!canvas) return;

        const base64Data = canvas.toDataURL('image/jpeg', 0.92);
        const base64Input = document.getElementById('avatarCroppedBase64');
        if (base64Input) {
            base64Input.value = base64Data;
        }

        canvas.toBlob(function(blob) {
            if (blob) {
                try {
                    const croppedFile = new File([blob], 'avatar_' + Date.now() + '.jpg', { type: 'image/jpeg' });
                    const dt = new DataTransfer();
                    dt.items.add(croppedFile);
                    const fileInput = document.getElementById('editAvatarFileInput');
                    if (fileInput) {
                        fileInput.files = dt.files;
                    }
                } catch (err) {
                    console.warn('DataTransfer File API fallback ke base64:', err);
                }
            }

            let imgEl = document.getElementById('avatarEditPreviewImg');
            const placeholderEl = document.getElementById('avatarEditPreviewPlaceholder');

            if (!imgEl) {
                imgEl = document.createElement('img');
                imgEl.id = 'avatarEditPreviewImg';
                imgEl.className = 'border shadow-sm d-block';
                imgEl.style.width = '72px';
                imgEl.style.height = '72px';
                imgEl.style.borderRadius = '12px';
                imgEl.style.objectFit = 'cover';
                imgEl.style.borderColor = '#cbd5e1';
                if (placeholderEl && placeholderEl.parentNode) {
                    placeholderEl.parentNode.replaceChild(imgEl, placeholderEl);
                }
            }
            imgEl.src = base64Data;

            const label = document.getElementById('labelPilihFoto');
            if (label) {
                label.textContent = 'Atur Ulang Posisi Foto';
            }

            cancelCropAvatar();
        }, 'image/jpeg', 0.92);
    }
</script>
@endpush
