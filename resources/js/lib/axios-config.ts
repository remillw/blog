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

// Intercepteur pour gérer les erreurs de réponse
axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 419) {
            console.error('❌ CSRF token mismatch. Please refresh the page.');
            // Vous pouvez ici afficher une notification à l'utilisateur
            if (typeof window !== 'undefined') {
                alert('Erreur de sécurité (CSRF). Veuillez rafraîchir la page et réessayer.');
            }
        }
        return Promise.reject(error);
    }
);

export default axios; 