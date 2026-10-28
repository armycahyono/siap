/**
 * SIAP Mobile Router & View Stacking Engine
 * Memberikan pengalaman navigasi instan 0ms antar menu tanpa jeda reload browser
 */
const SIAP_ROUTER = (function() {
    const ROUTES = {
        '/auth/login': {
            page: 'pages/auth/login.html',
            requiresAuth: false,
            title: 'Masuk SIAP',
            init: 'initAuthLogin',
            hideNav: true,
        },
        '/dashboard': {
            page: 'pages/dashboard.html',
            requiresAuth: true,
            title: 'Beranda GTK',
            init: 'initDashboard',
            tabKey: 'dashboard',
        },
        '/attendance': {
            page: 'pages/attendance.html',
            requiresAuth: true,
            title: 'Presensi Geotagging',
            init: 'initAttendance',
            tabKey: 'attendance',
        },
        '/tasks': {
            page: 'pages/tasks.html',
            requiresAuth: true,
            title: 'Tugas & Monitoring',
            init: 'initTasks',
            tabKey: 'tasks',
        },
        '/profile': {
            page: 'pages/profile.html',
            requiresAuth: true,
            title: 'Profil & Dokumen SK',
            init: 'initProfile',
            tabKey: 'profile',
        },
    };

    const templateCache = {};
    const mountedViews = {}; // Map: path -> DOM Element (View Stacking)
    let currentUser = null;
    let currentPath = null;

    function setCurrentUser(user) {
        currentUser = user;
        if (!user) {
            clearMountedViews();
        }
    }

    function getCurrentUser() {
        return currentUser;
    }

    function clearMountedViews() {
        const container = document.getElementById('app-view-container');
        if (container) {
            container.innerHTML = '';
        }
        for (const k in mountedViews) {
            delete mountedViews[k];
        }
        currentPath = null;
    }

    async function loadTemplate(pagePath) {
        if (templateCache[pagePath]) {
            return templateCache[pagePath];
        }

        try {
            const response = await fetch(pagePath, { cache: 'no-cache' });
            if (!response.ok) {
                throw new Error(`Gagal memuat template: ${pagePath} (${response.status})`);
            }
            const html = await response.text();
            templateCache[pagePath] = html;
            return html;
        } catch (err) {
            console.error('Template load error:', err);
            throw err;
        }
    }

    async function navigate(path, params = {}) {
        const cleanPath = path.split('?')[0];
        const route = ROUTES[cleanPath];

        if (!route) {
            console.warn(`Rute tidak ditemukan: ${path}, dialihkan ke beranda.`);
            return navigate(currentUser ? '/dashboard' : '/auth/login');
        }

        // Cek guard autentikasi
        if (route.requiresAuth && !currentUser) {
            return navigate('/auth/login');
        }

        if (!route.requiresAuth && currentUser && cleanPath === '/auth/login') {
            return navigate('/dashboard');
        }

        const container = document.getElementById('app-view-container');
        if (!container) return;

        // Bersihkan loader awal saat rute pertama kali dimuat
        const initialLoader = document.getElementById('initial-app-loader');
        if (initialLoader) {
            initialLoader.remove();
        }

        // Sembunyikan view aktif saat ini (View Stacking)
        if (currentPath && mountedViews[currentPath]) {
            mountedViews[currentPath].classList.add('hidden');
        }

        // Tampilkan atau Pasang View Baru
        let viewPane = mountedViews[cleanPath];

        if (!viewPane) {
            // Belum pernah dimuat: buat node baru
            try {
                const html = await loadTemplate(route.page);
                viewPane = document.createElement('div');
                viewPane.className = 'app-view-pane w-full';
                viewPane.setAttribute('data-route-path', cleanPath);
                viewPane.innerHTML = html;
                container.appendChild(viewPane);
                mountedViews[cleanPath] = viewPane;
            } catch (err) {
                container.innerHTML = `
                    <div class="p-6 text-center text-slate-500">
                        <p class="font-bold text-rose-500 mb-2">Gagal Memuat Halaman</p>
                        <p class="text-xs mb-4">${err.message}</p>
                        <button onclick="SIAP_ROUTER.navigate('${cleanPath}')" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-xs font-semibold">Coba Lagi</button>
                    </div>
                `;
                return;
            }
        }

        // Tampilkan pane target (0ms switch)
        viewPane.classList.remove('hidden');
        currentPath = cleanPath;

        // Perbarui URL hash tanpa reload
        if (window.location.hash !== `#${cleanPath}`) {
            window.location.hash = `#${cleanPath}`;
        }

        // Update Top Bar & Bottom Nav
        updateShellUI(route);

        // Jalankan lifecycle controller view
        if (route.init && typeof window[route.init] === 'function') {
            try {
                window[route.init](viewPane, params);
            } catch (err) {
                console.error(`Init error on [${route.init}]:`, err);
            }
        }
    }

    function updateShellUI(route) {
        const topBar = document.getElementById('mobile-top-bar');
        const bottomNav = document.getElementById('mobile-bottom-nav');
        const topBarTitle = document.getElementById('top-bar-title');

        if (route.hideNav) {
            if (topBar) topBar.classList.add('hidden');
            if (bottomNav) bottomNav.classList.add('hidden');
        } else {
            if (topBar) topBar.classList.remove('hidden');
            if (bottomNav) bottomNav.classList.remove('hidden');
            if (topBarTitle) topBarTitle.textContent = route.title || 'SIAP Mobile';
        }

        // Update active bottom nav item
        if (route.tabKey) {
            document.querySelectorAll('[data-tab-nav]').forEach(item => {
                const key = item.getAttribute('data-tab-nav');
                const icon = item.querySelector('.tab-icon');
                const label = item.querySelector('.tab-label');

                if (key === route.tabKey) {
                    item.className = 'flex flex-col items-center justify-center flex-1 py-1.5 text-blue-600 transition-colors';
                    if (icon) icon.classList.add('scale-110');
                    if (label) label.className = 'tab-label text-[10px] font-bold text-blue-600';
                } else {
                    item.className = 'flex flex-col items-center justify-center flex-1 py-1.5 text-slate-400 hover:text-slate-600 transition-colors';
                    if (icon) icon.classList.remove('scale-110');
                    if (label) label.className = 'tab-label text-[10px] font-medium text-slate-400';
                }
            });
        }
    }

    function init() {
        // Tangani perubahan hash di browser (tombol back/forward)
        window.addEventListener('hashchange', () => {
            const rawHash = window.location.hash.replace(/^#/, '');
            if (rawHash && rawHash !== currentPath) {
                navigate(rawHash);
            }
        });
    }

    return {
        ROUTES,
        init,
        navigate,
        setCurrentUser,
        getCurrentUser,
        getCurrentPath: () => currentPath,
        clearMountedViews,
    };
})();

window.SIAP_ROUTER = SIAP_ROUTER;
