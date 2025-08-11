import template from './sw-condition-order-custom-field.html.twig';
import './sw-condition-order-custom-field.scss';

const { Component, Filter, Mixin } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const { Criteria } = Shopware.Data;

/**
 * @public
 * @sw-package fundamentals@after-sales
 * @description Order custom item for the condition-tree. This component must be a child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-order-custom-field :condition="condition"></sw-condition-order-custom-field>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'feature',
    ],

    mixins: [
        Mixin.getByName('sw-inline-snippet'),
    ],

    computed: {
        customFieldCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('customFieldSet');
            criteria.addFilter(Criteria.equals('customFieldSet.relations.entityName', 'order'));
            criteria.addSorting(Criteria.sort('customFieldSet.name', 'ASC'));
            return criteria;
        },

        operator: {
            get() {
                this.ensureValueExist();
                return this.condition.value.operator;
            },
            set(operator) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, operator };
            },
        },

        renderedField: {
            get() {
                this.ensureValueExist();
                return this.condition.value.renderedField;
            },
            set(renderedField) {
                this.ensureValueExist();
                this.condition.value = {
                    ...this.condition.value,
                    renderedField,
                };
            },
        },

        selectedField: {
            get() {
                this.ensureValueExist();
                return this.condition.value.selectedField;
            },
            set(selectedField) {
                this.ensureValueExist();
                this.condition.value = {
                    ...this.condition.value,
                    selectedField,
                };
            },
        },

        selectedFieldSet: {
            get() {
                this.ensureValueExist();
                return this.condition.value.selectedFieldSet;
            },
            set(selectedFieldSet) {
                this.ensureValueExist();
                this.condition.value = {
                    ...this.condition.value,
                    selectedFieldSet,
                };
            },
        },

        renderedFieldValue: {
            get() {
                this.ensureValueExist();
                return this.condition.value.renderedFieldValue;
            },
            set(renderedFieldValue) {
                this.ensureValueExist();
                this.condition.value = {
                    ...this.condition.value,
                    renderedFieldValue,
                };
            },
        },

        operators() {
            return this.conditionDataProviderService.getOperatorSetByComponent(this.renderedField);
        },

        currentError() {
            return (
                this.conditionValueRenderedFieldError ||
                this.conditionValueSelectedFieldError ||
                this.conditionValueSelectedFieldSetError ||
                this.conditionValueOperatorError ||
                this.conditionValueRenderedFieldValueError
            );
        },

        truncateFilter() {
            return Filter.getByName('truncate');
        },

        ...mapPropertyErrors('condition', [
            'value.renderedField',
            'value.selectedField',
            'value.selectedFieldSet',
            'value.operator',
            'value.renderedFieldValue',
        ]),
    },

    methods: {
        getFieldDescription(item) {
            return this.getInlineSnippet(item.customFieldSet.config.label) || item.customFieldSet.name;
        },

        onFieldChange(id) {
            if (!this.$refs.selectedField.resultCollection?.has(id)) {
                this.operator = null;
                this.renderedFieldValue = null;
                this.renderedField = null;
                this.selectedFieldSet = null;
                return;
            }

            this.operator = null;
            this.renderedFieldValue = null;
            this.renderedField = this.$refs.selectedField.resultCollection.get(id);
            this.selectedFieldSet = this.renderedField.customFieldSetId;
        },
    },
};
