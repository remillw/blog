<script setup lang="ts">
import Code from '@editorjs/code';
import EditorJS from '@editorjs/editorjs';
import Header from '@editorjs/header';
import ImageTool from '@editorjs/image';
import List from '@editorjs/list';
import { computed, defineEmits, defineProps, onBeforeUnmount, onMounted, ref, watch } from 'vue';
// @ts-expect-error - EditorJS embed plugin lacks TypeScript definitions
import Embed from '@editorjs/embed';
// @ts-expect-error - EditorJS link plugin lacks TypeScript definitions
import LinkTool from '@editorjs/link';
import Paragraph from '@editorjs/paragraph';
// @ts-expect-error - Alignment tune plugin lacks TypeScript definitions
import AttachesTool from '@editorjs/attaches';
import AlignmentTuneTool from 'editorjs-text-alignment-blocktune';

// Implémentation de la classe CustomTool au lieu d'une simple déclaration
// eslint-disable-next-line @typescript-eslint/no-explicit-any
class CustomTool {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    constructor() {
        // Constructeur de base
    }

    static get isInline() {
        return false;
    }

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    render(): any {
        return null;
    }

    save() {
        return {};
    }

    static get sanitize() {
        return {};
    }
}

// Classe pour le bouton personnalisé avec call-to-actions ChatGPT
class ButtonTool extends CustomTool {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    api: any;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    data: any;
    wrapper: HTMLElement | null = null;
    isEditing: boolean = false;

    static get toolbox() {
        return {
            title: 'Call-to-Action',
            icon: '<svg width="17" height="15" viewBox="0 0 17 15" xmlns="http://www.w3.org/2000/svg"><rect x="1" y="4" width="15" height="7" rx="3" stroke="currentColor" fill="none" stroke-width="1.5"/><path d="M6 7.5h5M8.5 6v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
        };
    }

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    constructor({ data, api }: any) {
        super();
        this.api = api;
        this.data = {
            text: data?.text || '👆 Cliquez ici',
            link: data?.link || '',
            style: data?.style || 'primary',
            target: data?.target || '_self',
            rel: data?.rel || '',
        };
    }

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    override render() {
        this.wrapper = document.createElement('div');
        this.wrapper.classList.add('button-tool');

        this.renderViewMode();
        this.setupHoverSystem();

        return this.wrapper;
    }

    renderViewMode() {
        if (!this.wrapper) return;

        this.wrapper.innerHTML = '';
        this.isEditing = false;

        const buttonPreview = document.createElement('div');
        buttonPreview.classList.add('button-tool__preview');
        buttonPreview.style.position = 'relative';
        buttonPreview.style.cursor = 'pointer';
        buttonPreview.title = 'Cliquez pour éditer ce call-to-action';

        const button = document.createElement('a');
        button.classList.add('button-tool__btn');
        button.classList.add(`button-tool__btn--${this.data.style}`);
        button.textContent = this.data.text;
        button.href = this.data.link || '#';
        button.target = this.data.target;
        if (this.data.rel) {
            button.rel = this.data.rel;
        }
        
        // Appliquer les couleurs inline selon le style
        this.applyInlineColors(button);
        
        button.onclick = (e) => e.preventDefault(); // Empêcher la navigation en mode édition

        // Ajouter un gestionnaire pour éditer directement en cliquant sur le bouton
        button.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.renderEditMode();
        });

        // Ajouter aussi un gestionnaire de double-clic pour une édition encore plus rapide
        button.addEventListener('dblclick', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.renderEditMode();
        });

        buttonPreview.appendChild(button);

        // Ajouter le badge "CTA" pour différencier des liens
        const ctaBadge = document.createElement('div');
        ctaBadge.className = 'cta-badge';
        ctaBadge.textContent = 'CTA';
        ctaBadge.style.cssText = `
            position: absolute;
            top: -8px;
            right: -8px;
            background: #f59e0b;
            color: white;
            font-size: 10px;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 8px;
            font-family: monospace;
            pointer-events: none;
        `;
        buttonPreview.appendChild(ctaBadge);

        // Ajouter un bouton d'édition visible
        const editButton = document.createElement('button');
        editButton.type = 'button';
        editButton.className = 'cta-edit-button';
        editButton.innerHTML = '✏️';
        editButton.title = 'Éditer ce call-to-action';
        editButton.style.cssText = `
            position: absolute;
            top: -8px;
            left: -8px;
            width: 24px;
            height: 24px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            transition: all 0.2s ease;
            opacity: 0;
        `;

        editButton.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.renderEditMode();
        });

        // Montrer/cacher le bouton d'édition au survol
        buttonPreview.addEventListener('mouseenter', () => {
            editButton.style.opacity = '1';
            editButton.style.transform = 'scale(1.1)';
        });

        buttonPreview.addEventListener('mouseleave', () => {
            editButton.style.opacity = '0';
            editButton.style.transform = 'scale(1)';
        });

        buttonPreview.appendChild(editButton);
        this.wrapper.appendChild(buttonPreview);
    }

    renderEditMode() {
        if (!this.wrapper) return;

        this.wrapper.innerHTML = '';
        this.isEditing = true;

        // Mode édition avec formulaire complet
        const editContainer = document.createElement('div');
        editContainer.classList.add('button-tool__edit');

        const buttonPreview = document.createElement('div');
        buttonPreview.classList.add('button-tool__preview');
        
        const button = document.createElement('a');
        button.classList.add('button-tool__btn');
        button.classList.add(`button-tool__btn--${this.data.style}`);
        button.textContent = this.data.text;
        button.href = this.data.link || '#';
        button.target = this.data.target;
        if (this.data.rel) {
            button.rel = this.data.rel;
        }
        button.onclick = (e) => e.preventDefault();
        
        // Appliquer les couleurs inline aussi en mode édition
        this.applyInlineColors(button);

        buttonPreview.appendChild(button);

        // Formulaire de configuration
        const form = document.createElement('div');
        form.classList.add('button-tool__form');

        // Champ texte
        const textGroup = document.createElement('div');
        textGroup.classList.add('button-tool__group');
        const textLabel = document.createElement('label');
        textLabel.textContent = '📝 Texte du bouton:';
        textLabel.className = 'button-tool__label';

        const textInput = document.createElement('input');
        textInput.classList.add('button-tool__input');
        textInput.placeholder = 'Ex: Découvrez maintenant, En savoir plus...';
        textInput.value = this.data.text;
        textInput.addEventListener('input', () => {
            this.data.text = textInput.value;
            button.textContent = textInput.value;
        });

        // Auto-focus et sélection du texte quand on entre en mode édition
        setTimeout(() => {
            textInput.focus();
            textInput.select();
        }, 100);

        // Raccourcis clavier pour une édition plus rapide
        textInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.renderViewMode(); // Sauvegarder et revenir à la vue
            } else if (e.key === 'Escape') {
                e.preventDefault();
                this.renderViewMode(); // Annuler et revenir à la vue
            }
        });

        textGroup.appendChild(textLabel);
        textGroup.appendChild(textInput);

        // Champ lien
        const linkGroup = document.createElement('div');
        linkGroup.classList.add('button-tool__group');
        const linkLabel = document.createElement('label');
        linkLabel.textContent = '🔗 Lien (URL):';
        linkLabel.className = 'button-tool__label';

        const linkInput = document.createElement('input');
        linkInput.classList.add('button-tool__input');
        linkInput.placeholder = 'https://exemple.com ou /page-interne';
        linkInput.value = this.data.link;
        linkInput.addEventListener('input', () => {
            this.data.link = linkInput.value;
            button.href = linkInput.value || '#';
            
            // Détecter automatiquement les liens externes
            if (linkInput.value && (linkInput.value.startsWith('http') || linkInput.value.startsWith('https'))) {
                this.data.target = '_blank';
                this.data.rel = 'noopener noreferrer';
                targetSelect.value = '_blank';
            } else {
                this.data.target = '_self';
                this.data.rel = '';
                targetSelect.value = '_self';
            }
            button.target = this.data.target;
            button.rel = this.data.rel;
        });

        linkGroup.appendChild(linkLabel);
        linkGroup.appendChild(linkInput);

        // Style du bouton
        const styleGroup = document.createElement('div');
        styleGroup.classList.add('button-tool__group');
        const styleLabel = document.createElement('label');
        styleLabel.textContent = '🎨 Style:';
        styleLabel.className = 'button-tool__label';

        const styleSelect = document.createElement('select');
        styleSelect.classList.add('button-tool__select');

        const options = [
            { value: 'primary', text: '🔥 Principal (Attention)', description: 'Bouton principal avec couleur site' },
            { value: 'secondary', text: '📋 Secondaire (Info)', description: 'Bouton neutre pour infos' },
            { value: 'success', text: '✅ Succès (Action)', description: 'Vert pour actions positives' },
            { value: 'warning', text: '⚠️ Attention (Promo)', description: 'Orange pour promotions' },
            { value: 'danger', text: '🚨 Urgent (Dernière chance)', description: 'Rouge pour urgence' },
            { value: 'outline', text: '⭕ Contour (Discret)', description: 'Bordure seule, plus discret' },
        ];

        options.forEach((opt) => {
            const option = document.createElement('option');
            option.value = opt.value;
            option.textContent = opt.text;
            option.title = opt.description;
            if (opt.value === this.data.style) {
                option.selected = true;
            }
            styleSelect.appendChild(option);
        });

        styleSelect.addEventListener('change', () => {
            button.classList.remove(`button-tool__btn--${this.data.style}`);
            this.data.style = styleSelect.value;
            button.classList.add(`button-tool__btn--${this.data.style}`);
            // Réappliquer les couleurs inline avec le nouveau style
            this.applyInlineColors(button);
        });

        styleGroup.appendChild(styleLabel);
        styleGroup.appendChild(styleSelect);

        // Target du lien
        const targetGroup = document.createElement('div');
        targetGroup.classList.add('button-tool__group');
        const targetLabel = document.createElement('label');
        targetLabel.textContent = '🎯 Ouverture:';
        targetLabel.className = 'button-tool__label';
        
        const targetSelect = document.createElement('select');
        targetSelect.classList.add('button-tool__select');

        const targetOptions = [
            { value: '_self', text: '📄 Même page' },
            { value: '_blank', text: '🆕 Nouvel onglet' },
        ];

        targetOptions.forEach((opt) => {
            const option = document.createElement('option');
            option.value = opt.value;
            option.textContent = opt.text;
            if (opt.value === this.data.target) {
                option.selected = true;
            }
            targetSelect.appendChild(option);
        });

        targetSelect.addEventListener('change', () => {
            this.data.target = targetSelect.value;
            button.target = this.data.target;
            
            // Ajouter rel="noopener noreferrer" pour les liens externes
            if (this.data.target === '_blank') {
                this.data.rel = 'noopener noreferrer';
                button.rel = this.data.rel;
            } else {
                this.data.rel = '';
                button.rel = '';
            }
        });

        targetGroup.appendChild(targetLabel);
        targetGroup.appendChild(targetSelect);

        // Suggestions de CTAs
        const suggestionsGroup = document.createElement('div');
        suggestionsGroup.classList.add('button-tool__group');
        const suggestionsLabel = document.createElement('label');
        suggestionsLabel.textContent = '💡 Suggestions de CTA:';
        suggestionsLabel.className = 'button-tool__label';
        
        const suggestionsContainer = document.createElement('div');
        suggestionsContainer.classList.add('button-tool__suggestions');

        const suggestions = [
            '🔍 Découvrir maintenant',
            '📖 En savoir plus',
            '🎯 Commencer gratuitement',
            '💎 Voir l\'offre spéciale',
            '📧 Me tenir informé(e)',
            '🔥 Profiter de -50%',
            '⚡ Accès immédiat',
            '🎁 Récupérer mon bonus',
            '📞 Contactez-nous',
            '📋 Télécharger le guide',
        ];

        suggestions.forEach((suggestion) => {
            const suggestionBtn = document.createElement('button');
            suggestionBtn.type = 'button';
            suggestionBtn.textContent = suggestion;
            suggestionBtn.className = 'button-tool__suggestion';
            suggestionBtn.addEventListener('click', () => {
                textInput.value = suggestion;
                this.data.text = suggestion;
                button.textContent = suggestion;
            });
            suggestionsContainer.appendChild(suggestionBtn);
        });

        suggestionsGroup.appendChild(suggestionsLabel);
        suggestionsGroup.appendChild(suggestionsContainer);

        // Bouton de sauvegarde
        const saveGroup = document.createElement('div');
        saveGroup.classList.add('button-tool__group');
        saveGroup.style.cssText = `
            border-top: 1px solid #e1e5e9;
            padding-top: 16px;
            margin-top: 16px;
            display: flex;
            justify-content: flex-end;
        `;

        const saveButton = document.createElement('button');
        saveButton.type = 'button';
        saveButton.textContent = '💾 Sauvegarder';
        saveButton.className = 'button-tool__save';
        saveButton.style.cssText = `
            background: #22c55e;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s ease;
        `;

        saveButton.addEventListener('click', () => {
            this.renderViewMode();
        });

        saveButton.addEventListener('mouseenter', () => {
            saveButton.style.backgroundColor = '#16a34a';
        });

        saveButton.addEventListener('mouseleave', () => {
            saveButton.style.backgroundColor = '#22c55e';
        });

        saveGroup.appendChild(saveButton);

        // Assembler le formulaire
        form.appendChild(textGroup);
        form.appendChild(linkGroup);
        form.appendChild(styleGroup);
        form.appendChild(targetGroup);
        form.appendChild(suggestionsGroup);
        form.appendChild(saveGroup);

        editContainer.appendChild(buttonPreview);
        editContainer.appendChild(form);
        this.wrapper.appendChild(editContainer);
    }

    setupHoverSystem() {
        // Le système de hover est maintenant simplifié car nous avons l'édition directe
        // On garde juste une indication visuelle simple
        if (!this.wrapper) return;

        this.wrapper.addEventListener('mouseenter', () => {
            if (this.isEditing) return;
            
            // Ajouter un effet visuel simple au survol
            const button = this.wrapper.querySelector('.button-tool__btn');
            if (button) {
                (button as HTMLElement).style.transform = 'translateY(-2px)';
                (button as HTMLElement).style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';
            }
        });

        this.wrapper.addEventListener('mouseleave', () => {
            if (this.isEditing) return;
            
            const button = this.wrapper.querySelector('.button-tool__btn');
            if (button) {
                (button as HTMLElement).style.transform = 'translateY(0)';
                (button as HTMLElement).style.boxShadow = 'none';
            }
        });
    }



    // Méthode pour appliquer les couleurs inline basées sur les couleurs du site
    applyInlineColors(button: HTMLElement) {
        // Récupérer les couleurs du site depuis les props Vue (via un event global ou data attribute)
        const siteColors = this.getSiteColors();
        
        let backgroundColor = '';
        let color = 'white';
        let borderColor = '';
        
        switch (this.data.style) {
            case 'primary':
                backgroundColor = siteColors.primary;
                borderColor = siteColors.primary;
                break;
            case 'secondary':
                backgroundColor = siteColors.secondary;
                borderColor = siteColors.secondary;
                break;
            case 'success':
                backgroundColor = '#22c55e';
                borderColor = '#22c55e';
                break;
            case 'warning':
                backgroundColor = '#f59e0b';
                borderColor = '#f59e0b';
                break;
            case 'danger':
                backgroundColor = '#ef4444';
                borderColor = '#ef4444';
                break;
            case 'outline':
                backgroundColor = 'transparent';
                color = siteColors.primary;
                borderColor = siteColors.primary;
                break;
            default:
                backgroundColor = siteColors.primary;
                borderColor = siteColors.primary;
        }
        
        // Appliquer les styles inline
        button.style.cssText = `
            display: inline-block;
            padding: 12px 24px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            text-decoration: none;
            border: 2px solid ${borderColor};
            min-width: 160px;
            background-color: ${backgroundColor};
            color: ${color};
        `;
        
        // Ajouter les effets hover via JavaScript
        button.addEventListener('mouseenter', () => {
            if (this.data.style === 'outline') {
                button.style.backgroundColor = siteColors.primary;
                button.style.color = 'white';
            } else {
                button.style.transform = 'translateY(-2px)';
                button.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';
            }
        });
        
        button.addEventListener('mouseleave', () => {
            if (this.data.style === 'outline') {
                button.style.backgroundColor = 'transparent';
                button.style.color = siteColors.primary;
            } else {
                button.style.transform = 'translateY(0)';
                button.style.boxShadow = 'none';
            }
        });
    }
    
    // Méthode pour récupérer les couleurs du site
    getSiteColors() {
        // Essayer de récupérer depuis un data attribute ou variable globale
        const editorElement = document.querySelector('.codex-editor');
        let siteColors = {
            primary: '#4E8D44',
            secondary: '#6b7280',
            accent: '#10b981'
        };
        
        if (editorElement) {
            const primaryColor = editorElement.getAttribute('data-primary-color');
            const secondaryColor = editorElement.getAttribute('data-secondary-color');
            const accentColor = editorElement.getAttribute('data-accent-color');
            
            if (primaryColor) siteColors.primary = primaryColor;
            if (secondaryColor) siteColors.secondary = secondaryColor;
            if (accentColor) siteColors.accent = accentColor;
        }
        
        return siteColors;
    }

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    override save() {
        return {
            text: this.data.text,
            link: this.data.link,
            style: this.data.style,
            target: this.data.target,
            rel: this.data.rel,
            siteColors: this.getSiteColors(), // Sauvegarder les couleurs aussi
        };
    }

    static override get sanitize() {
        return {
            text: {},
            link: {},
            style: {},
            target: {},
            rel: {},
        };
    }
}

