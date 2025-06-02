<template>
    <div class="mx-auto max-w-4xl">
        <form @submit.prevent="submit" class="space-y-6">
            <!-- Sélection du site en premier -->
            <div class="space-y-2">
                <Label for="site_id">Site</Label>
                <MultiSelect
                    v-model="selectedSiteValues"
                    :options="siteOptions"
                    placeholder="Sélectionner un site..."
                    :disabled="form.processing"
                    :max-selections="1"
                    class="w-full"
                />
                <InputError :message="form.errors.site_id" />
            </div>

            <!-- Affichage des couleurs du site -->
            <div v-if="siteColors.primary_color" class="space-y-2">
                <Label class="text-sm font-medium">Couleurs du site</Label>
                <div class="bg-muted/30 flex items-center gap-6 rounded-lg border p-4">
                    <div class="flex items-center gap-2">
                        <div class="h-8 w-8 rounded-lg border shadow-sm" :style="{ backgroundColor: siteColors.primary_color }"></div>
                        <div>
                            <p class="text-muted-foreground text-xs font-medium">Primary</p>
                            <p class="font-mono text-xs">{{ siteColors.primary_color }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="h-8 w-8 rounded-lg border shadow-sm" :style="{ backgroundColor: siteColors.secondary_color }"></div>
                        <div>
                            <p class="text-muted-foreground text-xs font-medium">Secondary</p>
                            <p class="font-mono text-xs">{{ siteColors.secondary_color }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="h-8 w-8 rounded-lg border shadow-sm" :style="{ backgroundColor: siteColors.accent_color }"></div>
                        <div>
                            <p class="text-muted-foreground text-xs font-medium">Accent</p>
                            <p class="font-mono text-xs">{{ siteColors.accent_color }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sélection de la langue après le site -->
            <div v-if="selectedSiteValues.length > 0 && siteLanguages.length > 0" class="space-y-2">
                <Label class="text-sm font-medium">Langue de l'article</Label>
                <Select v-model="currentLanguage" @update:model-value="switchLanguage">
                    <SelectTrigger class="w-full">
                        <SelectValue :placeholder="`${getLanguageFlag(currentLanguage)} ${getLanguageName(currentLanguage)}`" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="lang in siteLanguages" :key="lang.code" :value="lang.code">
                            <span class="flex items-center gap-2">
                                <span class="text-lg">{{ getLanguageFlag(lang.code) }}</span>
                                <span>{{ lang.name }}</span>
                            </span>
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p class="text-xs text-blue-600">{{ articleVersions.size }} version(s) disponible(s)</p>
            </div>

            <!-- Message si pas de site sélectionné -->
            <div v-if="selectedSiteValues.length === 0" class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                <div class="flex items-center gap-2">
                    <span class="text-amber-600">⚠️</span>
                    <p class="text-sm text-amber-800">Sélectionnez d'abord un site pour choisir la langue et les catégories</p>
                </div>
            </div>

            <!-- Message si pas de langue sélectionnée -->
            <div v-if="selectedSiteValues.length > 0 && !currentLanguage && siteLanguages.length > 0" class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                <div class="flex items-center gap-2">
                    <span class="text-blue-600">ℹ️</span>
                    <p class="text-sm text-blue-800">Sélectionnez une langue pour voir les catégories disponibles</p>
                </div>
            </div>

            <!-- Sélection des catégories après la langue -->
            <div v-if="selectedSiteValues.length > 0 && currentLanguage" class="space-y-2">
                <Label for="categories">Catégories ({{ getLanguageName(currentLanguage) }})</Label>
                <div v-if="availableCategories.length === 0" class="rounded-md border border-gray-200 bg-gray-50 p-3 text-sm text-gray-600">
                    Aucune catégorie disponible pour cette langue
                </div>
                <MultiSelect
                    v-else
                    v-model="selectedCategoryValues"
                    :options="categoryOptions"
                    placeholder="Sélectionnez les catégories..."
                    :disabled="form.processing"
                    class="w-full"
                />
                <InputError :message="form.errors.categories" class="mt-2" />
            </div>

            <!-- Sélecteur de langue pour navigation multi-langues (ancien système pour compatibilité) -->
            <div
                v-if="articleVersions.size > 1"
                class="rounded-lg border border-blue-200 bg-blue-50 p-4"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <Label class="text-sm font-medium text-blue-800">Navigation entre les versions</Label>
                        <p class="text-xs text-blue-600">Naviguez entre les différentes versions linguistiques</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <Select v-model="currentLanguage" @update:model-value="switchLanguage">
                            <SelectTrigger class="w-48">
                                <SelectValue placeholder="Choisir la langue" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="lang in availableLanguagesForSelection" :key="lang.code" :value="lang.code">
                                    <span class="flex items-center gap-2">
                                        <span class="text-lg">{{ lang.flag }}</span>
                                        <span>{{ lang.name }}</span>
                                    </span>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <div class="text-xs text-blue-600">{{ articleVersions.size }} version(s) disponible(s)</div>
                    </div>
                </div>
            </div>

            <!-- Section Génération IA Nouvelle Version -->
            <div class="space-y-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                <h3 class="text-lg font-semibold text-emerald-900">🤖 Génération d'article par IA</h3>

                <div class="space-y-3">
                    <div v-if="selectedSiteValues.length === 0" class="rounded-md border border-orange-200 bg-orange-50 p-3 text-sm text-orange-800">
                        ⚠️ Sélectionnez d'abord un site pour utiliser l'IA (contexte nécessaire)
                    </div>

                    <div v-else class="space-y-3">
                        <!-- Barre de progression pendant la génération -->
                        <div v-if="generatingWithAI" class="space-y-3 rounded-md border border-blue-200 bg-blue-50 p-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-blue-800">Génération en cours...</span>
                                <span class="text-sm text-blue-600">{{ Math.round(generationProgress) }}%</span>
                            </div>
                            
                            <!-- Barre de progression -->
                            <div class="w-full bg-blue-200 rounded-full h-2.5">
                                <div 
                                    class="bg-blue-600 h-2.5 rounded-full transition-all duration-500 ease-out"
                                    :style="{ width: generationProgress + '%' }"
                                ></div>
                            </div>
                            
                            <!-- Langue en cours de génération -->
                            <div v-if="currentGeneratingLanguage" class="flex items-center gap-2 text-sm text-blue-700">
                                <span class="animate-spin">⚙️</span>
                                <span>Génération pour : <strong>{{ getLanguageName(currentGeneratingLanguage) }}</strong></span>
                            </div>
                        </div>

                        <!-- Sélection des langues de génération -->
                        <div v-if="!generatingWithAI">
                            <Label class="mb-2 block text-sm font-medium text-emerald-800">Langues de génération</Label>
                            <MultiSelect
                                v-model="selectedGenerationLanguages"
                                :options="siteLanguageOptionsWithFlags"
                                placeholder="Choisir les langues pour la génération..."
                                :disabled="generatingWithAI || siteLanguages.length === 0"
                                class="w-full"
                            />
                            <div v-if="siteLanguages.length === 0" class="mt-1 text-xs text-emerald-600">Aucune langue configurée pour ce site</div>
                        </div>

                        <!-- Prompt de génération -->
                        <div v-if="!generatingWithAI">
                            <Label class="mb-2 block text-sm font-medium text-emerald-800">Sujet de l'article</Label>
                            <Input
                                v-model="aiPrompt"
                                placeholder="Ex: Guide complet du jardinage urbain pour débutants..."
                                :disabled="generatingWithAI"
                            />
                        </div>

                        <!-- Configuration du nombre de mots -->
                        <div v-if="!generatingWithAI" class="grid grid-cols-2 gap-4">
                            <div>
                                <Label class="mb-2 block text-sm font-medium text-emerald-800">Nombre de mots</Label>
                                <Input
                                    v-model="aiWordCount"
                                    type="number"
                                    min="300"
                                    max="2000"
                                    step="50"
                                    placeholder="700"
                                    :disabled="generatingWithAI"
                                />
                                <p class="text-xs text-emerald-600 mt-1">Recommandé: 700-800 mots</p>
                            </div>
                            <div class="flex items-end">
                                <div class="grid grid-cols-2 gap-2 w-full">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="aiWordCount = 500"
                                        :disabled="generatingWithAI"
                                    >
                                        📄 Court (500)
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="aiWordCount = 700"
                                        :disabled="generatingWithAI"
                                    >
                                        📖 Moyen (700)
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="aiWordCount = 1000"
                                        :disabled="generatingWithAI"
                                    >
                                        📚 Long (1000)
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="aiWordCount = 1500"
                                        :disabled="generatingWithAI"
                                    >
                                        📜 Détaillé (1500)
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <!-- Bouton de génération -->
                        <div class="flex items-center gap-3">
                            <Button
                                type="button"
                                @click="generateMultiLanguageArticle"
                                :disabled="!aiPrompt.trim() || selectedGenerationLanguages.length === 0 || generatingWithAI"
                                class="flex-1"
                            >
                                {{ generatingWithAI ? '🔄 Génération en cours...' : `🪄 Générer en ${selectedGenerationLanguages.length} langue(s)` }}
                            </Button>
                        </div>

                        <div v-if="selectedGenerationLanguages.length > 0 && !generatingWithAI" class="text-xs text-emerald-600">
                            Génération dans : {{ selectedGenerationLanguages.map((lang) => getLanguageName(lang)).join(', ') }}
                        </div>
                    </div>

                    <p class="text-xs text-emerald-600">
                        L'IA créera des articles complets avec titre, contenu structuré, méta-données SEO et suggestions de catégories.
                    </p>
                </div>
            </div>

            <!-- Section Génération Batch IA (50% moins cher!) -->
            <div class="space-y-4 rounded-lg border border-purple-200 bg-purple-50 p-4">
                <h3 class="text-lg font-semibold text-purple-900">🚀 Génération en Batch (70% moins cher!)</h3>
                <p class="text-sm text-purple-700">
                    💰 Mode batch avec GPT-4o-mini : 70% moins cher que la génération normale, traitement en 2-6h
                </p>

                <div class="space-y-3">
                    <div v-if="selectedSiteValues.length === 0" class="rounded-md border border-orange-200 bg-orange-50 p-3 text-sm text-orange-800">
                        ⚠️ Sélectionnez d'abord un site pour utiliser le mode batch
                    </div>

                    <div v-else class="space-y-3">
                        <!-- Liste des prompts batch -->
                        <div>
                            <Label class="mb-2 block text-sm font-medium text-purple-800">
                                Articles à générer en batch (max 50)
                            </Label>
                            
                            <div class="space-y-2">
                                <div 
                                    v-for="(prompt, index) in batchPrompts" 
                                    :key="index"
                                    class="flex items-center gap-2 p-2 bg-white rounded border"
                                >
                                    <Input
                                        v-model="prompt.text"
                                        placeholder="Ex: Guide complet du jardinage urbain..."
                                        class="flex-1"
                                    />
                                    <Select v-model="prompt.language">
                                        <SelectTrigger class="w-32">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="lang in siteLanguages" :key="lang.code" :value="lang.code">
                                                <span class="flex items-center gap-1">
                                                    <span class="text-sm">{{ getLanguageFlag(lang.code) }}</span>
                                                    <span class="text-xs">{{ lang.name }}</span>
                                                </span>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="removeBatchPrompt(index)"
                                        class="text-red-600 hover:text-red-700"
                                    >
                                        ✕
                                    </Button>
                                </div>
                                
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="addBatchPrompt"
                                    :disabled="batchPrompts.length >= 50"
                                    class="w-full"
                                >
                                    + Ajouter un article ({{ batchPrompts.length }}/50)
                                </Button>
                            </div>
                        </div>

                        <!-- Estimation des coûts -->
                        <div v-if="batchPrompts.length > 0" class="rounded-md bg-purple-100 p-3">
                            <div class="text-sm space-y-1">
                                <div class="flex justify-between">
                                    <span>Articles à générer:</span>
                                    <span class="font-medium">{{ validBatchPrompts.length }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Coût estimé (batch -70%):</span>
                                    <span class="font-medium text-green-600">${{ (validBatchPrompts.length * 0.00225).toFixed(5) }}</span>
                                </div>
                                <div class="flex justify-between text-xs text-purple-600">
                                    <span>Coût normal (pour comparaison):</span>
                                    <span class="line-through">${{ (validBatchPrompts.length * 0.0075).toFixed(4) }}</span>
                                </div>
                                <div class="text-xs text-purple-600 mt-2">
                                    ⏱️ Temps de traitement estimé: 2-6 heures
                                </div>
                            </div>
                        </div>

                        <!-- Bouton de lancement batch -->
                        <Button
                            type="button"
                            @click="createBatch"
                            :disabled="validBatchPrompts.length === 0 || creatingBatch"
                            class="w-full"
                        >
                            {{ creatingBatch ? '🔄 Création du batch...' : `🚀 Lancer le batch (${validBatchPrompts.length} articles)` }}
                        </Button>

                        <!-- Batches en cours -->
                        <div v-if="userBatches.length > 0" class="mt-4">
                            <Label class="mb-2 block text-sm font-medium text-purple-800">Batches en cours/terminés</Label>
                            <div class="space-y-2">
                                <div 
                                    v-for="batch in userBatches.slice(0, 3)" 
                                    :key="batch.id"
                                    class="flex items-center justify-between p-2 bg-white rounded border text-sm"
                                >
                                    <div>
                                        <span class="font-medium">{{ batch.total_requests }} articles</span>
                                        <span class="mx-2">•</span>
                                        <span :class="{
                                            'text-green-600': batch.status === 'completed',
                                            'text-blue-600': batch.status === 'submitted',
                                            'text-orange-600': batch.status === 'pending',
                                            'text-red-600': batch.status === 'failed'
                                        }">
                                            {{ getBatchStatusText(batch.status) }}
                                        </span>
                                        <div v-if="batch.progress_percentage > 0" class="w-full bg-gray-200 rounded-full h-1 mt-1">
                                            <div 
                                                class="bg-purple-600 h-1 rounded-full transition-all duration-300"
                                                :style="{ width: batch.progress_percentage + '%' }"
                                            ></div>
                                        </div>
                                    </div>
                                    <div class="flex gap-1">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            @click="checkBatchStatus(batch.id)"
                                        >
                                            🔄
                                        </Button>
                                        <Button
                                            v-if="batch.status === 'completed'"
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            @click="loadBatchResults(batch.id)"
                                        >
                                            📥 Charger
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section Traduction Simplifiée -->
            <div v-if="hasContent" class="space-y-4 rounded-lg border border-purple-200 bg-purple-50 p-4">
                <h3 class="text-lg font-semibold text-purple-900">🌍 Traduction automatique</h3>

                <div class="space-y-3">
                    <div v-if="selectedSiteValues.length === 0" class="text-sm text-purple-600 italic">
                        Sélectionnez d'abord un site pour voir les langues disponibles
                    </div>

                    <div v-else class="space-y-3">
                        <!-- Sélection des langues de traduction -->
                        <div>
                            <Label class="mb-2 block text-sm font-medium text-purple-800">Langues de traduction</Label>
                            <MultiSelect
                                v-model="selectedTranslationLanguages"
                                :options="siteLanguageOptionsWithFlags"
                                placeholder="Choisir les langues de traduction..."
                                :disabled="translating || siteLanguages.length === 0"
                                class="w-full"
                            />
                            <div v-if="siteLanguages.length === 0" class="mt-1 text-xs text-purple-600">Aucune langue configurée pour ce site</div>
                        </div>

                        <!-- Bouton de traduction -->
                        <div class="flex items-center gap-3">
                            <Button
                                type="button"
                                @click="translateToMultipleLanguages"
                                :disabled="translating || selectedTranslationLanguages.length === 0"
                                class="flex-1"
                            >
                                {{ translating ? '🔄 Traduction...' : `🌍 Traduire vers ${selectedTranslationLanguages.length} langue(s)` }}
                            </Button>
                        </div>

                        <div v-if="selectedTranslationLanguages.length > 0" class="text-xs text-purple-600">
                            Traduction vers : {{ selectedTranslationLanguages.map((lang) => getLanguageName(lang)).join(', ') }}
                        </div>
                    </div>

                    <!-- Résultats de traduction -->
                    <div v-if="translationResults.length > 0" class="mt-3">
                        <Label class="mb-2 block text-sm font-medium text-purple-800">Traductions créées :</Label>
                        <div class="space-y-1">
                            <div v-for="result in translationResults" :key="result.language" class="flex items-center gap-2 text-xs">
                                <span class="text-green-600">✓</span>
                                <span>{{ getLanguageName(result.language) }}</span>
                                <Button size="sm" variant="ghost" @click="loadTranslation(result)">📝 Charger</Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-6">
                <div class="space-y-4">
                    <div class="space-y-2">
                        <Label for="title">Title</Label>
                        <Input id="title" v-model="form.title" type="text" required :disabled="form.processing" />
                        <InputError :message="form.errors.title" />
                    </div>

                    <div class="space-y-2">
                        <Label for="excerpt">Excerpt</Label>
                        <Textarea id="excerpt" v-model="form.excerpt" :disabled="form.processing" rows="3" />
                        <InputError :message="form.errors.excerpt" />
                    </div>

                    <div class="space-y-2">
                        <Label for="cover_image">Cover Image</Label>
                        <div class="space-y-3">
                            <!-- Prévisualisation de l'image actuelle -->
                            <div v-if="currentCoverImageUrl" class="relative">
                                <img :src="currentCoverImageUrl" alt="Cover image preview" class="h-32 w-full rounded-lg border object-cover" />
                                <Button type="button" variant="destructive" size="sm" class="absolute top-2 right-2" @click="removeCoverImage">
                                    ✕
                                </Button>
                            </div>

                            <!-- Upload d'image -->
                            <div class="flex items-center gap-3">
                                <input ref="coverImageInput" type="file" accept="image/*" class="hidden" @change="handleCoverImageUpload" />
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="coverImageInput?.click()"
                                    :disabled="form.processing || uploadingCoverImage"
                                >
                                    {{ uploadingCoverImage ? 'Uploading...' : 'Choose Image' }}
                                </Button>
                                <span class="text-sm text-gray-500">Max 2MB, JPG/PNG</span>
                            </div>
                        </div>
                        <InputError :message="form.errors.cover_image" />
                    </div>

                    <div class="space-y-2">
                        <Label for="status">Status</Label>
                        <Select v-model="form.status" :disabled="form.processing">
                            <SelectTrigger>
                                <SelectValue placeholder="Select status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="draft">Draft</SelectItem>
                                <SelectItem value="published">Published</SelectItem>
                                <SelectItem value="scheduled">Scheduled</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.status" />
                    </div>

                    <div v-if="form.status === 'scheduled'" class="space-y-2">
                        <Label for="scheduled_at">Schedule Date</Label>
                        <Input id="scheduled_at" v-model="form.scheduled_at" type="datetime-local" :disabled="form.processing" />
                        <InputError :message="form.errors.scheduled_at" />
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="space-y-2">
                        <Label for="meta_title">Meta Title</Label>
                        <Input id="meta_title" v-model="form.meta_title" type="text" :disabled="form.processing" />
                        <InputError :message="form.errors.meta_title" />
                    </div>

                    <div class="space-y-2">
                        <Label for="meta_description">Meta Description</Label>
                        <Textarea id="meta_description" v-model="form.meta_description" :disabled="form.processing" rows="3" />
                        <InputError :message="form.errors.meta_description" />
                    </div>

                    <div class="space-y-2">
                        <Label for="meta_keywords">Meta Keywords</Label>
                        <Input
                            id="meta_keywords"
                            v-model="form.meta_keywords"
                            type="text"
                            :disabled="form.processing"
                            placeholder="Separate keywords with commas"
                        />
                        <InputError :message="form.errors.meta_keywords" />
                    </div>

                    <div class="space-y-2">
                        <Label for="canonical_url">Canonical URL</Label>
                        <Input id="canonical_url" v-model="form.canonical_url" type="url" :disabled="form.processing" />
                        <InputError :message="form.errors.canonical_url" />
                    </div>

                    <div class="space-y-2">
                        <Label for="author_name">Author Name</Label>
                        <Input id="author_name" v-model="form.author_name" type="text" :disabled="form.processing" />
                        <InputError :message="form.errors.author_name" />
                    </div>

                    <div class="space-y-2">
                        <Label for="author_bio">Author Bio</Label>
                        <Textarea id="author_bio" v-model="form.author_bio" :disabled="form.processing" rows="3" />
                        <InputError :message="form.errors.author_bio" />
                    </div>
                </div>
            </div>

            <div class="space-y-2">
                <Label>Content</Label>
                <!-- Note informative sur les couleurs et la hiérarchie -->
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-3 mb-3">
                    <div class="flex items-start gap-2">
                        <span class="text-blue-600 text-sm">ℹ️</span>
                        <div class="text-sm text-blue-800">
                            <p class="font-medium">Hiérarchie visuelle des titres</p>
                            <p class="text-blue-700 mt-1">
                                Les badges colorés (H1, H2, H3...) et les tailles différentes ne s'enregistrent pas dans l'article final. 
                                Ils sont uniquement là pour vous aider à visualiser la hiérarchie pendant l'édition.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- **NOUVEAU: Compteur de backlinks en temps réel** -->
                <div v-if="selectedSiteValues.length > 0" class="rounded-lg border border-orange-200 bg-orange-50 p-3 mb-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="text-orange-600 text-sm">🔗</span>
                            <div class="text-sm">
                                <p class="font-medium text-orange-800">Système de backlinks</p>
                                <p class="text-orange-700">
                                    {{ backlinkCount }} lien(s) total - {{ externalBacklinkCount }} externe(s) = -{{ externalBacklinkCount }} point(s)
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="text-right">
                                <p class="text-xs text-orange-600">Points disponibles</p>
                                <p class="font-bold text-orange-800">{{ userPoints }}</p>
                            </div>
                            <div class="h-8 w-px bg-orange-300"></div>
                            <div class="text-right">
                                <p class="text-xs text-orange-600">Liens externes max</p>
                                <p class="font-bold text-orange-800">2</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Barre de progression des liens externes -->
                    <div class="mt-2">
                        <div class="flex items-center justify-between text-xs text-orange-600 mb-1">
                            <span>Liens externes utilisés</span>
                            <span>{{ externalBacklinkCount }}/2</span>
                        </div>
                        <div class="w-full bg-orange-200 rounded-full h-2">
                            <div 
                                class="h-2 rounded-full transition-all duration-300"
                                :class="{
                                    'bg-orange-500': externalBacklinkCount <= 2,
                                    'bg-red-500': externalBacklinkCount > 2
                                }"
                                :style="{ width: Math.min((externalBacklinkCount / 2) * 100, 100) + '%' }"
                            ></div>
                        </div>
                        <p v-if="externalBacklinkCount > 2" class="text-xs text-red-600 mt-1">
                            ⚠️ Limite dépassée ! Réduisez le nombre de liens externes.
                        </p>
                        <p v-else-if="externalBacklinkCount > userPoints" class="text-xs text-red-600 mt-1">
                            ⚠️ Pas assez de points ! Vous avez {{ userPoints }} point(s) disponible(s).
                        </p>
                    </div>
                </div>

                <div class="rounded-lg border">
                    <EditorJS
                        ref="editorJSComponent"
                        :initial-content="form.content"
                        :site-colors="siteColors"
                        :ai-word-count="aiWordCount"
                        @update:content="handleContentUpdate"
                        @update:word-count="handleWordCountUpdate"
                        :disabled="form.processing"
                        class="min-h-[400px]"
                    />
                </div>
                <InputError :message="form.errors.content" />
            </div>

            <div class="flex justify-end space-x-2">
                <Button type="submit" :disabled="form.processing">
                    {{ isEditing ? 'Update' : 'Create' }}
                </Button>
            </div>
        </form>

        <!-- Toast notification system -->
        <div
            v-if="notification.show"
            class="animate-in fade-in slide-in-from-bottom-5 fixed right-4 bottom-4 z-[9999] flex items-center gap-2 rounded-lg border bg-white p-4 shadow-lg transition-opacity"
            :class="notification.type === 'success' ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50'"
        >
            <div
                class="flex h-8 w-8 items-center justify-center rounded-full"
                :class="notification.type === 'success' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'"
            >
                <CheckIcon v-if="notification.type === 'success'" class="h-5 w-5" />
                <XIcon v-else class="h-5 w-5" />
            </div>
            <div>
                <p class="font-medium" :class="notification.type === 'success' ? 'text-green-800' : 'text-red-800'">
                    {{ notification.title }}
                </p>
                <p class="text-sm" :class="notification.type === 'success' ? 'text-green-700' : 'text-red-700'">
                    {{ notification.message }}
                </p>
            </div>
            <Button variant="ghost" size="icon" class="ml-auto h-6 w-6 p-0" @click="notification.show = false">
                <XIcon class="h-4 w-4" />
            </Button>
        </div>
    </div>
</template>

<script setup lang="ts">
import EditorJS from '@/components/Editor/EditorJS.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import MultiSelect from '@/components/ui/MultiSelect.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useEditorJSConverter } from '@/composables/useEditorJSConverter';
import { useRoutes } from '@/composables/useRoutes';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { CheckIcon, XIcon } from 'lucide-vue-next';
import { computed, reactive, ref, watch, nextTick, onMounted } from 'vue';

interface Category {
    id: number;
    name: string;
    language_code?: string;
}

interface Article {
    id?: number;
    title: string;
    excerpt: string;
    content: string;
    content_html: string;
    cover_image?: string;
    status: string;
    scheduled_at?: string;
    meta_title: string;
    meta_description: string;
    meta_keywords: string;
    canonical_url: string;
    author_name: string;
    author_bio: string;
    site_id?: number;
    categories?: Category[];
    language_code?: string;
}

interface Language {
    id: number;
    code: string;
    name: string;
    flag: string;
    flag_url?: string;
}

const props = defineProps<{
    article?: Article;
    categories: Category[];
    sites: { id: number; name: string }[];
}>();

const emit = defineEmits(['close']);

const { articleRoutes, siteRoutes } = useRoutes();
const { convertForWebhook, convertHTMLToEditorJS } = useEditorJSConverter();

// Déclarations des refs AVANT les watchers
const siteColors = ref({
    primary_color: '',
    secondary_color: '',
    accent_color: '',
});

const selectedSiteValues = ref<string[]>([]);
const selectedCategoryValues = ref<string[]>([]);
const availableCategories = ref<Category[]>([]);
const uploadingCoverImage = ref(false);
const currentCoverImageUrl = ref<string>('');
const coverImageInput = ref<HTMLInputElement>();

// Variables pour l'IA et multi-langues
const aiPrompt = ref<string>('');
const aiWordCount = ref<number>(700);
const wordCount = ref<number>(0);
const generatingWithAI = ref<boolean>(false);
const translating = ref<boolean>(false);

// **NOUVEAU: Compteur de backlinks en temps réel**
const backlinkCount = ref<number>(0);
const externalBacklinkCount = ref<number>(0);
const userPoints = ref<number>(20);

// Variables pour la barre de progression
const generationProgress = ref<number>(0);
const currentGeneratingLanguage = ref<string>('');

// Référence vers le composant EditorJS
const editorJSComponent = ref<any>(null);

// Nouvelles variables pour la génération multi-langues
const selectedGenerationLanguages = ref<string[]>([]);
const generationResults = ref<any[]>([]);

// Variables pour le système batch
const batchPrompts = ref<Array<{text: string, language: string}>>([{text: '', language: 'fr'}]);
const creatingBatch = ref<boolean>(false);
const userBatches = ref<any[]>([]);

// Variables pour la gestion multi-langues en temps réel
const currentLanguage = ref<string>('fr');
const articleVersions = ref<Map<string, any>>(new Map());

// Notification system (comme dans SiteList.vue)
const notification = reactive({
    show: false,
    type: 'success' as 'success' | 'error',
    title: '',
    message: '',
    timeout: null as number | null,
});

const form = useForm({
    title: '',
    excerpt: '',
    content: '',
    content_html: '',
    cover_image: null as File | null,
    status: 'draft',
    scheduled_at: undefined as string | undefined,
    categories: [] as number[],
    meta_title: '',
    meta_description: '',
    meta_keywords: '',
    canonical_url: '',
    author_name: '',
    author_bio: '',
    site_id: '' as string,
});

// Computed properties
const categoryOptions = computed(() => {
    return availableCategories.value.map((c) => ({
        value: c.id.toString(),
        label: c.name,
    }));
});

const siteOptions = computed(() => {
    if (!Array.isArray(props.sites)) {
        return [];
    }
    const options = props.sites
        .filter((s) => s.id !== undefined && s.id !== null)
        .map((s) => ({
            value: String(s.id),
            label: s.name,
        }));
    return options;
});

const hasContent = computed(() => {
    return !!(form.title || form.excerpt || form.content);
});

// Computed properties pour les nouvelles fonctionnalités avec les bons drapeaux
const siteLanguageOptions = computed(() => {
    return siteLanguages.value.map((lang: Language) => ({
        value: lang.code,
        label: `${getLanguageFlag(lang.code)} ${lang.name}`,
    }));
});

// Version avec affichage correct des drapeaux pour MultiSelect
const siteLanguageOptionsWithFlags = computed(() => {
    return siteLanguages.value.map((lang: Language) => ({
        value: lang.code,
        label: `${getLanguageFlag(lang.code)} ${lang.name}`,
        display: `${getLanguageFlag(lang.code)} ${lang.name}`,
    }));
});

// Langues disponibles pour la sélection dans le header avec les bons drapeaux
const availableLanguagesForSelection = computed(() => {
    const siteLangs = siteLanguages.value.map((lang: Language) => ({
        code: lang.code,
        name: lang.name,
        flag: lang.flag || getLanguageFlag(lang.code),
    }));

    // Si on a des versions d'articles, inclure toutes les langues qui ont du contenu
    const versionsLangs = Array.from(articleVersions.value.keys()).map((code) => {
        const siteLanguage = siteLanguages.value.find(l => l.code === code);
        return {
        code,
        name: getLanguageName(code),
            flag: siteLanguage?.flag || getLanguageFlag(code),
        };
    });

    // Combiner et dédupliquer
    const combined = [...siteLangs, ...versionsLangs];
    const unique = combined.filter((lang, index, self) => index === self.findIndex((l) => l.code === lang.code));

    return unique;
});

// Fonction pour obtenir le drapeau d'une langue
const getLanguageFlag = (code: string): string => {
    const flags: Record<string, string> = {
        fr: '🇫🇷',
        en: '🇬🇧',
        es: '🇪🇸',
        de: '🇩🇪',
        it: '🇮🇹',
        pt: '🇵🇹',
        nl: '🇳🇱',
        ru: '🇷🇺',
        ja: '🇯🇵',
        zh: '🇨🇳',
    };
    return flags[code] || '🌐';
};

// Function pour sauvegarder la version actuelle avant de changer
const saveCurrentVersion = () => {
    if (currentLanguage.value && (form.title || form.excerpt || form.content)) {
        articleVersions.value.set(currentLanguage.value, {
            title: form.title,
            excerpt: form.excerpt,
            content: form.content,
            content_html: form.content_html,
            meta_title: form.meta_title,
            meta_description: form.meta_description,
            meta_keywords: form.meta_keywords,
            canonical_url: form.canonical_url,
            author_name: form.author_name,
            author_bio: form.author_bio,
            categories: [...selectedCategoryValues.value],
        });

        console.log('💾 Saved version for language:', currentLanguage.value);
    }
};

// Fonction pour charger une version linguistique
const loadLanguageVersion = async (languageCode: string) => {
    const version = articleVersions.value.get(languageCode);

    console.log('📄 Loading language version for:', languageCode, version ? 'found' : 'not found');

    if (version) {
        // Charger les données de cette version
        form.title = version.title || '';
        form.excerpt = version.excerpt || '';
        form.meta_title = version.meta_title || '';
        form.meta_description = version.meta_description || '';
        form.meta_keywords = version.meta_keywords || '';
        form.canonical_url = version.canonical_url || '';
        form.author_name = version.author_name || '';
        form.author_bio = version.author_bio || '';
        selectedCategoryValues.value = version.categories || [];

        // Charger le contenu et déclencher la mise à jour de l'éditeur
        form.content = version.content || '';
        form.content_html = version.content_html || '';

        // Forcer la mise à jour de l'éditeur EditorJS
        if (version.content) {
            console.log('📝 Updating EditorJS with loaded content');
            await updateEditorContent(version.content);
        }

        console.log('📄 Loaded version for language:', languageCode);
    } else {
        // Nouvelle langue, vider les champs
        form.title = '';
        form.excerpt = '';
        form.meta_title = '';
        form.meta_description = '';
        form.meta_keywords = '';
        form.canonical_url = '';
        form.author_name = '';
        form.author_bio = '';
        selectedCategoryValues.value = [];

        // Vider le contenu
        form.content = '';
        form.content_html = '';

        // Nettoyer l'éditeur
        await updateEditorContent('');

        console.log('🆕 New language version:', languageCode);
    }
};

// Function pour changer de langue et recharger les catégories
const switchLanguage = async (value: any) => {
    const newLanguage = value as string;
    if (!newLanguage || newLanguage === currentLanguage.value) return;

    // Sauvegarder la version actuelle avant de changer
    saveCurrentVersion();

    // Changer la langue actuelle
    currentLanguage.value = newLanguage;

    // Charger la version de la nouvelle langue
    await loadLanguageVersion(newLanguage);

    // Recharger les catégories pour cette langue si un site est sélectionné
    if (selectedSiteValues.value.length > 0) {
        await fetchSiteCategories(selectedSiteValues.value[0], newLanguage);
        selectedCategoryValues.value = []; // Reset categories when changing language
    }

    showNotification('success', 'Langue changée', `Basculé vers ${getLanguageName(newLanguage)}`);
};

// Functions
const fetchSiteColors = async (value: any) => {
    const siteId = value ? String(value) : '';
    if (!siteId) {
        siteColors.value = { primary_color: '', secondary_color: '', accent_color: '' };
        return;
    }
    try {
        const response = await axios.get(siteRoutes.show(siteId) + '/colors');
        siteColors.value = response.data;
    } catch (error) {
        console.error('Error fetching site colors:', error);
        siteColors.value = { primary_color: '', secondary_color: '', accent_color: '' };
    }
};

const fetchSiteCategories = async (siteId: any, languageCode?: string) => {
    console.log('Fetching categories for site:', siteId, 'and language:', languageCode);

    if (!siteId) {
        console.log('No siteId provided, clearing categories');
        availableCategories.value = [];
        return;
    }

    try {
        let url = siteRoutes.show(siteId) + '/categories';
        
        // Ajouter le paramètre de langue si spécifié
        if (languageCode) {
            url += `?language=${languageCode}`;
        }
        
        console.log('Making request to:', url);
        const response = await axios.get(url);
        console.log('Categories response:', response.data);
        availableCategories.value = response.data;
    } catch (error: any) {
        console.error('Error fetching site categories:', error);
        if (error.response) {
            console.error('Error response:', error.response.data);
            console.error('Error status:', error.response.status);
        }
        availableCategories.value = [];
    }
};

// Handler pour la mise à jour du compteur de mots
const handleWordCountUpdate = (count: number) => {
    wordCount.value = count;
};

// Handler pour la mise à jour du contenu
const handleContentUpdate = (content: string) => {
    console.log('🔥 handleContentUpdate called with:', content ? content.substring(0, 200) + '...' : 'empty content');

    form.content = content;

    // Convertir immédiatement en HTML
    if (content) {
        try {
            console.log('🔄 Attempting to parse content...');
            const editorJSData = typeof content === 'string' ? JSON.parse(content) : content;
            console.log('✅ Parsed EditorJS data:', JSON.stringify(editorJSData, null, 2));

            // Vérifier la structure des blocs
            if (editorJSData.blocks) {
                console.log('📦 Blocks found:', editorJSData.blocks.length);
                editorJSData.blocks.forEach((block: any, index: number) => {
                    console.log(`Block ${index}:`, {
                        type: block.type,
                        data: block.data,
                    });
                });
            }

            console.log('🔄 Converting to HTML...');
            const htmlResult = convertForWebhook(editorJSData);
            console.log('✅ HTML conversion result:', htmlResult);

            form.content_html = htmlResult;
            console.log('✅ form.content_html updated:', form.content_html.substring(0, 100) + '...');

            // **NOUVEAU: Analyser les backlinks dans le contenu**
            analyzeBacklinks(htmlResult);

        } catch (error) {
            console.error('❌ Erreur lors de la conversion du contenu:', error);
            form.content_html = '';
        }
    } else {
        console.log('⚠️ Content is empty, clearing content_html');
        form.content_html = '';
        // Reset des compteurs
        backlinkCount.value = 0;
        externalBacklinkCount.value = 0;
    }
};

// **NOUVEAU: Fonction pour analyser les backlinks dans le contenu HTML**
const analyzeBacklinks = (html: string) => {
    if (!html) {
        backlinkCount.value = 0;
        externalBacklinkCount.value = 0;
        return;
    }

    // Créer un élément DOM temporaire pour analyser le HTML
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = html;
    
    // Compter tous les liens
    const allLinks = tempDiv.querySelectorAll('a[href]');
    backlinkCount.value = allLinks.length;
    
    // Compter les liens externes (qui commencent par http ou qui ont data-external="true")
    externalBacklinkCount.value = Array.from(allLinks).filter(link => {
        const href = link.getAttribute('href') || '';
        const isExternal = link.getAttribute('data-external') === 'true';
        const isHttpLink = href.startsWith('http://') || href.startsWith('https://');
        const isNotSameDomain = isHttpLink && !href.includes(window.location.hostname);
        
        return isExternal || isNotSameDomain;
    }).length;

    console.log('🔗 Backlinks analysis:', {
        total: backlinkCount.value,
        external: externalBacklinkCount.value,
        internal: backlinkCount.value - externalBacklinkCount.value
    });
};

// **NOUVEAU: Charger les points de l'utilisateur**
const loadUserPoints = async () => {
    if (!selectedSiteValues.value.length) return;

    try {
        const response = await axios.get('/api/user/backlink-points');
        userPoints.value = response.data.available_points || 0;
        console.log('💰 User points loaded:', userPoints.value);
    } catch (error) {
        console.error('❌ Error loading user points:', error);
        userPoints.value = 0;
    }
};

const handleCoverImageUpload = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    if (!file) return;

    // Vérifier la taille du fichier (2MB max)
    if (file.size > 2 * 1024 * 1024) {
        alert('File size must be less than 2MB');
        return;
    }

    uploadingCoverImage.value = true;

    try {
        // Créer une prévisualisation immédiate
        const reader = new FileReader();
        reader.onload = (e) => {
            currentCoverImageUrl.value = e.target?.result as string;
        };
        reader.readAsDataURL(file);

        // Stocker le fichier dans le form
        form.cover_image = file;
    } catch (error) {
        console.error('Error uploading cover image:', error);
        alert('Error uploading image');
    } finally {
        uploadingCoverImage.value = false;
    }
};

