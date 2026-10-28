/**
 * SIAP Mobile Application Controller & View Handlers
 */
(function() {
    let dashboardDataCache = null;
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
        showToast('Anda telah keluar dari sistem.', 'info');
        SIAP_ROUTER.navigate('/auth/login');
    }
    window.handleLogout = handleLogout;

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
    // 3. ATTENDANCE VIEW CONTROLLER
    // ==========================================
    window.initAttendance = async function(container) {
        async function fetchStatus() {
            try {
                const res = await SIAP_API.getAttendanceStatus();
                if (res.success && res.data) {
                    attendanceDataCache = res.data;
                    renderAttendanceContent(container, res.data);
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

        if (checkInTimeEl) checkInTimeEl.textContent = data.today?.check_in_time || '--:--';
        if (checkOutTimeEl) checkOutTimeEl.textContent = data.today?.check_out_time || '--:--';

        if (btnCheckIn) {
            btnCheckIn.disabled = !data.today?.can_check_in;
            btnCheckIn.className = data.today?.can_check_in
                ? 'w-full py-3 bg-blue-600 text-white rounded-xl text-xs font-bold shadow-md hover:bg-blue-700 active:scale-95 transition'
                : 'w-full py-3 bg-slate-200 text-slate-400 rounded-xl text-xs font-bold cursor-not-allowed';
        }

        if (btnCheckOut) {
            btnCheckOut.disabled = !data.today?.can_check_out;
            btnCheckOut.className = data.today?.can_check_out
                ? 'w-full py-3 bg-emerald-600 text-white rounded-xl text-xs font-bold shadow-md hover:bg-emerald-700 active:scale-95 transition'
                : 'w-full py-3 bg-slate-200 text-slate-400 rounded-xl text-xs font-bold cursor-not-allowed';
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
                            <span class="w-2 h-2 rounded-full ${l.type === 'check_in' ? 'bg-blue-500' : 'bg-emerald-500'}"></span>
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

        const originalText = button.textContent;
        button.disabled = true;
        button.innerHTML = '<span class="inline-block animate-spin mr-1">&#8635;</span> Membaca GPS...';

        navigator.geolocation.getCurrentPosition(
            async (pos) => {
                const lat = pos.coords.latitude;
                const lon = pos.coords.longitude;
                const acc = Math.round(pos.coords.accuracy);

                button.innerHTML = '<span class="inline-block animate-spin mr-1">&#8635;</span> Mengirim...';

                try {
                    const res = await SIAP_API.submitAttendance({
                        latitude: lat,
                        longitude: lon,
                        accuracy: acc,
                        type: type,
                    });

                    if (res.success) {
                        showToast(res.message || 'Presensi berhasil dicatat!', 'success');
                        window.initAttendance(container);
                    } else {
                        showToast(res.message || 'Presensi ditolak.', 'error');
                    }
                } catch (err) {
                    showToast(err.message || 'Gagal menyimpan presensi.', 'error');
                } finally {
                    button.disabled = false;
                    button.textContent = originalText;
                }
            },
            (err) => {
                button.disabled = false;
                button.textContent = originalText;
                let msg = 'Gagal mendeteksi lokasi GPS.';
                if (err.code === 1) msg = 'Izin akses lokasi (GPS) ditolak oleh browser/HP.';
                else if (err.code === 2) msg = 'Lokasi tidak dapat ditentukan.';
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
        // Date label
        const dateLabel = container.querySelector('#tasks-target-date-label');
        if (dateLabel) {
            dateLabel.textContent = `${data.target_date?.day_name}, ${data.target_date?.current}`;
        }

        // Homeroom Live Monitoring (8 cards)
        const homeroomSection = container.querySelector('#tasks-homeroom-section');
        if (homeroomSection) {
            if (data.homeroom) {
                homeroomSection.classList.remove('hidden');
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

                // Log Siswa Kronologis
                const eventList = container.querySelector('#tasks-student-events');
                if (eventList) {
                    const events = data.homeroom.student_events || [];
                    if (events.length === 0) {
                        eventList.innerHTML = '<div class="text-center py-4 text-xs text-slate-400">Tidak ada kejadian/pelanggaran siswa hari ini.</div>';
                    } else {
                        eventList.innerHTML = events.map(ev => `
                            <div class="p-3 card-subtle mb-2">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-xs font-bold text-slate-800">${ev.student_name}</span>
                                    <span class="text-[10px] font-mono text-slate-500">${ev.time}</span>
                                </div>
                                <p class="text-[11px] text-slate-600 font-medium">${ev.title}: ${ev.description}</p>
                                ${ev.action ? `<p class="text-[10px] text-blue-600 mt-1">Tindakan: ${ev.action}</p>` : ''}
                            </div>
                        `).join('');
                    }
                }
            } else {
                homeroomSection.classList.add('hidden');
            }
        }

        // Operational Roles Badges (BK & Kesiswaan)
        const roleOps = data.roles_operational || {};
        const bkBadge = container.querySelector('#tasks-badge-bk');
        const kesiswaanBadge = container.querySelector('#tasks-badge-kesiswaan');

        if (bkBadge) {
            if (roleOps.can_operate_bk) bkBadge.classList.remove('hidden');
            else bkBadge.classList.add('hidden');
        }

        if (kesiswaanBadge) {
            if (roleOps.can_manage_discipline || roleOps.is_waka_kesiswaan) kesiswaanBadge.classList.remove('hidden');
            else kesiswaanBadge.classList.add('hidden');
        }
    }

    // ==========================================
    // 5. PROFILE VIEW CONTROLLER
    // ==========================================
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
        SIAP_ROUTER.init();

        try {
            const res = await SIAP_API.me();
            if (res.authenticated && res.data?.user) {
                SIAP_ROUTER.setCurrentUser(res.data.user);
                localStorage.setItem('siap_user_data', JSON.stringify(res.data.user));

                const hash = window.location.hash.replace(/^#/, '');
                if (hash && hash !== '/auth/login') {
                    SIAP_ROUTER.navigate(hash);
                } else {
                    SIAP_ROUTER.navigate('/dashboard');
                }
            } else {
                SIAP_ROUTER.setCurrentUser(null);
                SIAP_ROUTER.navigate('/auth/login');
            }
        } catch (err) {
            SIAP_ROUTER.setCurrentUser(null);
            SIAP_ROUTER.navigate('/auth/login');
        }
    }

    // Start App on DOM Ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initApp);
    } else {
        initApp();
    }
})();
