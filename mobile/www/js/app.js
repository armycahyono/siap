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

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
    window.escapeHtml = escapeHtml;

    function formatWhatsappNumber(phone) {
        if (!phone) return '';
        let clean = String(phone).replace(/\D/g, '');
        if (!clean) return '';
        if (clean.startsWith('0')) return '62' + clean.slice(1);
        if (clean.startsWith('8')) return '62' + clean;
        return clean;
    }
    window.formatWhatsappNumber = formatWhatsappNumber;

    function getWhatsappUrl(phone, message = '') {
        const clean = formatWhatsappNumber(phone);
        if (!clean) return '';
        const textParam = message ? `?text=${encodeURIComponent(message)}` : '';
        return `https://wa.me/${clean}${textParam}`;
    }
    window.getWhatsappUrl = getWhatsappUrl;

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

        // Piket Today / Management Badge
        const piketCard = container.querySelector('#dash-piket-card');
        const piketTitle = container.querySelector('#dash-piket-title');
        const piketDesc = container.querySelector('#dash-piket-desc');
        const piketInfo = data.piket_info;
        const currentUser = SIAP_ROUTER.getCurrentUser();
        const isSuperOrAdmin = currentUser?.role === 'admin' || currentUser?.is_superadmin || piketInfo?.is_management || piketInfo?.can_access;

        if (piketCard) {
            if (piketInfo?.is_piket_today) {
                piketCard.classList.remove('hidden');
                if (piketTitle) piketTitle.textContent = 'Petugas Piket Hari Ini';
                if (piketDesc) piketDesc.textContent = 'Anda bertugas di Meja Piket & Gerbang hari ini.';
            } else if (isSuperOrAdmin) {
                piketCard.classList.remove('hidden');
                if (piketTitle) piketTitle.textContent = 'Meja Piket Sekolah';
                if (piketDesc) piketDesc.textContent = 'Monitoring operasional presensi, izin siswa & kejadian hari ini.';
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

        window.refreshKbm = () => fetchKbmData();

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

    function renderUnifiedLiveMonitoring(container, lmData) {
        const cardLm = container.querySelector('#card-unified-live-monitoring');
        if (!cardLm) return;

        if (lmData) {
            cardLm.classList.remove('hidden');

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
                                        <span class="font-bold text-slate-800 text-[11px] truncate">${escapeHtml(ev.person_name)}</span>
                                        <span class="text-[9px] text-slate-400 font-mono flex-shrink-0">(${escapeHtml(ev.classroom_name)})</span>
                                    </div>
                                    <span class="px-1.5 py-0.2 rounded text-[8px] font-bold border ${badgeColorClass} uppercase font-mono flex-shrink-0">${escapeHtml(ev.badge)}</span>
                                </div>
                                <div class="flex items-center gap-1.5 text-[9px] text-slate-500 mb-1">
                                    <span class="font-mono font-bold text-slate-700">⏱ ${escapeHtml(ev.time)}</span>
                                    <span>•</span>
                                    <span class="font-semibold text-slate-600">${escapeHtml(ev.title)}</span>
                                </div>
                                ${ev.description ? `<p class="text-[10px] text-slate-600 leading-snug mb-1">${escapeHtml(ev.description)}</p>` : ''}
                                ${ev.action_taken ? `<div class="text-[9px] text-emerald-700 font-medium">✓ ${escapeHtml(ev.action_taken)}</div>` : ''}
                            </div>
                        `;
                    }).join('');
                }
            }
        } else {
            cardLm.classList.add('hidden');
        }
    }

    function renderBkCard(container, bkData) {
        const cardBk = container.querySelector('#card-bk-section');
        if (!cardBk) return;

        if (bkData && bkData.can_access) {
            cardBk.classList.remove('hidden');
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
                                    <span class="font-bold text-slate-800">${escapeHtml(ref.student_name)} <span class="text-[10px] text-slate-400 font-normal">(${escapeHtml(ref.classroom_name)})</span></span>
                                    <span class="px-2 py-0.5 rounded text-[9px] font-bold ${statusBadgeClass} uppercase">${escapeHtml(ref.referral_status_label)}</span>
                                </div>
                                <div class="flex items-center gap-1.5 mb-1.5">
                                    <span class="px-1.5 py-0.2 rounded text-[8px] font-bold bg-indigo-100 text-indigo-800 uppercase">${escapeHtml(ref.category_label)}</span>
                                    <span class="text-[9px] text-slate-400 font-mono">${escapeHtml(ref.incident_date)}</span>
                                </div>
                                <p class="text-[11px] text-slate-600 mb-1 leading-tight">${escapeHtml(ref.description || 'Tidak ada deskripsi')}</p>
                                ${ref.action_taken ? `<p class="text-[10px] text-indigo-900 font-medium mb-1.5">🎯 Piket: ${escapeHtml(ref.action_taken)}</p>` : ''}
                                <div class="flex items-center justify-between pt-1.5 border-t border-slate-100 text-[10px]">
                                    <button type="button" onclick="SIAP_ROUTER.navigate('/tasks/bk')" class="text-indigo-600 font-bold hover:underline">Tinjau &raquo;</button>
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
            cardBk.classList.add('hidden');
        }
    }

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

        // 3. Live Monitoring Hari Ini & Kartu Layanan BK
        renderUnifiedLiveMonitoring(container, data.live_monitoring);
        renderBkCard(container, data.bk);

        // 4. Render Schedules with Filter
        applyKbmFilters(container, data);

        // 5. Swap Sessions (Tukar Jam)
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

        // 1.5 KARTU UTAMA: UNIFIED LIVE MONITORING (if present)
        renderUnifiedLiveMonitoring(container, data.live_monitoring);

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
        const isManagement = data.piket?.is_management;
        const canAccessPiket = data.piket?.can_access;
        const currentTasksUser = SIAP_ROUTER.getCurrentUser();
        const isAdminOrManagement = isManagement || canAccessPiket || currentTasksUser?.role === 'admin' || currentTasksUser?.is_superadmin;

        if (isAdminOrManagement || isPiketToday || weeklyDays.length > 0) {
            if (cardPiket) cardPiket.classList.remove('hidden');
            if (isPiketToday) {
                if (badgePiketToday) {
                    badgePiketToday.textContent = 'Aktif Hari Ini';
                    badgePiketToday.className = 'px-2 py-0.5 rounded-full text-[9px] font-bold bg-purple-600 text-white shadow-xs';
                }
                if (piketLocationSub) {
                    piketLocationSub.textContent = 'Bertugas Hari Ini di Meja Piket & Gerbang';
                }
            } else if (isAdminOrManagement) {
                if (badgePiketToday) {
                    badgePiketToday.textContent = 'Akses Manajemen';
                    badgePiketToday.className = 'px-2 py-0.5 rounded-full text-[9px] font-bold bg-purple-100 text-purple-800';
                }
                if (piketLocationSub) {
                    piketLocationSub.textContent = 'Akses Penuh Manajemen & Meja Piket';
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

        // 7. Kartu Tugas Layanan BK (if present)
        renderBkCard(container, data.bk);

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
                                <button type="button" onclick="SIAP_ROUTER.navigate('/tasks/permits')" class="px-2 py-0.5 rounded bg-blue-600 text-white font-bold hover:bg-blue-700">Tinjau</button>
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
                if (window.refreshKbm) {
                    window.refreshKbm();
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
                if (window.refreshKbm) {
                    window.refreshKbm();
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

        const lm = kbmDataCache?.live_monitoring || tasksDataCache?.live_monitoring;
        if (!lm) return;

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
        const roleBadgeEl = container.querySelector('#prof-role-badge');
        const gtkStatusEl = container.querySelector('#prof-gtk-status');
        const nipEl = container.querySelector('#prof-nip');
        const nigsEl = container.querySelector('#prof-nigs');
        const nuptkEl = container.querySelector('#prof-nuptk');
        const birthEl = container.querySelector('#prof-birth');
        const emailEl = container.querySelector('#prof-email');
        const phoneWrap = container.querySelector('#prof-phone-wrap');
        const avatarEl = container.querySelector('#prof-avatar');

        if (nameEl) nameEl.textContent = u.name || '-';
        if (roleBadgeEl) roleBadgeEl.textContent = u.role_name || u.position || 'Guru';
        if (gtkStatusEl) gtkStatusEl.textContent = u.gtk_status || 'GTY / Tetap';
        if (nipEl) nipEl.textContent = u.nip || '-';
        if (nigsEl) nigsEl.textContent = u.nigs || '-';
        if (nuptkEl) nuptkEl.textContent = u.nuptk || '-';
        if (birthEl) {
            birthEl.textContent = u.birth_info || '-';
            birthEl.title = u.birth_info || '-';
        }
        if (emailEl) emailEl.textContent = u.email || '-';
        if (avatarEl && u.avatar_url) avatarEl.src = u.avatar_url;

        // WhatsApp Phone with direct link
        if (phoneWrap) {
            if (u.phone) {
                const waUrl = getWhatsappUrl(u.phone);
                if (waUrl) {
                    phoneWrap.innerHTML = `<a href="${waUrl}" target="_blank" class="text-xs font-bold text-emerald-700 font-mono hover:underline inline-flex items-center gap-1" title="Chat WhatsApp"><span>💬</span><span>${escapeHtml(u.phone)}</span></a>`;
                } else {
                    phoneWrap.innerHTML = `<p class="text-xs font-bold text-slate-800 font-mono">${escapeHtml(u.phone)}</p>`;
                }
            } else {
                phoneWrap.innerHTML = '<p class="text-xs font-bold text-slate-400 font-mono">-</p>';
            }
        }

        // Telegram Bot Status & ID Chat
        const tgBox = container.querySelector('#prof-telegram-box');
        const tgBadge = container.querySelector('#prof-telegram-status-badge');
        const tgId = container.querySelector('#prof-telegram-id');
        const tgAction = container.querySelector('#prof-telegram-action');

        if (tgBox && tgBadge && tgId && tgAction) {
            if (u.is_telegram_connected) {
                tgBox.className = 'mt-2 p-2 rounded-xl border border-emerald-100 bg-emerald-50/30 flex items-center justify-between gap-2';
                tgBadge.textContent = 'Terhubung';
                tgBadge.className = 'px-1.5 py-0.2 rounded-full text-[8px] font-bold bg-emerald-100 text-emerald-800';
                const unameStr = u.telegram_username ? ` <span class="text-slate-500 font-normal">(@${escapeHtml(u.telegram_username)})</span>` : '';
                tgId.innerHTML = `ID: <span class="text-emerald-800 font-mono">${escapeHtml(u.telegram_chat_id)}</span>${unameStr}`;
                tgAction.innerHTML = '<span class="text-[10px] font-bold text-emerald-700 flex items-center gap-0.5"><span>✅</span><span>Aktif</span></span>';
            } else {
                tgBox.className = 'mt-2 p-2 rounded-xl border border-amber-100 bg-amber-50/30 flex items-center justify-between gap-2';
                tgBadge.textContent = 'Belum Terhubung';
                tgBadge.className = 'px-1.5 py-0.2 rounded-full text-[8px] font-bold bg-amber-100 text-amber-800';
                tgId.textContent = 'Notifikasi Telegram belum aktif';
                if (u.telegram_connect_url) {
                    tgAction.innerHTML = `<a href="${u.telegram_connect_url}" target="_blank" class="px-2.5 py-1 rounded-lg bg-sky-600 hover:bg-sky-700 text-white font-bold text-[10px] active:scale-95 transition flex items-center gap-1 shadow-2xs"><span>Hubungkan</span><span>↗</span></a>`;
                } else {
                    tgAction.innerHTML = '<span class="text-[9px] text-slate-400 font-medium">Belum Diatur</span>';
                }
            }
        }

        // Seluruh Penugasan Aktif (All Assignments)
        const assignmentsContainer = container.querySelector('#prof-assignments-container');
        const assignmentsCountBadge = container.querySelector('#prof-assignments-count');

        if (assignmentsContainer) {
            const assignmentCards = [];

            // 1. Wali Kelas
            if (data.homeroom_classrooms && data.homeroom_classrooms.length > 0) {
                data.homeroom_classrooms.forEach(c => {
                    assignmentCards.push(`
                        <div class="p-2 rounded-xl bg-blue-50/70 border border-blue-100 flex items-center gap-2">
                            <span class="text-sm shrink-0">🏫</span>
                            <div class="min-w-0">
                                <div class="text-[11px] font-bold text-blue-950 truncate">Wali Kelas ${escapeHtml(c.name)}</div>
                                <div class="text-[9px] text-blue-700 font-medium">Perwalian Siswa Rombel TP ${escapeHtml(c.academic_year || '-')}</div>
                            </div>
                        </div>
                    `);
                });
            }

            // 2. Mata Pelajaran Diampu
            if (data.subjects && data.subjects.length > 0) {
                const subStr = data.subjects.join(', ');
                assignmentCards.push(`
                    <div class="p-2 rounded-xl bg-purple-50/70 border border-purple-100 flex items-center gap-2">
                        <span class="text-sm shrink-0">📚</span>
                        <div class="min-w-0">
                            <div class="text-[11px] font-bold text-purple-950 truncate">Guru Mata Pelajaran</div>
                            <div class="text-[9px] text-purple-700 font-medium leading-snug line-clamp-2">${escapeHtml(subStr)}</div>
                        </div>
                    </div>
                `);
            }

            // 3. Tugas Tambahan / Struktural
            if (data.structural_positions && data.structural_positions.length > 0) {
                data.structural_positions.forEach(s => {
                    assignmentCards.push(`
                        <div class="p-2 rounded-xl bg-amber-50/70 border border-amber-100 flex items-center gap-2">
                            <span class="text-sm shrink-0">💼</span>
                            <div class="min-w-0">
                                <div class="text-[11px] font-bold text-amber-950 truncate">${escapeHtml(s.position_name)}</div>
                                <div class="text-[9px] text-amber-700 font-medium">Tugas Tambahan Struktural Resmi</div>
                            </div>
                        </div>
                    `);
                });
            }

            // 4. Jadwal Piket Rutin
            if (data.piket_schedules && data.piket_schedules.length > 0) {
                data.piket_schedules.forEach(p => {
                    assignmentCards.push(`
                        <div class="p-2 rounded-xl bg-emerald-50/70 border border-emerald-100 flex items-center gap-2">
                            <span class="text-sm shrink-0">🛡️</span>
                            <div class="min-w-0">
                                <div class="text-[11px] font-bold text-emerald-950 truncate">Petugas Piket ${escapeHtml(p.day)}</div>
                                <div class="text-[9px] text-emerald-700 font-medium">${escapeHtml(p.duty_location)} • Peran: ${escapeHtml(p.role)}</div>
                            </div>
                        </div>
                    `);
                });
            }

            // 5. Penugasan Khusus (Supervisor & BK)
            if (data.special_assignments && data.special_assignments.length > 0) {
                data.special_assignments.forEach(sp => {
                    assignmentCards.push(`
                        <div class="p-2 rounded-xl bg-indigo-50/70 border border-indigo-100 flex items-center gap-2">
                            <span class="text-sm shrink-0">⭐</span>
                            <div class="min-w-0">
                                <div class="text-[11px] font-bold text-indigo-950 truncate">${escapeHtml(sp.label)}</div>
                                <div class="text-[9px] text-indigo-700 font-medium">Penugasan Fungsional Khusus</div>
                            </div>
                        </div>
                    `);
                });
            }

            if (assignmentCards.length === 0) {
                assignmentsContainer.innerHTML = '<div class="text-center py-2 text-[10px] text-slate-400">Tidak ada penugasan tambahan aktif saat ini.</div>';
            } else {
                assignmentsContainer.innerHTML = assignmentCards.join('');
            }

            if (assignmentsCountBadge) {
                assignmentsCountBadge.textContent = `${assignmentCards.length} Tugas`;
            }
        }

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

        // Render Riwayat Log Aktivitas Akun
        if (data.activity_logs) {
            renderProfileActivityLogs(data.activity_logs, container);
        }
    }

    let currentActivityLogs = [];
    let currentActivityFilter = 'all';
    let currentActivityDate = null;

    function renderProfileActivityLogs(logs, container = null) {
        const root = container || document;
        const listEl = root.querySelector('#prof-activity-logs-list');
        const countInfoEl = root.querySelector('#act-logs-count-info');
        if (!listEl) return;

        currentActivityLogs = logs || [];

        const filtered = currentActivityFilter === 'all'
            ? currentActivityLogs
            : currentActivityLogs.filter(item => item.category === currentActivityFilter);

        if (countInfoEl) {
            countInfoEl.textContent = `Menampilkan ${filtered.length} catatan aktivitas`;
        }

        if (filtered.length === 0) {
            const dateNotice = currentActivityDate ? ` pada tanggal ${currentActivityDate}` : '';
            listEl.innerHTML = `
                <div class="p-6 text-center text-slate-400 text-xs bg-slate-50 rounded-xl border border-dashed border-slate-200">
                    <span class="text-xl block mb-1">📋</span>
                    Tidak ada catatan aktivitas${dateNotice} untuk filter ini.
                </div>
            `;
            return;
        }

        listEl.innerHTML = filtered.map(item => {
            const badgeBg = {
                emerald: 'bg-emerald-100 text-emerald-800 border-emerald-200',
                blue: 'bg-blue-100 text-blue-800 border-blue-200',
                indigo: 'bg-indigo-100 text-indigo-800 border-indigo-200',
                amber: 'bg-amber-100 text-amber-800 border-amber-200',
                purple: 'bg-purple-100 text-purple-800 border-purple-200',
                cyan: 'bg-cyan-100 text-cyan-800 border-cyan-200',
                teal: 'bg-teal-100 text-teal-800 border-teal-200',
                rose: 'bg-rose-100 text-rose-800 border-rose-200',
                slate: 'bg-slate-100 text-slate-800 border-slate-200',
            }[item.badge_color] || 'bg-slate-100 text-slate-800 border-slate-200';

            const channelBadge = item.channel === 'Mobile'
                ? '<span class="text-[9px] font-mono px-1.5 py-0.2 rounded bg-blue-50 border border-blue-200 text-blue-600 shrink-0 font-bold">📱 Mobile</span>'
                : '<span class="text-[9px] font-mono px-1.5 py-0.2 rounded bg-slate-100 border border-slate-200 text-slate-600 shrink-0 font-bold">💻 Web</span>';

            return `
                <div class="p-2.5 rounded-xl border border-slate-100 bg-slate-50/70 hover:bg-slate-50 transition">
                    <div class="flex items-center justify-between mb-1 gap-1">
                        <div class="flex items-center gap-1.5 min-w-0">
                            <span class="px-1.5 py-0.2 rounded text-[8px] font-bold uppercase tracking-wider border ${badgeBg} shrink-0">
                                ${escapeHtml(item.category_label || item.category)}
                            </span>
                            <h5 class="text-xs font-bold text-slate-800 truncate">${escapeHtml(item.title)}</h5>
                        </div>
                        ${channelBadge}
                    </div>
                    <p class="text-[11px] text-slate-600 leading-snug my-0.5">${escapeHtml(item.description)}</p>
                    <div class="flex items-center justify-between mt-1 pt-1 border-t border-slate-200/50 text-[9px] text-slate-400 font-mono">
                        <span>📅 ${escapeHtml(item.date_formatted)}</span>
                        <span>${escapeHtml(item.time_human)}</span>
                    </div>
                </div>
            `;
        }).join('');
    }

    function updateDateFilterButtons(activeMode) {
        const btnToday = document.getElementById('btn-act-date-today');
        const btnAll = document.getElementById('btn-act-date-all');
        if (btnToday) {
            btnToday.className = activeMode === 'today'
                ? 'px-2.5 py-1 rounded-lg bg-blue-600 text-white text-[10px] font-bold shadow-xs active:scale-95 transition shrink-0'
                : 'px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-slate-600 text-[10px] font-bold active:scale-95 transition shrink-0 hover:bg-slate-100';
        }
        if (btnAll) {
            btnAll.className = activeMode === 'all'
                ? 'px-2.5 py-1 rounded-lg bg-blue-600 text-white text-[10px] font-bold shadow-xs active:scale-95 transition shrink-0'
                : 'px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-slate-600 text-[10px] font-bold active:scale-95 transition shrink-0 hover:bg-slate-100';
        }
    }

    async function loadActivityLogsByDate(dateStr) {
        const listEl = document.querySelector('#prof-activity-logs-list');
        const countInfoEl = document.querySelector('#act-logs-count-info');
        if (listEl) {
            listEl.innerHTML = '<div class="text-center py-6 text-xs text-slate-400 animate-pulse">Memuat log aktivitas...</div>';
        }
        if (countInfoEl) {
            countInfoEl.textContent = 'Memuat aktivitas...';
        }

        try {
            const res = await SIAP_API.getActivityLogs(dateStr, 50);
            if (res.success && res.data) {
                renderProfileActivityLogs(res.data);
            }
        } catch (err) {
            if (listEl) {
                listEl.innerHTML = `<div class="p-4 text-center text-xs text-rose-500">Gagal memuat aktivitas: ${escapeHtml(err.message)}</div>`;
            }
        }
    }

    window.setActivityDateFilter = function(mode) {
        if (mode === 'today') {
            const todayStr = new Date().toISOString().split('T')[0];
            currentActivityDate = todayStr;
            const dateInput = document.getElementById('act-date-input');
            if (dateInput) dateInput.value = todayStr;
            updateDateFilterButtons('today');
            loadActivityLogsByDate(todayStr);
        } else {
            currentActivityDate = null;
            const dateInput = document.getElementById('act-date-input');
            if (dateInput) dateInput.value = '';
            updateDateFilterButtons('all');
            loadActivityLogsByDate(null);
        }
    };

    window.handleActivityDateChange = function(val) {
        const todayStr = new Date().toISOString().split('T')[0];
        if (val === todayStr) {
            updateDateFilterButtons('today');
        } else if (!val) {
            updateDateFilterButtons('all');
        } else {
            updateDateFilterButtons('custom');
        }
        currentActivityDate = val || null;
        loadActivityLogsByDate(currentActivityDate);
    };

    window.filterActivityLogs = function(category) {
        currentActivityFilter = category;

        // Perbarui visual tombol chip filter
        ['all', 'presensi', 'kbm', 'tugas', 'verifikasi', 'akun'].forEach(cat => {
            const btn = document.getElementById(`btn-act-filter-${cat}`);
            if (btn) {
                if (cat === category) {
                    btn.className = 'px-2.5 py-1 rounded-lg bg-slate-900 text-white shrink-0 shadow-xs transition';
                } else {
                    btn.className = 'px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-slate-600 shrink-0 transition hover:bg-slate-50';
                }
            }
        });

        renderProfileActivityLogs(currentActivityLogs);
    };

    window.refreshProfileActivityLogs = async function() {
        showToast('Memuat ulang riwayat aktivitas...', 'info');
        await loadActivityLogsByDate(currentActivityDate);
    };

    // ==========================================
    // 6. SUB-PAGE CONTROLLERS: TASKS MODULES
    // ==========================================

    // --- A. IZIN & CUTI GTK CONTROLLER ---
    let currentPermitsContainer = null;
    let permitsDataCache = null;

    window.initTasksPermits = function(container, params = {}) {
        currentPermitsContainer = container;
        const startDateInput = container.querySelector('#permit-start-date');
        if (startDateInput) {
            startDateInput.value = new Date().toISOString().split('T')[0];
        }

        const hashQuery = (window.location.hash || '').split('?')[1] || '';
        const searchParams = new URLSearchParams(hashQuery);
        const tab = params?.tab || searchParams.get('tab');
        if (tab === 'history') {
            switchPermitsTab('history');
        } else if (tab === 'approval') {
            switchPermitsTab('approval');
        } else {
            switchPermitsTab('form');
        }

        loadTasksPermits();
    };

    window.switchPermitsTab = function(tab) {
        if (!currentPermitsContainer) return;
        const panelForm = currentPermitsContainer.querySelector('#panel-permits-form');
        const panelHistory = currentPermitsContainer.querySelector('#panel-permits-history');
        const panelApproval = currentPermitsContainer.querySelector('#panel-permits-approval');

        const btnForm = currentPermitsContainer.querySelector('#tab-permits-form-btn');
        const btnHistory = currentPermitsContainer.querySelector('#tab-permits-history-btn');
        const btnApproval = currentPermitsContainer.querySelector('#tab-permits-approval-btn');

        if (panelForm) panelForm.classList.add('hidden');
        if (panelHistory) panelHistory.classList.add('hidden');
        if (panelApproval) panelApproval.classList.add('hidden');

        [btnForm, btnHistory, btnApproval].forEach(btn => {
            if (btn) {
                btn.className = 'flex-1 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 transition flex items-center justify-center gap-1';
            }
        });

        if (tab === 'form') {
            if (panelForm) panelForm.classList.remove('hidden');
            if (btnForm) btnForm.className = 'flex-1 py-1.5 rounded-lg bg-white shadow-xs text-blue-600 transition flex items-center justify-center gap-1';
        } else if (tab === 'history') {
            if (panelHistory) panelHistory.classList.remove('hidden');
            if (btnHistory) btnHistory.className = 'flex-1 py-1.5 rounded-lg bg-white shadow-xs text-blue-600 transition flex items-center justify-center gap-1';
        } else if (tab === 'approval') {
            if (panelApproval) panelApproval.classList.remove('hidden');
            if (btnApproval) btnApproval.className = 'flex-1 py-1.5 rounded-lg bg-white shadow-xs text-blue-600 transition flex items-center justify-center gap-1';
        }
    };

    window.loadTasksPermits = async function() {
        if (!currentPermitsContainer) return;
        try {
            const res = await SIAP_API.getPermits();
            if (res.success && res.data) {
                permitsDataCache = res.data;
                renderPermitsUI(res.data);
            }
        } catch (err) {
            showToast('Gagal memuat data izin: ' + err.message, 'error');
        }
    };

    function renderPermitsUI(data) {
        if (!currentPermitsContainer) return;
        const listHistory = currentPermitsContainer.querySelector('#list-my-permits-container');
        const listApproval = currentPermitsContainer.querySelector('#list-pending-approvals-container');
        const btnApproval = currentPermitsContainer.querySelector('#tab-permits-approval-btn');
        const badgePending = currentPermitsContainer.querySelector('#permits-pending-badge');

        if (data.can_manage && btnApproval) {
            btnApproval.classList.remove('hidden');
            const pendingCount = (data.pending_approvals || []).length;
            if (badgePending) badgePending.textContent = pendingCount;
        }

        // Render Riwayat Saya
        if (listHistory) {
            const myPermits = data.my_permits || [];
            if (myPermits.length === 0) {
                listHistory.innerHTML = '<div class="text-center py-8 text-xs text-slate-400">Belum ada riwayat pengajuan izin.</div>';
            } else {
                listHistory.innerHTML = myPermits.map(p => {
                    const isApproved = p.status === 'approved';
                    const isRejected = p.status === 'rejected';
                    const badgeClass = isApproved ? 'bg-emerald-100 text-emerald-800' : (isRejected ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800');
                    const statusLabel = isApproved ? 'Disetujui' : (isRejected ? 'Ditolak' : 'Menunggu');

                    return `
                        <div class="card-elevated p-3 border-slate-200">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase bg-blue-100 text-blue-800">${p.permit_type} (${p.duration_days} Hari)</span>
                                <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase ${badgeClass}">${statusLabel}</span>
                            </div>
                            <p class="text-xs font-bold text-slate-800 mb-1">${p.reason}</p>
                            <div class="text-[10px] text-slate-400 font-mono mb-1">
                                📅 ${p.start_date} ${p.end_date && p.end_date !== p.start_date ? 's/d ' + p.end_date : ''}
                            </div>
                            ${p.approval_note ? `<p class="text-[10px] text-slate-500 bg-slate-50 p-1.5 rounded-lg mt-1 border border-slate-100 italic">Catatan KS: "${p.approval_note}"</p>` : ''}
                            ${p.assignment_document_url ? `
                                <div class="mt-2 p-2 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <span class="text-sm">📎</span>
                                        <div class="min-w-0">
                                            <div class="text-[11px] font-bold text-indigo-950 truncate">${escapeHtml(p.assignment_document_name || 'Lampiran Tugas KBM')}</div>
                                            <div class="text-[9px] text-indigo-600 font-medium">Materi untuk Guru Inval</div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1 shrink-0">
                                        <a href="${p.assignment_document_url}" target="_blank" class="px-2 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-[10px] active:scale-95 transition">
                                            Unduh
                                        </a>
                                        <button type="button" onclick="sharePermitTaskWhatsApp('Saya', '${escapeHtml(p.permit_type)}', '${p.assignment_document_url}', '${escapeHtml(p.reason)}')" class="px-2 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[10px] active:scale-95 transition flex items-center gap-0.5">
                                            <span>📲</span><span>WA</span>
                                        </button>
                                    </div>
                                </div>
                            ` : ''}
                        </div>
                    `;
                }).join('');
            }
        }

        // Render Persetujuan KS
        if (listApproval && data.can_manage) {
            const pending = data.pending_approvals || [];
            if (pending.length === 0) {
                listApproval.innerHTML = '<div class="text-center py-8 text-xs text-slate-400">Tidak ada pengajuan yang menunggu persetujuan.</div>';
            } else {
                listApproval.innerHTML = pending.map(p => `
                    <div class="card-elevated p-3 border-amber-200 bg-amber-50/20">
                        <div class="flex items-center justify-between mb-1">
                            <div>
                                <h4 class="text-xs font-black text-slate-900">${p.user_name}</h4>
                                <span class="text-[9px] text-slate-500">${p.user_position}</span>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-amber-100 text-amber-800 uppercase font-mono">${p.permit_type}</span>
                        </div>
                        <p class="text-xs text-slate-700 my-1 font-medium italic">"${p.reason}"</p>
                        <div class="text-[10px] text-slate-400 font-mono mb-2">
                            ${p.start_date} ${p.end_date && p.end_date !== p.start_date ? 's/d ' + p.end_date : ''} (${p.duration_days} Hari)
                        </div>
                        ${p.assignment_document_url ? `
                            <div class="my-2 p-2 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-between gap-2">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <span class="text-sm">📎</span>
                                    <div class="min-w-0">
                                        <div class="text-[11px] font-bold text-indigo-950 truncate">${escapeHtml(p.assignment_document_name || 'Lampiran Tugas KBM')}</div>
                                        <div class="text-[9px] text-indigo-600 font-medium">Materi untuk Guru Inval</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1 shrink-0">
                                    <a href="${p.assignment_document_url}" target="_blank" class="px-2 py-0.5 rounded-lg bg-indigo-600 text-white font-bold text-[10px]">Unduh</a>
                                    <button type="button" onclick="sharePermitTaskWhatsApp('${escapeHtml(p.user_name)}', '${escapeHtml(p.permit_type)}', '${p.assignment_document_url}', '${escapeHtml(p.reason)}')" class="px-2 py-0.5 rounded-lg bg-emerald-600 text-white font-bold text-[10px] flex items-center gap-0.5">
                                        <span>📲</span><span>WA</span>
                                    </button>
                                </div>
                            </div>
                        ` : ''}
                        <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-200/60">
                            <button type="button" onclick="handlePermitActionPrompt(${p.id}, 'reject')" class="py-1.5 px-3 rounded-lg bg-slate-100 hover:bg-rose-50 text-rose-700 text-xs font-bold active:scale-95 transition">
                                ✕ Tolak
                            </button>
                            <button type="button" onclick="handlePermitActionPrompt(${p.id}, 'approve')" class="py-1.5 px-3 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-xs active:scale-95 transition">
                                ✓ Setujui
                            </button>
                        </div>
                    </div>
                `).join('');
            }
        }
    }

    window.handlePermitSubmit = async function(e) {
        e.preventDefault();
        const form = e.target;
        const btn = form.querySelector('#btn-submit-permit');
        btn.disabled = true;
        btn.textContent = 'Mengirimkan...';

        try {
            const formData = new FormData(form);
            const res = await SIAP_API.submitPermit(formData);
            if (res.success) {
                showToast(res.message || 'Pengajuan izin berhasil dikirimkan!', 'success');
                form.reset();
                await loadTasksPermits();
                switchPermitsTab('history');
            } else {
                showToast(res.message || 'Gagal mengirim pengajuan.', 'error');
            }
        } catch (err) {
            showToast('Gagal mengajukan izin: ' + err.message, 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Kirim Pengajuan Izin';
        }
    };

    window.handlePermitActionPrompt = async function(id, action) {
        let note = null;
        if (action === 'reject') {
            note = prompt('Masukkan alasan penolakan izin:');
            if (note === null) return; // user batal
            if (!note.trim()) {
                showToast('Alasan penolakan wajib diisi.', 'warning');
                return;
            }
        }
        try {
            showToast('Memproses keputusan...', 'info');
            const res = await SIAP_API.actionPermit(id, action, note);
            if (res.success) {
                showToast(res.message || 'Keputusan berhasil disimpan.', 'success');
                loadTasksPermits();
            }
        } catch (err) {
            showToast('Gagal memproses izin: ' + err.message, 'error');
        }
    };

    window.sharePermitTaskWhatsApp = function(teacherName, permitType, fileUrl, notes) {
        const fullUrl = fileUrl ? (fileUrl.startsWith('http') ? fileUrl : (window.location.origin + fileUrl)) : '';
        const text = `*INFO TUGAS KELAS KOSONG (GTK IZIN)*\n\n` +
            `*Guru Pengampu:* ${teacherName}\n` +
            `*Keterangan:* Izin ${permitType}\n` +
            (notes ? `*Alasan/Instruksi:* ${notes}\n\n` : '\n') +
            (fullUrl ? `*Link Lampiran Tugas:*\n${fullUrl}\n\n` : '') +
            `_Diteruskan dari Meja Piket SIAP Mobile_`;

        if (navigator.share) {
            navigator.share({
                title: `Tugas Kelas Kosong - ${teacherName}`,
                text: text,
                url: fullUrl || window.location.href,
            }).catch(() => {
                window.open(`https://api.whatsapp.com/send?text=${encodeURIComponent(text)}`, '_blank');
            });
        } else {
            window.open(`https://api.whatsapp.com/send?text=${encodeURIComponent(text)}`, '_blank');
        }
    };

    window.shareInvalTaskWhatsApp = function(absentTeacher, classroom, subject, periodLabel, notes, fileUrl) {
        const fullUrl = fileUrl ? (fileUrl.startsWith('http') ? fileUrl : (window.location.origin + fileUrl)) : '';
        const text = `*PENUGASAN KELAS KOSONG (GURU INVAL)*\n\n` +
            `*Kelas:* ${classroom}\n` +
            `*Mata Pelajaran:* ${subject}\n` +
            `*Waktu:* ${periodLabel}\n` +
            `*Guru Pengampu:* ${absentTeacher}\n` +
            (notes ? `*Instruksi Tugas:*\n"${notes}"\n\n` : '\n') +
            (fullUrl ? `*Link File Tugas:*\n${fullUrl}\n\n` : '') +
            `_Pesan resmi Petugas Meja Piket SIAP Mobile_`;

        if (navigator.share) {
            navigator.share({
                title: `Tugas Inval ${classroom} - ${subject}`,
                text: text,
                url: fullUrl || window.location.href,
            }).catch(() => {
                window.open(`https://api.whatsapp.com/send?text=${encodeURIComponent(text)}`, '_blank');
            });
        } else {
            window.open(`https://api.whatsapp.com/send?text=${encodeURIComponent(text)}`, '_blank');
        }
    };

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    /**
     * Smart Autocomplete Student Picker Component
     * Model pencarian pintar ketika diketik >= 3 karakter otomatis menampilkan referensi siswa yang cocok.
     */
    function setupStudentSmartPicker(pickerElement, options = {}) {
        if (!pickerElement) return null;

        const isMultiple = Boolean(options.multiple);
        let selectedStudents = [];

        const hiddenInput = pickerElement.querySelector('input[type="hidden"]');
        const selectedCard = pickerElement.querySelector('.selected-student-card');
        const selectedName = pickerElement.querySelector('.student-selected-name');
        const selectedMeta = pickerElement.querySelector('.student-selected-meta');
        const selectedInitial = pickerElement.querySelector('.student-avatar-initial');
        const btnClearSelection = pickerElement.querySelector('.btn-clear-selection');

        const chipsContainer = options.chipsContainer || pickerElement.querySelector('.selected-students-list');
        const countBadge = options.countBadge || pickerElement.querySelector('#inc-selected-count-badge');

        const searchWrapper = pickerElement.querySelector('.search-input-wrapper');
        const searchInput = pickerElement.querySelector('.student-search-input');
        const btnClearInput = pickerElement.querySelector('.btn-clear-input');
        const suggestionsPanel = pickerElement.querySelector('.student-suggestions-panel');

        let debounceTimer = null;
        let lastQuery = '';

        function renderChips() {
            if (!chipsContainer) return;
            if (selectedStudents.length === 0) {
                chipsContainer.innerHTML = '';
                chipsContainer.classList.add('hidden');
                if (countBadge) countBadge.classList.add('hidden');
                return;
            }

            chipsContainer.classList.remove('hidden');
            if (countBadge) {
                countBadge.textContent = `${selectedStudents.length} siswa terpilih`;
                countBadge.classList.remove('hidden');
            }

            chipsContainer.innerHTML = selectedStudents.map(s => `
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-rose-50 border border-rose-200 text-xs font-semibold text-rose-800 shadow-2xs">
                    <span class="w-4 h-4 rounded-full bg-rose-600 text-white text-[9px] font-bold flex items-center justify-center shrink-0">
                        ${(s.name || 'S').trim().charAt(0).toUpperCase()}
                    </span>
                    <span class="truncate max-w-[130px]">${escapeHtml(s.name)}</span>
                    <span class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-white text-rose-700 border border-rose-200 shrink-0">
                        ${escapeHtml(s.classroom_name || '-')}
                    </span>
                    <button type="button" class="btn-remove-chip hover:bg-rose-200 text-rose-600 rounded-full w-4 h-4 flex items-center justify-center font-bold text-xs leading-none ml-0.5 cursor-pointer" data-id="${s.id}">&times;</button>
                </span>
            `).join('');

            chipsContainer.querySelectorAll('.btn-remove-chip').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const sid = parseInt(btn.getAttribute('data-id'), 10);
                    removeStudent(sid);
                });
            });
        }

        function removeStudent(studentId) {
            selectedStudents = selectedStudents.filter(s => s.id !== studentId);
            renderChips();
            updateHiddenValues();
            if (typeof options.onChange === 'function') {
                options.onChange(selectedStudents);
            }
        }

        function updateHiddenValues() {
            if (hiddenInput) {
                hiddenInput.value = isMultiple
                    ? selectedStudents.map(s => s.id).join(',')
                    : (selectedStudents[0]?.id || '');
                hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
            const hiddenName = pickerElement.querySelector('input[name="student_name"]');
            if (hiddenName) {
                hiddenName.value = selectedStudents.map(s => s.name).join(', ');
            }
            const hiddenClass = pickerElement.querySelector('input[name="classroom_id"]');
            if (hiddenClass) {
                hiddenClass.value = selectedStudents[0]?.classroom_id || '';
            }
        }

        function showSuggestions(html) {
            if (!suggestionsPanel) return;
            suggestionsPanel.innerHTML = html;
            suggestionsPanel.classList.remove('hidden');
        }

        function hideSuggestions() {
            if (!suggestionsPanel) return;
            suggestionsPanel.classList.add('hidden');
        }

        function selectStudent(student) {
            if (!student || !student.id) return;

            if (isMultiple) {
                if (!selectedStudents.some(s => s.id === student.id)) {
                    selectedStudents.push(student);
                    renderChips();
                    updateHiddenValues();
                }
                if (searchInput) {
                    searchInput.value = '';
                    searchInput.focus();
                }
                if (btnClearInput) btnClearInput.classList.add('hidden');
                hideSuggestions();

                if (typeof options.onSelect === 'function') {
                    options.onSelect(student, selectedStudents);
                }
                if (typeof options.onChange === 'function') {
                    options.onChange(selectedStudents);
                }
                return;
            }

            // Single student selection mode
            selectedStudents = [student];
            if (hiddenInput) hiddenInput.value = student.id;
            if (selectedName) selectedName.textContent = student.name;
            if (selectedMeta) {
                const parts = [];
                if (student.classroom_name) parts.push(student.classroom_name);
                if (student.nisn) parts.push(`NISN: ${student.nisn}`);
                else if (student.nis) parts.push(`NIS: ${student.nis}`);
                selectedMeta.textContent = parts.join(' • ') || 'Siswa Aktif';
            }
            if (selectedInitial) {
                selectedInitial.textContent = (student.name || 'S').trim().charAt(0).toUpperCase();
            }

            if (searchWrapper) searchWrapper.classList.add('hidden');
            if (selectedCard) selectedCard.classList.remove('hidden');
            hideSuggestions();

            if (hiddenInput) {
                hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
            }

            if (typeof options.onSelect === 'function') {
                options.onSelect(student, selectedStudents);
            }
        }

        function clearSelection() {
            selectedStudents = [];
            if (isMultiple) {
                renderChips();
                updateHiddenValues();
                if (searchInput) searchInput.value = '';
                if (btnClearInput) btnClearInput.classList.add('hidden');
                hideSuggestions();
                if (typeof options.onClear === 'function') options.onClear();
                if (typeof options.onChange === 'function') options.onChange([]);
                return;
            }

            if (hiddenInput) hiddenInput.value = '';
            if (selectedCard) selectedCard.classList.add('hidden');
            if (searchWrapper) searchWrapper.classList.remove('hidden');
            if (searchInput) {
                searchInput.value = '';
                searchInput.focus();
            }
            if (btnClearInput) btnClearInput.classList.add('hidden');
            hideSuggestions();

            if (hiddenInput) {
                hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
            }

            if (typeof options.onClear === 'function') {
                options.onClear();
            }
        }

        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                const query = e.target.value.trim();
                lastQuery = query;

                if (query.length > 0) {
                    if (btnClearInput) btnClearInput.classList.remove('hidden');
                } else {
                    if (btnClearInput) btnClearInput.classList.add('hidden');
                }

                clearTimeout(debounceTimer);

                if (query.length < 3) {
                    if (query.length > 0) {
                        showSuggestions(`
                            <div class="p-3 text-center text-[11px] text-slate-400">
                                💡 Ketik minimal 3 huruf nama atau NISN siswa...
                            </div>
                        `);
                    } else {
                        hideSuggestions();
                    }
                    return;
                }

                showSuggestions(`
                    <div class="p-3 text-center text-xs text-slate-400 animate-pulse flex items-center justify-center gap-1.5">
                        <svg class="w-3.5 h-3.5 animate-spin text-slate-400" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                        <span>Mencari referensi siswa...</span>
                    </div>
                `);

                debounceTimer = setTimeout(async () => {
                    try {
                        const res = await SIAP_API.searchStudents(query);
                        if (lastQuery !== query) return; // Mencegah race condition

                        const students = (res && res.data) ? res.data : [];
                        if (students.length === 0) {
                            showSuggestions(`
                                <div class="p-3 text-center text-xs text-slate-400">
                                    Tidak ada siswa ditemukan dengan kata kunci "<strong>${escapeHtml(query)}</strong>".
                                </div>
                            `);
                            return;
                        }

                        suggestionsPanel.innerHTML = students.map((s, idx) => {
                            const isAlreadySelected = isMultiple && selectedStudents.some(sel => sel.id === s.id);
                            return `
                                <div class="p-2.5 rounded-lg hover:bg-slate-50 active:bg-slate-100 cursor-pointer flex items-center justify-between transition group ${isAlreadySelected ? 'opacity-50 pointer-events-none bg-slate-50' : ''}" data-idx="${idx}">
                                    <div class="min-w-0 pr-2">
                                        <div class="text-xs font-bold text-slate-800 group-hover:text-cyan-700 truncate">
                                            ${escapeHtml(s.name)}
                                            ${isAlreadySelected ? '<span class="text-[9px] text-emerald-600 ml-1 font-bold">(Sudah dipilih)</span>' : ''}
                                        </div>
                                        <div class="text-[10px] text-slate-400">NISN: ${escapeHtml(s.nisn || '-')} &bull; NIS: ${escapeHtml(s.nis || '-')}</div>
                                    </div>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 shrink-0 border border-slate-200">
                                        ${escapeHtml(s.classroom_name || '-')}
                                    </span>
                                </div>
                            `;
                        }).join('');

                        suggestionsPanel.querySelectorAll('[data-idx]').forEach(item => {
                            item.addEventListener('click', () => {
                                const idx = parseInt(item.getAttribute('data-idx'), 10);
                                selectStudent(students[idx]);
                            });
                        });

                        suggestionsPanel.classList.remove('hidden');
                    } catch (err) {
                        showSuggestions(`
                            <div class="p-3 text-center text-xs text-rose-500">
                                Gagal mencari siswa: ${escapeHtml(err.message)}
                            </div>
                        `);
                    }
                }, 250);
            });
        }

        if (btnClearInput) {
            btnClearInput.addEventListener('click', () => {
                if (searchInput) {
                    searchInput.value = '';
                    searchInput.focus();
                }
                btnClearInput.classList.add('hidden');
                hideSuggestions();
            });
        }

        if (btnClearSelection) {
            btnClearSelection.addEventListener('click', () => {
                clearSelection();
            });
        }

        document.addEventListener('click', (e) => {
            if (!pickerElement.contains(e.target)) {
                hideSuggestions();
            }
        });

        return {
            selectStudent,
            removeStudent,
            clearSelection,
            reset: clearSelection,
            getValue: () => (isMultiple ? selectedStudents.map(s => s.id) : (hiddenInput ? hiddenInput.value : '')),
            getSelectedStudents: () => [...selectedStudents],
        };
    }

    // --- B. VISITASI SISWA CONTROLLER ---
    let currentVisitationsContainer = null;
    let visitationStudentPicker = null;

    window.initTasksVisitations = function(container, params = {}) {
        currentVisitationsContainer = container;
        const dateInput = container.querySelector('#visitation-planned-date');
        if (dateInput) {
            dateInput.value = new Date().toISOString().split('T')[0];
        }
        visitationStudentPicker = setupStudentSmartPicker(container.querySelector('#visitation-student-picker'));

        const hashQuery = (window.location.hash || '').split('?')[1] || '';
        const searchParams = new URLSearchParams(hashQuery);
        const tab = params?.tab || searchParams.get('tab');
        if (tab === 'form') {
            switchVisitationTab('form');
        } else {
            switchVisitationTab('list');
        }

        loadTasksVisitations();
    };

    window.switchVisitationTab = function(tab) {
        if (!currentVisitationsContainer) return;
        const panelList = currentVisitationsContainer.querySelector('#panel-visitation-list');
        const panelForm = currentVisitationsContainer.querySelector('#panel-visitation-form');
        const btnList = currentVisitationsContainer.querySelector('#tab-visitation-list-btn');
        const btnForm = currentVisitationsContainer.querySelector('#tab-visitation-form-btn');

        if (tab === 'list') {
            if (panelList) panelList.classList.remove('hidden');
            if (panelForm) panelForm.classList.add('hidden');
            if (btnList) btnList.className = 'flex-1 py-1.5 rounded-lg bg-white shadow-xs text-cyan-700 transition';
            if (btnForm) btnForm.className = 'flex-1 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 transition';
        } else {
            if (panelList) panelList.classList.add('hidden');
            if (panelForm) panelForm.classList.remove('hidden');
            if (btnList) btnList.className = 'flex-1 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 transition';
            if (btnForm) btnForm.className = 'flex-1 py-1.5 rounded-lg bg-white shadow-xs text-cyan-700 transition';
        }
    };

    window.loadTasksVisitations = async function() {
        if (!currentVisitationsContainer) return;
        try {
            const res = await SIAP_API.getVisitations();
            if (res.success && res.data) {
                renderVisitationsUI(res.data);
            }
        } catch (err) {
            showToast('Gagal memuat data visitasi: ' + err.message, 'error');
        }
    };

    function renderVisitationsUI(data) {
        if (!currentVisitationsContainer) return;
        const listContainer = currentVisitationsContainer.querySelector('#list-visitations-container');
        const primarySelect = currentVisitationsContainer.querySelector('#visitation-primary-officer');
        const secondarySelect = currentVisitationsContainer.querySelector('#visitation-secondary-officer');

        // Populate Petugas Dropdown
        if (primarySelect && data.officers) {
            primarySelect.innerHTML = '<option value="">-- Pilih Petugas Utama --</option>' +
                data.officers.map(o => `<option value="${o.id}" ${o.id === data.my_user_id ? 'selected' : ''}>${o.name} (${o.position || 'Petugas'})</option>`).join('');
        }
        if (secondarySelect && data.officers) {
            secondarySelect.innerHTML = '<option value="">-- Tidak Ada / Sendiri --</option>' +
                data.officers.map(o => `<option value="${o.id}">${o.name} (${o.position || 'Petugas'})</option>`).join('');
        }

        // Render List Visitasi
        if (listContainer) {
            const visitations = data.visitations || [];
            if (visitations.length === 0) {
                listContainer.innerHTML = '<div class="text-center py-8 text-xs text-slate-400">Belum ada agenda visitasi siswa.</div>';
            } else {
                listContainer.innerHTML = visitations.map(v => {
                    const isDone = v.status === 'terlaksana';
                    const badgeClass = isDone ? 'bg-emerald-100 text-emerald-800' : 'bg-cyan-100 text-cyan-800';
                    return `
                        <div class="card-elevated p-3 border-cyan-100">
                            <div class="flex items-center justify-between mb-1">
                                <h4 class="text-xs font-black text-slate-900">${escapeHtml(v.student_name)} <span class="text-[10px] text-slate-400 font-normal">(${escapeHtml(v.classroom_name)})</span></h4>
                                <span class="px-2 py-0.2 rounded text-[9px] font-bold uppercase ${badgeClass}">${escapeHtml(v.status)}</span>
                            </div>
                            <p class="text-xs text-slate-700 my-1 font-medium">🎯 ${escapeHtml(v.reason)}</p>
                            <div class="text-[10px] text-slate-500 font-mono flex items-center justify-between pt-1 border-t border-slate-100">
                                <span>📅 ${escapeHtml(v.planned_date)}</span>
                                <span>👤 ${escapeHtml(v.primary_officer)} ${v.secondary_officer ? '+ ' + escapeHtml(v.secondary_officer) : ''}</span>
                            </div>
                        </div>
                    `;
                }).join('');
            }
        }
    }

    window.handleVisitationSubmit = async function(e) {
        e.preventDefault();
        const form = e.target;
        const btn = form.querySelector('#btn-submit-visitation');
        const studentId = form.querySelector('#visitation-student-id')?.value;

        if (!studentId) {
            showToast('Silakan cari dan pilih siswa target terlebih dahulu.', 'warning');
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Menyimpan...';

        try {
            const payload = {
                student_id: studentId,
                planned_date: form.querySelector('#visitation-planned-date').value,
                visit_type: form.querySelector('#visitation-type-select').value,
                primary_officer_id: form.querySelector('#visitation-primary-officer').value,
                secondary_officer_id: form.querySelector('#visitation-secondary-officer').value || null,
                reason: form.querySelector('#visitation-reason').value,
            };

            const res = await SIAP_API.submitVisitation(payload);
            if (res.success) {
                showToast(res.message || 'Jadwal visitasi berhasil disimpan!', 'success');
                form.reset();
                if (visitationStudentPicker) visitationStudentPicker.reset();
                await loadTasksVisitations();
                switchVisitationTab('list');
            } else {
                showToast(res.message || 'Gagal menyimpan visitasi.', 'error');
            }
        } catch (err) {
            showToast('Gagal membuat visitasi: ' + err.message, 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Simpan Jadwal Visitasi';
        }
    };

    // --- C. TIM KEDISIPLINAN CONTROLLER ---
    let currentDisciplineContainer = null;
    let disciplineDataCache = null;
    let currentDisciplineFilter = 'all';

    window.initTasksDiscipline = function(container) {
        currentDisciplineContainer = container;
        loadTasksDiscipline();
    };

    window.loadTasksDiscipline = async function() {
        if (!currentDisciplineContainer) return;
        try {
            const res = await SIAP_API.getDiscipline();
            if (res.success && res.data) {
                disciplineDataCache = res.data;
                renderDisciplineUI();
            }
        } catch (err) {
            showToast('Gagal memuat data kedisiplinan: ' + err.message, 'error');
        }
    };

    window.filterDisciplineCategory = function(cat) {
        currentDisciplineFilter = cat;
        if (!currentDisciplineContainer) return;
        ['all', 'menunggu_verifikasi', 'terverifikasi', 'rujuk_bk'].forEach(c => {
            const btn = currentDisciplineContainer.querySelector('#filter-disc-' + c);
            if (btn) {
                if (c === cat) {
                    btn.className = 'px-2.5 py-1 rounded-lg bg-slate-900 text-white shrink-0 shadow-xs';
                } else {
                    btn.className = 'px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-slate-600 shrink-0';
                }
            }
        });
        renderDisciplineList();
    };

    function renderDisciplineUI() {
        if (!currentDisciplineContainer || !disciplineDataCache) return;
        const summary = disciplineDataCache.summary || {};
        const statTotal = currentDisciplineContainer.querySelector('#stat-disc-total');
        const statWaiting = currentDisciplineContainer.querySelector('#stat-disc-waiting');
        const statVerified = currentDisciplineContainer.querySelector('#stat-disc-verified');
        const statBk = currentDisciplineContainer.querySelector('#stat-disc-bk');

        if (statTotal) statTotal.textContent = summary.total || 0;
        if (statWaiting) statWaiting.textContent = summary.menunggu || 0;
        if (statVerified) statVerified.textContent = summary.terverifikasi || 0;
        if (statBk) statBk.textContent = summary.rujukan_bk || 0;

        renderDisciplineList();
    }

    function renderDisciplineList() {
        if (!currentDisciplineContainer || !disciplineDataCache) return;
        const listContainer = currentDisciplineContainer.querySelector('#list-discipline-container');
        if (!listContainer) return;

        let incidents = disciplineDataCache.incidents || [];
        if (currentDisciplineFilter === 'menunggu_verifikasi') {
            incidents = incidents.filter(i => i.verification_status === 'menunggu_verifikasi');
        } else if (currentDisciplineFilter === 'terverifikasi') {
            incidents = incidents.filter(i => i.verification_status === 'terverifikasi');
        } else if (currentDisciplineFilter === 'rujuk_bk') {
            incidents = incidents.filter(i => i.follow_up_target === 'rujuk_bk');
        }

        if (incidents.length === 0) {
            listContainer.innerHTML = '<div class="text-center py-8 text-xs text-slate-400">Tidak ada kasus pada filter ini.</div>';
            return;
        }

        const canVerify = disciplineDataCache.can_verify;

        listContainer.innerHTML = incidents.map(inc => {
            const isWaiting = inc.verification_status === 'menunggu_verifikasi';
            const statusClass = isWaiting ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800';

            return `
                <div class="card-elevated p-3 border-rose-100">
                    <div class="flex items-center justify-between mb-1">
                        <h4 class="text-xs font-black text-slate-900">${inc.student_name} <span class="text-[10px] text-slate-400 font-normal">(${inc.classroom_name})</span></h4>
                        <span class="px-2 py-0.2 rounded text-[9px] font-bold uppercase ${statusClass}">${inc.verification_status_label}</span>
                    </div>
                    <div class="flex items-center gap-1.5 mb-1.5">
                        <span class="px-1.5 py-0.2 rounded text-[8px] font-bold bg-rose-100 text-rose-800 uppercase">${inc.category_label}</span>
                        <span class="text-[9px] font-mono text-slate-400">${inc.incident_date} ${inc.incident_time}</span>
                        ${inc.penalty_points ? `<span class="text-[9px] font-bold text-rose-600 bg-rose-50 px-1 rounded">+${inc.penalty_points} Poin</span>` : ''}
                    </div>
                    <p class="text-xs text-slate-700 mb-1 leading-snug">${inc.description || 'Tidak ada deskripsi'}</p>
                    ${inc.action_taken ? `<p class="text-[10px] text-slate-500 italic mb-2">Tindakan: "${inc.action_taken}"</p>` : ''}
                    
                    ${canVerify && isWaiting ? `
                        <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100">
                            <button type="button" onclick="quickVerifyFromDiscipline(${inc.id}, 'bukan_pelanggaran')" class="py-1 px-2 rounded-lg bg-slate-100 text-slate-600 text-xs font-bold active:scale-95 transition">
                                Bukan Pelanggaran
                            </button>
                            <button type="button" onclick="quickVerifyFromDiscipline(${inc.id}, 'terverifikasi')" class="py-1 px-2 rounded-lg bg-emerald-600 text-white text-xs font-bold shadow-xs active:scale-95 transition">
                                ✓ Sahkan Pelanggaran
                            </button>
                        </div>
                    ` : ''}
                </div>
            `;
        }).join('');
    }

    window.quickVerifyFromDiscipline = async function(id, status) {
        try {
            showToast('Memverifikasi...', 'info');
            const res = await SIAP_API.verifyDisciplineIncident(id, status);
            if (res.success) {
                showToast(res.message || 'Berhasil diverifikasi!', 'success');
                loadTasksDiscipline();
            }
        } catch (err) {
            showToast('Gagal verifikasi: ' + err.message, 'error');
        }
    };

    // --- D. LAYANAN BK CONTROLLER ---
    let currentBkContainer = null;
    let bkStudentPicker = null;
    let bkDataCache = null;

    window.initTasksBk = function(container, params = {}) {
        currentBkContainer = container;
        const dateInput = container.querySelector('#bk-service-date');
        if (dateInput) {
            dateInput.value = new Date().toISOString().split('T')[0];
        }
        bkStudentPicker = setupStudentSmartPicker(container.querySelector('#bk-student-picker'));

        const hashQuery = (window.location.hash || '').split('?')[1] || '';
        const searchParams = new URLSearchParams(hashQuery);
        const tab = params?.tab || searchParams.get('tab');
        if (tab === 'form') {
            switchBkTab('form');
        } else if (tab === 'services') {
            switchBkTab('services');
        } else {
            switchBkTab('referrals');
        }

        loadTasksBk();
    };

    window.switchBkTab = function(tab) {
        if (!currentBkContainer) return;
        const panelReferrals = currentBkContainer.querySelector('#panel-bk-referrals');
        const panelForm = currentBkContainer.querySelector('#panel-bk-form');
        const panelServices = currentBkContainer.querySelector('#panel-bk-services');

        const btnReferrals = currentBkContainer.querySelector('#tab-bk-referrals-btn');
        const btnForm = currentBkContainer.querySelector('#tab-bk-form-btn');
        const btnServices = currentBkContainer.querySelector('#tab-bk-services-btn');

        [panelReferrals, panelForm, panelServices].forEach(p => p && p.classList.add('hidden'));
        [btnReferrals, btnForm, btnServices].forEach(b => {
            if (b) b.className = 'flex-1 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 transition';
        });

        if (tab === 'referrals') {
            if (panelReferrals) panelReferrals.classList.remove('hidden');
            if (btnReferrals) btnReferrals.className = 'flex-1 py-1.5 rounded-lg bg-white shadow-xs text-indigo-700 transition';
        } else if (tab === 'form') {
            if (panelForm) panelForm.classList.remove('hidden');
            if (btnForm) btnForm.className = 'flex-1 py-1.5 rounded-lg bg-white shadow-xs text-indigo-700 transition';
        } else if (tab === 'services') {
            if (panelServices) panelServices.classList.remove('hidden');
            if (btnServices) btnServices.className = 'flex-1 py-1.5 rounded-lg bg-white shadow-xs text-indigo-700 transition';
        }
    };

    window.loadTasksBk = async function() {
        if (!currentBkContainer) return;
        try {
            const res = await SIAP_API.getBk();
            if (res.success && res.data) {
                bkDataCache = res.data;
                renderBkUI(res.data);
            }
        } catch (err) {
            showToast('Gagal memuat data BK: ' + err.message, 'error');
        }
    };

    function renderBkUI(data) {
        if (!currentBkContainer) return;
        const stats = data.stats || {};
        const statTotal = currentBkContainer.querySelector('#stat-bk-total');
        const statWaiting = currentBkContainer.querySelector('#stat-bk-menunggu');
        const statProcessing = currentBkContainer.querySelector('#stat-bk-diproses');
        const statDone = currentBkContainer.querySelector('#stat-bk-selesai');

        if (statTotal) statTotal.textContent = stats.total_rujukan || 0;
        if (statWaiting) statWaiting.textContent = stats.menunggu || 0;
        if (statProcessing) statProcessing.textContent = stats.diproses || 0;
        if (statDone) statDone.textContent = stats.selesai || 0;

        // Render Rujukan Meja Piket
        const listRefContainer = currentBkContainer.querySelector('#list-bk-referrals-container');
        if (listRefContainer) {
            const referrals = data.referrals || [];
            if (referrals.length === 0) {
                listRefContainer.innerHTML = '<div class="text-center py-8 text-xs text-slate-400">Tidak ada rujukan meja piket saat ini.</div>';
            } else {
                listRefContainer.innerHTML = referrals.map(ref => {
                    const isMenunggu = ref.referral_status === 'menunggu';
                    const isDiproses = ref.referral_status === 'diproses';
                    const badgeClass = isMenunggu ? 'bg-amber-100 text-amber-800' : (isDiproses ? 'bg-blue-100 text-blue-800' : 'bg-emerald-100 text-emerald-800');

                    return `
                        <div class="card-elevated p-3 border-indigo-100">
                            <div class="flex items-center justify-between mb-1">
                                <h4 class="text-xs font-black text-slate-900">${escapeHtml(ref.student_name)} <span class="text-[10px] text-slate-400 font-normal">(${escapeHtml(ref.classroom_name)})</span></h4>
                                <span class="px-2 py-0.2 rounded text-[9px] font-bold uppercase ${badgeClass}">${escapeHtml(ref.referral_status_label)}</span>
                            </div>
                            <div class="flex items-center gap-1.5 mb-1.5">
                                <span class="px-1.5 py-0.2 rounded text-[8px] font-bold bg-indigo-100 text-indigo-800 uppercase">${escapeHtml(ref.category_label)}</span>
                                <span class="text-[9px] font-mono text-slate-400">${escapeHtml(ref.incident_date)}</span>
                            </div>
                            <p class="text-xs text-slate-700 mb-1 leading-snug">${escapeHtml(ref.description || 'Tidak ada deskripsi')}</p>
                            ${ref.action_taken ? `<p class="text-[10px] text-indigo-900 font-medium mb-2">🎯 Piket: ${escapeHtml(ref.action_taken)}</p>` : ''}
                            
                            <div class="flex items-center justify-end gap-1.5 pt-2 border-t border-slate-100 text-xs">
                                ${ref.student_id ? `
                                    <button type="button" onclick="fillBkReferralToForm(${ref.student_id}, '${escapeHtml(ref.student_name)}', '${escapeHtml(ref.classroom_name)}', '${escapeHtml(ref.student_nisn || '')}', '${escapeHtml(ref.description || '')}')" class="py-1 px-2.5 rounded-lg bg-indigo-50 border border-indigo-200 text-indigo-700 font-bold active:scale-95 transition text-[10px]">
                                        📝 Catat Konseling
                                    </button>
                                ` : ''}
                                ${isMenunggu ? `
                                    <button type="button" onclick="quickUpdateBkStatusFromPage(${ref.id}, 'diproses')" class="py-1 px-3 rounded-lg bg-indigo-600 text-white font-bold shadow-xs active:scale-95 transition">
                                        ⚡ Tangani Kasus
                                    </button>
                                ` : (isDiproses ? `
                                    <button type="button" onclick="quickUpdateBkStatusFromPage(${ref.id}, 'selesai')" class="py-1 px-3 rounded-lg bg-emerald-600 text-white font-bold shadow-xs active:scale-95 transition">
                                        ✓ Selesaikan
                                    </button>
                                ` : '')}
                            </div>
                        </div>
                    `;
                }).join('');
            }
        }

        // Render Riwayat Sesi Layanan
        const listServicesContainer = currentBkContainer.querySelector('#list-bk-services-container');
        if (listServicesContainer) {
            const services = data.services || [];
            if (services.length === 0) {
                listServicesContainer.innerHTML = '<div class="text-center py-8 text-xs text-slate-400">Belum ada riwayat konseling tercatat.</div>';
            } else {
                listServicesContainer.innerHTML = services.map(s => `
                    <div class="card-elevated p-3 border-indigo-100">
                        <div class="flex items-center justify-between mb-1">
                            <h4 class="text-xs font-black text-slate-900">${escapeHtml(s.student_name)} <span class="text-[10px] text-slate-400 font-normal">(${escapeHtml(s.classroom_name)})</span></h4>
                            <span class="px-2 py-0.2 rounded text-[9px] font-bold uppercase bg-indigo-100 text-indigo-800">${escapeHtml(s.service_type)}</span>
                        </div>
                        <p class="text-xs text-slate-800 font-medium my-1">💬 "${escapeHtml(s.summary)}"</p>
                        <p class="text-[11px] text-slate-600 bg-slate-50 p-2 rounded-lg border border-slate-100">Hasil: ${escapeHtml(s.result)}</p>
                        ${s.follow_up_plan ? `<p class="text-[10px] text-indigo-900 font-bold mt-1">RTL: ${escapeHtml(s.follow_up_plan)}</p>` : ''}
                        <div class="text-[10px] text-slate-400 font-mono mt-1 pt-1 border-t border-slate-100 flex justify-between">
                            <span>📅 ${escapeHtml(s.service_date)}</span>
                            <span>👤 ${escapeHtml(s.counselor_name)}</span>
                        </div>
                    </div>
                `).join('');
            }
        }
    }

    window.fillBkReferralToForm = function(studentId, studentName, classroomName, nisn, desc) {
        switchBkTab('form');
        if (bkStudentPicker) {
            bkStudentPicker.selectStudent({
                id: studentId,
                name: studentName,
                classroom_name: classroomName,
                nisn: nisn
            });
        }
        const summaryInput = currentBkContainer?.querySelector('#bk-summary');
        if (summaryInput && desc) {
            summaryInput.value = 'Rujukan Piket: ' + desc;
        }
        showToast(`Siswa ${studentName} dipilih. Silakan isi rencana konseling.`, 'info');
    };

    window.quickUpdateBkStatusFromPage = async function(id, status) {
        try {
            showToast('Memperbarui status...', 'info');
            const res = await SIAP_API.updateBkReferralStatus(id, status);
            if (res.success) {
                showToast(res.message || 'Status berhasil diperbarui!', 'success');
                loadTasksBk();
            }
        } catch (err) {
            showToast('Gagal update status: ' + err.message, 'error');
        }
    };

    window.handleBkServiceSubmit = async function(e) {
        e.preventDefault();
        const form = e.target;
        const btn = form.querySelector('#btn-submit-bk-service');
        const studentId = form.querySelector('#bk-student-id')?.value;

        if (!studentId) {
            showToast('Silakan cari dan pilih siswa terlebih dahulu.', 'warning');
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Menyimpan...';

        try {
            const payload = {
                student_id: studentId,
                service_date: form.querySelector('#bk-service-date').value,
                service_type: form.querySelector('#bk-service-type').value,
                summary: form.querySelector('#bk-summary').value,
                result: form.querySelector('#bk-result').value,
                follow_up_plan: form.querySelector('#bk-follow-up-plan').value || null,
            };

            const res = await SIAP_API.submitBkService(payload);
            if (res.success) {
                showToast(res.message || 'Sesi konseling berhasil disimpan!', 'success');
                form.reset();
                if (bkStudentPicker) bkStudentPicker.reset();
                await loadTasksBk();
                switchBkTab('services');
            } else {
                showToast(res.message || 'Gagal menyimpan konseling.', 'error');
            }
        } catch (err) {
            showToast('Gagal mencatat konseling: ' + err.message, 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Simpan Sesi Konseling';
        }
    };

    // ==========================================
    // 6.5. MEJA PIKET & TUKAR JADWAL PIKET
    // ==========================================
    let currentPiketContainer = null;
    let piketDataCache = null;
    let currentPiketTab = 'permits';
    let piketPermitPicker = null;
    let piketIncidentPicker = null;
    let piketIncidentPhotoBase64 = null;

    let currentPiketSwapContainer = null;
    let piketSwapDataCache = null;
    let currentSwapTab = 'history';

    window.initTasksPiket = function(container, params = {}) {
        currentPiketContainer = container;
        const now = new Date();
        const currentTimeStr = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;

        // Set default times in form inputs
        const entryTime = container.querySelector('#permit-entry-time');
        const depTime = container.querySelector('#permit-departure-time');
        const incTime = container.querySelector('#inc-time');
        if (entryTime) entryTime.value = currentTimeStr;
        if (depTime) depTime.value = currentTimeStr;
        if (incTime) incTime.value = currentTimeStr;

        // Initialize smart autocomplete pickers
        piketPermitPicker = setupStudentSmartPicker(container.querySelector('#permit-student-picker'), {
            onSelect: (student) => {
                const clsHidden = container.querySelector('#permit-classroom-id');
                if (clsHidden && student.classroom_id) clsHidden.value = student.classroom_id;
                const nameHidden = container.querySelector('#permit-student-name');
                if (nameHidden) nameHidden.value = student.name;
            },
            onClear: () => {
                const clsHidden = container.querySelector('#permit-classroom-id');
                if (clsHidden) clsHidden.value = '';
                const nameHidden = container.querySelector('#permit-student-name');
                if (nameHidden) nameHidden.value = '';
            }
        });

        piketIncidentPicker = setupStudentSmartPicker(container.querySelector('#incident-student-picker'), {
            multiple: true,
            chipsContainer: container.querySelector('#inc-selected-students-list'),
            countBadge: container.querySelector('#inc-selected-count-badge'),
            onSelect: (student, allSelected) => {
                const clsHidden = container.querySelector('#inc-classroom-id');
                if (clsHidden && student.classroom_id) clsHidden.value = student.classroom_id;
            },
            onChange: (allSelected) => {
                const clsHidden = container.querySelector('#inc-classroom-id');
                if (clsHidden) clsHidden.value = allSelected[0]?.classroom_id || '';
            }
        });

        // Check if action or tab parameter is passed
        const hashQuery = (window.location.hash || '').split('?')[1] || '';
        const searchParams = new URLSearchParams(hashQuery);
        const action = params?.action || searchParams.get('action');
        const tab = params?.tab || searchParams.get('tab');

        if (action && ['permits', 'incidents', 'invals', 'guests'].includes(action)) {
            openPiketAction(action);
        } else if (tab && ['permits', 'incidents', 'invals', 'guests'].includes(tab)) {
            switchPiketTab(tab);
        }

        loadTasksPiket();
    };

    window.switchPiketTab = function(tab) {
        if (!currentPiketContainer) return;
        currentPiketTab = tab;

        const tabs = ['permits', 'incidents', 'invals', 'guests'];
        tabs.forEach(t => {
            const panel = currentPiketContainer.querySelector(`#panel-piket-${t}`);
            const btn = currentPiketContainer.querySelector(`#tab-piket-${t}-btn`);
            if (panel) panel.classList.toggle('hidden', t !== tab);
            if (btn) {
                if (t === tab) {
                    btn.className = 'flex-1 py-1.5 rounded-lg bg-white shadow-xs text-purple-700 font-bold transition';
                } else {
                    btn.className = 'flex-1 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 font-bold transition';
                }
            }
        });
    };

    window.openPiketAction = function(action) {
        switchPiketTab(action);
        if (action === 'permits') openPiketPermitModal();
        else if (action === 'incidents') openPiketIncidentModal();
        else if (action === 'invals') openPiketInvalModal();
        else if (action === 'guests') openPiketGuestModal();
    };

    window.loadTasksPiket = async function() {
        if (!currentPiketContainer) return;
        try {
            const res = await SIAP_API.getPiket();
            if (res.success && res.data) {
                piketDataCache = res.data;
                renderPiketUI(res.data);
            }
        } catch (err) {
            showToast('Gagal memuat data meja piket: ' + err.message, 'error');
        }
    };

    function renderPiketUI(data) {
        if (!currentPiketContainer) return;

        // 1. Tanggal Hari Ini
        const dateLabel = currentPiketContainer.querySelector('#piket-today-date-label');
        if (dateLabel) {
            dateLabel.textContent = `${data.today_day}, ${data.today}`;
        }

        // 2. Status Shift User
        const badgeShift = currentPiketContainer.querySelector('#piket-shift-status-badge');
        const detailShift = currentPiketContainer.querySelector('#piket-shift-detail');
        if (badgeShift && detailShift) {
            if (data.is_on_duty_today) {
                badgeShift.textContent = 'Bertugas Hari Ini';
                badgeShift.className = 'px-2 py-0.5 rounded-full text-[9px] font-bold bg-purple-600 text-white shadow-xs';
                detailShift.textContent = 'Anda aktif bertugas di Meja Piket & Gerbang hari ini.';
            } else if (data.is_management) {
                badgeShift.textContent = 'Supervisi Manajemen';
                badgeShift.className = 'px-2 py-0.5 rounded-full text-[9px] font-bold bg-purple-600 text-white shadow-xs';
                detailShift.textContent = 'Akses Penuh Manajemen: Hak supervisi, operasional, & penutupan jurnal.';
            } else {
                badgeShift.textContent = 'Tidak Bertugas Hari Ini';
                badgeShift.className = 'px-2 py-0.5 rounded-full text-[9px] font-bold bg-amber-100 text-amber-800';
                if (data.my_schedules && data.my_schedules.length > 0) {
                    detailShift.textContent = `Jadwal piket rutin Anda: ${data.my_schedules.join(', ')}`;
                } else {
                    detailShift.textContent = 'Anda belum terdaftar dalam jadwal piket rutin semester ini.';
                }
            }
        }

        // 3. Status Jurnal & Tombol Tutup
        const journalBadge = currentPiketContainer.querySelector('#piket-journal-status');
        const journalActions = currentPiketContainer.querySelector('#piket-journal-actions');
        if (journalBadge) {
            if (data.journal?.status === 'selesai') {
                journalBadge.textContent = '✓ Jurnal Selesai';
                journalBadge.className = 'px-2 py-0.5 rounded-md text-[9px] font-bold bg-emerald-100 text-emerald-800';
                if (journalActions) journalActions.classList.add('hidden');
            } else {
                journalBadge.textContent = '● Jurnal Berlangsung';
                journalBadge.className = 'px-2 py-0.5 rounded-md text-[9px] font-bold bg-amber-100 text-amber-800 animate-pulse';
                if (journalActions) {
                    if (data.is_coordinator || data.is_management) {
                        journalActions.classList.remove('hidden');
                    } else {
                        journalActions.classList.add('hidden');
                    }
                }
            }
        }

        // 4. Daftar Petugas Piket Hari Ini
        const officersCount = currentPiketContainer.querySelector('#piket-officers-count');
        const officersChips = currentPiketContainer.querySelector('#piket-officers-chips');
        const officers = data.scheduled_officers || [];
        if (officersCount) officersCount.textContent = `${officers.length} Petugas`;
        if (officersChips) {
            if (officers.length === 0) {
                officersChips.innerHTML = '<span class="text-[10px] text-slate-400">Tidak ada jadwal piket resmi hari ini.</span>';
            } else {
                officersChips.innerHTML = officers.map(o => {
                    const isCoord = o.role === 'koordinator';
                    const roleBadge = isCoord ? '<span class="text-[8px] bg-purple-600 text-white px-1 rounded font-bold">Koordinator</span>' : '';
                    const swapBadge = o.is_swapped ? `<span class="text-[8px] bg-amber-100 text-amber-800 px-1 rounded font-bold">Digantikan: ${escapeHtml(o.replacement_teacher_name || '-')}</span>` : '';
                    return `
                        <div class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-white border border-purple-100 text-slate-800 text-[10px] font-medium shadow-2xs">
                            <span>👤 ${escapeHtml(o.teacher_name)}</span>
                            ${roleBadge}
                            ${swapBadge}
                        </div>
                    `;
                }).join('');
            }
        }

        // 5. Statistik Utama (4 Kartu Cepat)
        const stats = data.stats || {};
        const statPermits = currentPiketContainer.querySelector('#stat-piket-permits-count');
        const statIncidents = currentPiketContainer.querySelector('#stat-piket-incidents-count');
        const statInvals = currentPiketContainer.querySelector('#stat-piket-invals-count');
        const statGuests = currentPiketContainer.querySelector('#stat-piket-guests-count');
        if (statPermits) statPermits.textContent = stats.total_permits || 0;
        if (statIncidents) statIncidents.textContent = stats.total_incidents || 0;
        if (statInvals) statInvals.textContent = stats.total_invals || 0;
        if (statGuests) statGuests.textContent = stats.total_guests || 0;

        // Sub-stats Izin
        const subLate = currentPiketContainer.querySelector('#stat-permit-terlambat');
        const subExit = currentPiketContainer.querySelector('#stat-permit-keluar');
        const subHome = currentPiketContainer.querySelector('#stat-permit-pulang');
        const subAbs = currentPiketContainer.querySelector('#stat-permit-tidak-masuk');
        if (subLate) subLate.textContent = stats.permits_terlambat || 0;
        if (subExit) subExit.textContent = stats.permits_keluar || 0;
        if (subHome) subHome.textContent = stats.permits_pulang || 0;
        if (subAbs) subAbs.textContent = stats.permits_tidak_masuk || 0;

        // Sub-stats Kasus
        const subIncRec = currentPiketContainer.querySelector('#stat-inc-tercatat');
        const subIncDone = currentPiketContainer.querySelector('#stat-inc-selesai');
        const subIncBk = currentPiketContainer.querySelector('#stat-inc-bk');
        if (subIncRec) subIncRec.textContent = stats.incidents_tercatat || 0;
        if (subIncDone) subIncDone.textContent = stats.incidents_selesai || 0;
        if (subIncBk) subIncBk.textContent = stats.incidents_rujuk_bk || 0;

        // Sub-stats Inval
        const subInvHandled = currentPiketContainer.querySelector('#stat-inval-handled');
        const subInvUnhandled = currentPiketContainer.querySelector('#stat-inval-unhandled');
        if (subInvHandled) subInvHandled.textContent = `${stats.invals_handled || 0} Kelas`;
        if (subInvUnhandled) subInvUnhandled.textContent = `${stats.invals_unhandled || 0} Kelas`;

        // Sub-stats Tamu
        const subGuestVisiting = currentPiketContainer.querySelector('#stat-guest-visiting');
        const subGuestOut = currentPiketContainer.querySelector('#stat-guest-checked-out');
        if (subGuestVisiting) subGuestVisiting.textContent = `${stats.guests_visiting || 0} Orang`;
        if (subGuestOut) subGuestOut.textContent = `${stats.guests_checked_out || 0} Orang`;

        // 6. Populate Dropdowns Helpers
        const helpers = data.helpers || {};
        if (currentPiketContainer.querySelector('#permit-classroom-select')) {
            populateSelect(currentPiketContainer.querySelector('#permit-classroom-select'), helpers.classrooms, 'id', 'name', '-- Pilih Kelas --');
        }
        if (currentPiketContainer.querySelector('#inc-classroom-select')) {
            populateSelect(currentPiketContainer.querySelector('#inc-classroom-select'), helpers.classrooms, 'id', 'name', '-- Pilih Kelas --');
        }
        populateSelect(currentPiketContainer.querySelector('#inval-classroom-select'), helpers.classrooms, 'id', 'name', '-- Pilih Kelas --');
        populateSelect(currentPiketContainer.querySelector('#inval-absent-teacher-select'), helpers.teachers, 'id', 'name', '-- Pilih Guru Berhalangan --');
        populateSelect(currentPiketContainer.querySelector('#inval-teacher-select'), helpers.teachers, 'id', 'name', '-- Belum Ditugaskan / Cari Nanti --');
        populateSelect(currentPiketContainer.querySelector('#inval-subject-select'), helpers.subjects, 'id', 'name', '-- Pilih Mapel --');

        // 7. Render Recent Lists
        renderPiketPermitsList(data.permits || []);
        renderPiketIncidentsList(data.incidents || []);
        renderPiketGtkPermitsList(data.gtk_permits_today || []);
        renderPiketInvalsList(data.invals || []);
        renderPiketGuestsList(data.guests || []);
    }

    function populateSelect(selectEl, items, valKey, labelKey, placeholder) {
        if (!selectEl || !items) return;
        const currentVal = selectEl.value;
        selectEl.innerHTML = `<option value="">${placeholder}</option>` +
            items.map(item => `<option value="${item[valKey]}">${escapeHtml(item[labelKey])}</option>`).join('');
        if (currentVal) selectEl.value = currentVal;
    }

    function renderPiketPermitsList(permits) {
        const container = currentPiketContainer?.querySelector('#list-piket-permits-container');
        if (!container) return;

        if (permits.length === 0) {
            container.innerHTML = '<div class="text-center py-8 text-xs text-slate-400">Belum ada surat izin siswa yang diterbitkan hari ini.</div>';
            return;
        }

        container.innerHTML = permits.map(p => {
            const typeLabels = {
                terlambat: 'Masuk Terlambat',
                izin_keluar_sementara: 'Keluar Sementara',
                izin_pulang_cepat: 'Pulang Lebih Cepat',
                izin_tidak_masuk: 'Tidak Masuk / Dispen',
            };
            const typeBadgeColors = {
                terlambat: 'bg-amber-100 text-amber-800',
                izin_keluar_sementara: 'bg-blue-100 text-blue-800',
                izin_pulang_cepat: 'bg-purple-100 text-purple-800',
                izin_tidak_masuk: 'bg-indigo-100 text-indigo-800',
            };
            const badgeColor = typeBadgeColors[p.permit_type] || 'bg-slate-100 text-slate-700';

            return `
                <div class="card-elevated p-3 border-purple-100">
                    <div class="flex items-center justify-between mb-1">
                        <h4 class="text-xs font-black text-slate-900">${escapeHtml(p.student_name)} <span class="text-[10px] text-slate-400 font-normal">(${escapeHtml(p.classroom_name)})</span></h4>
                        <span class="px-2 py-0.2 rounded text-[9px] font-bold uppercase ${badgeColor}">${typeLabels[p.permit_type] || p.permit_type}</span>
                    </div>
                    <div class="flex items-center gap-2 text-[10px] text-slate-500 mb-1.5 font-mono">
                        <span>#${escapeHtml(p.permit_number)}</span>
                        ${p.entry_time ? `<span>• Masuk: ${p.entry_time}</span>` : ''}
                        ${p.departure_time ? `<span>• Keluar: ${p.departure_time}</span>` : ''}
                        ${p.period_assigned ? `<span>• Jam ke-${p.period_assigned}</span>` : ''}
                    </div>
                    <p class="text-xs text-slate-700 mb-1 leading-snug"><span class="font-bold text-slate-800">Alasan:</span> ${escapeHtml(p.reason || '-')}</p>
                    ${p.action_taken ? `<p class="text-[10px] text-purple-900 font-medium italic mb-2">Pembinaan: "${escapeHtml(p.action_taken)}"</p>` : ''}
                    <div class="flex items-center justify-between pt-1.5 border-t border-slate-100">
                        <span class="text-[9px] text-slate-400">Petugas: ${escapeHtml(p.given_by_name || 'Piket')}</span>
                        <button type="button" onclick="printPermitSlip(${p.id})" class="px-2.5 py-1 rounded-lg bg-purple-50 hover:bg-purple-100 text-purple-700 font-bold text-[10px] active:scale-95 transition flex items-center gap-1 border border-purple-200 cursor-pointer">
                            <span>🖨️</span><span>Cetak Slip</span>
                        </button>
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderPiketIncidentsList(incidents) {
        const container = currentPiketContainer?.querySelector('#list-piket-incidents-container');
        if (!container) return;

        if (incidents.length === 0) {
            container.innerHTML = '<div class="text-center py-8 text-xs text-slate-400">Belum ada kejadian atau pelanggaran yang dicatat hari ini.</div>';
            return;
        }

        container.innerHTML = incidents.map(inc => {
            const targetLabels = {
                selesai_di_piket: 'Selesai di Piket',
                rujuk_bk: 'Rujuk Guru BK',
                rujuk_wali_kelas: 'Rujuk Wali Kelas',
                rujuk_kesiswaan: 'Rujuk Kesiswaan',
            };
            const targetColors = {
                selesai_di_piket: 'bg-emerald-100 text-emerald-800',
                rujuk_bk: 'bg-indigo-100 text-indigo-800',
                rujuk_wali_kelas: 'bg-amber-100 text-amber-800',
                rujuk_kesiswaan: 'bg-rose-100 text-rose-800',
            };
            const targetColor = targetColors[inc.follow_up_target] || 'bg-slate-100 text-slate-700';

            return `
                <div class="card-elevated p-3 border-rose-100">
                    <div class="flex items-center justify-between mb-1">
                        <h4 class="text-xs font-black text-slate-900">${escapeHtml(inc.student_name)} <span class="text-[10px] text-slate-400 font-normal">(${escapeHtml(inc.classroom_name)})</span></h4>
                        <span class="px-2 py-0.2 rounded text-[9px] font-bold uppercase ${targetColor}">${targetLabels[inc.follow_up_target] || inc.follow_up_target}</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-[9px] text-slate-400 mb-1.5 font-mono">
                        <span class="px-1.5 py-0.2 rounded font-bold bg-rose-50 text-rose-700 uppercase">${escapeHtml(inc.category)}</span>
                        <span>• Pukul ${inc.incident_time}</span>
                        <span>• Oleh: ${escapeHtml(inc.recorder_name)}</span>
                    </div>
                    <p class="text-xs text-slate-700 mb-1 leading-snug">${escapeHtml(inc.description || '-')}</p>
                    <p class="text-[10px] text-rose-900 font-medium mb-1.5">Tindakan: "${escapeHtml(inc.action_taken || '-')}"</p>
                    ${inc.evidence_photo_url ? `
                        <div class="mt-1">
                            <a href="${inc.evidence_photo_url}" target="_blank" class="inline-flex items-center gap-1 text-[10px] font-bold text-rose-600 hover:underline">
                                📷 Lihat Foto Bukti
                            </a>
                        </div>
                    ` : ''}
                </div>
            `;
        }).join('');
    }

    function renderPiketGtkPermitsList(permits) {
        const wrap = currentPiketContainer?.querySelector('#piket-gtk-permits-wrap');
        const badge = currentPiketContainer?.querySelector('#piket-gtk-permits-badge');
        const container = currentPiketContainer?.querySelector('#list-piket-gtk-permits-container');
        if (!wrap || !container) return;

        if (!permits || permits.length === 0) {
            wrap.classList.add('hidden');
            return;
        }

        wrap.classList.remove('hidden');
        if (badge) badge.textContent = `${permits.length} Guru`;

        container.innerHTML = permits.map(p => {
            const hasTask = Boolean(p.assignment_document_url);
            const teacherName = p.user_name || p.teacher_name || 'Guru';
            const teacherId = p.user_id || p.teacher_id;
            const typeLabel = p.type_label || (p.permission_type ? p.permission_type.toUpperCase() : 'IZIN');
            const dateRange = (p.start_date && p.end_date) ? `${escapeHtml(p.start_date)} - ${escapeHtml(p.end_date)}` : '';
            return `
                <div class="card-elevated p-2.5 border-purple-100 bg-white shadow-2xs">
                    <div class="flex items-center justify-between mb-1">
                        <div class="flex items-center gap-1.5 min-w-0">
                            <span class="text-sm">👩‍🏫</span>
                            <div class="min-w-0">
                                <h4 class="text-xs font-bold text-slate-900 truncate">${escapeHtml(teacherName)}</h4>
                                <div class="text-[9px] text-purple-700 font-semibold uppercase tracking-wider">${escapeHtml(typeLabel)}${dateRange ? ` • ${dateRange}` : ''}</div>
                            </div>
                        </div>
                        <button type="button" onclick="openPiketInvalModal(${teacherId})" class="px-2 py-1 rounded-lg bg-purple-600 hover:bg-purple-700 text-white font-bold text-[10px] shrink-0 active:scale-95 transition flex items-center gap-1 shadow-2xs">
                            <span>🔄</span><span>Tugaskan</span>
                        </button>
                    </div>
                    <p class="text-[10px] text-slate-600 mb-1 leading-snug line-clamp-2"><span class="font-semibold text-slate-700">Alasan:</span> ${escapeHtml(p.reason || '-')}</p>
                    ${hasTask ? `
                        <div class="mt-1.5 p-1.5 rounded-lg bg-purple-50 border border-purple-100 flex items-center justify-between gap-1.5">
                            <div class="flex items-center gap-1 min-w-0">
                                <span class="text-xs">📎</span>
                                <div class="min-w-0">
                                    <div class="text-[10px] font-bold text-purple-950 truncate">${escapeHtml(p.assignment_document_name || 'Lampiran Tugas KBM')}</div>
                                    <div class="text-[8px] text-purple-600">File tugas dari guru izin</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <a href="${p.assignment_document_url}" target="_blank" class="px-1.5 py-0.5 rounded bg-purple-600 hover:bg-purple-700 text-white font-bold text-[9px] active:scale-95 transition">
                                    Unduh
                                </a>
                                <button type="button" onclick="sharePermitTaskWhatsApp('${p.assignment_document_url}', '${escapeHtml(p.assignment_document_name || 'Tugas Guru')}', '${escapeHtml(teacherName)}', '${escapeHtml(p.reason || '-')}')" class="px-1.5 py-0.5 rounded bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[9px] active:scale-95 transition flex items-center gap-0.5">
                                    <span>📲</span><span>WA</span>
                                </button>
                            </div>
                        </div>
                    ` : ''}
                </div>
            `;
        }).join('');
    }

    function renderPiketInvalsList(invals) {
        const container = currentPiketContainer?.querySelector('#list-piket-invals-container');
        if (!container) return;

        if (invals.length === 0) {
            container.innerHTML = '<div class="text-center py-8 text-xs text-slate-400">Belum ada jadwal guru inval hari ini.</div>';
            return;
        }

        container.innerHTML = invals.map(inv => {
            const isHandled = inv.is_handled;
            const statusBadge = isHandled 
                ? '<span class="px-2 py-0.5 rounded text-[9px] font-bold bg-emerald-100 text-emerald-800">✓ Terisi Inval</span>'
                : '<span class="px-2 py-0.5 rounded text-[9px] font-bold bg-rose-100 text-rose-800 animate-pulse">Belum Ada Inval</span>';

            return `
                <div class="card-elevated p-3 border-indigo-100">
                    <div class="flex items-center justify-between mb-1">
                        <h4 class="text-xs font-black text-slate-900">${escapeHtml(inv.classroom_name)} <span class="text-[10px] text-slate-500 font-normal">(${escapeHtml(inv.subject_name)})</span></h4>
                        ${statusBadge}
                    </div>
                    <div class="text-[10px] text-slate-500 mb-1.5 flex items-center gap-1 font-mono">
                        <span class="font-bold text-indigo-700">${escapeHtml(inv.period_label)}</span>
                        <span>• Guru Izin: ${escapeHtml(inv.absent_teacher_name)}</span>
                    </div>
                    <div class="p-2 rounded-xl bg-slate-50 border border-slate-100 text-xs mb-1.5">
                        <div class="flex items-center gap-1.5 text-slate-800 font-semibold mb-0.5">
                            <span>👨‍🏫 Guru Inval:</span>
                            <span class="${isHandled ? 'text-emerald-700 font-bold' : 'text-rose-600 italic'}">${escapeHtml(inv.inval_teacher_name || 'Belum ditugaskan')}</span>
                        </div>
                        <p class="text-[10px] text-slate-500"><span class="font-medium text-slate-600">Alasan:</span> ${escapeHtml(inv.reason || '-')}</p>
                    </div>
                    ${inv.assignment_material ? `
                        <p class="text-[10px] text-indigo-900 font-medium">Tugas Mapel: "${escapeHtml(inv.assignment_material)}"</p>
                    ` : ''}
                    ${inv.assignment_file_url ? `
                        <div class="mt-2 p-2 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-between gap-2">
                            <div class="flex items-center gap-1.5 min-w-0">
                                <span class="text-sm">📎</span>
                                <div class="min-w-0">
                                    <div class="text-[11px] font-bold text-indigo-950 truncate">${escapeHtml(inv.assignment_file_name || 'File Tugas KBM')}</div>
                                    <div class="text-[9px] text-indigo-600 font-medium">Lampiran Tugas Guru Mapel</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <a href="${inv.assignment_file_url}" target="_blank" class="px-2 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-[10px] active:scale-95 transition">
                                    Unduh
                                </a>
                                <button type="button" onclick="shareInvalTaskWhatsApp('${escapeHtml(inv.absent_teacher_name)}', '${escapeHtml(inv.classroom_name)}', '${escapeHtml(inv.subject_name)}', '${escapeHtml(inv.period_label)}', '${escapeHtml(inv.assignment_material || inv.reason || '')}', '${inv.assignment_file_url}')" class="px-2 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[10px] active:scale-95 transition flex items-center gap-0.5">
                                    <span>📲</span><span>WA</span>
                                </button>
                            </div>
                        </div>
                    ` : ''}
                </div>
            `;
        }).join('');
    }

    function renderPiketGuestsList(guests) {
        const container = currentPiketContainer?.querySelector('#list-piket-guests-container');
        if (!container) return;

        if (guests.length === 0) {
            container.innerHTML = '<div class="text-center py-8 text-xs text-slate-400">Belum ada kunjungan tamu hari ini.</div>';
            return;
        }

        container.innerHTML = guests.map(g => {
            const isCheckedOut = g.is_checked_out;
            const badge = isCheckedOut
                ? `<span class="px-2 py-0.5 rounded text-[9px] font-bold bg-slate-100 text-slate-600">Selesai (${g.check_out_time})</span>`
                : '<span class="px-2 py-0.5 rounded text-[9px] font-bold bg-amber-100 text-amber-800 animate-pulse">Sedang Bertemu</span>';

            const guestWaUrl = getWhatsappUrl(g.phone);
            const phoneDisplay = g.phone
                ? (guestWaUrl
                    ? `• <a href="${guestWaUrl}" target="_blank" class="text-emerald-700 font-bold inline-flex items-center gap-0.5 hover:underline" title="Chat WhatsApp Tamu"><span>📱</span><span>${escapeHtml(g.phone)}</span></a>`
                    : `• ${escapeHtml(g.phone)}`)
                : '';

            return `
                <div class="card-elevated p-3 border-emerald-100">
                    <div class="flex items-center justify-between mb-1">
                        <div>
                            <h4 class="text-xs font-black text-slate-900">${escapeHtml(g.guest_name)}</h4>
                            <p class="text-[10px] text-slate-500">${escapeHtml(g.institution)} ${phoneDisplay}</p>
                        </div>
                        ${badge}
                    </div>
                    <div class="text-[10px] text-slate-600 mb-1.5 font-mono">
                        <span>Check-in: ${g.check_in_time}</span>
                        <span>• Menemui: <strong class="text-slate-800">${escapeHtml(g.meet_with)}</strong></span>
                        ${g.badge_number ? `<span>• No. Badge: ${escapeHtml(g.badge_number)}</span>` : ''}
                    </div>
                    <p class="text-xs text-slate-700 leading-snug mb-2"><span class="font-bold text-slate-800">Keperluan:</span> ${escapeHtml(g.purpose || '-')}</p>
                    ${!isCheckedOut ? `
                        <div class="flex items-center justify-end pt-1.5 border-t border-slate-100">
                            <button type="button" onclick="quickCheckoutPiketGuest(${g.id})" class="px-3 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[10px] shadow-xs active:scale-95 transition">
                                ✓ Checkout Tamu Keluar
                            </button>
                        </div>
                    ` : ''}
                </div>
            `;
        }).join('');
    }

    // Modal Sheet Handlers
    window.printPermitSlip = function(permitId, printUrl = null) {
        const url = printUrl || `/piket/perizinan/${permitId}/slip?autoprint=1`;
        try {
            const win = window.open(url, '_blank');
            if (!win || win.closed || typeof win.closed === 'undefined') {
                let iframe = document.getElementById('piket-print-iframe');
                if (!iframe) {
                    iframe = document.createElement('iframe');
                    iframe.id = 'piket-print-iframe';
                    iframe.style.position = 'fixed';
                    iframe.style.right = '0';
                    iframe.style.bottom = '0';
                    iframe.style.width = '1px';
                    iframe.style.height = '1px';
                    iframe.style.border = '0';
                    document.body.appendChild(iframe);
                }
                iframe.src = url;
            }
        } catch (err) {
            window.open(url, '_blank');
        }
    };

    window.openPiketPermitModal = function() {
        const modal = currentPiketContainer?.querySelector('#modal-piket-permit');
        const bottomNav = document.getElementById('mobile-bottom-nav');
        if (bottomNav) bottomNav.classList.add('hidden');
        if (modal) {
            modal.classList.remove('hidden');
            const form = modal.querySelector('#form-piket-permit');
            if (form) form.reset();
            if (piketPermitPicker) piketPermitPicker.reset();

            // Default waktu masuk ke jam sekarang
            const now = new Date();
            const currentTimeStr = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
            const entryTime = modal.querySelector('#permit-entry-time');
            if (entryTime) entryTime.value = currentTimeStr;

            togglePermitFields('terlambat');
        }
    };

    window.closePiketPermitModal = function() {
        const modal = currentPiketContainer?.querySelector('#modal-piket-permit');
        const bottomNav = document.getElementById('mobile-bottom-nav');
        if (modal) modal.classList.add('hidden');
        if (bottomNav) bottomNav.classList.remove('hidden');
    };

    window.togglePermitFields = function(type) {
        if (!currentPiketContainer) return;
        const grpLate = currentPiketContainer.querySelector('#field-group-terlambat');
        const grpExit = currentPiketContainer.querySelector('#field-group-keluar');
        if (grpLate) grpLate.classList.toggle('hidden', type !== 'terlambat');
        if (grpExit) grpExit.classList.toggle('hidden', type !== 'izin_keluar_sementara');
    };

    window.handlePiketPermitSubmit = async function(e) {
        e.preventDefault();
        const form = e.target;
        const btn = form.querySelector('#btn-submit-piket-permit');
        const studentName = form.querySelector('#permit-student-name')?.value || form.querySelector('#permit-student-picker input[type="text"]')?.value;
        const studentId = form.querySelector('#permit-student-id')?.value;
        const classroomId = form.querySelector('#permit-classroom-id')?.value;

        if (!studentId && !studentName) {
            showToast('Silakan pilih siswa terlebih dahulu.', 'error');
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Menerbitkan...';

        try {
            const payload = {
                permit_type: form.querySelector('#permit-type-select').value,
                student_id: studentId || null,
                student_name: studentName || null,
                classroom_id: classroomId || null,
                entry_time: form.querySelector('#permit-entry-time')?.value || null,
                departure_time: form.querySelector('#permit-departure-time')?.value || null,
                return_time: form.querySelector('#permit-return-time')?.value || null,
                reason: form.querySelector('#permit-reason').value,
                action_taken: form.querySelector('#permit-action-taken')?.value || null,
            };

            const res = await SIAP_API.submitPiketStudentPermit(payload);
            if (res.success) {
                showToast(res.message || 'Surat izin berhasil diterbitkan!', 'success');
                closePiketPermitModal();
                loadTasksPiket();

                // Otomatis cetak slip izin masuk
                const permitId = res.data?.id;
                const printUrl = res.print_url || res.data?.print_url;
                if (permitId) {
                    window.printPermitSlip(permitId, printUrl);
                }
            } else {
                showToast(res.message || 'Gagal menerbitkan surat izin.', 'error');
            }
        } catch (err) {
            showToast('Gagal terbitkan izin: ' + err.message, 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Terbitkan Surat Izin';
        }
    };

    window.openPiketIncidentModal = function() {
        const modal = currentPiketContainer?.querySelector('#modal-piket-incident');
        const bottomNav = document.getElementById('mobile-bottom-nav');
        if (bottomNav) bottomNav.classList.add('hidden');
        if (modal) {
            modal.classList.remove('hidden');
            const form = modal.querySelector('#form-piket-incident');
            if (form) form.reset();
            if (piketIncidentPicker) piketIncidentPicker.reset();
            clearIncidentPhoto();

            // Default jam kejadian
            const now = new Date();
            const currentTimeStr = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
            const incTime = modal.querySelector('#inc-time');
            if (incTime) incTime.value = currentTimeStr;
        }
    };

    window.closePiketIncidentModal = function() {
        const modal = currentPiketContainer?.querySelector('#modal-piket-incident');
        const bottomNav = document.getElementById('mobile-bottom-nav');
        if (modal) modal.classList.add('hidden');
        if (bottomNav) bottomNav.classList.remove('hidden');
    };

    window.previewIncidentPhoto = async function(input) {
        if (!input.files || !input.files[0]) return;
        try {
            const dataUrl = await resizeImageToBase64(input.files[0], 1024, 1024, 0.85);
            piketIncidentPhotoBase64 = dataUrl;
            const wrap = currentPiketContainer?.querySelector('#inc-photo-preview-wrap');
            const img = currentPiketContainer?.querySelector('#inc-photo-preview');
            if (wrap && img) {
                img.src = dataUrl;
                wrap.classList.remove('hidden');
            }
        } catch (err) {
            showToast('Gagal memproses foto: ' + err.message, 'error');
        }
    };

    window.clearIncidentPhoto = function() {
        piketIncidentPhotoBase64 = null;
        const input = currentPiketContainer?.querySelector('#inc-evidence-photo');
        const wrap = currentPiketContainer?.querySelector('#inc-photo-preview-wrap');
        const img = currentPiketContainer?.querySelector('#inc-photo-preview');
        if (input) input.value = '';
        if (img) img.src = '';
        if (wrap) wrap.classList.add('hidden');
    };

    window.handlePiketIncidentSubmit = async function(e) {
        e.preventDefault();
        const form = e.target;
        const btn = form.querySelector('#btn-submit-piket-incident');

        const selectedStudents = piketIncidentPicker ? piketIncidentPicker.getSelectedStudents() : [];
        const studentIds = selectedStudents.map(s => s.id);
        const studentNames = selectedStudents.map(s => s.name).join(', ');
        const singleStudentId = form.querySelector('#inc-student-id')?.value;
        const singleStudentName = form.querySelector('#inc-student-name')?.value;
        const classroomId = form.querySelector('#inc-classroom-id')?.value || selectedStudents[0]?.classroom_id;

        if (studentIds.length === 0 && !singleStudentId && !singleStudentName) {
            showToast('Silakan pilih minimal 1 siswa yang terlibat.', 'error');
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Menyimpan...';

        try {
            const payload = {
                student_ids: studentIds.length > 0 ? studentIds : (singleStudentId ? [singleStudentId] : []),
                student_id: studentIds[0] || singleStudentId || null,
                student_name: studentNames || singleStudentName || null,
                classroom_id: classroomId || null,
                category: form.querySelector('#inc-category-select').value,
                incident_time: form.querySelector('#inc-time')?.value || null,
                location: form.querySelector('#inc-location')?.value || null,
                description: form.querySelector('#inc-description').value,
                action_taken: form.querySelector('#inc-action-taken').value,
                follow_up_target: form.querySelector('#inc-follow-up').value,
                confiscated_items: form.querySelector('#inc-confiscated')?.value || null,
                evidence_photo_base64: piketIncidentPhotoBase64,
            };

            const res = await SIAP_API.submitPiketIncident(payload);
            if (res.success) {
                showToast(res.message || 'Catatan kejadian berhasil disimpan!', 'success');
                closePiketIncidentModal();
                loadTasksPiket();
                switchPiketTab('incidents');
            } else {
                showToast(res.message || 'Gagal menyimpan kejadian.', 'error');
            }
        } catch (err) {
            showToast('Gagal catat kejadian: ' + err.message, 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Simpan Catatan Kejadian';
        }
    };

    let currentInvalTeacherSchedules = [];
    let currentInvalTeacherActivePermit = null;
    let isUsingPresetPermitAttachment = false;

    window.openPiketInvalModal = function(preselectedTeacherId = null) {
        const modal = currentPiketContainer?.querySelector('#modal-piket-inval');
        const bottomNav = document.getElementById('mobile-bottom-nav');
        if (bottomNav) bottomNav.classList.add('hidden');
        if (modal) {
            modal.classList.remove('hidden');
            const form = modal.querySelector('#form-piket-inval');
            if (form) form.reset();

            currentInvalTeacherSchedules = [];
            currentInvalTeacherActivePermit = null;
            isUsingPresetPermitAttachment = false;

            const schedSelect = modal.querySelector('#inval-schedule-select');
            const schedBadge = modal.querySelector('#inval-schedule-count-badge');
            const schedHint = modal.querySelector('#inval-schedule-hint');
            const presetWrap = modal.querySelector('#inval-preset-permit-wrap');
            const btnUsePreset = modal.querySelector('#btn-use-preset-file');
            const hiddenPreset = modal.querySelector('#inval-use-permit-attachment');

            if (schedSelect) schedSelect.innerHTML = '<option value="">-- Pilih Guru Di Atas Terlebih Dahulu --</option>';
            if (schedBadge) schedBadge.classList.add('hidden');
            if (schedHint) {
                schedHint.textContent = '';
                schedHint.classList.add('hidden');
            }
            if (presetWrap) presetWrap.classList.add('hidden');
            if (btnUsePreset) {
                btnUsePreset.className = 'px-2 py-0.5 rounded-lg bg-white border border-purple-300 text-purple-800 font-bold text-[10px] active:scale-95 transition';
                btnUsePreset.textContent = '✓ Pakai Ini';
            }
            if (hiddenPreset) hiddenPreset.value = '0';

            if (preselectedTeacherId) {
                const absentSelect = modal.querySelector('#inval-absent-teacher-select');
                if (absentSelect) {
                    absentSelect.value = preselectedTeacherId;
                    handleAbsentTeacherChange(preselectedTeacherId);
                }
            }
        }
    };

    window.closePiketInvalModal = function() {
        const modal = currentPiketContainer?.querySelector('#modal-piket-inval');
        const bottomNav = document.getElementById('mobile-bottom-nav');
        if (modal) modal.classList.add('hidden');
        if (bottomNav) bottomNav.classList.remove('hidden');
    };

    window.handleAbsentTeacherChange = async function(teacherId) {
        const modal = currentPiketContainer?.querySelector('#modal-piket-inval');
        if (!modal) return;

        const schedSelect = modal.querySelector('#inval-schedule-select');
        const schedBadge = modal.querySelector('#inval-schedule-count-badge');
        const schedHint = modal.querySelector('#inval-schedule-hint');
        const reasonInput = modal.querySelector('#inval-reason');
        const presetWrap = modal.querySelector('#inval-preset-permit-wrap');
        const presetName = modal.querySelector('#inval-preset-file-name');
        const presetLink = modal.querySelector('#inval-preset-file-link');

        if (!teacherId) {
            if (schedSelect) schedSelect.innerHTML = '<option value="">-- Pilih Guru Di Atas Terlebih Dahulu --</option>';
            if (schedBadge) schedBadge.classList.add('hidden');
            if (schedHint) schedHint.classList.add('hidden');
            if (presetWrap) presetWrap.classList.add('hidden');
            return;
        }

        if (schedSelect) schedSelect.innerHTML = '<option value="">Sedang memuat jadwal KBM guru hari ini...</option>';

        try {
            const res = await SIAP_API.getPiketTeacherSchedules(teacherId);
            if (res.success && res.data) {
                currentInvalTeacherSchedules = res.data.schedules || [];
                currentInvalTeacherActivePermit = res.data.active_permit || null;

                // 1. Render dropdown jadwal
                if (currentInvalTeacherSchedules.length === 0) {
                    if (schedSelect) schedSelect.innerHTML = '<option value="">-- Guru Tidak Ada Jadwal KBM Hari Ini --</option>';
                    if (schedBadge) {
                        schedBadge.textContent = '0 Jadwal';
                        schedBadge.className = 'text-[9px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-mono';
                        schedBadge.classList.remove('hidden');
                    }
                    if (schedHint) {
                        schedHint.textContent = 'ℹ️ Guru ini tidak memiliki jadwal tatap muka kelas hari ini.';
                        schedHint.className = 'text-[9.5px] text-amber-700 font-medium mt-1 block bg-amber-50 p-2 rounded-lg border border-amber-200';
                        schedHint.classList.remove('hidden');
                    }
                } else {
                    if (schedBadge) {
                        schedBadge.textContent = `${currentInvalTeacherSchedules.length} Jadwal`;
                        schedBadge.className = 'text-[9px] font-bold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 font-mono';
                        schedBadge.classList.remove('hidden');
                    }
                    if (schedHint) schedHint.classList.add('hidden');

                    let optionsHtml = '<option value="">-- Pilih Jadwal KBM --</option>';
                    if (currentInvalTeacherSchedules.length > 1) {
                        optionsHtml += `<option value="all">⭐ Semua Jadwal Hari Ini (${currentInvalTeacherSchedules.length} Rombel / 1 Hari Penuh)</option>`;
                    }
                    optionsHtml += currentInvalTeacherSchedules.map(s => `
                        <option value="${s.id}">
                            ${escapeHtml(s.label)} ${s.is_already_assigned ? '(Sudah ditugaskan: ' + escapeHtml(s.inval_teacher_name || 'Inval') + ')' : ''}
                        </option>
                    `).join('');

                    if (schedSelect) schedSelect.innerHTML = optionsHtml;
                }

                // 2. Cek apakah ada pengajuan izin GTK aktif
                if (currentInvalTeacherActivePermit) {
                    if (reasonInput && !reasonInput.value.trim()) {
                        reasonInput.value = `${currentInvalTeacherActivePermit.type_label}: ${currentInvalTeacherActivePermit.reason}`;
                    }

                    if (currentInvalTeacherActivePermit.assignment_document_url && presetWrap) {
                        presetWrap.classList.remove('hidden');
                        if (presetName) presetName.textContent = currentInvalTeacherActivePermit.assignment_document_name || 'Lampiran Tugas dari Izin Guru';
                        if (presetLink) presetLink.href = currentInvalTeacherActivePermit.assignment_document_url;
                        toggleUsePresetFile(true);
                    } else if (presetWrap) {
                        presetWrap.classList.add('hidden');
                    }
                } else {
                    if (presetWrap) presetWrap.classList.add('hidden');
                }
            }
        } catch (err) {
            showToast('Gagal memuat jadwal guru: ' + err.message, 'error');
            if (schedSelect) schedSelect.innerHTML = '<option value="">Gagal memuat jadwal</option>';
        }
    };

    window.handleInvalScheduleChange = function(val) {
        const modal = currentPiketContainer?.querySelector('#modal-piket-inval');
        if (!modal) return;

        const schedHint = modal.querySelector('#inval-schedule-hint');
        const hiddenClass = modal.querySelector('#inval-classroom-id');
        const hiddenSubj = modal.querySelector('#inval-subject-id');
        const hiddenStart = modal.querySelector('#inval-period-start');
        const hiddenEnd = modal.querySelector('#inval-period-end');

        if (val === 'all') {
            if (schedHint) {
                schedHint.textContent = `✓ Menugaskan otomatis ke seluruh ${currentInvalTeacherSchedules.length} jadwal rombel kelas kosong hari ini.`;
                schedHint.className = 'text-[9.5px] text-indigo-700 font-bold mt-1 block bg-indigo-50 p-2 rounded-lg border border-indigo-200';
                schedHint.classList.remove('hidden');
            }
            if (hiddenClass && currentInvalTeacherSchedules[0]) hiddenClass.value = currentInvalTeacherSchedules[0].classroom_id;
            if (hiddenSubj && currentInvalTeacherSchedules[0]) hiddenSubj.value = currentInvalTeacherSchedules[0].subject_id;
            return;
        }

        const sch = currentInvalTeacherSchedules.find(s => String(s.id) === String(val));
        if (sch) {
            if (hiddenClass) hiddenClass.value = sch.classroom_id;
            if (hiddenSubj) hiddenSubj.value = sch.subject_id;
            if (hiddenStart) hiddenStart.value = sch.start_period;
            if (hiddenEnd) hiddenEnd.value = sch.end_period;

            if (schedHint) {
                schedHint.textContent = `Ruang ${sch.classroom_name} • ${sch.subject_name} • ${sch.period_label}`;
                schedHint.className = 'text-[9.5px] text-slate-600 font-semibold mt-1 block';
                schedHint.classList.remove('hidden');
            }
        } else {
            if (schedHint) schedHint.classList.add('hidden');
        }
    };

    window.toggleUsePresetFile = function(forceValue = null) {
        const modal = currentPiketContainer?.querySelector('#modal-piket-inval');
        if (!modal) return;

        if (forceValue !== null) {
            isUsingPresetPermitAttachment = Boolean(forceValue);
        } else {
            isUsingPresetPermitAttachment = !isUsingPresetPermitAttachment;
        }

        const hiddenPreset = modal.querySelector('#inval-use-permit-attachment');
        const btnUsePreset = modal.querySelector('#btn-use-preset-file');

        if (hiddenPreset) hiddenPreset.value = isUsingPresetPermitAttachment ? '1' : '0';
        if (btnUsePreset) {
            if (isUsingPresetPermitAttachment) {
                btnUsePreset.className = 'px-2 py-0.5 rounded-lg bg-purple-700 text-white font-bold text-[10px] active:scale-95 transition';
                btnUsePreset.textContent = '✓ Terpakai';
            } else {
                btnUsePreset.className = 'px-2 py-0.5 rounded-lg bg-white border border-purple-300 text-purple-800 font-bold text-[10px] active:scale-95 transition';
                btnUsePreset.textContent = 'Pakai Ini';
            }
        }
    };

    window.handlePiketInvalSubmit = async function(e) {
        e.preventDefault();
        const form = e.target;
        const btn = form.querySelector('#btn-submit-piket-inval');

        const absentTeacherId = form.querySelector('#inval-absent-teacher-select')?.value;
        const scheduleId = form.querySelector('#inval-schedule-select')?.value;

        if (!absentTeacherId) {
            showToast('Silakan pilih guru yang berhalangan.', 'error');
            return;
        }

        if (!scheduleId && currentInvalTeacherSchedules.length > 0) {
            showToast('Silakan pilih jadwal KBM hari ini atau pilih Semua Jadwal.', 'error');
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Menyimpan...';

        try {
            const formData = new FormData(form);
            const res = await SIAP_API.submitPiketInval(formData);
            if (res.success) {
                showToast(res.message || 'Tugas guru inval berhasil disimpan!', 'success');
                closePiketInvalModal();
                loadTasksPiket();
                switchPiketTab('invals');
            } else {
                showToast(res.message || 'Gagal menyimpan tugas inval.', 'error');
            }
        } catch (err) {
            showToast('Gagal simpan inval: ' + err.message, 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Simpan Tugas Inval';
        }
    };

    window.openPiketGuestModal = function() {
        const modal = currentPiketContainer?.querySelector('#modal-piket-guest');
        const bottomNav = document.getElementById('mobile-bottom-nav');
        if (bottomNav) bottomNav.classList.add('hidden');
        if (modal) {
            modal.classList.remove('hidden');
            const form = modal.querySelector('#form-piket-guest');
            if (form) form.reset();
        }
    };

    window.closePiketGuestModal = function() {
        const modal = currentPiketContainer?.querySelector('#modal-piket-guest');
        const bottomNav = document.getElementById('mobile-bottom-nav');
        if (modal) modal.classList.add('hidden');
        if (bottomNav) bottomNav.classList.remove('hidden');
    };

    window.handlePiketGuestSubmit = async function(e) {
        e.preventDefault();
        const form = e.target;
        const btn = form.querySelector('#btn-submit-piket-guest');

        btn.disabled = true;
        btn.textContent = 'Menyimpan...';

        try {
            const payload = {
                guest_name: form.querySelector('#guest-name').value,
                institution: form.querySelector('#guest-institution').value || null,
                phone: form.querySelector('#guest-phone').value || null,
                meet_with: form.querySelector('#guest-meet-with').value,
                badge_number: form.querySelector('#guest-badge-number').value || null,
                purpose: form.querySelector('#guest-purpose').value,
            };

            const res = await SIAP_API.submitPiketGuest(payload);
            if (res.success) {
                showToast(res.message || 'Buku tamu berhasil dicatat!', 'success');
                closePiketGuestModal();
                loadTasksPiket();
                switchPiketTab('guests');
            } else {
                showToast(res.message || 'Gagal menyimpan buku tamu.', 'error');
            }
        } catch (err) {
            showToast('Gagal catat tamu: ' + err.message, 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Simpan Buku Tamu';
        }
    };

    window.quickCheckoutPiketGuest = async function(guestId) {
        try {
            showToast('Memproses check-out...', 'info');
            const res = await SIAP_API.checkoutPiketGuest(guestId);
            if (res.success) {
                showToast(res.message || 'Tamu berhasil check-out!', 'success');
                loadTasksPiket();
            } else {
                showToast(res.message || 'Gagal checkout tamu.', 'error');
            }
        } catch (err) {
            showToast('Gagal checkout: ' + err.message, 'error');
        }
    };

    window.promptClosePiketJournal = async function() {
        const confirmClose = window.confirm('Apakah Anda yakin ingin memvalidasi dan menutup jurnal piket hari ini? Semua rekap akan difinalkan.');
        if (!confirmClose) return;

        try {
            showToast('Menutup jurnal piket...', 'info');
            const res = await SIAP_API.closePiketJournal();
            if (res.success) {
                showToast(res.message || 'Jurnal piket harian berhasil ditutup!', 'success');
                loadTasksPiket();
            } else {
                showToast(res.message || 'Gagal menutup jurnal.', 'error');
            }
        } catch (err) {
            showToast('Gagal menutup jurnal: ' + err.message, 'error');
        }
    };

    // ==========================================
    // TUKAR JADWAL PIKET CONTROLLER
    // ==========================================
    window.initTasksPiketSwap = function(container, params = {}) {
        currentPiketSwapContainer = container;
        const dutyDateInput = container.querySelector('#swap-duty-date');
        if (dutyDateInput) {
            dutyDateInput.value = new Date().toISOString().split('T')[0];
            dutyDateInput.min = new Date().toISOString().split('T')[0];
        }

        const hashQuery = (window.location.hash || '').split('?')[1] || '';
        const searchParams = new URLSearchParams(hashQuery);
        const tab = params?.tab || searchParams.get('tab');
        if (tab === 'form') {
            switchSwapTab('form');
        } else {
            switchSwapTab('history');
        }

        loadTasksPiketSwap();
    };

    window.switchSwapTab = function(tab) {
        if (!currentPiketSwapContainer) return;
        currentSwapTab = tab;

        const panelHistory = currentPiketSwapContainer.querySelector('#panel-swap-history');
        const panelForm = currentPiketSwapContainer.querySelector('#panel-swap-form');
        const btnHistory = currentPiketSwapContainer.querySelector('#tab-swap-history-btn');
        const btnForm = currentPiketSwapContainer.querySelector('#tab-swap-form-btn');

        if (tab === 'history') {
            if (panelHistory) panelHistory.classList.remove('hidden');
            if (panelForm) panelForm.classList.add('hidden');
            if (btnHistory) btnHistory.className = 'flex-1 py-1.5 rounded-lg bg-white shadow-xs text-purple-700 transition font-bold';
            if (btnForm) btnForm.className = 'flex-1 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 transition font-bold';
        } else {
            if (panelHistory) panelHistory.classList.add('hidden');
            if (panelForm) panelForm.classList.remove('hidden');
            if (btnHistory) btnHistory.className = 'flex-1 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 transition font-bold';
            if (btnForm) btnForm.className = 'flex-1 py-1.5 rounded-lg bg-white shadow-xs text-purple-700 transition font-bold';
        }
    };

    window.loadTasksPiketSwap = async function() {
        if (!currentPiketSwapContainer) return;
        try {
            const res = await SIAP_API.getPiketSwaps();
            if (res.success && res.data) {
                piketSwapDataCache = res.data;
                renderPiketSwapUI(res.data);
            }
        } catch (err) {
            showToast('Gagal memuat pertukaran jadwal: ' + err.message, 'error');
        }
    };

    function renderPiketSwapUI(data) {
        if (!currentPiketSwapContainer) return;

        // Info jadwal saya
        const mySchedText = currentPiketSwapContainer.querySelector('#piket-swap-my-schedules-text');
        const mySchedules = data.my_schedules || [];
        if (mySchedText) {
            if (mySchedules.length > 0) {
                mySchedText.textContent = mySchedules.map(s => `${s.day} (${s.role || 'Petugas'})`).join(', ');
            } else {
                mySchedText.textContent = 'Anda belum memiliki jadwal piket semester ini.';
            }
        }

        // Populate selects
        const schedSelect = currentPiketSwapContainer.querySelector('#swap-original-schedule-select');
        if (schedSelect) {
            schedSelect.innerHTML = '<option value="">-- Pilih Jadwal Anda --</option>' +
                mySchedules.map(s => `<option value="${s.id}">Hari ${s.day} (${s.role})</option>`).join('');
        }

        const teacherSelect = currentPiketSwapContainer.querySelector('#swap-replacement-teacher-select');
        if (teacherSelect && data.teachers) {
            teacherSelect.innerHTML = '<option value="">-- Pilih Guru Pengganti --</option>' +
                data.teachers.map(t => `<option value="${t.id}">${escapeHtml(t.name)}</option>`).join('');
        }

        // Render swaps list
        const listContainer = currentPiketSwapContainer.querySelector('#list-swap-requests-container');
        if (listContainer) {
            const swaps = data.swaps || [];
            if (swaps.length === 0) {
                listContainer.innerHTML = '<div class="text-center py-8 text-xs text-slate-400">Belum ada riwayat permohonan tukar jadwal piket.</div>';
                return;
            }

            listContainer.innerHTML = swaps.map(s => {
                const statusBadges = {
                    menunggu: '<span class="px-2 py-0.5 rounded text-[9px] font-bold bg-amber-100 text-amber-800 animate-pulse">Menunggu Persetujuan</span>',
                    disetujui: '<span class="px-2 py-0.5 rounded text-[9px] font-bold bg-emerald-100 text-emerald-800">✓ Disetujui</span>',
                    ditolak: '<span class="px-2 py-0.5 rounded text-[9px] font-bold bg-rose-100 text-rose-800">✗ Ditolak</span>',
                };
                const badge = statusBadges[s.status] || statusBadges.menunggu;

                return `
                    <div class="card-elevated p-3 border-purple-100">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-[10px] font-mono text-purple-800 font-bold">Tukar Hari ${escapeHtml(s.schedule_day)} (${escapeHtml(s.duty_date_formatted)})</span>
                            ${badge}
                        </div>
                        <div class="p-2 rounded-xl bg-slate-50 border border-slate-100 text-xs mb-1.5 space-y-0.5">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500 text-[10px]">Pemohon:</span>
                                <span class="font-bold text-slate-800 text-[11px]">${escapeHtml(s.requester_name)}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500 text-[10px]">Guru Pengganti:</span>
                                <span class="font-bold text-purple-700 text-[11px]">${escapeHtml(s.replacement_teacher_name)}</span>
                            </div>
                        </div>
                        <p class="text-xs text-slate-700 leading-snug mb-2"><span class="font-bold text-slate-800">Alasan:</span> ${escapeHtml(s.reason || '-')}</p>
                        ${s.can_action ? `
                            <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100">
                                <button type="button" onclick="actionPiketSwapRequest(${s.id}, 'reject')" class="py-1 px-2 rounded-lg bg-rose-50 text-rose-700 border border-rose-200 text-xs font-bold active:scale-95 transition">
                                    Tolak
                                </button>
                                <button type="button" onclick="actionPiketSwapRequest(${s.id}, 'approve')" class="py-1 px-2 rounded-lg bg-emerald-600 text-white text-xs font-bold shadow-xs active:scale-95 transition">
                                    Setujui Tukar
                                </button>
                            </div>
                        ` : ''}
                    </div>
                `;
            }).join('');
        }
    }

    window.handlePiketSwapSubmit = async function(e) {
        e.preventDefault();
        const form = e.target;
        const btn = form.querySelector('#btn-submit-piket-swap');

        btn.disabled = true;
        btn.textContent = 'Mengirim Permohonan...';

        try {
            const payload = {
                original_schedule_id: form.querySelector('#swap-original-schedule-select').value,
                replacement_teacher_id: form.querySelector('#swap-replacement-teacher-select').value,
                duty_date: form.querySelector('#swap-duty-date').value,
                reason: form.querySelector('#swap-reason').value,
            };

            const res = await SIAP_API.submitPiketSwap(payload);
            if (res.success) {
                showToast(res.message || 'Permohonan tukar jadwal piket berhasil diajukan!', 'success');
                form.reset();
                await loadTasksPiketSwap();
                switchSwapTab('history');
            } else {
                showToast(res.message || 'Gagal mengajukan tukar jadwal.', 'error');
            }
        } catch (err) {
            showToast('Gagal mengajukan: ' + err.message, 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Kirim Permohonan Tukar Piket';
        }
    };

    window.actionPiketSwapRequest = async function(swapId, action) {
        const actionLabel = action === 'approve' ? 'menyetujui' : 'menolak';
        const confirmAction = window.confirm(`Apakah Anda yakin ingin ${actionLabel} permohonan pertukaran jadwal piket ini?`);
        if (!confirmAction) return;

        try {
            showToast('Memproses tanggapan...', 'info');
            const res = await SIAP_API.actionPiketSwap(swapId, action);
            if (res.success) {
                showToast(res.message || `Permohonan berhasil di-${action === 'approve' ? 'setujui' : 'tolak'}!`, 'success');
                loadTasksPiketSwap();
            } else {
                showToast(res.message || 'Gagal memproses permohonan.', 'error');
            }
        } catch (err) {
            showToast('Gagal proses: ' + err.message, 'error');
        }
    };

    // ==========================================
    // 7. INITIAL APP BOOTSTRAPPER
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
