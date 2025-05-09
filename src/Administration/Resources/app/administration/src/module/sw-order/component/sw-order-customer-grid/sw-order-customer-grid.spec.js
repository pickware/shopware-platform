import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

let customerData = [];

function setCustomerData(customers) {
    customerData = [...customers];
    customerData.total = customers.length;
    customerData.criteria = {
        page: 1,
        limit: 5,
    };
}

const customers = generateCustomers();

const contextState = {
    id: 'context',
    state: () => ({
        api: {
            languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
            systemLanguageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
        },
    }),
    actions: {
        resetLanguageToDefault: jest.fn(),
        setApiLanguageId: jest.fn(function (newLanguageId) {
            this.api.languageId = newLanguageId;
        }),
    },
    getters: {
        isSystemDefaultLanguage: () => false,
    },
};

function generateCustomers() {
    const items = [];

    for (let i = 1; i <= 10; i += 1) {
        items.push({
            id: i,
            firstName: `Quynh ${i}`,
            lastName: 'Nguyen',
            email: `quynh${i}@example.com`,
            salesChannelId: '1234',
            customerNumber: `sw${i}`,
            salesChannel: {
                translated: {
                    name: 'Storefront',
                },
            },
            boundSalesChannel: {
                translated: {
                    name: 'Storefront',
                },
            },
            boundSalesChannelId: '1234',
        });
    }

    return items;
}

async function createWrapper() {
    return mount(await wrapTestComponent('sw-order-customer-grid', { sync: true }), {
        global: {
            stubs: {
                'mt-card': {
                    template: `
                        <div class="sw-card__content">
                            <slot name="toolbar"></slot>
                            <slot name="grid"></slot>
                        </div>
                    `,
                },
                'mt-number-field': {
                    template: '<input class="mt-number-field" type="number" v-model="value" />',
                    props: {
                        value: 0,
                    },
                },
                'sw-checkbox-field': {
                    template: '<input class="sw-checkbox-field" type="checkbox" v-model="value" />',
                    props: {
                        value: false,
                    },
                },
                'sw-entity-listing': await wrapTestComponent('sw-entity-listing', { sync: true }),
                'sw-data-grid': await wrapTestComponent('sw-data-grid', {
                    sync: true,
                }),
                'sw-pagination': await wrapTestComponent('sw-pagination', {
                    sync: true,
                }),
                'sw-product-variant-info': true,
                'sw-data-grid-settings': true,
                'sw-data-grid-skeleton': true,
                'sw-context-button': {
                    template: '<div class="sw-context-button"><slot></slot></div>',
                },
                'sw-context-menu-item': true,
                'sw-empty-state': true,
                'sw-card-filter': {
                    data() {
                        return { term: '' };
                    },
                    template:
                        '<input class="sw-card-filter" :value="term" @input="$emit(\'sw-card-filter-term-change\', $event.target.value)">',
                },
                'sw-field': true,
                'router-link': true,
                'sw-order-new-customer-modal': true,
                'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select', { sync: true }),
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-select-selection-list': await wrapTestComponent('sw-select-selection-list'),
                'sw-select-result': {
                    props: [
                        'item',
                        'index',
                    ],
                    template: `
                        <li class="sw-select-result" @click.stop="onClickResult">
                            <slot></slot>
                        </li>`,
                    methods: {
                        onClickResult() {
                            this.$parent.$parent.$emit('item-select', this.item);
                        },
                    },
                },
                'sw-bulk-edit-modal': true,

                'sw-data-grid-inline-edit': true,
                'sw-data-grid-column-boolean': true,
                'sw-select-field': true,
                'sw-highlight-text': true,
                'sw-loader': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
                'sw-field-error': true,
                'sw-popover': {
                    template: '<div class="sw-popover"><slot></slot></div>',
                },
                'sw-provide': { template: '<slot/>', inheritAttrs: false },
            },
            provide: {
                searchRankingService: () => {},
                repositoryFactory: {
                    create: (entity) => ({
                        search: () => {
                            if (entity === 'sales_channel') {
                                return Promise.resolve([
                                    {
                                        id: '1234',
                                        name: 'Lazada',
                                    },
                                    {
                                        id: '123456',
                                        name: 'Tiki',
                                    },
                                ]);
                            }

                            return Promise.resolve(customerData);
                        },
                        get: () => Promise.resolve({ ...customers[0] }),
                        searchIds: () =>
                            Promise.resolve({
                                data: customers.map((customer) => customer.salesChannelId),
                            }),
                    }),
                },
            },
            mocks: {
                $tc: (key, value) => {
                    if (!value) {
                        return key;
                    }
                    return key + JSON.stringify(value);
                },
                $t: (key, value) => {
                    if (!value) {
                        return key;
                    }
                    return key + JSON.stringify(value);
                },
            },
        },
    });
}