const removeCoverImage = () => {
    currentCoverImageUrl.value = '';
    form.cover_image = null;
    if (coverImageInput.value) {
        coverImageInput.value.value = '';
    }
};

// Nouvelles fonctions pour la traduction multi-langues
const fetchSiteLanguages = async (siteId: any) => {
    if (!siteId) {
        siteLanguages.value = [];
        return;
    }

    try {
        console.log('🌍 Fetching languages for site:', siteId);
        const response = await axios.get(siteRoutes.show(siteId) + '/languages');
        
        // Mapper les langues avec les drapeaux du site
        siteLanguages.value = response.data.map((lang: any) => ({
            id: lang.id,
            code: lang.code || lang.language_code,
            name: lang.name,
            flag: lang.flag || getLanguageFlag(lang.code || lang.language_code),
            flag_url: lang.flag_url,
        }));
        
        console.log('✅ Site languages fetched:', siteLanguages.value);
    } catch (error: any) {
        console.error('❌ Error fetching site languages:', error);
        siteLanguages.value = [];
    }
};

const translateToMultipleLanguages = async () => {
    if (!hasContent.value || selectedTranslationLanguages.value.length === 0) return;

    translating.value = true;
    translationResults.value = [];

    try {
        for (const targetLanguage of selectedTranslationLanguages.value) {
            console.log('🌍 Translating to:', targetLanguage);

            const response = await axios.post(
                '/articles/translate',
                {
                    title: form.title,
                    excerpt: form.excerpt,
                    content_html: form.content_html,
                    meta_title: form.meta_title,
                    meta_description: form.meta_description,
                    meta_keywords: form.meta_keywords,
                    author_bio: form.author_bio,
                    target_language: targetLanguage,
                    source_language: 'fr',
                },
                {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                },
            );

            const translatedData = response.data;
            translationResults.value.push({
                language: targetLanguage,
                data: translatedData,
            });
        }

        showNotification('success', 'Traduction réussie', `Articles traduits en ${selectedTranslationLanguages.value.length} langue(s)`);

        console.log('✅ All translations completed:', translationResults.value);
    } catch (error: any) {
        console.error('❌ Erreur lors de la traduction multiple:', error);

        let errorMessage = 'Erreur lors de la traduction';
        if (error.response?.data?.message) {
            errorMessage = error.response.data.message;
        } else if (error.message) {
            errorMessage = error.message;
        }

        showNotification('error', 'Erreur de traduction', errorMessage);
    } finally {
        translating.value = false;
    }
};

