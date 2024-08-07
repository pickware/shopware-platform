/**
 * @package buyers-experience
 */
import template from './sw-cms-section-actions.html.twig';
import './sw-cms-section-actions.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        section: {
            type: Object,
            required: true,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    inject: {
        feature: {
            from: 'feature',
            default: null,
        },

        swCmsSectionEmitPageConfigOpen: {
            from: 'swCmsSectionEmitPageConfigOpen',
            default: null,
        },
    },

    data() {
        return {
            /* @deprecated: tag:v6.7.0 - Will be removed use cmsPageStateStore instead. */
            cmsPageState: Shopware.Store.get('cmsPageState'),
        };
    },

    computed: {
        componentClasses() {
            return {
                'is--disabled': this.disabled,
            };
        },
        cmsPageStateStore() {
            return Shopware.Store.get('cmsPageState');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.cmsPageState.selectedSection) {
                this.cmsPageStateStore.setSection(this.section);
            }
        },

        selectSection() {
            if (this.disabled) {
                return;
            }

            this.cmsPageStateStore.setSection(this.section);

            if (this.isCompatEnabled('INSTANCE_EVENT_EMITTER')) {
                this.$parent.$parent.$emit('page-config-open', 'itemConfig');
            } else {
                this.swCmsSectionEmitPageConfigOpen?.('itemConfig');
            }
        },
    },
};
