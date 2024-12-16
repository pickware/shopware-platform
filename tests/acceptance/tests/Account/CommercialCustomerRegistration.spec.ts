import { test } from '@fixtures/AcceptanceTest';

test('As a new customer, I must be able to register as a commercial customer in the Storefront.', { tag: '@Registration' }, async ({
    ShopCustomer,
    StorefrontAccountLogin,
    StorefrontAccount,
    IdProvider,
    Register,
    SetSystemConfigValues,
}) => {
    const uuid = IdProvider.getIdPair().uuid;
    const customer = { email: uuid + '@test.com', vatRegNo: uuid + '-VatId' };
    const setAccountType = 'core.loginRegistration.showAccountTypeSelection';
    await ShopCustomer.attemptsTo(SetSystemConfigValues({ [setAccountType]: true }, { [setAccountType]: false }));

    await ShopCustomer.goesTo(StorefrontAccountLogin.url());
    await StorefrontAccountLogin.accountTypeSelect.selectOption('Commercial');
    await ShopCustomer.attemptsTo(Register(customer, true));
    await ShopCustomer.expects(StorefrontAccount.page.getByText(customer.email, { exact: true })).toBeVisible();
    await ShopCustomer.expects(StorefrontAccount.page.getByText('shopware - Operations VAT Reg')).toBeVisible();
    await ShopCustomer.expects(StorefrontAccount.page.getByText('shopware - Operations VAT Reg')).toContainText(customer.vatRegNo);

});
