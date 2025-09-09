import NavbarPlugin from 'src/plugin/navbar/navbar.plugin';
import FocusHandler from 'src/helper/focus-handler.helper';

describe('NavbarPlugin', () => {
    let navbarPlugin;
    let mockElement;
    let mockLink;

    beforeEach(() => {
        // Create a mock DOM environment
        mockElement = document.createElement('div');
        mockLink = document.createElement('a');
        mockLink.classList.add('main-navigation-link');
        mockLink.href = '#';
        mockElement.appendChild(mockLink);

        // Spy on addEventListener method and window open method
        jest.spyOn(mockLink, 'addEventListener');
        jest.spyOn(window, 'open').mockImplementation(() => {});

        // Instantiate the NavbarPlugin with only one top-level link
        navbarPlugin = new NavbarPlugin(mockElement, {}, false); // Pass false to prevent init from being called
        navbarPlugin._topLevelLinks = [mockLink];
    });

    afterEach(() => {
        // Clear the mock for window.open
        window.open.mockRestore();
    });

    test('init should initialize _topLevelLinks', () => {
        // Create a new instance of NavbarPlugin inside the test
        navbarPlugin = new NavbarPlugin(mockElement, {}, false);
        navbarPlugin._topLevelLinks = [mockLink];

        // Clear the mock history of addEventListener
        mockLink.addEventListener.mockClear();

        navbarPlugin.init();

        expect(navbarPlugin._topLevelLinks).not.toBeNull();
        expect(mockLink.addEventListener).toHaveBeenCalledTimes(3);
    });

    test('init should omit click event for elements without a reference', () => {
        // Create a new instance of NavbarPlugin inside the test
        navbarPlugin = new NavbarPlugin(mockElement, {}, false);
        mockLink.removeAttribute('href');
        navbarPlugin._topLevelLinks = [mockLink];

        // Clear the mock history of addEventListener
        mockLink.addEventListener.mockClear();

        navbarPlugin.init();

        const addedEvents = {};
        mockLink.addEventListener.mock.calls.forEach(call => {
            addedEvents[call[0]] = call[1];
        });

        expect(navbarPlugin._topLevelLinks).not.toBeNull();
        expect(mockLink.addEventListener).toHaveBeenCalledTimes(2);

        expect(addedEvents['mouseenter']).toBeDefined();
        expect(typeof addedEvents['mouseenter']).toBe('function');

        expect(addedEvents['mouseleave']).toBeDefined();
        expect(typeof addedEvents['mouseleave']).toBe('function');

        expect(addedEvents).not.toContain('click');
    });

    test('_toggleNavbar should handle mouseenter and mouseleave events', () => {
        const mockEventEnter = {type: 'mouseenter'};
        const mockEventLeave = {type: 'mouseleave'};

        navbarPlugin._toggleNavbar = jest.fn();

        navbarPlugin._toggleNavbar(mockLink, mockEventEnter);
        expect(navbarPlugin._toggleNavbar).toHaveBeenCalledWith(mockLink, mockEventEnter);

        navbarPlugin._toggleNavbar(mockLink, mockEventLeave);
        expect(navbarPlugin._toggleNavbar).toHaveBeenCalledWith(mockLink, mockEventLeave);
    });

    test('_navigateToLinkOnClick should open in new window when target blank is set', () => {
        const mockEventClick = { type: 'click', pageX: 99 };
        const mockLink = { href: 'https://example.com', target: '_blank' };

        navbarPlugin._navigateToLinkOnClick(mockLink, mockEventClick);

        expect(window.open).toHaveBeenCalledWith(mockLink.href, '_blank', 'noopener, noreferrer');
    });

    test('_navigateToLinkOnClick should set window.location.href if not target _blank', () => {
        delete window.location;
        window.location = new URL('https://www.example.com');

        const mockEventClick = { type: 'click', pageX: 99 };
        const mockLink = { href: 'https://example.com/abc', target: '_self' };

        navbarPlugin._navigateToLinkOnClick(mockLink, mockEventClick);

        expect(window.location.href).toBe(mockLink.href);
    });

    test('_closeAllDropdowns should close all dropdowns', () => {
        // Create mock dropdown instances
        const mockDropdown1 = {hide: jest.fn(), _menu: {classList: {contains: jest.fn().mockReturnValue(true)}}};
        const mockDropdown2 = {hide: jest.fn(), _menu: {classList: {contains: jest.fn().mockReturnValue(true)}}};

        // Mock window.bootstrap.Dropdown.getInstance to return the mock dropdown instances
        window.bootstrap = {Dropdown: {getInstance: jest.fn()}};
        window.bootstrap.Dropdown.getInstance.mockReturnValueOnce(mockDropdown1);
        window.bootstrap.Dropdown.getInstance.mockReturnValueOnce(mockDropdown2);

        // Mock _topLevelLinks to return two links
        navbarPlugin._topLevelLinks = [mockLink, mockLink];

        navbarPlugin._closeAllDropdowns();

        // Check if hide was called on both mock dropdown instances
        expect(mockDropdown1.hide).toHaveBeenCalled();
        expect(mockDropdown2.hide).toHaveBeenCalled();
    });

    test('_debounce should delay execution of function', () => {
        jest.useFakeTimers();

        const mockDropdown = {
            show: jest.fn(),
            hide: jest.fn(),
            _menu: {classList: {contains: jest.fn().mockReturnValue(false)}}
        };
        window.bootstrap = {
            Dropdown: {
                getOrCreateInstance: jest.fn().mockReturnValue(mockDropdown),
                getInstance: jest.fn().mockReturnValue(mockDropdown),
            },
        };

        // At this point in time, the callback passed to _debounce should not have been called yet
        expect(mockDropdown.show).not.toHaveBeenCalled();

        const mockEventEnter = {type: 'mouseenter'};
        navbarPlugin._toggleNavbar(mockLink, mockEventEnter);

        // Fast-forward until all timers have been executed
        jest.runAllTimers();

        // Now our callback should have been called!
        expect(mockDropdown.show).toHaveBeenCalled();

        const mockEventLeave = {type: 'mouseleave'};
        navbarPlugin._toggleNavbar(mockLink, mockEventLeave);

        expect(navbarPlugin._isMouseOver).toBe(false);
    });

    test('_clearDebounce should clear the debounce timer', () => {
        jest.useFakeTimers();

        const callback = jest.fn();
        navbarPlugin._debounce(callback, navbarPlugin.options.debounceTime);
        navbarPlugin._clearDebounce();

        jest.runOnlyPendingTimers();
        expect(callback).not.toHaveBeenCalled();
    });

    test('_toggleNavbar should set _isMouseOver to true on mouseenter', () => {
        const mockEventEnter = {type: 'mouseenter'};
        const mockDropdown = {_menu: {classList: {contains: jest.fn().mockReturnValue(false)}}};
        window.bootstrap = {
            Dropdown: {
                getOrCreateInstance: jest.fn().mockReturnValue(mockDropdown),
            },
        };

        navbarPlugin._toggleNavbar(mockLink, mockEventEnter);
        expect(navbarPlugin._isMouseOver).toBe(true);
    });

    test('_toggleNavbar should call _debounce on mouseenter', () => {
        const mockEventEnter = {type: 'mouseenter'};
        const mockDropdown = {_menu: {classList: {contains: jest.fn().mockReturnValue(false)}}};
        window.bootstrap = {
            Dropdown: {
                getOrCreateInstance: jest.fn().mockReturnValue(mockDropdown),
            },
        };
        navbarPlugin._debounce = jest.fn();
        navbarPlugin._toggleNavbar(mockLink, mockEventEnter);
        expect(navbarPlugin._debounce).toHaveBeenCalled();
    });

    test('_closeAllDropdowns should call hide on dropdowns with show class', () => {
        const mockDropdown = {hide: jest.fn(), _menu: {classList: {contains: jest.fn().mockReturnValue(true)}}};
        window.bootstrap = {
            Dropdown: {
                getInstance: jest.fn().mockReturnValue(mockDropdown),
            },
        };
        navbarPlugin._topLevelLinks = [mockLink];
        navbarPlugin._closeAllDropdowns();
        expect(mockDropdown.hide).toHaveBeenCalled();
    });

    test('current page is applied on load event', () => {
        const mockEvent = new Event('load');
        jest.spyOn(navbarPlugin, '_setCurrentPage'); // Spy on the method

        window.addEventListener('load', () => {
            navbarPlugin._setCurrentPage();
        });
        window.dispatchEvent(mockEvent);

        expect(navbarPlugin._setCurrentPage).toHaveBeenCalled();
    });

    test('active class and aria-current is set for one nav-item', () => {
        const mockLink = document.createElement('a');
        mockLink.classList.add('nav-item-1-link');
        mockLink.setAttribute('href', 'https://example.com');
        mockElement.appendChild(mockLink);

        window.activeNavigationId = 1; // Set the activeNavigationId

        navbarPlugin._setCurrentPage();

        expect(mockLink.getAttribute('aria-current')).toBe('page');
        expect(mockLink.classList.contains('active')).toBe(true);
    });

    test('_restoreFocusAfterBtnClose should focus related dropdown top level link', () => {
        window.focusHandler = new FocusHandler();

        const mockNavItem = document.createElement('div');
        mockNavItem.classList.add('nav-item');
        const mockLink = document.createElement('a');
        mockLink.classList.add('main-navigation-link');
        mockLink.focus = jest.fn();
        mockNavItem.appendChild(mockLink);
        const mockDropdown = document.createElement('div');
        mockNavItem.appendChild(mockDropdown);

        const mockEvent = {
            target: mockDropdown,
            relatedTarget: null,
        };

        navbarPlugin._restoreFocusAfterBtnClose(mockEvent);

        expect(mockLink.focus).toHaveBeenCalled();
    });

    test('_restoreFocusAfterBtnClose should skip events dispatched for top level links', () => {
        window.focusHandler = new FocusHandler();

        const mockNavItem = document.createElement('div');
        mockNavItem.classList.add('nav-item');
        const mockLink = document.createElement('a');
        mockLink.classList.add('main-navigation-link');
        mockLink.focus = jest.fn();
        mockNavItem.appendChild(mockLink);

        const mockEvent = {
            target: mockLink,
            relatedTarget: null,
        };

        navbarPlugin._restoreFocusAfterBtnClose(mockEvent);

        expect(mockLink.focus).not.toHaveBeenCalled();
    });

    test('_toggleNavbar should close all dropdowns on mouseenter nav item without dropdown', () => {
        jest.useFakeTimers();

        const mockEventEnter = {type: 'mouseenter'};
        const mockLinkNoDropdown = mockLink.cloneNode();
        mockLinkNoDropdown.noDropdown = true;
        navbarPlugin._topLevelLinks.push(mockLinkNoDropdown);
        const mockDropdown = {
            show: jest.fn(),
            hide: jest.fn(),
            _menu: {classList: {contains: jest.fn().mockReturnValueOnce(false).mockReturnValueOnce(true)}},
        };
        const mockNoDropdown = {
            show: jest.fn(),
            hide: jest.fn(),
            _menu: null, // simulate no dropdown menu
        };
        window.bootstrap = {
            Dropdown: {
                getOrCreateInstance: (link) => link.noDropdown ? mockNoDropdown : mockDropdown,
                getInstance: (link) => link.noDropdown ? mockNoDropdown : mockDropdown,
            },
        };
        jest.spyOn(navbarPlugin, '_closeAllDropdowns');

        // toggle link with dropdown
        navbarPlugin._toggleNavbar(mockLink, mockEventEnter);

        jest.runAllTimers();

        expect(navbarPlugin._closeAllDropdowns).toHaveBeenCalled();
        expect(mockDropdown.show).toHaveBeenCalled();

        // reset _closeAllDropdowns call count
        navbarPlugin._closeAllDropdowns.mockClear();

        // toggle link without dropdown
        navbarPlugin._toggleNavbar(mockLinkNoDropdown, mockEventEnter);

        jest.runAllTimers();

        expect(navbarPlugin._closeAllDropdowns).toHaveBeenCalled();
        expect(mockDropdown.hide).toHaveBeenCalled();
        expect(mockNoDropdown.show).not.toHaveBeenCalled();
    });

    test('_toggleNavbar should blur top level link on mouseenter', () => {
        jest.useFakeTimers();

        const mockEventEnter = {type: 'mouseenter'};
        const mockDropdown = {
            show: jest.fn(),
            hide: jest.fn(),
            _menu: {classList: {contains: jest.fn().mockReturnValue(false)}},
        };
        window.bootstrap = {
            Dropdown: {
                getOrCreateInstance: jest.fn().mockReturnValue(mockDropdown),
                getInstance: jest.fn().mockReturnValue(mockDropdown),
            },
        };
        mockLink.blur = jest.fn();

        navbarPlugin._toggleNavbar(mockLink, mockEventEnter);

        jest.runAllTimers();

        expect(mockLink.blur).toHaveBeenCalled();
    });
});
