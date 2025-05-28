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

namespace WerkraumMedia\ABTest;

use DeviceDetector\DeviceDetector;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Frontend\Event\BeforePageIsResolvedEvent;
use WerkraumMedia\ABTest\Events\SwitchedToVariant;

/**
 * Will decide whether to switch to another variant.
 */
class Switcher
{
    private PageRepository $pageRepository;

    private EventDispatcher $eventDispatcher;

    public function __construct(
        PageRepository $pageRepository,
        EventDispatcher $eventDispatcher
    ) {
        $this->pageRepository = $pageRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function switch(BeforePageIsResolvedEvent $event): void
    {
        if ($this->isRequestByBot($event->getRequest())) {
            return;
        }

        $currentPageId = $event->getPageInformation()->getId();
        if ($currentPageId === 0) {
            return;
        }

        $currentPagePropertiesArray = $this->pageRepository->getPage($currentPageId);
        if ((int)$currentPagePropertiesArray['tx_abtest_variant'] === 0) {
            return;
        }

        $requestedViaCookie = (int)($event->getRequest()->getCookieParams()['ab-' . $currentPageId] ?? '0');
        $targetPage = $this->getTargetPage($currentPagePropertiesArray, $requestedViaCookie);

        if ($event->getPageInformation()->getId() !== (int)$targetPage['uid']) {
            $event->getPageInformation()->setId((int)$targetPage['uid']);
            $event->getPageInformation()->setContentFromPid((int)$targetPage['uid']);
            $event->getPageInformation()->setPageRecord($targetPage);
        }

        if (
            $requestedViaCookie === 0
            || (int)$targetPage['uid'] !== $requestedViaCookie
        ) {
            $this->pageRepository->updateCounter((int)$targetPage['uid'], ++$targetPage['tx_abtest_counter']);
        }

        $this->eventDispatcher->dispatch(new SwitchedToVariant(
            $currentPagePropertiesArray,
            $targetPage
        ));
    }

    private function isRequestByBot(ServerRequestInterface $request): bool
    {
        $deviceDetector = new DeviceDetector();
        $deviceDetector->setUserAgent($request->getHeaderLine('User-Agent'));
        try {
            $deviceDetector->parse();
            return $deviceDetector->isBot();
        } catch (\Exception $e) {
        }

        return false;
    }

    private function getTargetPage(array $page, int $cookiePageUid): array
    {
        if ($cookiePageUid > 0 && $cookiePageUid === (int)$page['uid']) {
            return $page;
        }

        $variantPage = $this->pageRepository->getPage((int)$page['tx_abtest_variant']);

        if (
            $variantPage !== []
            && (
                ($cookiePageUid > 0 && $cookiePageUid === (int)$variantPage['uid'])
                || ((int)$variantPage['tx_abtest_counter'] < (int)$page['tx_abtest_counter'])
            )
        ) {
            return $variantPage;
        }

        return $page;
    }
}
