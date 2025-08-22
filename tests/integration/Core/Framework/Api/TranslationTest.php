<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\MissingSystemTranslationException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Language\TranslationValidator;
use Shopware\Core\System\Locale\LocaleEntity;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Group('slow')]
class TranslationTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testNoOverride(): void
    {
        $langId = Uuid::randomHex();
        $this->createLanguage($langId);

        $this->assertTranslation(
            ['name' => 'not translated', 'translated' => ['name' => 'not translated']],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'not translated'],
                    $langId => ['name' => 'translated'],
                ],
            ]
        );
    }

    public function testDefault(): void
    {
        $this->assertTranslation(
            ['name' => 'not translated'],
            [
                'name' => 'not translated',
                'translations' => [
                    $this->getDeDeLanguageId() => ['name' => 'german'],
                ],
            ]
        );
    }

    public function testDefault2(): void
    {
        $this->assertTranslation(
            ['name' => 'not translated'],
            [
                'name' => 'not translated',
            ]
        );
    }

    public function testDefaultAndExplicitSystem(): void
    {
        $this->assertTranslation(
            ['name' => 'system'],
            [
                'name' => 'default',
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'system'],
                ],
            ]
        );
    }

    public function testFallback(): void
    {
        $langId = Uuid::randomHex();
        $fallbackId = Uuid::randomHex();
        $this->createLanguage($langId, $fallbackId);

        $this->assertTranslation(
            ['name' => null, 'translated' => ['name' => 'translated by fallback']],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'default'],
                    $fallbackId => ['name' => 'translated by fallback'],
                ],
            ],
            $langId
        );
    }

    public function testDefaultFallback(): void
    {
        $this->assertTranslation(
            ['name' => 'translated by default fallback'],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'translated by default fallback'],
                ],
            ]
        );
    }

    public function testWithLanguageIdParam(): void
    {
        $this->assertTranslation(
            ['name' => 'translated by default fallback'],
            [
                'translations' => [
                    ['languageId' => Defaults::LANGUAGE_SYSTEM, 'name' => 'translated by default fallback'],
                ],
            ]
        );
    }

    public function testOnlySystemLocaleIdentifier(): void
    {
        $localeRepo = static::getContainer()->get('locale.repository');
        /** @var LocaleEntity $locale */
        $locale = $localeRepo->search(new Criteria([$this->getLocaleIdOfSystemLanguage()]), Context::createDefaultContext())->first();

        $this->assertTranslation(
            ['name' => 'system translation'],
            [
                'translations' => [
                    $locale->getCode() => ['name' => 'system translation'],
                ],
            ]
        );
    }

    public function testEmptyLanguageIdError(): void
    {
        $baseResource = '/api/category';
        $headerName = $this->getLangHeaderName();
        $langId = '';

        $this->getBrowser()->jsonRequest('GET', $baseResource, [], [$headerName => $langId]);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(412, $response->getStatusCode(), (string) $response->getContent());

        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(RoutingException::LANGUAGE_NOT_FOUND, $data['errors'][0]['code']);
    }

    public function testInvalidUuidLanguageIdError(): void
    {
        $baseResource = '/api/category';
        $headerName = $this->getLangHeaderName();
        $langId = 'foobar';

        $this->getBrowser()->jsonRequest('GET', $baseResource, [], [$headerName => $langId]);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(412, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(RoutingException::LANGUAGE_NOT_FOUND, $data['errors'][0]['code']);

        $langId = \sprintf('id=%s', 'foobar');
        $this->getBrowser()->jsonRequest('GET', $baseResource, [], [$headerName => $langId]);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(412, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(RoutingException::LANGUAGE_NOT_FOUND, $data['errors'][0]['code']);
    }

    public function testNonExistingLanguageIdError(): void
    {
        $baseResource = '/api/category';
        $headerName = $this->getLangHeaderName();
        $langId = Uuid::randomHex();

        $this->getBrowser()->jsonRequest('GET', $baseResource, [], [$headerName => $langId]);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(412, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(RoutingException::LANGUAGE_NOT_FOUND, $data['errors'][0]['code']);

        $langId = \sprintf('id=%s', Uuid::randomHex());
        $this->getBrowser()->jsonRequest('GET', $baseResource, [], [$headerName => $langId]);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(412, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(RoutingException::LANGUAGE_NOT_FOUND, $data['errors'][0]['code']);
    }

    public function testOverride(): void
    {
        $langId = Uuid::randomHex();
        $this->createLanguage($langId);

        $this->assertTranslation(
            ['name' => 'translated'],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'not translated'],
                    $langId => ['name' => 'translated'],
                ],
            ],
            $langId
        );
    }

    public function testNoDefaultTranslation(): void
    {
        $langId = Uuid::randomHex();
        $this->createLanguage($langId);

        $this->assertTranslationError(
            [
                [
                    'code' => MissingSystemTranslationException::VIOLATION_MISSING_SYSTEM_TRANSLATION,
                    'status' => '400',
                    'source' => [
                        'pointer' => '/0/translations/' . Defaults::LANGUAGE_SYSTEM,
                    ],
                ],
            ],
            [
                'translations' => [
                    $langId => ['name' => 'translated'],
                ],
            ]
        );
    }

    public function testExplicitDefaultTranslation(): void
    {
        $langId = Uuid::randomHex();
        $this->createLanguage($langId);

        $this->assertTranslation(
            ['name' => 'not translated'],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'not translated'],
                    $langId => ['name' => 'translated'],
                ],
            ],
            Defaults::LANGUAGE_SYSTEM
        );
    }

    public function testPartialTranslationWithFallback(): void
    {
        $langId = Uuid::randomHex();
        $fallbackId = Uuid::randomHex();
        $this->createLanguage($langId, $fallbackId);

        $this->assertTranslation(
            [
                'name' => 'translated',
                'territory' => null,
                'translated' => [
                    'territory' => 'translated by fallback',
                ],
            ],
            [
                'code' => 'test',
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'default', 'territory' => 'translated by default'],
                    $langId => [
                        'name' => 'translated',
                    ],
                    $fallbackId => [
                        'name' => 'translated by fallback',
                        'territory' => 'translated by fallback',
                    ],
                ],
            ],
            $langId,
            'locale'
        );
    }

    public function testChildTranslationWithoutRequiredField(): void
    {
        $langId = Uuid::randomHex();
        $fallbackId = Uuid::randomHex();
        $this->createLanguage($langId, $fallbackId);

        $this->assertTranslation(
            [
                'name' => null,
                'territory' => 'translated',
                'translated' => [
                    'name' => 'only translated by fallback',
                    'territory' => 'translated',
                ],
            ],
            [
                'code' => 'test',
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'default', 'territory' => 'translated by default'],
                    $langId => [
                        'territory' => 'translated',
                    ],
                    $fallbackId => [
                        'name' => 'only translated by fallback',
                    ],
                ],
            ],
            $langId,
            'locale'
        );
    }

    public function testWithOverrideInPatch(): void
    {
        $baseResource = '/api/locale';
        $id = Uuid::randomHex();
        $langId = Uuid::randomHex();

        $notTranslated = [
            'id' => $id,
            'code' => 'test',
            'name' => 'not translated',
            'territory' => 'not translated',
        ];

        $this->createLanguage($langId);

        $headerName = $this->getLangHeaderName();

        $this->getBrowser()->jsonRequest('POST', $baseResource, $notTranslated);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(204, $response->getStatusCode());

        $this->assertEntityExists($this->getBrowser(), 'locale', $id);

        $translated = [
            'id' => $id,
            'name' => 'translated',
        ];

        $this->getBrowser()->jsonRequest('PATCH', $baseResource . '/' . $id, $translated, [$headerName => $langId]);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(204, $response->getStatusCode());

        $this->getBrowser()->jsonRequest('GET', $baseResource . '/' . $id, [], [$headerName => $langId]);
        $response = $this->getBrowser()->getResponse();
        $responseData = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame($translated['name'], $responseData['data']['attributes']['name']);
        static::assertNull($responseData['data']['attributes']['territory']);

        static::assertSame($notTranslated['territory'], $responseData['data']['attributes']['translated']['territory']);
    }

    public function testDelete(): void
    {
        $baseResource = '/api/category';
        $id = Uuid::randomHex();
        $langId = Uuid::randomHex();

        $name = 'Test category';
        $translatedName = $name . '_translated';

        $categoryData = [
            'id' => $id,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => $name],
                $langId => ['name' => $translatedName],
            ],
        ];

        $this->createLanguage($langId);

        $this->getBrowser()->jsonRequest('POST', $baseResource, $categoryData);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(204, $response->getStatusCode());
        $this->assertEntityExists($this->getBrowser(), 'category', $id);

        $headerName = $this->getLangHeaderName();

        $this->getBrowser()->jsonRequest('GET', $baseResource . '/' . $id, [], [$headerName => Defaults::LANGUAGE_SYSTEM]);
        $response = $this->getBrowser()->getResponse();
        $responseData = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame($name, $responseData['data']['attributes']['name']);

        $this->getBrowser()->jsonRequest('GET', $baseResource . '/' . $id, [], [$headerName => $langId]);
        $response = $this->getBrowser()->getResponse();
        $responseData = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame($translatedName, $responseData['data']['attributes']['name']);

        $this->getBrowser()->jsonRequest('DELETE', $baseResource . '/' . $id . '/translations/' . $langId);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(204, $response->getStatusCode(), (string) $response->getContent());

        $this->getBrowser()->jsonRequest('GET', $baseResource . '/' . $id, [], [$headerName => $langId]);
        $response = $this->getBrowser()->getResponse();
        $responseData = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertNull($responseData['data']['attributes']['name']);
    }

    public function testDeleteSystemLanguageViolation(): void
    {
        $baseResource = '/api/category';
        $id = Uuid::randomHex();

        $categoryData = [
            'id' => $id,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => 'Test category'],
            ],
        ];
        $this->getBrowser()->jsonRequest('POST', $baseResource, $categoryData);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(204, $response->getStatusCode());
        $this->assertEntityExists($this->getBrowser(), 'category', $id);

        $this->getBrowser()->jsonRequest('DELETE', $baseResource . '/' . $id . '/translations/' . Defaults::LANGUAGE_SYSTEM);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(400, $response->getStatusCode(), (string) $response->getContent());

        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(TranslationValidator::VIOLATION_DELETE_SYSTEM_TRANSLATION, $data['errors'][0]['code']);
        static::assertSame('/' . $id . '/translations/' . Defaults::LANGUAGE_SYSTEM, $data['errors'][0]['source']['pointer']);
    }

    public function testDeleteEntityWithOneRootTranslation(): void
    {
        /**
         * This works because the dal does not generate a `DeleteCommand` for the `CategoryTranslation`.
         * The translation is deleted by the foreign key delete cascade.
         */
        $baseResource = '/api/category';
        $id = Uuid::randomHex();
        $rootId = Uuid::randomHex();

        $this->createLanguage($rootId);

        $categoryData = [
            'id' => $id,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => 'Test category'],
            ],
        ];

        $this->getBrowser()->jsonRequest('POST', $baseResource, $categoryData);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(204, $response->getStatusCode());
        $this->assertEntityExists($this->getBrowser(), 'category', $id);

        $this->getBrowser()->jsonRequest('DELETE', $baseResource . '/' . $id);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(204, $response->getStatusCode());
    }

    public function testDeleteNonSystemRootTranslations(): void
    {
        $baseResource = '/api/category';
        $id = Uuid::randomHex();
        $rootDelete = Uuid::randomHex();
        $this->createLanguage($rootDelete);

        $categoryData = [
            'id' => $id,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => 'system'],
                $rootDelete => ['name' => 'root delete'],
            ],
        ];
        $this->getBrowser()->jsonRequest('POST', $baseResource, $categoryData);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(204, $response->getStatusCode());
        $this->assertEntityExists($this->getBrowser(), 'category', $id);

        $this->getBrowser()->jsonRequest('DELETE', $baseResource . '/' . $id . '/translations/' . $rootDelete);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(204, $response->getStatusCode());
    }

    public function testDeleteChildLanguageTranslation(): void
    {
        $baseResource = '/api/category';
        $id = Uuid::randomHex();
        $rootId = Uuid::randomHex();
        $childId = Uuid::randomHex();

        $this->createLanguage($childId, $rootId);

        $categoryData = [
            'id' => $id,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => 'system'],
                $rootId => ['name' => 'root'],
                $childId => ['name' => 'child'],
            ],
        ];
        $this->getBrowser()->jsonRequest('POST', $baseResource, $categoryData);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(204, $response->getStatusCode());
        $this->assertEntityExists($this->getBrowser(), 'category', $id);

        $this->getBrowser()->jsonRequest('DELETE', $baseResource . '/' . $id . '/translations/' . $childId);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(204, $response->getStatusCode());
    }

    public function testMixedTranslationStatus(): void
    {
        $baseResource = '/api/category';
        $rootLangId = Uuid::randomHex();
        $childLangId = Uuid::randomHex();
        $this->createLanguage($childLangId, $rootLangId);

        $idSystem = Uuid::randomHex();
        $system = [
            'id' => $idSystem,
            'name' => '1. system',
        ];
        $this->getBrowser()->jsonRequest('POST', $baseResource, $system);
        $this->assertEntityExists($this->getBrowser(), 'category', $idSystem);

        $idRoot = Uuid::randomHex();
        $root = [
            'id' => $idRoot,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => '2. system'],
                $rootLangId => ['name' => '2. root'],
            ],
        ];
        $this->getBrowser()->jsonRequest('POST', $baseResource, $root);
        $this->assertEntityExists($this->getBrowser(), 'category', $idRoot);

        $idChild = Uuid::randomHex();
        $childAndRoot = [
            'id' => $idChild,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => '3. system'],
                $rootLangId => ['name' => '3. root'],
                $childLangId => ['name' => '3. child'],
            ],
        ];
        $this->getBrowser()->jsonRequest('POST', $baseResource, $childAndRoot);
        $this->assertEntityExists($this->getBrowser(), 'category', $idChild);

        $headers = [
            'HTTP_ACCEPT' => 'application/json',
            $this->getLangHeaderName() => $childLangId,
        ];
        $this->getBrowser()->jsonRequest('GET', $baseResource . '?sort=name', [], $headers);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['data'];

        static::assertNull($data[0]['name']);
        static::assertNull($data[1]['name']);
        static::assertSame('3. child', $data[2]['name']);

        static::assertSame('1. system', $data[0]['translated']['name']);
        static::assertSame('2. root', $data[1]['translated']['name']);
        static::assertSame('3. child', $data[2]['translated']['name']);
    }

    private function getLangHeaderName(): string
    {
        return 'HTTP_' . mb_strtoupper(str_replace('-', '_', PlatformRequest::HEADER_LANGUAGE_ID));
    }

    /**
     * @param list<array{code: string, status:string, source: array{pointer: string}}> $errors
     * @param array{translations: array<string, array<string, string>>} $data
     */
    private function assertTranslationError(array $errors, array $data): void
    {
        $baseResource = '/api/category';

        $categoryData = [
            'id' => Uuid::randomHex(),
        ];
        $categoryData = array_merge_recursive($categoryData, $data);

        $this->getBrowser()->jsonRequest('POST', $baseResource, $categoryData);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(400, $response->getStatusCode(), (string) $response->getContent());

        $responseData = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(\count($errors), $responseData['errors']);

        $actualErrors = array_map(function ($error) {
            $e = [
                'code' => $error['code'],
                'status' => $error['status'],
            ];
            if (isset($error['source'])) {
                $e['source'] = $error['source'];
            }

            return $e;
        }, $responseData['errors']);

        static::assertSame($errors, $actualErrors);
    }

    /**
     * @param array<string, string|array<string, string>|null> $expectedTranslations
     * @param array<string, mixed> $data
     */
    private function assertTranslation(array $expectedTranslations, array $data, ?string $langOverride = null, string $entity = 'category'): void
    {
        $baseResource = '/api/' . $entity;

        $requestData = $data;
        if (!isset($requestData['id'])) {
            $requestData['id'] = Uuid::randomHex();
        }

        $this->getBrowser()->jsonRequest('POST', $baseResource, $requestData);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(204, $response->getStatusCode(), (string) $response->getContent());

        $this->assertEntityExists($this->getBrowser(), $entity, $requestData['id']);

        $headers = ['HTTP_ACCEPT' => 'application/json'];
        if ($langOverride) {
            $headers[$this->getLangHeaderName()] = $langOverride;
        }

        $this->getBrowser()->jsonRequest('GET', $baseResource . '/' . $requestData['id'], [], $headers);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $responseData = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('data', $responseData, (string) $response->getContent());
        foreach ($expectedTranslations as $key => $expectedTranslation) {
            if (!\is_array($expectedTranslation)) {
                static::assertSame($expectedTranslation, $responseData['data'][$key]);
            } else {
                foreach ($expectedTranslation as $key2 => $expectedTranslation2) {
                    static::assertSame($expectedTranslation2, $responseData['data'][$key][$key2]);
                }
            }
        }
    }

    private function createLanguage(string $langId, ?string $fallbackId = null): void
    {
        $baseUrl = '/api';

        if ($fallbackId) {
            $fallbackLocaleId = Uuid::randomHex();
            $parentLanguageData = [
                'id' => $fallbackId,
                'name' => 'test language ' . $fallbackId,
                'locale' => [
                    'id' => $fallbackLocaleId,
                    'code' => 'x-tst_' . $fallbackLocaleId,
                    'name' => 'Test locale ' . $fallbackLocaleId,
                    'territory' => 'Test territory ' . $fallbackLocaleId,
                ],
                'translationCodeId' => $fallbackLocaleId,
                'active' => true,
            ];
            $this->getBrowser()->jsonRequest('POST', $baseUrl . '/language', $parentLanguageData);
            static::assertSame(204, $this->getBrowser()->getResponse()->getStatusCode());
        }

        $localeId = Uuid::randomHex();
        $languageData = [
            'id' => $langId,
            'name' => 'test language ' . $langId,
            'parentId' => $fallbackId,
            'locale' => [
                'id' => $localeId,
                'code' => 'x-tst_' . $localeId,
                'name' => 'Test locale ' . $localeId,
                'territory' => 'Test territory ' . $localeId,
            ],
            'translationCodeId' => $localeId,
            'active' => true,
        ];

        $this->getBrowser()->jsonRequest('POST', $baseUrl . '/language', $languageData);
        static::assertSame(204, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());

        $this->getBrowser()->jsonRequest('GET', $baseUrl . '/language/' . $langId);
    }
}
