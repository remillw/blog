import { route } from 'ziggy-js';

/**
 * Composable pour la gestion centralisée des routes avec Ziggy
 */
export function useRoutes() {
    // Routes d'authentification
    const authRoutes = {
        login: () => route('login', undefined, false),
        register: () => route('register', undefined, false),
        logout: () => route('logout', undefined, false),
        passwordRequest: () => route('password.request', undefined, false),
        passwordEmail: () => route('password.email', undefined, false),
        passwordStore: () => route('password.store', undefined, false),
        passwordConfirm: () => route('password.confirm', undefined, false),
        verificationSend: () => route('verification.send', undefined, false),
        home: () => route('home', undefined, false),
    };

    // Routes principales de l'application
    const appRoutes = {
        dashboard: () => route('dashboard', undefined, false),
        profile: () => route('profile.edit', undefined, false),
    };

    // Routes pour les articles
    const articleRoutes = {
        index: () => route('articles.index', undefined, false),
        create: () => route('articles.create', undefined, false),
        store: () => route('articles.store', undefined, false),
        show: (id: string | number) => route('articles.show', id, false),
        edit: (id: string | number) => route('articles.edit', id, false),
        update: (id: string | number) => route('articles.update', id, false),
        destroy: (id: string | number) => route('articles.destroy', id, false),
    };

    // Routes pour les catégories
    const categoryRoutes = {
        index: () => route('categories.index', undefined, false),
        create: () => route('categories.create', undefined, false),
        store: () => route('categories.store', undefined, false),
        show: (id: string | number) => route('categories.show', id, false),
        edit: (id: string | number) => route('categories.edit', id, false),
        update: (id: string | number) => route('categories.update', id, false),
        destroy: (id: string | number) => route('categories.destroy', id, false),
    };

    // Routes pour les sites
    const siteRoutes = {
        index: () => route('sites.index', undefined, false),
        create: () => route('sites.create', undefined, false),
        store: () => route('sites.store', undefined, false),
        show: (id: string | number) => route('sites.show', id, false),
        edit: (id: string | number) => route('sites.edit', id, false),
        update: (id: string | number) => route('sites.update', id, false),
        destroy: (id: string | number) => route('sites.destroy', id, false),
    };

    // Routes pour les paramètres
    const settingsRoutes = {
        profile: () => route('profile.edit', undefined, false),
        update: () => route('profile.update', undefined, false),
        destroy: () => route('profile.destroy', undefined, false),
        security: () => route('password.update', undefined, false),
        appearance: () => route('appearance', undefined, false),
    };

    // Fonction utilitaire pour vérifier si une route existe
    const routeExists = (routeName: string): boolean => {
        try {
            route(routeName);
            return true;
        } catch {
            return false;
        }
    };

    // Fonction pour obtenir l'URL actuelle
    const currentRoute = () => window.location.pathname;

    // Fonction pour vérifier si on est sur une route spécifique
    const isCurrentRoute = (routeName: string, params?: any): boolean => {
        try {
            return route(routeName, params, false) === currentRoute();
        } catch {
            return false;
        }
    };

    return {
        // Groupes de routes
        authRoutes,
        appRoutes,
        articleRoutes,
        categoryRoutes,
        siteRoutes,
        settingsRoutes,
        
        // Fonctions utilitaires
        route,
        routeExists,
        currentRoute,
        isCurrentRoute,
    };
} 