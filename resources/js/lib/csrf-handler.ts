import { router } from '@inertiajs/vue3';

// Fonction pour mettre à jour le token CSRF dans le meta tag
const updateCSRFToken = (newToken: string) => {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    if (metaTag) {
        metaTag.setAttribute('content', newToken);
        console.log('✅ CSRF token updated in meta tag');
    }
};

// Fonction pour récupérer un nouveau token CSRF
const fetchNewCSRFToken = async (): Promise<string | null> => {
    try {
        console.log('🔄 Fetching new CSRF token...');
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
            
            if (newToken) {
                updateCSRFToken(newToken);
                console.log('✅ New CSRF token fetched successfully');
                return newToken;
            }
        }
    } catch (error) {
        console.error('❌ Failed to fetch new CSRF token:', error);
    }
    return null;
};

// Intercepteur pour les erreurs de réponse Inertia
router.on('error', (event) => {
    const error = event.detail.errors;
    
    // Vérifier si c'est une erreur CSRF (419)
    if (event.detail.response?.status === 419 || 
        error?.csrf_token || 
        error?.message?.includes('CSRF') || 
        error?.message?.includes('token')) {
        
        console.warn('⚠️ CSRF error detected, attempting to refresh token and reload page...');
        
        // Essayer de récupérer un nouveau token
        fetchNewCSRFToken().then((newToken) => {
            if (newToken) {
                // Recharger la page pour utiliser le nouveau token
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                // Si échec, rediriger vers login
                console.error('❌ Could not refresh CSRF token, redirecting to login...');
                window.location.href = '/login';
            }
        });
    }
});

// Mise à jour automatique du token CSRF à partir des données partagées Inertia
router.on('navigate', (event) => {
    const page = event.detail.page;
    if (page.props.csrf_token) {
        updateCSRFToken(page.props.csrf_token);
    }
});

export { updateCSRFToken, fetchNewCSRFToken }; 