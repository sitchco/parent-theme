(function () {
    'use strict';

    /**
     * Get pattern ID from URL (handles multiple URL formats)
     */
    function getPatternIdFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);

        // Format 1: ?postType=wp_block&postId=123
        const postType = urlParams.get('postType');
        const postId = urlParams.get('postId');
        if (postType === 'wp_block' && postId) {
            return parseInt(postId, 10);
        }

        // Format 2: ?p=/wp_block/123
        const pParam = urlParams.get('p');
        if (pParam) {
            const match = pParam.match(/\/wp_block\/(\d+)/);
            if (match) {
                return parseInt(match[1], 10);
            }
        }

        // Format 3: Check for canvas=edit and postId
        const canvas = urlParams.get('canvas');
        if (canvas === 'edit' && postId) {
            return parseInt(postId, 10);
        }
        return null;
    }

    /**
     * Check if we're on a pattern editing page
     */
    function isPatternEditPage() {
        const url = window.location.href;
        return url.includes('wp_block') || url.includes('postType=wp_block');
    }

    /**
     * Add Save to Theme button in bulk actions footer (patterns list view)
     */
    function addBulkActionButton() {
        const container = document.querySelector('.dataviews-bulk-actions-footer__action-buttons');
        if (!container) {
            return false;
        }
        if (container.querySelector('[data-save-to-theme]')) {
            return true;
        }

        const exportButton = Array.from(container.querySelectorAll('button')).find((btn) =>
            btn.textContent.includes('Export')
        );
        if (!exportButton) {
            return false;
        }

        const saveButton = document.createElement('button');
        saveButton.type = 'button';
        saveButton.className = 'components-button is-compact';
        saveButton.textContent = 'Save to Theme';
        saveButton.setAttribute('data-save-to-theme', 'true');
        saveButton.addEventListener('click', handleBulkSaveClick);

        exportButton.after(saveButton);
        return true;
    }

    /**
     * Add Save to Theme menu item in single pattern dropdown menu
     */
    function addSinglePatternMenuItem() {
        // Check if we're on a pattern page
        if (!isPatternEditPage()) {
            return false;
        }

        const patternId = getPatternIdFromUrl();
        console.log('Save to Theme: Pattern ID from URL:', patternId, 'URL:', window.location.search);

        // Find any open dropdown menu with role="menu"
        const menus = document.querySelectorAll('[role="menu"]');
        console.log('Save to Theme: Found menus:', menus.length);

        for (const menu of menus) {
            // Check if already added
            if (menu.querySelector('[data-save-to-theme-menu]')) {
                continue;
            }

            // Find the "Export as JSON" menu item
            const menuItems = menu.querySelectorAll('[role="menuitem"]');
            console.log('Save to Theme: Menu items in this menu:', menuItems.length);

            let exportItem = null;

            for (const item of menuItems) {
                const text = item.textContent.trim();
                console.log('Save to Theme: Menu item text:', text);

                if (text.includes('Export as JSON') || text.includes('Export')) {
                    exportItem = item;
                    break;
                }
            }

            if (!exportItem) {
                console.log('Save to Theme: No Export item found in this menu');
                continue;
            }

            console.log('Save to Theme: Found Export menu item, adding Save to Theme');

            // Clone the export item to get exact same structure and styles
            const saveMenuItem = exportItem.cloneNode(true);
            saveMenuItem.setAttribute('data-save-to-theme-menu', 'true');
            saveMenuItem.removeAttribute('id'); // Remove the id to avoid duplicates

            // Update the text content
            const truncateSpan = saveMenuItem.querySelector('.components-truncate');
            if (truncateSpan) {
                truncateSpan.textContent = 'Save to Theme';
            } else {
                // Fallback: find any text node and update it
                const textNode = saveMenuItem.querySelector('span:last-of-type');
                if (textNode) {
                    textNode.textContent = 'Save to Theme';
                }
            }

            saveMenuItem.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                // Get pattern ID - either from URL or try wp.data
                let id = patternId;
                if (!id && typeof wp !== 'undefined' && wp.data) {
                    const editorStore = wp.data.select('core/editor');
                    if (editorStore && editorStore.getCurrentPostId) {
                        id = editorStore.getCurrentPostId();
                    }
                }
                if (id) {
                    handleSingleSaveClick(id);
                } else {
                    alert('Could not determine pattern ID');
                }
            });

            // Add hover state handling (toggle data-active-item attribute)
            saveMenuItem.addEventListener('mouseenter', () => {
                // Remove active state from other items
                menu.querySelectorAll('[role="menuitem"]').forEach((item) => {
                    item.removeAttribute('data-active-item');
                });

                saveMenuItem.setAttribute('data-active-item', 'true');
            });

            saveMenuItem.addEventListener('mouseleave', () => {
                saveMenuItem.removeAttribute('data-active-item');
            });

            // Insert after Export as JSON
            exportItem.after(saveMenuItem);
            console.log('Save to Theme: Menu item added successfully');
            return true;
        }
        return false;
    }

    /**
     * Handle bulk save click from patterns list
     */
    async function handleBulkSaveClick() {
        const patternIds = getSelectedPatternIds();
        if (patternIds.length === 0) {
            alert('Could not determine selected patterns. Check browser console for debug info.');
            return;
        }

        console.log('Save to Theme: Found pattern IDs:', patternIds);

        const button = document.querySelector('[data-save-to-theme]');
        const originalText = button.textContent;
        button.textContent = 'Saving...';
        button.disabled = true;

        try {
            const response = await savePatterns(patternIds);
            showResultMessage(response);
        } catch (error) {
            alert('Error saving patterns: ' + (error.message || 'Unknown error'));
        } finally {
            button.textContent = originalText;
            button.disabled = false;
        }
    }

    /**
     * Handle single pattern save click
     */
    async function handleSingleSaveClick(patternId) {
        if (!patternId) {
            alert('Could not determine pattern ID.');
            return;
        }

        console.log('Save to Theme: Saving pattern ID:', patternId);

        try {
            const response = await savePatterns([patternId]);
            showResultMessage(response);
        } catch (error) {
            alert('Error saving pattern: ' + (error.message || 'Unknown error'));
        }
    }

    /**
     * Save patterns via REST API
     */
    async function savePatterns(patternIds) {
        return await wp.apiFetch({
            path: '/theme/v1/save-patterns',
            method: 'POST',
            data: { pattern_ids: patternIds },
        });
    }

    /**
     * Show result message to user
     */
    function showResultMessage(response) {
        const messages = [];
        if (response.created && response.created.length > 0) {
            messages.push(`Created: ${response.created.join(', ')}`);
        }
        if (response.updated && response.updated.length > 0) {
            messages.push(`Updated: ${response.updated.join(', ')}`);
        }
        if (response.unchanged && response.unchanged.length > 0) {
            messages.push(`Unchanged: ${response.unchanged.join(', ')}`);
        }
        if (response.errors && response.errors.length > 0) {
            messages.push(`Errors: ${response.errors.join(', ')}`);
        }

        alert(messages.join('\n\n') || 'No patterns were processed.');
    }

    /**
     * Get selected pattern IDs from bulk selection
     */
    function getSelectedPatternIds() {
        const patternIds = [];
        if (typeof wp === 'undefined' || !wp.data) {
            console.log('wp.data not available');
            return patternIds;
        }

        const coreStore = wp.data.select('core');
        const syncedPatterns = coreStore.getEntityRecords('postType', 'wp_block', { per_page: -1 });
        if (!syncedPatterns || syncedPatterns.length === 0) {
            console.log('No synced patterns found in store');
            return patternIds;
        }

        console.log('Synced patterns from store:', syncedPatterns);

        const allCards = document.querySelectorAll('.dataviews-view-grid__card');
        const totalCards = allCards.length;
        const syncedCount = syncedPatterns.length;
        const themePatternCount = totalCards - syncedCount;

        console.log(
            `Total cards: ${totalCards}, Synced patterns: ${syncedCount}, Theme patterns: ${themePatternCount}`
        );

        allCards.forEach((card, index) => {
            if (card.classList.contains('is-selected')) {
                if (index >= themePatternCount) {
                    const syncedIndex = index - themePatternCount;
                    if (syncedPatterns[syncedIndex]) {
                        console.log(
                            `Card ${index} -> Synced pattern index ${syncedIndex} -> ID ${syncedPatterns[syncedIndex].id}`
                        );

                        patternIds.push(syncedPatterns[syncedIndex].id);
                    }
                } else {
                    console.log(`Card ${index} is a theme pattern (not a synced wp_block), skipping`);
                }
            }
        });
        return [...new Set(patternIds)];
    }

    /**
     * Initialize - watch for UI changes and add buttons
     */
    function init() {
        console.log('Save to Theme: Initializing...');

        const observer = new MutationObserver(() => {
            addBulkActionButton();
            addSinglePatternMenuItem();
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
        });

        // Try immediately
        addBulkActionButton();
        addSinglePatternMenuItem();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
