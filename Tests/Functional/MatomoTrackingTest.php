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
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class MatomoTrackingTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        $this->testExtensionsToLoad = [
            'werkraummedia/abtest',
        ];

        $this->pathsToLinkInTestInstance = [
            'typo3conf/ext/abtest/Tests/Fixtures/Sites' => 'typo3conf/sites',
        ];

        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/BasicMatomoDatabase.csv');
    }

    #[Test]
    public function rendersPageWithoutVariantWithoutMatomo(): void
    {
        $request = new InternalRequest();
        $request = $request->withPageId(1);
        $response = $this->executeFrontendSubRequest($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Page 1 Title (No Variant)', $response->getBody()->__toString());
        $this->assertNoMatomoTrackingCode($response);
    }

    #[Test]
    public function rendersVariantAWithMatomo(): void
    {
        $request = new InternalRequest();
        $request = $request->withPageId(2);
        $response = $this->executeFrontendSubRequest($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Page 2 Title (Variant A)', $response->getBody()->__toString());
        $this->assertMatomoTrackingCode($response, 'TestForDevelopment', 'VariationA');
    }

    #[Test]
    public function rendersVariantBWithMatomo(): void
    {
        $request = new InternalRequest();
        $request = $request->withPageId(2);
        $request = $request->withCookieParams(['ab-2' => '3']);
        $response = $this->executeFrontendSubRequest($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Page 3 Title (Variant B)', $response->getBody()->__toString());
        $this->assertMatomoTrackingCode($response, 'TestForDevelopment', 'VariationB');
    }

    private function assertNoMatomoTrackingCode(ResponseInterface $response): void
    {
        self::assertStringNotContainsString('_paq.push', $response->getBody()->__toString());
    }

    private function assertMatomoTrackingCode(
        ResponseInterface $response,
        string $experiment,
        string $variation
    ): void {
        self::assertStringContainsString("_paq.push(['AbTesting::enter', {experiment: '$experiment', variation: '$variation'}]);", $response->getBody()->__toString());
    }
}
