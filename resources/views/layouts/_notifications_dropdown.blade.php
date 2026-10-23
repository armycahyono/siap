@php
    $navNotifications = collect();
    try {
        if (auth()->check()) {
            $navNotifications = \App\Services\NotificationService::getUserNotifications(auth()->user());
        }
    } catch (\Throwable $e) {
        $navNotifications = collect();
    }
    $navHasNotification = $navNotifications->isNotEmpty();
    $navNotificationCount = $navNotifications->count();
@endphp

<div class="nav-item dropdown me-1">
    <a href="#" class="nav-link px-2 d-flex align-items-center text-secondary position-relative rounded-2" 
       data-bs-toggle="dropdown" tabindex="-1" aria-label="Tampilkan Notifikasi" title="Pemberitahuan & Tugas" 
       style="width: 36px; height: 36px; justify-content: center; background: #f8fafc; border: 1px solid #e2e8f0; transition: all 0.15s ease;">
        <i class="ti ti-bell fs-2 text-dark"></i>
        @if($navHasNotification)
            <span class="badge bg-danger text-white rounded-pill position-absolute" 
                  style="top: -4px; right: -4px; font-size: 0.65rem; padding: 0.2rem 0.4rem; min-width: 18px; line-height: 1;">
                {{ $navNotificationCount }}
            </span>
        @endif
    </a>
    <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow dropdown-menu-card p-0 shadow-lg border" 
         style="width: 380px; max-width: 90vw; border-radius: 12px; z-index: 1050;">
        <div class="card border-0 mb-0">
            <div class="card-header py-2.5 px-3 bg-primary text-white d-flex align-items-center justify-content-between" 
                 style="border-radius: 12px 12px 0 0; background: linear-gradient(135deg, #102d52 0%, #195b9e 100%) !important;">
                <div class="d-flex align-items-center gap-2">
                    <i class="ti ti-bell fs-3"></i>
                    <span class="fw-bold fs-4">Pemberitahuan &amp; Tugas</span>
                </div>
                @if($navHasNotification)
                    <span class="badge bg-danger rounded-pill px-2 py-0.5 text-white" style="font-size: 0.68rem;">
                        {{ $navNotificationCount }} baru
                    </span>
                @endif
            </div>
            <div class="list-group list-group-flush overflow-auto" style="max-height: 420px;">
                @forelse($navNotifications as $notif)
                    <a href="{{ $notif['url'] ?? 'javascript:void(0)' }}" class="list-group-item list-group-item-action p-2.5 d-flex align-items-start gap-2.5 border-bottom">
                        <span class="avatar avatar-sm rounded-circle flex-shrink-0 bg-light d-flex align-items-center justify-content-center border" style="width: 34px; height: 34px;">
                            <i class="{{ $notif['icon'] ?? 'ti ti-info-circle text-primary' }} fs-2"></i>
                        </span>
                        <div class="flex-fill overflow-hidden">
                            <div class="d-flex align-items-center justify-content-between gap-1 mb-0.5">
                                <div class="fw-bold text-dark small text-truncate" style="font-size: 0.82rem;">{{ $notif['title'] }}</div>
                                @if(!empty($notif['badge']))
                                    <span class="badge {{ $notif['badge_class'] ?? 'bg-primary-lt' }} flex-shrink-0" style="font-size: 0.62rem; padding: 0.15rem 0.4rem; border-radius: 6px;">
                                        {{ $notif['badge'] }}
                                    </span>
                                @endif
                            </div>
                            <div class="text-muted small text-break lh-sm" style="font-size: 0.74rem;">
                                {{ $notif['message'] }}
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="p-4 text-center">
                        <div class="avatar avatar-md bg-teal-lt rounded-circle mx-auto mb-2" style="width: 44px; height: 44px;">
                            <i class="ti ti-circle-check fs-2 text-teal"></i>
                        </div>
                        <div class="fw-bold text-dark small">Tidak Ada Tugas Baru</div>
                        <p class="text-muted small mb-0 mt-0.5" style="font-size: 0.74rem;">
                            Semua tugas harian dan pemantauan Anda telah up to date.
                        </p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
