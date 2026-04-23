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
import { optinForm } from './optin-form';

// Auto-register modules when hook fires
addAction('divi.moduleLibrary.registerModuleLibraryStore.after', 'wfopModules', () => {
	// Register optin-form module
	try {
		if (!optinForm || !optinForm.metadata) {
			throw new Error('optinForm or optinForm.metadata is undefined');
		}
		const optinFormDefinition = omit(optinForm, 'metadata');
		registerModule(optinForm.metadata, optinFormDefinition);
	} catch (error) {
		// Silently fail - error handling is done by Divi framework
	}
});
