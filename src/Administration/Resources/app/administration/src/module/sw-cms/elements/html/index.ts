/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-el-html', () => import('./component'));
/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-el-preview-html', () => import('./preview'));
/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-el-config-html', () => import('./config'));

/**
 * @private
 * @sw-package discovery
 */
Shopware.Service('cmsService').registerCmsElement({
    name: 'html',
    label: 'sw-cms.elements.html.label',
    component: 'sw-cms-el-html',
    configComponent: 'sw-cms-el-config-html',
    previewComponent: 'sw-cms-el-preview-html',
    defaultConfig: {
        content: {
            source: 'static',
            value: `
<h2>Lorem ipsum dolor</h2>
<p>Lorem ipsum dolor sit amet</p>
<button type="button">
    Click me!
</button>`.trim(),
        },
    },
});
