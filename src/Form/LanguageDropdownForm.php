<?php

namespace Drupal\skilld_lang_dropdown\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Language Switch Form.
 */
class LanguageDropdownForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'skilld_lang_dropdown_form';
  }

  /**
   * LanguageDropdownForm constructor.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   Language manager.
   * @param \Drupal\Core\Path\PathMatcherInterface $pathMatcher
   *   Patch matcher.
   */
  public function __construct(LanguageManagerInterface $languageManager, PathMatcherInterface $pathMatcher) {
    $this->languageManager = $languageManager;
    $this->patchMatcher = $pathMatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static (
      $container->get('language_manager'),
      $container->get('path.matcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $build_info = $form_state->getBuildInfo();
    $languages = $build_info['args'][0];
    $settings = $build_info['args'][1];
    $language_session_selected = $this->languageManager->getCurrentLanguage();

    $unique_id = uniqid('skilld_lang_dropdown', TRUE);

    $options = [];

    // Iterate on $languages to build the needed options for the select element.
    foreach ($languages as $lang_code => $lang_options) {
      /** @var \Drupal\Core\Language\LanguageInterface $language */
      $language = $lang_options['language'];

      // Build the options in an associative array,
      // so it will be ready for #options in select form element.
      switch ($settings['display']) {
        case 1:
          $options += [$lang_code => $language->getName()];
          break;

        case 2:
          $options += [$lang_code => $lang_code];
          break;

        case 3:
          $options += [$lang_code => $lang_options['title']];
          break;

        default:
          $options += [$lang_code => $lang_options['title']['title']['#markup']];
      }
    }

    // Now we build the $form array.
    $form['lang_dropdown_select'] = [
      '#title' => $this->t('Select your language'),
      '#title_display' => 'invisible',
      '#type' => 'select',
      '#default_value' => $language_session_selected->getId(),
      '#options' => $options,
      '#attributes' => [
        'class' => ['skilld-lang-dropdown-select-element'],
        'id' => 'select-' . $unique_id,
      ],
      '#attached' => [
        'library' => ['skilld_lang_dropdown/skilld-lang-dropdown-form'],
      ],
    ];

    $form['#attributes']['class'][] = 'skilld_lang_dropdown_form';
    $form['#attributes']['class'][] = 'clearfix';
    $form['#attributes']['id'] = 'form-' . $unique_id;
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Go'),
      '#noscript' => TRUE,
      // The below prefix & suffix for graceful fallback
      // if JavaScript was disabled.
      '#prefix' => new FormattableMarkup('<noscript><div>', []),
      '#suffix' => new FormattableMarkup('</div></noscript>', []),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $language_code = $form_state->getValue('lang_dropdown_select');
    $language_codes = $this->languageManager->getLanguages();
    $route = $this->patchMatcher->isFrontPage() ? '<front>' : '<current>';
    $url = Url::fromRoute($route);
    $options['language'] = $language_codes[$language_code];
    $options['query'] = $this->getRequest()->query->all();
    $form_state->setRedirect($url->getRouteName(), $url->getRouteParameters(), $options);
  }

}
