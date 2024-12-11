import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';
// import './styles/onsen_youtube.css';

// this prevents draggable from working!  so only include it in the mobile app
// import 'https://cdn.jsdelivr.net/npm/onsenui@2.12.8/js/onsenui.min.js';
// window.ons = ons; // make global

// ES2015
import Translator from 'bazinga-translator';
window.Translator = Translator;

// import Sentry from 'sentry';
// Sentry.init({ dsn: 'https://<key>@sentry.io/<project>' });

import 'onsenui/js/onsenui.min.js'
import 'onsenui/css/onsenui-core.min.css'
import 'onsenui/css/onsen-css-components.min.css'
// import 'https://cdn.jsdelivr.net/npm/onsenui@2.12.8/js/onsenui.min.js';
// we can probably be even more precise with https://www.jsdelivr.com/package/npm/onsenui?tab=files&path=css%2Fionicons%2Ffonts

import 'onsenui/css/onsenui-fonts.min.css'