const loadTranslation = (result: any) => {
    const translatedData = result.data;

    // Remplacer le contenu par la traduction
    form.title = translatedData.title || form.title;
    form.excerpt = translatedData.excerpt || form.excerpt;
    form.meta_title = translatedData.meta_title || form.meta_title;
    form.meta_description = translatedData.meta_description || form.meta_description;
    form.meta_keywords = translatedData.meta_keywords || form.meta_keywords;
    form.author_bio = translatedData.author_bio || form.author_bio;

    // Convertir le HTML traduit en EditorJS
    if (translatedData.content_html) {
        try {
            console.log('🔄 Converting translated HTML to EditorJS');
            const editorJSData = convertHTMLToEditorJS(translatedData.content_html);
            form.content = JSON.stringify(editorJSData);
            form.content_html = translatedData.content_html;
            console.log('✅ Translated HTML successfully converted to EditorJS');
        } catch (error) {
            console.error('❌ Error converting translated HTML to EditorJS:', error);
            // Fallback vers le contenu HTML brut
            form.content_html = translatedData.content_html;
        }
    } else if (translatedData.content) {
        // Compatibilité avec l'ancien format EditorJS
        form.content = translatedData.content;
        try {
            const editorJSData = typeof translatedData.content === 'string' ? JSON.parse(translatedData.content) : translatedData.content;
            form.content_html = convertForWebhook(editorJSData);
        } catch (error) {
            console.error('Error converting translated EditorJS content:', error);
        }
    }

    console.log('✅ Translation loaded for:', getLanguageName(result.language));

    // Log pour débugger quels champs ont été remplis
    console.log('📋 Form fields after translation loading:', {
        title: form.title,
        excerpt: form.excerpt,
        meta_title: form.meta_title,
        meta_description: form.meta_description,
        meta_keywords: form.meta_keywords,
        author_name: form.author_name,
        author_bio: form.author_bio,
        categories: selectedCategoryValues.value,
    });
};

