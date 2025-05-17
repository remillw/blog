<script setup lang="ts">
import Code from '@editorjs/code';
import EditorJS from '@editorjs/editorjs';
import Header from '@editorjs/header';
import ImageTool from '@editorjs/image';
import List from '@editorjs/list';
import { defineEmits, defineProps, onBeforeUnmount, onMounted, ref } from 'vue';
// @ts-expect-error - EditorJS embed plugin lacks TypeScript definitions
import Embed from '@editorjs/embed';
// @ts-expect-error - EditorJS link plugin lacks TypeScript definitions
import LinkTool from '@editorjs/link';
import Paragraph from '@editorjs/paragraph';
// @ts-expect-error - Color plugin lacks TypeScript definitions
import ColorPlugin from 'editorjs-text-color-plugin';
// @ts-expect-error - Alignment tune plugin lacks TypeScript definitions
import { usePage } from '@inertiajs/vue3';
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

// Classe pour le bouton personnalisé
class ButtonTool extends CustomTool {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    api: any;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    data: any;

    static get toolbox() {
        return {
            title: 'Bouton',
            icon: '<svg width="17" height="15" viewBox="0 0 17 15" xmlns="http://www.w3.org/2000/svg"><path d="M1 7.5C1 3.91015 3.91015 1 7.5 1H15.5C16.0523 1 16.5 1.44772 16.5 2V13C16.5 13.5523 16.0523 14 15.5 14H7.5C3.91015 14 1 11.0899 1 7.5Z" stroke="currentColor" fill="none"/><path d="M5.5 7.5H11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
        };
    }

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    constructor({ data, api }: any) {
        super({ data, api });
        this.api = api;
        this.data = {
            text: data?.text || 'Cliquez ici',
            link: data?.link || '',
            style: data?.style || 'primary',
        };
    }

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    override render() {
        const container = document.createElement('div');
        container.classList.add('button-tool');

        const button = document.createElement('button');
        button.classList.add('button-tool__btn');
        button.classList.add(`button-tool__btn--${this.data.style}`);
        button.textContent = this.data.text;
        button.contentEditable = 'false';

        if (this.data.link) {
            button.dataset.href = this.data.link;
        }

        const textInput = document.createElement('input');
        textInput.classList.add('button-tool__input');
        textInput.placeholder = 'Texte du bouton';
        textInput.value = this.data.text;
        textInput.addEventListener('input', () => {
            this.data.text = textInput.value;
            button.textContent = textInput.value;
        });

        const linkInput = document.createElement('input');
        linkInput.classList.add('button-tool__input');
        linkInput.placeholder = 'Lien (https://...)';
        linkInput.value = this.data.link;
        linkInput.addEventListener('input', () => {
            this.data.link = linkInput.value;
            button.dataset.href = linkInput.value;
        });

        const styleSelect = document.createElement('select');
        styleSelect.classList.add('button-tool__select');

        const options = [
            { value: 'primary', text: 'Principal' },
            { value: 'secondary', text: 'Secondaire' },
            { value: 'danger', text: 'Danger' },
            { value: 'success', text: 'Succès' },
        ];

        options.forEach((opt) => {
            const option = document.createElement('option');
            option.value = opt.value;
            option.textContent = opt.text;
            if (opt.value === this.data.style) {
                option.selected = true;
            }
            styleSelect.appendChild(option);
        });

        styleSelect.addEventListener('change', () => {
            button.classList.remove(`button-tool__btn--${this.data.style}`);
            this.data.style = styleSelect.value;
            button.classList.add(`button-tool__btn--${this.data.style}`);
        });

        const inputWrapper = document.createElement('div');
        inputWrapper.classList.add('button-tool__inputs');
        inputWrapper.appendChild(textInput);
        inputWrapper.appendChild(linkInput);
        inputWrapper.appendChild(styleSelect);

        container.appendChild(button);
        container.appendChild(inputWrapper);

        return container;
    }

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    override save() {
        return {
            text: this.data.text,
            link: this.data.link,
            style: this.data.style,
        };
    }

