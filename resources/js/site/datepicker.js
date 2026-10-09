// Date pickers on the employment application (`.js-flatpickr` inputs; each input's
// data-* attributes carry its formats). Loaded by app.js only on pages that have
// one, as its own chunk with flatpickr's CSS. Spanish pages get Spanish month and
// day names.

import flatpickr from 'flatpickr';
import { Spanish } from 'flatpickr/dist/l10n/es.js';
import 'flatpickr/dist/flatpickr.min.css';

export default function initDatePickers(inputs) {
    const spanish = document.documentElement.lang === 'es';

    return flatpickr(inputs, spanish ? { locale: Spanish } : {});
}
