<?php

declare(strict_types=1);

/*
 * Copyright (C) 2023 Daniel Siepmann <coding@daniel-siepmann.de>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301, USA.
 */

namespace WerkraumMedia\ABTest\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Cookie;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class FrontendRenderingTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        $this->testExtensionsToLoad = [
            'werkraummedia/abtest',
        ];

        $this->pathsToLinkInTestInstance = [
            'typo3conf/ext/abtest/Tests/Fixtures/Sites' => 'typo3conf/sites',
        ];

        $this->configurationToUseInTestInstance = [
            'FE' => [
                'debug' => true,
            ],
            'SYS' => [
                'caching' => [
                    'cacheConfigurations' => [
                        'pages' => [
                            'backend' => Typo3DatabaseBackend::class,
                        ],
                    ],
                ],
            ],
        ];

        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/BasicDatabase.csv');
    }

    #[Test]
    public function opensDefaultPageIfNothingIsConfigured(): void
    {
        $request = new InternalRequest();
        $request = $request->withPageId(1);
        $response = $this->executeFrontendSubRequest($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('', $response->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString('Page 1 Title (No Variant)', $response->getBody()->__toString());
        $this->assertPageIsNotCached($response);
        $this->assertCounterOfPage(1, 0);
    }

    #[Test]
    public function opensVariantAForFirstVisitor(): void
    {
        $request = new InternalRequest();
        $request = $request->withPageId(2);
        $response = $this->executeFrontendSubRequest($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Page 2 Title (Variant A)', $response->getBody()->__toString());
        $this->assertPageIsNotCached($response);
        $this->assertCookie($response, 'ab-2', '2');
        $this->assertCounterOfPage(2, 1);
    }

    #[Test]
    public function opensVariantBForSecondVisitor(): void
    {
        $this->opensVariantAForFirstVisitor();

        $request = new InternalRequest();
        $request = $request->withPageId(2);
        $response = $this->executeFrontendSubRequest($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Page 3 Title (Variant B)', $response->getBody()->__toString());
        $this->assertCookie($response, 'ab-2', '3');
        $this->assertPageIsNotCached($response);
        $this->assertCounterOfPage(2, 1);
        $this->assertCounterOfPage(3, 1);
    }

    #[Test]
    public function opensVariantStoredInCookie(): void
    {
        $this->opensVariantAForFirstVisitor();

        $request = new InternalRequest();
        $request = $request->withPageId(2);
        $request = $request->withCookieParams(['ab-2' => '2']);
        $response = $this->executeFrontendSubRequest($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Page 2 Title (Variant A)', $response->getBody()->__toString());
        $this->assertPageIsCached($response);
        $this->assertCookie($response, 'ab-2', '2');
        // 1 from first visit, but not 2 as 2nd visit is via cookie.
        $this->assertCounterOfPage(2, 1, 'Opening from cookie should not increase counter.');
        $this->assertCounterOfPage(3, 0, 'Opening from cookie should not increase counter.');
    }

    #[Test]
    public function opensDefaultPageIfBotWasDetected(): void
    {
        $request = new InternalRequest();
        $request = $request->withPageId(2);
        $request = $request->withAddedHeader('User-Agent', 'Storebot-Google');
        $response = $this->executeFrontendSubRequest($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Page 2 Title (Variant A)', $response->getBody()->__toString());
        $this->assertPageIsNotCached($response);
        $this->assertCookieWasNotSet($response);
        $this->assertCounterOfPage(2, 0);
    }

    #[Test]
    public function opensRequestedPageIfVariantPageDoesNotExist(): void
    {
        $request = new InternalRequest();
        $request = $request->withPageId(4);
        $request = $request->withCookieParams(['ab-4' => '5']);
        $response = $this->executeFrontendSubRequest($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Page 4 Title (Variant A)', $response->getBody()->__toString());
        $this->assertPageIsNotCached($response);
        $this->assertCookie($response, 'ab-4', '4');
        $this->assertCounterOfPage(4, 1);
        $this->assertCounterOfPage(5, 0);
    }

    #[Test]
    public function opensRequestedPageIfCookieDoesNotMatchRequestedPage(): void
    {
        $request = new InternalRequest();
        $request = $request->withPageId(2);
        $request = $request->withCookieParams(['ab-2' => '5']);
        $response = $this->executeFrontendSubRequest($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Page 2 Title (Variant A)', $response->getBody()->__toString());
        $this->assertPageIsNotCached($response);
        $this->assertCookie($response, 'ab-2', '2');
        $this->assertCounterOfPage(2, 1);
        $this->assertCounterOfPage(5, 0);
    }

    #[Test]
    public function opensVariantBForSecondVisitorIfVariantFromCookieDoesNotMatchVariantB(): void
    {
        $this->opensVariantAForFirstVisitor();

        $request = new InternalRequest();
        $request = $request->withPageId(2);
        $request = $request->withCookieParams(['ab-2' => '5']);
        $response = $this->executeFrontendSubRequest($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Page 3 Title (Variant B)', $response->getBody()->__toString());
        $this->assertPageIsNotCached($response);
        $this->assertCookie($response, 'ab-2', '3');
        $this->assertCounterOfPage(2, 1);
        $this->assertCounterOfPage(3, 1);
    }

    #[Test]
    public function cookieHasDefaultLifetime(): void
    {
        $request = new InternalRequest();
        $request = $request->withPageId(2);
        $response = $this->executeFrontendSubRequest($request);

        self::assertSame(200, $response->getStatusCode());
        $cookie = Cookie::fromString($response->getHeaderLine('Set-Cookie'));
        self::assertSame(604800, $cookie->getMaxAge());
    }

    #[Test]
    public function cookieHasConfiguredLifetime(): void
    {
        $request = new InternalRequest();
        $request = $request->withPageId(4);
        $response = $this->executeFrontendSubRequest($request);

        self::assertSame(200, $response->getStatusCode());
        $cookie = Cookie::fromString($response->getHeaderLine('Set-Cookie'));
        self::assertSame(2419200, $cookie->getMaxAge());
    }

    /**
     * Ensure TYPO3 caching works as expected.
     * The first call should create a proper cache entry.
     * We should still be able to retrieve the other variant by adding the cookie.
     * The 2nd variant should also be delivered from cache on 2nd request.
     */
    #[Test]
    public function returnsCachedPage(): void
    {
        $request = new InternalRequest();
        $request = $request->withPageId(2);
        $response = $this->executeFrontendSubRequest($request);
        self::assertStringContainsString('Page 2 Title (Variant A)', $response->getBody()->__toString());
        $this->assertPageIsNotCached($response);

        $request = new InternalRequest();
        $request = $request->withPageId(2);
        $request = $request->withCookieParams(['ab-2' => '2']);
        $response = $this->executeFrontendSubRequest($request);
        self::assertStringContainsString('Page 2 Title (Variant A)', $response->getBody()->__toString());
        $this->assertPageIsCached($response);

        $request = new InternalRequest();
        $request = $request->withPageId(2);
        $response = $this->executeFrontendSubRequest($request);
        self::assertStringContainsString('Page 3 Title (Variant B)', $response->getBody()->__toString());
        $this->assertPageIsNotCached($response);

        $request = new InternalRequest();
        $request = $request->withPageId(2);
        $request = $request->withCookieParams(['ab-2' => '3']);
        $response = $this->executeFrontendSubRequest($request);
        self::assertStringContainsString('Page 3 Title (Variant B)', $response->getBody()->__toString());
        $this->assertPageIsCached($response);
    }

    private function assertCounterOfPage(
        int $pageUid,
        int $expectedCounter,
        string $message = ''
    ): void {
        $actualCounter = $this->getConnectionPool()
            ->getConnectionForTable('pages')
            ->select(['tx_abtest_counter'], 'pages', ['uid' => $pageUid])
            ->fetchFirstColumn()[0] ?? 0
        ;

        self::assertSame(
            $expectedCounter,
            $actualCounter,
            'Counter for page ' . $pageUid . ' was not as expected. ' . $message
        );
    }

    private function assertCookie(
        ResponseInterface $response,
        string $name,
        string $value
    ): void {
        $cookie = Cookie::fromString($response->getHeaderLine('Set-Cookie'));
        self::assertSame($name, $cookie->getName());
        self::assertSame($value, $cookie->getValue());
        self::assertSame('/', $cookie->getPath());
        self::assertSame('lax', $cookie->getSameSite());
        self::assertNull($cookie->getDomain());
    }

    private function assertCookieWasNotSet(ResponseInterface $response): void
    {
        self::assertSame(
            '',
            $response->getHeaderLine('Set-Cookie'),
            'Cookie was set but was not expected to be set.'
        );
    }

    private function assertPageIsNotCached(ResponseInterface $response): void
    {
        self::assertSame('', $response->getHeaderLine('X-TYPO3-Debug-Cache'));
    }

    private function assertPageIsCached(ResponseInterface $response): void
    {
        self::assertStringStartsWith('Cached page generated', $response->getHeaderLine('X-TYPO3-Debug-Cache'));
    }
}
