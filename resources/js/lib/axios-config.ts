import axios from 'axios';

// Configuration globale d'Axios pour inclure le token CSRF
const getCSRFToken = (): string => {
    const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (metaToken) {
        return metaToken;
    }
    
    const hiddenInput = document.querySelector('input[name="_token"]') as HTMLInputElement;
    if (hiddenInput?.value) {
        return hiddenInput.value;
    }
    
    const cookies = document.cookie.split(';');
    for (let cookie of cookies) {
        const [name, value] = cookie.trim().split('=');
        if (name === 'XSRF-TOKEN') {
            return decodeURIComponent(value);
        }
    }
    
    return '';
};

// Configuration des en-têtes par défaut d'Axios
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept'] = 'application/json';

// Intercepteur pour ajouter le token CSRF à chaque requête
axios.interceptors.request.use(
    (config) => {
        const token = getCSRFToken();
        if (token) {
            config.headers['X-CSRF-TOKEN'] = token;
            console.log('🔑 CSRF token added to request:', token.substring(0, 10) + '...');
        } else {
            console.warn('⚠️ No CSRF token found for request');
        }
        return config;
    },
    (error) => {
        console.error('❌ Request interceptor error:', error);
        return Promise.reject(error);
    }
);

// Fonction pour rafraîchir le token CSRF
const refreshCSRFToken = async (): Promise<string | null> => {
    try {
        console.log('🔄 Refreshing CSRF token...');
        const response = await fetch('/csrf-token', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (response.ok) {
            const data = await response.json();
            const newToken = data.csrf_token;
            
            // Mettre à jour le meta tag
            const metaTag = document.querySelector('meta[name="csrf-token"]');
            if (metaTag) {
                metaTag.setAttribute('content', newToken);
                console.log('✅ CSRF token refreshed successfully');
                return newToken;
            }
        }
    } catch (error) {
        console.error('❌ Failed to refresh CSRF token:', error);
    }
    return null;
};

// Intercepteur pour gérer les erreurs de réponse avec retry automatique
axios.interceptors.response.use(
    (response) => response,
    async (error) => {
        const originalRequest = error.config;
        
        if (error.response?.status === 419 && !originalRequest._retry) {
            console.error('❌ CSRF token mismatch - attempting to refresh...');
            originalRequest._retry = true;
            
            const newToken = await refreshCSRFToken();
            
            if (newToken) {
                // Réessayer la requête avec le nouveau token
                originalRequest.headers['X-CSRF-TOKEN'] = newToken;
                console.log('🔄 Retrying request with new CSRF token...');
                return axios(originalRequest);
            } else {
                // Si on n'arrive pas à rafraîchir le token, rediriger vers la page de connexion
                console.error('❌ Could not refresh CSRF token, redirecting to login...');
                if (typeof window !== 'undefined') {
                    window.location.href = '/login';
                }
            }
        }
        
        // Pour toutes les autres erreurs ou si le refresh a échoué
        if (error.response?.status === 419) {
            console.error('❌ CSRF protection error. Please refresh the page.');
            if (typeof window !== 'undefined') {
                alert('Erreur de sécurité (CSRF). La page va être rechargée automatiquement.');
                window.location.reload();
            }
        }
        
        return Promise.reject(error);
    }
);

export default axios; 