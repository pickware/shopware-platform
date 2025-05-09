/**
 * @sw-package framework
 */

import template from './sw-settings-logging-entry-info.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: ['close'],

    props: {
        logEntry: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            activeTab: 'raw',
        };
    },

    computed: {
        displayString() {
            return this.logEntry.context ? JSON.stringify(this.logEntry.context, null, 2) : '';
        },
    },

    methods: {
        onClose() {
            this.$emit('close');
        },
    },
};