// Watchers APRÈS les déclarations
watch(
    () => form.processing,
    (newValue) => {
        console.log('Form processing state:', newValue);
    },
    { immediate: true },
);

const isEditing = computed(() => !!props.article?.id);

watch(
    () => props.article,
    async (newArticle) => {
        if (newArticle && 'id' in newArticle) {
            form.title = newArticle.title;
            form.excerpt = newArticle.excerpt;

            // Gestion du contenu : priorité à EditorJS, sinon conversion depuis HTML
            if (newArticle.content) {
                // Si on a du contenu EditorJS, l'utiliser directement
                form.content = newArticle.content;
            } else if (newArticle.content_html) {
                // Si on a seulement du HTML (article reçu via webhook), le convertir
                const editorJSData = convertHTMLToEditorJS(newArticle.content_html);
                form.content = JSON.stringify(editorJSData);
            } else {
                form.content = '';
            }

            form.content_html = newArticle.content_html || '';

            // Afficher l'image de couverture existante
            if (newArticle.cover_image) {
                currentCoverImageUrl.value = newArticle.cover_image;
            }
            form.meta_title = newArticle.meta_title;
            form.meta_description = newArticle.meta_description;
            form.meta_keywords = newArticle.meta_keywords;
            form.canonical_url = newArticle.canonical_url;
            form.status = newArticle.status;
            form.scheduled_at = newArticle.scheduled_at || undefined;
            form.author_name = newArticle.author_name;
            form.author_bio = newArticle.author_bio;
            form.categories = newArticle.categories?.map((c) => c.id) || [];

            // Si l'article a un site_id, le précharger
            if (newArticle.site_id) {
                const siteOption = siteOptions.value.find((s) => s.value === String(newArticle.site_id));
                if (siteOption) {
                    selectedSiteValues.value = [String(newArticle.site_id)];
                    form.site_id = String(newArticle.site_id);
                    await Promise.all([fetchSiteColors(newArticle.site_id), fetchSiteCategories(newArticle.site_id)]);

                    // Précharger la catégorie sélectionnée si il y en a une
                    if (newArticle.categories && newArticle.categories.length > 0) {
                        selectedCategoryValues.value = newArticle.categories.map((cat) => cat.id.toString());
                    }
                }
            }
        } else {
            form.reset();
            selectedSiteValues.value = [];
            selectedCategoryValues.value = [];
            availableCategories.value = [];
            siteColors.value = { primary_color: '', secondary_color: '', accent_color: '' };
        }
    },
    { immediate: true },
);

