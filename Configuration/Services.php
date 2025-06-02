<?php

declare(strict_types=1);

namespace DanielSiepmann\Configuration;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;
use TYPO3\CMS\Frontend\Event\BeforePageIsResolvedEvent;
use WerkraumMedia\ABTest\Events\SwitchedToVariant;
use WerkraumMedia\ABTest\MatomoTracker;
use WerkraumMedia\ABTest\Middleware\SetCookie;
use WerkraumMedia\ABTest\Switcher;
use WerkraumMedia\ABTest\TCA\VariantFilter;

return static function (ContainerConfigurator $containerConfigurator) {
    $services = $containerConfigurator
        ->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
    ;

    $services->load('WerkraumMedia\\ABTest\\', '../Classes/');

    $services->set(VariantFilter::class)->public();
    $services->set(SetCookie::class)->tag('event.listener', [
        'method' => 'handleVariant',
        'event' => SwitchedToVariant::class,
    ]);
    $services->set(MatomoTracker::class)
        ->tag('event.listener', [
            'method' => 'handleVariant',
            'event' => SwitchedToVariant::class,
        ])
        ->tag('event.listener', [
            'method' => 'addScriptToHtmlMarkup',
            'event' => AfterCacheableContentIsGeneratedEvent::class,
        ])
    ;
    $services->set(Switcher::class)->tag('event.listener', [
        'method' => 'switch',
        'event' => BeforePageIsResolvedEvent::class,
    ]);
};
