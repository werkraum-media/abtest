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

use WerkraumMedia\ABTest\Events\SwitchedToVariant;

class MatomoTracker
{
    private string $experiment = '';

    private string $variation = '';

    public function handleVariant(SwitchedToVariant $event): void
    {
        $this->experiment = $event->getOriginalPage()['tx_abtest_matomo_experiment_id'] ?? '';
        $this->variation = $event->getVariantPage()['tx_abtest_matomo_variant_id'] ?? '';
    }

    public function addScriptToHtmlMarkup(string $markup): string
    {
        if ($this->experiment === '' || $this->variation === '') {
            return $markup;
        }

        $script = $this->generateScript();
        return str_replace('</body>', $script . '</body>', $markup);
    }

    private function generateScript(): string
    {
        $experiment = htmlspecialchars($this->experiment);
        $variation = htmlspecialchars($this->variation);

        return '<script>'
            . 'var _paq = window._paq = window._paq || [];'
            . "_paq.push(['AbTesting::enter', {experiment: '$experiment', variation: '$variation'}]);"
            . '</script>'
            . PHP_EOL
        ;
    }
}
