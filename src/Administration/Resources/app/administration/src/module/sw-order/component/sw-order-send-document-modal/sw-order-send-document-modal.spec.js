import { mount } from '@vue/test-utils';
import uuid from 'test/_helper_/uuid';
import EntityCollection from 'src/core/data/entity-collection.data';

/**
 * @sw-package checkout
 */

const mockOrderWithMailHeaderFooter = {
    orderCustomer: {
        email: 'test@shopware.com',
        firstName: 'Test',
        lastName: 'Tester',
    },
    salesChannel: {
        mailHeaderFooterId: uuid.get('headerFooter'),
    },
    salesChannelId: uuid.get('salesChannelId0'),
};

const mockOrderWithoutMailHeaderFooter = {
    orderCustomer: {
        email: 'test@shopware.com',
    },
    salesChannel: {
        mailHeaderFooterId: null,
    },
    salesChannelId: uuid.get('salesChannelId1'),
};

const mockDocuments = [
    {
        config: {
            documentNumber: '1000',
        },
        documentType: {
            name: 'Cancellation invoice',
            technicalName: 'storno',
        },
        documentNumber: 1000,
        createdAt: '2024-01-23T14:00:00.000+00:00',
        id: uuid.get('storno'),
        deepLinkCode: '12345',
        documentMediaFile: { id: '1235', fileExtension: 'pdf' },
        documentA11yMediaFile: { id: '123456', fileExtension: 'html' },
    },
    {
        config: {
            documentNumber: '1001',
        },
        documentType: {
            name: 'Credit note',
            technicalName: 'credit_note',
        },
        documentNumber: 1001,
        createdAt: '2024-01-23T14:00:00.000+00:00',
        id: uuid.get('credit_note'),
        documentMediaFile: { id: '1235', fileExtension: 'pdf' },
    },
    {
        config: {
            documentNumber: '1002',
        },
        documentType: {
            name: 'Invoice note',
            technicalName: 'invoice',
        },
        documentNumber: 1002,
        createdAt: '2024-01-23T14:00:00.000+00:00',
        id: uuid.get('invoice'),
        documentMediaFile: { id: '1235', fileExtension: 'pdf' },
    },
];

const mockUnknownDocument = {
    config: {
        documentNumber: '1003',
    },
    documentType: {
        name: 'Cancellation invoice',
        technicalName: 'unknown',
    },
    documentNumber: 1003,
    createdAt: '2024-01-23T14:00:00.000+00:00',
    id: uuid.get('unknown'),
};

const mockMailTemplates = [
    {
        id: uuid.get('cancellation_mail'),
        name: 'Test email 1',
        description: 'Test email description 1',
        mailTemplateType: {
            name: 'Cancellation invoice',
            technicalName: 'cancellation_mail',
        },
        contentHtml: '<div>Cancellation email template content.</div>\n',
        subject: 'Nex document for your order',
    },
    {
        id: uuid.get('delivery_mail'),
        name: 'Test email 2',
        description: 'Test email description 2',
        mailTemplateType: {
            name: 'Delivery note',
            technicalName: 'delivery_mail',
        },
        contentHtml: '<div>Delivery email template content.</div>\n',
        subject: 'Some other template subject',
    },
    {
        id: null,
        name: 'Test email 3',
        description: 'Test email description 3',
        mailTemplateType: {
            name: 'Invoice note',
            technicalName: 'invoice',
        },
        contentHtml: '<div>Delivery email template content.</div>\n',
        subject: 'And another template subject',
    },
    {
        id: uuid.get('personalized_order_mail'),
        name: 'Test email 4',
        description: 'Test email description 4',
        mailTemplateType: {
            name: 'Invoice note',
            technicalName: 'invoice_mail',
            templateData: {
                order: {
                    ...mockOrderWithoutMailHeaderFooter,
                    orderCustomer: {
                        email: 'personal@ema.il',
                        firstName: 'Personal',
                        lastName: 'Data',
                    },
                },
            },
        },
        contentHtml: '<div>{{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}}</div>\n',
        subject: 'Personal data from order',
    },
];

const mockMailHeaderFooter = {
    headerHtml: '<div>Header</div>\n',
    footerHtml: '<div>Footer</div>\n',
};

const mockRepositoryFactory = (entity, mailTemplates) => {
    if (entity === 'mail_template') {
        return {
            search: jest.fn(() =>
                Promise.resolve(new EntityCollection('', '', Shopware.Context.api, null, mailTemplates, 2)),
            ),
            get: jest.fn((value) => Promise.resolve(mailTemplates.filter((mailTemplate) => mailTemplate.id === value)[0])),
        };
    }
    if (entity === 'mail_header_footer') {
        return {
            search: (criteria) => {
                if (criteria.filters[0].value === null) {
                    return Promise.reject(new Error('mailHeaderFooterId should not be null in criteria filter!'));
                }
                return Promise.resolve(new EntityCollection('', '', Shopware.Context.api, null, [mockMailHeaderFooter], 1));
            },
        };
    }
    return {};
};

