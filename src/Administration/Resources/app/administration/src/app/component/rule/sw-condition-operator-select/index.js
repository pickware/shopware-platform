import template from './sw-condition-operator-select.html.twig';
import './sw-condition-operator-select.scss';

const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

/**
 * @private
 * @sw-package fundamentals@after-sales
 */
export default {
    template: template,
    emits: ['change'],
    props: {
        operators: {
            type: Array,
            required: true,
        },

        condition: {
            type: Object,
            required: true,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },

        /**
         * The used condition snippets depend on the pre-operator snippets and should be plural or singular
         * depending on the pre-operator selection.
         */
        plural: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        operator: {
            get() {
                if (!this.condition.value) {
                    return null;
                }
                return this.condition.value.operator;
            },
            set(operator) {
                if (!this.condition.value) {
                    // eslint-disable-next-line vue/no-mutating-props
                    this.condition.value = {};
                }
                // eslint-disable-next-line vue/no-mutating-props
                this.condition.value = { ...this.condition.value, operator };
            },
        },

        operatorClasses() {
            return {
                'has--error': this.hasError,
            };
        },

        hasError() {
            return !!this.conditionValueOperatorError;
        },

        translatedOperators() {
            return this.operators.map(({ identifier, label }) => {
                return {
                    identifier,
                    label: this.plural ? this.$tc(label, 2) : this.$tc(label),
                };
            });
        },

        ...mapPropertyErrors('condition', ['value.operator']),
    },

    methods: {
        changeOperator(event) {
            this.condition.value = {
                ...(this.condition.value ?? {}),
                operator: event,
            };

            if (event === 'empty') {
                this.condition.value = { operator: 'empty' };
            }

            this.$emit('change', this.condition);
        },
    },
};
