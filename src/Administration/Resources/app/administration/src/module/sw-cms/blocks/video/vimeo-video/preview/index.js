import template from './sw-cms-preview-vimeo-video.html.twig';
import './sw-cms-preview-vimeo-video.scss';

/**
 * @private
 * @package buyers-experience
 */
export default {
    template,

    compatConfig: Shopware.compatConfig,

    computed: {
        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },
    },
};
