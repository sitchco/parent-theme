import domReady from '@wordpress/dom-ready';
import { select, dispatch } from '@wordpress/data';

domReady(function() {
    const unsubscribe = select('core/editor').subscribe(() => {
        // Check if the editor is ready
        if (!select('core/editor').isEditorReady()) {
            return;
        }

        // Get the current post's additional CSS classes
        let currentClasses = select('core/editor').getEditedPostAttribute('className') || '';
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