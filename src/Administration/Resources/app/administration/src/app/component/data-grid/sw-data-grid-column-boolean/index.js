import template from './sw-data-grid-column-boolean.html.twig';
import './sw-data-grid-column-boolean.scss';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 */
Component.register('sw-data-grid-column-boolean', {
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        isInlineEdit: {
            type: Boolean,
            required: false,
            default: false,
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
        // eslint-disable-next-line vue/require-prop-types
        value: {
            required: true,
        },
    },

    computed: {
        currentValue: {
            get() {
                return this.value;
            },

            set(newValue) {
                this.$emit('update:value', newValue);
            },
        },
    },
});
