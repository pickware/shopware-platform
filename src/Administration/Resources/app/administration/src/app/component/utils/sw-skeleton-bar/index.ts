import template from './sw-skeleton-bar.html.twig';

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-skeleton-bar and mt-skeleton-bar. Autoswitches between the two components.
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    computed: {
        useMeteorComponent() {
            // Use new meteor component in major
            if (Shopware.Feature.isActive('ENABLE_METEOR_COMPONENTS')) {
                return true;
            }

            // Throw warning when deprecated component is used
            Shopware.Utils.debug.warn(
                'sw-skeleton-bar',
                // eslint-disable-next-line max-len
                'The old usage of "sw-skeleton-bar" is deprecated and will be removed in v6.8.0.0. Please use "mt-skeleton-bar" instead.',
            );

            return false;
        },
    },
});
