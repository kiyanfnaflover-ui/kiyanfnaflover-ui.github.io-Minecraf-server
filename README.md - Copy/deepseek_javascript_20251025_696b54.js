async authenticate(username, password) {
    try {
        const response = await this.makeRequest('/login', {
            method: 'POST',
            body: JSON.stringify({ username, password })
        });
        
        if (response.success) {
            this.isAuthenticated = true;
            return true;
        }
        return false;
    } catch (error) {
        console.error('Authentication error:', error);
        return false;
    }
}