const submit = () => {
    console.log('🚀 Submit called');
    console.log('📝 form.content:', form.content ? form.content.substring(0, 200) + '...' : 'empty');
    console.log('🌐 form.content_html BEFORE conversion:', form.content_html ? form.content_html.substring(0, 200) + '...' : 'empty');

    // Convertir le contenu EditorJS en HTML avant l'envoi
    if (form.content) {
        try {
            const editorJSData = typeof form.content === 'string' ? JSON.parse(form.content) : form.content;
            form.content_html = convertForWebhook(editorJSData);
            console.log('🌐 form.content_html AFTER conversion:', form.content_html ? form.content_html.substring(0, 200) + '...' : 'empty');
        } catch (error) {
            console.error('❌ Erreur lors de la conversion du contenu dans submit:', error);
            form.content_html = '';
        }
    }

    console.log('📤 Final form data being sent:', {
        title: form.title,
        content: form.content ? 'has content' : 'empty',
        content_html: form.content_html ? 'has html' : 'empty',
        site_id: form.site_id,
        cover_image: form.cover_image ? 'has file' : 'no file',
    });

    if (isEditing.value && props.article && props.article.id) {
        form.put(articleRoutes.update(props.article.id), {
            onSuccess: () => emit('close'),
        });
    } else {
        form.post(articleRoutes.store(), {
            onSuccess: () => emit('close'),
        });
    }
};

