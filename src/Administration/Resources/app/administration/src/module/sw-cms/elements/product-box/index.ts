/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-el-preview-product-box', () => import('./preview'));

/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-el-config-product-box', () => import('./config'));

/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-el-product-box', () => import('./component'));

/**
 * @private
 * @sw-package discovery
 */
Shopware.Service('cmsService').registerCmsElement({
    name: 'product-box',
    label: 'sw-cms.elements.productBox.label',
    component: 'sw-cms-el-product-box',
    previewComponent: 'sw-cms-el-preview-product-box',
    configComponent: 'sw-cms-el-config-product-box',
    defaultConfig: {
        product: {
            source: 'static',
            value: null,
            required: true,
            entity: {
                name: 'product',
                criteria: new Shopware.Data.Criteria(1, 25).addAssociation('cover'),
            },
        },
        boxLayout: {
            source: 'static',
            value: 'standard',
        },
        displayMode: {
            source: 'static',
            value: 'standard',
        },
        verticalAlign: {
            source: 'static',
            value: null,
        },
    },
    defaultData: {
        boxLayout: 'standard',
        product: null,
    },
    collect: Shopware.Service('cmsService').getCollectFunction(),
});