// Plugin d'image personnalisé avec contrôle de largeur
class CustomImageTool extends ImageTool {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    constructor(params: any) {
        super(params);
    }

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    override render() {
        const wrapper = super.render();

        // Ajouter le contrôle de largeur personnalisé
        this.addCustomWidthControl(wrapper);

        // Ajouter un bouton pour afficher/masquer le contrôle
        this.addToggleButton(wrapper);

        return wrapper;
    }

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    addCustomWidthControl(wrapper: any) {
        // Créer le conteneur pour le contrôle de largeur
        const widthControl = document.createElement('div');
        widthControl.className = 'image-width-control';
        widthControl.style.cssText = `
            display: none;
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e1e5e9;
        `;

        // Créer le label
        const label = document.createElement('label');
        label.textContent = 'Largeur personnalisée: ';
        label.style.cssText = `
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
            color: #374151;
        `;

        // Créer le slider
        const slider = document.createElement('input');
        slider.type = 'range';
        slider.min = '10';
        slider.max = '100';
        slider.value = '100';
        slider.style.cssText = `
            width: 100%;
            margin-bottom: 8px;
            accent-color: #3b82f6;
        `;

        // Créer l'affichage de la valeur
        const valueDisplay = document.createElement('span');
        valueDisplay.textContent = '100%';
        valueDisplay.style.cssText = `
            font-size: 14px;
            color: #6b7280;
            font-weight: 500;
        `;

        // Créer le champ alt text
        const altLabel = document.createElement('label');
        altLabel.textContent = 'Texte alternatif (Alt):';
        altLabel.style.cssText = `
            display: block;
            margin-top: 16px;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
            color: #374151;
        `;

        const altInput = document.createElement('input');
        altInput.type = 'text';
        altInput.placeholder = "Décrivez cette image pour l'accessibilité...";
        altInput.style.cssText = `
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s;
        `;

        // Récupérer la valeur alt existante si elle existe
        const imageElement = wrapper.querySelector('img');
        if (imageElement && imageElement.alt) {
            altInput.value = imageElement.alt;
        }

        // Gestionnaire d'événement pour le slider
        slider.addEventListener('input', (e) => {
            const value = (e.target as HTMLInputElement).value;
            valueDisplay.textContent = `${value}%`;

            // Appliquer la largeur à l'image - chercher le bon conteneur
            const currentImageElement = wrapper.querySelector('img');
            const imageContainer = wrapper.querySelector('.image-tool') || wrapper.querySelector('.cdx-block') || wrapper;

            if (currentImageElement && imageContainer) {
                // Appliquer la largeur au conteneur de l'image
                (imageContainer as HTMLElement).style.maxWidth = `${value}%`;
                (imageContainer as HTMLElement).style.width = `${value}%`;
                (imageContainer as HTMLElement).style.margin = '0 auto';

                // S'assurer que l'image elle-même s'adapte
                (currentImageElement as HTMLElement).style.width = '100%';
                (currentImageElement as HTMLElement).style.height = 'auto';

                console.log(`🎯 Applied width ${value}% to image container:`, imageContainer);
            } else {
                console.warn('❌ Could not find image or container for width adjustment');
            }
        });

        // Gestionnaire d'événement pour le champ alt
        altInput.addEventListener('input', (e) => {
            const altValue = (e.target as HTMLInputElement).value;
            const img = wrapper.querySelector('img');
            if (img) {
                img.alt = altValue;
            }
        });

        // Focus/blur styles pour le champ alt
        altInput.addEventListener('focus', () => {
            altInput.style.borderColor = '#3b82f6';
            altInput.style.boxShadow = '0 0 0 3px rgba(59, 130, 246, 0.1)';
        });

        altInput.addEventListener('blur', () => {
            altInput.style.borderColor = '#d1d5db';
            altInput.style.boxShadow = 'none';
        });

        // Assembler le contrôle
        const sliderContainer = document.createElement('div');
        sliderContainer.style.cssText = `
            display: flex;
            align-items: center;
            gap: 10px;
        `;

        sliderContainer.appendChild(slider);
        sliderContainer.appendChild(valueDisplay);

        widthControl.appendChild(label);
        widthControl.appendChild(sliderContainer);
        widthControl.appendChild(altLabel);
        widthControl.appendChild(altInput);

        // Ajouter le contrôle au wrapper
        wrapper.appendChild(widthControl);

        // Stocker la référence pour pouvoir l'afficher/masquer
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        (this as any).widthControl = widthControl;
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        (this as any).altInput = altInput;
    }

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    addToggleButton(wrapper: any) {
        const toggleButton = document.createElement('button');
        toggleButton.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                <rect x="2" y="8" width="16" height="4" rx="2" fill="currentColor"/>
                <circle cx="6" cy="10" r="2" fill="white"/>
                <path d="M1 4h18M1 16h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
        `;
        toggleButton.title = 'Ajuster la largeur';
        toggleButton.style.cssText = `
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid #e1e5e9;
            border-radius: 6px;
            padding: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            z-index: 10;
        `;

        toggleButton.addEventListener('mouseenter', () => {
            toggleButton.style.background = 'white';
            toggleButton.style.boxShadow = '0 2px 8px rgba(0, 0, 0, 0.1)';
        });

        toggleButton.addEventListener('mouseleave', () => {
            toggleButton.style.background = 'rgba(255, 255, 255, 0.9)';
            toggleButton.style.boxShadow = 'none';
        });

        toggleButton.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.toggleCustomWidthControl();
        });

        // Ajouter le bouton au wrapper avec position relative
        wrapper.style.position = 'relative';
        wrapper.appendChild(toggleButton);
    }

    // Méthode pour afficher/masquer le contrôle de largeur
    toggleCustomWidthControl() {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const control = (this as any).widthControl;
        if (control) {
            control.style.display = control.style.display === 'none' ? 'block' : 'none';
        }
    }
}

// Plugin de couleur personnalisé
class CustomColorTool {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    api: any;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    config: any;
    button: HTMLElement | null = null;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    currentSelection: any = null;

    static get isInline() {
        return true;
    }

    static get title() {
        return 'Color';
    }

    static get sanitize() {
        return {
            span: {
                style: true,
            },
        };
    }

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    constructor({ api, config }: any) {
        this.api = api;
        this.config = config || {};
    }

    render() {
        this.button = document.createElement('button');
        (this.button as HTMLButtonElement).type = 'button';
        this.button.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2L13.09 8.26L22 9L13.09 9.74L12 16L10.91 9.74L2 9L10.91 8.26L12 2Z" fill="currentColor"/>
                <rect x="4" y="18" width="16" height="3" rx="1" fill="currentColor"/>
            </svg>
        `;
        this.button.classList.add('ce-inline-tool');
        this.button.title = 'Couleur du texte';

        this.button.addEventListener('click', () => {
            this.toggleColorPalette();
        });

        return this.button;
    }

