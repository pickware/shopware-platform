import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import { createPinia, setActivePinia } from 'pinia';

/**
 * @sw-package checkout
 */

function getCollection(entity, collection) {
    return new EntityCollection(
        `/${entity}`,
        entity,
        null,
        { isShopwareContext: true },
        collection,
        collection.length,
        null,
    );
}

const orderFixture = {
    id: '1234',
    documents: [],
    taxStatus: 'gross',
    orderNumber: '10000',
    amountNet: 80,
    amountGross: 100,
    lineItems: [],
    deepLinkCode: 'abcdef',
};

const documentFixture = {
    orderId: 'order1',
    sent: true,
    documentMediaFileId: null,
    documentType: {
        id: '1',
        name: 'Invoice',
        technicalName: 'invoice',
    },
    config: {
        documentNumber: '1000',
    },
    id: 'document1',
    deepLinkCode: 'abcd',
    documentMediaFile: {
        id: '1234',
        fileExtension: 'pdf',
    },
    documentA11yMediaFile: {
        id: '12345',
        fileExtension: 'html',
    },
};

const documentTypeFixture = [
    {
        id: '0',
        name: 'Delivery note',
        technicalName: 'delivery_note',
        translated: {
            name: 'Delivery note',
        },
    },
    {
        id: '1',
        name: 'Invoice',
        technicalName: 'invoice',
        translated: {
            name: 'Invoice',
        },
    },
    {
        id: '2',
        name: 'Cancellation invoice',
        technicalName: 'storno',
        translated: {
            name: 'Cancellation invoice',
        },
    },
    {
        id: '3',
        name: 'Credit note',
        technicalName: 'credit_note',
        translated: {
            name: 'Credit note',
        },
    },
];

async function createWrapper() {
    const wrapper = mount(await wrapTestComponent('sw-order-document-card', { sync: true }), {
        global: {
            stubs: {
                'sw-empty-state': {
                    template: '<div class="sw-empty-state"><slot name="icon"></slot><slot name="actions"></slot></div>',
                },
                'sw-card-section': {
                    template: '<div class="sw-card-section"><slot></slot></div>',
                },
                'sw-card-filter': {
                    template: '<div class="sw-card-filter"><slot name="filter"></slot></div>',
                },
                'sw-modal': {
                    template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>',
                },
                'sw-container': {
                    template: '<div class="sw-container"><slot></slot></div>',
                },
                'sw-text-field': true,
                'sw-context-button': {
                    template: '<div class="sw-context-button"><slot></slot></div>',
                },
                'sw-order-select-document-type-modal': await wrapTestComponent('sw-order-select-document-type-modal', {
                    sync: true,
                }),
                'sw-order-send-document-modal': true,
                'sw-order-document-settings-modal': await wrapTestComponent('sw-order-document-settings-modal', {
                    sync: true,
                }),
                'sw-order-document-settings-delivery-note-modal': true,
                // eslint-disable-next-line max-len
                'sw-order-document-settings-invoice-modal': await wrapTestComponent(
                    'sw-order-document-settings-invoice-modal',
                    { sync: true },
                ),
                'sw-order-document-settings-credit-note-modal': true,
                'sw-order-document-settings-storno-modal': true,
                'sw-data-grid': await wrapTestComponent('sw-data-grid', {
                    sync: true,
                }),
                'sw-data-grid-column-boolean': {
                    props: ['value'],
                    template: '<div class="sw-data-grid-column-boolean"><slot></slot></div>',
                },
                'sw-context-menu-item': {
                    emits: ['click'],
                    template: `
                        <div class="sw-context-menu-item" @click="$emit('click', $event.target.value)">
                            <slot></slot>
                        </div>`,
                },
                'sw-radio-field': true,
                'sw-datepicker': true,
                'sw-textarea-field': true,

                'sw-button-group': await wrapTestComponent('sw-button-group', { sync: true }),
                'sw-loader': true,
                'sw-extension-component-section': true,
                'sw-ai-copilot-badge': true,
                'router-link': true,
                'sw-checkbox-field': true,
                'sw-data-grid-settings': true,
                'sw-data-grid-inline-edit': true,
                'sw-data-grid-skeleton': true,
                'sw-upload-listener': true,
                'sw-media-upload-v2': true,
                'sw-media-modal-v2': true,
                'sw-provide': { template: '<slot/>', inheritAttrs: false },
            },
            provide: {
                documentService: {
                    setListener: () => ({}),
                    getDocument: () =>
                        Promise.resolve({
                            headers: {
                                'content-disposition': 'attachment; filename=dummny.pdf',
                            },
                            data: 'https://shopware.test/dummny.pdf',
                        }),
                    createDocument: () =>
                        Promise.resolve({
                            data: {
                                documentId: '1234',
                                documentDeepLink: '12341234',
                            },
                        }),
                },
                numberRangeService: {
                    reserve: () => Promise.resolve({ number: 1000 }),
                },
                repositoryFactory: {
                    create: (entity) => ({
                        search: () => {
                            if (entity === 'document_type' || entity === 'document') {
                                return Promise.resolve(getCollection('document_type', documentTypeFixture));
                            }

                            return Promise.resolve([]);
                        },
                        get: () => {
                            if (entity === 'document') {
                                return Promise.resolve(documentTypeFixture);
                            }

                            return Promise.resolve({});
                        },
                        save: () => Promise.resolve({}),
                        searchIds: () => Promise.resolve([]),
                    }),
                },
                searchRankingService: {},
            },
            mocks: {
                $route: {
                    query: '',
                    name: 'sw.order.detail.documents',
                },
            },
            directives: {
                tooltip: {
                    beforeMount(el, binding) {
                        el.setAttribute('tooltip-message', binding.value.message);
                    },
                    mounted(el, binding) {
                        el.setAttribute('tooltip-message', binding.value.message);
                    },
                    updated(el, binding) {
                        el.setAttribute('tooltip-message', binding.value.message);
                    },
                },
            },
        },
        props: {
            order: orderFixture,
            isLoading: false,
        },
    });
    await flushPromises();
    return wrapper;
}

