import { Component, Mixin } from 'src/core/shopware';
import template from './sw-settings-country-detail.html.twig';
import './sw-settings-country-detail.scss';

Component.register('sw-settings-country-detail', {
    template,

    inject: ['repositoryFactory', 'context'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('discard-detail-page-changes')('country')
    ],

    data() {
        return {
            country: {},
            term: null,
            countryId: null,
            isLoading: false,
            currentCountryState: null,
            countryStateRepository: null,
            countryStateLoading: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        countryRepository() {
            return this.repositoryFactory.create('country');
        },
        identifier() {
            return this.placeholder(this.country, 'name');
        },
        stateColumns() {
            return this.getStateColumns();
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.countryId = this.$route.params.id;
                this.loadEntityData();
            }
        },

        loadEntityData() {
            this.isLoading = true;
            this.countryRepository.get(this.countryId, this.context).then(country => {
                this.country = country;

                this.isLoading = false;

                this.countryStateRepository = this.repositoryFactory.create(
                    this.country.states.entity,
                    this.country.states.source
                );
            });
        },

        onSave() {
            const countryName = this.country.name || this.country.translated.name;
            const titleSaveSuccess = this.$tc('sw-settings-country.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc('sw-settings-country.detail.messageSaveSuccess', 0, {
                name: countryName
            });

            return this.countryRepository.save(this.country, this.context).then(() => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
            });
        },

        onAddCountryState() {
            this.currentCountryState = this.countryStateRepository.create(this.context);
        },

        onSearchCountryState() {
            this.country.states.criteria.setTerm(this.term);
            this.refreshCountryStateList();
        },

        refreshCountryStateList() {
            this.countryStateLoading = true;

            this.$refs.countryStateGrid.load().then(() => {
                this.countryStateLoading = false;
            });
        },

        onSaveCountryState() {
            // Reset the isNew property if the item already exists in the collection
            if (this.country.states.has(this.currentCountryState.id)) {
                this.currentCountryState._isNew = false;
            }

            // dont send requests if we are on local mode(creating a new country)
            if (this.country.isNew()) {
                this.country.states.add(this.currentCountryState);
            } else {
                this.countryStateRepository.save(this.currentCountryState, this.context).then(() => {
                    this.refreshCountryStateList();
                });
            }

            this.currentCountryState = null;
        },

        onCancelCountryState() {
            this.currentCountryState = null;
        },

        onClickCountryState(item) {
            // Create a copy with the same id which will be edited
            const copy = this.countryStateRepository.create(this.context, item.id);

            this.currentCountryState = Object.assign(copy, item);
        },

        abortOnLanguageChange() {
            return this.countryRepository.hasChanges(this.country);
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage() {
            this.loadEntityData();
        },

        getStateColumns() {
            return [{
                property: 'name',
                dataIndex: 'name',
                label: 'Name',
                inlineEdit: 'string',
                primary: true
            }, {
                property: 'shortCode',
                dataIndex: 'shortCode',
                label: 'Kürzel',
                inlineEdit: 'string'
            }];
        }
    }
});
