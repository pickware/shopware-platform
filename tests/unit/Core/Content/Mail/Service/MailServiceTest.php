<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Service;

use Monolog\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Mail\Service\AbstractMailFactory;
use Shopware\Core\Content\Mail\Service\AbstractMailSender;
use Shopware\Core\Content\Mail\Service\MailService;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailErrorEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\HeaderInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[CoversClass(MailService::class)]
class MailServiceTest extends TestCase
{
    /**
     * @var MockObject&StringTemplateRenderer
     */
    private StringTemplateRenderer $templateRenderer;

    /**
     * @var MockObject&AbstractMailFactory
     */
    private AbstractMailFactory $mailFactory;

    /**
     * @var MockObject&EventDispatcherInterface
     */
    private EventDispatcherInterface $eventDispatcher;

    private MailService $mailService;

    /**
     * @var MockObject&EntityRepository<SalesChannelCollection>
     */
    private EntityRepository $salesChannelRepository;

    /**
     * @var MockObject&LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var MockObject&AbstractMailSender
     */
    private AbstractMailSender $mailSender;

    /**
     * @var MockObject&LanguageLocaleCodeProvider
     */
    private LanguageLocaleCodeProvider $languageLocaleCodeProvider;

    protected function setUp(): void
    {
        $this->mailFactory = $this->createMock(AbstractMailFactory::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->templateRenderer = $this->createMock(StringTemplateRenderer::class);
        $this->salesChannelRepository = $this->createMock(EntityRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->mailSender = $this->createMock(AbstractMailSender::class);
        $this->languageLocaleCodeProvider = $this->createMock(LanguageLocaleCodeProvider::class);

        $this->mailService = new MailService(
            $this->createMock(DataValidator::class),
            $this->templateRenderer,
            $this->mailFactory,
            $this->mailSender,
            $this->createMock(EntityRepository::class),
            $this->salesChannelRepository,
            $this->createMock(SystemConfigService::class),
            $this->eventDispatcher,
            $this->logger,
            $this->languageLocaleCodeProvider,
        );
    }

    public function testSendMailSuccess(): void
    {
        $salesChannelId = Uuid::randomHex();

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);
        $context = Context::createDefaultContext();

        $salesChannelResult = new EntitySearchResult(
            'sales_channel',
            1,
            new SalesChannelCollection([$salesChannel]),
            null,
            new Criteria(),
            $context
        );

        $this->salesChannelRepository->expects($this->once())->method('search')->willReturn($salesChannelResult);

        $data = [
            'recipients' => [],
            'senderName' => 'me',
            'senderEmail' => 'me@shopware.com',
            'subject' => 'Test email',
            'contentPlain' => 'Content plain',
            'contentHtml' => 'Content html',
            'salesChannelId' => $salesChannelId,
        ];

        $email = (new Email())->subject($data['subject'])
            ->html($data['contentHtml'])
            ->text($data['contentPlain'])
            ->to('me@shopware.com')
            ->from(new Address($data['senderEmail']));

        $this->mailFactory->expects($this->once())->method('create')->willReturn($email);
        $this->templateRenderer->expects($this->exactly(4))->method('render')->willReturn('');
        $this->eventDispatcher->expects($this->exactly(3))->method('dispatch')->willReturnOnConsecutiveCalls(
            static::isInstanceOf(MailBeforeValidateEvent::class),
            static::isInstanceOf(MailBeforeSentEvent::class),
            static::isInstanceOf(MailSentEvent::class)
        );
        $email = $this->mailService->send($data, Context::createDefaultContext());

        static::assertInstanceOf(Email::class, $email);
    }

    public function testSendMailWithRenderingError(): void
    {
        $salesChannelId = Uuid::randomHex();

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);
        $context = Context::createDefaultContext();

        $salesChannelResult = new EntitySearchResult(
            'sales_channel',
            1,
            new SalesChannelCollection([$salesChannel]),
            null,
            new Criteria(),
            $context
        );

        $this->salesChannelRepository->expects($this->once())->method('search')->willReturn($salesChannelResult);

        $data = [
            'recipients' => [],
            'senderName' => 'me',
            'senderEmail' => 'me@shopware.com',
            'subject' => 'Test email',
            'contentPlain' => 'Content plain',
            'contentHtml' => 'Content html',
            'salesChannelId' => $salesChannelId,
        ];

        $email = (new Email())->subject($data['subject'])
            ->html($data['contentHtml'])
            ->text($data['contentPlain'])
            ->to($data['senderEmail'])
            ->from(new Address($data['senderEmail']));

        $this->mailFactory->expects($this->never())->method('create')->willReturn($email);
        $beforeValidateEvent = null;
        $mailErrorEvent = null;

        $this->logger->expects($this->once())->method('log')->with(Level::Warning);
        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (Event $event) use (&$beforeValidateEvent, &$mailErrorEvent) {
                if ($event instanceof MailBeforeValidateEvent) {
                    $beforeValidateEvent = $event;

                    return $event;
                }

                $mailErrorEvent = $event;

                return $event;
            });

