import template from './sw-ai-copilot-badge.html.twig';
import './sw-ai-copilot-badge.scss';

/**
 * @sw-package framework
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        label: {
            type: Boolean,
            required: false,
            default: true,
        },
    },
});
