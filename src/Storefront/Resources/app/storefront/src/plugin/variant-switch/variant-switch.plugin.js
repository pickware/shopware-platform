/*
 * @sw-package inventory
 */

import Plugin from 'src/plugin-system/plugin.class';
import PageLoadingIndicatorUtil from 'src/utility/loading-indicator/page-loading-indicator.util';
/** @deprecated tag:v6.8.0 - HttpClient is deprecated. Use native fetch API instead. */
import HttpClient from 'src/service/http-client.service';

/**
 * this plugin submits the variant form
 * with the correct data options
 */
export default class VariantSwitchPlugin extends Plugin {

    static options = {
        url: '',
        elementId: '',
        pageType: '',
        radioFieldSelector: '.product-detail-configurator-option-input',
        selectFieldSelector: '.product-detail-configurator-select-input',
        focusHandlerKey: 'variant-switch',
    };

    init() {
        /** @deprecated tag:v6.8.0 - HttpClient is deprecated. Use native fetch API instead. */
        this._httpClient = new HttpClient();
        this._radioFields = this.el.querySelectorAll(this.options.radioFieldSelector);
        this._selectFields = this.el.querySelectorAll(this.options.selectFieldSelector);
        this._elementId = this.options.elementId;
        this._pageType = this.options.pageType;

        this._ensureFormElement();
        this._preserveCurrentValues();
        this._registerEvents();
        this._resumeFocusState();
    }

    /**
     * ensures that the plugin element is a form
     *
     * @private
     */
    _ensureFormElement() {
        if (this.el.nodeName.toLowerCase() !== 'form') {
            throw new Error('This plugin can only be applied on a form element!');
        }
    }

    /**
     * saves the current value on each form element
     * to be able to retrieve it once it has changed
     *
     * @private
     */
    _preserveCurrentValues() {
        if (this._radioFields) {
            this._radioFields.forEach(field => {
                if (VariantSwitchPlugin._isFieldSerializable(field)) {
                    if (field.dataset) {
                        field.dataset.variantSwitchValue = field.value;
                    }
                }
            });
        }
    }

    /**
     * register all needed events
     *
     * @private
     */
    _registerEvents() {
        this.el.addEventListener('change', event => this._onChange(event));
    }

    /**
     * callback when the form has changed
     *
     * @param event
     * @private
     */
    _onChange(event) {
        const switchedOptionId = this._getSwitchedOptionId(event.target);
        const selectedOptions = this._getFormValue();
        this._preserveCurrentValues();

        this.$emitter.publish('onChange');

        const query = {
            switched: switchedOptionId,
            options: JSON.stringify(selectedOptions),
        };

        if (this._elementId && this._pageType !== 'product_detail') {
            const url = `${this.options.url}?${new URLSearchParams({...query, elementId: this._elementId}).toString()}`;
            document.$emitter.publish('updateBuyWidget', { url, elementId: this._elementId });

            return;
        }

        this._saveFocusState(event.target);
        this._redirectToVariant(query);
    }

    /**
     * returns the option id of the recently switched field
     *
     * @param field
     * @returns {*}
     * @private
     */
    _getSwitchedOptionId(field) {
        if (!VariantSwitchPlugin._isFieldSerializable(field)) {
            return false;
        }

        return field.name;
    }

    /**
     * returns the current selected
     * variant options from the form
     *
     * @private
     */
    _getFormValue() {
        const serialized = {};
        if (this._radioFields) {
            this._radioFields.forEach(field => {
                if (VariantSwitchPlugin._isFieldSerializable(field)) {
                    if (field.checked) {
                        serialized[field.name] = field.value;
                    }
                }
            });
        }

        if (this._selectFields) {
            this._selectFields.forEach(field => {
                if (VariantSwitchPlugin._isFieldSerializable(field)) {
                    const selectedOption = [...field.options].find(option => option.selected);
                    serialized[field.name] = selectedOption.value;
                }
            });
        }

        return serialized;
    }

    /**
     * checks id the field is a value field
     * and therefore serializable
     *
     * @param field
     * @returns {boolean|*}
     *
     * @private
     */
    static _isFieldSerializable(field) {
        return !field.name || field.disabled || ['file', 'reset', 'submit', 'button'].indexOf(field.type) === -1;
    }

    /**
     * disables all form fields on the form submit
     *
     * @private
     */
    _disableFields() {
        this._radioFields.forEach(field => {
            if (field.classList) {
                field.classList.add('disabled', 'disabled');
            }
        });
    }

    /**
     * gets the url of the new variant
     * and redirects to this url
     *
     * @param {Object} data
     * @private
     */
    _redirectToVariant(data) {
        PageLoadingIndicatorUtil.create();

        const url = `${this.options.url}?${new URLSearchParams(data).toString()}`;

        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(response => response.json())
            .then(data => window.location.replace(data.url));
    }

    /**
     * @param {HTMLInputElement} inputElement
     * @private
     */
    _saveFocusState(inputElement) {
        window.focusHandler.saveFocusStatePersistent(this.options.focusHandlerKey, `[id="${inputElement.id}"]`);
    }

    /**
     * @private
     */
    _resumeFocusState() {
        window.focusHandler.resumeFocusStatePersistent(this.options.focusHandlerKey);
    }
}