const defaultProps = {
    order: mockOrderWithMailHeaderFooter,
    document: mockDocuments[0],
};

const replaceTemplateVariables = (template = '', variables = {}) => {
    if (Object.keys(variables).length === 0) {
        return template;
    }
    return template.replace(/\{\{(.*?)}}/g, (match, p1) => {
        const keys = p1.trim().split('.');
        return keys.reduce((acc, key) => (acc && acc[key] !== undefined ? acc[key] : ''), variables);
    });
};

async function createWrapper(props = defaultProps, sendingSucceds = true, mailTemplates = mockMailTemplates) {
    return mount(await wrapTestComponent('sw-order-send-document-modal', { sync: true }), {
        global: {
            stubs: {
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select'),
                'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-select-result': await wrapTestComponent('sw-select-result'),
                'sw-description-list': await wrapTestComponent('sw-description-list'),
                'sw-container': {
                    template: '<div><slot></slot></div>',
                },
                'sw-text-field': true,
                'sw-product-variant-info': true,
                'router-link': true,
                'sw-loader': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
                'sw-field-error': true,
            },
            provide: {
                repositoryFactory: {
                    create(entity) {
                        return mockRepositoryFactory(entity, mailTemplates);
                    },
                },
                mailService: {
                    buildRenderPreview: (_, mailTemplate) =>
                        Promise.resolve(
                            replaceTemplateVariables(mailTemplate.contentHtml, mailTemplate?.mailTemplateType?.templateData),
                        ),
                    sendMailTemplate: jest.fn(sendingSucceds ? () => Promise.resolve() : () => Promise.reject()),
                },
            },
        },
        props,
    });
}