// Watch pour convertir automatiquement le contenu EditorJS en HTML
watch(
    () => form.content,
    (newContent) => {
        if (newContent) {
            try {
                const editorJSData = typeof newContent === 'string' ? JSON.parse(newContent) : newContent;
                form.content_html = convertForWebhook(editorJSData);
            } catch (error) {
                console.error('Erreur lors de la conversion automatique du contenu:', error);
                form.content_html = '';
            }
        } else {
            form.content_html = '';
        }
    },
    { deep: true },
);

// Watch pour gérer les changements de site et de langue
watch(
    selectedSiteValues,
    async (newSiteValues) => {
        if (newSiteValues.length > 0) {
            const siteId = newSiteValues[0];
            form.site_id = siteId;

            // Reset categories when changing site
            form.categories = [];
            availableCategories.value = [];
            selectedCategoryValues.value = [];

            await Promise.all([
                fetchSiteColors(siteId), 
                fetchSiteLanguages(siteId),
                loadUserPoints() // **NOUVEAU: Charger les points utilisateur**
            ]);

            // Charger les catégories pour la langue actuelle
            if (currentLanguage.value) {
                await fetchSiteCategories(siteId, currentLanguage.value);
            }
        } else {
            form.site_id = '';
            siteColors.value = { primary_color: '', secondary_color: '', accent_color: '' };
            availableCategories.value = [];
            selectedCategoryValues.value = [];
            siteLanguages.value = [];
            userPoints.value = 0; // **NOUVEAU: Reset des points**
        }
    },
    { deep: true },
);

// Watch pour recharger les catégories quand la langue change
watch(
    currentLanguage,
    async (newLanguage) => {
        if (newLanguage && selectedSiteValues.value.length > 0) {
            await fetchSiteCategories(selectedSiteValues.value[0], newLanguage);
            // Reset categories selection when language changes
            selectedCategoryValues.value = [];
        }
    }
);

