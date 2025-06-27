/**
 * @sw-package framework
 */

import mitt from 'mitt';

/**
 * The pattern for event names = component name in kebab case followed by the event
 */
interface Events extends Record<string | symbol, unknown> {
    'sw-product-detail-save-finish': undefined;
    'sw-language-switch-change-application-language': { languageId: string };
    'sw-sales-channel-detail-sales-channel-change': undefined;
    'sw-sales-channel-detail-base-sales-channel-change': undefined;
    'sw-sales-channel-list-add-new-channel': undefined;
    'sw-media-library-item-updated': undefined;
}

const emitter = mitt<Events>();

/**
 * @private
 */
export default emitter;
