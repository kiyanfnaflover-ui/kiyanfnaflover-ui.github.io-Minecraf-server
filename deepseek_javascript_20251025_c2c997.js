async makeRequest(endpoint, options = {}) {
    // اگر از API واقعی استفاده نمی‌کنیم، شبیه‌سازی را برگردان
    if (!SECURE_CONFIG.USE_REAL_API) {
        return this.simulateAternosResponse(endpoint, options);
    }

    const url = SECURE_CONFIG.BACKEND_URL + endpoint;
    const config = {
        method: options.method || 'GET',
        headers: {
            'Content-Type': 'application/json',
            ...options.headers
        }
    };

    if (options.body) {
        config.body = options.body;
    }

    try {
        const response = await fetch(url, config);
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Request failed:', error);
        return { success: false, error: 'Network error' };
    }
}