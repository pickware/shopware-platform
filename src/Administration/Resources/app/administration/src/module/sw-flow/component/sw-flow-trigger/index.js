import template from './sw-flow-trigger.html.twig';
import './sw-flow-trigger.scss';

const { Component, Store } = Shopware;
const { mapPropertyErrors, mapState } = Component.getComponentHelper();
const utils = Shopware.Utils;
const { camelCase, capitalizeString } = Shopware.Utils.string;
const { isEmpty } = utils.types;

/**
 * @private
 * @sw-package after-sales
 */
export default {
    template,

    inject: [
        'repositoryFactory',
        'businessEventService',
    ],

    emits: ['option-select'],

    props: {
        overlay: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
        eventName: {
            type: String,
            required: true,
        },
        isUnknownTrigger: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            isExpanded: false,
            isLoading: false,
            searchTerm: '',
            searchResult: [],
            searchResultFocusItem: {},
            selectedTreeItem: {},
            setInputFocusClass: null,
            removeInputFocusClass: null,
            showConfirmModal: false,
            triggerSelect: {},
        };
    },

    computed: {
        swFlowTriggerClasses() {
            return { overlay: this.overlay };
        },

        formatEventName() {
            if (!this.eventName) {
                return this.eventName;
            }

            return this.getEventName(this.eventName);
        },

        showTreeView() {
            return this.eventTree.length >= 0 && (this.searchTerm.length <= 0 || this.searchTerm === this.formatEventName);
        },

        eventTree() {
            return this.getEventTree(this.triggerEvents);
        },

        isTemplate() {
            return this.$route.query?.type === 'template';
        },

        triggerNamePlaceholder() {
            if (!this.isUnknownTrigger) {
                return this.$tc('sw-flow.detail.trigger.placeholder');
            }

            return this.$tc('sw-flow.detail.trigger.unknownTriggerPlaceholder');
        },

        ...mapState(
            () => Store.get('swFlow'),
            [
                'flow',
                'triggerEvents',
                'isSequenceEmpty',
            ],
        ),
        ...mapPropertyErrors('flow', ['eventName']),
    },

    watch: {
        eventName: {
            immediate: true,
            handler(value) {
                if (!value) {
                    return;
                }

                this.$route.params.eventName = value;
                this.searchTerm = this.getEventName(value);
            },
        },

        searchTerm(value) {
            if (!value || value === this.formatEventName) {
                return;
            }

            const keyWords = value.split(/[\W_]+/gi);

            this.searchResult = this.triggerEvents.filter((event) => {
                const eventName = this.getEventName(event.name).toLowerCase();

                return keyWords.every((key) => eventName.includes(key.toLowerCase()));
            });

            // set first item as focus
            if (this.searchResult.length > 0) {
                this.searchResultFocusItem = this.searchResult[0];
            }
        },

        selectedTreeItem(newValue) {
            if (newValue?.id) {
                utils.debounce(() => {
                    const newElement = this.findTreeItemVNodeById(newValue.id).$el;
                    if (!newElement) {
                        return;
                    }
                    let offsetValue = 0;
                    let foundTreeRoot = false;
                    let actualElement = newElement;

                    while (!foundTreeRoot) {
                        if (!actualElement) {
                            break;
                        }

                        if (actualElement.classList.contains('sw-tree__content')) {
                            foundTreeRoot = true;
                        } else {
                            offsetValue += actualElement.offsetTop;
                            actualElement = actualElement.offsetParent;
                        }
                    }

                    actualElement?.scrollTo({
                        top: offsetValue - actualElement.clientHeight / 2 - 50,
                        behavior: 'smooth',
                    });
                }, 50)();
            }
        },
    },

    created() {
        this.createdComponent();
    },

    beforeUnmount() {
        this.beforeDestroyComponent();
    },

    methods: {
        createdComponent() {
            document.addEventListener('click', this.handleClickEvent);
            document.addEventListener('keydown', this.handleGeneralKeyEvents);

            this.isLoading = true;
            Store.get('swFlow').fetchTriggerActions();
            Store.get('swFlow').triggerEvent = this.getDataByEvent(this.eventName);
            Store.get('swFlow').restrictedRules = this.eventName;

            this.isLoading = false;
        },

        beforeDestroyComponent() {
            document.removeEventListener('click', this.handleClickEvent);
            document.removeEventListener('keydown', this.handleGeneralKeyEvents);
        },

        handleClickEvent(event) {
            const target = event.target;

            if (target.closest('.sw-tree-item .sw-tree-item__toggle')) {
                const selectedElement = target.closest('.sw-tree-item');

                selectedElement?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center',
                });

                return;
            }

            if (target.closest('.sw-tree-item .is--no-children.is--disabled')) {
                return;
            }

            if (
                target.closest('.sw-tree-item .is--no-children .sw-tree-item__content') ||
                target.closest('.sw-flow-trigger__search-result')
            ) {
                this.closeDropdown();
                return;
            }

            if (target.closest('.sw-flow-trigger') === null) {
                if (target.closest('svg')) {
                    return;
                }

                this.closeDropdown();

                if (this.searchTerm !== this.formatEventName) {
                    this.searchTerm = this.formatEventName;
                }
            }
        },

        handleGeneralKeyEvents(event) {
            if (event.type !== 'keydown' || !this.isExpanded) {
                return;
            }

            const key = event.key.toLowerCase();

            switch (key) {
                case 'tab':
                case 'escape': {
                    this.closeDropdown();
                    break;
                }

                case 'arrowdown':
                case 'arrowleft':
                case 'arrowright':
                case 'arrowup': {
                    this.handleArrowKeyEvents(event);
                    break;
                }

                case 'enter': {
                    // when user is searching
                    if (this.searchTerm.length > 0 && this.searchTerm !== this.formatEventName) {
                        this.onClickSearchItem(this.searchResultFocusItem);
                        this.closeDropdown();
                    } else {
                        if (this.selectedTreeItem?.childCount > 0) {
                            return;
                        }

                        this.changeTrigger(this.selectedTreeItem);
                        this.closeDropdown();
                    }

                    break;
                }

                default: {
                    break;
                }
            }
        },

        handleArrowKeyEvents(event) {
            const key = event.key.toLowerCase();

            // when user is searching
            if (this.searchTerm.length > 0 && this.searchTerm !== this.formatEventName) {
                switch (key) {
                    case 'arrowdown': {
                        event.preventDefault();
                        this.changeSearchSelection('next');
                        break;
                    }

                    case 'arrowup': {
                        event.preventDefault();
                        this.changeSearchSelection('previous');
                        break;
                    }

                    default: {
                        break;
                    }
                }
                return;
            }

            // when user has tree open
            const actualSelection = this.findTreeItemVNodeById();

            const actualSelectionItem = actualSelection?.component?.proxy?.item;

            switch (key) {
                case 'arrowdown': {
                    // check if actual selection was found
                    if (actualSelectionItem?.id) {
                        const actualSelectionOpened = actualSelection?.component?.proxy?.opened;

                        // when selection is open
                        if (actualSelectionOpened) {
                            // get first item of child
                            const newSelection = this.getFirstChildById(actualSelectionItem?.id);
                            if (newSelection) {
                                // update the selected item
                                this.selectedTreeItem = newSelection;
                            }
                            break;
                        }

                        // when selection is not open then get the next sibling
                        let newSelection = this.getSibling(true, actualSelectionItem);
                        // when next sibling exists
                        if (newSelection) {
                            // update the selected item
                            this.selectedTreeItem = newSelection;
                            break;
                        }

                        // Get the closest visible ancestor to actual section's position.
                        newSelection = this.getClosestSiblingAncestor(actualSelectionItem?.parentId);
                        // when next parent exists
                        if (newSelection) {
                            // update the selected item
                            this.selectedTreeItem = newSelection;
                            break;
                        }
                    }
                    break;
                }

                case 'arrowup': {
                    // check if actual selection was found
                    if (actualSelectionItem?.id) {
                        // when selection is first item in folder
                        const parent = this.findTreeItemVNodeById(actualSelectionItem?.parentId);

                        const parentItemFirstChildrenId = parent?.component?.proxy?.item?.children[0].id;
                        if (parentItemFirstChildrenId === actualSelectionItem?.id) {
                            // then get the parent folder
                            const newSelection = parent.component.proxy.item;

                            if (newSelection) {
                                // update the selected item
                                this.selectedTreeItem = newSelection;
                            }
                            break;
                        }

                        // when selection is not first item then get the previous sibling
                        const newSelection = this.getSibling(false, actualSelectionItem);
                        if (newSelection) {
                            // Get the closest visible sibling's descendant to actual selection's position
                            this.selectedTreeItem = this.getClosestSiblingDescendant(newSelection);
                        }
                    }
                    break;
                }

                case 'arrowright': {
                    this.toggleSelectedTreeItem(true);
                    break;
                }

                case 'arrowleft': {
                    const isClosed = !this.toggleSelectedTreeItem(false);

                    // when selection is an item or a closed folder
                    if (isClosed) {
                        // change the selection to the parent
                        const parent = this.findTreeItemVNodeById(actualSelectionItem?.parentId);

                        if (parent) {
                            const parentItem = parent.component.proxy.item;
                            this.selectedTreeItem = parentItem;
                        }
                    }

                    break;
                }

                default: {
                    break;
                }
            }
        },

        getClosestSiblingAncestor(parentId) {
            // when sibling does not exist, go to next parent sibling
            const parent = this.findTreeItemVNodeById(parentId);
            const nextParent = this.getSibling(true, parent.item);
            if (nextParent) {
                return nextParent;
            }

            const parentItemParentId = parent?.component?.proxy?.item?.parentId;

            if (!parentItemParentId) {
                return null;
            }

            return this.getClosestSiblingAncestor(parentItemParentId);
        },

        getClosestSiblingDescendant(item) {
            const foundItemNode = this.findTreeItemVNodeById(item.id);

            if (foundItemNode.opened && foundItemNode.item.childCount > 0) {
                const lastChildIndex = foundItemNode.item.children.length - 1;
                const lastChild = foundItemNode.item.children[lastChildIndex];

                if (lastChild.childCount === 0) {
                    return lastChild;
                }

                return this.getClosestSiblingDescendant(lastChild);
            }

            return item;
        },

        getFirstChildById(itemId, children = this.$refs.flowTriggerTree.treeItems) {
            const foundItem = children.find((child) => child.id === itemId);

            if (foundItem) {
                // return first child
                return foundItem.children[0];
            }

            for (let i = 0; i < children.length; i += 1) {
                const foundItemInChild = this.getFirstChildById(itemId, children[i].children);

                if (foundItemInChild) {
                    return foundItemInChild;
                }
            }

            return null;
        },

        getSibling(isNext, item, children = this.$refs.flowTriggerTree.treeItems) {
            // when no item exists
            if (!item) {
                return null;
            }

            let foundItem = null;
            const itemIndex = children.indexOf(item);

            if (itemIndex < 0) {
                foundItem = null;
            } else {
                foundItem = isNext ? children[itemIndex + 1] : children[itemIndex - 1];
            }

            if (foundItem) {
                return foundItem;
            }

            for (let i = 0; i < children.length; i += 1) {
                const foundItemInChild = this.getSibling(isNext, item, children[i].children);

                if (foundItemInChild) {
                    return foundItemInChild;
                }
            }

            return null;
        },

        changeSearchSelection(type = 'next') {
            const typeValue = type === 'previous' ? -1 : 1;

            const actualIndex = this.searchResult.indexOf(this.searchResultFocusItem);
            const focusItem = this.searchResult[actualIndex + typeValue];

            if (typeof focusItem !== 'undefined') {
                this.searchResultFocusItem = focusItem;
            }
        },

        toggleSelectedTreeItem(shouldOpen) {
            const vnode = this.findTreeItemVNodeById();

            if (vnode?.component?.proxy?.openTreeItem && vnode?.component?.proxy?.opened !== shouldOpen) {
                vnode.component.proxy.openTreeItem();
                return true;
            }

            return false;
        },

        findTreeItemVNodeById(
            itemId = this.selectedTreeItem.id,
            children = this.$refs.flowTriggerTree?.$?.subTree?.children,
        ) {
            let found = false;
            if (!children) {
                return found;
            }

            if (Array.isArray(children)) {
                found = children.find((child) => {
                    if (child.component?.proxy?.item?.id) {
                        return child.component?.proxy?.item?.id === itemId;
                    }

                    return false;
                });
            } else if (children.component?.proxy?.item?.id) {
                found = children.component?.proxy?.item?.id === itemId;
            }

            if (found) {
                return found;
            }

            let foundInChildren = false;

            // recursion to find vnode
            for (let i = 0; i < children.length; i += 1) {
                if (!children[i]) {
                    // eslint-disable-next-line no-continue
                    continue;
                }

                const childrenToIterate = children[i].component
                    ? children[i].component?.subTree?.children
                    : children[i].children;
                foundInChildren = this.findTreeItemVNodeById(itemId, childrenToIterate ?? null);
                // stop when found in children
                if (foundInChildren) {
                    break;
                }
            }

            return foundInChildren;
        },

        openDropdown({ setFocusClass, removeFocusClass }) {
            // make functions available
            this.setInputFocusClass = setFocusClass;
            this.removeInputFocusClass = removeFocusClass;

            this.setInputFocusClass();
            this.isExpanded = true;

            if (this.isLoading) {
                return;
            }

            // set first item or selected event as focus
            this.$nextTick().then(() => {
                if (this.searchTerm === this.formatEventName) {
                    const currentEvent = this.eventTree.find((event) => event.id === this.eventName);
                    this.selectedTreeItem = currentEvent || this.eventTree[0];
                }
            });
        },

        closeDropdown() {
            if (this.removeInputFocusClass) {
                this.removeInputFocusClass();
            }

            this.isExpanded = false;
        },

        changeTrigger(item) {
            if (item?.disabled || item?.childCount > 0) {
                return;
            }

            if (this.isSequenceEmpty) {
                const { id } = item.data;

                Store.get('swFlow').triggerEvent = this.getDataByEvent(id);
                Store.get('swFlow').restrictedRules = id;
                this.$emit('option-select', id);
            } else {
                this.showConfirmModal = this.flow.eventName !== item.id;
                this.triggerSelect = this.getDataByEvent(item.id);
            }
        },

        onConfirm() {
            Store.get('swFlow').triggerEvent = this.triggerSelect;
            Store.get('swFlow').restrictedRules = this.triggerSelect.name;
            this.$emit('option-select', this.triggerSelect.name);
        },

        onCloseConfirm() {
            this.showConfirmModal = false;
            this.triggerSelect = {};
        },

        getLastEventName({ parentId = null, id }) {
            const [eventName] = parentId ? id.split('.').reverse() : [id];

            return this.getEventNameTranslated(eventName);
        },

        getDataByEvent(event) {
            return this.triggerEvents.find((item) => item.name === event);
        },

        hasOnlyStopFlow(event) {
            const eventAware = this.triggerEvents.find((item) => item.name === event).aware || [];
            return eventAware.length === 0;
        },

        // Generate tree data which is compatible with sw-tree from business events
        getEventTree(events) {
            const mappedObj = {};

            events.forEach((event) => {
                // Split event name by '.'
                const eventNameKeys = event.name.split('.');
                if (eventNameKeys.length === 0) {
                    return;
                }

                /*
                 Group children to parent based on event names.
                 For instance, if event name is 'checkout.customer.deleted',
                 it's considered that customer is checkout's child and deleted is customer's child.
                */
                const generateTreeData = (currentIndex, keyWords, result) => {
                    const currentKey = keyWords[currentIndex];

                    // next key is child of current key
                    const nextKey = keyWords[currentIndex + 1];

                    result[currentKey] = result[currentKey] || {
                        id: currentKey,
                        parentId: null,
                        children: {},
                    };

                    if (!nextKey) {
                        return;
                    }

                    // Put next key into children of current key
                    result[currentKey].children[nextKey] = result[currentKey].children[nextKey] || {
                        id: `${result[currentKey].id}.${nextKey}`,
                        parentId: result[currentKey].id,
                        children: {},
                    };

                    generateTreeData(currentIndex + 1, keyWords, result[currentKey].children);
                };

                generateTreeData(0, eventNameKeys, mappedObj);
            });

            // Convert tree object to array to work with sw-tree
            const convertTreeToArray = (nodes, output = []) => {
                nodes.forEach((node) => {
                    const children = node.children ? Object.values(node.children) : [];
                    output.push({
                        id: node.id,
                        name: this.getLastEventName(node),
                        childCount: children.length,
                        parentId: node.parentId,
                        disabled: isEmpty(node.children) && this.hasOnlyStopFlow(node.id),
                        disabledToolTipText:
                            isEmpty(node.children) && this.hasOnlyStopFlow(node.id)
                                ? this.$tc('sw-flow.detail.trigger.textHint')
                                : null,
                    });

                    if (children.length > 0) {
                        output = convertTreeToArray(children, output);
                    }
                });
                return output;
            };

            return convertTreeToArray(Object.values(mappedObj));
        },

        getBreadcrumb(eventName) {
            if (!eventName) {
                return '';
            }

            const keyWords = eventName.split('.');

            return keyWords
                .map((key) => {
                    return capitalizeString(key);
                })
                .join(' / ')
                .replace(/_|-/g, ' ');
        },

        onClickSearchItem(item) {
            this.searchTerm = this.formatEventName;
            this.searchResult = [];

            if (this.isSequenceEmpty) {
                this.$emit('option-select', item.name);
                Store.get('swFlow').triggerEvent = item;
                Store.get('swFlow').restrictedRules = item.name;
            } else {
                this.showConfirmModal = true;
                this.triggerSelect = item;
            }
        },

        getEventName(eventName) {
            if (this.isUnknownTrigger) {
                return '';
            }

            if (!eventName) {
                return eventName;
            }

            const keyWords = eventName.split('.');

            return keyWords
                .map((key) => {
                    return this.getEventNameTranslated(key);
                })
                .join(' / ');
        },

        isSearchResultInFocus(item) {
            return item.name === this.searchResultFocusItem.name;
        },

        getEventNameTranslated(eventName) {
            const eventNameCamelCase = camelCase(eventName);
            const translatedEventName = [
                `sw-flow-app.triggers-app.${eventNameCamelCase}`,
                `sw-flow-custom-event.event-tree.${eventNameCamelCase}`,
                `sw-flow.triggers.${eventNameCamelCase}`,
            ].find((key) => this.$te(key));

            return translatedEventName ? this.$tc(translatedEventName) : eventName.replace(/_|-/g, ' ');
        },
    },
};
