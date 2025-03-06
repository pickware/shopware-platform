import template from './sw-checkbox-field.html.twig';

const { Component } = Shopware;

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-checkbox-field and mt-checkbox-field. Autoswitches between the two components.
 */
Component.register('sw-checkbox-field', {
    template,

    props: {
        modelValue: {
            type: String,
            required: false,
            default: null,
        },

        value: {
            type: Boolean,
            required: false,
            default: null,
        },
    },

    computed: {
        compatValue: {
            get() {
                if (this.value === null || this.value === undefined) {
                    return this.modelValue;
                }

                return this.value;
            },
            set(value: string) {
                this.$emit('update:value', value);
                this.$emit('update:modelValue', value);
            },
        },
    },

    methods: {
        getSlots() {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access

            return this.$slots;
        },

        handleUpdateChecked(event: unknown) {
            this.$emit('update:checked', event);

            // Emit old event for backwards compatibility
            this.$emit('update:value', event);
        },
    },
});
