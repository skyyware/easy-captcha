import 'altcha/external';
import 'altcha/i18n/de';
import { install } from './forms.mjs';

globalThis.$altcha.algorithms.set('PBKDF2/SHA-256', () => new Worker(new URL('./pbkdf2.js', import.meta.url)));
const element = document.querySelector('[data-starcode-config]');
if (element) window.EasyCaptcha = window.Starcode = install(window, JSON.parse(element.dataset.starcodeConfig));
document.querySelector('[data-starcode-back]')?.addEventListener('click', () => history.back());
