/**
 * @sw-package framework
 */

import getErrorCode from 'src/core/data/error-codes/login.error-codes';
import template from './sw-login-login.html.twig';

const { Component, Mixin } = Shopware;

/**
 * @private
 */
Component.register('sw-login-login', {
    template,

    inject: [
        'loginService',
        'userService',
        'licenseViolationService',
    ],

    emits: [
        'is-loading',
        'is-not-loading',
        'login-success',
        'login-error',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            username: '',
            password: '',
            rememberMe: false,
            loginAlertMessage: '',
        };
    },

    computed: {
        showLoginAlert() {
            return typeof this.loginAlertMessage === 'string' && this.loginAlertMessage.length >= 1;
        },
    },

    created() {
        if (!localStorage.getItem('sw-admin-locale')) {
            Shopware.Store.get('session').setAdminLocale(navigator.language);
        }
    },

    methods: {
        loginUserWithPassword() {
            this.$emit('is-loading');

            this.loginService.setRememberMe(this.rememberMe);

            return this.loginService
                .loginByUsername(this.username, this.password)
                .then(() => {
                    this.handleLoginSuccess();
                    this.$emit('is-not-loading');
                })
                .catch((response) => {
                    this.password = '';

                    this.handleLoginError(response);
                    this.$emit('is-not-loading');
                });
        },

        handleLoginSuccess() {
            this.password = '';

            this.$emit('login-success');

            const animationPromise = new Promise((resolve) => {
                setTimeout(resolve, 150);
            });

            if (this.licenseViolationService) {
                this.licenseViolationService.removeTimeFromLocalStorage(this.licenseViolationService.key.showViolationsKey);
            }

            return animationPromise.then(() => {
                this.$parent.isLoginSuccess = false;
                this.forwardLogin();

                const shouldReload = sessionStorage.getItem('sw-login-should-reload');

                if (shouldReload) {
                    sessionStorage.removeItem('sw-login-should-reload');
                    // reload page to rebuild the administration with all dependencies
                    window.location.reload(true);
                }
            });
        },

        forwardLogin() {
            const previousRoute = JSON.parse(sessionStorage.getItem('sw-admin-previous-route'));
            sessionStorage.removeItem('sw-admin-previous-route');

            const firstRunWizard = Shopware.Context.app.firstRunWizard;

            if (
                firstRunWizard &&
                !this.$router?.currentRoute?.value?.name?.startsWith('sw.first.run.wizard') &&
                this.$router.hasRoute('sw.first.run.wizard.index')
            ) {
                this.$router.push({ name: 'sw.first.run.wizard.index' });
                return;
            }

            if (previousRoute?.fullPath) {
                this.$router.push(previousRoute.fullPath);
                return;
            }

            this.$router.push({ name: 'core' });
        },

        handleLoginError(response) {
            this.password = '';

            this.$emit('login-error');
            setTimeout(() => {
                this.$emit('login-error');
            }, 500);

            this.createNotificationFromResponse(response);
        },

        createNotificationFromResponse(response) {
            if (!response.response) {
                this.createNotificationError({
                    message: this.$tc('sw-login.index.messageGeneralRequestError'),
                });
                return;
            }

            const url = response.config.url;
            let error = response.response.data.errors;
            error = Array.isArray(error) ? error[0] : error;

            if (parseInt(error.status, 10) === 429) {
                const seconds = error?.meta?.parameters?.seconds;
                this.loginAlertMessage = this.$tc('sw-login.index.messageAuthThrottled', { seconds }, 0);

                setTimeout(() => {
                    this.loginAlertMessage = '';
                }, seconds * 1000);
                return;
            }

            if (error.code?.length) {
                const { message, title } = getErrorCode(parseInt(error.code, 10));

                this.createNotificationError({
                    title: this.$tc(title),
                    message: this.$tc(message, 0, { url }),
                });
            }
        },
    },
});
