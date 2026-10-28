<div class="card mb-3 border-0 shadow-sm">
    <div class="card-body p-2">
        <ul class="nav nav-pills nav-fill gap-2 flex-wrap">
            @if(auth()->user()->hasPermission('manage_master_data') || auth()->user()->hasPermission('manage_settings') || auth()->user()->hasRole('waka_kurikulum') || auth()->user()->hasRole('super_admin'))
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('academic-years.*') ? 'active bg-primary text-white fw-bold shadow-sm' : 'text-dark' }}" href="{{ route('academic-years.index') }}">
                    <i class="ti ti-calendar-stats me-1 fs-3"></i>
                    <span>Tahun Ajaran</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('departments.*') ? 'active bg-primary text-white fw-bold shadow-sm' : 'text-dark' }}" href="{{ route('departments.index') }}">
                    <i class="ti ti-school me-1 fs-3"></i>
                    <span>Jurusan / Keahlian</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('classrooms.*') ? 'active bg-primary text-white fw-bold shadow-sm' : 'text-dark' }}" href="{{ route('classrooms.index') }}">
                    <i class="ti ti-layout-grid me-1 fs-3"></i>
                    <span>Tingkat & Rombel</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('subjects.*') ? 'active bg-primary text-white fw-bold shadow-sm' : 'text-dark' }}" href="{{ route('subjects.index') }}">
                    <i class="ti ti-books me-1 fs-3"></i>
                    <span>Mata Pelajaran</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('teacher-assignments.*', 'gtk-assignments.*') ? 'active bg-primary text-white fw-bold shadow-sm' : 'text-dark' }}" href="{{ route('teacher-assignments.index') }}">
                    <i class="ti ti-user-check me-1 fs-3"></i>
                    <span>Penugasan GTK</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('gtk-decrees.*') ? 'active bg-primary text-white fw-bold shadow-sm' : 'text-dark' }}" href="{{ route('gtk-decrees.index') }}">
                    <i class="ti ti-file-certificate me-1 fs-3"></i>
                    <span>Arsip SK GTK</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('structural-positions.*', 'duty-positions.*') ? 'active bg-primary text-white fw-bold shadow-sm' : 'text-dark' }}" href="{{ route('structural-positions.index') }}">
                    <i class="ti ti-briefcase me-1 fs-3"></i>
                    <span>Master Penugasan</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('stakeholders.*') ? 'active bg-primary text-white fw-bold shadow-sm' : 'text-dark' }}" href="{{ route('stakeholders.index') }}">
                    <i class="ti ti-users-group me-1 fs-3"></i>
                    <span>Pejabat & Pihak Terkait</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('activity-categories.*') ? 'active bg-primary text-white fw-bold shadow-sm' : 'text-dark' }}" href="{{ route('activity-categories.index') }}">
                    <i class="ti ti-tags me-1 fs-3"></i>
                    <span>Kategori Kegiatan</span>
                </a>
            </li>
            @endif
        </ul>
    </div>
</div>
