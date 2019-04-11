<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\VersionTransformation;

use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiVersionTransformationResponseListener implements EventSubscriberInterface
{
    /**
     * @var VersionTransformationRegistry
     */
    private $versionTransformationRegistry;

    public function __construct(VersionTransformationRegistry $versionTransformationRegistry)
    {
        $this->versionTransformationRegistry = $versionTransformationRegistry;
    }

    public function onFilterResponse(FilterResponseEvent $event)
    {
        $version = (int) $event->getRequest()->headers->get(PlatformRequest::HEADER_API_VERSION);

        if ($version === null) {
            return;
        }

        /** @var ApiVersionTransformation $transformation */
        foreach ($this->versionTransformationRegistry->getResponseTransformationsForVersionAndRoute($version, $event->getRequest()->get('_route')) as $transformation) {
            $transformation->transformResponse($event->getResponse());
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::RESPONSE => 'onFilterResponse',
        ];
    }
}
