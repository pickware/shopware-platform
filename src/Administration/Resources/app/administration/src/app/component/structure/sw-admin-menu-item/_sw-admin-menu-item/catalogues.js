/**
 * @sw-package discovery
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    id: 'sw-catalogue',
    moduleType: 'core',
    label: 'global.sw-admin-menu.navigation.mainMenuItemCatalogue',
    color: '#57D9A3',
    icon: 'regular-products',
    position: 20,
    level: 1,
    children: [
        {
            id: 'sw-product',
            moduleType: 'core',
            label: 'sw-product.general.mainMenuItemGeneral',
            color: '#57D9A3',
            path: 'sw.product.index',
            icon: 'regular-products',
            parent: 'sw-catalogue',
            position: 10,
            children: [],
            level: 2,
        },
        {
            id: 'sw-review',
            moduleType: 'core',
            label: 'sw-review.general.mainMenuItemList',
            color: '#57D9A3',
            path: 'sw.review.index',
            icon: 'regular-products',
            parent: 'sw-catalogue',
            position: 20,
            children: [],
            level: 2,
        },
        {
            id: 'sw-category',
            moduleType: 'core',
            path: 'sw.category.index',
            label: 'sw-category.general.mainMenuItemIndex',
            parent: 'sw-catalogue',
            position: 20,
            children: [],
            level: 2,
        },
        {
            path: 'sw.product.stream.index',
            label: 'sw-product-stream.general.mainMenuItemGeneral',
            id: 'sw-product-stream',
            moduleType: 'core',
            parent: 'sw-catalogue',
            color: '#57D9A3',
            position: 30,
            children: [],
            level: 2,
        },
        {
            id: 'sw-property',
            moduleType: 'core',
            label: 'sw-property.general.mainMenuItemGeneral',
            parent: 'sw-catalogue',
            path: 'sw.property.index',
            position: 40,
            children: [],
            level: 2,
        },
        {
            path: 'sw.manufacturer.index',
            label: 'sw-manufacturer.general.mainMenuItemList',
            id: 'sw-manufacturer',
            moduleType: 'core',
            parent: 'sw-catalogue',
            color: '#57D9A3',
            position: 50,
            children: [],
            level: 2,
        },
        {
            path: 'sw.foo.index',
            label: 'sw-foo.general.mainMenuItemList',
            id: 'sw-foo',
            moduleType: 'plugin',
            parent: 'sw-catalogue',
            position: 1010,
            children: [],
            level: 2,
        },
        {
            path: 'sw.bar.index',
            label: 'sw-bar.general.mainMenuItemList',
            id: 'sw-bar',
            moduleType: 'plugin',
            parent: 'sw-catalogue',
            position: 1010,
            children: [],
            level: 2,
        },
    ],
};
