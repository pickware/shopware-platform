/*
 * @sw-package inventory
 */

import template from './sw-property-detail-base.html.twig';

const { Component, Mixin } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    mixins: [
        Mixin.getByName('placeholder'),
    ],

    props: {
        propertyGroup: {
            type: Object,
            required: true,
            default() {
                return {};
            },
        },
        isLoading: {
            type: Boolean,
            default: false,
        },
        allowEdit: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
    },

    data() {
        return {
            sortingTypes: [
                {
                    value: 'alphanumeric',
                    label: this.$tc('sw-property.detail.alphanumericSortingType'),
                },
                {
                    value: 'position',
                    label: this.$tc('sw-property.detail.positionSortingType'),
                },
            ],
            displayTypes: [
                {
                    value: 'media',
                    label: this.$tc('sw-property.detail.mediaDisplayType'),
                },
                {
                    value: 'text',
                    label: this.$tc('sw-property.detail.textDisplayType'),
                },
                {
                    value: 'select',
                    label: this.$tc('sw-property.detail.selectDisplayType'),
                },
                {
                    value: 'color',
                    label: this.$tc('sw-property.detail.colorDisplayType'),
                },
            ],
        };
    },

    computed: {
        ...mapPropertyErrors('propertyGroup', [
            'name',
            'displayType',
            'sortingType',
        ]),

        displayTypeOptions() {
            return this.displayTypes.map((displayType) => {
                return {
                    id: displayType.value,
                    value: displayType.value,
                    label: displayType.label,
                };
            });
        },

        sortingTypeOptions() {
            return this.sortingTypes.map((sortingType) => {
                return {
                    id: sortingType.value,
                    value: sortingType.value,
                    label: sortingType.label,
                };
            });
        },
    },
};
