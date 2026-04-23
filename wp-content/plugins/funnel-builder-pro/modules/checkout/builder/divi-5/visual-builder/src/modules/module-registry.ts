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
import { miniCart } from './mini-cart';
import { checkoutForm } from './checkout-form';

// Auto-register modules when hook fires
addAction('divi.moduleLibrary.registerModuleLibraryStore.after', 'wfacpModules', () => {
	// Register MiniCart module
	try {
		if (!miniCart || !miniCart.metadata) {
			throw new Error('miniCart or miniCart.metadata is undefined');
		}
		const miniCartDefinition = omit(miniCart, 'metadata');
		registerModule(miniCart.metadata, miniCartDefinition);
	} catch (error) {
		// Error registering MiniCart module
	}

	// Register CheckoutForm module
	try {
		if (!checkoutForm || !checkoutForm.metadata) {
			throw new Error('checkoutForm or checkoutForm.metadata is undefined');
		}
		const checkoutFormDefinition = omit(checkoutForm, 'metadata');
		registerModule(checkoutForm.metadata, checkoutFormDefinition);
	} catch (error) {
		// Error registering CheckoutForm module
	}
});
