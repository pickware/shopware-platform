/**
 * @sw-package framework
 */

import { config, mount } from '@vue/test-utils';

import 'src/module/sw-login/view/sw-login-recovery';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-alert';

async function createWrapper() {
    // delete global $router and $routes mocks
    delete config.global.mocks.$router;
    delete config.global.$route;

    return mount(await wrapTestComponent('sw-login-recovery', { sync: true }), {
        global: {
            mocks: {
                $tc: (...args) => JSON.stringify([...args]),
                $router: { push: jest.fn() },
            },
            provide: {
                userService: {},
                licenseViolationService: {},
            },
            stubs: {
                'router-view': true,
                'sw-loader': true,
                'sw-text-field': {
                    props: {
                        value: {
                            required: true,
                            type: String,
                        },
                    },
                    template:
                        '<div><input id="email" :value="value" @input="ev => $emit(`input`, ev.target.value)"></input></div>',
                },
                'sw-contextual-field': true,
                'router-link': true,
            },
        },
    });
}

describe('module/sw-login/recovery.spec.js', () => {
    let wrapper;

    beforeEach(async () => {
        if (!Shopware.Service('userRecoveryService')) {
            Shopware.Service().register('userRecoveryService', () => {
                return {
                    createRecovery: () => {
                        return new Promise((resolve, reject) => {
                            const response = {
                                config: {
                                    url: 'test.test.de',
                                },
                                response: {
                                    data: {
                                        errors: {
                                            status: 429,
                                            meta: {
                                                parameters: {
                                                    seconds: 1,
                                                },
                                            },
                                        },
                                    },
                                },
                            };

                            reject(response);
                        });
                    },
                };
            });
        }
        wrapper = await createWrapper();
    });

    it('should redirect on submit', async () => {
        await wrapper.get('input').setValue('test@example.com');

        expect(wrapper.find('.sw-alert').exists()).toBe(false);

        await wrapper.get('.sw-login__recovery-form').trigger('submit');

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.$router.push).toHaveBeenLastCalledWith({
            name: 'sw.login.index.recoveryInfo',
            params: {
                waitTime: 1,
            },
        });
    });
});
