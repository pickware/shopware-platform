import template from './sw-text-editor-toolbar-button.html.twig';
import './sw-text-editor-toolbar-button.scss';

/**
 * @sw-package framework
 * @deprecated tag:v6.8.0 - Will be removed, use mt-text-editor instead.
 *
 * @private
 */
export default {
    template,

    emits: [
        'button-click',
        'menu-toggle',
    ],

    props: {
        buttonConfig: {
            type: Object,
            required: true,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },

        isInlineEdit: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            flyoutClasses: [],
        };
    },

    computed: {
        classes() {
            return {
                'is--active': !!this.buttonConfig.active || this.buttonConfig.expanded,
                'is--disabled': !!this.disabled,
            };
        },

        tooltipAppearance() {
            return this.isInlineEdit ? 'light' : 'dark';
        },
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            this.$device.onResize({
                listener: this.positionLinkMenu,
            });
        },
        buttonHandler(event, button) {
            if (this.disabled) {
                return null;
            }

            return button.children || button.type === 'link' || button.type === 'table' || button.type === 'foreColor'
                ? this.onToggleMenu(event, button)
                : this.handleButtonClick(button);
        },

        childActive(child) {
            return {
                'is--active': !!child.active,
            };
        },

        handleButtonClick(button, parent = null) {
            if (this.disabled) {
                return;
            }

            this.$emit('button-click', button, parent);
            this.$emit('menu-toggle', null, button);
        },

        onToggleMenu(event, button) {
            if (
                ![
                    'link',
                    'table',
                    'foreColor',
                ].includes(button.type) &&
                !button.children
            ) {
                return;
            }

            if (button.type === 'foreColor' && event.target.closest('.mt-colorpicker__colorpicker')) {
                return;
            }

            if (button.type === 'link' && event.target.closest('.sw-text-editor-toolbar-button__link-menu')) {
                return;
            }

            if (button.type === 'table' && event.target.closest('.sw-text-editor-toolbar-button__table-menu')) {
                return;
            }

            if (event.target.closest('.sw-text-editor-toolbar-button__children')) {
                return;
            }

            this.$emit('menu-toggle', event, button);
        },

        getDropdownClasses(buttonConfig) {
            const position = buttonConfig.dropdownPosition || 'right';
            const positionClass = `is--${position}`;

            return [positionClass];
        },

        onChildMounted() {
            const flyoutMenu = this.$refs?.flyoutMenu;

            if (!flyoutMenu || this.flyoutClasses.includes('is--left', 'is--right')) {
                return;
            }

            const flyoutMenuRightBound = flyoutMenu.getBoundingClientRect().right;
            const windowRightBound = this.$root.$el.parentElement.getBoundingClientRect().right;

            const isOutOfRightBound = flyoutMenuRightBound - windowRightBound > 0;
            this.flyoutClasses = isOutOfRightBound ? ['is--left'] : ['is--right'];
        },

        getTooltipConfig(buttonConfig, child) {
            return {
                disabled: !child.title,
                appearance: this.tooltipAppearance,
                width: 'auto',
                message: child.title,
                showDelay: buttonConfig.tooltipShowDelay || 100,
                hideDelay: buttonConfig.tooltipHideDelay || 100,
            };
        },

        positionLinkMenu() {
            const flyoutLinkMenu = this.$refs?.flyoutLinkMenu;

            if (!(flyoutLinkMenu instanceof HTMLElement)) {
                return;
            }

            const flyoutLinkMenuWidth = flyoutLinkMenu.clientWidth;

            const linkIcon = this.$el;
            const linkIconWidth = linkIcon.clientWidth;

            const linkIconRightBound = linkIcon.getBoundingClientRect().right;

            const linkFlyoutMenuRightBound = linkIconRightBound - linkIconWidth + flyoutLinkMenuWidth;
            const windowRightBound = this.$device.getViewportWidth();

            const modalContainer = this.$el.closest('.mt-modal');
            const containerRightBound = modalContainer ? modalContainer.getBoundingClientRect().right : windowRightBound;

            const isOutOfRightBound = containerRightBound - linkFlyoutMenuRightBound;

            let flyoutLinkLeftOffset = 0;
            let arrowPosition = 10;

            if (isOutOfRightBound < 0) {
                if (modalContainer) {
                    flyoutLinkLeftOffset = -(flyoutLinkMenuWidth / 2) + linkIconWidth / 2;
                    arrowPosition = flyoutLinkMenuWidth / 2;
                } else {
                    flyoutLinkLeftOffset = isOutOfRightBound - 50;
                    arrowPosition = Math.abs(flyoutLinkLeftOffset) + 10;
                }
            }

            flyoutLinkMenu.style.setProperty('--flyoutLinkLeftOffset', `${flyoutLinkLeftOffset}px`);
            flyoutLinkMenu.style.setProperty('--arrow-position', `${arrowPosition}px`);
        },
    },
};
