import { test } from '@fixtures/AcceptanceTest';

// Annotate entire file as serial run.
test.describe.configure({ mode: 'serial' });

test('As a shop customer, I want use a basic cookie consent banner in storefront.', { tag: '@Settings' }, async ({
    ShopCustomer,
    StorefrontHome,
}) => {

    await ShopCustomer.goesTo(StorefrontHome.url());
    await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).toBeVisible();
    await ShopCustomer.expects(StorefrontHome.consentAcceptAllCookiesButton).not.toBeVisible();
    await ShopCustomer.expects(StorefrontHome.consentOnlyTechnicallyRequiredButton).toBeVisible();
    await ShopCustomer.expects(StorefrontHome.consentConfigureButton).toBeVisible();
    await ShopCustomer.expects(StorefrontHome.consentCookiePermissionContent).toContainText('This website uses cookies to ensure the best experience possible.');

    await StorefrontHome.consentConfigureButton.click();
    await ShopCustomer.expects(StorefrontHome.consentDialogTechnicallyRequiredCheckbox).toBeChecked();
    await StorefrontHome.consentDialogSaveButton.click();

    await StorefrontHome.page.reload();
    await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).not.toBeVisible();

});

test('As a shop customer, I want to use a accept all cookies button in the basic cookie consent banner in storefront.', { tag: '@Settings' }, async ({
    ShopCustomer,
    StorefrontHome,
    TestDataService,
    DefaultSalesChannel,
}) => {

    await TestDataService.createSystemConfigEntry('core.basicInformation.acceptAllCookies', true, DefaultSalesChannel.salesChannel.id);

    await ShopCustomer.goesTo(StorefrontHome.url());
    await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).toBeVisible();
    await ShopCustomer.expects(StorefrontHome.consentAcceptAllCookiesButton).toBeVisible();
    await StorefrontHome.consentAcceptAllCookiesButton.click();

    await StorefrontHome.page.reload();
    await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).not.toBeVisible();
});

test('As a shop customer, I want to continue shopping without accepting the cookies in storefront.', { tag: '@Settings' }, async ({
    ShopCustomer,
    StorefrontHome,
    TestDataService,
    DefaultSalesChannel,
}) => {

    await TestDataService.createSystemConfigEntry('core.basicInformation.acceptAllCookies', true, DefaultSalesChannel.salesChannel.id);
    const product = await TestDataService.createBasicProduct();
    const category = await TestDataService.createCategory();
    await TestDataService.assignProductCategory(product.id, category.id);
    const productListItemLocators = await StorefrontHome.getListingItemByProductId(product.id);

    await ShopCustomer.goesTo(StorefrontHome.url());
    await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).toBeVisible();
    await ShopCustomer.expects(StorefrontHome.consentAcceptAllCookiesButton).toBeVisible();
    await StorefrontHome.consentConfigureButton.click();
    await StorefrontHome.offcanvasBackdrop.click();
    await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).not.toBeVisible();

    await productListItemLocators.productImage.click();
    await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).toBeVisible();
});
