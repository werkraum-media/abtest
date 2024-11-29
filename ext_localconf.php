<?php

defined('TYPO3_MODE') or die();
if (PHP_SAPI !== 'cli') {
    \WerkraumMedia\ABTest\Hook\TypoScriptFrontendController::register();
}