    surround() {
        // Ne pas appliquer automatiquement une couleur, juste ouvrir la palette
        return;
    }

    checkState() {
        const selection = this.api.selection.findParentTag('SPAN');
        return !!selection;
    }

    toggleColorPalette() {
        // Supprimer toute palette existante
        const existingPalette = document.querySelector('.custom-color-palette');
        if (existingPalette) {
            existingPalette.remove();
            return;
        }

        // Créer la palette de couleurs
        const palette = document.createElement('div');
        palette.className = 'custom-color-palette';
        palette.style.cssText = `
            position: fixed;
            background: white;
            border: 1px solid #e1e5e9;
            border-radius: 12px;
            padding: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            z-index: 10000;
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 6px;
            min-width: 240px;
            backdrop-filter: blur(10px);
        `;

        // Ajouter les couleurs
        const colors = this.config.colors || [
            '#000000',
            '#FFFFFF',
            '#6b7280',
            '#ef4444',
            '#f97316',
            '#eab308',
            '#22c55e',
            '#3b82f6',
            '#8b5cf6',
            '#ec4899',
        ];

        colors.forEach((color: string) => {
            const colorButton = document.createElement('button');
            colorButton.style.cssText = `
                width: 36px;
                height: 36px;
                border: 2px solid ${color === '#FFFFFF' ? '#e1e5e9' : 'transparent'};
                border-radius: 8px;
                background-color: ${color};
                cursor: pointer;
                transition: all 0.2s ease;
                position: relative;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            `;

            // Ajouter un effet de survol
            colorButton.addEventListener('mouseenter', () => {
                colorButton.style.transform = 'scale(1.15)';
                colorButton.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.2)';
            });

            colorButton.addEventListener('mouseleave', () => {
                colorButton.style.transform = 'scale(1)';
                colorButton.style.boxShadow = '0 2px 4px rgba(0, 0, 0, 0.1)';
            });

            colorButton.addEventListener('click', () => {
                this.applyColor(color);
                palette.remove();
            });

            palette.appendChild(colorButton);
        });

        // Ajouter un bouton pour supprimer la couleur
        const removeButton = document.createElement('button');
        removeButton.innerHTML = '✕';
        removeButton.title = 'Supprimer la couleur';
        removeButton.style.cssText = `
            width: 36px;
            height: 36px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            background-color: #f8f9fa;
            cursor: pointer;
            font-size: 18px;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        `;

        removeButton.addEventListener('mouseenter', () => {
            removeButton.style.transform = 'scale(1.15)';
            removeButton.style.backgroundColor = '#fee2e2';
            removeButton.style.color = '#dc2626';
        });

        removeButton.addEventListener('mouseleave', () => {
            removeButton.style.transform = 'scale(1)';
            removeButton.style.backgroundColor = '#f8f9fa';
            removeButton.style.color = '#666';
        });

        removeButton.addEventListener('click', () => {
            this.removeColor();
            palette.remove();
        });

        palette.appendChild(removeButton);

        // Positionner la palette près du bouton
        if (this.button) {
            const rect = this.button.getBoundingClientRect();
            const paletteWidth = 240;
            const paletteHeight = 120;

            // Calculer la position optimale
            let left = rect.left - paletteWidth / 2 + rect.width / 2;
            let top = rect.bottom + 8;

            // Ajuster si la palette sort de l'écran
            if (left < 10) left = 10;
            if (left + paletteWidth > window.innerWidth - 10) {
                left = window.innerWidth - paletteWidth - 10;
            }

            if (top + paletteHeight > window.innerHeight - 10) {
                top = rect.top - paletteHeight - 8;
            }

            palette.style.left = `${left}px`;
            palette.style.top = `${top}px`;
        }

        document.body.appendChild(palette);

        // Fermer la palette en cliquant ailleurs
        setTimeout(() => {
            document.addEventListener(
                'click',
                (e) => {
                    if (!palette.contains(e.target as Node) && e.target !== this.button) {
                        palette.remove();
                    }
                },
                { once: true },
            );
        }, 100);
    }

    applyColor(color: string) {
        // Sauvegarder la sélection actuelle
        const currentSelection = window.getSelection();
        if (!currentSelection || currentSelection.rangeCount === 0) return;

        const range = currentSelection.getRangeAt(0);

        if (!range.collapsed) {
            // Il y a du texte sélectionné
            const selectedText = range.toString();

            // Approche simple et robuste : toujours créer un nouveau span propre
            // 1. Supprimer tout le contenu sélectionné (spans inclus)
            range.deleteContents();

            // 2. Créer un span complètement nouveau
            const span = document.createElement('span');
            span.style.color = color;
            span.textContent = selectedText;

            // 3. Insérer le nouveau span
            range.insertNode(span);

            // 4. Sélectionner le nouveau span
            const newRange = document.createRange();
            newRange.selectNodeContents(span);
            currentSelection.removeAllRanges();
            currentSelection.addRange(newRange);
        } else {
            // Pas de sélection, chercher un span parent
            const parentSpan = this.findParentSpan(range.startContainer);
            if (parentSpan) {
                parentSpan.style.color = color;
            }
        }
    }

    // Fonction pour nettoyer les spans de couleur imbriqués (simplifiée)
    cleanColorSpans(content: DocumentFragment): DocumentFragment {
        // Cette fonction n'est plus nécessaire avec la nouvelle approche
        return content;
    }

    removeColor() {
        const currentSelection = window.getSelection();
        if (!currentSelection || currentSelection.rangeCount === 0) return;

        const range = currentSelection.getRangeAt(0);
        const parentSpan = this.findParentSpan(range.startContainer);

        if (parentSpan) {
            // Remplacer le span par son contenu
            const parent = parentSpan.parentNode;
            if (parent) {
                while (parentSpan.firstChild) {
                    parent.insertBefore(parentSpan.firstChild, parentSpan);
                }
                parent.removeChild(parentSpan);
            }
        }
    }

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    findParentSpan(node: any): HTMLSpanElement | null {
        while (node && node !== document.body) {
            if (node.nodeType === Node.ELEMENT_NODE && node.tagName === 'SPAN') {
                return node as HTMLSpanElement;
            }
            node = node.parentNode;
        }
        return null;
    }
}

// Plugin de colonnes responsive avec support des blocks EditorJS
class AdvancedColumnsTool extends CustomTool {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    api: any;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    data: any;
    wrapper: HTMLElement | null = null;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    columnEditors: any[] = [];

    static get toolbox() {
        return {
            title: 'Colonnes Avancées',
            icon: '<svg width="17" height="15" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><rect x="2" y="3" width="8" height="18" rx="1" fill="currentColor"/><rect x="14" y="3" width="8" height="18" rx="1" fill="currentColor"/><circle cx="6" cy="12" r="2" fill="white"/><circle cx="18" cy="12" r="2" fill="white"/></svg>',
        };
    }

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    constructor({ data, api }: any) {
        super();
        this.api = api;
        this.data = {
            columns: data?.columns || [
                {
                    blocks: [
                        {
                            type: 'paragraph',
                            data: { text: 'Colonne 1 - Ajoutez vos blocs ici...' },
                        },
                    ],
                },
                {
                    blocks: [
                        {
                            type: 'paragraph',
                            data: { text: 'Colonne 2 - Ajoutez vos blocs ici...' },
                        },
                    ],
                },
            ],
            layout: data?.layout || '2-cols',
        };
    }

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    override render() {
        this.wrapper = document.createElement('div');
        this.wrapper.classList.add('advanced-columns-tool');

        // Créer le sélecteur de layout
        const layoutSelector = document.createElement('div');
        layoutSelector.classList.add('columns-layout-selector');
        layoutSelector.innerHTML = `
            <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #374151;">Layout des colonnes:</label>
            <select style="padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 4px; margin-bottom: 12px;">
                <option value="2-cols" ${this.data.layout === '2-cols' ? 'selected' : ''}>2 colonnes égales (50/50)</option>
                <option value="3-cols" ${this.data.layout === '3-cols' ? 'selected' : ''}>3 colonnes égales (33/33/33)</option>
                <option value="2-1" ${this.data.layout === '2-1' ? 'selected' : ''}>2 colonnes (66/33)</option>
                <option value="1-2" ${this.data.layout === '1-2' ? 'selected' : ''}>2 colonnes (33/66)</option>
            </select>
        `;

        // Créer le conteneur des colonnes
        const columnsContainer = document.createElement('div');
        columnsContainer.classList.add('advanced-columns-container');

        this.updateLayout();
        this.renderColumns(columnsContainer);

        // Gestionnaire pour le changement de layout
        const select = layoutSelector.querySelector('select');
        select?.addEventListener('change', (e) => {
            const target = e.target as HTMLSelectElement;
            this.data.layout = target.value;
            this.updateLayout();
            this.renderColumns(columnsContainer);
        });

        this.wrapper.appendChild(layoutSelector);
        this.wrapper.appendChild(columnsContainer);

        return this.wrapper;
    }

