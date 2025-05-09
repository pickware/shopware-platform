/*
 * @sw-package inventory
 */

import Plugin from 'src/plugin-system/plugin.class';
/** @deprecated tag:v6.8.0 - HttpClient is deprecated. Use native fetch API instead. */
import HttpClient from 'src/service/http-client.service';
import ElementReplaceHelper from 'src/helper/element-replace.helper';
import Debouncer from 'src/helper/debouncer.helper';

export default class ListingPlugin extends Plugin {

    static options = {
        dataUrl: '',
        filterUrl: '',
        params: {},
        filterPanelSelector: '.filter-panel',
        cmsProductListingSelector: '.cms-element-product-listing',
        cmsProductListingWrapperSelector: '.cms-element-product-listing-wrapper',
        cmsProductListingResultsSelector: '.js-listing-wrapper',
        activeFilterContainerSelector: '.filter-panel-active-container',
        activeFilterLabelClasses: 'filter-active btn',
        activeFilterLabelSelector: '.filter-active',
        activeFilterLabelPreviewClass: 'filter-active-preview',
        resetAllFilterButtonClasses: 'filter-reset-all btn btn-outline-danger',
        resetAllFilterButtonSelector: '.filter-reset-all',
        loadingIndicatorClass: 'is-loading',
        loadingElementLoaderClass: 'has-element-loader',
        ariaLiveSelector: '.filter-panel-aria-live',
        ariaLiveUpdates: true,
        disableEmptyFilter: false,
        snippets: {
            resetAllButtonText: 'Reset all',
            resetAllFiltersAriaLabel: 'Reset all filters',
            removeFilterAriaLabel: 'Remove filter',
        },
        //if the window should be scrolled to top of to the listingWrapper element
        scrollTopListingWrapper: true,
        // how much px the scrolling should be offset
        scrollOffset: 15,
    };

    init() {
        this._registry = [];

        /** @deprecated tag:v6.8.0 - HttpClient is deprecated. Use native fetch API instead. */
        this.httpClient = new HttpClient();

        this._urlFilterParams = Object.fromEntries(new URLSearchParams(window.location.search).entries());

        this._filterPanel = document.querySelector(this.options.filterPanelSelector);
        this._filterPanelActive = !!this._filterPanel;

        // Init functionality for the filter panel
        if (this._filterPanelActive) {
            this._showResetAll = false;
            this.activeFilterContainer = document.querySelector(this.options.activeFilterContainerSelector
            );
            this.ariaLiveContainer = document.querySelector(this.options.ariaLiveSelector);
        }

        this._cmsProductListingWrapper = document.querySelector(this.options.cmsProductListingWrapperSelector);
        this._cmsProductListingWrapperActive = !!this._cmsProductListingWrapper;

        this._allFiltersInitializedDebounce = Debouncer.debounce(this.sendDisabledFiltersRequest.bind(this), 100);

        this._registerEvents();
    }

    /**
     * @public
     */
    refreshRegistry() {
        // get only the registrations which are visible in the document
        const visibleRegistrations = this._registry.filter((entry) => document.body.contains(entry.el));

        // reinitialize the listing plugin
        this.init();

        // replace empty registry with existing visible registries
        this._registry = visibleRegistrations;

        // initialize plugins which are not registered yet
        window.PluginManager.initializePlugins();
    }

    /**
     * @param pushHistory
     * @param overrideParams
     * @public
     */
    changeListing(pushHistory = true, overrideParams = {}) {
        this._buildRequest(pushHistory, overrideParams);

        if (this._filterPanelActive) {
            this._buildLabels();
        }
    }

    /**
     * @param filterItem
     * @public
     */
    registerFilter(filterItem) {
        this._registry.push(filterItem);

        this._setFilterState(filterItem);

        if (this.options.disableEmptyFilter) {
            this._allFiltersInitializedDebounce();
        }
    }

    _setFilterState(filterItem) {
        if (Object.keys(this._urlFilterParams).length > 0 && typeof filterItem.setValuesFromUrl === 'function' ) {
            const stateChanged = filterItem.setValuesFromUrl(this._urlFilterParams);

            // Return if state of filter has not changed or filter panel is not active
            if (!stateChanged || !this._filterPanelActive) return;

            this._showResetAll = true;
            this._buildLabels();
        }
    }

    /**
     * @param filterItem
     * @public
     */
    deregisterFilter(filterItem) {
        this._registry = this._registry.filter((item) => {
            return (item !== filterItem);
        });
    }

    /**
     * @private
     */
    _fetchValuesOfRegisteredFilters() {
        const filters = {};

        this._registry.forEach((filterPlugin) => {
            const values = filterPlugin.getValues();

            Object.keys(values).forEach((key) => {
                if (Object.prototype.hasOwnProperty.call(filters, key)) {
                    Object.values(values[key]).forEach((value) => {
                        filters[key].push(value);
                    });
                } else {
                    filters[key] = values[key];
                }
            });
        });

        return filters;
    }

