import template from './sw-flow-detail-executions.html.twig';
import './sw-flow-detail-executions.scss';

const { Mixin, Data: { Criteria }, Component } = Shopware;
const { mapState, mapGetters } = Component.getComponentHelper();

/**
 * @private
 * @package services-settings
 */
export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['acl', 'flowBuilderService', 'repositoryFactory'],

    emits: ['on-update-total'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('listing'),
    ],

    props: {
        searchTerm: {
            type: String,
            required: false,
            default: '',
        },
    },

    data() {
        return {
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            total: 0,
            isLoading: false,
            flowExecutions: null,
            selectedItems: [],
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        flowExecutionRepository() {
            return this.repositoryFactory.create('flow_execution');
        },

        flowExecutionCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            if (this.searchTerm) {
                criteria.setTerm(this.searchTerm);
            }

            criteria
                .addSorting(Criteria.sort(this.sortBy, this.sortDirection))
                .addSorting(Criteria.sort('updatedAt', 'DESC'));

            criteria
                .addFilter(Criteria.equals('flow.id', this.flow.id));

            criteria
                .addAssociation('failedFlowSequence');

            return criteria;
        },

        flowExecutionColumns() {
            return [
                {
                    property: 'date',
                    label: this.$tc('sw-flow.detail.executions.list.labelColumnDate'),
                    sortable: true,
                },
                {
                    property: 'successful',
                    label: this.$tc('sw-flow.detail.executions.list.labelColumnSuccessful'),
                    width: '120px',
                    align: 'center',
                },
                {
                    property: 'failedActionName',
                    label: this.$tc('sw-flow.detail.executions.list.labelColumnFailedAction'),
                    sortable: false,
                },
                {
                    property: 'errorMessage',
                    label: this.$tc('sw-flow.detail.executions.list.labelColumnErrorMessage'),
                    multiLine: true,
                    sortable: false,
                },
            ];
        },

        detailPageLinkText() {
            return this.$tc('global.default.view');
        },

        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },

        ...mapState('swFlowState', ['flow']),
        ...mapGetters(
            'swFlowState',
            [
                'getSelectedAppAction',
            ],
        ),
    },

    watch: {
        searchTerm(value) {
            this.onSearch(value);
        },
    },

    created() {
        this.createComponent();
    },

    methods: {
        createComponent() {
            this.getList();
        },

        getList() {
            this.isLoading = true;

            this.flowExecutionRepository.search(this.flowExecutionCriteria)
                .then((data) => {
                    this.total = data.total;
                    this.flowExecutions = data;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        getActionName(failedFlowSequence) {
            if (!failedFlowSequence) {
                return '';
            }

            const failedActionName = this.getSelectedAppAction(failedFlowSequence.actionName)?.label;

            if (failedActionName !== undefined) {
                return failedActionName;
            }

            const actionTitle = this.flowBuilderService.getActionTitle(failedFlowSequence.actionName);

            if (actionTitle !== null) {
                return this.$tc(actionTitle.label);
            }

            return '';
        },

        selectionChange(selection) {
            this.selectedItems = Object.values(selection);
        },

        onHighlightFailedSequence(failedFlowSequenceId) {
            this.$router.push({
                name: 'sw.flow.detail.flow',
                params: { id: this.flow.id },
                query: { failedFlowSequenceId },
            });
        },
    },
};
