import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import { ACTION } from 'src/module/sw-flow/constant/flow.constant';
import { createPinia, setActivePinia } from 'pinia';

/**
 * @sw-package after-sales
 */

function getSequencesCollection(collection = []) {
    return new EntityCollection(
        '/flow_sequence',
        'flow_sequence',
        null,
        { isShopwareContext: true },
        collection,
        collection.length,
        null,
    );
}

const sequenceFixture = {
    id: '2',
    actionName: '',
    ruleId: null,
    parentId: '1',
    position: 1,
    displayGroup: 1,
    trueCase: false,
    config: {
        entity: 'Customer',
        tagIds: ['123'],
    },
};

const sequencesFixture = [
    {
        ...sequenceFixture,
        actionName: ACTION.ADD_TAG,
    },
];

const mockBusinessEvents = [
    {
        name: 'checkout.customer.before.login',
        mailAware: true,
        aware: ['Shopware\\Core\\Framework\\Event\\SalesChannelAware'],
    },
    {
        name: 'checkout.customer.changed-payment-method',
        mailAware: false,
        aware: ['Shopware\\Core\\Framework\\Event\\SalesChannelAware'],
    },
    {
        name: 'checkout.customer.deleted',
        mailAware: true,
        aware: ['Shopware\\Core\\Framework\\Event\\SalesChannelAware'],
    },
    {
        name: 'checkout.disabledElement',
    },
];

const mockTranslations = {
    'sw-flow.triggers.before': 'Before',
    'sw-flow.triggers.mail': 'Mail',
    'sw-flow.triggers.send': 'Send',
    'sw-flow.triggers.checkout': 'Checkout',
    'sw-flow.triggers.customer': 'Customer',
    'sw-flow.triggers.login': 'Login',
    'sw-flow.triggers.changedPaymentMethod': 'Changed payment method',
    'sw-flow.triggers.deleted': 'Deleted',
    'sw-flow.triggers.disabledElement': 'Disabled Element',
};

const pinia = createPinia();
async function createWrapper(propsData) {
    return mount(
        await wrapTestComponent('sw-flow-trigger', {
            sync: true,
        }),
        {
            props: {
                eventName: '',
                ...propsData,
            },
            global: {
                plugins: [pinia],
                mocks: {
                    $tc(translationKey) {
                        return mockTranslations[translationKey] ? mockTranslations[translationKey] : translationKey;
                    },

                    $te(translationKey) {
                        return !!mockTranslations[translationKey];
                    },
                },
                provide: {
                    businessEventService: {
                        getBusinessEvents: jest.fn(() => {
                            return Promise.resolve(mockBusinessEvents);
                        }),
                    },
                    repositoryFactory: {
                        create: () => ({}),
                    },
                    shortcutService: {
                        stopEventListener() {},
                        startEventListener() {},
                    },
                },
                stubs: {
                    'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-container': await wrapTestComponent('sw-container'),
                    'sw-tree': await wrapTestComponent('sw-tree', {
                        sync: true,
                    }),
                    'sw-tree-item': await wrapTestComponent('sw-tree-item', {
                        sync: true,
                    }),
                    'sw-tree-input-field': await wrapTestComponent('sw-tree-input-field'),
                    'sw-vnode-renderer': await wrapTestComponent('sw-vnode-renderer'),
                    'sw-flow-event-change-confirm-modal': await wrapTestComponent('sw-flow-event-change-confirm-modal'),
                    'sw-inheritance-switch': await wrapTestComponent('sw-inheritance-switch'),
                    'sw-ai-copilot-badge': await wrapTestComponent('sw-ai-copilot-badge'),
                    'sw-help-text': await wrapTestComponent('sw-help-text'),
                    'sw-confirm-field': await wrapTestComponent('sw-confirm-field'),
                    'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                    'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
                    'sw-context-button': await wrapTestComponent('sw-context-button'),
                    'sw-context-menu': await wrapTestComponent('sw-context-menu'),
                    'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item'),
                    'router-link': true,
                    'sw-skeleton': await wrapTestComponent('sw-skeleton'),
                    'sw-text-field': await wrapTestComponent('sw-text-field'),
                    'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                    'sw-highlight-text': true,
                    'sw-field-error': true,
                    'sw-loader': true,
                    'sw-field-copyable': true,
                },
            },
        },
    );
}