    static override get sanitize() {
        return {
            text: {},
            link: {},
            style: {},
        };
    }
}

const props = defineProps({
    initialContent: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:content']);
const editorContainer = ref<HTMLElement | null>(null);
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const editor = ref<any | null>(null);

// Récupérer les couleurs du site depuis la page Inertia
const page = usePage();
const siteColors = ref({
    primary: page.props.site?.primary_color || '#4E8D44',
    secondary: page.props.site?.secondary_color || '#6b7280',
    accent: page.props.site?.accent_color || '#10b981',
});

onMounted(() => {
    if (editorContainer.value) {
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

        // @ts-expect-error - EditorJS types are not fully compatible with our implementation
        editor.value = new EditorJS({
            holder: editorContainer.value,
            tools: {
                paragraph: {
                    // @ts-expect-error - EditorJS types are not fully compatible with our implementation
                    class: Paragraph,
                    inlineToolbar: ['bold', 'italic', 'Color', 'Marker'],
                    tunes: ['alignmentTune'],
                },
                Color: {
                    class: ColorPlugin,
                    config: {
                        colorCollections: [
                            '#EC7878',
                            '#9C27B0',
                            '#673AB7',
                            '#3F51B5',
                            '#0070FF',
                            '#03A9F4',
                            '#00BCD4',
                            '#4CAF50',
                            '#8BC34A',
                            '#CDDC39',
                            '#FFF',
                        ],
                        defaultColor: '#4E8D44',
                        type: 'text',
                        customPicker: true,
                    },
                },
                header: {
                    // @ts-expect-error - EditorJS types are not fully compatible with our implementation
                    class: Header,
                    config: {
                        levels: [2, 3, 4, 5, 6],
                        defaultLevel: 3,
                    },
                    inlineToolbar: ['bold', 'italic', 'Color', 'Marker'],
                    tunes: ['alignmentTune'],
                },
                list: {
                    // @ts-expect-error - EditorJS types are not fully compatible with our implementation
                    class: List,
                    inlineToolbar: ['bold', 'italic', 'Color', 'Marker'],
                    tunes: ['alignmentTune'],
                },
                code: Code,
                image: {
                    class: ImageTool,
                    config: {
                        endpoints: {
                            byFile: '/articles/upload-image',
                        },
                        field: 'image',
                        types: 'image/*',
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
                    class: ColorPlugin,
                    config: {
                        colorCollections: ['#FFBF00', '#FFD700', '#FFFF00'],
                        defaultColor: '#FFBF00',
                        type: 'marker',
                        customPicker: true,
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
            },
            data: initialData,
            onChange: async () => {
                const outputData = await editor.value?.save();
                if (outputData) {
                    emit('update:content', JSON.stringify(outputData));
                }
            },
        });
    }
});

onBeforeUnmount(() => {
    if (editor.value) {
        editor.value.destroy();
        editor.value = null;
    }
});
</script>

<template>
    <div>
        <div ref="editorContainer" class="container min-h-[300px] w-full rounded-md border p-4"></div>
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

/* Styles pour les boutons personnalisés (Block Tool) */
.button-tool {
    padding: 10px 0;
}

.button-tool__btn {
    display: inline-block;
    padding: 8px 16px;
    font-size: 14px;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: center;
    margin-bottom: 10px;
}

/* Styles dynamiques pour les boutons basés sur les couleurs du site */
.button-tool__btn--primary {
    background-color: v-bind('siteColors?.primary || "#4E8D44"');
    color: white;
    border: none;
}

.button-tool__btn--secondary {
    background-color: v-bind('siteColors?.secondary || "#6b7280"');
    color: white;
    border: none;
}

.button-tool__btn--accent {
    background-color: v-bind('siteColors?.accent || "#10b981"');
    color: white;
    border: none;
}

.button-tool__btn--danger {
    background-color: #ef4444;
    color: white;
    border: none;
}

.button-tool__inputs {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.button-tool__input,
.button-tool__select {
    padding: 6px 8px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    font-size: 14px;
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
</style>