describe('src/module/sw-order/view/sw-order-customer-grid', () => {
    beforeAll(() => {
        Shopware.Service().register('contextStoreService', () => {
            return {
                updateCustomerContext: () =>
                    Promise.resolve({
                        status: 200,
                    }),
                getSalesChannelContext: () =>
                    Promise.resolve({
                        data: {
                            salesChannelId: '1234',
                        },
                    }),
            };
        });

        Shopware.Service().register('cartStoreService', () => {
            return {
                getCart: () =>
                    Promise.resolve({
                        data: {
                            token: 'HE6KD7HOCC3TCS0AX903KCA6JHXCTXU2',
                            lineItems: [],
                        },
                    }),
                createCart: () =>
                    Promise.resolve({
                        data: {
                            token: 'HE6KD7HOCC3TCS0AX903KCA6JHXCTXU2',
                        },
                    }),
            };
        });

        Shopware.Store.get('swOrder').setCart({ token: '', lineItems: [] });
        Shopware.Store.get('swOrder').setContext({ customer: {} });

        if (Shopware.Store.get('context')) {
            Shopware.Store.unregister('context');
        }

        Shopware.Store.register(contextState);
    });

    it('should show empty state view when there is no customer', async () => {
        setCustomerData([]);

        const wrapper = await createWrapper();
        await flushPromises();

        const emptyState = wrapper.find('sw-empty-state-stub');
        expect(emptyState.exists()).toBeTruthy();
    });

    it('should show empty title correctly', async () => {
        setCustomerData([]);

        const wrapper = await createWrapper();
        await flushPromises();

        let emptyState = wrapper.find('sw-empty-state-stub');
        expect(emptyState.attributes('title')).toBe('sw-customer.list.messageEmpty');

        const searchField = wrapper.find('.sw-card-filter');

        await searchField.setValue('Hello World');
        await searchField.trigger('input');

        emptyState = wrapper.find('sw-empty-state-stub');
        expect(emptyState.attributes('title')).toBe(
            'sw-order.initialModal.customerGrid.textEmptySearch{"name":"Hello World"}',
        );
    });

    it('should show customer grid', async () => {
        setCustomerData(customers);

        const wrapper = await createWrapper();
        await flushPromises();

        const emptyState = wrapper.find('sw-empty-state-stub');
        expect(emptyState.exists()).toBeFalsy();

        const gridBody = wrapper.find('.sw-data-grid__body');
        expect(gridBody.findAll('.sw-data-grid__row')).toHaveLength(customers.length);
    });

    it('should able to search customer', async () => {
        setCustomerData(customers);

        const wrapper = await createWrapper();
        await flushPromises();

        let gridBody = wrapper.find('.sw-data-grid__body');
        expect(gridBody.findAll('.sw-data-grid__row')).toHaveLength(customers.length);

        setCustomerData([{ ...customers[1] }]);
        const searchField = wrapper.find('.sw-card-filter');

        await searchField.setValue('Quynh 2');
        await searchField.trigger('input');

        gridBody = wrapper.find('.sw-data-grid__body');
        expect(gridBody.findAll('.sw-data-grid__row')).toHaveLength(1);
    });

    it('should open add new customer modal', async () => {
        setCustomerData([]);

        const wrapper = await createWrapper();
        await flushPromises();

        const buttonAddCustomer = wrapper.find('.sw-order-customer-grid__add-customer');
        let modalAddCustomer = wrapper.find('sw-order-new-customer-modal-stub');

        expect(modalAddCustomer.exists()).toBeFalsy();

        await buttonAddCustomer.trigger('click');

        modalAddCustomer = wrapper.find('sw-order-new-customer-modal-stub');
        expect(modalAddCustomer.exists()).toBeTruthy();
    });

    it('should refresh grid list after adding new customer successfully', async () => {
        customers[0].boundSalesChannelId = customers[0].salesChannelId;

        setCustomerData([]);

        const wrapper = await createWrapper();
        await flushPromises();

        const spyGetList = jest.spyOn(wrapper.vm, 'getList');
        const buttonAddCustomer = wrapper.find('.sw-order-customer-grid__add-customer');
        await buttonAddCustomer.trigger('click');

        const modalAddCustomer = wrapper.findComponent('sw-order-new-customer-modal-stub');
        modalAddCustomer.vm.$emit('on-select-existing-customer', 'customer1');

        expect(spyGetList).toHaveBeenCalled();
    });

    it('should create cart after selecting a customer if there is no cart token', async () => {
        setCustomerData(customers);

        const wrapper = await createWrapper();
        await flushPromises();

        const spyCreateCart = jest.spyOn(wrapper.vm, 'createCart');

        const firstRow = wrapper.find('.sw-data-grid__body .sw-data-grid__row--0');
        await firstRow.find('.sw-field__radio-input input').setChecked(true);

        await flushPromises();

        expect(spyCreateCart).toHaveBeenCalled();
    });

    it('should update customer context and cart after selecting a customer', async () => {
        setCustomerData(customers);
        Shopware.Store.get('swOrder').setCartToken('1d8af3ddddbd378ba0065debd5e4e4b1');

        const wrapper = await createWrapper();

        wrapper.vm.customerRepository.get = jest.fn(() => Promise.resolve(customers[0]));
        const spyUpdateCustomerContext = jest.spyOn(wrapper.vm, 'updateCustomerContext');
        const spyGetCart = jest.spyOn(wrapper.vm, 'getCart');

        const firstRow = wrapper.find('.sw-data-grid__body .sw-data-grid__row--0');
        await firstRow.find('.sw-field__radio-input input').setChecked(true);
        await flushPromises();

        expect(spyUpdateCustomerContext).toHaveBeenCalled();

        await flushPromises();

        expect(spyGetCart).toHaveBeenCalled();
    });

    it('should check customer initially if customer exists', async () => {
        setCustomerData(customers);

        Shopware.Store.get('swOrder').setCustomer({
            ...customers[0],
        });

        const wrapper = await createWrapper();
        const spyOnSearchList = jest.spyOn(wrapper.vm, 'onSearch');

        const firstRow = wrapper.find('.sw-data-grid__body .sw-data-grid__row--0');
        const firstRowRadioField = firstRow.find('.sw-field__radio-input input');

        await flushPromises();

        expect(wrapper.find('.sw-card-filter').element.value).toBe('sw1');
        expect(spyOnSearchList).toHaveBeenCalledWith('sw1');

        expect(firstRowRadioField.element.checked).toBeTruthy();
    });

    it('should set the customer language id when customer has a language id', async () => {
        customers[0].salesChannel.languageId = '1234';
        setCustomerData(customers);

        const wrapper = await createWrapper();
        await flushPromises();

        const firstRow = wrapper.find('.sw-data-grid__body .sw-data-grid__row--0');
        await firstRow.find('.sw-field__radio-input input').setChecked(true);

        expect(Shopware.Store.get('context').api.languageId).toBe('1234');
    });

    it('should reset language to default if system language exists in customer sales channel languages', async () => {
        customers[0].salesChannel.languages = [
            {
                id: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
            },
        ];
        setCustomerData(customers);

        Shopware.Store.get('swOrder').setCartToken('HE6KD7HOCC3TCS0AX903KCA6JHXCTXU2');

        const resetLanguageToDefaultSpy = jest.spyOn(Shopware.Store.get('context'), 'resetLanguageToDefault');

        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.customerRepository.get = jest.fn(() => Promise.resolve(customers[0]));

        const firstRow = wrapper.find('.sw-data-grid__body .sw-data-grid__row--0');
        await firstRow.find('.sw-field__radio-input input').setChecked(true);

        await flushPromises();

        expect(resetLanguageToDefaultSpy).toHaveBeenCalled();
    });

    it('should set customer is null when close modal', async () => {
        customers[0].boundSalesChannelId = null;
        setCustomerData(customers);

        Shopware.Store.get('swOrder').setCartToken('HE6KD7HOCC3TCS0AX903KCA6JHXCTXU2');

        const wrapper = await createWrapper();
        await flushPromises();

        const firstRow = wrapper.find('.sw-data-grid__body .sw-data-grid__row--0');
        await firstRow.find('.sw-field__radio-input input').setChecked(true);

        await flushPromises();

        expect(wrapper.find('.sw-order-customer-grid__sales-channel-selection-modal').exists()).toBeTruthy();

        await wrapper.findByText('button', 'global.default.cancel').trigger('click');

        expect(wrapper.vm.customer).toBeNull();
        expect(wrapper.find('.sw-order-customer-grid__sales-channel-selection-modal').exists()).toBeFalsy();
    });

    it('should call handleSelectCustomer after sales channel selected', async () => {
        customers[0].boundSalesChannelId = null;
        setCustomerData(customers);

        Shopware.Store.get('swOrder').setCartToken('HE6KD7HOCC3TCS0AX903KCA6JHXCTXU2');

        const wrapper = await createWrapper();
        await flushPromises();

        const handleSelectCustomerSpy = jest.spyOn(wrapper.vm, 'handleSelectCustomer');

        const firstRow = wrapper.find('.sw-data-grid__body .sw-data-grid__row--0');
        await firstRow.find('.sw-field__radio-input input').setChecked(true);

        let select = wrapper.find('.sw-order-customer-grid__sales-channel-selection');
        await select.trigger('click');

        await flushPromises();

        select = wrapper.find('.sw-entity-single-select__selection');
        await select.trigger('click');

        await flushPromises();

        const entityItem = wrapper.findAll('.sw-select-result');
        await entityItem.at(0).trigger('click');
        await flushPromises();

        const buttonSelect = wrapper.findByText('button', 'sw-order.initialModal.customerGrid.buttonSelectSalesChannel');
        expect(buttonSelect.attributes('disabled')).toBeUndefined();

        await buttonSelect.trigger('click');

        expect(handleSelectCustomerSpy).toHaveBeenCalled();
    });

    it('should show sales channel select modal when customer sales channel is not in the allowed list and has no bound sales channel', async () => {
        customers[0].boundSalesChannelId = null;

        setCustomerData(customers);

        Shopware.Store.get('swOrder').setCartToken('HE6KD7HOCC3TCS0AX903KCA6JHXCTXU2');

        const wrapper = await createWrapper();
        await flushPromises();

        const firstRow = wrapper.find('.sw-data-grid__body .sw-data-grid__row--0');
        await firstRow.find('.sw-field__radio-input input').setChecked(true);

        await flushPromises();

        expect(wrapper.find('.sw-order-customer-grid__sales-channel-selection-modal').exists()).toBeTruthy();
    });

    it('should handle select customer when customer sales channel is in the allowed list and has a bound sales channel', async () => {
        customers[0].salesChannelId = '1234';
        customers[0].boundSalesChannelId = '1234';
        setCustomerData(customers);

        Shopware.Store.get('swOrder').setCartToken('HE6KD7HOCC3TCS0AX903KCA6JHXCTXU2');

        const wrapper = await createWrapper();
        await wrapper.setData({
            customerDraft: null,
        });

        const handleSelectCustomerSpy = jest.spyOn(wrapper.vm, 'handleSelectCustomer');

        await flushPromises();

        const firstRow = wrapper.find('.sw-data-grid__body .sw-data-grid__row--0');
        await firstRow.find('.sw-field__radio-input input').setChecked(true);

        expect(handleSelectCustomerSpy).toHaveBeenCalled();
    });

    it('should show customer changes modal when switching to a different customer whose sales channel is different from the current one', async () => {
        customers[0].boundSalesChannelId = '1234';
        setCustomerData(customers);

        Shopware.Store.get('swOrder').setCartToken('HE6KD7HOCC3TCS0AX903KCA6JHXCTXU2');

        const wrapper = await createWrapper();

        await wrapper.setData({
            customerDraft: {
                boundSalesChannelId: null,
                salesChannelId: '123456',
            },
        });

        await flushPromises();

        const firstRow = wrapper.find('.sw-data-grid__body .sw-data-grid__row--0');
        await firstRow.find('.sw-field__radio-input input').setChecked(true);

        await flushPromises();

        expect(wrapper.find('.sw-order-customer-grid__customer-changes-modal').exists()).toBeTruthy();

        const cancelButton = wrapper.findByText('button', 'global.default.cancel');
        expect(cancelButton.attributes('disabled')).toBeUndefined();
        await cancelButton.trigger('click');

        expect(wrapper.vm.customer).toEqual(wrapper.vm.customerDraft);
    });

    it('should handle select customer when select customer changes', async () => {
        customers[0].boundSalesChannelId = '1234';
        setCustomerData(customers);

        Shopware.Store.get('swOrder').setCartToken('HE6KD7HOCC3TCS0AX903KCA6JHXCTXU2');

        const wrapper = await createWrapper();

        await wrapper.setData({
            customerDraft: {
                boundSalesChannelId: null,
                salesChannelId: '123456',
            },
        });

        const handleSelectCustomerSpy = jest.spyOn(wrapper.vm, 'handleSelectCustomer');

        await flushPromises();

        const firstRow = wrapper.find('.sw-data-grid__body .sw-data-grid__row--0');
        await firstRow.find('.sw-field__radio-input input').setChecked(true);

        expect(wrapper.find('.sw-order-customer-grid__customer-changes-modal').exists()).toBeTruthy();

        const changeCustomerButton = wrapper.findByText('button', 'sw-order.initialModal.customerGrid.buttonChangeCustomer');
        expect(changeCustomerButton.attributes('disabled')).toBeUndefined();

        await changeCustomerButton.trigger('click');

        expect(handleSelectCustomerSpy).toHaveBeenCalled();
    });
});
