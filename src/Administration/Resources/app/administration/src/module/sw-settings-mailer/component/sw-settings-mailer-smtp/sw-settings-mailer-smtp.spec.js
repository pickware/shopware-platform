/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

describe('src/module/sw-settings-mailer/component/sw-settings-mailer-smtp', () => {
    const createWrapper = async (mailerSettings = {}) => {
        return mount(
            await wrapTestComponent('sw-settings-mailer-smtp', {
                sync: true,
            }),
            {
                props: {
                    mailerSettings,
                },
                global: {
                    renderStubDefaultSlot: true,
                    provide: {
                        validationService: {},
                    },
                    stubs: {
                        'sw-text-field': await wrapTestComponent('sw-text-field'),
                        'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                        'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                        'sw-block-field': await wrapTestComponent('sw-block-field'),
                        'sw-base-field': await wrapTestComponent('sw-base-field'),
                        'sw-field-error': true,
                        'sw-single-select': true,

                        'sw-help-text': true,
                        'sw-field-copyable': true,
                        'sw-inheritance-switch': true,
                        'sw-ai-copilot-badge': true,
                    },
                    mocks: {
                        $tc(translationKey) {
                            return translationKey;
                        },
                    },
                },
            },
        );
    };

    it('should be a vue js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should assign host value', async () => {
        const wrapper = await createWrapper({
            'core.mailerSettings.host': 'https://example.com',
        });
        await flushPromises();

        const host = wrapper.find('input[aria-label="sw-settings-mailer.card-smtp.host"]').element.value;
        expect(host).toBe('https://example.com');
    });

    it('should assign port value', async () => {
        const wrapper = await createWrapper({
            'core.mailerSettings.port': 476,
        });
        await flushPromises();

        const port = wrapper.findByLabel('sw-settings-mailer.card-smtp.port').element.value;
        expect(port).toBe('476');
    });
});
