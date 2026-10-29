/**
 * SIAP Mobile API Client
 * Penghubung client mobile SPA/APK ke REST API Laravel SIAP (/api/v1/mobile/*)
 */
const SIAP_API = (function() {
    const isNativeAppProtocol = window.location.protocol === 'file:' ||
                                window.location.protocol === 'capacitor:' ||
                                window.location.protocol === 'ionic:';

    const defaultOnlineServer = 'https://siap.smkpgrikras.sch.id';
    let initialBaseUrl = '';

    if (!isNativeAppProtocol && window.location.origin && window.location.origin !== 'null') {
        initialBaseUrl = window.location.origin;
    } else {
        const stored = localStorage.getItem('siap_server_url');
        if (stored && !stored.startsWith('file:') && !stored.startsWith('capacitor:')) {
            initialBaseUrl = stored;
        } else {
            initialBaseUrl = defaultOnlineServer;
        }
    }

    const API_CONFIG = {
        baseUrl: initialBaseUrl.replace(/\/+$/, ''),
        token: localStorage.getItem('siap_mobile_token') || '',
    };

    function setServerUrl(url) {
        if (!url) return;
        API_CONFIG.baseUrl = url.replace(/\/+$/, '');
        localStorage.setItem('siap_server_url', API_CONFIG.baseUrl);
    }

    function getServerUrl() {
        return API_CONFIG.baseUrl;
    }

    function setToken(token) {
        API_CONFIG.token = token || '';
        if (token) {
            localStorage.setItem('siap_mobile_token', token);
        } else {
            localStorage.removeItem('siap_mobile_token');
        }
    }

    function getToken() {
        return API_CONFIG.token || localStorage.getItem('siap_mobile_token');
    }

    async function request(endpoint, options = {}) {
        const cleanEndpoint = endpoint.startsWith('/') ? endpoint.slice(1) : endpoint;
        const url = `${API_CONFIG.baseUrl}/api/v1/mobile/${cleanEndpoint}`;

        const headers = {
            'Accept': 'application/json',
            ...(options.headers || {}),
        };

        if (!(options.body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
        }

        const token = getToken();
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        try {
            const response = await fetch(url, {
                ...options,
                headers,
                credentials: 'include', // Dukung HTTP-only session cookies
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                if (response.status === 401 && endpoint.includes('auth/me')) {
                    setToken(null);
                    localStorage.removeItem('siap_user_data');
                    return { success: false, authenticated: false, data: null };
                }

                if (response.status === 401 && !endpoint.includes('auth/login')) {
                    // Sesi kedaluwarsa, bersihkan token & lempar error
                    setToken(null);
                    localStorage.removeItem('siap_user_data');
                    if (window.SIAP_ROUTER) {
                        window.SIAP_ROUTER.navigate('/auth/login');
                    }
                }
                const errorMsg = data.message || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Terjadi kesalahan pada sistem.');
                const err = new Error(errorMsg);
                err.status = response.status;
                err.data = data;
                throw err;
            }

            return data;
        } catch (err) {
            console.error(`API Error [${endpoint}]:`, err);
            throw err;
        }
    }

    return {
        setServerUrl,
        getServerUrl,
        setToken,
        getToken,
        request,

        // Auth
        login: (login, password, remember = true) => request('auth/login', {
            method: 'POST',
            body: JSON.stringify({ login, password, remember }),
        }),
        me: () => request('auth/me', { method: 'GET' }),
        logout: () => request('auth/logout', { method: 'POST' }),

        // Core Data
        getDashboard: () => request('dashboard', { method: 'GET' }),
        getKbm: (date = null) => request(date ? `kbm?date=${date}` : 'kbm', { method: 'GET' }),
        getTasks: (date = null) => request(date ? `tasks?date=${date}` : 'tasks', { method: 'GET' }),
        
        // Modul Tugas Khusus Mobile
        getPermits: () => request('tasks/permits', { method: 'GET' }),
        submitPermit: (formData) => request('tasks/permits/store', {
            method: 'POST',
            body: formData,
        }),
        actionPermit: (id, action, note = null) => request(`tasks/permits/${id}/action`, {
            method: 'POST',
            body: JSON.stringify({ action, note }),
        }),

        searchStudents: (query) => request('students/search?q=' + encodeURIComponent(query || ''), { method: 'GET' }),

        getVisitations: () => request('tasks/visitations', { method: 'GET' }),
        submitVisitation: (payload) => request('tasks/visitations/store', {
            method: 'POST',
            body: JSON.stringify(payload),
        }),

        getDiscipline: () => request('tasks/discipline', { method: 'GET' }),
        verifyDisciplineIncident: (incidentId, verificationStatus, verificationNotes = null, followUpTarget = null) => request('tasks/discipline/verify', {
            method: 'POST',
            body: JSON.stringify({
                incident_id: incidentId,
                verification_status: verificationStatus,
                verification_notes: verificationNotes,
                follow_up_target: followUpTarget,
            }),
        }),

        getBk: () => request('tasks/bk', { method: 'GET' }),
        submitBkService: (payload) => request('tasks/bk/services/store', {
            method: 'POST',
            body: JSON.stringify(payload),
        }),
        updateBkReferralStatus: (incidentId, status, notes = null) => request('tasks/bk/status', {
            method: 'POST',
            body: JSON.stringify({ incident_id: incidentId, status, notes }),
        }),
        getProfile: () => request('profile', { method: 'GET' }),
        getActivityLogs: (date = null, limit = 50) => {
            let url = 'profile/activity-logs?limit=' + limit;
            if (date) url += '&date=' + encodeURIComponent(date);
            return request(url, { method: 'GET' });
        },
        getNotifications: () => request('notifications', { method: 'GET' }),
        updateProfile: (data) => request('profile/update', {
            method: 'POST',
            body: JSON.stringify(data),
        }),
        updatePassword: (currentPassword, newPassword, confirmPassword) => request('profile/password', {
            method: 'POST',
            body: JSON.stringify({
                current_password: currentPassword,
                password: newPassword,
                password_confirmation: confirmPassword,
            }),
        }),

        // Attendance Geotagging
        getAttendanceStatus: () => request('attendance/status', { method: 'GET' }),
        submitAttendance: (payload) => request('attendance/store', {
            method: 'POST',
            body: JSON.stringify(payload),
        }),
    };
})();

window.SIAP_API = SIAP_API;
