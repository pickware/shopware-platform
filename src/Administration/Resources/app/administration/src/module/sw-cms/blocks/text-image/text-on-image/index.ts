/**
 * @private
 * @sw-package discovery
 */
import CMS from '../../../constant/sw-cms.constant';

/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-preview-text-on-image', () => import('./preview'));

/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-block-text-on-image', () => import('./component'));

/**
 * @private
 * @sw-package discovery
 */
Shopware.Service('cmsService').registerCmsBlock({
    name: 'text-on-image',
    label: 'sw-cms.blocks.textImage.textOnImage.label',
    category: 'text-image',
    component: 'sw-cms-block-text-on-image',
    previewComponent: 'sw-cms-preview-text-on-image',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
        backgroundMedia: {
            url: '/administration/administration/static/img/cms/preview_mountain_large.jpg',
        },
    },
    slots: {
        content: {
            type: 'text',
            default: {
                config: {
                    content: {
                        source: 'static',
                        value: `
                        <h2 style="text-align: center; color: #FFFFFF">Lorem Ipsum</h2>
                        <p style="text-align: center; color: #FFFFFF">Lorem ipsum dolor sit amet, consetetur
                        sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam
                        lorem ipsum dolor sit amet.</p>
                        `.trim(),
                    },
                },
                data: {
                    media: {
                        value: CMS.MEDIA.previewMountain,
                        source: 'default',
                    },
                },
            },
        },
    },
});
