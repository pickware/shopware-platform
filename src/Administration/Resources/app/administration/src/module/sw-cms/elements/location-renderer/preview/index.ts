import template from './sw-cms-el-preview-location-renderer.html.twig';
import type { ElementDataProp } from '../index';

const { Component } = Shopware;

/**
 * @private
 * @sw-package discovery
 */
export default Component.wrapComponentConfig({
    template,

    props: {
        elementData: {
            type: Object as PropType<ElementDataProp>,
            required: true,
        },
    },

    computed: {
        src(): string {
            return this.elementData.appData.baseUrl;
        },

        previewLocation(): string {
            return `${this.elementData.name}-preview`;
        },

        publishingKey(): string {
            return `${this.elementData.name}__config-element`;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            Shopware.ExtensionAPI.publishData({
                id: this.publishingKey,
                path: 'element',
                scope: this,
            });
        },
    },
});
