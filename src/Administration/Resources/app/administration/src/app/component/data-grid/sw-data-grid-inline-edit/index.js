import template from './sw-data-grid-inline-edit.html.twig';
import './sw-data-grid-inline-edit.scss';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 */
Component.register('sw-data-grid-inline-edit', {
    template,

    inject: [
        'feature',
    ],

    props: {
        column: {
            type: Object,
            required: true,
            default() {
                return {};
            },
        },
        // eslint-disable-next-line vue/require-prop-types
        value: {
            required: true,
        },
        compact: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            currentValue: null,
        };
    },

    computed: {
        classes() {
            return {
                'is--compact': this.compact,
            };
        },

        inputFieldSize() {
            return this.compact ? 'small' : 'default';
        },
    },

    created() {
        this.createdComponent();
    },

    beforeUnmount() {
        this.beforeDestroyComponent();
    },

    methods: {
        createdComponent() {
            this.currentValue = this.value;

            this.$parent.$parent.$on('inline-edit-assign', this.emitInput);
        },

        beforeDestroyComponent() {
            this.$parent.$parent.$off('inline-edit-assign', this.emitInput);
        },

        emitInput() {
            this.$emit('update:value', this.currentValue);
        },
    },
});
