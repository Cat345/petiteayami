/**
 * Module Registry for Thank You Page Divi 5 Visual Builder.
 * @since 1.0.0
 */
import { omit } from 'lodash';
import { addAction } from '@wordpress/hooks';
import { registerModule } from '@divi/module-library';
import { customerDetails } from './customer-details';
import { orderDetails } from './order-details';

addAction('divi.moduleLibrary.registerModuleLibraryStore.after', 'wftyModules', () => {
  try {
    if (customerDetails?.metadata) {
      registerModule(customerDetails.metadata, omit(customerDetails, 'metadata'));
    }
  } catch (e) {
    // Silently fail
  }
  try {
    if (orderDetails?.metadata) {
      registerModule(orderDetails.metadata, omit(orderDetails, 'metadata'));
    }
  } catch (e) {
    // Silently fail
  }
});
