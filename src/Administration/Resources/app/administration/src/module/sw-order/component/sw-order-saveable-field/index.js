import template from './sw-order-saveable-field.html.twig';
import './sw-order-saveable-field.scss';

/**
 * @sw-package checkout
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: [
        'value-change',
        'update:value',
    ],

    props: {
        // eslint-disable-next-line vue/require-prop-types
        value: {
            required: true,
            default: null,
        },
        type: {
            type: String,
            required: true,
            default: 'text',
        },
        // eslint-disable-next-line vue/require-prop-types
        placeholder: {
            required: false,
            default: null,
        },
        editable: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
    },

    data() {
        return {
            isEditing: false,
            isLoading: false,
        };
    },

    computed: {
        component() {
            switch (this.type) {
                case 'checkbox':
                    return 'sw-checkbox-field';
                case 'colorpicker':
                    return 'sw-colorpicker';
                case 'compactColorpicker':
                    return 'sw-compact-colorpicker';
                case 'date':
                    return 'sw-datepicker';
                case 'email':
                    return 'sw-email-field';
                case 'number':
                    return 'sw-number-field';
                case 'password':
                    return 'mt-password-field';
                case 'radio':
                    return 'sw-radio-field';
                case 'select':
                    return 'sw-select-field';
                case 'switch':
                    return 'sw-switch-field';
                case 'textarea':
                    return 'sw-textarea-field';
                case 'url':
                    return 'sw-url-field';
                default:
                    return 'sw-text-field';
            }
        },

        valuePropName() {
            switch (this.component) {
                case 'mt-textarea':
                case 'mt-switch':
                case 'mt-number-field':
                    return 'modelValue';
                default:
                    return 'value';
            }
        },

        computedAttrs() {
            return {
                ...this.$attrs,
                [this.valuePropName]: this.value,
                'onUpdate:modelValue': (value) => this.$emit('update:value', value),
            };
        },
    },

    methods: {
        onClick() {
            if (this.editable) {
                this.isEditing = true;
            }
        },

        onSaveButtonClicked() {
            this.isEditing = false;
            this.$emit('value-change', this.$refs['edit-field'].currentValue);
        },

        onCancelButtonClicked() {
            this.isEditing = false;
        },
    },
};
