# 📅 Workflow Editorial : Topics → Articles → Synchronisation

Ce document explique le workflow complet pour automatiser la création et la synchronisation de contenu.

## 🔄 Vue d'ensemble du processus

```
1. 📋 PLANIFICATION
   Topics créés/programmés
   └── Calendrier éditorial (/topics)

2. 🤖 GÉNÉRATION AUTO
   Articles générés depuis topics
   └── Commande: articles:generate-auto

3. 🔄 SYNCHRONISATION
   Articles envoyés vers autre site
   └── Commande: saas:sync-articles
```

## 📝 Étape 1 : Planification des Topics

### Créer des topics manuellement
```bash
# Aller sur le calendrier éditorial
http://votre-site.com/topics
```

### Générer des topics avec l'IA
```bash
# Via l'interface web
1. Cliquez sur "🤖 Générer avec IA"
2. Sélectionnez le site et la langue
3. Définissez le nombre de topics à générer
4. Ajoutez un domaine de focus (optionnel)
```

### Programmer des topics
```bash
# Pour chaque topic créé :
1. Définir une date de publication (scheduled_date)
2. Définir une heure (scheduled_time)
3. Changer le statut vers "scheduled"
```

## 🤖 Étape 2 : Génération automatique d'articles

### Commande manuelle
```bash
# Générer les articles pour tous les topics programmés aujourd'hui
php artisan articles:generate-auto

# Options disponibles :
php artisan articles:generate-auto --site-id=1    # Site spécifique
php artisan articles:generate-auto --dry-run      # Simulation
php artisan articles:generate-auto --force        # Ignorer l'heure programmée
```

### Automatisation (Cron)
```bash
# La commande s'exécute automatiquement toutes les 30 minutes
# Configuré dans routes/console.php

# Pour vérifier le cron Laravel :
php artisan schedule:list
```

### Ce qui se passe automatiquement :
1. ✅ Recherche des topics avec `status='scheduled'` et `scheduled_date=today()`
2. ✅ Vérification de l'heure programmée (`scheduled_time`)
3. ✅ Génération du contenu avec OpenAI basé sur le topic
4. ✅ Création de l'article avec `external_id` unique
5. ✅ Marquage du topic comme `published`
6. ✅ Association des catégories

## 🔄 Étape 3 : Synchronisation vers l'autre site

### Configuration requise
```bash
# Variables d'environnement nécessaires :
OPENAI_API_KEY=sk-...                    # Pour la génération IA
SAAS_SYNC_URL=http://autre-site.com      # URL du site de destination  
SAAS_API_KEY=votre-cle-api               # Clé API pour l'authentification
```

### Synchronisation manuelle
```bash
# Synchroniser tous les nouveaux articles
php artisan saas:sync-articles --api-key=VOTRE_CLE

# Options utiles :
php artisan saas:sync-articles \
  --api-key=VOTRE_CLE \
  --saas-url=http://autre-site.com \
  --status=published \
  --per-page=20 \
  --dry-run
```

### Automatisation de la synchronisation
```bash
# Ajouter dans routes/console.php :
Schedule::command('saas:sync-articles --api-key=VOTRE_CLE')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/sync.log'));
```

## 📊 Suivi et monitoring

### Logs disponibles
```bash
# Génération automatique
tail -f storage/logs/auto-generation.log

# Synchronisation
tail -f storage/logs/sync.log

# Laravel général
tail -f storage/logs/laravel.log
```

### Vérifications utiles
```bash
# Vérifier les topics programmés pour aujourd'hui
php artisan tinker
>>> App\Models\SiteTopic::where('status', 'scheduled')->where('scheduled_date', today())->count()

# Vérifier les articles en attente de sync
>>> App\Models\Article::where('is_synced', false)->count()

# Vérifier le dernier sync
>>> App\Models\SyncLog::latest()->first()
```

## 🔧 Configuration recommandée

### Cron Laravel (sur le serveur)
```bash
# Ajouter dans crontab -e :
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

### Workflow quotidien automatique
```bash
# 1. Génération d'articles (toutes les 30 min)
09:00, 09:30, 10:00, 10:30... → articles:generate-auto

# 2. Synchronisation (toutes les heures)
10:00, 11:00, 12:00... → saas:sync-articles

# 3. Nettoyage (quotidien à 2h)
02:00 → articles:clean-synced
```

## 🎯 Exemple de workflow complet

```bash
# 1. Créer des topics via l'interface web
# - Aller sur /topics
# - Générer 10 topics avec l'IA pour demain 9h
# - Les programmer pour demain 09:00

# 2. Le lendemain à 9h, automatiquement :
# - Le cron génère les articles depuis les topics
# - Les articles sont créés avec external_id

# 3. À 10h, automatiquement :
# - Le cron synchronise les nouveaux articles
# - Les articles apparaissent sur l'autre site

# 4. Vérification :
php artisan articles:generate-auto --dry-run
php artisan saas:sync-articles --api-key=XXX --dry-run
```

## 🚨 Dépannage

### Topics non générés ?
```bash
# Vérifier la configuration OpenAI
php artisan tinker
>>> config('services.openai.key')

# Vérifier les topics programmés
>>> App\Models\SiteTopic::where('status', 'scheduled')->get()
```

### Articles non synchronisés ?
```bash
# Vérifier les articles en attente
>>> App\Models\Article::where('is_synced', false)->count()

# Test de l'API
curl -H "X-API-Key: VOTRE_CLE" http://autre-site.com/api/articles
```

### Problèmes de permissions ?
```bash
# Vérifier les permissions des logs
chmod -R 775 storage/logs/
chown -R www-data:www-data storage/
``` 