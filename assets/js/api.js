/**
 * assets/js/api.js
 * Unified API Client for Northwind BMS
 */

const API = {
  // Dynamically resolve base API path (works on /projectII/api/ or localhost:8000/api/)
  baseUrl: window.location.pathname.includes('/projectII') ? '/projectII/api' : '/api',

  getToken() {
    return localStorage.getItem('northwind_jwt') || '';
  },

  setToken(token) {
    if (token) {
      localStorage.setItem('northwind_jwt', token);
    } else {
      localStorage.removeItem('northwind_jwt');
    }
  },

  getUser() {
    try {
      return JSON.parse(localStorage.getItem('northwind_user') || 'null');
    } catch (e) {
      return null;
    }
  },

  setUser(user) {
    if (user) {
      localStorage.setItem('northwind_user', JSON.stringify(user));
    } else {
      localStorage.removeItem('northwind_user');
    }
  },

  isAuthenticated() {
    return Boolean(this.getToken());
  },

  async request(endpoint, options = {}) {
    const cleanEndpoint = '/' + endpoint.replace(/^\/+/, '');
    const url = new URL(this.baseUrl + cleanEndpoint, window.location.origin);

    // Append query params if provided
    if (options.params) {
      Object.entries(options.params).forEach(([key, val]) => {
        if (val !== undefined && val !== null && val !== '') {
          url.searchParams.append(key, val);
        }
      });
    }

    const headers = {
      'Accept': 'application/json',
      ...(options.headers || {})
    };

    const token = this.getToken();
    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(options.body);
    }

    try {
      const response = await fetch(url.toString(), {
        ...options,
        headers
      });

      // Handle CSV or non-json download
      const contentType = response.headers.get('content-type') || '';
      if (contentType.includes('text/csv')) {
        return response.blob();
      }

      const data = await response.json();

      if (response.status === 401) {
        // Token expired or invalid
        if (this.getToken()) {
          this.setToken(null);
          this.setUser(null);
          if (window.App && typeof window.App.showScreen === 'function') {
            window.App.showScreen('login');
            window.App.toast('เซสชันหมดอายุ กรุณาเข้าสู่ระบบใหม่', 'warning');
          }
        }
      }

      if (!response.ok) {
        const error = new Error(data.message || `เกิดข้อผิดพลาด (${response.status})`);
        error.status = response.status;
        error.errors = data.errors || null;
        error.data = data;
        throw error;
      }

      return data;
    } catch (err) {
      console.error(`API Error [${options.method || 'GET'} ${cleanEndpoint}]:`, err);
      throw err;
    }
  },

  get(endpoint, params = {}) {
    return this.request(endpoint, { method: 'GET', params });
  },

  post(endpoint, body = {}) {
    return this.request(endpoint, { method: 'POST', body });
  },

  put(endpoint, body = {}) {
    return this.request(endpoint, { method: 'PUT', body });
  },

  delete(endpoint) {
    return this.request(endpoint, { method: 'DELETE' });
  }
};

window.API = API;
