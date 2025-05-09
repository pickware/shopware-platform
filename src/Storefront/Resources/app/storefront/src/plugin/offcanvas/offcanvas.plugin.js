import NativeEventEmitter from 'src/helper/emitter.helper';

// The bootstrap offcanvas class
const OFF_CANVAS_CLASS = 'offcanvas';
// Custom offcanvas class to identify offcanvas created by OffCanvasSingleton
const OFF_CANVAS_JS_CLASS = 'js-offcanvas-singleton';
const OFF_CANVAS_FULLWIDTH_CLASS = 'is-fullwidth';
const OFF_CANVAS_CLOSE_TRIGGER_CLASS = 'js-offcanvas-close';
const REMOVE_OFF_CANVAS_DELAY = 350;

/**
 * OffCanvas uses Bootstraps OffCanvas JavaScript implementation
 * @see https://getbootstrap.com/docs/5.2/components/offcanvas
 * @sw-package framework
 */
class OffCanvasSingleton {

    constructor() {
        this.$emitter = new NativeEventEmitter();
    }

    /**
     * Open the offcanvas and its backdrop
     * @param {string} content
     * @param {function|null} callback
     * @param {'left'|'right'|'bottom'} position
     * @param {boolean} closable
     * @param {number} delay
     * @param {boolean} fullwidth
     * @param {array|string} cssClass
     */
    open(content, callback, position, closable, delay, fullwidth, cssClass) {
        // avoid multiple backdrops
        this._removeExistingOffCanvas();

        const offCanvas = this._createOffCanvas(position, fullwidth, cssClass, closable);
        this.setContent(content, delay);
        this._openOffcanvas(offCanvas, callback);
    }

    /**
     * Method to change the content of the already visible OffCanvas
     * @param {string} content
     * @param {number} delay
     */
    setContent(content, delay) {
        const offCanvas = this.getOffCanvas();

        if (!offCanvas[0]) {
            return;
        }

        offCanvas[0].innerHTML = content;

        // register events again
        this._registerEvents(delay);
        this._setAriaAttrs();
    }

    /**
     * Method to add additional class to the first OffCanvas
     * @param {string} className
     */
    setAdditionalClassName(className) {
        const offCanvas = this.getOffCanvas();
        offCanvas[0].classList.add(className);
    }

    /**
     * Determine list of existing offcanvas
     * @returns {NodeListOf<Element>}
     * @private
     */
    getOffCanvas() {
        return document.querySelectorAll(`.${OFF_CANVAS_JS_CLASS}`);
    }

    /**
     * Close the offcanvas and its backdrop when the browser goes back in history
     * @param {number} delay
     */
    close(delay) {
        const OffCanvasElements = this.getOffCanvas();

        OffCanvasElements.forEach(offCanvas => {
            const offCanvasInstance = bootstrap.Offcanvas.getInstance(offCanvas);
            offCanvasInstance.hide();
        });

        setTimeout(() => {
            this.$emitter.publish('onCloseOffcanvas', {
                offCanvasContent: OffCanvasElements,
            });
        }, delay);
    }

    /**
     * Callback for close button, goes back in browser history to trigger close
     * @returns {void}
     */
    goBackInHistory() {
        window.history.back();
    }

    /**
     * Returns whether any OffCanvas exists or not
     * @returns {boolean}
     */
    exists() {
        return (this.getOffCanvas().length > 0);
    }

    /**
     * Opens the offcanvas and its backdrop
     *
     * @param {HTMLElement} offCanvas
     * @param {function} callback
     *
     * @private
     */
    _openOffcanvas(offCanvas, callback) {
        window.focusHandler.saveFocusState('offcanvas');

        OffCanvasSingleton.bsOffcanvas.show();
        window.history.pushState('offcanvas-open', '');

        // if a callback function is being injected execute it after opening the OffCanvas
        if (typeof callback === 'function') {
            callback();
        }
    }

    /**
     * Register events
     * @param {number} delay
     * @private
     */
    _registerEvents(delay) {
        const event = 'click';
        const offCanvasElements = this.getOffCanvas();

        // Ensure OffCanvas is removed from the DOM and events are published.
        offCanvasElements.forEach(offCanvas => {
            const onBsClose = () => {
                setTimeout(() => {
                    offCanvas.remove();

                    window.focusHandler.resumeFocusState('offcanvas');

                    this.$emitter.publish('onCloseOffcanvas', {
                        offCanvasContent: offCanvasElements,
                    });
                }, delay);

                offCanvas.removeEventListener('hide.bs.offcanvas', onBsClose);
            };

            offCanvas.addEventListener('hide.bs.offcanvas', onBsClose);
        });

        window.addEventListener('popstate', this.close.bind(this, delay), { once: true });
        const closeTriggers = document.querySelectorAll(`.${OFF_CANVAS_CLOSE_TRIGGER_CLASS}`);
        closeTriggers.forEach(trigger => trigger.addEventListener(event, this.close.bind(this, delay)));
    }

