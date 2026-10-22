@props([
    'name' => 'search',
    'value' => '',
    'placeholder' => 'Cari data...',
    'resetUrl' => null,
    'hasActiveFilter' => false,
])

<div {{ $attributes->merge(['class' => 'd-flex align-items-center gap-1.5 w-100 m-0']) }}>
    <!-- Kolom Teks Pencarian -->
    <input type="text" 
           name="{{ $name }}" 
           class="form-control form-control-sm flex-fill" 
           style="font-size: 0.8rem; height: 36px; border-radius: 8px;" 
           value="{{ $value }}" 
           placeholder="{{ $placeholder }}">

    <!-- Tombol Cari (Kotak Biru Proporsional) -->
    <button type="submit" 
            class="btn btn-primary d-flex align-items-center justify-content-center flex-shrink-0 shadow-sm" 
            style="height: 36px; width: 42px; border-radius: 8px; padding: 0;" 
            title="Cari">
        <i class="ti ti-search fs-2"></i>
    </button>

    <!-- Tombol Reset (Border) -->
    @if($resetUrl && ($value || $hasActiveFilter))
        <a href="{{ $resetUrl }}" 
           class="btn btn-outline-secondary d-flex align-items-center justify-content-center flex-shrink-0" 
           style="height: 36px; width: 36px; border-radius: 8px; padding: 0;" 
           title="Reset Pencarian / Filter">
            <i class="ti ti-x fs-2"></i>
        </a>
    @endif
</div>
