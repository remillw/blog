import edjsHTML from 'editorjs-html';

export function useEditorJSConverter() {
    // Configuration du convertisseur avec les outils personnalisés
    const edjsParser = edjsHTML({
        paragraph: (data: any) => {
            return `<p>${data.text}</p>`;
        },
        header: (data: any) => {
            return `<h${data.level}>${data.text}</h${data.level}>`;
        },
        list: (data: any) => {
            const listTag = data.style === 'ordered' ? 'ol' : 'ul';
            const items = data.items.map((item: any) => {
                // Gérer différents formats d'items de liste
                let itemText = '';
                if (typeof item === 'string') {
                    itemText = item;
                } else if (item && typeof item === 'object') {
                    // Si c'est un objet, chercher le texte dans diverses propriétés
                    itemText = item.text || item.content || item.value || JSON.stringify(item);
                } else {
                    itemText = String(item);
                }
                return `<li>${itemText}</li>`;
            }).join('');
            return `<${listTag}>${items}</${listTag}>`;
        },
        quote: (data: any) => {
            return `<blockquote><p>${data.text}</p><cite>${data.caption}</cite></blockquote>`;
        },
        code: (data: any) => {
            return `<pre><code>${data.code}</code></pre>`;
        },
        delimiter: () => {
            return '<hr>';
        },
        table: (data: any) => {
            const rows = data.content
                .map((row: string[]) => {
                    const cells = row.map((cell) => `<td>${cell}</td>`).join('');
                    return `<tr>${cells}</tr>`;
                })
                .join('');
            return `<table><tbody>${rows}</tbody></table>`;
        },
        image: (data: any) => {
            const caption = data.caption ? `<figcaption>${data.caption}</figcaption>` : '';
            return `<figure><img src="${data.file.url}" alt="${data.caption || ''}">${caption}</figure>`;
        },
        embed: (data: any) => {
            return `<div class="embed"><iframe src="${data.embed}" width="${data.width}" height="${data.height}"></iframe></div>`;
        },
        linkTool: (data: any) => {
            return `<a href="${data.link}" target="_blank">${data.meta.title || data.link}</a>`;
        },
        raw: (data: any) => {
            return data.html;
        },
        warning: (data: any) => {
            return `<div class="warning"><h4>${data.title}</h4><p>${data.message}</p></div>`;
        },
        checklist: (data: any) => {
            const items = data.items
                .map((item: any) => {
                    const checked = item.checked ? 'checked' : '';
                    return `<li><input type="checkbox" ${checked} disabled> ${item.text}</li>`;
                })
                .join('');
            return `<ul class="checklist">${items}</ul>`;
        },
    });

    /**
     * Convertit le contenu EditorJS en HTML avec un convertisseur personnalisé
     */
    const convertToHTMLCustom = (editorJSData: any): string => {
        try {
            if (!editorJSData || !editorJSData.blocks || !Array.isArray(editorJSData.blocks)) {
                return '';
            }

            const htmlParts: string[] = [];

            editorJSData.blocks.forEach((block: any) => {
                const blockHtml = convertBlockToHTML(block);
                if (blockHtml) {
                    htmlParts.push(blockHtml);
                }
            });

            return htmlParts.join('\n');
        } catch (error) {
            console.error('Erreur lors de la conversion personnalisée EditorJS vers HTML:', error);
            return '';
        }
    };

    /**
     * Convertit un block EditorJS individuel en HTML
     */
    const convertBlockToHTML = (block: any): string => {
        if (!block.type || !block.data) {
            return '';
        }

        switch (block.type) {
            case 'paragraph':
                if (block.data.text) {
                    return `<p>${block.data.text}</p>`;
                }
                break;

            case 'header':
                if (block.data.text && block.data.level) {
                    const level = Math.min(Math.max(1, block.data.level), 6);
                    return `<h${level}>${block.data.text}</h${level}>`;
                }
                break;

            case 'list':
                if (block.data.items && Array.isArray(block.data.items)) {
                    const listTag = block.data.style === 'ordered' ? 'ol' : 'ul';
                    const items = block.data.items.map((item: any) => {
                        // Gérer différents formats d'items de liste
                        let itemText = '';
                        if (typeof item === 'string') {
                            itemText = item;
                        } else if (item && typeof item === 'object') {
                            // Si c'est un objet, chercher le texte dans diverses propriétés
                            itemText = item.text || item.content || item.value || JSON.stringify(item);
                        } else {
                            itemText = String(item);
                        }
                        return `<li>${itemText}</li>`;
                    }).join('');
                    return `<${listTag}>${items}</${listTag}>`;
                }
                break;

            case 'quote':
                if (block.data.text) {
                    const caption = block.data.caption ? `<cite>${block.data.caption}</cite>` : '';
                    return `<blockquote><p>${block.data.text}</p>${caption}</blockquote>`;
                }
                break;

            case 'code':
                if (block.data.code) {
                    return `<pre><code>${block.data.code}</code></pre>`;
                }
                break;

            case 'delimiter':
                return '<hr>';

            case 'table':
                if (block.data.content && Array.isArray(block.data.content)) {
                    const rows = block.data.content
                        .map((row: string[]) => {
                            const cells = row.map((cell) => `<td>${cell}</td>`).join('');
                            return `<tr>${cells}</tr>`;
                        })
                        .join('');
                    return `<table><tbody>${rows}</tbody></table>`;
                }
                break;

            case 'image':
                if (block.data.file && block.data.file.url) {
                    const caption = block.data.caption ? `<figcaption>${block.data.caption}</figcaption>` : '';
                    const alt = block.data.caption || '';
                    return `<figure><img src="${block.data.file.url}" alt="${alt}">${caption}</figure>`;
                }
                break;

            case 'embed':
                if (block.data.embed) {
                    const width = block.data.width || '100%';
                    const height = block.data.height || '315';
                    return `<div class="embed"><iframe src="${block.data.embed}" width="${width}" height="${height}"></iframe></div>`;
                }
                break;

            case 'linkTool':
                if (block.data.link) {
                    const title = block.data.meta?.title || block.data.link;
                    return `<a href="${block.data.link}" target="_blank">${title}</a>`;
                }
                break;

            case 'raw':
                if (block.data.html) {
                    return block.data.html;
                }
                break;

            case 'warning':
                if (block.data.title || block.data.message) {
                    const title = block.data.title ? `<h4>${block.data.title}</h4>` : '';
                    const message = block.data.message ? `<p>${block.data.message}</p>` : '';
                    return `<div class="warning">${title}${message}</div>`;
                }
                break;

            case 'checklist':
                if (block.data.items && Array.isArray(block.data.items)) {
                    const items = block.data.items
                        .map((item: any) => {
                            const checked = item.checked ? 'checked' : '';
                            return `<li><input type="checkbox" ${checked} disabled> ${item.text}</li>`;
                        })
                        .join('');
                    return `<ul class="checklist">${items}</ul>`;
                }
                break;

            case 'button':
                if (block.data.text) {
                    const link = block.data.link ? ` href="${block.data.link}"` : '';
                    const target = block.data.target === '_blank' ? ' target="_blank"' : '';
                    const rel = block.data.rel ? ` rel="${block.data.rel}"` : '';
                    const style = block.data.style || 'primary';
                    
                    // Classes de base communes à tous les boutons avec votre style exact
                    const baseClasses = [
                        'px-4', 'py-2', 'font-black', 'inline-block', 'text-center', 'no-underline', 'cursor-pointer',
                        'hover:-translate-y-1', 'hover:translate-x-1', 'hover:shadow-[4px_4px_0px_0px_rgba(0,0,0,0.8)]',
                        'transition-all', 'duration-300', 'min-w-[160px]', 'rounded-lg'
                    ];
                    
                    // Classes spécifiques selon le style
                    let styleClasses: string[] = [];
                    
                    switch (style) {
                        case 'primary':
                            styleClasses = ['bg-primary', 'text-white', 'border-2', 'border-primary'];
                            break;
                        case 'secondary':
                            styleClasses = ['bg-secondary', 'text-white', 'border-2', 'border-secondary'];
                            break;
                        case 'accent':
                            styleClasses = ['bg-accent', 'text-white', 'border-2', 'border-accent'];
                            break;
                        case 'success':
                            styleClasses = ['bg-green-500', 'text-white', 'border-2', 'border-green-500'];
                            break;
                        case 'warning':
                            styleClasses = ['bg-yellow-500', 'text-white', 'border-2', 'border-yellow-500'];
                            break;
                        case 'danger':
                            styleClasses = ['bg-red-500', 'text-white', 'border-2', 'border-red-500'];
                            break;
                        case 'outline':
                            styleClasses = ['bg-transparent', 'text-primary', 'border-2', 'border-primary', 'hover:bg-primary', 'hover:text-white'];
                            break;
                        default:
                            styleClasses = ['bg-primary', 'text-white', 'border-2', 'border-primary'];
                    }
                    
                    // Combiner toutes les classes
                    const allClasses = [...baseClasses, ...styleClasses].join(' ');
                    
                    return `<a${link}${target}${rel} class="${allClasses}">${block.data.text}</a>`;
                }
                break;

            case 'attaches':
                if (block.data.file) {
                    const fileName = block.data.title || block.data.file.name || 'Fichier téléchargé';
                    const fileSize = block.data.file.size ? ` (${Math.round(block.data.file.size / 1024)} KB)` : '';
                    return `
                        <div class="attachment">
                            <a href="${block.data.file.url}" download="${fileName}" class="attachment-link">
                                📎 ${fileName}${fileSize}
                            </a>
                        </div>
                    `;
                }
                break;

            case 'columns':
                if (block.data.columns && Array.isArray(block.data.columns)) {
                    const layout = block.data.layout || '2-cols';
                    const columnClasses = {
                        '2-cols': 'grid grid-cols-1 md:grid-cols-2 gap-4',
                        '3-cols': 'grid grid-cols-1 md:grid-cols-3 gap-4',
                        '2-1': 'grid grid-cols-1 md:grid-cols-3 gap-4', // 2/3 + 1/3
                        '1-2': 'grid grid-cols-1 md:grid-cols-3 gap-4', // 1/3 + 2/3
                    };

                    const gridClass = columnClasses[layout as keyof typeof columnClasses] || columnClasses['2-cols'];

                    const columnsHtml = block.data.columns
                        .map((column: any, index: number) => {
                            let colSpanClass = '';

                            // Gérer les classes col-span pour les layouts asymétriques
                            if (layout === '2-1') {
                                colSpanClass = index === 0 ? 'md:col-span-2' : 'md:col-span-1';
                            } else if (layout === '1-2') {
                                colSpanClass = index === 0 ? 'md:col-span-1' : 'md:col-span-2';
                            }

                            // Traiter le contenu de la colonne
                            let columnContent = '';
                            if (column.blocks && Array.isArray(column.blocks)) {
                                // Nouvelle structure avec blocks EditorJS
                                columnContent = column.blocks
                                    .map((columnBlock: any) => {
                                        // Récursivement convertir chaque block de la colonne
                                        return convertBlockToHTML(columnBlock);
                                    })
                                    .join('\n');
                            } else if (column.content) {
                                // Ancienne structure avec simple contenu texte
                                columnContent = column.content;
                            }

                            return `<div class="column ${colSpanClass}">
                            <div class="prose max-w-none">
                                ${columnContent}
                            </div>
                        </div>`;
                        })
                        .join('');

                    return `<div class="columns-block ${gridClass}">${columnsHtml}</div>`;
                }
                break;

            default:
                console.warn(`Type de bloc non supporté: ${block.type}`, block.data);
                // Essayer de récupérer du texte si possible
                if (block.data.text) {
                    return `<p>${block.data.text}</p>`;
                }
                break;
        }

        return '';
    };

    /**
     * Convertit le contenu EditorJS en HTML
     */
    const convertToHTML = (editorJSData: any): string => {
        // Essayer d'abord le convertisseur personnalisé
        const customResult = convertToHTMLCustom(editorJSData);
        if (customResult) {
            return customResult;
        }

        // Fallback vers l'ancien convertisseur
        try {
            if (!editorJSData || !editorJSData.blocks) {
                return '';
            }

            const htmlResult = edjsParser.parse(editorJSData);

            // Vérifier si le résultat est un tableau ou une chaîne
            if (Array.isArray(htmlResult)) {
                return htmlResult.join('');
            } else if (typeof htmlResult === 'string') {
                return htmlResult;
            } else {
                console.warn('Format de retour inattendu du parser EditorJS:', htmlResult);
                return '';
            }
        } catch (error) {
            console.error('Erreur lors de la conversion EditorJS vers HTML:', error);
            return '';
        }
    };

    /**
     * Nettoie le HTML pour le webhook (supprime les styles inline si nécessaire)
     */
    const cleanHTMLForWebhook = (html: string): string => {
        console.log('🧹 Cleaning HTML input:', html);

        // Préserver les couleurs mais supprimer les autres styles inline
        let result = html
            // Convertir les balises font avec couleur en span avec style
            .replace(/<font([^>]*style="[^"]*"[^>]*)>/g, (match, attributes) => {
                console.log('🎨 Found font tag:', match);
                console.log('🎨 Attributes:', attributes);

                // Extraire le style de la balise font
                const styleMatch = attributes.match(/style="([^"]*)"/);
                if (styleMatch) {
                    const styleContent = styleMatch[1];
                    console.log('🎨 Style content:', styleContent);

                    const colorStyles = [];
                    const styles = styleContent.split(';');

                    for (const style of styles) {
                        const trimmedStyle = style.trim();
                        if (trimmedStyle.includes('color:') || trimmedStyle.includes('background-color:') || trimmedStyle.includes('background:')) {
                            colorStyles.push(trimmedStyle);
                        }
                    }

                    console.log('🎨 Color styles found:', colorStyles);

                    if (colorStyles.length > 0) {
                        const result = `<span style="${colorStyles.join('; ')}">`;
                        console.log('🎨 Converted to:', result);
                        return result;
                    }
                }
                return '<span>';
            })
            // Remplacer les balises font de fermeture par span
            .replace(/<\/font>/g, '</span>')
            // Supprimer les balises font restantes (sans couleur)
            .replace(/<font[^>]*>/g, '')
            // Préserver les styles de couleur et background-color, supprimer le reste
            .replace(/style="([^"]*)"/g, (match, styleContent) => {
                // Extraire seulement les propriétés de couleur
                const colorStyles = [];
                const styles = styleContent.split(';');

                for (const style of styles) {
                    const trimmedStyle = style.trim();
                    if (trimmedStyle.includes('color:') || trimmedStyle.includes('background-color:') || trimmedStyle.includes('background:')) {
                        colorStyles.push(trimmedStyle);
                    }
                }

                // Si on a des styles de couleur, les conserver
                if (colorStyles.length > 0) {
                    return `style="${colorStyles.join('; ')}"`;
                }

                // Sinon, supprimer l'attribut style
                return '';
            });

        // Nettoyer les spans de couleur imbriqués
        result = cleanNestedColorSpans(result);

        console.log('🧹 Cleaning HTML result:', result);
        return result;
    };

    // Fonction pour nettoyer les spans de couleur imbriqués
    const cleanNestedColorSpans = (html: string): string => {
        console.log('🔧 Cleaning nested spans input:', html);

        // Créer un élément DOM temporaire pour parser le HTML
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;

        // Fonction récursive pour nettoyer les spans imbriqués
        const cleanElement = (element: Element): void => {
            const children = Array.from(element.children);

            for (const child of children) {
                if (child.tagName === 'SPAN' && child.hasAttribute('style')) {
                    // Vérifier si ce span a des spans enfants avec des couleurs
                    const colorSpanChildren = Array.from(child.children).filter(
                        (grandChild) => grandChild.tagName === 'SPAN' && grandChild.hasAttribute('style'),
                    );

                    if (colorSpanChildren.length > 0) {
                        // Il y a des spans de couleur imbriqués
                        // Prendre la couleur du span le plus profond (le dernier appliqué)
                        const deepestSpan = colorSpanChildren[colorSpanChildren.length - 1] as HTMLElement;
                        const deepestColor = deepestSpan.style.color;

                        if (deepestColor) {
                            // Extraire tout le texte du span parent
                            const textContent = child.textContent || '';

                            // Remplacer le span parent par un span simple avec la couleur la plus profonde
                            const newSpan = document.createElement('span');
                            newSpan.style.color = deepestColor;
                            newSpan.textContent = textContent;

                            child.parentNode?.replaceChild(newSpan, child);
                            continue;
                        }
                    }
                }

                // Continuer récursivement pour les autres éléments
                cleanElement(child);
            }
        };

        cleanElement(tempDiv);

        const result = tempDiv.innerHTML;
        console.log('🔧 Cleaning nested spans result:', result);
        return result;
    };

    /**
     * Convertit et nettoie le contenu pour l'envoi via webhook
     */
    const convertForWebhook = (editorJSData: any): string => {
        console.log('🔄 Converting for webhook, input data:', editorJSData);

        const html = convertToHTML(editorJSData);
        console.log('🔄 HTML before cleaning:', html);

        const cleanedHtml = cleanHTMLForWebhook(html);
        console.log('🔄 HTML after cleaning:', cleanedHtml);

        return cleanedHtml;
    };

    /**
     * Convertit HTML vers EditorJS (pour l'édition après webhook)
     */
    const convertHTMLToEditorJS = (html: string): any => {
        try {
            if (!html || html.trim() === '') {
                return {
                    time: Date.now(),
                    blocks: [],
                    version: '2.31.0',
                };
            }

            // Créer un élément DOM temporaire pour parser le HTML
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;

            const blocks: any[] = [];
            let blockId = 0;

            // Parcourir tous les éléments enfants
            Array.from(tempDiv.children).forEach((element) => {
                const tagName = element.tagName.toLowerCase();
                const textContent = element.textContent || '';

                switch (tagName) {
                    case 'p':
                        if (textContent.trim()) {
                            blocks.push({
                                id: `block_${blockId++}`,
                                type: 'paragraph',
                                data: {
                                    text: element.innerHTML,
                                },
                            });
                        }
                        break;

                    case 'h1':
                    case 'h2':
                    case 'h3':
                    case 'h4':
                    case 'h5':
                    case 'h6':
                        blocks.push({
                            id: `block_${blockId++}`,
                            type: 'header',
                            data: {
                                text: element.innerHTML,
                                level: parseInt(tagName.charAt(1)),
                            },
                        });
                        break;

                    case 'ul':
                    case 'ol':
                        const listItems = Array.from(element.querySelectorAll('li')).map((li) => li.innerHTML);
                        if (listItems.length > 0) {
                            blocks.push({
                                id: `block_${blockId++}`,
                                type: 'list',
                                data: {
                                    style: tagName === 'ol' ? 'ordered' : 'unordered',
                                    items: listItems,
                                },
                            });
                        }
                        break;

                    case 'blockquote':
                        const quoteText = element.querySelector('p')?.innerHTML || element.innerHTML;
                        const citation = element.querySelector('cite')?.innerHTML || '';
                        blocks.push({
                            id: `block_${blockId++}`,
                            type: 'quote',
                            data: {
                                text: quoteText,
                                caption: citation,
                                alignment: 'left',
                            },
                        });
                        break;

                    case 'pre':
                        const codeElement = element.querySelector('code');
                        if (codeElement) {
                            blocks.push({
                                id: `block_${blockId++}`,
                                type: 'code',
                                data: {
                                    code: codeElement.textContent || '',
                                },
                            });
                        }
                        break;

                    case 'hr':
                        blocks.push({
                            id: `block_${blockId++}`,
                            type: 'delimiter',
                            data: {},
                        });
                        break;

                    case 'figure':
                        const img = element.querySelector('img');
                        const figcaption = element.querySelector('figcaption');
                        if (img) {
                            blocks.push({
                                id: `block_${blockId++}`,
                                type: 'image',
                                data: {
                                    file: {
                                        url: img.src,
                                    },
                                    caption: figcaption?.textContent || img.alt || '',
                                    withBorder: false,
                                    stretched: false,
                                    withBackground: false,
                                },
                            });
                        }
                        break;

                    case 'table':
                        const rows = Array.from(element.querySelectorAll('tr'));
                        const tableContent = rows.map((row) => Array.from(row.querySelectorAll('td, th')).map((cell) => cell.innerHTML));
                        if (tableContent.length > 0) {
                            blocks.push({
                                id: `block_${blockId++}`,
                                type: 'table',
                                data: {
                                    content: tableContent,
                                },
                            });
                        }
                        break;

                    case 'div':
                        // Gérer les blocks de colonnes
                        if (element.classList.contains('columns-block')) {
                            const columns = Array.from(element.querySelectorAll('.column')).map((col) => {
                                const proseContent = col.querySelector('.prose');
                                return {
                                    content: proseContent ? proseContent.innerHTML : col.innerHTML,
                                    width: 50, // Valeur par défaut, sera ajustée selon le layout
                                };
                            });

                            // Déterminer le layout basé sur les classes CSS
                            let layout = '2-cols';
                            if (element.classList.contains('md:grid-cols-3')) {
                                const firstColumn = element.querySelector('.column');
                                if (firstColumn?.classList.contains('md:col-span-2')) {
                                    layout = '2-1';
                                } else if (element.querySelectorAll('.column')[1]?.classList.contains('md:col-span-2')) {
                                    layout = '1-2';
                                } else {
                                    layout = '3-cols';
                                }
                            }

                            blocks.push({
                                id: `block_${blockId++}`,
                                type: 'columns',
                                data: {
                                    columns: columns,
                                    layout: layout,
                                },
                            });
                        }
                        // Gérer les attachements
                        else if (element.classList.contains('attachment')) {
                            const link = element.querySelector('a');
                            if (link) {
                                const fileName = link.textContent?.replace('📎 ', '').split('(')[0].trim() || 'Fichier';
                                blocks.push({
                                    id: `block_${blockId++}`,
                                    type: 'attaches',
                                    data: {
                                        file: {
                                            url: link.href,
                                            name: fileName,
                                        },
                                        title: fileName,
                                    },
                                });
                            }
                        }
                        // Pour les autres divs, traiter comme HTML brut
                        else if (textContent.trim()) {
                            blocks.push({
                                id: `block_${blockId++}`,
                                type: 'raw',
                                data: {
                                    html: element.outerHTML,
                                },
                            });
                        }
                        break;

                    default:
                        // Pour les éléments non reconnus, les traiter comme du HTML brut
                        if (textContent.trim()) {
                            blocks.push({
                                id: `block_${blockId++}`,
                                type: 'raw',
                                data: {
                                    html: element.outerHTML,
                                },
                            });
                        }
                        break;
                }
            });

            // Si aucun bloc n'a été créé mais qu'il y a du contenu, créer un paragraphe
            if (blocks.length === 0 && html.trim()) {
                blocks.push({
                    id: `block_${blockId++}`,
                    type: 'paragraph',
                    data: {
                        text: html,
                    },
                });
            }

            return {
                time: Date.now(),
                blocks: blocks,
                version: '2.31.0',
            };
        } catch (error) {
            console.error('Erreur lors de la conversion HTML vers EditorJS:', error);
            return {
                time: Date.now(),
                blocks: [
                    {
                        id: 'error_block',
                        type: 'paragraph',
                        data: {
                            text: 'Erreur lors du chargement du contenu',
                        },
                    },
                ],
                version: '2.31.0',
            };
        }
    };

    return {
        convertToHTML,
        cleanHTMLForWebhook,
        convertForWebhook,
        convertHTMLToEditorJS,
    };
}