describe('src/module/sw-order/component/sw-order-document-card', () => {
    let wrapper;

    beforeAll(() => {
        global.allowedErrors.push({
            method: 'warn',
            msgCheck: (msg) => {
                if (typeof msg !== 'string') {
                    return false;
                }

                return msg.includes('[sw-data-grid] Can not resolve accessor');
            },
        });

        setActivePinia(createPinia());
    });

    it('should be a Vue.js component', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have an disabled create new button', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        const createNewButton = wrapper.findComponent('.sw-order-document-grid-button');
        expect(createNewButton.attributes('disabled')).toBeDefined();
    });

    it('should not have an disabled create new button', async () => {
        global.activeAclRoles = [
            'order.editor',
            'document.viewer',
        ];
        wrapper = await createWrapper();
        const createNewButton = wrapper.find('.sw-order-document-grid-button');

        expect(createNewButton.attributes().disabled).toBeUndefined();
    });

    it('should show the error of invoice number is existing', async () => {
        global.activeAclRoles = [];

        wrapper = await createWrapper();
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.convertStoreEventToVueEvent({
            action: 'create-document-fail',
            payload: {
                code: 'DOCUMENT__NUMBER_ALREADY_EXISTS',
                detail: 'error message',
                meta: {
                    parameters: [],
                },
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-order.documentCard.error.DOCUMENT__NUMBER_ALREADY_EXISTS',
        });

        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should show the error of credit note number is existing', async () => {
        global.activeAclRoles = [];

        wrapper = await createWrapper();
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.convertStoreEventToVueEvent({
            action: 'create-document-fail',
            payload: {
                code: 'DOCUMENT__NUMBER_ALREADY_EXISTS',
                detail: 'error message',
                meta: {
                    parameters: [],
                },
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-order.documentCard.error.DOCUMENT__NUMBER_ALREADY_EXISTS',
        });

        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should show the error of delivery note number is existing', async () => {
        global.activeAclRoles = [];

        wrapper = await createWrapper();
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.convertStoreEventToVueEvent({
            action: 'create-document-fail',
            payload: {
                code: 'DOCUMENT__NUMBER_ALREADY_EXISTS',
                detail: 'error message',
                meta: {
                    parameters: [],
                },
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-order.documentCard.error.DOCUMENT__NUMBER_ALREADY_EXISTS',
        });

        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should show the error of cancellation invoice number is existing', async () => {
        global.activeAclRoles = [];

        wrapper = await createWrapper();
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.convertStoreEventToVueEvent({
            action: 'create-document-fail',
            payload: {
                code: 'DOCUMENT__NUMBER_ALREADY_EXISTS',
                detail: 'error message',
                meta: {
                    parameters: [],
                },
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-order.documentCard.error.DOCUMENT__NUMBER_ALREADY_EXISTS',
        });

        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should save document when the event return finished', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.convertStoreEventToVueEvent({
            action: 'create-document-finished',
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showModal).toBeFalsy();

        // Wait 3 ticks for parent component to update
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('document-save')).toBeTruthy();
    });

    it('should show Select document type modal when click on Create new button', async () => {
        global.activeAclRoles = [
            'order.editor',
            'document.viewer',
        ];
        wrapper = await createWrapper();

        const createNewButton = wrapper.find('.sw-order-document-grid-button');
        await createNewButton.trigger('click');

        const documentTypeSelectModal = wrapper.find('.sw-order-select-document-type-modal');
        expect(documentTypeSelectModal.exists()).toBeTruthy();
    });

    it('should show modal regarding to current document type', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();

        await wrapper.setData({
            currentDocumentType: {
                id: '0',
                name: 'Delivery note',
                technicalName: 'delivery_note',
                translated: {
                    name: 'Delivery note',
                },
            },
            showModal: true,
        });

        expect(wrapper.find('sw-order-document-settings-delivery-note-modal-stub').exists()).toBeTruthy();

        await wrapper.setData({
            currentDocumentType: {
                id: '1',
                name: 'Invoice',
                technicalName: 'invoice',
                translated: {
                    name: 'Invoice',
                },
            },
        });

        expect(wrapper.find('.sw-modal[title="sw-order.documentModal.modalTitle - Invoice"]').exists()).toBeTruthy();

        await wrapper.setData({
            currentDocumentType: {
                id: '2',
                name: 'Cancellation invoice',
                technicalName: 'storno',
                translated: {
                    name: 'Cancellation invoice',
                },
            },
        });

        expect(wrapper.find('sw-order-document-settings-storno-modal-stub').exists()).toBeTruthy();

        await wrapper.setData({
            currentDocumentType: {
                id: '3',
                name: 'Credit note',
                technicalName: 'credit_note',
                translated: {
                    name: 'Credit note',
                },
            },
        });

        expect(wrapper.find('sw-order-document-settings-credit-note-modal-stub').exists()).toBeTruthy();
    });

    it('should show Send document modal when click on Send document option', async () => {
        global.activeAclRoles = ['order.editor'];
        wrapper = await createWrapper();

        await wrapper.setData({
            documents: getCollection('document', [
                documentFixture,
            ]),
        });
        expect(wrapper.find('.sw-data-grid').exists()).toBeTruthy();

        const sendDocumentButton = wrapper.find('.sw-order-document-card__context-button-send');
        await sendDocumentButton.trigger('click');

        const sendDocumentModal = wrapper.find('sw-order-send-document-modal-stub');
        expect(sendDocumentModal.exists()).toBeTruthy();
        expect(wrapper.vm.sendDocument).toEqual(documentFixture);
    });

    it('should show attach column when attachView is true', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();

        await wrapper.setData({
            documents: getCollection('document', [
                documentFixture,
            ]),
        });

        let columns = wrapper.findAll('.sw-data-grid__cell--header');
        // 5 data columns + 1 action column
        expect(columns).toHaveLength(6);

        await wrapper.setProps({
            attachView: true,
        });

        columns = wrapper.findAll('.sw-data-grid__cell--header');
        expect(columns).toHaveLength(6);
        expect(columns[5].text()).toBe('sw-order.documentCard.labelAttach');
    });

    it('should show card filter when order has document', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        expect(wrapper.find('.sw-card-filter').exists()).toBeFalsy();

        await wrapper.setProps({
            order: {
                documents: getCollection('document', [
                    documentFixture,
                ]),
            },
        });

        await wrapper.setData({
            documents: getCollection('document', [
                documentFixture,
            ]),
        });

        expect(wrapper.find('.sw-card-filter').exists()).toBeTruthy();
    });

    it('should change sent status when click on "Mark as unsent" context menu', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();

        await wrapper.setData({
            documents: getCollection('document', [
                documentFixture,
            ]),
        });

        expect(wrapper.findComponent('.sw-data-grid-column-boolean').props('value')).toBeTruthy();

        // Mark as sent option is disabled
        const markSentButton = wrapper.find('.sw-order-document-card__context-button-mark-sent');
        expect(markSentButton.attributes('disabled')).toBe('true');

        // Mark as unsent
        const markUnsentButton = wrapper.find('.sw-order-document-card__context-button-mark-unsent');
        await markUnsentButton.trigger('click');

        expect(wrapper.findComponent('.sw-data-grid-column-boolean').props('value')).toBeFalsy();
        expect(markUnsentButton.attributes('disabled')).toBe('true');
    });

    it('should change sent status when click on "Mark as sent" context menu', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();

        await wrapper.setData({
            documents: getCollection('document', [
                {
                    ...documentFixture,
                    sent: false,
                },
            ]),
        });

        const spyMarkDocumentAsSent = jest.spyOn(wrapper.vm, 'markDocumentAsSent');

        expect(wrapper.findComponent('.sw-data-grid-column-boolean').props('value')).toBeFalsy();

        // Mark as unsent option is disabled
        const markUnsentButton = wrapper.find('.sw-order-document-card__context-button-mark-unsent');
        expect(markUnsentButton.attributes('disabled')).toBe('true');

        // Mark as unsent
        const markSentButton = wrapper.find('.sw-order-document-card__context-button-mark-sent');
        await markSentButton.trigger('click');

        expect(spyMarkDocumentAsSent).toHaveBeenCalledTimes(1);
        expect(markSentButton.attributes('disabled')).toBe('true');
    });

    it('should show Send mail modal when choosing option Create and send in Create document modal', async () => {
        global.activeAclRoles = ['order.editor'];
        wrapper = await createWrapper();

        await wrapper.setData({
            currentDocumentType: {
                id: '1',
                name: 'Invoice',
                technicalName: 'invoice',
                translated: {
                    name: 'Invoice',
                },
            },
            showModal: true,
        });

        expect(wrapper.find('.sw-modal[title="sw-order.documentModal.modalTitle - Invoice"]').exists()).toBeTruthy();
        await wrapper.find('.sw-order-document-settings-modal__send-button').trigger('click');
        await flushPromises();

        expect(wrapper.find('sw-order-send-document-modal-stub').exists()).toBeTruthy();
    });

    it('should call downloadDocument method when choosing option Create and download in Create document modal', async () => {
        global.activeAclRoles = ['order.editor'];
        wrapper = await createWrapper();

        wrapper.vm.downloadDocument = jest.fn();

        await wrapper.setData({
            currentDocumentType: {
                id: '1',
                name: 'Invoice',
                technicalName: 'invoice',
                translated: {
                    name: 'Invoice',
                },
            },
            showModal: true,
        });

        expect(wrapper.find('.sw-modal[title="sw-order.documentModal.modalTitle - Invoice"]').exists()).toBeTruthy();
        await wrapper.find('.sw-order-document-settings-modal__download-button').trigger('click');
        await flushPromises();

        expect(wrapper.vm.downloadDocument).toHaveBeenCalled();
        wrapper.vm.downloadDocument.mockRestore();
    });

    it('should show permission tooltip message on Create document button correctly', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();

        const buttonCreate = wrapper.find('.sw-order-document-grid-button');
        expect(buttonCreate.attributes('tooltip-message')).toBe('sw-privileges.tooltip.warning');
        expect(buttonCreate.attributes('disabled')).toBeDefined();
    });

    it('should show order unsaved tooltip message on Create document button correctly', async () => {
        global.activeAclRoles = [
            'order.editor',
            'document.viewer',
        ];
        wrapper = await createWrapper();

        Shopware.Store.get('swOrderDetail').editing = true;
        await wrapper.vm.$nextTick();

        const buttonCreate = wrapper.findComponent('.sw-order-document-grid-button');
        expect(buttonCreate.attributes()['tooltip-message']).toBe('sw-order.documentTab.tooltipSaveBeforeCreateDocument');
        expect(buttonCreate.attributes('disabled')).toBeDefined();
    });

    it('should search documents with criteria queries', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();

        expect(wrapper.vm.documentCriteria.term).toBeNull();
        expect(wrapper.vm.documentCriteria.queries).toEqual([]);

        await wrapper.setData({
            term: '1000',
        });

        expect(wrapper.vm.documentCriteria.term).toBe('1000');
        expect(wrapper.vm.documentCriteria.queries).toEqual([
            {
                score: 500,
                query: {
                    type: 'contains',
                    field: 'config.documentDate',
                    value: '1000',
                },
            },
            {
                score: 500,
                query: {
                    type: 'equals',
                    field: 'config.documentNumber',
                    value: '1000',
                },
            },
        ]);
    });

    it('should render the only pdf on available formats column', async () => {
        wrapper = await createWrapper();

        await wrapper.setData({
            documents: getCollection('document', [
                { ...documentFixture, documentMediaFile: { fileExtension: 'pdf' }, documentA11yMediaFile: null },
            ]),
        });

        await flushPromises();

        const row = wrapper.find('.sw-data-grid__row--0');
        const fileTypes = row.find('.sw-data-grid__cell--fileTypes');

        expect(fileTypes.text()).toBe('PDF');
    });

    it('should render html and pdf on available formats column', async () => {
        wrapper = await createWrapper();

        await wrapper.setData({
            documents: getCollection('document', [
                {
                    ...documentFixture,
                    documentMediaFile: {
                        fileExtension: 'pdf',
                    },
                    documentA11yMediaFile: {
                        fileExtension: 'html',
                    },
                },
            ]),
        });

        await flushPromises();

        const row = wrapper.find('.sw-data-grid__row--0');
        const fileTypes = row.find('.sw-data-grid__cell--fileTypes');

        expect(fileTypes.text()).toBe('PDF, HTML');
    });
});
