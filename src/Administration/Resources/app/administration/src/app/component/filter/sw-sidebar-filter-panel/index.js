/**
 * @sw-package framework
 */

import template from './sw-sidebar-filter-panel.html.twig';
import './sw-sidebar-filter-panel.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-sidebar-filter-panel', {
    template,

    props: {
        activeFilterNumber: {
            type: Number,
            required: true,
        },
    },

    computed: {},

    methods: {
        resetAll() {
            this.$refs.filterPanel.resetAll();
        },
    },
});