    updateLayout() {
        const layouts: Record<string, Array<{ width: number }>> = {
            '2-cols': [{ width: 50 }, { width: 50 }],
            '3-cols': [{ width: 33.33 }, { width: 33.33 }, { width: 33.33 }],
            '2-1': [{ width: 66.66 }, { width: 33.33 }],
            '1-2': [{ width: 33.33 }, { width: 66.66 }],
        };

        const newLayout = layouts[this.data.layout] || layouts['2-cols'];

        // Ajuster le nombre de colonnes si nécessaire
        while (this.data.columns.length < newLayout.length) {
            this.data.columns.push({
                blocks: [
                    {
                        type: 'paragraph',
                        data: { text: `Colonne ${this.data.columns.length + 1} - Ajoutez vos blocs ici...` },
                    },
                ],
            });
        }

        // Supprimer les colonnes en trop
        if (this.data.columns.length > newLayout.length) {
            this.data.columns = this.data.columns.slice(0, newLayout.length);
        }
    }

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    renderColumns(container: any) {
        container.innerHTML = '';

        // Nettoyer les anciens éditeurs
        this.columnEditors.forEach((editor) => {
            if (editor && editor.destroy) {
                editor.destroy();
            }
        });
        this.columnEditors = [];

        container.style.cssText = `
            display: grid;
            grid-template-columns: ${this.data.columns.map(() => '1fr').join(' ')};
            gap: 16px;
            min-height: 200px;
            margin-top: 8px;
        `;

        this.data.columns.forEach((column: any, index: number) => {
            const columnDiv = document.createElement('div');
            columnDiv.classList.add('advanced-column');
            columnDiv.style.cssText = `
                border: 1px solid #e1e5e9;
                border-radius: 8px;
                padding: 8px;
                background: #fafafa;
                transition: all 0.2s ease;
                min-height: 200px;
            `;

            // Créer un conteneur pour l'éditeur de cette colonne
            const editorContainer = document.createElement('div');
            editorContainer.id = `column-editor-${index}-${Date.now()}`;
            editorContainer.style.cssText = `
                min-height: 150px;
                background: white;
                border-radius: 4px;
                padding: 8px;
            `;

            columnDiv.appendChild(editorContainer);
            container.appendChild(columnDiv);

            // Créer un mini-éditeur pour cette colonne
            setTimeout(() => {
                try {
                    const columnEditor = new EditorJS({
                        holder: editorContainer.id,
                        tools: {
                            paragraph: {
                                // @ts-expect-error - EditorJS types compatibility
                                class: Paragraph,
                                inlineToolbar: ['bold', 'italic', 'Color', 'Marker', 'InlineLink'],
                                tunes: ['alignmentTune'],
                            },
                            Color: {
                                class: CustomColorTool,
                                config: {
                                    colors: [
                                        ...(props.siteColors
                                            ? [
                                                  props.siteColors.primary_color,
                                                  props.siteColors.secondary_color,
                                                  props.siteColors.accent_color,
                                              ].filter(Boolean)
                                            : []),
                                        '#000000',
                                        '#FFFFFF',
                                        '#6b7280',
                                        '#ef4444',
                                        '#f97316',
                                        '#eab308',
                                        '#22c55e',
                                        '#3b82f6',
                                        '#8b5cf6',
                                        '#ec4899',
                                    ],
                                    defaultColor: props.siteColors?.primary_color || '#4E8D44',
                                },
                            },
                            InlineLink: {
                                class: InlineLinkTool,
                            },
                            header: {
                                // @ts-expect-error - EditorJS types compatibility
                                class: Header,
                                config: {
                                    levels: [1, 2, 3, 4, 5, 6], // Ajout du H1
                                    defaultLevel: 2,
                                },
                                inlineToolbar: ['bold', 'italic', 'Color', 'Marker', 'InlineLink'],
                                tunes: ['alignmentTune'],
                            },
                            list: {
                                // @ts-expect-error - EditorJS types compatibility
                                class: List,
                                inlineToolbar: ['bold', 'italic', 'Color', 'Marker', 'InlineLink'],
                                tunes: ['alignmentTune'],
                            },
                            code: Code,
                            image: {
                                class: CustomImageTool,
                                config: {
                                    endpoints: {
                                        byFile: '/articles/upload-image',
                                    },
                                    field: 'image',
                                    types: 'image/*',
                                    additionalRequestHeaders: {
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                                    },
                                    captionPlaceholder: 'Entrez une légende...',
                                    buttonContent: 'Sélectionner une image',
                                    actions: [
                                        {
                                            name: 'stretch',
                                            icon: '<svg width="17" height="10" viewBox="0 0 17 10" xmlns="http://www.w3.org/2000/svg"><path d="M13.568 5.925H4.056l1.703 1.703a1.125 1.125 0 0 1-1.59 1.591L.962 6.014A1.069 1.069 0 0 1 .588 4.26L4.38.469a1.069 1.069 0 0 1 1.512 1.511L4.084 3.787h9.606l-1.85-1.85a1.069 1.069 0 1 1 1.512-1.51l3.792 3.791a1.069 1.069 0 0 1-.475 1.788L13.514 9.16a1.125 1.125 0 0 1-1.59-1.591l1.644-1.644z"/></svg>',
                                            title: "Étirer l'image",
                                            toggle: true,
                                        },
                                        {
                                            name: 'withBorder',
                                            icon: '<svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M15.8 10.592v2.043h2.35v2.138H15.8v2.232h-2.25v-2.232h-2.4v-2.138h2.4v-2.043h2.25zm1.9-8.025v2.138h-2.35v2.232h-2.25v-2.232h-2.4V2.567h2.4V.429h2.25v2.138h2.35z"/><path d="M4.05 8.967h2.35v2.043h2.25v2.138H6.4v2.232H4.05v-2.232H1.65v-2.138h2.4V8.967z"/></svg>',
                                            title: 'Ajouter une bordure',
                                            toggle: true,
                                        },
                                        {
                                            name: 'withBackground',
                                            icon: '<svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10.043 8.265l3.183-3.183h-2.924L4.75 10.636v2.923l4.15-4.15v2.351l-2.158 2.159H8.9v2.137H4.7c-1.215 0-2.2-.936-2.2-2.09v-8.93c0-1.154.985-2.09 2.2-2.09h10.663c1.215 0 2.2.936 2.2 2.09v3.183L15.4 9.26V6.334c0-.607-.49-1.098-1.097-1.098H5.802c-.608 0-1.098.49-1.098 1.098v7.132c0 .608.49 1.098 1.098 1.098H10.043V8.265z"/></svg>',
                                            title: 'Ajouter un arrière-plan',
                                            toggle: true,
                                        },
                                        {
                                            name: 'small',
                                            icon: '<svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><rect x="2" y="2" width="6" height="6" rx="1" fill="currentColor"/><rect x="2" y="10" width="16" height="2" rx="1" fill="currentColor"/><rect x="2" y="14" width="16" height="2" rx="1" fill="currentColor"/></svg>',
                                            title: 'Petite taille (25%)',
                                            toggle: true,
                                        },
                                        {
                                            name: 'medium',
                                            icon: '<svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><rect x="2" y="2" width="10" height="8" rx="1" fill="currentColor"/><rect x="2" y="12" width="16" height="2" rx="1" fill="currentColor"/><rect x="2" y="16" width="16" height="2" rx="1" fill="currentColor"/></svg>',
                                            title: 'Taille moyenne (50%)',
                                            toggle: true,
                                        },
                                        {
                                            name: 'large',
                                            icon: '<svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><rect x="2" y="2" width="16" height="10" rx="1" fill="currentColor"/><rect x="2" y="14" width="16" height="2" rx="1" fill="currentColor"/><rect x="2" y="17" width="16" height="1" rx="0.5" fill="currentColor"/></svg>',
                                            title: 'Grande taille (75%)',
                                            toggle: true,
                                        },
                                    ],
                                },
                            },
                            embed: {
                                class: Embed,
                                inlineToolbar: true,
                                config: {
                                    services: {
                                        youtube: true,
                                        vimeo: true,
                                    },
                                },
                            },
                            linkTool: {
                                class: LinkTool,
                                config: {
                                    endpoint: '/articles/fetch-url-metadata',
                                },
                            },
                            button: {
                                class: ButtonTool,
                                tunes: ['alignmentTune'],
                            },
                            Marker: {
                                class: CustomColorTool,
                                config: {
                                    colors: ['#FFBF00', '#FFD700', '#FFFF00'],
                                    defaultColor: '#FFBF00',
                                    icon: `<svg fill="#000000" height="20" width="20" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg"><g><path d="M17.6,6L6.9,16.7c-0.2,0.2-0.3,0.4-0.3,0.6L6,23.9c0,0.3,0.1,0.6,0.3,0.8C6.5,24.9,6.7,25,7,25c0,0,0.1,0,0.1,0l6.6-0.6 c0.2,0,0.5-0.1,0.6-0.3L25,13.4L17.6,6z"></path><path d="M26.4,12l1.4-1.4c1.2-1.2,1.1-3.1-0.1-4.3l-3-3c-0.6-0.6-1.3-0.9-2.2-0.9c-0.8,0-1.6,0.3-2.2,0.9L19,4.6L26.4,12z"></path></g><g><path d="M28,29H4c-0.6,0-1-0.4-1-1s0.4-1,1-1h24c0.6,0,1,0.4,1,1S28.6,29,28,29z"></path></g></svg>`,
                                },
                            },
                            alignmentTune: {
                                class: AlignmentTuneTool,
                                config: {
                                    default: 'left',
                                    blocks: {
                                        header: 'left',
                                        list: 'left',
                                        paragraph: 'left',
                                        button: 'left',
                                    },
                                },
                            },
                            attaches: {
                                class: AttachesTool,
                                config: {
                                    endpoint: '/articles/upload-file',
                                    field: 'file',
                                    types: '*',
                                    buttonText: 'Sélectionner un fichier',
                                    errorMessage: "Erreur lors de l'upload du fichier",
                                    additionalRequestHeaders: {
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                                    },
                                },
                            },
                        },
                        data: {
                            blocks: column.blocks || [],
                        },
                        placeholder: `Contenu de la colonne ${index + 1}...`,
                        onChange: async () => {
                            try {
                                const outputData = await columnEditor.save();
                                this.data.columns[index].blocks = outputData.blocks;
                            } catch (error) {
                                console.error('Erreur lors de la sauvegarde de la colonne:', error);
                            }
                        },
                    });

                    this.columnEditors.push(columnEditor);
                } catch (error) {
                    console.error("Erreur lors de la création de l'éditeur de colonne:", error);
                    // Fallback vers un textarea simple
                    editorContainer.innerHTML = `
                        <textarea 
                            placeholder="Contenu de la colonne ${index + 1}..."
                            style="width: 100%; min-height: 150px; border: none; resize: vertical; outline: none;"
                        ></textarea>
                    `;
                }
            }, 100);
        });
    }

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    override save() {
        return {
            columns: this.data.columns,
            layout: this.data.layout,
        };
    }

    static override get sanitize() {
        return {
            columns: {
                blocks: {},
            },
            layout: {},
        };
    }

    // Nettoyer les éditeurs quand le bloc est supprimé
    destroy() {
        this.columnEditors.forEach((editor) => {
            if (editor && editor.destroy) {
                try {
                    editor.destroy();
                } catch (error) {
                    console.error("Erreur lors de la destruction de l'éditeur:", error);
                }
            }
        });
        this.columnEditors = [];
    }
}

const props = defineProps({
    initialContent: {
        type: String,
        default: '',
    },
    siteColors: {
        type: Object,
        default: () => ({
            primary_color: '#4E8D44',
            secondary_color: '#6b7280',
            accent_color: '#10b981',
        }),
    },
    aiWordCount: {
        type: Number,
        default: 700,
    },
});

const emit = defineEmits(['update:content', 'update:wordCount', 'update:aiWordCount']);
const editorContainer = ref<HTMLElement | null>(null);
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const editor = ref<any | null>(null);
const wordCount = ref(0);

// Utiliser les couleurs du site passées en props ou les valeurs par défaut
const siteColors = computed(() => ({
    primary: props.siteColors?.primary_color || '#4E8D44',
    secondary: props.siteColors?.secondary_color || '#6b7280',
    accent: props.siteColors?.accent_color || '#10b981',
}));

// Fonction pour compter les mots dans le contenu EditorJS
const countWords = (editorData: any) => {
    if (!editorData || !editorData.blocks) return 0;
    
    let totalWords = 0;
    
    editorData.blocks.forEach((block: any) => {
        let text = '';
        
        switch (block.type) {
            case 'paragraph':
            case 'header':
                text = block.data.text || '';
                break;
            case 'list':
                if (block.data.items) {
                    text = block.data.items.join(' ');
                }
                break;
            case 'quote':
                text = (block.data.text || '') + ' ' + (block.data.caption || '');
                break;
            case 'button':
                text = block.data.text || '';
                break;
            case 'columns':
                if (block.data.columns) {
                    block.data.columns.forEach((column: any) => {
                        if (column.blocks) {
                            const columnData = { blocks: column.blocks };
                            totalWords += countWords(columnData);
                        }
                    });
                }
                return; // On return ici car on a déjà compté dans la récursion
            default:
                break;
        }
        
        // Nettoyer le HTML et compter les mots
        if (text) {
            const cleanText = text
                .replace(/<[^>]*>/g, '') // Supprimer les balises HTML
                .replace(/&nbsp;/g, ' ') // Remplacer les espaces insécables
                .replace(/\s+/g, ' ') // Remplacer les espaces multiples
                .trim();
            
            if (cleanText) {
                const words = cleanText.split(' ').filter(word => word.length > 0);
                totalWords += words.length;
            }
        }
    });
    
    return totalWords;
};

