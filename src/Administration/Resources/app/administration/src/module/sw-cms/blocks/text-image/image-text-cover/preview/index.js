import template from './sw-cms-preview-image-text-cover.html.twig';
import './sw-cms-preview-image-text-cover.scss';

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    computed: {
        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },
    },
};