    _setAriaAttrs() {
        const offCanvas = this.getOffCanvas()[0];
        const headlineId = 'off-canvas-headline';
        const headline = offCanvas.querySelector(`[data-id="${headlineId}"]`);

        if (headline && !offCanvas.hasAttribute('aria-labelledby')) {
            headline.setAttribute('id', headlineId);
            offCanvas.setAttribute('aria-labelledby', headlineId);
        }
    }

    /**
     * Remove all existing offcanvas from DOM
     * @private
     */
    _removeExistingOffCanvas() {
        OffCanvasSingleton.bsOffcanvas = null;
        const offCanvasElements = this.getOffCanvas();
        return offCanvasElements.forEach(offCanvas => offCanvas.remove());
    }

    /**
     * Defines the position of the offcanvas by setting css class
     * @param {'left'|'right'|'bottom'} position
     * @returns {string}
     * @private
     */
    _getPositionClass(position) {
        if (position === 'left') {
            return 'offcanvas-start';
        }

        if (position === 'right') {
            return 'offcanvas-end';
        }

        return `offcanvas-${position}`;
    }

    /**
     * Creates the offcanvas element prototype including all relevant settings,
     * appends it to the DOM and returns the HTMLElement for further processing
     * @param {'left'|'right'|'bottom'} position
     * @param {boolean} fullwidth
     * @param {array|string} cssClass
     * @param {boolean} closable
     * @returns {HTMLElement}
     * @private
     */
    _createOffCanvas(position, fullwidth, cssClass, closable) {
        const offCanvas = document.createElement('div');
        offCanvas.classList.add(OFF_CANVAS_CLASS, OFF_CANVAS_JS_CLASS);
        offCanvas.classList.add(this._getPositionClass(position));
        offCanvas.setAttribute('tabindex', '-1');

        if (fullwidth === true) {
            offCanvas.classList.add(OFF_CANVAS_FULLWIDTH_CLASS);
        }

        if (cssClass) {
            const type = typeof cssClass;

            if (type === 'string') {
                offCanvas.classList.add(cssClass);
            } else if (Array.isArray(cssClass)) {
                cssClass.forEach((value) => {
                    offCanvas.classList.add(value);
                });
            } else {
                throw new Error(`The type "${type}" is not supported. Please pass an array or a string.`);
            }
        }

        document.body.appendChild(offCanvas);

        OffCanvasSingleton.bsOffcanvas = new bootstrap.Offcanvas(offCanvas, {
            // Only use "static" mode (no close via click on backdrop) when "closable" option is explicitly set to "false".
            backdrop: closable === false ? 'static' : true,
        });

        return offCanvas;
    }
}

/**
 * Create the OffCanvas instance.
 * @type {Readonly<OffCanvasSingleton>}
 */
export const OffCanvasInstance = Object.freeze(new OffCanvasSingleton());

export default class OffCanvas {

    /**
     * Open the OffCanvas
     * @param {string} content
     * @param {function|null} callback
     * @param {'left'|'right'|'bottom'} position
     * @param {boolean} closable
     * @param {number} delay
     * @param {boolean} fullwidth
     * @param {array|string} cssClass
     */
    static open(content, callback = null, position = 'left', closable = true, delay = REMOVE_OFF_CANVAS_DELAY, fullwidth = false, cssClass = '') {
        OffCanvasInstance.open(content, callback, position, closable, delay, fullwidth, cssClass);
    }

    /**
     * Change content of visible OffCanvas
     * @param {string} content
     * @param {boolean} closable
     * @param {number} delay
     */
    static setContent(content, closable = true, delay = REMOVE_OFF_CANVAS_DELAY) {
        OffCanvasInstance.setContent(content, closable, delay);
    }

    /**
     * adds an additional class to the offcanvas
     *
     * @param {string} className
     */
    static setAdditionalClassName(className) {
        OffCanvasInstance.setAdditionalClassName(className);
    }

    /**
     * Close the OffCanvas
     * @param {number} delay
     */
    static close(delay = REMOVE_OFF_CANVAS_DELAY) {
        OffCanvasInstance.close(delay);
    }

    /**
     * Returns whether any OffCanvas exists or not
     * @returns {boolean}
     */
    static exists() {
        return OffCanvasInstance.exists();
    }

    /**
     * returns all existing offcanvas elements
     *
     * @returns {NodeListOf<Element>}
     */
    static getOffCanvas() {
        return OffCanvasInstance.getOffCanvas();
    }

    /**
     * Expose constant
     * @returns {number}
     */
    static REMOVE_OFF_CANVAS_DELAY() {
        return REMOVE_OFF_CANVAS_DELAY;
    }
}