describe('src/module/sw-order/component/sw-order-send-document-modal', () => {
    it('should display the correct order and document information', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const descriptionListElements = wrapper.findAll('.sw-description-list > dd');
        expect(descriptionListElements[0].text()).toBe(String(mockDocuments[0].documentNumber));
        expect(descriptionListElements[1].text()).toBe(String(mockDocuments[0].documentType.name));
        expect(descriptionListElements[2].text()).toBe('23 January 2024 at 14:00');

        expect(wrapper.find('.sw-entity-single-select__selection-text').text()).toBe(
            mockMailTemplates[0].mailTemplateType.name,
        );

        const textFields = wrapper.findAllComponents('.mt-text-field');
        expect(textFields[0].props('modelValue')).toBe(String(mockOrderWithMailHeaderFooter.orderCustomer.email));
        expect(textFields[1].props('modelValue')).toBe(mockMailTemplates[0].subject);
    });

    it('should display mail template select', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const mailTemplateSelect = wrapper.find('.sw-order-send-document-modal__mail-template-select');
        expect(mailTemplateSelect.exists()).toBe(true);

        await mailTemplateSelect.find('.sw-entity-single-select__selection').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-select-result__result-item-description').text()).toBe(mockMailTemplates[0].description);
    });

    it('should truncate mail template description', async () => {
        const wrapper = await createWrapper(defaultProps, true, [
            {
                ...mockMailTemplates[0],
                description: 'swag'.repeat(50),
            },
        ]);
        await flushPromises();

        await wrapper.find('.sw-entity-single-select__selection').trigger('click');
        await flushPromises();

        const text = wrapper.find('.sw-select-result__result-item-description').text();
        expect(text).toHaveLength(160);
        expect(text.endsWith('...')).toBe(true);
    });

    it('should display the email content preview between a header and footer with existing mailHeaderFooterId', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const previewContent = wrapper.find('.sw-order-send-document-modal__email-content');
        expect(previewContent.element.innerHTML).toBe(
            mockMailHeaderFooter.headerHtml + mockMailTemplates[0].contentHtml + mockMailHeaderFooter.footerHtml,
        );
    });

    it('should not display the email content preview between a header and footer with missing mailHeaderFooterId', async () => {
        const wrapper = await createWrapper({
            ...defaultProps,
            order: mockOrderWithoutMailHeaderFooter,
        });
        await flushPromises();

        const previewContent = wrapper.find('.sw-order-send-document-modal__email-content');
        expect(previewContent.element.innerHTML).toBe(mockMailTemplates[0].contentHtml);
    });

    it('should replace mail template data with order data', async () => {
        const wrapper = await createWrapper(
            {
                ...defaultProps,
                document: mockDocuments[2],
            },
            true,
            [mockMailTemplates[3]],
        );
        await flushPromises();

        const previewContent = wrapper.find('.sw-order-send-document-modal__email-content');
        expect(previewContent.element.innerHTML).toBe(
            mockMailHeaderFooter.headerHtml +
                replaceTemplateVariables(mockMailTemplates[3].contentHtml, defaultProps) +
                mockMailHeaderFooter.footerHtml,
        );
    });

    it('should update the email template information when changing the email template', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-entity-single-select__selection-input').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-select-option--1').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-entity-single-select__selection-text').text()).toBe(
            mockMailTemplates[1].mailTemplateType.name,
        );

        const textFields = wrapper.findAllComponents('.mt-text-field');
        expect(textFields[1].props('modelValue')).toBe(mockMailTemplates[1].subject);

        const previewContent = wrapper.find('.sw-order-send-document-modal__email-content');
        expect(previewContent.element.innerHTML).toBe(
            mockMailHeaderFooter.headerHtml + mockMailTemplates[1].contentHtml + mockMailHeaderFooter.footerHtml,
        );
    });

    it('should emit the modal closing message', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.findByText('button', 'sw-order.documentSendModal.labelClose').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('modal-close')).toHaveLength(1);
    });

    it('should not search the mailTemplateRepository for a not configured document type on loading', async () => {
        const wrapper = await createWrapper({
            ...defaultProps,
            order: mockOrderWithMailHeaderFooter,
            document: mockUnknownDocument,
        });
        await flushPromises();

        expect(wrapper.vm.mailTemplateRepository.search).toHaveBeenCalledTimes(0);
    });

    it('should not try to set the mailTemplateId, subject and content, when not finding a mail template', async () => {
        const wrapper = await createWrapper({
            ...defaultProps,
            order: mockOrderWithMailHeaderFooter,
            document: mockDocuments[1],
        });
        await flushPromises();

        expect(wrapper.find('.sw-entity-single-select__selection-text').text()).toBe('');
        expect(wrapper.findAll('.mt-text-field .mt-field__hint-wrapper')[0].text()).toBe('');
        expect(wrapper.findAll('.mt-text-field .mt-field__hint-wrapper')[1].text()).toBe('');
        expect(wrapper.find('.sw-order-send-document-modal__email-content').text()).toBe('');
    });

    it('should not try to load the subject and content of a mail template with missing mail template', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-entity-single-select__selection-input').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-select-option--2').trigger('click');
        await flushPromises();

        expect(wrapper.findAll('.mt-text-field .mt-field__hint-wrapper')[0].text()).toBe('');
        expect(wrapper.findAll('.mt-text-field .mt-field__hint-wrapper')[1].text()).toBe('');
        expect(wrapper.find('.sw-order-send-document-modal__email-content').text()).toBe('');
    });

    it('should send an email without an error', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.findByText('button', 'sw-order.documentCard.labelSendDocument').trigger('click');
        await flushPromises();

        expect(wrapper.vm.mailService.sendMailTemplate).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.mailService.sendMailTemplate).toHaveBeenCalledWith(
            mockOrderWithMailHeaderFooter.orderCustomer.email,
            `${mockOrderWithMailHeaderFooter.orderCustomer.firstName} ${mockOrderWithMailHeaderFooter.orderCustomer.lastName}`,
            {
                ...mockMailTemplates[0],
                ...{
                    recipient: mockOrderWithMailHeaderFooter.orderCustomer.email,
                },
            },
            {
                getIds: expect.any(Function),
            },
            mockOrderWithMailHeaderFooter.salesChannelId,
            false,
            [mockDocuments[0].id],
            {
                order: mockOrderWithMailHeaderFooter,
                salesChannel: mockOrderWithMailHeaderFooter.salesChannel,
                document: mockDocuments[0],
                a11yDocuments: [
                    {
                        documentId: mockDocuments[0].id,
                        deepLinkCode: mockDocuments[0].deepLinkCode,
                        fileExtension: 'html',
                    },
                ],
            },
            null,
            null,
            Shopware.Context.api,
        );
        expect(wrapper.emitted('document-sent')).toHaveLength(1);
    });

    it('should show an error when the email sending fails', async () => {
        const wrapper = await createWrapper(
            {
                ...defaultProps,
                order: mockOrderWithMailHeaderFooter,
                document: mockDocuments[0],
            },
            false,
        );
        wrapper.vm.createNotificationError = jest.fn();
        await flushPromises();

        await wrapper.findByText('button', 'sw-order.documentCard.labelSendDocument').trigger('click');
        await flushPromises();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted('modal-close')).toHaveLength(1);
    });

    it('should load the link with a11y documents', async () => {
        const wrapper = await createWrapper();

        await flushPromises();

        expect(wrapper.vm.a11yDocuments).toHaveLength(1);
        expect(wrapper.vm.a11yDocuments[0]).toEqual({
            documentId: mockDocuments[0].id,
            deepLinkCode: '12345',
            fileExtension: 'html',
        });
    });
});