describe('src/module/sw-flow/component/sw-flow-trigger', () => {
    beforeAll(() => {
        Shopware.Service().register('ruleConditionDataProviderService', () => {
            return {
                getRestrictedRules: () => Promise.resolve([]),
            };
        });

        Shopware.Service().register('businessEventService', () => {
            return {
                getBusinessEvents: () => Promise.resolve(mockBusinessEvents),
            };
        });

        window.HTMLElement.prototype.scrollIntoView = jest.fn();
    });

    beforeEach(() => {
        setActivePinia(pinia);
    });

    afterEach(async () => {
        await flushPromises();
        jest.restoreAllMocks();
    });

    afterAll(() => {
        delete window.HTMLElement.prototype.scrollIntoView;
    });

    it('should display event tree when focus search field', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        let eventTree = wrapper.find('.sw-tree');
        expect(eventTree.exists()).toBeFalsy();

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');
        await flushPromises();

        eventTree = wrapper.find('.sw-tree');

        expect(eventTree.exists()).toBeTruthy();
    });

    it('should display event tree with event get from flow state', async () => {
        Shopware.Store.get('swFlow').triggerEvents = mockBusinessEvents;

        const wrapper = await createWrapper();
        await wrapper.setProps({
            isUnknownTrigger: true,
        });
        await flushPromises();

        let eventTree = wrapper.find('.sw-tree');
        expect(eventTree.exists()).toBeFalsy();

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        expect(searchField.attributes().placeholder).toBe('sw-flow.detail.trigger.unknownTriggerPlaceholder');
        await searchField.trigger('focus');
        await flushPromises();

        eventTree = wrapper.find('.sw-tree');
        expect(eventTree.exists()).toBeTruthy();
        Shopware.Store.get('swFlow').triggerEvents = [];
    });

    it('should show event name with correct format', async () => {
        const wrapper = await createWrapper({
            eventName: 'mail.before.send',
        });
        await flushPromises();

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        expect(searchField.element.value).toBe('Mail / Before / Send');
    });

    it('should show event name from custom event snippet with correct format', async () => {
        const wrapper = await createWrapper({
            eventName: 'swag.open.the_doors',
        });
        await flushPromises();

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        expect(searchField.element.value).toBe('swag / open / the doors');
    });

    it('should get event tree data correctly', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.eventTree).toEqual([
            {
                childCount: 2,
                id: 'checkout',
                name: 'Checkout',
                parentId: null,
                disabled: false,
                disabledToolTipText: null,
            },
            {
                childCount: 3,
                id: 'checkout.customer',
                name: 'Customer',
                parentId: 'checkout',
                disabled: false,
                disabledToolTipText: null,
            },
            {
                childCount: 1,
                id: 'checkout.customer.before',
                name: 'Before',
                parentId: 'checkout.customer',
                disabled: false,
                disabledToolTipText: null,
            },
            {
                childCount: 0,
                id: 'checkout.customer.before.login',
                name: 'Login',
                parentId: 'checkout.customer.before',
                disabled: false,
                disabledToolTipText: null,
            },
            {
                childCount: 0,
                id: 'checkout.customer.changed-payment-method',
                name: 'Changed payment method',
                parentId: 'checkout.customer',
                disabled: false,
                disabledToolTipText: null,
            },
            {
                childCount: 0,
                id: 'checkout.customer.deleted',
                name: 'Deleted',
                parentId: 'checkout.customer',
                disabled: false,
                disabledToolTipText: null,
            },
            {
                childCount: 0,
                id: 'checkout.disabledElement',
                name: 'Disabled Element',
                parentId: 'checkout',
                disabled: true,
                disabledToolTipText: 'sw-flow.detail.trigger.textHint',
            },
        ]);
    });

    it('should emit an event when clicking tree item', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');
        await flushPromises();

        const treeItem = wrapper.find('.tree-items .sw-tree-item:first-child .sw-tree-item__toggle');
        await treeItem.trigger('click');
        await flushPromises();

        await wrapper.find('.sw-tree-item__children .sw-tree-item:first-child .sw-tree-item__toggle').trigger('click');
        await flushPromises();

        const transitionStub = wrapper
            .find('transition-stub')
            .find('.sw-tree-item__children transition-stub .sw-tree-item:last-child');

        await transitionStub.find('.sw-tree-item__content .tree-link').trigger('click');

        const emittedEvent = wrapper.emitted()['option-select'];
        expect(emittedEvent).toBeTruthy();
        expect(emittedEvent[0]).toEqual(['checkout.customer.deleted']);
    });

    it('should scroll to the selected element', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        document.body.appendChild(wrapper.element);

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');
        await flushPromises();

        const treeItemToggle = wrapper.find('.sw-tree-item__toggle');
        await treeItemToggle.trigger('click');
        await flushPromises();

        expect(window.HTMLElement.prototype.scrollIntoView).toHaveBeenCalledWith({
            behavior: 'smooth',
            block: 'center',
        });
    });

    it('should not close event tree when clicking on a disabled element', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        document.body.appendChild(wrapper.element);

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');
        await flushPromises();

        expect(wrapper.find('.sw-tree').exists()).toBeTruthy();

        const treeItemToggle = wrapper.find('.sw-tree-item__toggle');
        await treeItemToggle.trigger('click');
        await flushPromises();

        const disabledElement = wrapper.find('.sw-tree-item.is--no-children.is--disabled');
        await disabledElement.trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-tree').exists()).toBeTruthy();
    });

    it('should close event tree when clicking on a content element', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        document.body.appendChild(wrapper.element);

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');
        await flushPromises();

        expect(wrapper.find('.sw-tree').exists()).toBeTruthy();

        let treeItemToggle = wrapper.find('.sw-tree-item__toggle');
        await treeItemToggle.trigger('click');
        await flushPromises();

        treeItemToggle = wrapper.find('.sw-tree-item__children .sw-tree-item__toggle');
        await treeItemToggle.trigger('click');
        await flushPromises();

        const contentElement = wrapper.find('.sw-tree-item .is--no-children .sw-tree-item__content');
        await contentElement.trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-tree').exists()).toBeFalsy();
    });

    it('should not close event tree when clicking on an svg outside of sw-flow-trigger', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        document.body.appendChild(document.createElement('svg'));

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');
        await flushPromises();

        expect(wrapper.find('.sw-tree').exists()).toBeTruthy();

        const svgElement = document.body.querySelector('svg');
        svgElement.dispatchEvent(new Event('click', { bubbles: true }));
        await flushPromises();

        expect(wrapper.find('.sw-tree').exists()).toBeTruthy();
    });

    it('should close event tree when clicking on an element (besides svg) outside of sw-flow-trigger', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        document.body.appendChild(document.createElement('img'));

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');
        await flushPromises();

        expect(wrapper.find('.sw-tree').exists()).toBeTruthy();

        const someElement = document.body.querySelector('img');
        someElement.dispatchEvent(new Event('click', { bubbles: true }));
        await flushPromises();

        expect(wrapper.find('.sw-tree').exists()).toBeFalsy();
    });

    it('should clear search input and close search results when clicking on an element (besides svg) outside of sw-flow-trigger', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        document.body.appendChild(document.createElement('img'));

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');
        await flushPromises();

        expect(wrapper.find('.sw-tree').exists()).toBeTruthy();

        await searchField.setValue('payment');
        await searchField.trigger('input');
        await flushPromises();

        expect(wrapper.find('.sw-flow-trigger__search-results').exists()).toBeTruthy();

        const someElement = document.body.querySelector('img');
        someElement.dispatchEvent(new Event('click', { bubbles: true }));
        await flushPromises();

        expect(wrapper.find('.sw-tree').exists()).toBeFalsy();
        expect(wrapper.find('.sw-flow-trigger__search-results').exists()).toBeFalsy();
        expect(wrapper.vm.searchTerm).toBe(wrapper.vm.eventName);
    });

    it('should show search list when user type search term in search field', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');
        await flushPromises();

        let eventTree = wrapper.find('.sw-tree');
        expect(eventTree.exists()).toBeTruthy();

        await searchField.setValue('payment');
        await searchField.trigger('input');
        await flushPromises();

        eventTree = wrapper.find('.sw-tree');
        expect(eventTree.exists()).toBeFalsy();

        const searchResults = wrapper.find('.sw-flow-trigger__search-results');
        expect(searchResults.exists()).toBeTruthy();
    });

    it('should show search result correctly', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');
        await flushPromises();

        await searchField.setValue('payment');
        await searchField.trigger('input');
        await flushPromises();

        let searchResults = wrapper.findAll('.sw-flow-trigger__search-result');
        expect(searchResults).toHaveLength(1);

        let result = wrapper.find('sw-highlight-text-stub');
        expect(result.attributes().text).toBe('Checkout / Customer / Changed payment method');

        await searchField.setValue('deleted');
        await searchField.trigger('input');
        await flushPromises();

        searchResults = wrapper.findAll('.sw-flow-trigger__search-result');
        expect(searchResults).toHaveLength(1);

        result = wrapper.find('sw-highlight-text-stub');
        expect(result.attributes().text).toBe('Checkout / Customer / Deleted');
    });

    it('should emit an event when clicking on search item', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');
        await flushPromises();

        await searchField.setValue('payment');
        await searchField.trigger('input');
        await flushPromises();

        const searchResult = wrapper.find('.sw-flow-trigger__search-result');
        await searchResult.trigger('click');
        await flushPromises();

        const emittedEvent = wrapper.emitted()['option-select'];
        expect(emittedEvent).toBeTruthy();
        expect(emittedEvent[0]).toEqual([
            'checkout.customer.changed-payment-method',
        ]);
    });

    it('should be able to navigate search results with arrow keys', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');
        await flushPromises();

        await searchField.setValue('check');
        await searchField.trigger('input');
        await flushPromises();

        expect(wrapper.findAll('.sw-flow-trigger__search-result')[0].classes('is--focus')).toBeTruthy();

        window.document.dispatchEvent(
            new KeyboardEvent('keydown', {
                key: 'Arrowdown',
            }),
        );
        await flushPromises();

        expect(wrapper.findAll('.sw-flow-trigger__search-result')[0].classes('is--focus')).toBeFalsy();
        expect(wrapper.findAll('.sw-flow-trigger__search-result')[1].classes('is--focus')).toBeTruthy();

        window.document.dispatchEvent(
            new KeyboardEvent('keydown', {
                key: 'Arrowleft',
            }),
        );
        await flushPromises();

        expect(wrapper.findAll('.sw-flow-trigger__search-result')[0].classes('is--focus')).toBeFalsy();
        expect(wrapper.findAll('.sw-flow-trigger__search-result')[1].classes('is--focus')).toBeTruthy();

        window.document.dispatchEvent(
            new KeyboardEvent('keydown', {
                key: 'Arrowright',
            }),
        );
        await flushPromises();

        expect(wrapper.findAll('.sw-flow-trigger__search-result')[0].classes('is--focus')).toBeFalsy();
        expect(wrapper.findAll('.sw-flow-trigger__search-result')[1].classes('is--focus')).toBeTruthy();

        window.document.dispatchEvent(
            new KeyboardEvent('keydown', {
                key: 'Arrowdown',
            }),
        );
        await flushPromises();

        expect(wrapper.findAll('.sw-flow-trigger__search-result')[1].classes('is--focus')).toBeFalsy();
        expect(wrapper.findAll('.sw-flow-trigger__search-result')[2].classes('is--focus')).toBeTruthy();

        window.document.dispatchEvent(
            new KeyboardEvent('keydown', {
                key: 'Arrowup',
            }),
        );
        await flushPromises();

        expect(wrapper.findAll('.sw-flow-trigger__search-result')[2].classes('is--focus')).toBeFalsy();
        expect(wrapper.findAll('.sw-flow-trigger__search-result')[1].classes('is--focus')).toBeTruthy();
    });

    it('should be able to close the event selection by tab key', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        // focus trigger input to open event selection
        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');
        await flushPromises();

        // Selection is expanded
        let eventSelection = wrapper.find('.sw-flow-trigger__event-selection');
        expect(eventSelection.exists()).toBeTruthy();

        // Press tab button to close the tree
        window.document.dispatchEvent(
            new KeyboardEvent('keydown', {
                key: 'Tab',
            }),
        );

        await flushPromises();

        // Selection is collapsed
        eventSelection = wrapper.find('.sw-flow-trigger__event-selection');
        expect(eventSelection.exists()).toBeFalsy();
    });

    it('should be able to close the event selection by escape key', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');
        await flushPromises();

        let eventSelection = wrapper.find('.sw-flow-trigger__event-selection');
        expect(eventSelection.exists()).toBeTruthy();

        // Press escape button to close the tree
        window.document.dispatchEvent(
            new KeyboardEvent('keydown', {
                key: 'Escape',
            }),
        );

        await flushPromises();

        // Selection is collapsed
        eventSelection = wrapper.find('.sw-flow-trigger__event-selection');
        expect(eventSelection.exists()).toBeFalsy();
    });

    it('should able to interact tree by arrow key', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        // focus trigger input to open event selection
        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');
        await flushPromises();

        // Selection is expanded
        let treeItems = wrapper.findAll('.sw-tree-item');
        expect(treeItems).toHaveLength(1);
        expect(treeItems.at(0).classes()).toContain('is--focus');
        expect(treeItems.at(0).text()).toBe('Checkout');

        // Press arrow right to open checkout tree
        window.document.dispatchEvent(
            new KeyboardEvent('keydown', {
                key: 'Arrowright',
            }),
        );

        await flushPromises();
        treeItems = wrapper.findAll('.sw-tree-item');
        expect(treeItems).toHaveLength(3);

        // Move down to customer item
        window.document.dispatchEvent(
            new KeyboardEvent('keydown', {
                key: 'Arrowdown',
            }),
        );

        await flushPromises();

        expect(treeItems.at(0).classes()).not.toContain('is--focus');
        expect(treeItems.at(1).classes()).toContain('is--focus');

        // open customer tree
        window.document.dispatchEvent(
            new KeyboardEvent('keydown', {
                key: 'Arrowright',
            }),
        );

        await flushPromises();

        treeItems = wrapper.findAll('.sw-tree-item');
        expect(treeItems).toHaveLength(6);
        expect(treeItems.at(2).text()).toBe('Before');
        expect(treeItems.at(3).text()).toBe('Changed payment method');
        expect(treeItems.at(4).text()).toBe('Deleted');
        expect(treeItems.at(5).text()).toBe('Disabled Element');

        // close customer tree
        window.document.dispatchEvent(
            new KeyboardEvent('keydown', {
                key: 'Arrowleft',
            }),
        );

        await flushPromises();

        treeItems = wrapper.findAll('.sw-tree-item');
        expect(treeItems).toHaveLength(3);

        // Move up to checkout item
        window.document.dispatchEvent(
            new KeyboardEvent('keydown', {
                key: 'Arrowup',
            }),
        );

        await flushPromises();

        treeItems = wrapper.findAll('.sw-tree-item');
        expect(treeItems.at(1).classes()).not.toContain('is--focus');
        expect(treeItems.at(0).classes()).toContain('is--focus');

        // close checkout tree
        window.document.dispatchEvent(
            new KeyboardEvent('keydown', {
                key: 'Arrowleft',
            }),
        );

        await flushPromises();

        treeItems = wrapper.findAll('.sw-tree-item');
        expect(treeItems).toHaveLength(1);
    });

    it('should able to emit an event when pressing Enter on the item which has no children', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        // focus trigger input to open event selection
        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');

        let treeItems = wrapper.findAll('.sw-tree-item');

        // Press arrow right to open checkout tree
        window.document.dispatchEvent(
            new KeyboardEvent('keydown', {
                key: 'Arrowright',
            }),
        );

        await flushPromises();

        // Move down to customer item
        window.document.dispatchEvent(
            new KeyboardEvent('keydown', {
                key: 'Arrowdown',
            }),
        );

        // Press enter to select customer item
        window.document.dispatchEvent(
            new KeyboardEvent('keydown', {
                key: 'Enter',
            }),
        );

        await flushPromises();

        let emittedEvent = wrapper.emitted()['option-select'];
        expect(emittedEvent).toBeFalsy();

        let eventSelection = wrapper.find('.sw-flow-trigger__event-selection');
        expect(eventSelection.exists()).toBeTruthy();

        // open customer tree
        window.document.dispatchEvent(
            new KeyboardEvent('keydown', {
                key: 'Arrowright',
            }),
        );

        await flushPromises();

        treeItems = wrapper.findAll('.sw-tree-item');
        expect(treeItems).toHaveLength(6);
        expect(treeItems.at(2).text()).toBe('Before');
        expect(treeItems.at(3).text()).toBe('Changed payment method');
        expect(treeItems.at(4).text()).toBe('Deleted');
        expect(treeItems.at(5).text()).toBe('Disabled Element');

        // move down to changed payment method item
        window.document.dispatchEvent(
            new KeyboardEvent('keydown', {
                key: 'Arrowdown',
            }),
        );

        window.document.dispatchEvent(
            new KeyboardEvent('keydown', {
                key: 'Arrowdown',
            }),
        );

        await flushPromises();

        // changed payment method item is focused
        expect(treeItems.at(3).classes()).toContain('is--focus');

        window.document.dispatchEvent(
            new KeyboardEvent('keydown', {
                key: 'Enter',
            }),
        );

        await flushPromises();

        emittedEvent = wrapper.emitted()['option-select'];
        expect(emittedEvent).toBeTruthy();
        expect(emittedEvent[0]).toEqual([
            'checkout.customer.changed-payment-method',
        ]);

        eventSelection = wrapper.find('.sw-flow-trigger__event-selection');
        expect(eventSelection.exists()).toBeFalsy();
    });

    it('should emit an event when pressing Enter on search item', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');
        await flushPromises();

        await searchField.setValue('checkout');
        await searchField.trigger('input');
        await flushPromises();

        window.document.dispatchEvent(
            new KeyboardEvent('keydown', {
                key: 'Enter',
            }),
        );

        await flushPromises();

        const emittedEvent = wrapper.emitted()['option-select'];
        expect(emittedEvent).toBeTruthy();
        expect(emittedEvent[0]).toEqual(['checkout.customer.before.login']);
    });

    it('should show confirmation modal when clicking tree item', async () => {
        Shopware.Store.get('swFlow').setSequences(getSequencesCollection(sequencesFixture));
        const wrapper = await createWrapper();
        await flushPromises();

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');
        await flushPromises();

        const treeItem = wrapper.find('.tree-items .sw-tree-item:first-child .sw-tree-item__toggle');
        await treeItem.trigger('click');
        await flushPromises();

        await wrapper.find('.sw-tree-item__children .sw-tree-item:first-child .sw-tree-item__toggle').trigger('click');
        await flushPromises();

        const transitionStub = wrapper
            .find('transition-stub')
            .find('.sw-tree-item__children transition-stub .sw-tree-item:last-child');

        await transitionStub.find('.sw-tree-item__content .tree-link').trigger('click');
        await flushPromises();

        const isSequenceEmpty = Shopware.Store.get('swFlow').isSequenceEmpty;

        expect(isSequenceEmpty).toBe(false);
        expect(wrapper.find('.sw-flow-event-change-confirm-modal').exists()).toBeTruthy();
    });

    it('should show confirmation modal when pressing Enter on search item', async () => {
        Shopware.Store.get('swFlow').setSequences(getSequencesCollection(sequencesFixture));

        const wrapper = await createWrapper();
        await flushPromises();

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');
        await flushPromises();

        await searchField.setValue('checkout');
        await searchField.trigger('input');
        await flushPromises();

        window.document.dispatchEvent(
            new KeyboardEvent('keydown', {
                key: 'Enter',
            }),
        );

        await flushPromises();

        const isSequenceEmpty = Shopware.Store.get('swFlow').isSequenceEmpty;

        expect(isSequenceEmpty).toBe(false);
        expect(wrapper.find('.sw-flow-event-change-confirm-modal').exists()).toBeTruthy();
    });

    it('should show tool tip when trigger has only stop flow action', async () => {
        const wrapper = await createWrapper({ eventName: 'mail.send' });
        await flushPromises();

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');
        await flushPromises();

        const treeItem = wrapper.find('.tree-items .sw-tree-item:first-child .sw-tree-item__toggle');
        await treeItem.trigger('click');
        await flushPromises();

        const treeItemLink = await wrapper.find('.sw-tree-item__content .tree-link');
        await treeItemLink.trigger('click');
        await flushPromises();

        const treeItemContent = await wrapper.find('.sw-tree-item__content');

        expect(treeItemContent.attributes()['tooltip-mock-id']).toBeTruthy();

        const emittedEvent = wrapper.emitted()['option-select'];
        expect(emittedEvent).toBeFalsy();
    });

    it('should not translate if the snippet is not exists', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.getEventNameTranslated('send')).toBe('Send');
        expect(wrapper.vm.getEventNameTranslated('test_event_name')).toBe('test event name');
    });

    it('should get event tree data correctly with custom event', async () => {
        const wrapper = await createWrapper({});
        await flushPromises();

        mockBusinessEvents.push({
            name: 'swag.before.open.the_doors',
            mailAware: true,
            aware: ['Shopware\\Core\\Framework\\Event\\CustomEventAware'],
        });

        expect(wrapper.vm.eventTree).toEqual([
            {
                childCount: 2,
                id: 'checkout',
                name: 'Checkout',
                parentId: null,
                disabled: false,
                disabledToolTipText: null,
            },
            {
                childCount: 3,
                id: 'checkout.customer',
                name: 'Customer',
                parentId: 'checkout',
                disabled: false,
                disabledToolTipText: null,
            },
            {
                childCount: 1,
                id: 'checkout.customer.before',
                name: 'Before',
                parentId: 'checkout.customer',
                disabled: false,
                disabledToolTipText: null,
            },
            {
                childCount: 0,
                id: 'checkout.customer.before.login',
                name: 'Login',
                parentId: 'checkout.customer.before',
                disabled: false,
                disabledToolTipText: null,
            },
            {
                childCount: 0,
                id: 'checkout.customer.changed-payment-method',
                name: 'Changed payment method',
                parentId: 'checkout.customer',
                disabled: false,
                disabledToolTipText: null,
            },
            {
                childCount: 0,
                id: 'checkout.customer.deleted',
                name: 'Deleted',
                parentId: 'checkout.customer',
                disabled: false,
                disabledToolTipText: null,
            },
            {
                childCount: 0,
                disabled: true,
                disabledToolTipText: 'sw-flow.detail.trigger.textHint',
                id: 'checkout.disabledElement',
                name: 'Disabled Element',
                parentId: 'checkout',
            },
            {
                childCount: 1,
                disabled: false,
                disabledToolTipText: null,
                id: 'swag',
                name: 'swag',
                parentId: null,
            },
            {
                childCount: 1,
                disabled: false,
                disabledToolTipText: null,
                id: 'swag.before',
                name: 'Before',
                parentId: 'swag',
            },
            {
                childCount: 1,
                disabled: false,
                disabledToolTipText: null,
                id: 'swag.before.open',
                name: 'open',
                parentId: 'swag.before',
            },
            {
                childCount: 0,
                disabled: false,
                disabledToolTipText: null,
                id: 'swag.before.open.the_doors',
                name: 'the doors',
                parentId: 'swag.before.open',
            },
        ]);
    });
});