    /**
     * @private
     */
    _mapFilters(filters) {
        const mapped = {};
        Object.keys(filters).forEach((key) => {
            let value = filters[key];

            if (Array.isArray(value)) {
                value = value.join('|');
            }

            const string = `${value}`;
            if (string.length) {
                mapped[key] = value;
            }
        });

        return mapped;
    }

    /**
     * @param pushHistory
     * @param overrideParams
     * @private
     */
    _buildRequest(pushHistory = true, overrideParams = {}) {
        const filters = this._fetchValuesOfRegisteredFilters();
        const mapped = this._mapFilters(filters);

        if (this._filterPanelActive) {
            this._showResetAll = !!Object.keys(mapped).length;
        }

        if (this.options.params) {
            Object.keys(this.options.params).forEach((key) => {
                mapped[key] = this.options.params[key];
            });
        }

        Object.entries(overrideParams).forEach(([paramKey, paramValue]) => {
            mapped[paramKey] = paramValue;
        });

        let query = new URLSearchParams(mapped).toString();
        this.sendDataRequest(query);

        delete mapped['slots'];
        delete mapped['no-aggregations'];
        delete mapped['reduce-aggregations'];
        delete mapped['only-aggregations'];
        query = new URLSearchParams(mapped).toString();

        if (pushHistory) {
            this._updateHistory(query);
        }

        if (this.options.scrollTopListingWrapper) {
            this._scrollTopOfListing();
        }
    }

    _scrollTopOfListing() {
        const elemRect = this._cmsProductListingWrapper.getBoundingClientRect();
        if (elemRect.top >= 0) {
            return;
        }

        const top = elemRect.top + window.scrollY - this.options.scrollOffset;
        window.scrollTo({
            top: top,
            behavior: 'smooth',
        });
    }

    /**
     * @private
     */
    _getDisabledFiltersParamsFromParams(params) {
        const filterParams = Object.assign({}, {'only-aggregations': 1, 'reduce-aggregations': 1}, params);
        delete filterParams['p'];
        delete filterParams['order'];
        delete filterParams['no-aggregations'];

        return filterParams;
    }

    _updateHistory(query) {
        window.history.pushState({}, '', `${window.location.pathname}?${query}`);
    }

    /**
     * Build all labels for the currently active filters.
     */
    _buildLabels() {
        let labelHtml = '';

        this._registry.forEach((filterPlugin) => {
            const labels = filterPlugin.getLabels();

            if (labels.length) {
                labels.forEach((label) => {
                    labelHtml += this.getLabelTemplate(label);
                });
            }
        });

        this.activeFilterContainer.innerHTML = labelHtml;

        const resetButtons = this.activeFilterContainer.querySelectorAll(this.options.activeFilterLabelSelector);

        if (labelHtml.length) {
            this._registerLabelEvents(resetButtons);
            this.createResetAllButton();
        }
    }

    _registerLabelEvents(resetButtons) {
        resetButtons.forEach((label) => {
            label.addEventListener('click', () => this.resetFilter(label));
        });
    }

    /**
     * Create the button to reset all active filters.
     * Register event listener to remove a single filter.
     */
    createResetAllButton() {
        this.activeFilterContainer.insertAdjacentHTML('beforeend', this.getResetAllButtonTemplate());

        const resetAllButtonEl = this.activeFilterContainer.querySelector(this.options.resetAllFilterButtonSelector
        );

        resetAllButtonEl.removeEventListener('click', this.resetAllFilter.bind(this));
        resetAllButtonEl.addEventListener('click', this.resetAllFilter.bind(this));

        if (!this._showResetAll) {
            resetAllButtonEl.remove();
        }
    }

    /**
     * Remove the given filter from the filter map.
     *
     * @param {Object} label
     */
    resetFilter(label) {
        this._registry.forEach((filterPlugin) => {
            filterPlugin.reset(label.dataset.id);
        });

        this._buildRequest();
        this._buildLabels();
    }

    /**
     * Reset all active filters.
     */
    resetAllFilter() {
        this._registry.forEach((filterPlugin) => {
            filterPlugin.resetAll();
        });

        this._buildRequest();
        this._buildLabels();
    }

    /**
     * Template for an active filter label.
     *
     * @param {Object} label
     * @returns {string}
     */
    getLabelTemplate(label) {
        return `
        <button
            class="${this.options.activeFilterLabelClasses}"
            data-id="${label.id}"
            title="${this.options.snippets.removeFilterAriaLabel}: ${label.label}"
            aria-label="${this.options.snippets.removeFilterAriaLabel}: ${label.label}">
            ${this.getLabelPreviewTemplate(label)}
            ${label.label}
            <span aria-hidden="true" class="ms-1 fs-4">&times;</span>
        </button>
        `;
    }

