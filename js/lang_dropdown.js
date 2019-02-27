/**
 * @file
 * Part of module's library.
 */

'use strict';

Drupal.behaviors.lang_drop_down_switcher = {
  attach: function (context, settings) {
    document.querySelector('select.skilld-lang-dropdown-select-element').addEventListener('change', function submitForm(e) {
      e.currentTarget.removeEventListener(e.type, submitForm);
      document.querySelector('.skilld-lang-dropdown-form').submit();
    });
  }
};
