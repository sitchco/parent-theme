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
 * Look up a pattern's post ID by its title using wp.data store.
 */
function getPatternIdByTitle(title) {
    if (!title || typeof wp === 'undefined' || !wp.data) {
        return null;
    }

    const patterns = wp.data.select('core').getEntityRecords('postType', 'wp_block', {
        per_page: 100,
    });
    if (!patterns) {
        return null;
    }

    const match = patterns.find((p) => p.title.raw === title);
    return match ? match.id : null;
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

    // Find any open dropdown menu with role="menu"
    const menus = document.querySelectorAll('[role="menu"]');

    for (const menu of menus) {
        // Check if already added
        if (menu.querySelector('[data-save-to-theme-menu]')) {
            continue;
        }

        // Find the "Export as JSON" menu item
        const menuItems = menu.querySelectorAll('[role="menuitem"]');

        let exportItem = null;

        for (const item of menuItems) {
            const text = item.textContent.trim();
            if (text.includes('Export as JSON') || text.includes('Export')) {
                exportItem = item;
                break;
            }
        }

        if (!exportItem) {
            continue;
        }

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
            // Get pattern ID - try URL first (detail/edit page)
            let id = patternId;
            if (!id && typeof wp !== 'undefined' && wp.data) {
                // On the list page, resolve from the card's title
                const expandedButton = document.querySelector(
                    '.dataviews-view-grid__card button[aria-expanded="true"]'
                );
                if (expandedButton) {
                    const card = expandedButton.closest('.dataviews-view-grid__card');
                    if (card) {
                        const titleEl = card.querySelector('.dataviews-view-grid__title-field span');
                        if (titleEl) {
                            id = getPatternIdByTitle(titleEl.textContent.trim());
                        }
                    }
                }
                // Fallback: try the editor store (edit page without URL params)
                if (!id) {
                    const editorStore = wp.data.select('core/editor');
                    if (editorStore && editorStore.getCurrentPostId) {
                        id = editorStore.getCurrentPostId();
                    }
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
        alert('No synced patterns selected. Only synced (wp_block) patterns can be saved to the theme.');
        return;
    }

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
 * Get selected pattern IDs from bulk selection by finding checked
 * checkboxes and resolving each pattern's title to a post ID via wp.data.
 */
function getSelectedPatternIds() {
    const patternIds = [];
    const checkedBoxes = document.querySelectorAll('.dataviews-view-grid__card input[type="checkbox"]:checked');

    for (const checkbox of checkedBoxes) {
        const title = checkbox.getAttribute('aria-label');
        if (!title) {
            continue;
        }

        const id = getPatternIdByTitle(title);
        if (id) {
            patternIds.push(id);
        }
    }
    return patternIds;
}

/**
 * Initialize - watch for UI changes and add buttons
 */
function init() {
    let debounceTimer = null;

    const observer = new MutationObserver(() => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            addBulkActionButton();
            addSinglePatternMenuItem();
        }, 200);
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