        $this->templateRenderer->expects($this->exactly(1))->method('render')->willThrowException(new \Exception('cannot render'));

        $email = $this->mailService->send($data, Context::createDefaultContext());

        static::assertNull($email);
        static::assertNotNull($beforeValidateEvent);
        static::assertInstanceOf(MailErrorEvent::class, $mailErrorEvent);
        static::assertSame(Level::Warning, $mailErrorEvent->getLogLevel());
        static::assertNotNull($mailErrorEvent->getMessage());

        $message = 'Could not render Mail-Subject with error message: cannot render';

        static::assertSame($message, $mailErrorEvent->getMessage());
        static::assertSame('Test email', $mailErrorEvent->getTemplate());
        static::assertSame([
            'salesChannel' => $salesChannel,
            'salesChannelId' => $salesChannelId,
        ], $mailErrorEvent->getTemplateData());
    }

    public function testSendMailWithoutSenderName(): void
    {
        $salesChannelId = Uuid::randomHex();

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);
        $context = Context::createDefaultContext();

        $salesChannelResult = new EntitySearchResult(
            'sales_channel',
            1,
            new SalesChannelCollection([$salesChannel]),
            null,
            new Criteria(),
            $context
        );

        $this->salesChannelRepository->expects($this->once())->method('search')->willReturn($salesChannelResult);

        $data = [
            'recipients' => [],
            'subject' => 'Test email',
            'senderName' => null,
            'contentPlain' => 'Content plain',
            'contentHtml' => 'Content html',
            'salesChannelId' => $salesChannelId,
        ];

        $this->logger->expects($this->once())->method('log')->with(Level::Error);
        $this->eventDispatcher->expects($this->exactly(4))->method('dispatch')->willReturnOnConsecutiveCalls(
            static::isInstanceOf(MailBeforeValidateEvent::class),
            static::isInstanceOf(MailErrorEvent::class),
            static::isInstanceOf(MailBeforeSentEvent::class),
            static::isInstanceOf(MailSentEvent::class)
        );

        $email = (new Email())->subject($data['subject'])
            ->html($data['contentHtml'])
            ->text($data['contentPlain'])
            ->to('test@shopware.com')
            ->from(new Address('test@shopware.com'));

        $this->mailFactory->expects($this->once())->method('create')->willReturn($email);

        $email = $this->mailService->send($data, Context::createDefaultContext());

        static::assertInstanceOf(Email::class, $email);
    }

    public function testMailSenderExceptionIsHandled(): void
    {
        $salesChannelId = Uuid::randomHex();

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);
        $context = Context::createDefaultContext();

        $salesChannelResult = new EntitySearchResult(
            'sales_channel',
            1,
            new SalesChannelCollection([$salesChannel]),
            null,
            new Criteria(),
            $context
        );

        $this->salesChannelRepository->expects($this->once())->method('search')->willReturn($salesChannelResult);

        $data = [
            'recipients' => [],
            'senderName' => 'me',
            'senderEmail' => 'me@shopware.com',
            'subject' => 'Test email',
            'contentPlain' => 'Content plain',
            'contentHtml' => 'Content html',
            'salesChannelId' => $salesChannelId,
        ];

        $email = (new Email())->subject($data['subject'])
            ->html($data['contentHtml'])
            ->text($data['contentPlain'])
            ->to('me@shopware.com')
            ->from(new Address($data['senderEmail']));

        $this->mailFactory->expects($this->once())->method('create')->willReturn($email);
        $this->templateRenderer->expects($this->exactly(4))->method('render')->willReturn('');

        $this->logger->expects($this->once())->method('log')->with(Level::Error);

        $beforeValidateEvent = null;
        $mailErrorEvent = null;

        $this->eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(function (Event $event) use (&$beforeValidateEvent, &$mailErrorEvent) {
                if ($event instanceof MailBeforeValidateEvent) {
                    $beforeValidateEvent = $event;

                    return $event;
                }

                $mailErrorEvent = $event;

                return $event;
            });

        $this->mailSender->expects($this->once())->method('send')->willThrowException(new \Exception('Mail sending failed'));

        $email = $this->mailService->send($data, Context::createDefaultContext());

        static::assertNull($email);
        static::assertNotNull($beforeValidateEvent);
        static::assertInstanceOf(MailErrorEvent::class, $mailErrorEvent);
        static::assertSame(Level::Error, $mailErrorEvent->getLogLevel());
        static::assertNotNull($mailErrorEvent->getMessage());
        static::assertSame('Could not send mail with error message: Mail sending failed', $mailErrorEvent->getMessage());
        static::assertSame('Content html', $mailErrorEvent->getTemplate());
        static::assertEmpty($mailErrorEvent->getTemplateData());
    }

    public function testMailInTestModeHasNoEmptyHeaders(): void
    {
        $salesChannelId = Uuid::randomHex();

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);
        $context = Context::createDefaultContext();

        $salesChannelResult = new EntitySearchResult(
            'sales_channel',
            1,
            new SalesChannelCollection([$salesChannel]),
            null,
            new Criteria(),
            $context
        );

        $this->salesChannelRepository->expects($this->once())->method('search')->willReturn($salesChannelResult);

        $data = [
            'testMode' => true,
            'recipients' => [],
            'senderName' => 'me',
            'senderEmail' => 'me@shopware.com',
            'subject' => 'Test email',
            'contentPlain' => 'Content plain',
            'contentHtml' => 'Content html',
            'salesChannelId' => $salesChannelId,
        ];

        $email = (new Email())->subject($data['subject'])
            ->html($data['contentHtml'])
            ->text($data['contentPlain'])
            ->to('me@shopware.com')
            ->from(new Address($data['senderEmail']));

        $this->mailFactory->expects($this->once())->method('create')->willReturn($email);
        $this->templateRenderer->expects($this->exactly(4))->method('render')->willReturn('');
        $this->eventDispatcher->expects($this->exactly(3))->method('dispatch')->willReturnOnConsecutiveCalls(
            static::isInstanceOf(MailBeforeValidateEvent::class),
            static::isInstanceOf(MailBeforeSentEvent::class),
            static::isInstanceOf(MailSentEvent::class)
        );
        $this->languageLocaleCodeProvider->expects($this->once())->method('getLocaleForLanguageId')->willReturn('en-GB');

        $email = $this->mailService->send($data, Context::createDefaultContext());

        static::assertInstanceOf(Email::class, $email);
        $headers = $email->getHeaders();
        static::assertSame(Defaults::LANGUAGE_SYSTEM, $headers->get('X-Shopware-Language-Id')?->getBody());
        static::assertSame($salesChannelId, $headers->get('X-Shopware-Sales-Channel-Id')?->getBody());

        // check that no header is empty (e.g. Amazon SES doesn't like that)
        foreach ($headers->all() as $header) {
            static::assertInstanceOf(HeaderInterface::class, $header);
            static::assertNotEmpty($header->getBodyAsString(), 'mail header ' . $header->getName() . ' should not be empty');
        }
    }
}
