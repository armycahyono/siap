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
            } else {
                SIAP_ROUTER.setCurrentUser(null);
                localStorage.removeItem('siap_user_data');
            }
        } catch (err) {
            SIAP_ROUTER.setCurrentUser(null);
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
