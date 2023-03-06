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

namespace WerkraumMedia\ABTest\Hook;

use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController as Typo3TypoScriptFrontendController;
use WerkraumMedia\ABTest\MatomoTracker;
use WerkraumMedia\ABTest\Switcher;

class TypoScriptFrontendController
{
    private Switcher $switcher;

    private MatomoTracker $matomoTracker;

    public function __construct(
        Switcher $switcher,
        MatomoTracker $matomoTracker
    ) {
        $this->switcher = $switcher;
        $this->matomoTracker = $matomoTracker;
    }

    public function determineIdPostProc(
        array $params,
        Typo3TypoScriptFrontendController $frontendController
    ): void {
        $this->switcher->switch($frontendController);
    }

    public function contentPostProcAll(
        array $params,
        Typo3TypoScriptFrontendController $frontendController
    ): void {
        $frontendController->content = $this->matomoTracker->addScriptToHtmlMarkup($frontendController->content);
    }

    public static function register(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['determineId-PostProc'][self::class] = self::class . '->determineIdPostProc';
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['contentPostProc-all'][self::class] = self::class . '->contentPostProcAll';
    }
}
