# Configuration IA et Multi-langues

## 🚀 Nouvelles fonctionnalités ajoutées

### 1. Génération automatique d'articles par IA

- **Prompt simple** : Décrivez votre sujet et l'IA génère un article complet
- **Génération complète** : Titre, extrait, contenu EditorJS, méta-données, suggestions de catégories
- **Optimisation SEO** : Contenu optimisé pour le référencement
- **Support multi-langues** : 10 langues supportées

### 2. Traduction automatique

- **Traduction intelligente** : Conserve la structure EditorJS
- **10 langues supportées** : Français, Anglais, Espagnol, Allemand, Italien, Portugais, Néerlandais, Russe, Japonais, Chinois
- **Préservation du SEO** : Maintient l'optimisation des méta-données

## ⚙️ Configuration requise

### 1. Clé API OpenAI

Ajoutez cette ligne à votre fichier `.env` :

```bash
OPENAI_API_KEY=sk-your-openai-api-key-here
```

**Pour obtenir votre clé API :**

1. Allez sur [platform.openai.com](https://platform.openai.com/)
2. Connectez-vous ou créez un compte
3. Allez dans "API Keys"
4. Créez une nouvelle clé API
5. Copiez-la dans votre fichier `.env`

### 2. Modèle utilisé

Le système utilise **GPT-4** pour une qualité optimale. Assurez-vous d'avoir accès à GPT-4 sur votre compte OpenAI.

## 🎯 Comment utiliser

### Génération d'article automatique

1. **Sélectionnez un site** (pour le contexte et les catégories)
2. **Choisissez la langue** de l'article
3. **Entrez votre prompt** dans le champ "Génération automatique d'article"
    - Exemple : "Écris un article sur les bienfaits du thé vert pour la santé"
    - Exemple : "Guide complet pour débuter en jardinage urbain"
4. **Cliquez sur "🪄 Générer"**

L'IA va automatiquement remplir :

- ✅ Titre principal
- ✅ Extrait/résumé
- ✅ Contenu complet avec EditorJS (paragraphes, titres, listes)
- ✅ Titre SEO (meta_title)
- ✅ Description SEO (meta_description)
- ✅ Mots-clés SEO
- ✅ Nom et bio de l'auteur
- ✅ Suggestions de catégories (basées sur celles disponibles)

### Traduction automatique

1. **Créez ou modifiez un article** existant
2. **Sélectionnez la langue cible** dans le sélecteur
3. **Cliquez sur "🌍 Traduire"**

L'IA va traduire tout le contenu en conservant :

- ✅ Structure EditorJS exacte
- ✅ Formatage et blocs
- ✅ Optimisation SEO
- ✅ Ton et style originaux

## 🎯 Nouveau système d'utilisation (Version améliorée)

### 🤖 Génération d'article automatique par IA

**Section dédiée avec interface verte** :

1. **Sélectionnez d'abord un site** (obligatoire pour le contexte)
2. **Choisissez la langue de génération** (FR, EN, ES, DE, IT, PT, NL, RU, JA, ZH)
3. **Entrez votre sujet/prompt détaillé** :
    - ✅ "Guide complet du référencement SEO pour e-commerce en 2024"
    - ✅ "Recette authentique du couscous marocain avec légumes de saison"
    - ✅ "Comment créer un jardin urbain sur balcon : guide pratique"
4. **Cliquez sur "🪄 Générer Article"**

**Résultat** : Article complet généré automatiquement avec structure EditorJS professionnelle.

### 🌍 Traduction automatique multi-langues

**Section dédiée avec interface violette** (apparaît seulement si du contenu existe) :

1. **Le système récupère automatiquement les langues du site sélectionné**
2. **Sélectionnez une ou plusieurs langues cibles** avec le MultiSelect
3. **Cliquez sur "🌍 Traduire (X)" pour traduire vers toutes les langues sélectionnées**
4. **Les traductions s'affichent dans la liste** avec possibilité de les charger individuellement

**Avantages du nouveau système** :

- ✅ **Séparation claire** : Génération ≠ Traduction
- ✅ **Langues contextuelles** : Seules les langues du site sont proposées
- ✅ **Traduction multiple** : Vers plusieurs langues simultanément
- ✅ **Gestion des résultats** : Chargement facile des traductions créées

## 💡 Conseils d'utilisation

### Pour la génération d'articles

**Prompts efficaces :**

```
✅ "Guide complet du référencement SEO pour débutants"
✅ "Les tendances marketing digital en 2024"
✅ "Comment optimiser la vitesse de son site web"
✅ "Recette traditionnelle de la ratatouille provençale"
```

**Évitez :**

```
❌ "Article"
❌ "Contenu"
❌ Prompts trop vagues
```

### Pour la traduction

- **Vérifiez toujours** le contenu traduit
- **Adaptez si nécessaire** selon le marché local
- **Optimisez les mots-clés** pour la langue cible

## 🔧 Dépannage

### Erreur "Clé API OpenAI non configurée"

- Vérifiez que `OPENAI_API_KEY` est bien définie dans `.env`
- Redémarrez votre serveur après modification

### Erreur de génération

- Vérifiez votre solde OpenAI
- Assurez-vous d'avoir accès à GPT-4
- Essayez un prompt plus simple

### Traduction incomplète

- Vérifiez que tous les champs sont remplis
- Essayez de traduire par petits blocs

## 🌐 Langues supportées

| Code | Langue     | Drapeau |
| ---- | ---------- | ------- |
| `fr` | Français   | 🇫🇷      |
| `en` | English    | 🇬🇧      |
| `es` | Español    | 🇪🇸      |
| `de` | Deutsch    | 🇩🇪      |
| `it` | Italiano   | 🇮🇹      |
| `pt` | Português  | 🇵🇹      |
| `nl` | Nederlands | 🇳🇱      |
| `ru` | Русский    | 🇷🇺      |
| `ja` | 日本語     | 🇯🇵      |
| `zh` | 中文       | 🇨🇳      |

## 🎨 Interface ajoutée

Une nouvelle section "🤖 Outils IA & Multi-langues" a été ajoutée dans le formulaire d'article avec :

- **Champ de prompt IA** avec bouton de génération
- **Sélecteur de langue** avec drapeaux
- **Bouton de traduction** (apparaît quand du contenu existe)
- **Gestionnaire de traductions** (pour les versions multiples)

## 📊 Coûts OpenAI estimés

- **Génération d'article** : ~0.10-0.30$ par article (selon la longueur)
- **Traduction** : ~0.05-0.15$ par traduction
- **Total mensuel estimé** : 10-50$ pour usage modéré (50-200 articles/mois)

## 🔐 Sécurité

- Les appels API sont **authentifiés** (middleware auth)
- La clé OpenAI est **sécurisée** côté serveur
- **Validation** de tous les inputs
- **Logs** détaillés pour le debugging

---

**✨ Profitez de ces nouveaux outils pour créer du contenu de qualité plus rapidement !**
