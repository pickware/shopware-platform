<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Exception\CustomerAlreadyConfirmedException;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundByHashException;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractRegisterConfirmRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractRegisterRoute;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\QueryDataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\Exception\StorefrontException;
use Shopware\Storefront\Framework\AffiliateTracking\AffiliateTrackingListener;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Page\Account\CustomerGroupRegistration\AbstractCustomerGroupRegistrationPageLoader;
use Shopware\Storefront\Page\Account\CustomerGroupRegistration\CustomerGroupRegistrationPageLoadedHook;
use Shopware\Storefront\Page\Account\Login\AccountLoginPageLoader;
use Shopware\Storefront\Page\Account\Register\AccountRegisterPageLoadedHook;
use Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPageLoadedHook;
use Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPageLoader;
use Shopware\Storefront\Pagelet\Footer\FooterPageletLoaderInterface;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoaderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
#[Package('checkout')]
class RegisterController extends StorefrontController
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomerCollection> $customerRepository
     * @param EntityRepository<SalesChannelDomainCollection> $domainRepository
     */
    public function __construct(
        private readonly AccountLoginPageLoader $loginPageLoader,
        private readonly AbstractRegisterRoute $registerRoute,
        private readonly AbstractRegisterConfirmRoute $registerConfirmRoute,
        private readonly CartService $cartService,
        private readonly CheckoutRegisterPageLoader $registerPageLoader,
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $customerRepository,
        private readonly AbstractCustomerGroupRegistrationPageLoader $customerGroupRegistrationPageLoader,
        private readonly EntityRepository $domainRepository,
        private readonly HeaderPageletLoaderInterface $headerPageletLoader,
        private readonly FooterPageletLoaderInterface $footerPageletLoader,
    ) {
    }

    #[Route(path: '/account/register', name: 'frontend.account.register.page', defaults: ['_noStore' => true], methods: ['GET'])]
    public function accountRegisterPage(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        if ($context->getCustomer() && $context->getCustomer()->getGuest()) {
            return $this->redirectToRoute('frontend.account.logout.page');
        }

        if ($context->getCustomer()) {
            return $this->redirectToRoute('frontend.account.home.page');
        }

        // Add '_httpCache' => true, to defaults in Route and remove _noStore
        if (Feature::isActive('PERFORMANCE_TWEAKS') || Feature::isActive('v6.8.0.0')) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, true);
            $request->attributes->remove(PlatformRequest::ATTRIBUTE_NO_STORE);
        }

        $redirect = $request->query->get('redirectTo', 'frontend.account.home.page');
        $errorRoute = $request->attributes->get('_route');

        $page = $this->loginPageLoader->load($request, $context);

        $this->hook(new AccountRegisterPageLoadedHook($page, $context));

        return $this->renderStorefront('@Storefront/storefront/page/account/register/index.html.twig', [
            'redirectTo' => $redirect,
            'redirectParameters' => $request->get('redirectParameters', json_encode([], \JSON_THROW_ON_ERROR)),
            'errorRoute' => $errorRoute,
            'page' => $page,
            'data' => $data,
        ]);
    }

    #[Route(path: '/customer-group-registration/{customerGroupId}', name: 'frontend.account.customer-group-registration.page', defaults: ['_noStore' => true], methods: ['GET'])]
    public function customerGroupRegistration(string $customerGroupId, Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        if ($context->getCustomer() && $context->getCustomer()->getGuest()) {
            return $this->redirectToRoute('frontend.account.logout.page');
        }

        if ($context->getCustomer()) {
            return $this->redirectToRoute('frontend.account.home.page');
        }

        // Add '_httpCache' => true, to defaults in Route and remove _noStore
        if (Feature::isActive('PERFORMANCE_TWEAKS') || Feature::isActive('v6.8.0.0')) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, true);
            $request->attributes->remove(PlatformRequest::ATTRIBUTE_NO_STORE);
        }

        $redirect = $request->query->get('redirectTo', 'frontend.account.home.page');

        $page = $this->customerGroupRegistrationPageLoader->load($request, $context);

        if ($page->getGroup()->getTranslation('registrationOnlyCompanyRegistration')) {
            $data->set('accountType', CustomerEntity::ACCOUNT_TYPE_BUSINESS);
        }

        $this->hook(new CustomerGroupRegistrationPageLoadedHook($page, $context));

        return $this->renderStorefront('@Storefront/storefront/page/account/customer-group-register/index.html.twig', [
            'redirectTo' => $redirect,
            'redirectParameters' => $request->get('redirectParameters', json_encode([], \JSON_THROW_ON_ERROR)),
            'errorRoute' => $request->attributes->get('_route'),
            'errorParameters' => json_encode(['customerGroupId' => $customerGroupId], \JSON_THROW_ON_ERROR),
            'page' => $page,
            'data' => $data,
        ]);
    }

    #[Route(path: '/checkout/register', name: 'frontend.checkout.register.page', options: ['seo' => false], defaults: ['_noStore' => true], methods: ['GET'])]
    public function checkoutRegisterPage(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        $redirect = $request->get('redirectTo', 'frontend.checkout.confirm.page');
        \assert(\is_string($redirect));
        $errorRoute = $request->attributes->get('_route');

        if ($context->getCustomer()) {
            return $this->redirectToRoute($redirect);
        }

        if ($this->cartService->getCart($context->getToken(), $context)->getLineItems()->count() === 0) {
            return $this->redirectToRoute('frontend.checkout.cart.page');
        }

        $page = $this->registerPageLoader->load($request, $context);

        $this->hook(new CheckoutRegisterPageLoadedHook($page, $context));

        $header = $this->headerPageletLoader->load($request, $context);
        $footer = $this->footerPageletLoader->load($request, $context);

        return $this->renderStorefront(
            '@Storefront/storefront/page/checkout/address/index.html.twig',
            [
                'redirectTo' => $redirect,
                'errorRoute' => $errorRoute,
                'page' => $page,
                'header' => $header,
                'footer' => $footer,
                'data' => $data,
            ]
        );
    }

    #[Route(path: '/account/register', name: 'frontend.account.register.save', defaults: ['_captcha' => true], methods: ['POST'])]
    public function register(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        if ($context->getCustomer()) {
            return $this->redirectToRoute('frontend.account.home.page');
        }

        try {
            if (!$data->has('differentShippingAddress')) {
                $data->remove('shippingAddress');
            }

            $data->set('storefrontUrl', $this->getConfirmUrl($context, $request));
            $data = $this->prepareAffiliateTracking($data, $request->getSession());
            $data->set('guest', !$data->getBoolean('createCustomerAccount'));

            $this->registerRoute->register(
                $data->toRequestDataBag(),
                $context,
                false,
                $this->getAdditionalRegisterValidationDefinitions($data, $context)
            );
        } catch (ConstraintViolationException $formViolations) {
            if (!$request->request->has('errorRoute')) {
                throw RoutingException::missingRequestParameter('errorRoute');
            }

            if (empty($request->request->get('errorRoute'))) {
                $request->request->set('errorRoute', 'frontend.account.register.page');
            }

            $params = $this->decodeParam($request, 'errorParameters');

            // this is to show the correct form because we have different use-cases (account/register||checkout/register)
            return $this->forwardToRoute($request->get('errorRoute'), ['formViolations' => $formViolations], $params);
        }

        if ($this->isDoubleOptIn($data, $context)) {
            return $this->redirectToRoute('frontend.account.register.page');
        }

        return $this->createActionResponse($request);
    }

    #[Route(path: '/registration/confirm', name: 'frontend.account.register.mail', methods: ['GET'])]
    public function confirmRegistration(SalesChannelContext $context, QueryDataBag $queryDataBag): Response
    {
        try {
            $customerId = $this->registerConfirmRoute
                ->confirm($queryDataBag->toRequestDataBag(), $context)
                ->getCustomer()
                ->getId();
        } catch (CustomerNotFoundByHashException|CustomerAlreadyConfirmedException|ConstraintViolationException) {
            $this->addFlash(self::DANGER, $this->trans('account.confirmationIsAlreadyDone'));

            return $this->redirectToRoute('frontend.account.register.page');
        }

        $customer = $this->customerRepository->search(new Criteria([$customerId]), $context->getContext())->getEntities()->first();
        \assert($customer !== null);

        if ($customer->getGuest()) {
            $this->addFlash(self::SUCCESS, $this->trans('account.doubleOptInMailConfirmationSuccessfully'));

            return $this->redirectToRoute('frontend.checkout.confirm.page');
        }

        $this->addFlash(self::SUCCESS, $this->trans('account.doubleOptInRegistrationSuccessfully'));

        if ($redirectTo = $queryDataBag->get('redirectTo')) {
            $parameters = $queryDataBag->all();
            unset($parameters['em'], $parameters['hash'], $parameters['redirectTo']);

            return $this->redirectToRoute($redirectTo, $parameters);
        }

        return $this->redirectToRoute('frontend.account.home.page');
    }

    private function isDoubleOptIn(DataBag $data, SalesChannelContext $context): bool
    {
        $createCustomerAccount = $data->getBoolean('createCustomerAccount');

        $configKey = $createCustomerAccount
            ? 'core.loginRegistration.doubleOptInRegistration'
            : 'core.loginRegistration.doubleOptInGuestOrder';

        $doubleOptInRequired = $this->systemConfigService
            ->get($configKey, $context->getSalesChannelId());

        if (!$doubleOptInRequired) {
            return false;
        }

        if ($createCustomerAccount) {
            $this->addFlash(self::SUCCESS, $this->trans('account.optInRegistrationAlert'));

            return true;
        }

        $this->addFlash(self::SUCCESS, $this->trans('account.optInGuestAlert'));

        return true;
    }

    private function getAdditionalRegisterValidationDefinitions(DataBag $data, SalesChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('storefront.confirmation');

        if ($this->systemConfigService->get('core.loginRegistration.requireEmailConfirmation', $context->getSalesChannelId())) {
            $definition->add('emailConfirmation', new NotBlank(), new EqualTo([
                'value' => $data->get('email'),
            ]));
        }

        if ($data->getBoolean('guest')) {
            return $definition;
        }

        if ($this->systemConfigService->get('core.loginRegistration.requirePasswordConfirmation', $context->getSalesChannelId())) {
            $definition->add('passwordConfirmation', new NotBlank(), new EqualTo([
                'value' => $data->get('password'),
            ]));
        }

        return $definition;
    }

    private function prepareAffiliateTracking(RequestDataBag $data, SessionInterface $session): RequestDataBag
    {
        $affiliateCode = $session->get(AffiliateTrackingListener::AFFILIATE_CODE_KEY);
        $campaignCode = $session->get(AffiliateTrackingListener::CAMPAIGN_CODE_KEY);

        if ($affiliateCode !== null) {
            $data->set(AffiliateTrackingListener::AFFILIATE_CODE_KEY, $affiliateCode);
        }

        if ($campaignCode !== null) {
            $data->set(AffiliateTrackingListener::CAMPAIGN_CODE_KEY, $campaignCode);
        }

        return $data;
    }

    private function getConfirmUrl(SalesChannelContext $context, Request $request): string
    {
        $domainUrl = $this->systemConfigService->getString('core.loginRegistration.doubleOptInDomain', $context->getSalesChannelId());
        if ($domainUrl) {
            return $domainUrl;
        }

        $domainUrl = $request->attributes->get(RequestTransformer::STOREFRONT_URL);
        if ($domainUrl) {
            return $domainUrl;
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('salesChannelId', $context->getSalesChannelId()))
            ->setLimit(1);

        $domain = $this->domainRepository->search($criteria, $context->getContext())->getEntities()->first();
        if (!$domain) {
            throw StorefrontException::domainNotFound($context->getSalesChannel());
        }

        return $domain->getUrl();
    }
}
