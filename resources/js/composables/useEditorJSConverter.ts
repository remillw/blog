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
            const items = data.items.map((item: string) => `<li>${item}</li>`).join('');
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
                if (!block.type || !block.data) {
                    return;
                }

                switch (block.type) {
                    case 'paragraph':
                        if (block.data.text) {
                            htmlParts.push(`<p>${block.data.text}</p>`);
                        }
                        break;

                    case 'header':
                        if (block.data.text && block.data.level) {
                            const level = Math.min(Math.max(1, block.data.level), 6);
                            htmlParts.push(`<h${level}>${block.data.text}</h${level}>`);
                        }
                        break;

                    case 'list':
                        if (block.data.items && Array.isArray(block.data.items)) {
                            const listTag = block.data.style === 'ordered' ? 'ol' : 'ul';
                            const items = block.data.items.map((item: string) => `<li>${item}</li>`).join('');
                            htmlParts.push(`<${listTag}>${items}</${listTag}>`);
                        }
                        break;

                    case 'quote':
                        if (block.data.text) {
                            const caption = block.data.caption ? `<cite>${block.data.caption}</cite>` : '';
                            htmlParts.push(`<blockquote><p>${block.data.text}</p>${caption}</blockquote>`);
                        }
                        break;

                    case 'code':
                        if (block.data.code) {
                            htmlParts.push(`<pre><code>${block.data.code}</code></pre>`);
                        }
                        break;

                    case 'delimiter':
                        htmlParts.push('<hr>');
                        break;

                    case 'table':
                        if (block.data.content && Array.isArray(block.data.content)) {
                            const rows = block.data.content
                                .map((row: string[]) => {
                                    const cells = row.map((cell) => `<td>${cell}</td>`).join('');
                                    return `<tr>${cells}</tr>`;
                                })
                                .join('');
                            htmlParts.push(`<table><tbody>${rows}</tbody></table>`);
                        }
                        break;

                    case 'image':
                        if (block.data.file && block.data.file.url) {
                            const caption = block.data.caption ? `<figcaption>${block.data.caption}</figcaption>` : '';
                            htmlParts.push(`<figure><img src="${block.data.file.url}" alt="${block.data.caption || ''}">${caption}</figure>`);
                        }
                        break;

                    case 'embed':
                        if (block.data.embed) {
                            const width = block.data.width || '100%';
                            const height = block.data.height || '315';
                            htmlParts.push(
                                `<div class="embed"><iframe src="${block.data.embed}" width="${width}" height="${height}"></iframe></div>`,
                            );
                        }
                        break;

                    case 'linkTool':
                        if (block.data.link) {
                            const title = block.data.meta?.title || block.data.link;
                            htmlParts.push(`<a href="${block.data.link}" target="_blank">${title}</a>`);
                        }
                        break;

                    case 'raw':
                        if (block.data.html) {
                            htmlParts.push(block.data.html);
                        }
                        break;

                    case 'warning':
                        if (block.data.title || block.data.message) {
                            const title = block.data.title ? `<h4>${block.data.title}</h4>` : '';
                            const message = block.data.message ? `<p>${block.data.message}</p>` : '';
                            htmlParts.push(`<div class="warning">${title}${message}</div>`);
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
                            htmlParts.push(`<ul class="checklist">${items}</ul>`);
                        }
                        break;

                    case 'button':
                        if (block.data.text) {
                            const link = block.data.link ? ` href="${block.data.link}"` : '';
                            const style = block.data.style || 'primary';
                            htmlParts.push(`<a${link} class="button button--${style}">${block.data.text}</a>`);
                        }
                        break;

                    default:
                        console.warn(`Type de bloc non supporté: ${block.type}`, block.data);
                        // Essayer de récupérer du texte si possible
                        if (block.data.text) {
                            htmlParts.push(`<p>${block.data.text}</p>`);
                        }
                        break;
                }
            });

            return htmlParts.join('\n');
        } catch (error) {
            console.error('Erreur lors de la conversion personnalisée EditorJS vers HTML:', error);
            return '';
        }
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
