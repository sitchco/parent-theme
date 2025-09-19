import domReady from '@wordpress/dom-ready';
import { select, dispatch, subscribe } from '@wordpress/data';

domReady(function() {
    const unsubscribe = subscribe(() => {
        // Check if the 'core/editor' store is available and if a post ID can be retrieved.
        // This is a more robust way to check if the editor is ready for interaction.
        const editorStore = select('core/editor');
        if (!editorStore || !editorStore.getCurrentPostId()) {
            return;
        }

        // Get the current post's additional CSS classes
        let currentClasses = editorStore.getEditedPostAttribute('className') || '';
        const classToAdd = 'site-header';

        // Check if 'site-header' is already present
        if (!currentClasses.includes(classToAdd)) {
            // Add 'site-header' to the classes
            const newClasses = currentClasses ? `${currentClasses} ${classToAdd}` : classToAdd;

            // Dispatch an action to update the post's className
            dispatch('core/editor').editPost({ className: newClasses });
        }

        // Unsubscribe after the first successful update to avoid unnecessary checks
        unsubscribe();
    });
});