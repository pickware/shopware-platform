import { MtTextEditor as MtTextEditorOriginal } from '@shopware-ag/meteor-component-library';
// eslint-disable-next-line max-len
import type { CustomButton } from '@shopware-ag/meteor-component-library/dist/esm/components/form/mt-text-editor/_internal/mt-text-editor-toolbar';
import template from './mt-text-editor.html.twig';
import './mt-text-editor.scss';

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for mt-text-editor. Replaces the link
 * button with a custom implementation specific to the Shopware admin.
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    components: {
        'mt-text-editor-original': MtTextEditorOriginal,
    },

    props: {
        modelValue: {
            type: String,
            required: false,
            default: '',
        },

        /**
         * Custom buttons to be added to the toolbar
         */
        customButtons: {
            type: Array as PropType<CustomButton[]>,
            default: () => [],
        },

        /**
         * Excluded buttons from the toolbar
         */
        excludedButtons: {
            type: Array as PropType<string[]>,
            default: () => [],
        },
    },

    emits: [
        'update:modelValue',
    ],

    computed: {
        compatValue: {
            get() {
                return this.modelValue;
            },
            set(value: string) {
                this.$emit('update:modelValue', value);
            },
        },

        mergedCustomButtons() {
            const editorButtons: CustomButton[] = [];

            return [
                ...editorButtons,
                ...this.customButtons,
            ];
        },

        mergedExcludedButtons() {
            const excludedEditorButtons: string[] = [];

            return [
                ...excludedEditorButtons,
                ...this.excludedButtons,
            ];
        },
    },

    methods: {
        getSlots() {
            return this.$slots;
        },

        onUpdateModelValue(value: string) {
            this.$emit('update:modelValue', value);
        },
    },
});