    getLabelPreviewTemplate(label) {
        const previewClass = this.options.activeFilterLabelPreviewClass;

        if (label.previewHex) {
            return `
                <span class="${previewClass}" style="background-color: ${label.previewHex};"></span>
            `;
        }

        if (label.previewImageUrl) {
            return `
                <span class="${previewClass}" style="background-image: url('${label.previewImageUrl}');"></span>
            `;
        }

        return '';
    }

    getResetAllButtonTemplate() {
        return `
        <button class="${this.options.resetAllFilterButtonClasses}" aria-label="${this.options.snippets.resetAllFiltersAriaLabel}">
            ${this.options.snippets.resetAllButtonText}
        </button>
        `;
    }

    /**
     * Add classes to add loading styling.
     * Prevents the user from clicking filter labels during filter request.
     */
    addLoadingIndicatorClass() {
        this._filterPanel.classList.add(this.options.loadingIndicatorClass);
    }

    /**
     * Remove loading styling classes.
     */
    removeLoadingIndicatorClass() {
        this._filterPanel.classList.remove(this.options.loadingIndicatorClass);
    }

    /**
     * Add classes to add loading styling for product listing
     */
    addLoadingElementLoaderClass() {
        this._cmsProductListingWrapper.classList.add(this.options.loadingElementLoaderClass);
    }

    /**
     * Remove loading styling classes for product listing
     */
    removeLoadingElementLoaderClass() {
        this._cmsProductListingWrapper.classList.remove(this.options.loadingElementLoaderClass);
    }

    /**
     * Send request to get filtered product data.
     *
     * @param {String} filterParams - active filters as querystring
     */
    sendDataRequest(filterParams) {
        if (this._filterPanelActive) {
            this.addLoadingIndicatorClass();
        }

        if (this._cmsProductListingWrapperActive) {
            this.addLoadingElementLoaderClass();
        }

        if (this.options.disableEmptyFilter) {
            this.sendDisabledFiltersRequest();
        }

        fetch(`${this.options.dataUrl}?${filterParams}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((response) => response.text())
            .then((response) => {
                this.renderResponse(response);

                if (this._filterPanelActive) {
                    this.removeLoadingIndicatorClass();
                    this._updateAriaLive();
                }

                if (this._cmsProductListingWrapperActive) {
                    this.removeLoadingElementLoaderClass();
                }
            });
    }

    /**
     * Send request to get disabled filters data
     */
    sendDisabledFiltersRequest() {
        const filters = this._fetchValuesOfRegisteredFilters();
        const mapped = this._mapFilters(filters);
        if (this.options.params) {
            Object.keys(this.options.params).forEach((key) => {
                mapped[key] = this.options.params[key];
            });
        }

        // unset the debounce function after first execution
        this._allFiltersInitializedDebounce = () => {};

        const filterParams = this._getDisabledFiltersParamsFromParams(mapped);
        const paramsString = new URLSearchParams(filterParams).toString();

        fetch(`${this.options.filterUrl}?${paramsString}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(response => response.json())
            .then(filter => {
                this._registry.forEach((item) => {
                    if (typeof item.refreshDisabledState === 'function') {
                        item.refreshDisabledState(filter, filterParams);
                    }
                });
            });
    }

    /**
     * Inject the HTML of the filtered products to the page.
     *
     * @param {String} response - HTML of filtered product data.
     */
    renderResponse(response) {
        ElementReplaceHelper.replaceFromMarkup(response, this.options.cmsProductListingSelector);

        this._registry.forEach((item) => {
            if (typeof item.afterContentChange === 'function') {
                item.afterContentChange();
            }
        });

        window.PluginManager.initializePlugins();

        this.$emitter.publish('Listing/afterRenderResponse', { response });
    }

    /**
     * Update the aria-live region with the current listing results.
     *
     * @private
     */
    _updateAriaLive() {
        if (!this.options.ariaLiveUpdates) {
            return;
        }

        if (!this.ariaLiveContainer) {
            return;
        }

        const listingResultsEl = this.el.querySelector(this.options.cmsProductListingResultsSelector);
        this.ariaLiveContainer.innerHTML = listingResultsEl.dataset.ariaLiveText;
    }

    /**
     * @private
     */
    _registerEvents() {
        window.onpopstate = this._onWindowPopstate.bind(this);
    }

    /**
     * @private
     */
    _onWindowPopstate() {
        this.refreshRegistry();

        this._registry.forEach(filterItem => {
            if (Object.keys(this._urlFilterParams).length === 0) {
                this._urlFilterParams.p = 1;
            }
            this._setFilterState(filterItem);
        });

        if (this.options.disableEmptyFilter) {
            this._allFiltersInitializedDebounce();
        }

        this.changeListing(false);
    }
}