const initializeEditor = () => {
    if (!editorContainer.value) return;

    console.log('🚀 Initializing editor with site colors:', props.siteColors);
    console.log('🚀 ColorPlugin available:', CustomColorTool);

    // Créer la collection de couleurs de manière plus simple
    const siteSpecificColors = [props.siteColors?.primary_color, props.siteColors?.secondary_color, props.siteColors?.accent_color].filter(Boolean);

    const baseColors = ['#000000', '#FFFFFF', '#6b7280', '#ef4444', '#f97316', '#eab308', '#22c55e', '#3b82f6', '#8b5cf6', '#ec4899'];

    const finalColorCollection = [...siteSpecificColors, ...baseColors];

    console.log('🚀 Final color collection:', finalColorCollection);

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    let initialData: any = {
        blocks: [],
    };

    // Si le contenu initial est un JSON valide, l'utiliser, sinon créer un bloc par défaut
    if (props.initialContent) {
        try {
            initialData = JSON.parse(props.initialContent);
            if (!initialData.blocks) {
                initialData.blocks = [];
            }
        } catch {
            // Si le contenu n'est pas un JSON valide, créer un paragraphe avec le texte
            initialData = {
                blocks: [
                    {
                        type: 'paragraph',
                        data: {
                            text: props.initialContent,
                        },
                    },
                ],
            };
        }
    }

    // Configuration du plugin Color avec vérification
    const colorConfig = {
        colors: finalColorCollection,
        defaultColor: props.siteColors?.primary_color || '#4E8D44',
    };

    console.log('🎨 Color plugin config:', colorConfig);

    // Ajouter les couleurs du site comme data attributes sur le conteneur
    if (editorContainer.value) {
        editorContainer.value.setAttribute('data-primary-color', props.siteColors?.primary_color || '#4E8D44');
        editorContainer.value.setAttribute('data-secondary-color', props.siteColors?.secondary_color || '#6b7280');
        editorContainer.value.setAttribute('data-accent-color', props.siteColors?.accent_color || '#10b981');
    }

    editor.value = new EditorJS({
        holder: editorContainer.value,
        tools: {
            paragraph: {
                // @ts-expect-error - EditorJS types compatibility
                class: Paragraph,
                inlineToolbar: ['bold', 'italic', 'Color', 'Marker', 'InlineLink'],
                tunes: ['alignmentTune'],
            },
            Color: {
                class: CustomColorTool,
                config: colorConfig,
            },
            InlineLink: {
                class: InlineLinkTool,
            },
            header: {
                // @ts-expect-error - EditorJS types compatibility
                class: Header,
                config: {
                    levels: [1, 2, 3, 4, 5, 6], // Ajout du H1
                    defaultLevel: 2,
                },
                inlineToolbar: ['bold', 'italic', 'Color', 'Marker', 'InlineLink'],
                tunes: ['alignmentTune'],
            },
            list: {
                // @ts-expect-error - EditorJS types compatibility
                class: List,
                inlineToolbar: ['bold', 'italic', 'Color', 'Marker', 'InlineLink'],
                tunes: ['alignmentTune'],
            },
            code: Code,
            image: {
                class: CustomImageTool,
                config: {
                    endpoints: {
                        byFile: '/articles/upload-image',
                    },
                    field: 'image',
                    types: 'image/*',
                    additionalRequestHeaders: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                    captionPlaceholder: 'Entrez une légende...',
                    buttonContent: 'Sélectionner une image',
                    uploader: {
                        uploadByFile: async (file: File) => {
                            const formData = new FormData();
                            formData.append('image', file);

                            const response = await fetch('/articles/upload-image', {
                                method: 'POST',
                                body: formData,
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                                },
                            });

                            const result = await response.json();
                            return result;
                        },
                    },
                    actions: [
                        {
                            name: 'stretch',
                            icon: '<svg width="17" height="10" viewBox="0 0 17 10" xmlns="http://www.w3.org/2000/svg"><path d="M13.568 5.925H4.056l1.703 1.703a1.125 1.125 0 0 1-1.59 1.591L.962 6.014A1.069 1.069 0 0 1 .588 4.26L4.38.469a1.069 1.069 0 0 1 1.512 1.511L4.084 3.787h9.606l-1.85-1.85a1.069 1.069 0 1 1 1.512-1.51l3.792 3.791a1.069 1.069 0 0 1-.475 1.788L13.514 9.16a1.125 1.125 0 0 1-1.59-1.591l1.644-1.644z"/></svg>',
                            title: "Étirer l'image",
                            toggle: true,
                        },
                        {
                            name: 'withBorder',
                            icon: '<svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M15.8 10.592v2.043h2.35v2.138H15.8v2.232h-2.25v-2.232h-2.4v-2.138h2.4v-2.043h2.25zm1.9-8.025v2.138h-2.35v2.232h-2.25v-2.232h-2.4V2.567h2.4V.429h2.25v2.138h2.35z"/><path d="M4.05 8.967h2.35v2.043h2.25v2.138H6.4v2.232H4.05v-2.232H1.65v-2.138h2.4V8.967z"/></svg>',
                            title: 'Ajouter une bordure',
                            toggle: true,
                        },
                        {
                            name: 'withBackground',
                            icon: '<svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10.043 8.265l3.183-3.183h-2.924L4.75 10.636v2.923l4.15-4.15v2.351l-2.158 2.159H8.9v2.137H4.7c-1.215 0-2.2-.936-2.2-2.09v-8.93c0-1.154.985-2.09 2.2-2.09h10.663c1.215 0 2.2.936 2.2 2.09v3.183L15.4 9.26V6.334c0-.607-.49-1.098-1.097-1.098H5.802c-.608 0-1.098.49-1.098 1.098v7.132c0 .608.49 1.098 1.098 1.098H10.043V8.265z"/></svg>',
                            title: 'Ajouter un arrière-plan',
                            toggle: true,
                        },
                        {
                            name: 'small',
                            icon: '<svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><rect x="2" y="2" width="6" height="6" rx="1" fill="currentColor"/><rect x="2" y="10" width="16" height="2" rx="1" fill="currentColor"/><rect x="2" y="14" width="16" height="2" rx="1" fill="currentColor"/></svg>',
                            title: 'Petite taille (25%)',
                            toggle: true,
                        },
                        {
                            name: 'medium',
                            icon: '<svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><rect x="2" y="2" width="10" height="8" rx="1" fill="currentColor"/><rect x="2" y="12" width="16" height="2" rx="1" fill="currentColor"/><rect x="2" y="16" width="16" height="2" rx="1" fill="currentColor"/></svg>',
                            title: 'Taille moyenne (50%)',
                            toggle: true,
                        },
                        {
                            name: 'large',
                            icon: '<svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><rect x="2" y="2" width="16" height="10" rx="1" fill="currentColor"/><rect x="2" y="14" width="16" height="2" rx="1" fill="currentColor"/><rect x="2" y="17" width="16" height="1" rx="0.5" fill="currentColor"/></svg>',
                            title: 'Grande taille (75%)',
                            toggle: true,
                        },
                    ],
                },
            },
            embed: {
                class: Embed,
                inlineToolbar: true,
                config: {
                    services: {
                        youtube: true,
                        vimeo: true,
                    },
                },
            },
            linkTool: {
                class: LinkTool,
                config: {
                    endpoint: '/articles/fetch-url-metadata',
                },
            },
            button: {
                class: ButtonTool,
                tunes: ['alignmentTune'],
            },
            Marker: {
                class: CustomColorTool,
                config: {
                    colors: ['#FFBF00', '#FFD700', '#FFFF00'],
                    defaultColor: '#FFBF00',
                    icon: `<svg fill="#000000" height="20" width="20" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg"><g><path d="M17.6,6L6.9,16.7c-0.2,0.2-0.3,0.4-0.3,0.6L6,23.9c0,0.3,0.1,0.6,0.3,0.8C6.5,24.9,6.7,25,7,25c0,0,0.1,0,0.1,0l6.6-0.6 c0.2,0,0.5-0.1,0.6-0.3L25,13.4L17.6,6z"></path><path d="M26.4,12l1.4-1.4c1.2-1.2,1.1-3.1-0.1-4.3l-3-3c-0.6-0.6-1.3-0.9-2.2-0.9c-0.8,0-1.6,0.3-2.2,0.9L19,4.6L26.4,12z"></path></g><g><path d="M28,29H4c-0.6,0-1-0.4-1-1s0.4-1,1-1h24c0.6,0,1,0.4,1,1S28.6,29,28,29z"></path></g></svg>`,
                },
            },
            alignmentTune: {
                class: AlignmentTuneTool,
                config: {
                    default: 'left',
                    blocks: {
                        header: 'left',
                        list: 'left',
                        paragraph: 'left',
                        button: 'left',
                    },
                },
            },
            attaches: {
                class: AttachesTool,
                config: {
                    endpoint: '/articles/upload-file',
                    field: 'file',
                    types: '*',
                    buttonText: 'Sélectionner un fichier',
                    errorMessage: "Erreur lors de l'upload du fichier",
                    additionalRequestHeaders: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                },
            },
            columns: {
                // @ts-expect-error - EditorJS types are not fully compatible with our implementation
                class: AdvancedColumnsTool,
                tunes: ['alignmentTune'],
            },
        },
        data: initialData,
        onChange: async () => {
            const outputData = await editor.value?.save();
            if (outputData) {
                emit('update:content', JSON.stringify(outputData));
                
                // Compter les mots et émettre la mise à jour
                const currentWordCount = countWords(outputData);
                wordCount.value = currentWordCount;
                emit('update:wordCount', currentWordCount);
            }
        },
    });

    console.log('✅ Editor initialized successfully');
    
    // Compter les mots du contenu initial
    if (initialData && initialData.blocks) {
        const initialWordCount = countWords(initialData);
        wordCount.value = initialWordCount;
        emit('update:wordCount', initialWordCount);
    }
};

onMounted(() => {
    initializeEditor();
});

// Watcher pour recréer l'éditeur quand les couleurs du site changent
watch(
    () => props.siteColors,
    async (newColors, oldColors) => {
        console.log('🔄 Site colors changed!');
        console.log('🔄 Old colors:', oldColors);
        console.log('🔄 New colors:', newColors);

        // Vérifier si les couleurs ont vraiment changé
        if (JSON.stringify(newColors) !== JSON.stringify(oldColors) && editor.value) {
            console.log('🔄 Colors actually changed, recreating editor...');

            // Sauvegarder le contenu actuel
            const currentData = await editor.value.save();

            // Détruire l'éditeur actuel
            editor.value.destroy();
            editor.value = null;

            // Recréer l'éditeur avec les nouvelles couleurs
            // Attendre un tick pour s'assurer que l'ancien éditeur est complètement détruit
            setTimeout(() => {
                console.log('🔄 Initializing editor with new colors...');
                initializeEditor();

                // Restaurer le contenu si il y en avait un
                if (currentData && currentData.blocks && currentData.blocks.length > 0) {
                    editor.value?.render(currentData);
                }
            }, 100);
        } else {
            console.log('🔄 Colors unchanged or editor not ready');
        }
    },
    { deep: true, immediate: true },
);

onBeforeUnmount(() => {
    if (editor.value) {
        editor.value.destroy();
        editor.value = null;
    }
});

