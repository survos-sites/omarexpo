<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    'mobile_app' => [
        'path' => './assets/mobile_app.js',
        'entrypoint' => true,
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    '@survos-mobile/mobile' => [
        'path' => './vendor/survos/mobile-bundle/assets/src/controllers/mobile_controller.js',
    ],
    '@survos-js-twig/dexie' => [
        'path' => './vendor/survos/js-twig-bundle/assets/src/controllers/dexie_controller.js',
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@hotwired/turbo' => [
        'version' => '8.0.12',
    ],
    'twig' => [
        'version' => '1.17.1',
    ],
    'locutus/php/strings/sprintf' => [
        'version' => '2.0.32',
    ],
    'locutus/php/strings/vsprintf' => [
        'version' => '2.0.32',
    ],
    'locutus/php/math/round' => [
        'version' => '2.0.32',
    ],
    'locutus/php/math/max' => [
        'version' => '2.0.32',
    ],
    'locutus/php/math/min' => [
        'version' => '2.0.32',
    ],
    'locutus/php/strings/strip_tags' => [
        'version' => '2.0.32',
    ],
    'locutus/php/datetime/strtotime' => [
        'version' => '2.0.32',
    ],
    'locutus/php/datetime/date' => [
        'version' => '2.0.32',
    ],
    'locutus/php/var/boolval' => [
        'version' => '2.0.32',
    ],
    'dexie' => [
        'version' => '4.0.11',
    ],
    'simple-datatables' => [
        'version' => '9.2.2',
    ],
    'simple-datatables/dist/style.min.css' => [
        'version' => '9.2.2',
        'type' => 'css',
    ],
    'qrcode' => [
        'version' => '1.5.4',
    ],
    'dijkstrajs' => [
        'version' => '1.0.3',
    ],
    'bazinga-translator' => [
        'version' => '7.0.0',
    ],
    'intl-messageformat' => [
        'version' => '10.7.15',
    ],
    'tslib' => [
        'version' => '2.8.1',
    ],
    '@formatjs/fast-memoize' => [
        'version' => '2.2.6',
    ],
    '@formatjs/icu-messageformat-parser' => [
        'version' => '2.11.1',
    ],
    '@formatjs/icu-skeleton-parser' => [
        'version' => '1.8.13',
    ],
    'onsenui' => [
        'version' => '2.12.8',
    ],
    'onsenui/js/onsenui.min.js' => [
        'version' => '2.12.8',
    ],
    'onsenui/css/onsenui-core.min.css' => [
        'version' => '2.12.8',
        'type' => 'css',
    ],
    'onsenui/css/onsen-css-components.min.css' => [
        'version' => '2.12.8',
        'type' => 'css',
    ],
    'onsenui/css/onsenui-fonts.min.css' => [
        'version' => '2.12.8',
        'type' => 'css',
    ],
    'stimulus-attributes' => [
        'version' => '1.0.1',
    ],
    'escape-html' => [
        'version' => '1.0.3',
    ],
    'fos-routing' => [
        'version' => '0.0.6',
    ],
    '@tabler/core' => [
        'version' => '1.0.0',
    ],
    '@tabler/core/dist/css/tabler.min.css' => [
        'version' => '1.0.0',
        'type' => 'css',
    ],
    '@symfony/ux-live-component' => [
        'path' => './vendor/symfony/ux-live-component/assets/dist/live_controller.js',
    ],
];
