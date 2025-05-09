/**
 * @sw-package inventory
 */

import template from './sw-product-variants-configurator-selection.html.twig';
import './sw-product-variants-configurator-selection.scss';

const { Mixin } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    emits: ['option-select'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        product: {
            type: Object,
            required: true,
        },
    },

    watch: {
        isAddOnly() {
            this.selectOptions(this.$refs.optionGrid);
        },
    },

    computed: {
        configuratorSettingsRepository() {
            // get configuratorSettingsRepository
            return this.repositoryFactory.create(
                this.product.configuratorSettings.entity,
                this.product.configuratorSettings.source,
            );
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        /**
         * Important: options = configurators
         * Reason: Is extended from sw-property-search
         */
        addOptionCount() {
            this.groups.forEach((group) => {
                const optionCount = this.options.filter((configurator) => {
                    // check if option belongs to group
                    return configurator.option.groupId === group.id && !configurator.isDeleted;
                });

                // set reactive
                group.optionCount = optionCount.length;
            });

            this.$emit('option-select');
        },

        selectOptions(grid) {
            grid.selectAll(false);
            this.preventSelection = true;
            this.options.forEach((configurator) => {
                if (configurator.option) {
                    configurator.option.gridDisabled = this.disabled && !configurator._isNew;
                    grid.selectItem(!configurator.isDeleted, configurator.option);
                }
            });

            this.preventSelection = false;
        },

        onOptionSelect(selection, item) {
            if (this.preventSelection) {
                return;
            }

            const exists = this.options.find((i) => i.optionId === item.id);

            if (exists) {
                this.options.remove(exists.id);
                this.addOptionCount();
                return;
            }

            const newOption = this.configuratorSettingsRepository.create();
            newOption.optionId = item.id;
            newOption.option = item;

            this.options.add(newOption);

            this.addOptionCount();
        },
    },
};
