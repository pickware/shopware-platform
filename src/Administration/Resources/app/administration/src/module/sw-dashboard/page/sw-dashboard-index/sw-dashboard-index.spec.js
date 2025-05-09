import { mount } from '@vue/test-utils';
import dictionary from 'src/module/sw-dashboard/snippet/en-GB.json';

const snippetPathGreeting = 'sw-dashboard.introduction.daytimeHeadline';

async function createWrapper(privileges = []) {
    return mount(await wrapTestComponent('sw-dashboard-index', { sync: true }), {
        global: {
            stubs: {
                'sw-page': await wrapTestComponent('sw-page'),
                'sw-card-view': await wrapTestComponent('sw-card-view'),
                'sw-external-link': true,
                'sw-dashboard-statistics': true,
                'sw-usage-data-consent-banner': true,
                'sw-help-text': true,
                'sw-extension-component-section': true,
                'sw-search-bar': true,
                'sw-app-topbar-button': true,
                'sw-notification-center': true,
                'sw-help-center-v2': true,
                'router-link': true,
                'sw-app-actions': true,
                'sw-error-summary': true,
                'sw-context-menu-item': true,
                'sw-context-button': true,
            },
            mocks: {
                $tc: jest.fn().mockImplementation((snippetPath, placeholders) => {
                    return `${snippetPathGreeting}, ${placeholders?.greetingName || ''}`;
                }),
                $i18n: {
                    locale: 'en-GB',
                    fallbackLocale: { value: 'en-GB' },
                    messages: {
                        value: { 'en-GB': dictionary },
                    },
                },
                $route: {
                    meta: {
                        $module: {},
                    },
                },
            },
            provide: {
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
            },
        },
    });
}

/**
 * @sw-package after-sales
 */
describe('module/sw-dashboard/page/sw-dashboard-index', () => {
    let wrapper;

    beforeAll(async () => {
        jest.useFakeTimers('modern');
    });

    afterAll(() => {
        jest.useRealTimers();
    });

    it('shall not print a personal message if firstName is not set', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-dashboard-index__welcome-title').text()).toStrictEqual(snippetPathGreeting);
    });

    it('should display users firstName', async () => {
        const firstName = 'John';
        wrapper = await createWrapper();
        await flushPromises();

        Shopware.Store.get('session').setCurrentUser({
            firstName: firstName,
        });
        await flushPromises();

        expect(wrapper.find('.sw-dashboard-index__welcome-title').text()).toBe(`${snippetPathGreeting}, ${firstName}`);
    });

    it('shall not print a personal message if username but not firstName is set', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        Shopware.Store.get('session').setCurrentUser({
            username: 'username',
        });
        await flushPromises();

        expect(wrapper.find('.sw-dashboard-index__welcome-title').text()).toStrictEqual(snippetPathGreeting);
    });

    [
        {
            dateTime: new Date(2021, 4, 19, 4, 30, 30),
            expectedTimeSlot: '23h',
        },
        {
            dateTime: new Date(2021, 4, 19, 5, 30, 30),
            expectedTimeSlot: '5h',
        },
        {
            dateTime: new Date(2021, 4, 19, 10, 30, 30),
            expectedTimeSlot: '5h',
        },
        {
            dateTime: new Date(2021, 4, 19, 11, 30, 30),
            expectedTimeSlot: '11h',
        },
        {
            dateTime: new Date(2021, 4, 19, 14, 30, 30),
            expectedTimeSlot: '11h',
        },
        {
            dateTime: new Date(2021, 4, 19, 18, 30, 30),
            expectedTimeSlot: '18h',
        },
        {
            dateTime: new Date(2021, 4, 19, 22, 30, 30),
            expectedTimeSlot: '18h',
        },
        {
            dateTime: new Date(2021, 4, 19, 23, 30, 30),
            expectedTimeSlot: '23h',
        },
        {
            dateTime: new Date(2021, 4, 19, 0, 0, 0),
            expectedTimeSlot: '23h',
        },
    ].forEach(({ dateTime, expectedTimeSlot }) => {
        it(`should return datetime aware headline for daytime: ${dateTime.getHours()}h, expected slot: ${expectedTimeSlot}`, async () => {
            wrapper = await createWrapper();
            await flushPromises();

            const greetingType = 'daytimeHeadline';
            /* as of today there are 4 timeslots: 23 - 4, 5 - 10, 11 - 17, 18 - 22 */
            /* the first param of `getGreetingTimeKey` must be ' headline' or 'welcomeText' */
            jest.setSystemTime(dateTime);
            expect(wrapper.vm.getGreetingTimeKey(greetingType)).toContain(
                `sw-dashboard.introduction.${greetingType}.${expectedTimeSlot}`,
            );
        });
    });

    [
        {
            dateTime: new Date(2021, 4, 19, 4, 30, 30),
            expectedTimeSlot: '23h',
        },
        {
            dateTime: new Date(2021, 4, 19, 5, 30, 30),
            expectedTimeSlot: '5h',
        },
        {
            dateTime: new Date(2021, 4, 19, 10, 30, 30),
            expectedTimeSlot: '5h',
        },
        {
            dateTime: new Date(2021, 4, 19, 11, 30, 30),
            expectedTimeSlot: '11h',
        },
        {
            dateTime: new Date(2021, 4, 19, 14, 30, 30),
            expectedTimeSlot: '11h',
        },
        {
            dateTime: new Date(2021, 4, 19, 18, 30, 30),
            expectedTimeSlot: '18h',
        },
        {
            dateTime: new Date(2021, 4, 19, 22, 30, 30),
            expectedTimeSlot: '18h',
        },
        {
            dateTime: new Date(2021, 4, 19, 23, 30, 30),
            expectedTimeSlot: '23h',
        },
        {
            dateTime: new Date(2021, 4, 19, 0, 0, 0),
            expectedTimeSlot: '23h',
        },
    ].forEach(({ dateTime, expectedTimeSlot }) => {
        it(`should return datetime aware welcoming subline for daytime:\
            ${dateTime.getHours()}h, expected slot: ${expectedTimeSlot}`, async () => {
            wrapper = await createWrapper();
            await flushPromises();

            const greetingType = 'daytimeWelcomeText';
            /* as of today there are 4 timeslots: 23 - 4, 5 - 10, 11 - 17, 18 - 22 */
            /* the first param of `getGreetingTimeKey` must be ' headline' or 'welcomeText' */
            jest.setSystemTime(dateTime);
            expect(wrapper.vm.getGreetingTimeKey(greetingType)).toContain(
                `sw-dashboard.introduction.${greetingType}.${expectedTimeSlot}`,
            );
        });
    });
});
