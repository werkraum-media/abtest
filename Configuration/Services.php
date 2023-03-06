<?php

declare(strict_types=1);

namespace DanielSiepmann\Configuration;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use WerkraumMedia\ABTest\Events\SwitchedToVariant;
use WerkraumMedia\ABTest\Hook\TypoScriptFrontendController;
use WerkraumMedia\ABTest\Middleware\SetCookie;
use WerkraumMedia\ABTest\TCA\VariantFilter;

return static function (ContainerConfigurator $containerConfigurator) {
    $services = $containerConfigurator
        ->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
    ;

    $services->load('WerkraumMedia\\ABTest\\', '../Classes/');

    $services->set(TypoScriptFrontendController::class)->public();
    $services->set(VariantFilter::class)->public();
    $services->set(SetCookie::class)->tag('event.listener', [
        'method' => 'handleVariant',
        'event' => SwitchedToVariant::class,
    ]);
};
