import './sw-shopware-updates-info.scss';
import template from './sw-shopware-updates-info.html.twig';

const { Component } = Shopware;

/**
 * @sw-package framework
 * @private
 */
Component.register('sw-settings-shopware-updates-info', {
    template,

    props: {
        changelog: {
            type: String,
            required: true,
        },
        isLoading: {
            type: Boolean,
        },
    },
});