// Exposer l'instance de l'éditeur et le compteur de mots vers le composant parent
defineExpose({
    editor,
    wordCount
});

// Plugin de lien inline amélioré
class InlineLinkTool {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    api: any;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    config: any;
    button: HTMLElement | null = null;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    currentSelection: any = null;

    static get isInline() {
        return true;
    }

    static get title() {
        return 'Lien';
    }

    static get sanitize() {
        return {
            a: {
                href: true,
                target: true,
                rel: true,
            },
        };
    }

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    constructor({ api, config }: any) {
        this.api = api;
        this.config = config || {};
        this.setupLinkHoverSystem();
    }

    render() {
        this.button = document.createElement('button');
        (this.button as HTMLButtonElement).type = 'button';
        this.button.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M9 12l2 2l4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M10 9a3 3 0 1 1 0 6m4-6a3 3 0 1 1 0 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M12 1v6m0 6v6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
        `;
        this.button.classList.add('ce-inline-tool');
        this.button.title = 'Créer un lien';

        this.button.addEventListener('click', () => {
            this.showLinkDialog();
        });

        return this.button;
    }

    surround() {
        // Cette méthode sera appelée quand on clique sur le bouton
        return;
    }

    checkState() {
        const selection = this.api.selection.findParentTag('A');
        return !!selection;
    }

    // Nouveau système de survol pour les liens existants
    setupLinkHoverSystem() {
        // Attendre que le DOM soit prêt
        setTimeout(() => {
            this.addLinkHoverListeners();
        }, 1000);
    }

    addLinkHoverListeners() {
        // Observer les changements dans l'éditeur pour détecter les nouveaux liens
        const observer = new MutationObserver(() => {
            this.attachHoverToLinks();
        });

        // Observer l'éditeur
        const editorElement = document.querySelector('.codex-editor');
        if (editorElement) {
            observer.observe(editorElement, {
                childList: true,
                subtree: true,
            });
        }

        // Attacher immédiatement aux liens existants
        this.attachHoverToLinks();
    }

    attachHoverToLinks() {
        const links = document.querySelectorAll('.ce-block__content a[href]');
        links.forEach(link => {
            const htmlLink = link as HTMLElement;
            if (!htmlLink.dataset.hoverSetup) {
                htmlLink.dataset.hoverSetup = 'true';
                this.setupLinkHover(htmlLink);
            }
        });
    }

    setupLinkHover(link: HTMLElement) {
        let hoverTimeout: number;
        let tooltip: HTMLElement | null = null;

        link.addEventListener('mouseenter', () => {
            hoverTimeout = setTimeout(() => {
                tooltip = this.createLinkTooltip(link);
                document.body.appendChild(tooltip);
                this.positionTooltip(tooltip, link);
            }, 500) as unknown as number;
        });

        link.addEventListener('mouseleave', () => {
            clearTimeout(hoverTimeout);
            if (tooltip) {
                tooltip.remove();
                tooltip = null;
            }
        });

        // Empêcher la suppression du tooltip quand on survole le tooltip lui-même
        link.addEventListener('click', (e) => {
            if (e.ctrlKey || e.metaKey) {
                // Permettre Ctrl+Click pour ouvrir le lien
                return;
            }
            e.preventDefault();
            this.editExistingLink(link);
        });
    }

    createLinkTooltip(link: HTMLElement): HTMLElement {
        const href = link.getAttribute('href') || '';
        const tooltip = document.createElement('div');
        tooltip.className = 'link-hover-tooltip';
        tooltip.style.cssText = `
            position: fixed;
            background: white;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 8px 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 10000;
            font-size: 12px;
            color: #374151;
            max-width: 300px;
            word-break: break-all;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: tooltipAppear 0.2s ease-out;
        `;

        tooltip.innerHTML = `
            <span class="truncate">${href}</span>
            <button type="button" class="edit-link-btn" style="
                background: #3b82f6;
                color: white;
                border: none;
                border-radius: 4px;
                padding: 2px 6px;
                font-size: 10px;
                cursor: pointer;
                flex-shrink: 0;
                transition: background-color 0.2s ease;
            ">
                ✏️ Edit
            </button>
        `;

        const editBtn = tooltip.querySelector('.edit-link-btn');
        editBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            tooltip.remove();
            this.editExistingLink(link);
        });

        return tooltip;
    }

    positionTooltip(tooltip: HTMLElement, link: HTMLElement) {
        const linkRect = link.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();

        let left = linkRect.left + (linkRect.width / 2) - (tooltipRect.width / 2);
        let top = linkRect.top - tooltipRect.height - 8;

        // Ajuster si le tooltip sort de l'écran
        if (left < 10) left = 10;
        if (left + tooltipRect.width > window.innerWidth - 10) {
            left = window.innerWidth - tooltipRect.width - 10;
        }
        if (top < 10) {
            top = linkRect.bottom + 8;
        }

        tooltip.style.left = `${left}px`;
        tooltip.style.top = `${top}px`;
    }

    editExistingLink(link: HTMLElement) {
        const href = link.getAttribute('href') || '';
        const target = link.getAttribute('target') || '_self';
        const text = link.textContent || '';

        // Sélectionner le lien
        const range = document.createRange();
        range.selectNodeContents(link);
        const selection = window.getSelection();
        if (selection) {
            selection.removeAllRanges();
            selection.addRange(range);
        }

        // Montrer le dialog avec les valeurs pré-remplies
        this.showLinkDialog(href, target === '_blank', text);
    }

    showLinkDialog(existingUrl = '', existingNewTab = false, existingText = '') {
        const selection = window.getSelection();
        if (!selection || selection.rangeCount === 0) return;

        const range = selection.getRangeAt(0);
        let selectedText = existingText || range.toString();

        // Si on édite un lien existant et qu'il n'y a pas de texte sélectionné
        if (!selectedText && existingUrl) {
            const parentLink = range.commonAncestorContainer.parentElement;
            if (parentLink && parentLink.tagName === 'A') {
                selectedText = parentLink.textContent || '';
            }
        }

        if (!selectedText && !existingUrl) {
            alert('Veuillez sélectionner du texte pour créer un lien');
            return;
        }

        // Créer le modal de lien
        const modal = document.createElement('div');
        modal.className = 'inline-link-modal';
        modal.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border: 1px solid #e1e5e9;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            z-index: 10000;
            min-width: 400px;
            backdrop-filter: blur(10px);
        `;

        modal.innerHTML = `
            <div style="margin-bottom: 16px;">
                <h3 style="margin: 0 0 8px 0; font-size: 18px; font-weight: 600; color: #1f2937;">
                    🔗 ${existingUrl ? 'Modifier le lien' : 'Créer un lien'}
                </h3>
                <p style="margin: 0; color: #6b7280; font-size: 14px;">Texte: "${selectedText}"</p>
            </div>
            
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #374151; font-size: 14px;">URL du lien:</label>
                <input type="text" id="linkUrl" placeholder="https://exemple.com ou /page-interne" value="${existingUrl}"
                    style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                <div style="margin-top: 4px; font-size: 12px; color: #6b7280;">
                    💡 Liens internes: /article-slug ou externes: https://site.com
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; color: #374151;">
                    <input type="checkbox" id="openNewTab" ${existingNewTab ? 'checked' : ''} style="margin: 0;">
                    Ouvrir dans un nouvel onglet (recommandé pour liens externes)
                </label>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                ${existingUrl ? `
                <button type="button" id="deleteLink" 
                    style="padding: 8px 16px; background: #ef4444; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">
                    🗑️ Supprimer
                </button>
                ` : ''}
                <button type="button" id="cancelLink" 
                    style="padding: 8px 16px; background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 6px; cursor: pointer; font-size: 14px;">
                    Annuler
                </button>
                <button type="button" id="createLink" 
                    style="padding: 8px 16px; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500;">
                    ${existingUrl ? 'Modifier' : 'Créer'} le lien
                </button>
            </div>
        `;

        // Ajouter l'overlay
        const overlay = document.createElement('div');
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
        `;

        document.body.appendChild(overlay);
        document.body.appendChild(modal);

        // Focus sur le champ URL
        const urlInput = modal.querySelector('#linkUrl') as HTMLInputElement;
        urlInput.focus();
        urlInput.select();

        // Gestionnaires d'événements
        const createBtn = modal.querySelector('#createLink') as HTMLButtonElement;
        const cancelBtn = modal.querySelector('#cancelLink') as HTMLButtonElement;
        const deleteBtn = modal.querySelector('#deleteLink') as HTMLButtonElement;
        const newTabCheckbox = modal.querySelector('#openNewTab') as HTMLInputElement;

        const closeModal = () => {
            document.body.removeChild(overlay);
            document.body.removeChild(modal);
        };

        // Détecter automatiquement les liens externes
        urlInput.addEventListener('input', () => {
            const url = urlInput.value;
            if (url && (url.startsWith('http://') || url.startsWith('https://'))) {
                newTabCheckbox.checked = true;
            } else {
                newTabCheckbox.checked = false;
            }
        });

        createBtn.addEventListener('click', () => {
            const url = urlInput.value.trim();
            if (!url) {
                alert('Veuillez saisir une URL');
                return;
            }

            this.createOrUpdateLink(range, selectedText, url, newTabCheckbox.checked, existingUrl ? 'update' : 'create');
            closeModal();
        });

        if (deleteBtn) {
            deleteBtn.addEventListener('click', () => {
                this.removeLink(range);
                closeModal();
            });
        }

        cancelBtn.addEventListener('click', closeModal);
        overlay.addEventListener('click', closeModal);

        // Créer le lien avec Enter
        urlInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                createBtn.click();
            } else if (e.key === 'Escape') {
                closeModal();
            }
        });
    }

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    createOrUpdateLink(range: Range, text: string, url: string, openNewTab: boolean, action: 'create' | 'update') {
        if (action === 'update') {
            // Trouver le lien parent et le modifier
            const parentLink = range.commonAncestorContainer.parentElement;
            if (parentLink && parentLink.tagName === 'A') {
                parentLink.setAttribute('href', url);
                if (openNewTab) {
                    parentLink.setAttribute('target', '_blank');
                    parentLink.setAttribute('rel', 'noopener noreferrer');
                } else {
                    parentLink.removeAttribute('target');
                    parentLink.removeAttribute('rel');
                }
                return;
            }
        }

        // Créer un nouveau lien
        range.deleteContents();

        const link = document.createElement('a');
        link.href = url;
        link.textContent = text;
        
        if (openNewTab) {
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
        }

        // Ajouter des styles pour les liens
        link.style.cssText = `
            color: #3b82f6;
            text-decoration: underline;
            transition: color 0.2s ease;
        `;

        // Insérer le lien
        range.insertNode(link);

        // Sélectionner le nouveau lien
        const newRange = document.createRange();
        newRange.selectNodeContents(link);
        const selection = window.getSelection();
        if (selection) {
            selection.removeAllRanges();
            selection.addRange(newRange);
        }

        // Configurer le hover pour le nouveau lien
        setTimeout(() => {
            this.setupLinkHover(link);
        }, 100);
    }

    removeLink(range: Range) {
        const parentLink = range.commonAncestorContainer.parentElement;
        if (parentLink && parentLink.tagName === 'A') {
            const parent = parentLink.parentNode;
            if (parent) {
                while (parentLink.firstChild) {
                    parent.insertBefore(parentLink.firstChild, parentLink);
                }
                parent.removeChild(parentLink);
            }
        }
    }
}

// Fonction pour vérifier si on survole un titre
</script>

<template>
    <div>
        <div ref="editorContainer" class="container min-h-[300px] w-full rounded-md border p-4"></div>
        
        <!-- Compteur de mots -->
        <div class="word-counter">
            <div class="word-counter__content">
                <span class="word-counter__icon">📝</span>
                <span class="word-counter__text">{{ wordCount }} mot{{ wordCount > 1 ? 's' : '' }}</span>
                <div class="word-counter__bar">
                    <div 
                        class="word-counter__progress" 
                        :style="{ width: Math.min((wordCount / props.aiWordCount) * 100, 100) + '%' }"
                    ></div>
                </div>
                <span class="word-counter__target">Objectif IA: {{ props.aiWordCount }} mots</span>
            </div>
        </div>
    </div>
</template>

<style>
/* Styles personnalisés pour l'éditeur */
.codex-editor {
    padding: 0 !important;
}
.ce-toolbar__content,
.ce-block__content {
    max-width: 100% !important;
}

/* Styles pour les boutons personnalisés (Block Tool) - Version améliorée */
.button-tool {
    padding: 16px;
    border: 1px solid #e1e5e9;
    border-radius: 12px;
    background: #f8f9fa;
    margin: 12px 0;
    display: block !important; /* Forcer l'affichage */
    visibility: visible !important;
}

.button-tool__preview {
    text-align: center;
    margin-bottom: 16px;
    padding: 12px;
    background: white;
    border-radius: 8px;
    border: 1px solid #e1e5e9;
    display: block !important;
}

.button-tool__btn {
    display: inline-block !important;
    padding: 12px 24px;
    font-size: 16px;
    font-weight: 600;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: center;
    text-decoration: none;
    border: 2px solid transparent;
    min-width: 160px;
    visibility: visible !important;
}

.button-tool__form {
    display: grid;
    gap: 16px;
}

.button-tool__group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.button-tool__label {
    font-size: 14px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 4px;
}

.button-tool__input,
.button-tool__select {
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.2s ease;
    background: white;
}

.button-tool__input:focus,
.button-tool__select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    outline: none;
}

.button-tool__suggestions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 8px;
    margin-top: 8px;
}

.button-tool__suggestion {
    padding: 8px 12px;
    border: 1px solid #e1e5e9;
    border-radius: 6px;
    background: white;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.2s ease;
    text-align: left;
}

.button-tool__suggestion:hover {
    border-color: #3b82f6;
    background: #f0f9ff;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* Styles dynamiques pour les boutons basés sur les couleurs du site - Version étendue */
.button-tool__btn--primary {
    background-color: v-bind('siteColors?.primary || "#4E8D44"');
    color: white;
    border-color: v-bind('siteColors?.primary || "#4E8D44"');
}

.button-tool__btn--primary:hover {
    background-color: color-mix(in srgb, v-bind('siteColors?.primary || "#4E8D44"') 85%, black);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.button-tool__btn--secondary {
    background-color: v-bind('siteColors?.secondary || "#6b7280"');
    color: white;
    border-color: v-bind('siteColors?.secondary || "#6b7280"');
}

.button-tool__btn--secondary:hover {
    background-color: color-mix(in srgb, v-bind('siteColors?.secondary || "#6b7280"') 85%, black);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.button-tool__btn--success {
    background-color: #22c55e;
    color: white;
    border-color: #22c55e;
}

.button-tool__btn--success:hover {
    background-color: #16a34a;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
}

.button-tool__btn--warning {
    background-color: #f59e0b;
    color: white;
    border-color: #f59e0b;
}

.button-tool__btn--warning:hover {
    background-color: #d97706;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

.button-tool__btn--danger {
    background-color: #ef4444;
    color: white;
    border-color: #ef4444;
}

.button-tool__btn--danger:hover {
    background-color: #dc2626;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.button-tool__btn--outline {
    background-color: transparent;
    color: v-bind('siteColors?.primary || "#4E8D44"');
    border-color: v-bind('siteColors?.primary || "#4E8D44"');
}

.button-tool__btn--outline:hover {
    background-color: v-bind('siteColors?.primary || "#4E8D44"');
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Animation pour l'apparition des boutons */
@keyframes buttonAppear {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.button-tool {
    animation: buttonAppear 0.3s ease-out;
}

/* Responsive pour les boutons */
@media (max-width: 768px) {
    .button-tool__suggestions {
        grid-template-columns: 1fr;
    }
    
    .button-tool__btn {
        width: 100%;
        padding: 14px 20px;
        font-size: 15px;
    }
}

/* Styles pour l'alignement du texte appliqué aux blocs */
.text-left {
    text-align: left;
}

.text-center {
    text-align: center;
}

.text-right {
    text-align: right;
}

.text-justify {
    text-align: justify;
}

/* Styles pour les boutons et menus déroulants INLINE */
.ce-inline-toolbar {
    /* Style de base de la barre d'outils inline si nécessaire */
}

/* Conteneur pour chaque bouton inline qui a un menu déroulant */
.ce-inline-alignment-container {
    position: relative; /* Essentiel pour positionner le menu enfant */
    display: inline-block; /* Pour que le conteneur s'adapte au bouton */
}

/* Styles pour les menus déroulants (options d'alignement) */
.ce-alignment-options {
    position: absolute;
    top: 100%; /* Positionne le menu juste en dessous du conteneur parent */
    left: 0;
    z-index: 999; /* Très haut z-index pour éviter les superpositions */
    background: white;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    padding: 10px;
    display: none; /* Caché par défaut */
    min-width: 180px;
    margin-top: 5px; /* Petit espace entre le bouton et le menu */
}

/* Afficher le menu quand il est actif */
.ce-alignment-options.active {
    display: block !important; /* Forcer l'affichage */
}

/* Styles pour les options d'alignement */
.ce-alignment-option {
    width: 100%;
    padding: 8px 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    border: none;
    background: none;
    border-radius: 4px;
    transition: background-color 0.2s ease;
}

.ce-alignment-option:hover {
    background-color: #f0f0f0;
}

.ce-alignment-option svg {
    width: 18px;
    height: 18px;
    fill: currentColor; /* Utiliser la couleur du texte pour les icônes */
}

/* Surcharge potentielle des styles de la barre d'outils inline par défaut si nécessaire */
.ce-inline-tool {
    margin: 0 2px; /* Petit espace entre les boutons inline */
}

/* Styles pour les images EditorJS */
.image-tool--stretched .cdx-block {
    max-width: none;
}

.image-tool--withBorder img {
    border: 1px solid #e1e5e9;
    border-radius: 8px;
}

.image-tool--withBackground {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
}

.image-tool--withBackground img {
    max-width: 100%;
    height: auto;
}

/* Styles pour les tailles d'image */
.image-tool--small {
    max-width: 25%;
    margin: 0 auto;
}

.image-tool--small img {
    width: 100%;
    height: auto;
}

.image-tool--medium {
    max-width: 50%;
    margin: 0 auto;
}

.image-tool--medium img {
    width: 100%;
    height: auto;
}

.image-tool--large {
    max-width: 75%;
    margin: 0 auto;
}

.image-tool--large img {
    width: 100%;
    height: auto;
}

/* Combinaisons de styles */
.image-tool--small.image-tool--withBorder img,
.image-tool--medium.image-tool--withBorder img,
.image-tool--large.image-tool--withBorder img {
    border: 1px solid #e1e5e9;
    border-radius: 8px;
}

.image-tool--small.image-tool--withBackground,
.image-tool--medium.image-tool--withBackground,
.image-tool--large.image-tool--withBackground {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
}

/* Styles pour le contrôle de largeur personnalisé */
.image-width-control {
    margin-top: 10px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e1e5e9;
    transition: all 0.2s ease;
}

.image-width-control:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.image-width-control label {
    display: block;
    margin-bottom: 8px;
    font-size: 14px;
    font-weight: 500;
    color: #374151;
}

.image-width-control input[type='range'] {
    width: 100%;
    height: 6px;
    border-radius: 3px;
    background: #e2e8f0;
    outline: none;
    -webkit-appearance: none;
    appearance: none;
    cursor: pointer;
}

.image-width-control input[type='range']::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #3b82f6;
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    transition: all 0.2s ease;
}

.image-width-control input[type='range']::-webkit-slider-thumb:hover {
    background: #2563eb;
    transform: scale(1.1);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.image-width-control input[type='range']::-moz-range-thumb {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #3b82f6;
    cursor: pointer;
    border: none;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    transition: all 0.2s ease;
}

.image-width-control input[type='range']::-moz-range-thumb:hover {
    background: #2563eb;
    transform: scale(1.1);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.image-width-control .slider-container {
    display: flex;
    align-items: center;
    gap: 10px;
}

.image-width-control .value-display {
    font-size: 14px;
    color: #6b7280;
    font-weight: 500;
    min-width: 40px;
    text-align: right;
}

.image-width-control input[type='text'] {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.2s;
}

.image-width-control input[type='text']:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    outline: none;
}

/* Styles pour le block de colonnes */
.advanced-columns-tool {
    border: 1px solid #e1e5e9;
    border-radius: 8px;
    padding: 16px;
    background: #ffffff;
    margin: 8px 0;
}

.columns-layout-selector {
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f3f4f6;
}

.columns-layout-selector label {
    display: block;
    margin-bottom: 8px;
    font-size: 14px;
    font-weight: 500;
    color: #374151;
}

.columns-layout-selector select {
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: white;
    font-size: 14px;
    cursor: pointer;
    transition: border-color 0.2s;
}

.columns-layout-selector select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    outline: none;
}

.advanced-columns-container {
    display: grid;
    gap: 16px;
    min-height: 120px;
}

.advanced-columns-container .advanced-column {
    border: 1px solid #e1e5e9;
    border-radius: 8px;
    padding: 12px;
    background: #fafafa;
    transition: all 0.2s ease;
    position: relative;
}

.advanced-columns-container .advanced-column:hover {
    border-color: #cbd5e1;
}

.advanced-columns-container .advanced-column textarea {
    width: 100%;
    min-height: 100px;
    border: none;
    background: transparent;
    resize: vertical;
    outline: none;
    font-size: 14px;
    line-height: 1.5;
    font-family: inherit;
    color: #374151;
}

.advanced-columns-container .advanced-column textarea::placeholder {
    color: #9ca3af;
    font-style: italic;
}

/* Responsive pour les colonnes */
@media (max-width: 768px) {
    .advanced-columns-container {
        grid-template-columns: 1fr !important;
        gap: 12px;
    }

    .advanced-columns-container .advanced-column {
        padding: 16px;
    }

    .advanced-columns-container .advanced-column textarea {
        min-height: 80px;
    }
}

/* Animation pour les colonnes */
@keyframes columnAppear {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.advanced-columns-container .advanced-column {
    animation: columnAppear 0.3s ease-out;
}

/* Styles pour le bouton de contrôle d'image */
.custom-image-control-button {
    position: absolute;
    top: 8px;
    right: 8px;
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid #e1e5e9;
    border-radius: 6px;
    padding: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    z-index: 10;
    backdrop-filter: blur(4px);
}

.custom-image-control-button:hover {
    background: white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transform: translateY(-1px);
}

/* Styles pour le block de colonnes avancées */
.advanced-columns-tool .codex-editor {
    padding: 0 !important;
}

.advanced-columns-tool .ce-toolbar__content,
.advanced-columns-tool .ce-block__content {
    max-width: 100% !important;
}

.advanced-columns-tool .ce-block {
    margin: 8px 0 !important;
}

.advanced-columns-tool .ce-toolbar {
    margin-left: 0 !important;
}

.advanced-column .codex-editor {
    border: none !important;
    background: transparent !important;
}

.advanced-column .ce-toolbar__plus {
    color: #3b82f6 !important;
}

.advanced-column .ce-toolbar__plus:hover {
    background: #f3f4f6 !important;
}

/* Styles responsive pour les éditeurs dans les colonnes */
@media (max-width: 768px) {
    .advanced-columns-container .advanced-column {
        margin-bottom: 16px;
    }

    .advanced-column .codex-editor {
        min-height: 120px !important;
    }
}

/* Styles pour différencier les niveaux de titres dans l'éditeur avec indicateurs visuels */

/* Styles de base pour tous les titres */
.ce-block__content .ce-header {
    font-weight: 600 !important;
    line-height: 1.3 !important;
    margin: 16px 0 12px 0 !important;
    transition: all 0.2s ease !important;
    position: relative !important;
    padding-left: 45px !important; /* Espace pour l'indicateur */
}

/* Indicateur visuel du niveau de titre */
.ce-block__content .ce-header::before {
    content: "H" !important;
    position: absolute !important;
    left: 0 !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    background: v-bind('siteColors?.primary || "#4E8D44"') !important;
    color: white !important;
    font-size: 10px !important;
    font-weight: bold !important;
    padding: 2px 6px !important;
    border-radius: 12px !important;
    font-family: monospace !important;
    min-width: 24px !important;
    text-align: center !important;
    z-index: 1 !important;
}

/* Contenus spécifiques pour chaque niveau */
.ce-block__content h1.ce-header::before {
    content: "H1" !important;
    background: v-bind('siteColors?.primary || "#4E8D44"') !important;
}

.ce-block__content h2.ce-header::before {
    content: "H2" !important;
    background: v-bind('siteColors?.primary || "#4E8D44"') !important;
}

.ce-block__content h3.ce-header::before {
    content: "H3" !important;
    background: v-bind('siteColors?.primary || "#4E8D44"') !important;
}

.ce-block__content h4.ce-header::before {
    content: "H4" !important;
    background: v-bind('siteColors?.primary || "#4E8D44"') !important;
}

.ce-block__content h5.ce-header::before {
    content: "H5" !important;
    background: v-bind('siteColors?.primary || "#4E8D44"') !important;
}

.ce-block__content h6.ce-header::before {
    content: "H6" !important;
    background: v-bind('siteColors?.primary || "#4E8D44"') !important;
}

/* Effet hover pour les titres */
.ce-block__content .ce-header:hover {
    transform: translateX(2px) !important;
    cursor: text !important;
}

.ce-block__content .ce-header:hover::before {
    transform: translateY(-50%) scale(1.1) !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2) !important;
}

/* H1 - Titre principal de page */
.ce-block__content h1.ce-header {
    font-size: 32px !important;
    color: v-bind('siteColors?.primary || "#4E8D44"') !important;
    margin-top: 0 !important;
    margin-bottom: 24px !important;
    font-weight: 700 !important;
}

/* H2 - Titre principal de section */
.ce-block__content h2.ce-header {
    font-size: 28px !important;
    color: v-bind('siteColors?.primary || "#4E8D44"') !important;
    margin-top: 24px !important;
    margin-bottom: 16px !important;
}

/* H3 - Sous-titre de section */
.ce-block__content h3.ce-header {
    font-size: 22px !important;
    color: #374151 !important;
    margin-top: 20px !important;
    margin-bottom: 12px !important;
}

/* H4 - Titre de sous-section */
.ce-block__content h4.ce-header {
    font-size: 18px !important;
    color: #4b5563 !important;
    margin-top: 16px !important;
    margin-bottom: 10px !important;
    font-weight: 500 !important;
    text-decoration: underline !important;
    text-decoration-color: v-bind('siteColors?.accent || "#10b981"') !important;
    text-decoration-thickness: 2px !important;
    text-underline-offset: 4px !important;
}

/* H5 - Petit titre */
.ce-block__content h5.ce-header {
    font-size: 16px !important;
    color: #6b7280 !important;
    margin-top: 14px !important;
    margin-bottom: 8px !important;
    font-weight: 500 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
}

/* H6 - Le plus petit titre */
.ce-block__content h6.ce-header {
    font-size: 14px !important;
    color: #9ca3af !important;
    margin-top: 12px !important;
    margin-bottom: 6px !important;
    font-weight: 400 !important;
    font-style: italic !important;
}

/* Styles pour les titres dans les colonnes également */
.advanced-column .ce-block__content .ce-header {
    padding-left: 35px !important; /* Espace réduit dans les colonnes */
}

.advanced-column .ce-block__content .ce-header::before {
    content: "H" !important;
    position: absolute !important;
    left: 0 !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    background: v-bind('siteColors?.primary || "#4E8D44"') !important;
    color: white !important;
    font-size: 9px !important;
    font-weight: bold !important;
    padding: 1px 4px !important;
    border-radius: 8px !important;
    font-family: monospace !important;
    min-width: 20px !important;
    text-align: center !important;
}

.advanced-column .ce-block__content h1.ce-header {
    font-size: 24px !important;
    color: v-bind('siteColors?.primary || "#4E8D44"') !important;
}

.advanced-column .ce-block__content h2.ce-header {
    font-size: 20px !important;
    color: v-bind('siteColors?.primary || "#4E8D44"') !important;
}

.advanced-column .ce-block__content h3.ce-header {
    font-size: 18px !important;
    color: #374151 !important;
}

.advanced-column .ce-block__content h4.ce-header {
    font-size: 16px !important;
    color: #4b5563 !important;
    text-decoration: underline !important;
    text-decoration-color: v-bind('siteColors?.accent || "#10b981"') !important;
}

.advanced-column .ce-block__content h5.ce-header {
    font-size: 14px !important;
    color: #6b7280 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.3px !important;
}

.advanced-column .ce-block__content h6.ce-header {
    font-size: 13px !important;
    color: #9ca3af !important;
    font-style: italic !important;
}

/* Badges spécifiques pour les colonnes */
.advanced-column .ce-block__content h1.ce-header::before {
    content: "H1" !important;
    background: v-bind('siteColors?.primary || "#4E8D44"') !important;
}

.advanced-column .ce-block__content h2.ce-header::before {
    content: "H2" !important;
    background: v-bind('siteColors?.primary || "#4E8D44"') !important;
}

.advanced-column .ce-block__content h3.ce-header::before {
    content: "H3" !important;
    background: v-bind('siteColors?.primary || "#4E8D44"') !important;
}

.advanced-column .ce-block__content h4.ce-header::before {
    content: "H4" !important;
    background: v-bind('siteColors?.primary || "#4E8D44"') !important;
}

.advanced-column .ce-block__content h5.ce-header::before {
    content: "H5" !important;
    background: v-bind('siteColors?.primary || "#4E8D44"') !important;
}

.advanced-column .ce-block__content h6.ce-header::before {
    content: "H6" !important;
    background: v-bind('siteColors?.primary || "#4E8D44"') !important;
}

/* Styles pour le système de hover des liens */
.link-hover-tooltip {
    position: fixed !important;
    background: white !important;
    border: 1px solid #e1e5e9 !important;
    border-radius: 8px !important;
    padding: 8px 12px !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
    z-index: 10000 !important;
    font-size: 12px !important;
    color: #374151 !important;
    max-width: 300px !important;
    word-break: break-all !important;
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    animation: tooltipAppear 0.2s ease-out !important;
}

.link-hover-tooltip .edit-link-btn {
    background: #3b82f6 !important;
    color: white !important;
    border: none !important;
    border-radius: 4px !important;
    padding: 2px 6px !important;
    font-size: 10px !important;
    cursor: pointer !important;
    flex-shrink: 0 !important;
    transition: background-color 0.2s ease !important;
}

.link-hover-tooltip .edit-link-btn:hover {
    background: #2563eb !important;
}

/* Animation pour l'apparition du tooltip */
@keyframes tooltipAppear {
    from {
        opacity: 0;
        transform: translateY(-4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Améliorer les liens dans l'éditeur */
.ce-block__content a[href] {
    color: #3b82f6 !important;
    text-decoration: underline !important;
    transition: all 0.2s ease !important;
    cursor: pointer !important;
    position: relative !important;
}

.ce-block__content a[href]:hover {
    color: #2563eb !important;
    text-decoration: underline !important;
}

/* Styles pour les tooltips CTA (différents des liens) */
.cta-hover-tooltip {
    position: fixed !important;
    background: #f59e0b !important;
    color: white !important;
    border-radius: 8px !important;
    padding: 8px 12px !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
    z-index: 10000 !important;
    font-size: 12px !important;
    font-weight: 500 !important;
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    animation: tooltipAppear 0.2s ease-out !important;
    border: 2px solid #d97706 !important;
}

.cta-hover-tooltip .edit-cta-btn {
    background: white !important;
    color: #f59e0b !important;
    border: none !important;
    border-radius: 4px !important;
    padding: 2px 6px !important;
    font-size: 10px !important;
    cursor: pointer !important;
    flex-shrink: 0 !important;
    font-weight: bold !important;
    transition: all 0.2s ease !important;
}

.cta-hover-tooltip .edit-cta-btn:hover {
    background: #fef3c7 !important;
    transform: scale(1.05) !important;
}

/* Badge CTA pour différencier des liens */
.cta-badge {
    position: absolute !important;
    top: -8px !important;
    right: -8px !important;
    background: #f59e0b !important;
    color: white !important;
    font-size: 10px !important;
    font-weight: bold !important;
    padding: 2px 6px !important;
    border-radius: 8px !important;
    font-family: monospace !important;
    z-index: 2 !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
}

/* Styles pour le mode édition des CTA */
.button-tool__edit {
    border: 2px dashed #f59e0b !important;
    border-radius: 12px !important;
    padding: 16px !important;
    background: #fef3c7 !important;
}

.button-tool__save {
    background: #22c55e !important;
    color: white !important;
    border: none !important;
    border-radius: 6px !important;
    padding: 8px 16px !important;
    font-size: 14px !important;
    font-weight: 500 !important;
    cursor: pointer !important;
    transition: background-color 0.2s ease !important;
}

.button-tool__save:hover {
    background: #16a34a !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
}

/* Styles pour le compteur de mots */
.word-counter {
    margin-top: 16px;
    padding: 12px 16px;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.word-counter__content {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.word-counter__icon {
    font-size: 18px;
    filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.1));
}

.word-counter__text {
    font-size: 14px;
    font-weight: 600;
    color: #374151;
    min-width: 80px;
}

.word-counter__bar {
    flex: 1;
    min-width: 120px;
    height: 8px;
    background: #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
    position: relative;
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.1);
}

.word-counter__progress {
    height: 100%;
    background: linear-gradient(90deg, v-bind('siteColors?.primary || "#4E8D44"') 0%, #22c55e 100%);
    border-radius: 4px;
    transition: width 0.3s ease;
    position: relative;
    overflow: hidden;
}

.word-counter__progress::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg, transparent 0%, rgba(255, 255, 255, 0.3) 50%, transparent 100%);
    animation: shimmer 2s infinite;
}

.word-counter__target {
    font-size: 12px;
    color: #6b7280;
    font-weight: 500;
    white-space: nowrap;
}

/* Animation pour l'effet de brillance */
@keyframes shimmer {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
    }
}

/* Responsive pour le compteur */
@media (max-width: 640px) {
    .word-counter__content {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .word-counter__bar {
        width: 100%;
        min-width: unset;
    }
    
    .word-counter__text,
    .word-counter__target {
        align-self: stretch;
        text-align: center;
    }
}

/* États du compteur basés sur le pourcentage */
.word-counter__progress[style*="width: 100%"] {
    background: linear-gradient(90deg, #22c55e 0%, #16a34a 100%);
}

.word-counter__progress[style*="width: 9"]:not([style*="width: 100%"]),
.word-counter__progress[style*="width: 8"]:not([style*="width: 100%"]) {
    background: linear-gradient(90deg, #f59e0b 0%, #eab308 100%);
}

.word-counter:has(.word-counter__progress[style*="width: 100%"]) .word-counter__target {
    color: #16a34a;
    font-weight: 600;
}
</style>
