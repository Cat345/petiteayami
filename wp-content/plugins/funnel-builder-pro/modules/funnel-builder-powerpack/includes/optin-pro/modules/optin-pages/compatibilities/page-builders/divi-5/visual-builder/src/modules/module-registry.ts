/**
 * Module Registry for Visual Builder.
 *
 * This file provides a centralized way to register all Visual Builder modules.
 * To add a new module, import it and add it to the modules array.
 *
 * @since 1.0.0
 */

import { omit } from 'lodash';
import { addAction } from '@wordpress/hooks';
import { registerModule } from '@divi/module-library';

// Import modules
import { optinFormPopup } from './optin-form-popup';

// Auto-register modules when hook fires
addAction('divi.moduleLibrary.registerModuleLibraryStore.after', 'wfoppModules', () => {
	// Register optin-form-popup module
	try {
		if (!optinFormPopup || !optinFormPopup.metadata) {
			throw new Error('optinFormPopup or optinFormPopup.metadata is undefined');
		}
		const optinFormPopupDefinition = omit(optinFormPopup, 'metadata');
		registerModule(optinFormPopup.metadata, optinFormPopupDefinition);
	} catch (error) {
		// Silently fail - error handling is done by Divi framework
		console.error('Error registering optin-form-popup module:', error);
	}
});
