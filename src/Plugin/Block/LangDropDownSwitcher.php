<?php

namespace Drupal\skilld_lang_dropdown\Plugin\Block;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Asset\LibraryDiscovery;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\skilld_lang_dropdown\Form\LanguageDropdownForm;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface;

/**
 * This block displays languages list as dropdown.
 *
 * @Block(
 *   id = "lang_drop_down_switcher",
 *   admin_label = @Translation("Language DropDown Switcher"),
 *   category = @Translation("Skilld custom blocks"),
 *   deriver = "Drupal\lang_dropdown\Plugin\Derivative\LanguageDropdownBlock"
 * )
 */
class LangDropDownSwitcher extends BlockBase implements ContainerFactoryPluginInterface {

  const LANGDROPDOWN_SIMPLE_SELECT = 0;

  const LANGDROPDOWN_LIST_LINK = 1;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The path matcher.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected $pathMatcher;

  /**
   * The MIME type guesser.
   *
   * @var \Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface
   */
  protected $mimeTypeGuesser;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The library discovery service.
   *
   * @var \Drupal\Core\Asset\LibraryDiscovery
   */
  protected $libraryDiscovery;

  /**
   * Constructs an LanguageBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user account.
   * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
   *   The path matcher.
   * @param \Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface $mime_type_guesser
   *   The MIME type guesser instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder service.
   * @param \Drupal\Core\Asset\LibraryDiscovery $library_discovery
   *   The library discovery service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LanguageManagerInterface $language_manager,
    AccountProxyInterface $current_user,
    PathMatcherInterface $path_matcher,
    MimeTypeGuesserInterface $mime_type_guesser,
    ModuleHandlerInterface $module_handler,
    FormBuilderInterface $form_builder,
    LibraryDiscovery $library_discovery
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->languageManager = $language_manager;
    $this->currentUser = $current_user;
    $this->pathMatcher = $path_matcher;
    $this->mimeTypeGuesser = $mime_type_guesser;
    $this->moduleHandler = $module_handler;
    $this->formBuilder = $form_builder;
    $this->libraryDiscovery = $library_discovery;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('language_manager'),
      $container->get('current_user'),
      $container->get('path.matcher'),
      $container->get('file.mime_type.guesser'),
      $container->get('module_handler'),
      $container->get('form_builder'),
      $container->get('library.discovery')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'widget' => self::LANGDROPDOWN_LIST_LINK,
      'hidden_languages' => [],
      'display' => 0,
      'lang_names' => [],
      'lang_icons' => [],
      'lang_icons_position' => -10,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $languages = $this->languageManager->getLanguages();

    $roles = user_roles();

    $form['widget'] = [
      '#type' => 'select',
      '#title' => $this->t('Output type'),
      '#options' => [
        self::LANGDROPDOWN_LIST_LINK => $this->t('Simple List'),
        self::LANGDROPDOWN_SIMPLE_SELECT => $this->t('Simple HTML select'),
      ],
      '#default_value' => $this->configuration['widget'],
    ];

    $form['display'] = [
      '#type' => 'select',
      '#title' => $this->t('Display format'),
      '#options' => [
        1 => $this->t('Language Native Name'),
        2 => $this->t('Language Code'),
        3 => $this->t('Set custom names'),
        4 => $this->t('Use icons for languages'),
        5 => $this->t('Use names and icons for languages'),
      ],
      '#default_value' => $this->configuration['display'],
    ];

    $form['lang_names'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Custom language names'),
      '#states' => [
        'visible' => [
          [':input[name="settings[display]"]' => ['value' => 3]],
          'or',
          [':input[name="settings[display]"]' => ['value' => 5]],
        ],
      ],
    ];
    foreach ($languages as $code => $language) {
      $form['lang_names'][$code] = [
        '#type' => 'textfield',
        '#title' => $language->getName(),
        '#default_value' => isset($this->configuration['lang_names'][$code]) ? $this->configuration['lang_names'][$code] : '',
      ];
    }
    $form['lang_icons'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Custom language icons'),
      '#states' => [
        'visible' => [
          [':input[name="settings[display]"]' => ['value' => 4]],
          'or',
          [':input[name="settings[display]"]' => ['value' => 5]],
        ],
      ],
    ];
    $form['lang_icons']['lang_icons_position'] = [
      '#type' => 'select',
      '#title' => $this->t('Icon position'),
      '#weight' => 10,
      '#options' => [
        -10 => $this->t('Before'),
        10 => $this->t('After'),
      ],
      '#default_value' => $this->configuration['lang_icons_position'],
    ];
    foreach ($languages as $code => $language) {
      $form['lang_icons'][$code] = [
        '#type' => 'textfield',
        '#title' => $this->t('Path for language icon for %path', ['%path' => $language->getName()]),
        '#default_value' => isset($this->configuration['lang_icons'][$code]) ? $this->configuration['lang_icons'][$code] : '',
      ];
    }

    // Configuration options that allow to hide a specific language
    // to specific roles.
    $form['hideout'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Hide language settings'),
      '#description' => $this->t('Select which languages you want to hide to specific roles.'),
      '#weight' => 4,
    ];

    $role_names = [];
    $role_languages = [];
    foreach ($roles as $rid => $role) {
      // Retrieve role names for columns.
      $role_names[$rid] = new FormattableMarkup($role->label(), []);
      // Fetch languages for the roles.
      $role_languages[$rid] = !empty($this->configuration['hidden_languages'][$rid]) ? $this->configuration['hidden_languages'][$rid] : [];
    }

    // Store $role_names for use when saving the data.
    $form['hideout']['role_names'] = [
      '#type' => 'value',
      '#value' => $role_names,
    ];

    $form['hideout']['languages'] = [
      '#type' => 'table',
      '#header' => [$this->t('Languages')],
      '#id' => 'hidden_languages_table',
      '#sticky' => TRUE,
    ];

    foreach ($role_names as $name) {
      $form['hideout']['languages']['#header'][] = [
        'data' => $name,
        'class' => ['checkbox'],
      ];
    }

    foreach ($languages as $code => $language) {
      $form['hideout']['languages'][$code]['language'] = [
        '#type' => 'item',
        '#markup' => $language->getName(),
      ];

      foreach ($role_names as $rid => $role) {
        $form['hideout']['languages'][$code][$rid] = [
          '#title' => $rid . ': ' . $language->getName(),
          '#title_display' => 'invisible',
          '#wrapper_attributes' => [
            'class' => ['checkbox'],
          ],
          '#type' => 'checkbox',
          '#default_value' => in_array($code, $role_languages[$rid], FALSE) ? 1 : 0,
          '#attributes' => ['class' => ['rid-' . $rid]],
        ];
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['lang_icons_position'] = $form_state->getValue(['lang_icons', 'lang_icons_position']);
    $lang_dropdown = $form_state->getValue('hideout');
    $this->configuration['hidden_languages'] = [];
    foreach ($lang_dropdown['languages'] as $code => $values) {
      unset($values['language']);
      foreach ($values as $rid => $value) {
        if ($value) {
          $this->configuration['hidden_languages'][$rid][] = $code;
        }
      }
    }
    $this->configuration['display'] = $form_state->getValue('display');
    $this->configuration['lang_names'] = $form_state->getValue('lang_names');
    $this->configuration['lang_icons'] = $form_state->getValue('lang_icons');
    unset($this->configuration['lang_icons']['lang_icons_position']);
    $this->configuration['widget'] = $form_state->getValue('widget');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    $route_name = $this->pathMatcher->isFrontPage() ? '<front>' : '<current>';
    $links = $this->languageManager->getLanguageSwitchLinks(
      Language::TYPE_URL,
      Url::fromRoute($route_name)
    );
    $roles = $this->currentUser->getRoles();

    if (isset($links->links)) {
      foreach ($links->links as $langcode => &$link) {
        foreach ($roles as $role) {
          if (isset($this->configuration['hidden_languages'][$role]) &&
            in_array($langcode, $this->configuration['hidden_languages'][$role])
          ) {
            continue 2;
          }
        }
        switch ($this->configuration['display']) {
          // Language Native Name.
          case 1:
          default:
            break;

          // Language Code.
          case 2:
            $link['title'] = $langcode;
            break;

          // Set custom names.
          case 3:
            if (!empty($this->configuration['lang_names'][$langcode])) {
              $link['title'] = $this->configuration['lang_names'][$langcode];
            }
            break;

          // Use icons for languages.
          case 4:
          case 5:
            $title = $this->configuration['display'] == 4 ? $link['title'] : $this->configuration['lang_names'][$langcode];
            $link['title'] = [
              '#type' => 'container',
            ];
            $link['title']['title'] = [
              '#markup' => $title,
            ];
            if (!empty($this->configuration['lang_icons'][$langcode])) {
              $weight = $this->configuration['lang_icons_position'];
              $link['title']['icon'] = $this->buildIcons(
                $this->configuration['lang_icons'][$langcode],
                $title
              );
              $link['title']['icon']['#weight'] = $weight;

            }
            break;
        }
      }

      switch ($this->configuration['widget']) {
        case self::LANGDROPDOWN_LIST_LINK:
          $output = [
            '#theme' => 'links__language_block',
            '#links' => $links->links,
            '#prefix' => '<div class="dropdown">',
            '#sufix' => '</div>',
            '#attributes' => [
              'class' => [
                "language-switcher-{$links->method_id}",
              ],
            ],
            '#cache' => [
              'contexts' => [
                'user.permissions',
                'url.path',
                'url.query_args',
              ],
            ],
          ];
          break;

        case LANGDROPDOWN_SIMPLE_SELECT:
          $form = $this->formBuilder->getForm(LanguageDropdownForm::class, $links->links, $this->configuration);

          $output = [
            'lang_dropdown_form' => $form,
            '#cache' => [
              'contexts' => [
                'user.permissions',
                'url.path',
                'url.query_args',
              ],
            ],
          ];
      }

      return $output;
    }
    return $build;
  }

  /**
   * Build renderable array for lang icon.
   *
   * @param string $path
   *   Icon path.
   * @param string $title
   *   Title for image.
   *
   * @return array
   *   A renderable array representing the content of icon.
   */
  private function buildIcons($path = NULL, $title = '') {
    $filemime = $this->mimeTypeGuesser->guess($path);
    if ($filemime == 'image/svg+xml') {
      $svgRaw = file_get_contents($path);
      if ($svgRaw) {
        $svgRaw = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $svgRaw);
        $svgRaw = trim($svgRaw);
        $icon = [
          '#markup' => Markup::create($svgRaw),
        ];
      }

    }
    else {
      $icon = [
        '#theme' => 'image',
        '#uri' => Html::escape($path),
        '#alt' => $title,
        '#title' => $title,
        '#attributes' => ['class' => ['language-icon']],
      ];
    }
    return $icon;
  }

}
