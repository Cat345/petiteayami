// Local dependencies.
import { OptinFormAttrs } from './types';
import defaultRenderAttributes from './module-default-render-attributes.json';

/**
 * Placeholder content for a newly added Optin Form module.
 *
 * IMPORTANT: This should match defaultAttrs exactly.
 * placeholderContent is only used when a module is first added.
 * Once attributes are saved, Divi uses the saved attributes, not this.
 *
 * @since 1.0.0
 */
export const placeholderContent: OptinFormAttrs = defaultRenderAttributes as OptinFormAttrs;
