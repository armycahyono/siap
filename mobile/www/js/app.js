/**
 * SIAP Mobile Application Controller & View Handlers
 */
(function() {
    let dashboardDataCache = null;
    let kbmDataCache = null;
    let tasksDataCache = null;
    let profileDataCache = null;
    let attendanceDataCache = null;

    // Global Toast Notification
    function showToast(message, type = 'info') {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        const bgColors = {
            success: 'bg-emerald-600 text-white',
            error: 'bg-rose-600 text-white',
            warning: 'bg-amber-600 text-white',
            info: 'bg-slate-800 text-white',
        };

        toast.className = `px-4 py-2.5 rounded-xl text-xs font-semibold shadow-lg transition-all transform translate-y-2 opacity-0 flex items-center gap-2 ${bgColors[type] || bgColors.info}`;
        toast.textContent = message;

        container.appendChild(toast);
        requestAnimationFrame(() => {
            toast.classList.remove('translate-y-2', 'opacity-0');
        });

        setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-y-2');
            setTimeout(() => toast.remove(), 250);
        }, 3000);
    }
    window.showToast = showToast;

    // Global Logout Handler
    async function handleLogout() {
        if (!confirm('Apakah Anda yakin ingin keluar dari SIAP Mobile?')) return;
        try {
            await SIAP_API.logout();
        } catch (e) {}
        SIAP_API.setToken(null);
        SIAP_ROUTER.setCurrentUser(null);
        localStorage.removeItem('siap_user_data');
        updateTopBarUser(null);
        showToast('Anda telah keluar dari sistem.', 'info');
        SIAP_ROUTER.navigate('/auth/login');
    }
    window.handleLogout = handleLogout;

    // ==========================================
    // TOP BAR & NOTIFICATION HANDLERS
    // ==========================================
    function updateTopBarUser(user) {
        const avatarEl = document.getElementById('top-user-avatar');
        if (!avatarEl) return;
        if (user && user.avatar_url) {
            avatarEl.src = user.avatar_url;
        } else {
            avatarEl.src = 'assets/logo.png';
        }
    }
    window.updateTopBarUser = updateTopBarUser;

    async function fetchNotificationBadge() {
        try {
            const res = await SIAP_API.getNotifications();
            if (res && res.success && res.data) {
                const count = res.data.unread_count || 0;
                const badgeEl = document.getElementById('top-notif-badge');
                if (badgeEl) {
                    if (count > 0) {
                        badgeEl.textContent = count > 99 ? '99+' : count;
                        badgeEl.classList.remove('hidden');
                    } else {
                        badgeEl.classList.add('hidden');
                    }
                }
            }
        } catch (e) {
            // silent background error
        }
    }
    window.fetchNotificationBadge = fetchNotificationBadge;

    async function openNotificationModal() {
        const modal = document.getElementById('modal-notifications');
        const bottomNav = document.getElementById('mobile-bottom-nav');
        const listContainer = document.getElementById('notif-list-container');
        const counterEl = document.getElementById('notif-modal-counter');
        const subEl = document.getElementById('notif-modal-sub');
        const badgeEl = document.getElementById('top-notif-badge');

        if (!modal) return;

        if (bottomNav) bottomNav.classList.add('hidden');
        modal.classList.remove('hidden');

        if (listContainer) {
            listContainer.innerHTML = `
                <div class="text-center py-8 text-xs text-slate-400">
                    <svg class="animate-spin h-5 w-5 text-blue-600 mx-auto mb-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg>
                    Memuat data pemberitahuan...
                </div>
            `;
        }

        try {
            const res = await SIAP_API.getNotifications();
            if (res && res.success && res.data) {
                const list = res.data.notifications || [];
                const count = res.data.unread_count ?? list.length;

                // Sync header badge
                if (badgeEl) {
                    if (count > 0) {
                        badgeEl.textContent = count > 99 ? '99+' : count;
                        badgeEl.classList.remove('hidden');
                    } else {
                        badgeEl.classList.add('hidden');
                    }
                }

                // Sync modal badge counter
                if (counterEl) {
                    if (count > 0) {
                        counterEl.textContent = count;
                        counterEl.classList.remove('hidden');
                    } else {
                        counterEl.classList.add('hidden');
                    }
                }

                if (subEl) {
                    subEl.textContent = count > 0
                        ? `${count} tugas & pengingat aktif hari ini`
                        : 'Tidak ada tugas yang tertunda';
                }

                if (listContainer) {
                    if (list.length === 0) {
                        listContainer.innerHTML = `
                            <div class="text-center py-10 px-4">
                                <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-3 shadow-xs">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </div>
                                <h4 class="text-xs font-black text-slate-800">Semua Tugas Selesai</h4>
                                <p class="text-[11px] text-slate-500 mt-1 max-w-xs mx-auto leading-relaxed">Tidak ada pengingat presensi KBM, tugas piket, atau agenda yang tertunda hari ini.</p>
                            </div>
                        `;
                    } else {
                        listContainer.innerHTML = list.map((item) => {
                            const badgeMarkup = item.badge ? `<span class="px-2 py-0.5 rounded text-[9px] font-bold ${item.badge_class || 'bg-blue-100 text-blue-700'}">${item.badge}</span>` : '';
                            const targetUrl = item.url || '';
                            return `
                                <div onclick="handleNotificationClick('${targetUrl}')" class="p-3.5 rounded-2xl bg-slate-50 hover:bg-blue-50/60 border border-slate-200/70 transition-all cursor-pointer flex items-start gap-3">
                                    <div class="w-9 h-9 rounded-xl bg-blue-100 text-blue-600 flex-shrink-0 flex items-center justify-center shadow-xs">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-1.5 mb-0.5">
                                            <h4 class="text-xs font-bold text-slate-800 truncate">${item.title || 'Pemberitahuan'}</h4>
                                            ${badgeMarkup}
                                        </div>
                                        <p class="text-[11px] text-slate-600 leading-snug break-words">${item.message || ''}</p>
                                    </div>
                                </div>
                            `;
                        }).join('');
                    }
                }
            } else {
                throw new Error(res?.message || 'Gagal memuat pemberitahuan');
            }
        } catch (err) {
            console.error('Notification fetch error:', err);
            if (listContainer) {
                listContainer.innerHTML = `
                    <div class="text-center py-8 text-xs text-rose-500">
                        <p class="font-bold">Gagal memuat pemberitahuan</p>
                        <p class="text-[10px] text-slate-400 mt-1">${err.message || 'Terjadi kesalahan sistem'}</p>
                        <button onclick="openNotificationModal()" class="mt-3 px-3 py-1 bg-slate-100 text-slate-700 rounded-lg text-[10px] font-semibold hover:bg-slate-200">Coba Lagi</button>
                    </div>
                `;
            }
        }
    }
    window.openNotificationModal = openNotificationModal;

    function closeNotificationModal() {
        const modal = document.getElementById('modal-notifications');
        const bottomNav = document.getElementById('mobile-bottom-nav');
        if (modal) modal.classList.add('hidden');
        if (bottomNav) bottomNav.classList.remove('hidden');
    }
    window.closeNotificationModal = closeNotificationModal;

    function handleNotificationClick(url) {
        closeNotificationModal();
        if (!url) return;

        if (url.startsWith('#')) {
            SIAP_ROUTER.navigate(url.replace(/^#/, ''));
            return;
        }

        if (url.includes('/kbm')) {
            SIAP_ROUTER.navigate('/kbm');
        } else if (url.includes('/attendance')) {
            SIAP_ROUTER.navigate('/attendance');
        } else if (url.includes('/tasks')) {
            SIAP_ROUTER.navigate('/tasks');
        } else if (url.includes('/profile')) {
            SIAP_ROUTER.navigate('/profile');
        } else {
            window.location.href = url;
        }
    }
    window.handleNotificationClick = handleNotificationClick;

    // Close notification modal on backdrop click
    document.addEventListener('click', function(e) {
        const modal = document.getElementById('modal-notifications');
        if (modal && !modal.classList.contains('hidden')) {
            if (e.target === modal) {
                closeNotificationModal();
            }
        }
    });

    // ==========================================
    // 1. AUTH VIEW CONTROLLER
    // ==========================================
    window.initAuthLogin = function(container) {
        const form = container.querySelector('#form-mobile-login');
        if (!form) return;

        form.onsubmit = async function(e) {
            e.preventDefault();
            const loginInput = form.querySelector('#login-input').value.trim();
            const passwordInput = form.querySelector('#password-input').value;
            const btnSubmit = form.querySelector('#btn-login-submit');

            if (!loginInput || !passwordInput) {
                showToast('Mohon masukkan identitas dan kata sandi.', 'warning');
                return;
            }

            btnSubmit.disabled = true;
            btnSubmit.innerHTML = `
                <svg class="animate-spin h-4 w-4 mx-auto text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                </svg>
            `;

            try {
                const res = await SIAP_API.login(loginInput, passwordInput, true);
                if (res.success && res.data?.user) {
                    SIAP_ROUTER.setCurrentUser(res.data.user);
                    localStorage.setItem('siap_user_data', JSON.stringify(res.data.user));
                    updateTopBarUser(res.data.user);
                    fetchNotificationBadge();
                    showToast(res.message || 'Login berhasil!', 'success');
                    SIAP_ROUTER.navigate('/dashboard');
                } else {
                    showToast(res.message || 'Gagal masuk.', 'error');
                }
            } catch (err) {
                showToast(err.message || 'Identitas atau kata sandi tidak cocok.', 'error');
            } finally {
                btnSubmit.disabled = false;
                btnSubmit.textContent = 'Masuk Aplikasi';
            }
        };
    };

    // ==========================================
    // 2. DASHBOARD VIEW CONTROLLER
    // ==========================================
    window.initDashboard = async function(container) {
        async function fetchAndRender() {
            try {
                const res = await SIAP_API.getDashboard();
                if (res.success && res.data) {
                    dashboardDataCache = res.data;
                    renderDashboardContent(container, res.data);
                }
            } catch (err) {
                console.error('Error fetching dashboard:', err);
                showToast('Gagal menyegarkan data beranda.', 'error');
            }
        }

        if (dashboardDataCache) {
            renderDashboardContent(container, dashboardDataCache);
            fetchAndRender(); // Background revalidation
        } else {
            await fetchAndRender();
        }
    };

    function renderDashboardContent(container, data) {
        const user = SIAP_ROUTER.getCurrentUser() || {};
        
        // Date & Greeting
        const dateEl = container.querySelector('#dash-formatted-date');
        if (dateEl) dateEl.textContent = data.date_info?.formatted_date || '';

        const userNameEl = container.querySelector('#dash-user-name');
        if (userNameEl) userNameEl.textContent = user.name || 'Bapak/Ibu Guru';

        const userRoleEl = container.querySelector('#dash-user-role');
        if (userRoleEl) userRoleEl.textContent = user.role || 'GTK';

        const userAvatarEl = container.querySelector('#dash-user-avatar');
        if (userAvatarEl && user.avatar_url) userAvatarEl.src = user.avatar_url;

        // Attendance Today
        const attStatusEl = container.querySelector('#dash-att-status');
        const attCheckInEl = container.querySelector('#dash-att-in');
        const attCheckOutEl = container.querySelector('#dash-att-out');

        if (attStatusEl) attStatusEl.textContent = data.attendance_today?.status_label || '-';
        if (attCheckInEl) attCheckInEl.textContent = data.attendance_today?.check_in_time || '--:--';
        if (attCheckOutEl) attCheckOutEl.textContent = data.attendance_today?.check_out_time || '--:--';

        // Teaching Schedules Today
        const scheduleContainer = container.querySelector('#dash-schedule-list');
        if (scheduleContainer) {
            const schedules = data.teaching_today?.schedules || [];
            if (schedules.length === 0) {
                scheduleContainer.innerHTML = `
                    <div class="p-4 text-center text-slate-400 text-xs bg-slate-50 rounded-xl border border-slate-200">
                        Tidak ada jadwal KBM tatap muka hari ini.
                    </div>
                `;
            } else {
                scheduleContainer.innerHTML = schedules.map(s => `
                    <div class="flex items-center justify-between p-3 card-elevated">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-xs ${s.is_completed ? 'bg-emerald-50 text-emerald-600' : 'bg-blue-50 text-blue-600'}">
                                ${s.start_period}
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-slate-800">${s.subject_name}</h4>
                                <p class="text-[11px] text-slate-500">${s.classroom_name} &bull; ${s.period_text}</p>
                            </div>
                        </div>
                        <div>
                            ${s.is_completed
                                ? '<span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-700">Tersimpan</span>'
                                : '<span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-100 text-amber-700">Belum</span>'
                            }
                        </div>
                    </div>
                `).join('');
            }
        }

        // Piket Today Badge
        const piketCard = container.querySelector('#dash-piket-card');
        if (piketCard) {
            if (data.piket_info?.is_piket_today) {
                piketCard.classList.remove('hidden');
            } else {
                piketCard.classList.add('hidden');
            }
        }

        // Agenda Today
        const agendaCard = container.querySelector('#dash-agenda-card');
        const agendaTitle = container.querySelector('#dash-agenda-title');
        const agendaDesc = container.querySelector('#dash-agenda-desc');
        if (agendaCard && data.agendas_today && data.agendas_today.length > 0) {
            agendaCard.classList.remove('hidden');
            const ag = data.agendas_today[0];
            if (agendaTitle) agendaTitle.textContent = ag.title;
            if (agendaDesc) agendaDesc.textContent = ag.description || 'Agenda kalender kurikulum sekolah';
        } else if (agendaCard) {
            agendaCard.classList.add('hidden');
        }
    }

    // ==========================================
    // 2.5 KBM (JURNAL & PRESENSI KELAS) VIEW CONTROLLER (CUPERTINO PRO)
    // ==========================================
    let currentKbmFilter = 'all';

    window.initKbm = async function(container, params) {
        async function fetchKbmData() {
            try {
                const res = await SIAP_API.getKbm();
                if (res.success && res.data) {
                    kbmDataCache = res.data;
                    renderKbmContent(container, res.data);
                }
            } catch (err) {
                console.error('Error fetching KBM data:', err);
                showToast('Gagal memuat jadwal KBM.', 'error');
            }
        }

        // Live Search Input Handler
        const searchInput = container.querySelector('#kbm-search-input');
        if (searchInput) {
            searchInput.oninput = () => {
                if (kbmDataCache) applyKbmFilters(container, kbmDataCache);
            };
        }

        // Global Tab Filter Hook for view
        window.filterKbmCards = function(type) {
            currentKbmFilter = type;
            ['all', 'pending', 'done'].forEach(t => {
                const btn = container.querySelector('#kbm-tab-' + t);
                if (btn) {
                    if (t === type) {
                        btn.className = 'flex-1 py-1.5 rounded-lg font-bold bg-white text-slate-900 shadow-xs transition-all';
                    } else {
                        btn.className = 'flex-1 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 transition-all';
                    }
                }
            });
            if (kbmDataCache) applyKbmFilters(container, kbmDataCache);
        };

        if (kbmDataCache) {
            renderKbmContent(container, kbmDataCache);
            fetchKbmData(); // Background revalidation
        } else {
            await fetchKbmData();
        }
    };

    function renderKbmContent(container, data) {
        const dateInfo = data.date_info || {};
        const total = data.total_sessions || 0;
        const attended = data.attended_sessions_count || 0;
        const pending = Math.max(0, total - attended);
        const swapCount = (data.swap_sessions || []).length;
        
        // Date & Header Labels
        const dateLabelEl = container.querySelector('#kbm-date-label');
        if (dateLabelEl) dateLabelEl.textContent = dateInfo.formatted_date || 'Hari Ini';

        const totalInfoEl = container.querySelector('#kbm-total-info');
        if (totalInfoEl) {
            totalInfoEl.textContent = `${total} Jam Pelajaran`;
        }

        // 1. Cupertino Activity Stat Ribbon
        const statTotalEl = container.querySelector('#kbm-stat-total');
        const statAttendedEl = container.querySelector('#kbm-stat-attended');
        const statPendingEl = container.querySelector('#kbm-stat-pending');
        const statSwapEl = container.querySelector('#kbm-stat-swap');
        const barAttendedEl = container.querySelector('#kbm-bar-attended');
        const barPendingEl = container.querySelector('#kbm-bar-pending');

        if (statTotalEl) statTotalEl.textContent = total;
        if (statAttendedEl) statAttendedEl.textContent = attended;
        if (statPendingEl) statPendingEl.textContent = pending;
        if (statSwapEl) statSwapEl.textContent = swapCount;

        const attendedPct = total > 0 ? Math.round((attended / total) * 100) : 0;
        const pendingPct = total > 0 ? Math.round((pending / total) * 100) : 0;

        if (barAttendedEl) barAttendedEl.style.width = `${attendedPct}%`;
        if (barPendingEl) barPendingEl.style.width = `${pendingPct}%`;

        // 2. Holiday / Weekend Alert
        const alertHolidayEl = container.querySelector('#kbm-alert-holiday');
        const holidayTitleEl = container.querySelector('#kbm-holiday-title');
        const holidayDescEl = container.querySelector('#kbm-holiday-desc');

        if (alertHolidayEl) {
            if (!dateInfo.is_kbm_day) {
                alertHolidayEl.classList.remove('hidden');
                if (holidayTitleEl) {
                    holidayTitleEl.textContent = dateInfo.holiday_title || (dateInfo.is_weekend ? 'Akhir Pekan (Libur KBM)' : 'Hari Libur / Diliburkan');
                }
                if (holidayDescEl) {
                    holidayDescEl.textContent = dateInfo.is_weekend 
                        ? 'Kegiatan belajar mengajar tatap muka tidak dijadwalkan pada hari libur akhir pekan.'
                        : 'Kegiatan belajar mengajar tatap muka ditiadakan sesuai kalender akademik.';
                }
            } else {
                alertHolidayEl.classList.add('hidden');
            }
        }

        // 3. Render Schedules with Filter
        applyKbmFilters(container, data);

        // 4. Swap Sessions (Tukar Jam)
        const swapSectionEl = container.querySelector('#kbm-swap-section');
        const swapListEl = container.querySelector('#kbm-swap-list');
        const swapSessions = data.swap_sessions || [];

        if (swapSectionEl && swapListEl) {
            if (swapSessions.length > 0) {
                swapSectionEl.classList.remove('hidden');
                swapListEl.innerHTML = swapSessions.map(sw => `
                    <div class="p-3 card-elevated border-indigo-200/70 bg-indigo-50/40">
                        <div class="flex items-start justify-between">
                            <div>
                                <span class="px-2 py-0.5 rounded-md text-[9px] font-bold bg-indigo-100 text-indigo-800 uppercase tracking-wider">${sw.classroom_name} • ${sw.period_text}</span>
                                <h5 class="text-xs font-bold text-slate-800 mt-1">${sw.subject_name}</h5>
                                <p class="text-[10px] text-slate-500 mt-0.5">Menggantikan: <span class="font-medium text-slate-700">${sw.original_teacher_name}</span></p>
                            </div>
                        </div>
                    </div>
                `).join('');
            } else {
                swapSectionEl.classList.add('hidden');
            }
        }
    }

    function applyKbmFilters(container, data) {
        const scheduleContainer = container.querySelector('#kbm-schedules-list');
        const emptyResultEl = container.querySelector('#kbm-empty-result');
        if (!scheduleContainer) return;

        const searchInput = container.querySelector('#kbm-search-input');
        const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const schedules = data.my_schedules || [];

        const filtered = schedules.filter(s => {
            // Segmented tab filter
            if (currentKbmFilter === 'pending' && s.is_attended) return false;
            if (currentKbmFilter === 'done' && !s.is_attended) return false;

            // Search query filter
            if (query) {
                const matchClass = (s.classroom_name || '').toLowerCase().includes(query);
                const matchSub = (s.subject_name || '').toLowerCase().includes(query);
                const matchCode = (s.subject_code || '').toLowerCase().includes(query);
                if (!matchClass && !matchSub && !matchCode) return false;
            }
            return true;
        });

        if (schedules.length === 0) {
            scheduleContainer.innerHTML = `
                <div class="p-6 text-center text-slate-400 text-xs card-elevated">
                    <svg class="w-8 h-8 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    Tidak ada jadwal mengajar tatap muka yang terdaftar untuk Anda pada hari ini.
                </div>
            `;
            if (emptyResultEl) emptyResultEl.classList.add('hidden');
            return;
        }

        if (filtered.length === 0) {
            scheduleContainer.innerHTML = '';
            if (emptyResultEl) emptyResultEl.classList.remove('hidden');
            return;
        }

        if (emptyResultEl) emptyResultEl.classList.add('hidden');
        scheduleContainer.innerHTML = filtered.map(s => {
            const summary = s.attendance_summary;
            const totalStudents = summary ? summary.total : 0;
            const present = summary ? summary.present : 0;
            const sick = summary ? summary.sakit : 0;
            const leave = summary ? summary.izin : 0;
            const alpha = summary ? summary.alfa : 0;
            const presentPct = totalStudents > 0 ? Math.round((present / totalStudents) * 100) : 0;

            return `
                <div class="p-3.5 card-elevated transition hover:scale-[1.005]">
                    <!-- Header Kartu -->
                    <div class="flex items-start justify-between gap-2 mb-1.5">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="px-2.5 py-0.5 rounded-lg text-[10px] font-extrabold bg-blue-600 text-white tracking-wide uppercase shadow-xs">${s.classroom_name}</span>
                            <span class="text-[11px] font-semibold text-slate-500">${s.period_text}</span>
                        </div>
                        <div>
                            ${s.is_attended 
                                ? '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[9px] font-bold bg-emerald-100 text-emerald-800"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Sudah Diabsen</span>'
                                : '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[9px] font-bold bg-amber-100 text-amber-800"><span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>Belum Diabsen</span>'
                            }
                        </div>
                    </div>

                    <!-- Judul Mapel & Waktu -->
                    <h4 class="text-xs font-bold text-slate-800 mt-0.5">${s.subject_name}</h4>
                    ${s.time_range ? `<p class="text-[10px] text-slate-400 font-mono mt-0.5">${s.time_range}</p>` : ''}

                    <!-- Micro Breakdown Bar Kehadiran Siswa (Cupertino Pro) -->
                    ${s.is_attended && summary && totalStudents > 0 ? `
                        <div class="mt-2.5 p-2 bg-slate-50/90 rounded-xl border border-slate-200/70">
                            <div class="flex items-center justify-between text-[10px] mb-1">
                                <span class="font-semibold text-slate-600">Kehadiran Siswa</span>
                                <span class="font-bold text-slate-800">${present}/${totalStudents} Hadir (${presentPct}%)</span>
                            </div>

                            <!-- Segmented Color Progress Bar -->
                            <div class="w-full bg-slate-200/80 h-1.5 rounded-full overflow-hidden flex">
                                <div class="bg-emerald-500 h-full" style="width: ${(present / totalStudents) * 100}%" title="Hadir: ${present}"></div>
                                <div class="bg-amber-400 h-full" style="width: ${(sick / totalStudents) * 100}%" title="Sakit: ${sick}"></div>
                                <div class="bg-blue-400 h-full" style="width: ${(leave / totalStudents) * 100}%" title="Izin: ${leave}"></div>
                                <div class="bg-rose-500 h-full" style="width: ${(alpha / totalStudents) * 100}%" title="Alfa: ${alpha}"></div>
                            </div>

                            <!-- Micro Tags H / S / I / A -->
                            <div class="flex items-center gap-2 mt-1.5 text-[9px]">
                                <span class="text-emerald-700 font-bold">H: ${present}</span>
                                <span class="text-amber-700 font-semibold">S: ${sick}</span>
                                <span class="text-blue-700 font-semibold">I: ${leave}</span>
                                <span class="${alpha > 0 ? 'text-rose-600 font-bold' : 'text-slate-400'}">A: ${alpha}</span>
                            </div>
                        </div>
                    ` : ''}

                    <!-- Jurnal Materi Pelajaran -->
                    ${s.material_taught ? `
                        <div class="mt-2 text-[10px] text-slate-600 italic bg-white/70 p-2 rounded-lg border border-slate-200/60 line-clamp-2">
                            <span class="font-bold text-slate-700 not-italic">Materi:</span> ${s.material_taught}
                        </div>
                    ` : ''}

                    <!-- Tombol Aksi -->
                    <div class="mt-2.5 pt-2 border-t border-slate-100 flex items-center justify-between gap-2">
                        <span class="text-[10px] text-slate-400 font-mono">${s.period_text}</span>
                        <a href="${s.fill_url}" class="px-3.5 py-1.5 rounded-xl text-[11px] font-bold transition-all shadow-xs ${s.is_attended ? 'bg-slate-100 hover:bg-slate-200 text-slate-700' : 'bg-blue-600 hover:bg-blue-700 text-white'}">
                            ${s.is_attended ? 'Edit Presensi & Jurnal' : 'Masuk Kelas & Absen →'}
                        </a>
                    </div>
                </div>
            `;
        }).join('');
    }

    // ==========================================
    // 3. ATTENDANCE VIEW CONTROLLER
    // ==========================================
    let attendanceWatchId = null;
    let attendanceUserLocation = null;

    function calculateHaversine(lat1, lon1, lat2, lon2) {
        const R = 6371000; // Radius bumi dalam meter
        const toRad = (v) => (v * Math.PI) / 180;
        const dLat = toRad(lat2 - lat1);
        const dLon = toRad(lon2 - lon1);
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                  Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
                  Math.sin(dLon / 2) * Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return Math.round(R * c);
    }

    function stopAttendanceTracker() {
        if (attendanceWatchId !== null && navigator.geolocation) {
            navigator.geolocation.clearWatch(attendanceWatchId);
            attendanceWatchId = null;
        }
    }
    window.stopAttendanceTracker = stopAttendanceTracker;

    function updateAttendanceBadges(container, gpsState, radiusState, distance = null, radiusLimit = 100, errText = null) {
        const badgeGps = container.querySelector('#badge-gps-status');
        const dotGps = container.querySelector('#dot-gps-status');
        const textGps = container.querySelector('#text-gps-status');

        const badgeRadius = container.querySelector('#badge-radius-status');
        const dotRadius = container.querySelector('#dot-radius-status');
        const textRadius = container.querySelector('#text-radius-status');

        const distanceTextEl = container.querySelector('#att-distance-text');

        // 1. Update GPS Badge
        if (badgeGps && dotGps && textGps) {
            if (gpsState === 'ready') {
                badgeGps.className = 'flex items-center gap-1 px-2 py-0.5 rounded-lg bg-emerald-50 text-emerald-700 text-[9px] font-extrabold transition-all border border-emerald-200/60';
                dotGps.className = 'w-1.5 h-1.5 rounded-full bg-emerald-500';
                textGps.textContent = 'GPS';
            } else if (gpsState === 'loading') {
                badgeGps.className = 'flex items-center gap-1 px-2 py-0.5 rounded-lg bg-amber-50 text-amber-700 text-[9px] font-extrabold transition-all border border-amber-200/60';
                dotGps.className = 'w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse';
                textGps.textContent = 'GPS...';
            } else {
                badgeGps.className = 'flex items-center gap-1 px-2 py-0.5 rounded-lg bg-rose-50 text-rose-700 text-[9px] font-extrabold transition-all border border-rose-200/60';
                dotGps.className = 'w-1.5 h-1.5 rounded-full bg-rose-500';
                textGps.textContent = 'GPS Off';
            }
        }

        // 2. Update Radius Badge
        if (badgeRadius && dotRadius && textRadius) {
            if (radiusState === 'ready') {
                badgeRadius.className = 'flex items-center gap-1 px-2 py-0.5 rounded-lg bg-emerald-50 text-emerald-700 text-[9px] font-extrabold transition-all border border-emerald-200/60';
                dotRadius.className = 'w-1.5 h-1.5 rounded-full bg-emerald-500';
                textRadius.textContent = 'Radius';
            } else if (radiusState === 'out') {
                badgeRadius.className = 'flex items-center gap-1 px-2 py-0.5 rounded-lg bg-rose-50 text-rose-700 text-[9px] font-extrabold transition-all border border-rose-200/60';
                dotRadius.className = 'w-1.5 h-1.5 rounded-full bg-rose-500';
                textRadius.textContent = 'Radius Out';
            } else if (radiusState === 'checking') {
                badgeRadius.className = 'flex items-center gap-1 px-2 py-0.5 rounded-lg bg-slate-200 text-slate-500 text-[9px] font-extrabold transition-all';
                dotRadius.className = 'w-1.5 h-1.5 rounded-full bg-slate-400 animate-pulse';
                textRadius.textContent = 'Radius...';
            } else {
                badgeRadius.className = 'flex items-center gap-1 px-2 py-0.5 rounded-lg bg-rose-50 text-rose-700 text-[9px] font-extrabold transition-all border border-rose-200/60';
                dotRadius.className = 'w-1.5 h-1.5 rounded-full bg-rose-500';
                textRadius.textContent = 'Radius Off';
            }
        }

        // 3. Update Detail Jarak Text
        if (distanceTextEl) {
            if (distance !== null) {
                if (radiusState === 'ready') {
                    distanceTextEl.innerHTML = `<span class="text-emerald-700 font-bold">${distance}m</span> dari sekolah (Batas ${radiusLimit}m)`;
                } else if (radiusState === 'out') {
                    distanceTextEl.innerHTML = `<span class="text-rose-600 font-bold">${distance}m</span> (Di luar batas ${radiusLimit}m)`;
                } else {
                    distanceTextEl.textContent = `${distance} meter`;
                }
            } else if (errText) {
                distanceTextEl.textContent = errText;
            } else {
                distanceTextEl.textContent = 'Mencari lokasi perangkat...';
            }
        }
    }

    function initAttendanceTracker(container, schoolLocation) {
        stopAttendanceTracker();

        if (!navigator.geolocation) {
            updateAttendanceBadges(container, 'not_ready', 'not_ready', null, 100, 'GPS tidak didukung perangkat/browser');
            return;
        }

        updateAttendanceBadges(container, 'loading', 'checking');

        const onPosSuccess = (pos) => {
            const lat = pos.coords.latitude;
            const lon = pos.coords.longitude;
            const acc = Math.round(pos.coords.accuracy);

            attendanceUserLocation = {
                latitude: lat,
                longitude: lon,
                accuracy: acc,
                timestamp: Date.now(),
            };

            const schoolLat = schoolLocation?.latitude ?? -7.95590200;
            const schoolLon = schoolLocation?.longitude ?? 111.97259400;
            const radiusLimit = schoolLocation?.radius_meters ?? 100;

            const dist = calculateHaversine(lat, lon, schoolLat, schoolLon);
            attendanceUserLocation.distance = dist;

            const isInside = dist <= radiusLimit;
            updateAttendanceBadges(
                container,
                'ready',
                isInside ? 'ready' : 'out',
                dist,
                radiusLimit
            );
        };

        const onPosError = (err) => {
            attendanceUserLocation = null;
            let errMsg = 'Gagal membaca koordinat GPS';
            if (err.code === 1) errMsg = 'Izin lokasi (GPS) belum diaktifkan/ditolak';
            else if (err.code === 2) errMsg = 'Sinyal lokasi tidak dapat ditentukan';
            else if (err.code === 3) errMsg = 'Waktu deteksi GPS habis (timeout)';

            updateAttendanceBadges(container, 'not_ready', 'not_ready', null, 100, errMsg);
        };

        navigator.geolocation.getCurrentPosition(onPosSuccess, onPosError, {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0,
        });

        try {
            attendanceWatchId = navigator.geolocation.watchPosition(onPosSuccess, onPosError, {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 5000,
            });
        } catch (e) {}
    }

    window.initAttendance = async function(container) {
        async function fetchStatus() {
            try {
                const res = await SIAP_API.getAttendanceStatus();
                if (res.success && res.data) {
                    attendanceDataCache = res.data;
                    renderAttendanceContent(container, res.data);
                    initAttendanceTracker(container, res.data.school_location);
                }
            } catch (err) {
                console.error('Error fetching attendance status:', err);
                showToast('Gagal memuat status presensi.', 'error');
            }
        }

        await fetchStatus();

        // Bind GPS check-in/out button
        const btnCheckIn = container.querySelector('#btn-att-checkin');
        const btnCheckOut = container.querySelector('#btn-att-checkout');

        if (btnCheckIn) {
            btnCheckIn.onclick = () => doGeotagPresensi('check_in', btnCheckIn, container);
        }
        if (btnCheckOut) {
            btnCheckOut.onclick = () => doGeotagPresensi('check_out', btnCheckOut, container);
        }
    };

    function renderAttendanceContent(container, data) {
        const checkInTimeEl = container.querySelector('#att-today-in');
        const checkOutTimeEl = container.querySelector('#att-today-out');
        const btnCheckIn = container.querySelector('#btn-att-checkin');
        const btnCheckOut = container.querySelector('#btn-att-checkout');
        const subCheckIn = container.querySelector('#sub-att-checkin');
        const subCheckOut = container.querySelector('#sub-att-checkout');

        if (checkInTimeEl) checkInTimeEl.textContent = data.today?.check_in_time || '--:--';
        if (checkOutTimeEl) checkOutTimeEl.textContent = data.today?.check_out_time || '--:--';

        // 1. Tombol Presensi Masuk (Duo Grid Compact, Logo di Tengah)
        if (btnCheckIn) {
            const canIn = !!data.today?.can_check_in;
            btnCheckIn.disabled = !canIn;
            if (canIn) {
                btnCheckIn.className = 'p-3.5 rounded-2xl bg-gradient-to-b from-emerald-500 to-emerald-600 text-white flex flex-col items-center justify-center text-center shadow-sm hover:shadow transition active:scale-95 border border-emerald-400/40 cursor-pointer';
                if (subCheckIn) subCheckIn.textContent = 'Ketuk Rekam Masuk';
            } else {
                btnCheckIn.className = 'p-3.5 rounded-2xl bg-slate-100 text-slate-400 border border-slate-200 flex flex-col items-center justify-center text-center cursor-not-allowed shadow-none';
                if (subCheckIn) {
                    subCheckIn.textContent = data.today?.check_in_time 
                        ? 'Sudah Absen' 
                        : 'Tidak Tersedia';
                }
            }
        }

        // 2. Tombol Presensi Pulang (Duo Grid Compact, Logo di Tengah)
        if (btnCheckOut) {
            const canOut = !!data.today?.can_check_out;
            btnCheckOut.disabled = !canOut;
            if (canOut) {
                btnCheckOut.className = 'p-3.5 rounded-2xl bg-gradient-to-b from-blue-600 to-indigo-600 text-white flex flex-col items-center justify-center text-center shadow-sm hover:shadow transition active:scale-95 border border-blue-400/40 cursor-pointer';
                if (subCheckOut) {
                    subCheckOut.textContent = data.today?.has_check_out 
                        ? 'Ketuk Perbarui'
                        : 'Ketuk Rekam Pulang';
                }
            } else {
                btnCheckOut.className = 'p-3.5 rounded-2xl bg-slate-100 text-slate-400 border border-slate-200 flex flex-col items-center justify-center text-center cursor-not-allowed shadow-none';
                if (subCheckOut) subCheckOut.textContent = 'Belum Masuk';
            }
        }

        // Recent logs list
        const logList = container.querySelector('#att-recent-logs');
        if (logList) {
            const logs = data.recent_logs || [];
            if (logs.length === 0) {
                logList.innerHTML = '<div class="text-center py-6 text-xs text-slate-400">Belum ada riwayat presensi.</div>';
            } else {
                logList.innerHTML = logs.map(l => `
                    <div class="flex items-center justify-between py-2.5 border-b border-slate-100 last:border-0">
                        <div class="flex items-center gap-2.5">
                            <span class="w-2 h-2 rounded-full ${l.type === 'check_in' ? 'bg-emerald-500' : 'bg-blue-500'}"></span>
                            <div>
                                <p class="text-xs font-bold text-slate-800">${l.status_label}</p>
                                <p class="text-[10px] text-slate-400">${l.date} &bull; ${l.method}</p>
                            </div>
                        </div>
                        <span class="text-xs font-mono font-bold text-slate-700">${l.time} WIB</span>
                    </div>
                `).join('');
            }
        }
    }

    async function doGeotagPresensi(type, button, container) {
        if (!navigator.geolocation) {
            showToast('GPS tidak didukung oleh browser/perangkat Anda.', 'error');
            return;
        }

        // Ambil catatan opsional di bawah tombol pulang
        const notesInput = container.querySelector('#att-notes-input');
        const notesValue = notesInput ? notesInput.value.trim() : '';

        // Cek jika strict radius diaktifkan dan posisi terdeteksi di luar radius
        if (attendanceDataCache?.strict_radius && attendanceUserLocation) {
            const limit = attendanceDataCache.school_location?.radius_meters || 100;
            if (attendanceUserLocation.distance > limit) {
                showToast(`Anda berada ${attendanceUserLocation.distance}m dari sekolah (Maksimal ${limit}m). Presensi harus dalam radius sekolah.`, 'error');
                return;
            }
        }

        const originalHtml = button.innerHTML;
        button.disabled = true;
        button.innerHTML = `
            <div class="w-12 h-12 rounded-2xl bg-white/20 flex items-center justify-center mb-1.5 shadow-inner">
                <svg class="animate-spin h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg>
            </div>
            <span class="text-xs font-black tracking-tight uppercase">Membaca GPS &amp; Mengirim...</span>
            <span class="text-[10px] text-white/80 font-medium mt-0.5">Memvalidasi koordinat presensi</span>
        `;

        const executeSubmit = async (lat, lon, acc) => {
            try {
                const payload = {
                    latitude: lat,
                    longitude: lon,
                    accuracy: acc,
                    type: type,
                };
                if (notesValue) {
                    payload.notes = notesValue;
                }

                const res = await SIAP_API.submitAttendance(payload);

                if (res.success) {
                    showToast(res.message || 'Presensi berhasil dicatat!', 'success');
                    if (notesInput) notesInput.value = '';
                    window.initAttendance(container);
                } else {
                    showToast(res.message || 'Presensi ditolak.', 'error');
                }
            } catch (err) {
                showToast(err.message || 'Gagal menyimpan presensi.', 'error');
            } finally {
                button.disabled = false;
                button.innerHTML = originalHtml;
            }
        };

        // Jika lokasi sudah dicache kurang dari 10 detik lalu, langsung kirim
        if (attendanceUserLocation && (Date.now() - attendanceUserLocation.timestamp < 10000)) {
            await executeSubmit(
                attendanceUserLocation.latitude,
                attendanceUserLocation.longitude,
                attendanceUserLocation.accuracy
            );
            return;
        }

        navigator.geolocation.getCurrentPosition(
            async (pos) => {
                const lat = pos.coords.latitude;
                const lon = pos.coords.longitude;
                const acc = Math.round(pos.coords.accuracy);

                attendanceUserLocation = {
                    latitude: lat,
                    longitude: lon,
                    accuracy: acc,
                    timestamp: Date.now()
                };

                await executeSubmit(lat, lon, acc);
            },
            (err) => {
                button.disabled = false;
                button.innerHTML = originalHtml;
                let msg = 'Gagal mendeteksi lokasi GPS.';
                if (err.code === 1) msg = 'Izin akses lokasi (GPS) ditolak oleh browser/HP. Mohon aktifkan izin lokasi.';
                else if (err.code === 2) msg = 'Sinyal lokasi tidak dapat ditentukan.';
                else if (err.code === 3) msg = 'Waktu deteksi GPS habis (timeout).';
                showToast(msg, 'error');
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
        );
    }

    // ==========================================
    // 4. TASKS VIEW CONTROLLER
    // ==========================================
    window.initTasks = async function(container, params = {}) {
        let currentDate = params.date || null;

        async function fetchTasks(date) {
            try {
                const res = await SIAP_API.getTasks(date);
                if (res.success && res.data) {
                    tasksDataCache = res.data;
                    renderTasksContent(container, res.data);
                }
            } catch (err) {
                console.error('Error fetching tasks:', err);
                showToast('Gagal memuat data tugas.', 'error');
            }
        }

        await fetchTasks(currentDate);
        window.refreshTasks = () => fetchTasks(tasksDataCache?.target_date?.current || null);

        // Bind date navigation
        const btnPrev = container.querySelector('#tasks-btn-prev');
        const btnNext = container.querySelector('#tasks-btn-next');

        if (btnPrev) {
            btnPrev.onclick = () => {
                if (tasksDataCache?.target_date?.prev) {
                    fetchTasks(tasksDataCache.target_date.prev);
                }
            };
        }
        if (btnNext) {
            btnNext.onclick = () => {
                if (tasksDataCache?.target_date?.next) {
                    fetchTasks(tasksDataCache.target_date.next);
                }
            };
        }
    };

    function renderTasksContent(container, data) {
        // 1. Date label
        const dateLabel = container.querySelector('#tasks-target-date-label');
        if (dateLabel) {
            const isToday = data.target_date?.is_today;
            dateLabel.textContent = isToday
                ? `Hari Ini (${data.target_date?.day_name}, ${data.target_date?.current})`
                : `${data.target_date?.day_name}, ${data.target_date?.current}`;
        }

        // 1.5 KARTU UTAMA: UNIFIED LIVE MONITORING
        const cardLm = container.querySelector('#card-unified-live-monitoring');
        const lmData = data.live_monitoring;

        if (lmData) {
            if (cardLm) cardLm.classList.remove('hidden');

            const roleBadge = container.querySelector('#lm-role-badge');
            const scopeSub = container.querySelector('#lm-scope-subtitle');
            const siswaScope = container.querySelector('#lm-siswa-scope');
            const siswaSummary = container.querySelector('#lm-siswa-summary-count');

            if (roleBadge) roleBadge.textContent = lmData.role_label || 'Live Monitoring';
            if (scopeSub) scopeSub.textContent = `Monitoring Terpadu: ${lmData.scope_label || 'KBM & Aktivitas'}`;
            if (siswaScope) siswaScope.textContent = `(${lmData.scope_label || 'Sekolah'})`;

            // Stat Siswa (8 Status)
            const s = lmData.siswa || {};
            const statHadir = container.querySelector('#lm-stat-hadir');
            const statTelat = container.querySelector('#lm-stat-telat');
            const statSakit = container.querySelector('#lm-stat-sakit');
            const statIzin = container.querySelector('#lm-stat-izin');
            const statDispen = container.querySelector('#lm-stat-dispen');
            const statPulang = container.querySelector('#lm-stat-pulang');
            const statAlfa = container.querySelector('#lm-stat-alfa');
            const statBolos = container.querySelector('#lm-stat-bolos');

            if (statHadir) statHadir.textContent = s.hadir || 0;
            if (statTelat) statTelat.textContent = s.telat || 0;
            if (statSakit) statSakit.textContent = s.sakit || 0;
            if (statIzin) statIzin.textContent = s.izin || 0;
            if (statDispen) statDispen.textContent = s.dispen || 0;
            if (statPulang) statPulang.textContent = s.pulang || 0;
            if (statAlfa) statAlfa.textContent = s.alfa || 0;
            if (statBolos) statBolos.textContent = s.bolos || 0;

            if (siswaSummary) {
                const totalNonHadir = (s.telat || 0) + (s.sakit || 0) + (s.izin || 0) + (s.dispen || 0) + (s.pulang || 0) + (s.alfa || 0) + (s.bolos || 0);
                siswaSummary.textContent = `${s.hadir || 0} Hadir • ${totalNonHadir} Catatan`;
            }

            // Stat GTK (Khusus Kurikulum & Kepala Sekolah)
            const gtkSection = container.querySelector('#lm-gtk-section');
            const g = lmData.gtk;

            if (g && lmData.can_view_gtk) {
                if (gtkSection) gtkSection.classList.remove('hidden');
                const gtkTotalLabel = container.querySelector('#lm-gtk-total-label');
                const statGtkHadir = container.querySelector('#lm-stat-gtk-hadir');
                const statGtkTelat = container.querySelector('#lm-stat-gtk-telat');
                const statGtkBelum = container.querySelector('#lm-stat-gtk-belum');
                const statGtkIzin = container.querySelector('#lm-stat-gtk-izin');
                const statInval = container.querySelector('#lm-stat-inval');
                const statUnattended = container.querySelector('#lm-stat-unattended');

                if (gtkTotalLabel) gtkTotalLabel.textContent = `Total Wajib: ${g.total_expected || 0} GTK`;
                if (statGtkHadir) statGtkHadir.textContent = g.hadir || 0;
                if (statGtkTelat) statGtkTelat.textContent = g.terlambat || 0;
                if (statGtkBelum) statGtkBelum.textContent = g.belum_masuk || 0;
                if (statGtkIzin) statGtkIzin.textContent = g.izin || 0;
                if (statInval) statInval.textContent = g.inval || 0;
                if (statUnattended) statUnattended.textContent = g.unattended_kbm || 0;
            } else {
                if (gtkSection) gtkSection.classList.add('hidden');
            }

            // Timeline Feed
            const timelineList = container.querySelector('#lm-timeline-list');
            const timelineCount = container.querySelector('#lm-timeline-count');
            const events = lmData.timeline || [];

            if (timelineCount) timelineCount.textContent = `${events.length} Peristiwa`;

            if (timelineList) {
                if (events.length === 0) {
                    timelineList.innerHTML = '<div class="text-center py-3 text-xs text-slate-400">Belum ada peristiwa/kejadian pada tanggal ini.</div>';
                } else {
                    timelineList.innerHTML = events.map(ev => {
                        const badgeColorMap = {
                            rose: 'bg-rose-100 text-rose-800 border-rose-200',
                            amber: 'bg-amber-100 text-amber-800 border-amber-200',
                            purple: 'bg-purple-100 text-purple-800 border-purple-200',
                            slate: 'bg-slate-900 text-white border-slate-900',
                            indigo: 'bg-indigo-100 text-indigo-800 border-indigo-200',
                            blue: 'bg-blue-100 text-blue-800 border-blue-200',
                            emerald: 'bg-emerald-100 text-emerald-800 border-emerald-200',
                        };
                        const badgeColorClass = badgeColorMap[ev.badge_color] || 'bg-slate-100 text-slate-700 border-slate-200';

                        return `
                            <div class="p-2 rounded-xl bg-white border border-slate-100 shadow-2xs hover:border-emerald-200 transition">
                                <div class="flex items-center justify-between mb-1">
                                    <div class="flex items-center gap-1.5 overflow-hidden">
                                        <span class="font-bold text-slate-800 text-[11px] truncate">${ev.person_name}</span>
                                        <span class="text-[9px] text-slate-400 font-mono flex-shrink-0">(${ev.classroom_name})</span>
                                    </div>
                                    <span class="px-1.5 py-0.2 rounded text-[8px] font-bold border ${badgeColorClass} uppercase font-mono flex-shrink-0">${ev.badge}</span>
                                </div>
                                <div class="flex items-center gap-1.5 text-[9px] text-slate-500 mb-1">
                                    <span class="font-mono font-bold text-slate-700">⏱ ${ev.time}</span>
                                    <span>•</span>
                                    <span class="font-semibold text-slate-600">${ev.title}</span>
                                </div>
                                ${ev.description ? `<p class="text-[10px] text-slate-600 leading-snug mb-1">${ev.description}</p>` : ''}
                                ${ev.action_taken ? `<div class="text-[9px] text-emerald-700 font-medium">✓ ${ev.action_taken}</div>` : ''}
                            </div>
                        `;
                    }).join('');
                }
            }
        } else {
            if (cardLm) cardLm.classList.add('hidden');
        }

        // 2. Kartu Izin & Cuti Saya
        const badgeMyPermit = container.querySelector('#badge-my-permit-status');
        const infoMyPermit = container.querySelector('#info-my-permit-today');
        const textMyPermit = container.querySelector('#text-my-permit-today');
        const myPermitToday = data.gtk_permits?.my_permit_today;

        if (myPermitToday) {
            if (badgeMyPermit) badgeMyPermit.classList.remove('hidden');
            if (infoMyPermit) infoMyPermit.classList.remove('hidden');
            if (textMyPermit) {
                textMyPermit.textContent = `${(myPermitToday.type || 'Izin').toUpperCase()}: ${myPermitToday.reason || 'Izin Resmi'}`;
            }
        } else {
            if (badgeMyPermit) badgeMyPermit.classList.add('hidden');
            if (infoMyPermit) infoMyPermit.classList.add('hidden');
        }

        // 3. Kartu Piket Sekolah
        const cardPiket = container.querySelector('#card-piket-section');
        const piketLocationSub = container.querySelector('#piket-location-sub');
        const badgePiketToday = container.querySelector('#badge-piket-today');
        const isPiketToday = data.piket?.is_piket_today;
        const weeklyDays = data.piket?.weekly_days || [];

        if (isPiketToday || weeklyDays.length > 0) {
            if (cardPiket) cardPiket.classList.remove('hidden');
            if (isPiketToday) {
                if (badgePiketToday) {
                    badgePiketToday.textContent = 'Aktif Hari Ini';
                    badgePiketToday.className = 'px-2 py-0.5 rounded-full text-[9px] font-bold bg-purple-600 text-white shadow-xs';
                }
                if (piketLocationSub) {
                    piketLocationSub.textContent = 'Bertugas Hari Ini di Meja Piket & Gerbang';
                }
            } else {
                if (badgePiketToday) {
                    badgePiketToday.textContent = `Piket: ${weeklyDays.join(', ')}`;
                    badgePiketToday.className = 'px-2 py-0.5 rounded-full text-[9px] font-bold bg-purple-100 text-purple-800';
                }
                if (piketLocationSub) {
                    piketLocationSub.textContent = `Jadwal Piket Anda: ${weeklyDays.join(', ')}`;
                }
            }
        } else {
            if (cardPiket) cardPiket.classList.add('hidden');
        }

        // 4. Kartu Tugas Wali Kelas & Live Monitoring 8 Status
        const cardHomeroom = container.querySelector('#card-homeroom-section');
        const homeroomClsName = container.querySelector('#homeroom-classroom-name');
        const btnHomeroomPermits = container.querySelector('#btn-homeroom-permits');
        const btnHomeroomRecap = container.querySelector('#btn-homeroom-recap');
        const homeroomEventCounter = container.querySelector('#homeroom-event-counter');
        const eventList = container.querySelector('#tasks-student-events');

        if (data.homeroom) {
            if (cardHomeroom) cardHomeroom.classList.remove('hidden');
            if (homeroomClsName) homeroomClsName.textContent = data.homeroom.classroom?.name || 'Kelas Perwalian';

            if (btnHomeroomPermits && data.homeroom.classroom?.id) {
                btnHomeroomPermits.href = `/attendance/homeroom-permits?classroom_id=${data.homeroom.classroom.id}&date=${data.target_date?.current}`;
            }
            if (btnHomeroomRecap && data.homeroom.classroom?.id) {
                btnHomeroomRecap.href = `/attendance/homeroom-recap?classroom_id=${data.homeroom.classroom.id}&date=${data.target_date?.current}`;
            }

            const s = data.homeroom.summary || {};
            const setVal = (id, val) => {
                const el = container.querySelector(id);
                if (el) el.textContent = val ?? 0;
            };

            setVal('#stat-hadir', s.hadir_count);
            setVal('#stat-sakit', s.sakit_count);
            setVal('#stat-izin', s.izin_count);
            setVal('#stat-dispen', s.dispen_count);
            setVal('#stat-terlambat', s.late_count);
            setVal('#stat-pulang', s.pulang_count);
            setVal('#stat-alfa', s.alfa_count);
            setVal('#stat-bolos', s.truancy_count);

            const rateEl = container.querySelector('#stat-rate');
            if (rateEl) rateEl.textContent = s.attendance_rate !== null ? `${s.attendance_rate}%` : '-';

            // Log Kejadian Siswa Rombel
            const events = data.homeroom.student_events || [];
            if (homeroomEventCounter) {
                homeroomEventCounter.textContent = `${events.length} Catatan`;
            }

            if (eventList) {
                if (events.length === 0) {
                    eventList.innerHTML = '<div class="text-center py-4 text-xs text-slate-400">Tidak ada kejadian/pelanggaran siswa pada tanggal ini.</div>';
                } else {
                    eventList.innerHTML = events.map(ev => {
                        const badgeColor = ev.badge_class === 'danger' ? 'bg-rose-100 text-rose-700' : (ev.badge_class === 'dark' ? 'bg-slate-900 text-white' : 'bg-amber-100 text-amber-800');
                        return `
                            <div class="p-3 card-subtle border-slate-200/80 bg-white">
                                <div class="flex items-start justify-between gap-1.5 mb-1">
                                    <div class="overflow-hidden">
                                        <h5 class="text-xs font-bold text-slate-800 truncate">${ev.student_name}</h5>
                                        <p class="text-[10px] text-slate-400 mt-0.5">${ev.time !== '-' ? ev.time + ' WIB • ' : ''}${ev.title}</p>
                                    </div>
                                    <span class="px-2 py-0.5 rounded text-[9px] font-bold ${badgeColor} flex-shrink-0">${ev.badge}</span>
                                </div>
                                <div class="p-2 rounded-lg bg-slate-50 text-[11px] text-slate-700 mt-1.5 space-y-0.5">
                                    <div><strong>Keterangan:</strong> ${ev.description}</div>
                                    ${ev.action ? `<div class="text-slate-500"><strong>Tindakan:</strong> ${ev.action}</div>` : ''}
                                    ${ev.note ? `<div class="text-rose-600"><strong>Catatan:</strong> ${ev.note}</div>` : ''}
                                </div>
                                <div class="flex items-center justify-between text-[10px] text-slate-400 mt-1.5 pt-1 border-t border-slate-100">
                                    <span>Dicatat: ${ev.officer || 'Petugas'}</span>
                                    ${ev.photo ? `<a href="${ev.photo}" target="_blank" class="text-blue-600 font-semibold hover:underline">Lihat Bukti Foto</a>` : ''}
                                </div>
                            </div>
                        `;
                    }).join('');
                }
            }
        } else {
            if (cardHomeroom) cardHomeroom.classList.add('hidden');
        }

        // 5. Kartu Visitasi Siswa & BK
        const cardVisitation = container.querySelector('#card-visitation-section');
        const badgeVisitationCount = container.querySelector('#badge-visitation-count');
        const listRecentVisitation = container.querySelector('#visitation-recent-list');
        const visitations = data.visitations || {};

        if (visitations.can_access) {
            if (cardVisitation) cardVisitation.classList.remove('hidden');
            if (badgeVisitationCount) {
                badgeVisitationCount.textContent = `${visitations.pending_count || 0} Agenda`;
            }
            if (listRecentVisitation) {
                const recentV = visitations.recent || [];
                if (recentV.length === 0) {
                    listRecentVisitation.innerHTML = '<div class="text-center py-2 text-xs text-slate-400">Belum ada agenda visitasi siswa.</div>';
                } else {
                    listRecentVisitation.innerHTML = recentV.map(v => `
                        <div class="p-2.5 rounded-xl bg-white border border-cyan-200/70 text-xs">
                            <div class="flex items-center justify-between gap-1 mb-1">
                                <span class="font-bold text-slate-800 truncate">${v.student_name} (${v.classroom_name})</span>
                                <span class="px-2 py-0.2 rounded text-[9px] font-bold bg-cyan-100 text-cyan-800">${v.status}</span>
                            </div>
                            <div class="flex items-center justify-between text-[10px] text-slate-500">
                                <span>${v.purpose || 'Home Visit'}</span>
                                <span class="font-mono text-slate-400">${v.planned_date}</span>
                            </div>
                        </div>
                    `).join('');
                }
            }
        } else {
            if (cardVisitation) cardVisitation.classList.add('hidden');
        }

        // 6. Kartu Tugas Tim Kedisiplinan (Interactive Widget)
        const cardDiscipline = container.querySelector('#card-discipline-section');
        const disciplineData = data.discipline;

        if (disciplineData && disciplineData.can_access) {
            if (cardDiscipline) cardDiscipline.classList.remove('hidden');
            const summary = disciplineData.summary || {};
            const badgeDiscipline = container.querySelector('#badge-discipline-pending-count');
            const statToday = container.querySelector('#stat-discipline-today');
            const statWaiting = container.querySelector('#stat-discipline-waiting');
            const statFollowup = container.querySelector('#stat-discipline-followup');
            const statActive = container.querySelector('#stat-discipline-active');
            const listDiscipline = container.querySelector('#list-discipline-incidents');

            if (badgeDiscipline) badgeDiscipline.textContent = `${summary.waiting_verification_count || 0} Menunggu`;
            if (statToday) statToday.textContent = summary.today_count || 0;
            if (statWaiting) statWaiting.textContent = summary.waiting_verification_count || 0;
            if (statFollowup) statFollowup.textContent = summary.follow_up_count || 0;
            if (statActive) statActive.textContent = summary.active_monitoring_count || 0;

            if (listDiscipline) {
                const incidents = disciplineData.action_required_incidents || [];
                if (incidents.length === 0) {
                    listDiscipline.innerHTML = '<div class="text-center py-3 text-xs text-slate-400">Tidak ada kasus yang menunggu verifikasi.</div>';
                } else {
                    listDiscipline.innerHTML = incidents.map(inc => `
                        <div class="p-2.5 rounded-xl bg-white border border-rose-200/80 text-xs shadow-xs">
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-bold text-slate-800">${inc.student_name} <span class="text-[10px] text-slate-400 font-normal">(${inc.classroom_name})</span></span>
                                <span class="px-2 py-0.5 rounded text-[9px] font-bold ${inc.penalty_points > 0 ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-700'} uppercase font-mono">${inc.penalty_points} Poin</span>
                            </div>
                            <div class="flex items-center gap-1.5 mb-1.5">
                                <span class="px-1.5 py-0.2 rounded text-[8px] font-bold bg-amber-100 text-amber-800 uppercase">${inc.category_label}</span>
                                <span class="text-[9px] text-slate-400 font-mono">${inc.incident_date} ${inc.incident_time}</span>
                            </div>
                            <p class="text-[11px] text-slate-600 mb-1 leading-tight">${inc.description || 'Tidak ada deskripsi'}</p>
                            ${inc.confiscated_items ? `<p class="text-[10px] text-rose-700 font-medium mb-1.5">📦 Sitaan: ${inc.confiscated_items}</p>` : ''}
                            <div class="flex items-center justify-between pt-1.5 border-t border-slate-100 text-[10px]">
                                <span class="text-slate-400">Oleh: ${inc.recorded_by_name}</span>
                                <div class="flex items-center gap-1.5">
                                    <button onclick="quickVerifyDiscipline(${inc.id}, 'terverifikasi')" class="px-2.5 py-1 rounded-lg bg-emerald-600 text-white font-bold hover:bg-emerald-700 active:scale-95 transition">
                                        ✓ Sahkan
                                    </button>
                                    <button onclick="quickVerifyDiscipline(${inc.id}, 'bukan_pelanggaran')" class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 font-bold hover:bg-slate-200 active:scale-95 transition">
                                        Bukan
                                    </button>
                                </div>
                            </div>
                        </div>
                    `).join('');
                }
            }
        } else {
            if (cardDiscipline) cardDiscipline.classList.add('hidden');
        }

        // 7. Kartu Tugas Layanan BK (Interactive Widget)
        const cardBk = container.querySelector('#card-bk-section');
        const bkData = data.bk;

        if (bkData && bkData.can_access) {
            if (cardBk) cardBk.classList.remove('hidden');
            const summary = bkData.summary || {};
            const badgeBk = container.querySelector('#badge-bk-waiting-count');
            const statTotal = container.querySelector('#stat-bk-total');
            const statMenunggu = container.querySelector('#stat-bk-menunggu');
            const statDiproses = container.querySelector('#stat-bk-diproses');
            const statSelesai = container.querySelector('#stat-bk-selesai');
            const listBk = container.querySelector('#list-bk-referrals');

            if (badgeBk) badgeBk.textContent = `${summary.menunggu || 0} Menunggu`;
            if (statTotal) statTotal.textContent = summary.total_rujukan || 0;
            if (statMenunggu) statMenunggu.textContent = summary.menunggu || 0;
            if (statDiproses) statDiproses.textContent = summary.diproses || 0;
            if (statSelesai) statSelesai.textContent = summary.selesai || 0;

            if (listBk) {
                const referrals = bkData.recent_referrals || [];
                if (referrals.length === 0) {
                    listBk.innerHTML = '<div class="text-center py-3 text-xs text-slate-400">Tidak ada rujukan meja piket yang menunggu.</div>';
                } else {
                    listBk.innerHTML = referrals.map(ref => {
                        const isMenunggu = ref.referral_status === 'menunggu';
                        const isDiproses = ref.referral_status === 'diproses';
                        const statusBadgeClass = isMenunggu ? 'bg-amber-100 text-amber-800' : (isDiproses ? 'bg-blue-100 text-blue-800' : 'bg-emerald-100 text-emerald-800');

                        return `
                            <div class="p-2.5 rounded-xl bg-white border border-indigo-200/80 text-xs shadow-xs">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-bold text-slate-800">${ref.student_name} <span class="text-[10px] text-slate-400 font-normal">(${ref.classroom_name})</span></span>
                                    <span class="px-2 py-0.5 rounded text-[9px] font-bold ${statusBadgeClass} uppercase">${ref.referral_status_label}</span>
                                </div>
                                <div class="flex items-center gap-1.5 mb-1.5">
                                    <span class="px-1.5 py-0.2 rounded text-[8px] font-bold bg-indigo-100 text-indigo-800 uppercase">${ref.category_label}</span>
                                    <span class="text-[9px] text-slate-400 font-mono">${ref.incident_date}</span>
                                </div>
                                <p class="text-[11px] text-slate-600 mb-1 leading-tight">${ref.description || 'Tidak ada deskripsi'}</p>
                                ${ref.action_taken ? `<p class="text-[10px] text-indigo-900 font-medium mb-1.5">🎯 Piket: ${ref.action_taken}</p>` : ''}
                                <div class="flex items-center justify-between pt-1.5 border-t border-slate-100 text-[10px]">
                                    <a href="/bk/rujukan/${ref.id}" class="text-indigo-600 font-bold hover:underline">Tinjau &raquo;</a>
                                    <div class="flex items-center gap-1.5">
                                        ${isMenunggu ? `
                                            <button onclick="quickUpdateBkStatus(${ref.id}, 'diproses')" class="px-2.5 py-1 rounded-lg bg-indigo-600 text-white font-bold hover:bg-indigo-700 active:scale-95 transition">
                                                ⚡ Tangani
                                            </button>
                                        ` : (isDiproses ? `
                                            <button onclick="quickUpdateBkStatus(${ref.id}, 'selesai')" class="px-2.5 py-1 rounded-lg bg-emerald-600 text-white font-bold hover:bg-emerald-700 active:scale-95 transition">
                                                ✓ Selesai
                                            </button>
                                        ` : '')}
                                    </div>
                                </div>
                            </div>
                        `;
                    }).join('');
                }
            }
        } else {
            if (cardBk) cardBk.classList.add('hidden');
        }

        // 7. Kartu Persetujuan Izin GTK (Kepala Sekolah & Kurikulum)
        const cardAdminPermits = container.querySelector('#card-admin-gtk-permits');
        const badgePendingPermits = container.querySelector('#badge-pending-permits-count');
        const listPendingPermits = container.querySelector('#list-pending-gtk-permits');
        const countApprovedToday = container.querySelector('#count-approved-today');
        const pendingPermits = data.gtk_permits?.pending_approval || [];
        const todayApproved = data.gtk_permits?.today_approved || [];

        if (pendingPermits.length > 0 || todayApproved.length > 0) {
            if (cardAdminPermits) cardAdminPermits.classList.remove('hidden');
            if (badgePendingPermits) badgePendingPermits.textContent = `${pendingPermits.length} Pengajuan`;
            if (countApprovedToday) countApprovedToday.textContent = `${todayApproved.length} Orang`;

            if (listPendingPermits) {
                if (pendingPermits.length === 0) {
                    listPendingPermits.innerHTML = '<div class="text-center py-2 text-xs text-slate-400">Tidak ada pengajuan izin GTK yang menunggu.</div>';
                } else {
                    listPendingPermits.innerHTML = pendingPermits.map(p => `
                        <div class="p-2.5 rounded-xl bg-white border border-blue-200/80 text-xs">
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-bold text-slate-800">${p.user_name}</span>
                                <span class="px-2 py-0.2 rounded text-[9px] font-bold bg-amber-100 text-amber-800 uppercase">${p.permit_type} (${p.days_count}h)</span>
                            </div>
                            <p class="text-[11px] text-slate-600 mb-1.5 italic">"${p.reason || 'Izin'}"</p>
                            <div class="flex items-center justify-between pt-1 border-t border-slate-100 text-[10px]">
                                <span class="text-slate-400 font-mono">${p.start_date} s/d ${p.end_date}</span>
                                <a href="/izin-cuti-gtk" class="px-2 py-0.5 rounded bg-blue-600 text-white font-bold hover:bg-blue-700">Tinjau</a>
                            </div>
                        </div>
                    `).join('');
                }
            }
        } else {
            if (cardAdminPermits) cardAdminPermits.classList.add('hidden');
        }

        // 8. Kartu Pengesahan Supervisi Akademik (Kepala Sekolah & Kurikulum)
        const cardSupervision = container.querySelector('#card-supervision-section');
        const badgeSupervisionCount = container.querySelector('#badge-supervision-count');
        const listSupervisions = container.querySelector('#list-pending-supervisions');
        const pendingSupervisions = data.supervisions?.pending || [];

        if (pendingSupervisions.length > 0) {
            if (cardSupervision) cardSupervision.classList.remove('hidden');
            if (badgeSupervisionCount) badgeSupervisionCount.textContent = `${pendingSupervisions.length} Berkas`;
            if (listSupervisions) {
                listSupervisions.innerHTML = pendingSupervisions.map(s => `
                    <div class="p-2.5 rounded-xl bg-white border border-indigo-200/80 text-xs">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-bold text-slate-800">${s.teacher_name}</span>
                            <span class="px-2 py-0.2 rounded text-[9px] font-bold bg-amber-100 text-amber-800">Menunggu</span>
                        </div>
                        <div class="flex items-center justify-between text-[10px] text-slate-500 mb-1.5">
                            <span>Supervisor: ${s.supervisor_name}</span>
                            <span class="font-mono text-slate-400">${s.obs_date}</span>
                        </div>
                        <a href="/persetujuan" class="w-full py-1 rounded bg-indigo-600 text-white font-bold text-center block text-[10px] hover:bg-indigo-700">Buka &amp; Sahkan Supervisi</a>
                    </div>
                `).join('');
            }
        } else {
            if (cardSupervision) cardSupervision.classList.add('hidden');
        }
    }

    window.selectHomeroomCategory = function(cat) {
        if (!tasksDataCache?.homeroom) return;
        const s = tasksDataCache.homeroom.summary || {};
        const labels = {
            hadir: `Hadir: ${s.hadir_count || 0} siswa aktif di kelas.`,
            late: `Terlambat: ${s.late_count || 0} siswa izin masuk gerbang terlambat.`,
            sakit: `Sakit: ${s.sakit_count || 0} siswa tercatat sakit.`,
            izin: `Izin: ${s.izin_count || 0} siswa berhalangan izin.`,
            dispen: `Dispensasi: ${s.dispen_count || 0} siswa dispensasi tugas/lomba.`,
            pulang: `Pulang: ${s.pulang_count || 0} siswa izin pulang lebih awal.`,
            alfa: `Alfa: ${s.alfa_count || 0} siswa tidak hadir tanpa keterangan.`,
            truancy: `Indikasi Bolos: ${s.truancy_count || 0} siswa terdeteksi alfa di tengah jam KBM.`,
        };
        showToast(labels[cat] || `Kategori ${cat}`, 'info');
    };

    window.quickUpdateBkStatus = async function(incidentId, status) {
        try {
            showToast('Memperbarui status rujukan BK...', 'info');
            const res = await SIAP_API.updateBkReferralStatus(incidentId, status);
            if (res && res.success) {
                showToast(res.message || 'Status rujukan BK berhasil diperbarui!', 'success');
                if (window.refreshTasks) {
                    window.refreshTasks();
                }
            } else {
                showToast(res.message || 'Gagal memperbarui status rujukan BK.', 'error');
            }
        } catch (err) {
            showToast(err.message || 'Terjadi kesalahan sistem.', 'error');
        }
    };

    window.quickVerifyDiscipline = async function(incidentId, verificationStatus) {
        try {
            showToast('Memproses verifikasi insiden...', 'info');
            const res = await SIAP_API.verifyDisciplineIncident(incidentId, verificationStatus);
            if (res && res.success) {
                showToast(res.message || 'Verifikasi insiden berhasil disimpan!', 'success');
                if (window.refreshTasks) {
                    window.refreshTasks();
                }
            } else {
                showToast(res.message || 'Gagal memverifikasi insiden.', 'error');
            }
        } catch (err) {
            showToast(err.message || 'Terjadi kesalahan sistem.', 'error');
        }
    };

    window.toggleLmDrawer = function(category, type) {
        const drawer = document.getElementById('lm-drawer-detail');
        const drawerTitle = document.getElementById('lm-drawer-title');
        const drawerList = document.getElementById('lm-drawer-list');
        if (!drawer || !drawerList) return;

        if (!tasksDataCache?.live_monitoring) return;
        const lm = tasksDataCache.live_monitoring;

        let items = [];
        let title = '';

        if (category === 'siswa') {
            const detail = lm.siswa?.detail || {};
            items = detail[type] || [];
            const typeTitles = {
                hadir: 'Siswa Hadir',
                telat: 'Siswa Terlambat Masuk',
                sakit: 'Siswa Sakit',
                izin: 'Siswa Izin Tidak Masuk',
                dispen: 'Siswa Dispensasi Tugas/Lomba',
                pulang: 'Siswa Izin Pulang Cepat',
                alfa: 'Siswa Alfa Mapel',
                bolos: 'Siswa Terindikasi Bolos KBM',
            };
            title = typeTitles[type] || `Siswa (${type})`;
        } else if (category === 'gtk') {
            const detail = lm.gtk?.detail || {};
            items = detail[type] || [];
            const typeTitles = {
                hadir: 'GTK Hadir Tepat Waktu',
                telat: 'GTK Terlambat Masuk',
                belum_masuk: 'GTK Belum Absen Masuk (Wajib Hadir)',
                izin: 'GTK Izin / Cuti Resmi',
                inval: 'Penugasan Guru Inval Hari Ini',
                unattended: 'Jadwal KBM Belum Diabsen (Jam Lewat)',
            };
            title = typeTitles[type] || `GTK (${type})`;
        }

        if (drawerTitle) drawerTitle.textContent = `${title} (${items.length})`;

        if (items.length === 0) {
            drawerList.innerHTML = `<div class="text-center py-2 text-slate-400 text-[11px]">Tidak ada data untuk ${title.toLowerCase()}.</div>`;
        } else {
            if (category === 'siswa') {
                drawerList.innerHTML = items.map(item => `
                    <div class="p-1.5 rounded-lg bg-white border border-slate-200 flex items-center justify-between">
                        <div>
                            <span class="font-bold text-slate-800 text-[11px] block">${item.name}</span>
                            <span class="text-[9px] text-slate-500">${item.classroom} ${item.reason ? `• ${item.reason}` : ''}</span>
                        </div>
                        ${item.time ? `<span class="text-[9px] font-mono font-bold text-slate-600 bg-slate-100 px-1 rounded">${item.time}</span>` : ''}
                    </div>
                `).join('');
            } else if (category === 'gtk') {
                if (type === 'inval') {
                    drawerList.innerHTML = items.map(item => `
                        <div class="p-1.5 rounded-lg bg-white border border-indigo-200">
                            <div class="flex items-center justify-between mb-0.5">
                                <span class="font-bold text-indigo-950 text-[11px]">${item.classroom} &bull; ${item.subject}</span>
                                <span class="text-[9px] font-mono font-bold text-indigo-700 bg-indigo-50 px-1 rounded">${item.period}</span>
                            </div>
                            <p class="text-[10px] text-slate-600">Pengganti: <strong>${item.inval_teacher}</strong> (Asli: ${item.absent_teacher})</p>
                        </div>
                    `).join('');
                } else if (type === 'unattended') {
                    drawerList.innerHTML = items.map(item => `
                        <div class="p-1.5 rounded-lg bg-white border border-rose-200">
                            <div class="flex items-center justify-between mb-0.5">
                                <span class="font-bold text-slate-800 text-[11px]">${item.teacher_name}</span>
                                <span class="text-[9px] font-mono font-bold text-rose-700 bg-rose-50 px-1 rounded">${item.period}</span>
                            </div>
                            <p class="text-[10px] text-slate-500">${item.classroom} &bull; ${item.subject}</p>
                        </div>
                    `).join('');
                } else {
                    drawerList.innerHTML = items.map(item => `
                        <div class="p-1.5 rounded-lg bg-white border border-slate-200 flex items-center justify-between">
                            <div>
                                <span class="font-bold text-slate-800 text-[11px] block">${item.user_name}</span>
                                <span class="text-[9px] text-slate-500">${item.duty_info || item.position || item.reason || item.permit_type || '-'}</span>
                            </div>
                            ${item.time ? `<span class="text-[9px] font-mono font-bold text-slate-600 bg-slate-100 px-1 rounded">${item.time}</span>` : ''}
                        </div>
                    `).join('');
                }
            }
        }

        drawer.classList.remove('hidden');
        drawer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    window.closeLmDrawer = function() {
        const drawer = document.getElementById('lm-drawer-detail');
        if (drawer) drawer.classList.add('hidden');
    };

    // ==========================================
    // 5. PROFILE VIEW CONTROLLER
    // ==========================================
    function resizeImageToBase64(file, maxWidth = 800, maxHeight = 800, quality = 0.85) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    let width = img.width;
                    let height = img.height;

                    if (width > height) {
                        if (width > maxWidth) {
                            height = Math.round((height * maxWidth) / width);
                            width = maxWidth;
                        }
                    } else {
                        if (height > maxHeight) {
                            width = Math.round((width * maxHeight) / height);
                            height = maxHeight;
                        }
                    }

                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);
                    resolve(canvas.toDataURL('image/jpeg', quality));
                };
                img.onerror = reject;
                img.src = e.target.result;
            };
            reader.onerror = reject;
            reader.readAsDataURL(file);
        });
    }

    window.initProfile = async function(container) {
        async function fetchProfile() {
            try {
                const res = await SIAP_API.getProfile();
                if (res.success && res.data) {
                    profileDataCache = res.data;
                    renderProfileContent(container, res.data);
                }
            } catch (err) {
                console.error('Error fetching profile:', err);
                showToast('Gagal memuat profil GTK.', 'error');
            }
        }

        // Bind Avatar Upload Handler
        const avatarInput = container.querySelector('#prof-avatar-input');
        if (avatarInput) {
            avatarInput.onchange = async function(e) {
                const file = e.target.files?.[0];
                if (!file) return;

                if (!file.type.startsWith('image/')) {
                    showToast('Harap pilih file gambar (JPG, PNG, atau WebP).', 'warning');
                    return;
                }

                showToast('Mengunggah foto profil...', 'info');

                try {
                    const base64Data = await resizeImageToBase64(file, 800, 800, 0.85);
                    const res = await SIAP_API.updateProfile({
                        avatar_cropped_base64: base64Data,
                    });

                    if (res.success && res.data?.user) {
                        const updatedUser = res.data.user;
                        SIAP_ROUTER.setCurrentUser(updatedUser);
                        localStorage.setItem('siap_user_data', JSON.stringify(updatedUser));

                        const avatarEl = container.querySelector('#prof-avatar');
                        if (avatarEl && updatedUser.avatar_url) {
                            avatarEl.src = updatedUser.avatar_url + '?t=' + Date.now();
                        }

                        // Perbarui avatar di dashboard jika ada di DOM
                        const dashAvatar = document.querySelector('#dash-user-avatar');
                        if (dashAvatar && updatedUser.avatar_url) {
                            dashAvatar.src = updatedUser.avatar_url + '?t=' + Date.now();
                        }

                        // Perbarui avatar di top header jika ada di DOM
                        const topAvatar = document.querySelector('#top-user-avatar');
                        if (topAvatar && updatedUser.avatar_url) {
                            topAvatar.src = updatedUser.avatar_url + '?t=' + Date.now();
                        }

                        if (profileDataCache && profileDataCache.user) {
                            profileDataCache.user.avatar_url = updatedUser.avatar_url;
                        }

                        showToast('Foto profil berhasil diperbarui!', 'success');
                    } else {
                        showToast(res.message || 'Gagal memperbarui foto profil.', 'error');
                    }
                } catch (err) {
                    console.error('Avatar upload error:', err);
                    showToast('Gagal mengunggah foto profil.', 'error');
                } finally {
                    avatarInput.value = '';
                }
            };
        }

        // ==========================================
        // Modal Controls & Form Handlers (Profile & Password)
        // ==========================================
        const modalEditProfile = container.querySelector('#modal-edit-profile');
        const modalEditPassword = container.querySelector('#modal-edit-password');
        const formEditProfile = container.querySelector('#form-edit-profile');
        const formEditPassword = container.querySelector('#form-edit-password');

        const bottomNav = document.getElementById('mobile-bottom-nav');

        window.openEditProfileModal = function() {
            if (!modalEditProfile) return;
            const u = profileDataCache?.user || SIAP_ROUTER.getCurrentUser() || {};
            const inputName = container.querySelector('#edit-prof-name');
            const inputEmail = container.querySelector('#edit-prof-email');
            const inputPhone = container.querySelector('#edit-prof-phone');

            if (inputName) inputName.value = u.name || '';
            if (inputEmail) inputEmail.value = u.email || '';
            if (inputPhone) inputPhone.value = u.phone || '';

            if (bottomNav) bottomNav.classList.add('hidden');
            modalEditProfile.classList.remove('hidden');
        };

        window.closeEditProfileModal = function() {
            if (modalEditProfile) modalEditProfile.classList.add('hidden');
            if (bottomNav) bottomNav.classList.remove('hidden');
        };

        window.openEditPasswordModal = function() {
            if (!modalEditPassword) return;
            if (formEditPassword) formEditPassword.reset();
            if (bottomNav) bottomNav.classList.add('hidden');
            modalEditPassword.classList.remove('hidden');
        };

        window.closeEditPasswordModal = function() {
            if (modalEditPassword) modalEditPassword.classList.add('hidden');
            if (bottomNav) bottomNav.classList.remove('hidden');
        };

        // Form Submit: Edit Profil
        if (formEditProfile) {
            formEditProfile.onsubmit = async function(e) {
                e.preventDefault();
                const name = container.querySelector('#edit-prof-name').value.trim();
                const email = container.querySelector('#edit-prof-email').value.trim();
                const phone = container.querySelector('#edit-prof-phone').value.trim();
                const btnSubmit = container.querySelector('#btn-save-profile');

                if (!name || !email) {
                    showToast('Nama dan Email wajib diisi.', 'warning');
                    return;
                }

                btnSubmit.disabled = true;
                btnSubmit.innerHTML = `
                    <svg class="animate-spin h-4 w-4 mx-auto text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                `;

                try {
                    const res = await SIAP_API.updateProfile({ name, email, phone });
                    if (res.success && res.data?.user) {
                        const updatedUser = res.data.user;
                        SIAP_ROUTER.setCurrentUser(updatedUser);
                        localStorage.setItem('siap_user_data', JSON.stringify(updatedUser));

                        if (profileDataCache && profileDataCache.user) {
                            profileDataCache.user = { ...profileDataCache.user, ...updatedUser };
                            renderProfileContent(container, profileDataCache);
                        }

                        // Perbarui nama di dashboard jika aktif
                        const dashName = document.querySelector('#dash-user-name');
                        if (dashName) dashName.textContent = updatedUser.name;

                        showToast(res.message || 'Profil berhasil diperbarui!', 'success');
                        closeEditProfileModal();
                    } else {
                        showToast(res.message || 'Gagal memperbarui profil.', 'error');
                    }
                } catch (err) {
                    showToast(err.message || 'Terjadi kesalahan saat menyimpan profil.', 'error');
                } finally {
                    btnSubmit.disabled = false;
                    btnSubmit.textContent = 'Simpan Perubahan';
                }
            };
        }

        // Form Submit: Ganti Password
        if (formEditPassword) {
            formEditPassword.onsubmit = async function(e) {
                e.preventDefault();
                const currentPass = container.querySelector('#edit-pwd-current').value;
                const newPass = container.querySelector('#edit-pwd-new').value;
                const confirmPass = container.querySelector('#edit-pwd-confirm').value;
                const btnSubmit = container.querySelector('#btn-save-password');

                if (!currentPass || !newPass || !confirmPass) {
                    showToast('Semua kolom kata sandi wajib diisi.', 'warning');
                    return;
                }

                if (newPass.length < 6) {
                    showToast('Kata sandi baru minimal 6 karakter.', 'warning');
                    return;
                }

                if (newPass !== confirmPass) {
                    showToast('Konfirmasi kata sandi baru tidak cocok.', 'warning');
                    return;
                }

                btnSubmit.disabled = true;
                btnSubmit.innerHTML = `
                    <svg class="animate-spin h-4 w-4 mx-auto text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                `;

                try {
                    const res = await SIAP_API.updatePassword(currentPass, newPass, confirmPass);
                    if (res.success) {
                        showToast(res.message || 'Kata sandi berhasil diperbarui!', 'success');
                        formEditPassword.reset();
                        closeEditPasswordModal();
                    } else {
                        showToast(res.message || 'Gagal memperbarui kata sandi.', 'error');
                    }
                } catch (err) {
                    showToast(err.message || 'Kata sandi lama tidak cocok atau tidak valid.', 'error');
                } finally {
                    btnSubmit.disabled = false;
                    btnSubmit.textContent = 'Perbarui Kata Sandi';
                }
            };
        }

        if (profileDataCache) {
            renderProfileContent(container, profileDataCache);
            fetchProfile();
        } else {
            await fetchProfile();
        }
    };

    function renderProfileContent(container, data) {
        const u = data.user || {};

        const nameEl = container.querySelector('#prof-name');
        const nipEl = container.querySelector('#prof-nip');
        const nigsEl = container.querySelector('#prof-nigs');
        const emailEl = container.querySelector('#prof-email');
        const phoneEl = container.querySelector('#prof-phone');
        const avatarEl = container.querySelector('#prof-avatar');

        if (nameEl) nameEl.textContent = u.name || '-';
        if (nipEl) nipEl.textContent = u.nip || '-';
        if (nigsEl) nigsEl.textContent = u.nigs || '-';
        if (emailEl) emailEl.textContent = u.email || '-';
        if (phoneEl) phoneEl.textContent = u.phone || '-';
        if (avatarEl && u.avatar_url) avatarEl.src = u.avatar_url;

        // Active Decrees (SK GTK)
        const decreeList = container.querySelector('#prof-decrees-list');
        if (decreeList) {
            const decrees = data.active_decrees || [];
            if (decrees.length === 0) {
                decreeList.innerHTML = '<div class="text-center py-4 text-xs text-slate-400">Tidak ada dokumen SK GTK aktif saat ini.</div>';
            } else {
                decreeList.innerHTML = decrees.map(d => `
                    <div class="p-3 card-subtle mb-2.5">
                        <div class="flex items-start justify-between gap-2 mb-1.5">
                            <div>
                                <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-blue-100 text-blue-800 uppercase tracking-wider">${d.type}</span>
                                <h4 class="text-xs font-bold text-slate-800 mt-1">${d.title}</h4>
                                <p class="text-[11px] font-mono text-slate-500">${d.number}</p>
                            </div>
                            ${d.download_url ? `
                                <a href="${d.download_url}" target="_blank" class="px-2.5 py-1 bg-blue-600 text-white rounded-lg text-[10px] font-semibold hover:bg-blue-700 transition flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    PDF
                                </a>
                            ` : ''}
                        </div>
                        <div class="text-[10px] text-slate-400 mt-1 border-t border-slate-200/60 pt-1.5 flex justify-between">
                            <span>TMT: ${d.tmt_date}</span>
                            <span>Sampai: ${d.end_date}</span>
                        </div>
                    </div>
                `).join('');
            }
        }
    }

    // ==========================================
    // 6. INITIAL APP BOOTSTRAPPER
    // ==========================================
    async function initApp() {
        // 1. Cek sesi user terlebih dahulu
        try {
            const res = await SIAP_API.me();
            if (res && res.authenticated && res.data?.user) {
                SIAP_ROUTER.setCurrentUser(res.data.user);
                localStorage.setItem('siap_user_data', JSON.stringify(res.data.user));
                updateTopBarUser(res.data.user);
                fetchNotificationBadge();
            } else {
                SIAP_ROUTER.setCurrentUser(null);
                localStorage.removeItem('siap_user_data');
                updateTopBarUser(null);
            }
        } catch (err) {
            SIAP_ROUTER.setCurrentUser(null);
            updateTopBarUser(null);
        }

        // 2. Jalankan Router setelah sesi pengguna terkonfirmasi
        SIAP_ROUTER.init();
    }

    // Start App on DOM Ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initApp);
    } else {
        initApp();
    }
})();
