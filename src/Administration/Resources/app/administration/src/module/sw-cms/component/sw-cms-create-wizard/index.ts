import { type PropType } from 'vue';
import template from './sw-cms-create-wizard.html.twig';
import './sw-cms-create-wizard.scss';

const { Filter } = Shopware;

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: [
        'feature',
        'cmsPageTypeService',
        'customEntityDefinitionService',
    ],

    emits: [
        'on-section-select',
        'wizard-complete',
    ],

    props: {
        page: {
            type: Object as PropType<Entity<'cms_page'>>,
            required: true,
        },
    },

    data() {
        return {
            step: 1,
            steps: {
                pageType: 1,
                sectionType: 2,
                pageName: 3,
            },
        } as {
            step: number;
            steps: {
                pageType: number;
                sectionType: number;
                pageName: number;
                [key: string]: number;
            };
        };
    },

    computed: {
        visiblePageTypes() {
            return this.cmsPageTypeService.getVisibleTypes();
        },

        currentPageType() {
            return this.cmsPageTypeService.getType(this.page.type);
        },

        isCustomEntityType() {
            return this.page.type.startsWith('custom_entity_');
        },

        isCompletable() {
            return [
                this.page.name,
                !this.isCustomEntityType || this.page.entity,
            ].every((condition) => condition);
        },

        customEntities() {
            return this.customEntityDefinitionService.getCmsAwareDefinitions().map((entity) => {
                const snippetKey = `${entity.entity}.moduleTitle`;
                const value = entity.entity;

                return {
                    value,
                    label: this.$te(snippetKey) ? this.$tc(snippetKey) : value,
                };
            });
        },

        pagePreviewMedia() {
            const sections = this.page.sections ?? [];
            if (sections.length < 1) {
                return '';
            }

            const imgPath = 'administration/administration/static/img/cms';

            return `url(${this.assetFilter(`${imgPath}/preview_${this.page.type}_${sections[0].type}.png`)})`;
        },

        pagePreviewStyle() {
            return {
                'background-image': this.pagePreviewMedia,
                'background-size': 'cover',
            };
        },

        assetFilter() {
            return Filter.getByName('asset');
        },

        cmsPageStore() {
            return Shopware.Store.get('cmsPage');
        },
    },

    watch: {
        step(newStep: number) {
            if (this.getStepName(newStep) === 'sectionType') {
                this.page.sections = new Shopware.Data.EntityCollection(
                    `/cms-page/${this.page.id}/sections`,
                    'cms_section',
                    Shopware.Context.api,
                );
            }
        },
    },

    methods: {
        goToStep(stepName: string) {
            this.step = this.steps[stepName];
        },

        getStepName(stepValue: number) {
            const find = Object.entries(this.steps).find((step) => {
                return stepValue === step[1];
            });

            if (!find) {
                return '';
            }

            return find[0];
        },

        onPageTypeSelect(type: string) {
            this.cmsPageStore.setCurrentPageType(type);

            this.page.type = type;

            this.goToStep('sectionType');
        },

        onSectionSelect(section: Entity<'cms_section'>) {
            this.goToStep('pageName');

            this.$emit('on-section-select', section);
        },

        onCompletePageCreation() {
            if (!this.page.name) {
                return;
            }

            this.$emit('wizard-complete');
        },
    },
});