// Watch pour synchroniser les changements du multiselect avec le form
watch(
    selectedCategoryValues,
    (newCategories) => {
        form.categories = newCategories.map((value) => Number(value));
    },
    { deep: true },
);

// Charger les batches utilisateur au montage
onMounted(() => {
    loadUserBatches();
    // **NOUVEAU: Charger les points au montage si un site est déjà sélectionné**
    if (selectedSiteValues.value.length > 0) {
        loadUserPoints();
    }
});

// Fonction de notification (comme dans SiteList.vue)
function showNotification(type: 'success' | 'error', title: string, message: string) {
    // Clear any existing timeout
    if (notification.timeout) {
        clearTimeout(notification.timeout);
    }

    // Set notification data
    notification.type = type;
    notification.title = title;
    notification.message = message;
    notification.show = true;

    // Auto-hide after 5 seconds
    notification.timeout = setTimeout(() => {
        notification.show = false;
    }, 5000) as unknown as number;
}

// Nouvelle fonction pour génération multi-langues
const generateMultiLanguageArticle = async () => {
    if (!aiPrompt.value.trim() || selectedGenerationLanguages.value.length === 0) {
        showNotification('error', 'Paramètres manquants', 'Veuillez saisir un prompt et sélectionner au moins une langue');
        return;
    }

    console.log('🚀 Starting multi-language generation:', {
        prompt: aiPrompt.value,
        languages: selectedGenerationLanguages.value,
        siteId: form.site_id,
    });

    generatingWithAI.value = true;
    generationResults.value = [];
    generationProgress.value = 0;
    currentGeneratingLanguage.value = '';

    try {
        const totalLanguages = selectedGenerationLanguages.value.length;
        
        // Générer pour chaque langue sélectionnée
        for (let i = 0; i < selectedGenerationLanguages.value.length; i++) {
            const targetLanguage = selectedGenerationLanguages.value[i];
            currentGeneratingLanguage.value = targetLanguage;
            
            // Animation progressive au début de chaque génération
            const startProgress = (i / totalLanguages) * 100;
            const endProgress = ((i + 1) / totalLanguages) * 100;
            
            // Animation progressive plus réaliste
            generationProgress.value = startProgress + 2;
            await new Promise(resolve => setTimeout(resolve, 100));
            
            generationProgress.value = startProgress + 8;
            await new Promise(resolve => setTimeout(resolve, 200));
            
            generationProgress.value = startProgress + 15;
            
            console.log('🤖 Generating article for language:', targetLanguage);

            const requestData = {
                prompt: aiPrompt.value,
                site_id: form.site_id,
                language: targetLanguage,
                word_count: aiWordCount.value,
            };

            console.log('📤 Request data:', requestData);

            // Simuler progression pendant l'appel API de manière plus réaliste
            const progressInterval = setInterval(() => {
                if (generationProgress.value < endProgress - 5) {
                    generationProgress.value += 1; // Plus lent et régulier
                }
            }, 300); // Moins fréquent

            try {
            const response = await axios.post('/articles/generate-with-ai', requestData, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

                // Arrêter l'animation de progression
                clearInterval(progressInterval);

            const aiData = response.data;
            console.log('✅ AI generation response for', targetLanguage, ':', aiData);

            generationResults.value.push({
                language: targetLanguage,
                data: aiData,
            });
                
                // Finaliser la progression pour cette langue
                generationProgress.value = endProgress;
                await new Promise(resolve => setTimeout(resolve, 300));
                
            } catch (error) {
                clearInterval(progressInterval);
                throw error;
            }
        }

        // Progression complète
        generationProgress.value = 100;
        currentGeneratingLanguage.value = '';

        // Charger le premier résultat dans le formulaire
        if (generationResults.value.length > 0) {
            console.log('📝 Loading first generated article into form...');
            await loadGeneratedArticle(generationResults.value[0]);
        }

        showNotification('success', 'Génération réussie', `Articles générés en ${selectedGenerationLanguages.value.length} langue(s)`);

        // Vider le prompt après génération réussie
        aiPrompt.value = '';
    } catch (error: any) {
        console.error('❌ Erreur lors de la génération multi-langues:', error);
        console.error('📋 Error details:', {
            status: error.response?.status,
            data: error.response?.data,
            message: error.message,
        });

        let errorMessage = 'Erreur lors de la génération des articles';
        if (error.response?.data?.message) {
            errorMessage = error.response.data.message;
        } else if (error.response?.data?.error) {
            errorMessage = error.response.data.error;
        } else if (error.response?.data?.errors) {
            // Gestion des erreurs de validation Laravel
            const validationErrors = Object.values(error.response.data.errors).flat();
            errorMessage = validationErrors.join(', ');
        } else if (error.message) {
            errorMessage = error.message;
        }

        showNotification('error', 'Erreur de génération', errorMessage);
    } finally {
        generatingWithAI.value = false;
        generationProgress.value = 0;
        currentGeneratingLanguage.value = '';
    }
};

// Fonction pour charger un article généré et remplir TOUS les champs
const loadGeneratedArticle = async (result: any) => {
    const aiData = result.data;
    const language = result.language;

    console.log('📝 Loading generated article data for', language, ':', aiData);

    // Créer la version pour cette langue
    const version = {
        title: aiData.title || '',
        excerpt: aiData.excerpt || '',
        content: '',
        content_html: '',
        meta_title: aiData.meta_title || '',
        meta_description: aiData.meta_description || '',
        meta_keywords: aiData.meta_keywords || '',
        canonical_url: aiData.canonical_url || '',
        author_name: aiData.author_name || '',
        author_bio: aiData.author_bio || '',
        categories: [] as string[],
    };

    // Convertir le HTML en EditorJS si on a du content_html
    if (aiData.content_html) {
        try {
            console.log('🔄 Converting HTML to EditorJS for', language);
            console.log('📄 HTML content:', aiData.content_html.substring(0, 200) + '...');
            
            // Utiliser la fonction de conversion HTML vers EditorJS
            const editorJSData = convertHTMLToEditorJS(aiData.content_html);
                version.content = JSON.stringify(editorJSData);
            version.content_html = aiData.content_html; // Garder le HTML original aussi
            
            console.log('✅ HTML successfully converted to EditorJS for', language);
        } catch (error) {
            console.error('❌ Error converting HTML to EditorJS for', language, ':', error);
            // Fallback : créer un contenu EditorJS simple avec le HTML
            const fallbackContent = {
                    time: Date.now(),
                    blocks: [
                        {
                            type: 'paragraph',
                            data: {
                            text: aiData.content_html || 'Contenu généré par IA',
                            },
                        },
                    ],
                    version: '2.28.2',
                };
            version.content = JSON.stringify(fallbackContent);
            version.content_html = aiData.content_html || '';
        }
    } else if (aiData.content) {
        // Compatibilité avec l'ancien format EditorJS
        try {
            let editorJSData;
            if (typeof aiData.content === 'string') {
                editorJSData = JSON.parse(aiData.content);
            } else {
                editorJSData = aiData.content;
            }

            if (editorJSData && editorJSData.blocks) {
                version.content = JSON.stringify(editorJSData);
                version.content_html = convertForWebhook(editorJSData);
                console.log('✅ EditorJS content processed for', language);
            }
        } catch (error) {
            console.error('❌ Error processing EditorJS content for', language, ':', error);
        }
    }

    // Si des catégories sont suggérées et correspondent aux catégories disponibles
    if (aiData.suggested_categories && Array.isArray(aiData.suggested_categories)) {
        console.log('🏷️ Processing suggested categories:', aiData.suggested_categories);

        const matchingCategories = availableCategories.value
            .filter((cat) =>
                aiData.suggested_categories.some(
                    (suggested: string) =>
                        cat.name.toLowerCase().includes(suggested.toLowerCase()) || suggested.toLowerCase().includes(cat.name.toLowerCase()),
                ),
            )
            .map((cat) => cat.id.toString());

        console.log('✅ Matched categories:', matchingCategories);
        version.categories = matchingCategories;
    }

    // Sauvegarder cette version dans le système multi-langues
    articleVersions.value.set(language, version);

    // Si c'est la première génération ou si on génère pour la langue actuelle, charger dans le formulaire
    if (language === currentLanguage.value || articleVersions.value.size === 1) {
        currentLanguage.value = language;
        loadLanguageVersion(language);
    }

    console.log('✅ Article généré et sauvegardé pour:', getLanguageName(language));

    // Log pour débugger quels champs ont été remplis
    console.log('📋 Version saved for', language, ':', version);
};

// Watchers pour sauvegarder automatiquement les modifications
watch(
    [
        () => form.title,
        () => form.excerpt,
        () => form.content,
        () => form.meta_title,
        () => form.meta_description,
        () => form.meta_keywords,
        () => form.canonical_url,
        () => form.author_name,
        () => form.author_bio,
        () => selectedCategoryValues.value,
    ],
    () => {
        // Sauvegarder automatiquement la version actuelle quand l'utilisateur modifie quelque chose
        if (currentLanguage.value) {
            saveCurrentVersion();
        }
    },
    { deep: true, flush: 'post' },
);

// Fonctions pour l'IA et multi-langues
const languageNames: Record<string, string> = {
    fr: 'Français',
    en: 'English',
    es: 'Español',
    de: 'Deutsch',
    it: 'Italiano',
    pt: 'Português',
    nl: 'Nederlands',
    ru: 'Русский',
    ja: '日本語',
    zh: '中文',
};

const getLanguageName = (langCode: string): string => {
    return languageNames[langCode] || langCode;
};

// Computed pour valider les prompts batch
const validBatchPrompts = computed(() => {
    return batchPrompts.value.filter(prompt => prompt.text.trim().length > 0);
});

// Fonctions pour le système batch
const addBatchPrompt = () => {
    if (batchPrompts.value.length < 50) {
        batchPrompts.value.push({text: '', language: 'fr'});
    }
};

const removeBatchPrompt = (index: number) => {
    if (batchPrompts.value.length > 1) {
        batchPrompts.value.splice(index, 1);
    }
};

const createBatch = async () => {
    if (validBatchPrompts.value.length === 0) return;
    
    creatingBatch.value = true;
    
    try {
        const requests = validBatchPrompts.value.map(prompt => ({
            prompt: prompt.text,
            site_id: form.site_id,
            language: prompt.language
        }));
        
        const response = await axios.post('/ai/batch', { requests });
        
        showNotification('success', 'Batch créé', `Batch de ${requests.length} articles créé avec succès!`);
        
        // Réinitialiser les prompts
        batchPrompts.value = [{text: '', language: 'fr'}];
        
        // Recharger les batches
        await loadUserBatches();
        
    } catch (error: any) {
        console.error('Erreur création batch:', error);
        showNotification('error', 'Erreur', 'Erreur lors de la création du batch');
    } finally {
        creatingBatch.value = false;
    }
};

const loadUserBatches = async () => {
    try {
        const response = await axios.get('/ai/batches');
        userBatches.value = response.data;
    } catch (error) {
        console.error('Erreur chargement batches:', error);
    }
};

const checkBatchStatus = async (batchId: number) => {
    try {
        const response = await axios.get(`/ai/batch/${batchId}/status`);
        
        // Mettre à jour le batch dans la liste
        const batchIndex = userBatches.value.findIndex(b => b.id === batchId);
        if (batchIndex !== -1) {
            userBatches.value[batchIndex] = { ...userBatches.value[batchIndex], ...response.data };
        }
        
        showNotification('success', 'Statut mis à jour', `Batch ${batchId}: ${response.data.status}`);
        
    } catch (error) {
        console.error('Erreur vérification statut:', error);
    }
};

const loadBatchResults = async (batchId: number) => {
    try {
        const response = await axios.get(`/ai/batch/${batchId}/results`);
        
        if (response.data.results && response.data.results.length > 0) {
            // Charger le premier résultat dans le formulaire
            const firstResult = response.data.results[0];
            await loadGeneratedArticle({
                data: firstResult.result,
                language: firstResult.metadata.language
            });
            
            showNotification('success', 'Résultats chargés', `${response.data.results.length} articles du batch chargés`);
        }
        
    } catch (error) {
        console.error('Erreur chargement résultats:', error);
        showNotification('error', 'Erreur', 'Erreur lors du chargement des résultats');
    }
};

const getBatchStatusText = (status: string): string => {
    const statusMap: Record<string, string> = {
        'pending': 'En attente',
        'submitted': 'Soumis',
        'completed': 'Terminé',
        'failed': 'Échoué'
    };
    return statusMap[status] || status;
};

// Méthode pour forcer la mise à jour de l'éditeur EditorJS
const updateEditorContent = async (newContent: string) => {
    if (!editorJSComponent.value) {
        console.log('⚠️ EditorJS component not ready yet');
        return;
    }
    
    try {
        if (newContent) {
            console.log('🔄 Updating editor with new content');
            const editorData = JSON.parse(newContent);
            
            // Utiliser nextTick pour s'assurer que l'éditeur est prêt
            await nextTick();
            
            // Si l'éditeur a une méthode render, l'utiliser
            if (editorJSComponent.value.editor && editorJSComponent.value.editor.render) {
                await editorJSComponent.value.editor.render(editorData);
                console.log('✅ Editor content updated successfully');
            } else {
                console.warn('⚠️ Editor render method not available');
            }
        } else {
            console.log('🗑️ Clearing editor content');
            // Contenu vide, nettoyer l'éditeur
            if (editorJSComponent.value.editor && editorJSComponent.value.editor.clear) {
                await editorJSComponent.value.editor.clear();
            }
        }
    } catch (error) {
        console.error('❌ Error updating editor content:', error);
    }
};

// Nouvelles variables pour la séparation des fonctionnalités
const selectedTranslationLanguages = ref<string[]>([]);
const siteLanguages = ref<Language[]>([]);
const translationResults = ref<any[]>([]);
</script>

<style>
.custom-multiselect .multiselect__tags {
    min-height: 48px;
    border-radius: 8px;
    border: 1px solid #d1d5db;
    background: #ffffff;
    padding: 8px 12px;
    font-size: 15px;
    transition: all 0.2s ease;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.custom-multiselect .multiselect__tags:hover {
    border-color: #9ca3af;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.custom-multiselect .multiselect__tags:focus-within {
    border-color: #6b7280;
    box-shadow: 0 0 0 3px rgba(107, 114, 128, 0.1);
    transform: translateY(-1px);
}

.custom-multiselect .multiselect__input {
    border: none;
    background: transparent;
    font-size: 15px;
    padding: 4px 0;
}

.custom-multiselect .multiselect__input:focus {
    outline: none;
}

.custom-multiselect .multiselect__placeholder {
    color: #9ca3af;
    padding-top: 4px;
    margin-bottom: 8px;
    font-size: 15px;
}

.custom-multiselect .multiselect__content-wrapper {
    border: none;
    border-radius: 8px;
    box-shadow:
        0 4px 6px -1px rgba(0, 0, 0, 0.1),
        0 2px 4px -1px rgba(0, 0, 0, 0.06);
    background: white;
    margin-top: 4px;
    overflow: hidden;
    animation: dropdownAppear 0.15s ease-out;
}

.custom-multiselect .multiselect__content {
    max-height: 240px;
}

.custom-multiselect .multiselect__option {
    padding: 0;
    border-bottom: 1px solid #f3f4f6;
    transition: all 0.15s ease;
}

.custom-multiselect .multiselect__option:last-child {
    border-bottom: none;
}

.custom-multiselect .multiselect__option--highlight {
    background: #f9fafb;
    color: #374151;
}

.custom-multiselect .multiselect__option--selected {
    background: #f3f4f6;
    color: #1f2937;
    font-weight: 500;
}

.custom-multiselect .multiselect__option--selected::after {
    content: '✓';
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #059669;
    font-weight: bold;
}

.custom-multiselect .multiselect__tag {
    background: none !important;
    border: none !important;
    border-radius: 0 !important;
    color: inherit !important;
    font-size: inherit !important;
    font-weight: inherit !important;
    padding: 0 !important;
    margin: 0 !important;
    animation: tagAppear 0.2s ease-out;
}

.custom-multiselect .multiselect__tag-icon {
    display: none !important;
}

.custom-multiselect .multiselect__spinner {
    background: #6b7280;
    border-radius: 50%;
    width: 3px;
    height: 3px;
}

.custom-multiselect .multiselect__loading {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(2px);
}

/* Animation pour les tags */
@keyframes tagAppear {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* Transition douce pour le contenu */
@keyframes dropdownAppear {
    from {
        opacity: 0;
        transform: translateY(-8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
