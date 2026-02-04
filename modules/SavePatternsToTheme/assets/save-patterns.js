(function () {
    'use strict';

    function addSaveButton() {
        // Find the bulk actions container
        const container = document.querySelector('.dataviews-bulk-actions-footer__action-buttons');
        if (!container) {
            return false;
        }
        // Check if our button already exists
        if (container.querySelector('[data-save-to-theme]')) {
            return true;
        }

        // Find the "Export as JSON" button to insert after
        const exportButton = Array.from(container.querySelectorAll('button')).find((btn) =>
            btn.textContent.includes('Export')
        );
        if (!exportButton) {
            return false;
        }

        // Create our button
        const saveButton = document.createElement('button');
        saveButton.type = 'button';
        saveButton.className = 'components-button is-compact';
        saveButton.textContent = 'Save to Theme';
        saveButton.setAttribute('data-save-to-theme', 'true');

        saveButton.addEventListener('click', handleSaveClick);

        // Insert after export button
        exportButton.after(saveButton);
        return true;
    }

    async function handleSaveClick() {
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
            const response = await wp.apiFetch({
                path: '/theme/v1/save-patterns',
                method: 'POST',
                data: { pattern_ids: patternIds },
            });

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
        } catch (error) {
            alert('Error saving patterns: ' + (error.message || 'Unknown error'));
        } finally {
            button.textContent = originalText;
            button.disabled = false;
        }
    }

    function getSelectedPatternIds() {
        const patternIds = [];
        if (typeof wp === 'undefined' || !wp.data) {
            console.log('wp.data not available');
            return patternIds;
        }

        // Get all wp_block posts (synced patterns) from the store
        const coreStore = wp.data.select('core');
        const syncedPatterns = coreStore.getEntityRecords('postType', 'wp_block', { per_page: -1 });
        if (!syncedPatterns || syncedPatterns.length === 0) {
            console.log('No synced patterns found in store');
            return patternIds;
        }

        console.log('Synced patterns from store:', syncedPatterns);

        // Get all visible cards and find selected ones
        const allCards = document.querySelectorAll('.dataviews-view-grid__card');
        const totalCards = allCards.length;
        const syncedCount = syncedPatterns.length;

        // Theme patterns appear first, synced patterns appear after
        // Calculate the offset where synced patterns start
        const themePatternCount = totalCards - syncedCount;

        console.log(
            `Total cards: ${totalCards}, Synced patterns: ${syncedCount}, Theme patterns: ${themePatternCount}`
        );

        // Find selected card indices
        allCards.forEach((card, index) => {
            if (card.classList.contains('is-selected')) {
                // If this index is in the synced patterns range
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

    // Watch for the bulk actions footer to appear
    function init() {
        const observer = new MutationObserver(() => {
            addSaveButton();
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
        });

        // Also try immediately
        addSaveButton();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
