@php
    $heroAcademicYear = $academicYear ?? session('active_academic_year', session('academic_year', \App\Models\SchoolSetting::getSetting()->academic_year ?? '2026/2027'));
    $heroCategory = $category ?? 'SISTEM INFORMASI AKADEMIK & PENDIDIKAN';
    $heroIcon = $icon ?? 'ti ti-layout-dashboard';
@endphp

<div class="hero-context-banner rounded-3 p-3 p-md-4 text-white shadow-sm mb-3 position-relative overflow-hidden" 
     style="background: linear-gradient(135deg, #102d52 0%, #164377 50%, #195b9e 100%) !important; box-shadow: 0 10px 25px -5px rgba(16, 45, 82, 0.22), 0 4px 10px -2px rgba(16, 45, 82, 0.12) !important;">
    
    <!-- Ambient Glow / Decorative Circles -->
    <div class="position-absolute end-0 top-0 translate-middle-y me-n5 d-none d-md-block opacity-10 pointer-events-none" 
         style="width: 280px; height: 280px; border-radius: 50%; background: radial-gradient(circle, #ffffff 0%, rgba(255,255,255,0) 70%);"></div>
    <div class="position-absolute start-50 bottom-0 translate-middle-x opacity-10 pointer-events-none" 
         style="width: 400px; height: 80px; background: radial-gradient(ellipse, #38bdf8 0%, rgba(56,189,248,0) 70%);"></div>

    <div class="position-relative z-1 d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-3">
        <!-- Left: Icon & Title -->
        <div class="d-flex align-items-start align-items-sm-center gap-3">
            <div class="d-flex align-items-center justify-content-center flex-shrink-0 rounded-3 shadow-sm" 
                 style="width: 54px; height: 54px; background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.22); backdrop-filter: blur(8px);">
                <i class="{{ $heroIcon }} fs-1" style="color: #7dd3fc !important;"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                    <span class="badge" style="background: rgba(56, 189, 248, 0.2); color: #bae6fd; border: 1px solid rgba(56, 189, 248, 0.3); font-size: 0.68rem; letter-spacing: 0.05rem;">
                        {{ strtoupper($heroCategory) }}
                    </span>
                    @if($heroAcademicYear)
                        <span class="small text-white-50" style="font-size: 0.75rem;">
                            &bull; TP. {{ $heroAcademicYear }}
                        </span>
                    @endif
                </div>
                <h2 class="h2 fw-bold text-white mb-0" style="letter-spacing: -0.02rem; text-shadow: 0 2px 4px rgba(0,0,0,0.15);">
                    {{ $title }}
                </h2>
                @if(!empty($subtitle))
                    <p class="text-white-50 small mb-0 mt-1" style="max-width: 720px; line-height: 1.4;">
                        {{ $subtitle }}
                    </p>
                @endif
            </div>
        </div>

        <!-- Right: Actions -->
        @if(!empty($actions))
            <div class="d-flex align-items-center gap-2 flex-wrap ms-lg-auto mt-2 mt-lg-0">
                {!! $actions !!}
            </div>
        @endif
    </div>
</div>
