<?php

namespace Drupal\iq_barrio_helper\Service;

use Drupal\advagg\Form\OperationsForm;
use Drupal\Core\Form\FormState;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\fontyourface\Entity\Font;

/**
 * Provides a config form to alter stylings and CSS compilation functions.
 */
class IqBarrioService {

  use StringTranslationTrait;

  /**
   * Constructs a IqBarrioService object.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(TranslationInterface $string_translation) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * Updates a definition file with the given styling values.
   *
   * @param array $stylingValues
   *   The styling values to insert.
   * @param string $pathDefinitionTarget
   *   The definition file to write.
   * @param string $pathDefinitionSource
   *   The definition base file.
   */
  public function writeDefinitionsFile(array $stylingValues, string $pathDefinitionTarget, string $pathDefinitionSource = NULL) {

    $definitionSource = "";

    if (!$pathDefinitionSource) {
      $pathDefinitionSource = $pathDefinitionTarget . '.txt';
    }

    if (file_exists($pathDefinitionSource)) {

      $definitionSource = file_get_contents($pathDefinitionSource);

      $definitionCompiled = preg_replace_callback('/\{{(\w+)}}/', function ($match) use ($stylingValues) {
        $matched = $match[0];
        $name = $match[1];
        return $stylingValues[$name] ?? $matched;
      }, $definitionSource);

      file_put_contents($pathDefinitionTarget, $definitionCompiled);
    }
  }

  /**
   * Run the compilation service for custom modules and themes.
   */
  public function compile() {
    $compilationService = \Drupal::service('iq_scss_compiler.compilation_service');
    $compilationService->addSource(\Drupal::root() . '/modules/custom');
    $compilationService->addSource(\Drupal::root() . '/themes/custom');
    $compilationService->compile();
  }

  /**
   * Interpolates configuration values in the scss definition file.
   */
  public function interpolateConfig() {
    $theme_settings = \Drupal::config('system.theme.global')->get() + \Drupal::config('iq_barrio.settings')->get();
    $form_state = new FormState();
    $form_state->setValues($theme_settings);
    \Drupal::service('theme_handler')->getTheme('iq_barrio')->load();
    iq_barrio_form_system_theme_settings_submit([], $form_state);
  }

  /**
   * Reset the aggregated CSS.
   *
   * @todo Replace full cache rebuild by a more precise method.
   */
  public function resetCss() {

    if (\Drupal::moduleHandler()->moduleExists('advagg')) {
      $form = OperationsForm::create(\Drupal::getContainer());
      $form->clearAggregates();
    }
    else {
      // Flush all caches.
      drupal_flush_all_caches();
    }

  }

  /**
   * Add styling form to to Theme settings.
   */
  public function alterThemeSettingsForm(&$form, $formValues) {

    $form['#attached']['library'][] = 'iq_barrio/admin-style';

    $arr_colors = [
      'Colors' => [
        'primary' => $this->t('Primary color'),
        'secondary' => $this->t('Secondary color'),
        'tertiary' => $this->t('Tertiary color'),
        'quaternary' => $this->t('Quaternary color'),
      ],
      'Greyscales' => [
        'grey1' => $this->t('Grey 1'),
        'grey2' => $this->t('Grey 2'),
        'grey3' => $this->t('Grey 3'),
        'grey4' => $this->t('Grey 4'),
        'grey5' => $this->t('Grey 5'),
      ],
      'Constants' => [
        'black' => $this->t('Black'),
        'white' => $this->t('White'),
      ],
    ];

    $arr_fontweights = [
      '100' => $this->t('100 (lightest)'),
      '200' => '200',
      '300' => $this->t('300'),
      '400' => $this->t('400 (normal)'),
      '500' => $this->t('500'),
      '600' => $this->t('600'),
      '700' => $this->t('700 (bold)'),
      '800' => $this->t('800'),
      '900' => $this->t('900 (boldest)'),
    ];

    $arr_fontstyles = [
      'normal' => $this->t('Normal'),
      'italic' => $this->t('Italic'),
    ];

    $arr_texttransform = [
      'none' => $this->t('Normal'),
      'uppercase' => $this->t('Uppercase'),
      'lowercase' => $this->t('Lowercase'),
      'capitalize' => $this->t('Capitalize'),
    ];

    $arr_units = [
      'px' => 'px',
      'em' => 'em',
      'rem' => 'rem',
      '%'  => '%',
    ];

    $arr_fonts = [
      'System Fonts' => [
        'Arial' => $this->t('Arial'),
        'Courier New' => $this->t('Courier New'),
        'Helvetica' => $this->t('Helvetica'),
        'Times New Roman' => $this->t('Times New Roman'),
        'Verdana' => $this->t('Verdana'),
        '\'Open Sans\', sans-serif' => $this->t('Open Sans, sans-serif'),
      ],
    ];

    $moduleHandler = \Drupal::service('module_handler');

    if ($moduleHandler->moduleExists('fontyourface')) {
      $fonts = Font::loadActivatedFonts();
      if (!empty($fonts)) {
        foreach ($fonts as $font) {
          $css_family = $font->css_family->value;
          $arr_google_fonts[$css_family] = $font->css_family->value;
        }
        $arr_fonts['Additional fonts'] = $arr_google_fonts;
      }
    }

    $form['iq_theme'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Custom Theme Settings'),
      '#attributes' => [
        'class' => ['iq-barrio-settings'],
      ],
    ];

    // Section colors.
    $form['color_definitions'] = [
      '#type' => 'details',
      '#title' => $this->t('Colors'),
      // Controls the HTML5 'open' attribute. Defaults to FALSE.
      '#open' => TRUE,
      '#group' => 'iq_theme',
      '#prefix' => '<div id="iq-barrio-target"></div><div id="iq-barrio-source">' . file_get_contents(DRUPAL_ROOT . '/' . \Drupal::service('extension.list.theme')->getPath('iq_barrio') . "/resources/sass/backend-styling-preview.css.txt") . '</div>',
    ];

    $form['color_definitions']['base_definitions'] = [
      '#type' => 'details',
      '#title' => $this->t('Base color definitions'),
    ];

    $form['color_definitions']['base_definitions']['grey_holder'] = [
      '#type' => 'container',
      '#prefix' => '<strong>5 shades of grey</strong>',
      '#attributes' => [
        'class' => ['container-inline', 'unit-combo-field'],
      ],
    ];

    $form['color_definitions']['base_definitions']['grey_holder']['color_black'] = [
      '#type' => 'hidden',
      '#default_value' => $formValues['color_black'] ?? '#000000',
    ];

    $form['color_definitions']['base_definitions']['grey_holder']['color_white'] = [
      '#type' => 'hidden',
      '#default_value' => $formValues['color_white'] ?? '#FFFFFF',
    ];

    $form['color_definitions']['base_definitions']['grey_holder']['color_black'] = [
      '#type' => 'hidden',
      '#default_value' => $formValues['color_black'] ?? '#000000',
    ];

    $form['color_definitions']['base_definitions']['grey_holder']['color_white'] = [
      '#type' => 'hidden',
      '#default_value' => $formValues['color_white'] ?? '#FFFFFF',
    ];

    $form['color_definitions']['base_definitions']['grey_holder']['color_grey1'] = [
      '#type' => 'color',
      '#default_value' => $formValues['color_grey1'] ?? '#333333',
    ];

    $form['color_definitions']['base_definitions']['grey_holder']['color_grey2'] = [
      '#type' => 'color',
      '#default_value' => $formValues['color_grey2'] ?? '#666666',
    ];

    $form['color_definitions']['base_definitions']['grey_holder']['color_grey3'] = [
      '#type' => 'color',
      '#default_value' => $formValues['color_grey3'] ?? '#999999',
    ];

    $form['color_definitions']['base_definitions']['grey_holder']['color_grey4'] = [
      '#type' => 'color',
      '#default_value' => $formValues['color_grey4'] ?? '#cccccc',
    ];

    $form['color_definitions']['base_definitions']['grey_holder']['color_grey5'] = [
      '#type' => 'color',
      '#default_value' => $formValues['color_grey5'] ?? '#eeeeee',
    ];

    $form['color_definitions']['base_definitions']['color_primary'] = [
      '#type' => 'color',
      '#title' => $this->t('Primary color'),
      '#default_value' => $formValues['color_primary'] ?? '#e95e27',
    ];

    $form['color_definitions']['base_definitions']['color_secondary'] = [
      '#type' => 'color',
      '#title' => $this->t('Secondary color'),
      '#default_value' => $formValues['color_secondary'] ?? '#009C82',
    ];

    $form['color_definitions']['base_definitions']['color_tertiary'] = [
      '#type' => 'color',
      '#title' => $this->t('Tertiary color'),
      '#default_value' => $formValues['color_tertiary'] ?? '#9C360D',
    ];

    $form['color_definitions']['base_definitions']['color_quaternary'] = [
      '#type' => 'color',
      '#title' => $this->t('Quaternary color'),
      '#default_value' => $formValues['color_quaternary'] ?? '#27E9C9',
    ];

    $form['color_definitions']['section_definitions'] = [
      '#type' => 'details',
      '#title' => $this->t('Color assignments'),
    ];

    $form['color_definitions']['section_definitions']['color_page_background'] = [
      '#type' => 'select',
      '#title' => $this->t('Color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_page_background'] ?? 'white',
      '#prefix' => '<div class="styling-row"><div class="styling-input"><div class="inline-input-holder separator"><strong>Page Background</strong>',
    ];

    $form['color_definitions']['section_definitions']['opacity_color_page_background'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_color_page_background'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['color_definitions']['section_definitions']['color_page_background_meta_header'] = [
      '#type' => 'select',
      '#title' => $this->t('Background color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_page_background_meta_header'] ?? 'white',
      '#prefix' => '<div class="inline-input-holder"><strong>Meta header</strong>',
    ];

    $form['color_definitions']['section_definitions']['opacity_color_page_background_meta_header'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_color_page_background_meta_header'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['color_definitions']['section_definitions']['color_page_font_meta_header'] = [
      '#type' => 'select',
      '#title' => $this->t('Font color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_page_font_meta_header'] ?? 'grey3',
      '#prefix' => '<div class="inline-input-holder separator">',
    ];

    $form['color_definitions']['section_definitions']['opacity_color_page_font_meta_header'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_color_page_font_meta_header'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['color_definitions']['section_definitions']['color_page_background_header'] = [
      '#type' => 'select',
      '#title' => $this->t('Background color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_page_background_header'] ?? 'white',
      '#prefix' => '<div class="inline-input-holder"><strong>Header</strong>',
    ];

    $form['color_definitions']['section_definitions']['opacity_color_page_background_header'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_color_page_background_header'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['color_definitions']['section_definitions']['color_page_font_header'] = [
      '#type' => 'select',
      '#title' => $this->t('Font color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_page_font_header'] ?? 'grey3',
      '#prefix' => '<div class="inline-input-holder separator">',
    ];

    $form['color_definitions']['section_definitions']['opacity_color_page_font_header'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_color_page_font_header'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['color_definitions']['section_definitions']['color_page_background_footer'] = [
      '#type' => 'select',
      '#title' => $this->t('Background color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_page_background_footer'] ?? 'grey5',
      '#prefix' => '<div class="inline-input-holder"><strong>Footer</strong>',
    ];

    $form['color_definitions']['section_definitions']['opacity_color_page_background_footer'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_color_page_background_footer'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['color_definitions']['section_definitions']['color_page_font_footer'] = [
      '#type' => 'select',
      '#title' => $this->t('Font color footer'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_page_font_footer'] ?? 'grey3',
      '#prefix' => '<div class="inline-input-holder separator">',
    ];

    $form['color_definitions']['section_definitions']['opacity_color_page_font_footer'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_color_page_font_footer'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['color_definitions']['section_definitions']['color_page_background_elements'] = [
      '#type' => 'select',
      '#title' => $this->t('Background color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_page_background_elements'] ?? 'secondary',
      '#prefix' => '<div class="inline-input-holder separator"><strong>Elements (legacy)</strong>',
    ];

    $form['color_definitions']['section_definitions']['opacity_color_page_background_elements'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_color_page_background_elements'] ?? '1',
      '#suffix' => '</div></div><div class="styling-preview page-layout"><div id="color-definitions-preview-page"><div id="color-definitions-preview-meta-header">Meta header</div><div id="color-definitions-preview-header">Header</div><div id="color-definitions-preview-content">Content</div><div id="color-definitions-preview-footer">Footer</div></div></div></div>',
    ];

    // Section Typo.
    $form['typography'] = [
      '#type' => 'details',
      '#title' => $this->t('Typography'),
      '#summary' => $this->t('Typography definitions.'),
      '#group' => 'iq_theme',
    ];

    $form['typography']['headings'] = [
      '#type' => 'details',
      '#title' => $this->t('Headings'),
    ];

    $headings = [
      'h1' => 'Heading 1',
      'h2' => 'Heading 2',
      'h3' => 'Heading 3',
      'h4' => 'Heading 4',
      'h5' => 'Heading 5',
      'h6' => 'Heading 6',
    ];

    foreach ($headings as $tag => $title) {
      $font_family_value = $formValues[$tag . '_font_family'] ?? '000000';
      $color_value = $formValues[$tag . '_color'] ?? 'primary';
      $opacity_value = $formValues[$tag . '_opacity'] ?? 1;

      $color_inverted_value = $formValues[$tag . '_color_inverted'] ?? 'white';
      $opacity_inverted_value = $formValues[$tag . '_opacity_inverted'] ?? '1';

      $font_size_value = $formValues[$tag . '_font_size'] ?? '2.5';
      $font_size_unit_value = $formValues[$tag . '_font_size_unit'] ?? 'rem';

      $font_size_min_value = $formValues[$tag . '_font_size_min'] ?? '2';

      $line_height_value = $formValues[$tag . '_line_height'] ?? '1.2';
      $font_weight_value = $formValues[$tag . '_font_weight'] ?? 'normal';
      $font_style_value = $formValues[$tag . '_font_style'] ?? 'normal';

      $margin_top_value = $formValues[$tag . '_margin_top'] ?? '0';
      $margin_bottom_value = $formValues[$tag . '_margin_bottom'] ?? '30';
      $margin_unit_value = $formValues[$tag . '_margin_unit'] ?? 'px';

      $form['typography']['headings'][$tag . '_color'] = [
        '#prefix' => '<div class="styling-row"><div class="styling-input"><div class="inline-input-holder"><strong>' . $title . '</strong>',
        '#type' => 'select',
        '#title' => $this->t('Color'),
        '#options' => $arr_colors,
        '#default_value' => $color_value,
      ];

      $form['typography']['headings'][$tag . '_opacity'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Opacity'),
        '#default_value' => $opacity_value,
      ];

      $form['typography']['headings'][$tag . '_color_inverted'] = [
        '#type' => 'select',
        '#title' => $this->t('Inverted color'),
        '#options' => $arr_colors,
        '#default_value' => $color_inverted_value,

      ];

      $form['typography']['headings'][$tag . '_opacity_inverted'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Opacity'),
        '#default_value' => $opacity_inverted_value,
        '#suffix' => '</div>',
      ];

      $form['typography']['headings'][$tag . '_font_family'] = [
        '#type' => 'select',
        '#title' => $this->t('Font family'),
        '#options' => $arr_fonts,
        '#default_value' => $font_family_value,
        '#prefix' => '<div class="inline-input-holder">',
      ];

      $form['typography']['headings'][$tag . '_font_weight'] = [
        '#type' => 'select',
        '#options' => $arr_fontweights,
        '#title' => $this->t('Font weight'),
        '#default_value' => $font_weight_value,
      ];

      $form['typography']['headings'][$tag . '_font_style'] = [
        '#type' => 'select',
        '#options' => $arr_fontstyles,
        '#title' => $this->t('Font style'),
        '#default_value' => $font_style_value,
      ];

      $form['typography']['headings'][$tag . '_line_height'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Line height'),
        '#default_value' => $line_height_value,
        '#suffix' => '</div>',
      ];

      $form['typography']['headings'][$tag . '_font_size_min_holder'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['container-inline', 'unit-combo-field', 'separator'],
        ],
        '#prefix' => '<div class="inline-input-holder">',
      ];

      $form['typography']['headings'][$tag . '_font_size_min_holder'][$tag . '_font_size_min'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Font-size<br/>min'),
        '#default_value' => $font_size_min_value,
      ];

      $form['typography']['headings'][$tag . '_font_size_holder'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['container-inline', 'unit-combo-field', 'separator'],
        ],
      ];

      $form['typography']['headings'][$tag . '_font_size_holder'][$tag . '_font_size'] = [
        '#type' => 'textfield',
        '#title' => $this->t('<br/>max'),
        '#default_value' => $font_size_value,
      ];

      $form['typography']['headings'][$tag . '_font_size_holder'][$tag . '_font_size_unit'] = [
        '#type' => 'select',
        '#options' => $arr_units,
        '#default_value' => $font_size_unit_value,
      ];

      $form['typography']['headings'][$tag . '_margin_holder'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['container-inline', 'unit-combo-field', 'multi-valule'],
        ],
      ];

      $form['typography']['headings'][$tag . '_margin_holder'][$tag . '_margin_top'] = [
        '#title' => $this->t('Margin (top, bottom)'),
        '#type' => 'textfield',
        '#default_value' => $margin_top_value,
      ];

      $form['typography']['headings'][$tag . '_margin_holder'][$tag . '_margin_bottom'] = [
        '#type' => 'textfield',
        '#default_value' => $margin_bottom_value,
      ];

      $form['typography']['headings'][$tag . '_margin_holder'][$tag . '_margin_unit'] = [
        '#type' => 'textfield',
        '#type' => 'select',
        '#options' => $arr_units,
        '#default_value' => $margin_unit_value,
        '#suffix' => '</div>',
      ];

      $form['typography']['headings'][$tag . '_title'] = [
        '#prefix' => '</div><div class="styling-preview">',
        '#suffix' => '</div></div>',
        '#type' => 'item',
        '#markup' => '<' . $tag . ' >' . $title . '</' . $tag . '><p>Next element<br/>Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet</p>',
      ];
    }

    $form['typography']['text_elements'] = [
      '#type' => 'details',
      '#title' => $this->t('Text elements'),
    ];

    $text_elements = [
      'standard' => 'Standard text font',
      'small' => 'Small text font',
      'lead' => 'Lead text font',
      'deco_1' => 'Decoration font 1',
      'deco_2' => 'Decoration font 2',
      'deco_3' => 'Decoration font 3',
    ];

    foreach ($text_elements as $tag => $title) {
      $font_family_value = $formValues[$tag . '_font_family'] ?? '000000';
      $color_value = $formValues[$tag . '_color'] ?? 'grey3';
      $opacity_value = $formValues[$tag . '_opacity'] ?? '1';

      $color_inverted_value = $formValues[$tag . '_color_inverted'] ?? 'white';
      $opacity_inverted_value = $formValues[$tag . '_opacity_inverted'] ?? '1';

      $font_size_min_value = $formValues[$tag . '_font_size_min'] ?? '1';
      $font_size_value = $formValues[$tag . '_font_size'] ?? '1.5';
      $font_size_unit_value = $formValues[$tag . '_font_size_unit'] ?? 'rem';
      $line_height_value = $formValues[$tag . '_line_height'] ?? '1.2';
      $font_weight_value = $formValues[$tag . '_font_weight'] ?? '400';
      $font_style_value = $formValues[$tag . '_font_style'] ?? 'normal';

      $margin_top_value = $formValues[$tag . '_margin_top'] ?? '0';
      $margin_bottom_value = $formValues[$tag . '_margin_bottom'] ?? '0.5';
      $margin_unit_value = $formValues[$tag . '_margin_unit'] ?? 'rem';

      $form['typography']['text_elements'][$tag . '_color'] = [
        '#prefix' => '<div class="styling-row"><div class="styling-input"><div class="inline-input-holder"><strong>' . $title . '</strong>',
        '#type' => 'select',
        '#title' => $this->t('Color'),
        '#options' => $arr_colors,
        '#default_value' => $color_value,
      ];

      $form['typography']['text_elements'][$tag . '_opacity'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Opacity'),
        '#default_value' => $opacity_value,
      ];

      $form['typography']['text_elements'][$tag . '_color_inverted'] = [
        '#type' => 'select',
        '#title' => $this->t('Inverted color'),
        '#options' => $arr_colors,
        '#default_value' => $color_inverted_value,
      ];

      $form['typography']['text_elements'][$tag . '_opacity_inverted'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Opacity'),
        '#default_value' => $opacity_inverted_value,
        '#suffix' => '</div>',
      ];

      $form['typography']['text_elements'][$tag . '_font_family'] = [
        '#type' => 'select',
        '#title' => $this->t('Font family'),
        '#options' => $arr_fonts,
        '#default_value' => $font_family_value,
        '#prefix' => '<div class="inline-input-holder">',
      ];

      $form['typography']['text_elements'][$tag . '_font_weight'] = [
        '#type' => 'select',
        '#options' => $arr_fontweights,
        '#title' => $this->t('Font weight'),
        '#default_value' => $font_weight_value,
      ];

      $form['typography']['text_elements'][$tag . '_font_style'] = [
        '#type' => 'select',
        '#options' => $arr_fontstyles,
        '#title' => $this->t('Font style'),
        '#default_value' => $font_style_value,
      ];

      $form['typography']['text_elements'][$tag . '_line_height'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Line height'),
        '#default_value' => $line_height_value,
        '#suffix' => '</div>',
      ];

      $form['typography']['text_elements'][$tag . '_font_size_min_holder'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['container-inline', 'unit-combo-field', 'separator'],
        ],
        '#prefix' => '<div class="inline-input-holder">',
      ];

      $form['typography']['text_elements'][$tag . '_font_size_min_holder'][$tag . '_font_size_min'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Font-size<br/>min'),
        '#default_value' => $font_size_min_value,
      ];

      $form['typography']['text_elements'][$tag . '_font_size_holder'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['container-inline', 'unit-combo-field', 'separator'],
        ],
      ];

      $form['typography']['text_elements'][$tag . '_font_size_holder'][$tag . '_font_size'] = [
        '#type' => 'textfield',
        '#title' => $this->t('<br/>max'),
        '#default_value' => $font_size_value,
      ];

      $form['typography']['text_elements'][$tag . '_font_size_holder'][$tag . '_font_size_unit'] = [
        '#type' => 'select',
        '#options' => $arr_units,
        '#default_value' => $font_size_unit_value,
      ];

      $form['typography']['text_elements'][$tag . '_margin_holder'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'container-inline',
            'unit-combo-field',
            'multi-valule',
            'separator',
          ],
        ],
      ];

      $form['typography']['text_elements'][$tag . '_margin_holder'][$tag . '_margin_top'] = [
        '#title' => $this->t('Margin (top, bottom)'),
        '#type' => 'textfield',
        '#default_value' => $margin_top_value,
      ];

      $form['typography']['text_elements'][$tag . '_margin_holder'][$tag . '_margin_bottom'] = [
        '#type' => 'textfield',
        '#default_value' => $margin_bottom_value,
      ];

      $form['typography']['text_elements'][$tag . '_margin_holder'][$tag . '_margin_unit'] = [
        '#type' => 'textfield',
        '#type' => 'select',
        '#options' => $arr_units,
        '#default_value' => $margin_unit_value,
        '#suffix' => '</div>',
      ];

      $form['typography']['text_elements'][$tag . '_title'] = [
        '#prefix' => '</div><div class="styling-preview">',
        '#suffix' => '</div></div>',
        '#type' => 'item',
        '#markup' => '<p class="' . $tag . '">' . $title . '<br/>Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet</p>',
      ];
    }

    $form['typography']['links'] = [
      '#type' => 'details',
      '#title' => $this->t('Links'),
    ];

    $link_color_value = $formValues['link_color'] ?? 'primary';
    $link_opacity_value = $formValues['link_opacity'] ?? '1';
    $link_color_inverted_value = $formValues['link_color_inverted'] ?? 'white';
    $link_opacity_inverted_value = $formValues['link_opacity_inverted'] ?? '1';

    $link_text_decoration_value = $formValues['link_text_decoration'] ?? 'underline';

    $link_color_hover_value = $formValues['link_color_hover'] ?? 'primary';
    $link_opacity_hover_value = $formValues['link_opacity_hover'] ?? '1';
    $link_color_hover_inverted_value = $formValues['link_color_hover_inverted'] ?? 'white';
    $link_opacity_hover_inverted_value = $formValues['link_opacity_hover_inverted'] ?? '1';

    $link_text_decoration_hover_value = $formValues['link_text_decoration_hover'] ?? 'underline';

    $form['typography']['links']['link_color'] = [
      '#prefix' => '<div class="styling-row"><div class="styling-input"><div class="inline-input-holder separator"><strong>Standard</strong>',
      '#type' => 'select',
      '#title' => $this->t('Color'),
      '#options' => $arr_colors,
      '#default_value' => $link_color_value,
    ];

    $form['typography']['links']['link_opacity'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#title' => $this->t('Opacity'),
      '#default_value' => $link_opacity_value,
    ];

    $form['typography']['links']['link_color_inverted'] = [
      '#type' => 'select',
      '#title' => $this->t('Inverted color'),
      '#options' => $arr_colors,
      '#default_value' => $link_color_inverted_value,
    ];

    $form['typography']['links']['link_opacity_inverted'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $link_opacity_inverted_value,
    ];

    $form['typography']['links']['link_text_decoration'] = [
      '#type' => 'select',
      '#title' => $this->t('Text decoration'),
      '#options' => [
        'underline' => $this->t('Underline'),
        'underline dotted' => $this->t('Dotted'),
        'underline dashed' => $this->t('Dashed'),
        'none' => $this->t('none'),
      ],
      '#default_value' => $link_text_decoration_value,
      '#suffix' => '</div>',
    ];

    $form['typography']['links']['link_color_hover'] = [
      '#type' => 'select',
      '#title' => $this->t('Color'),
      '#options' => $arr_colors,
      '#default_value' => $link_color_hover_value,
      '#prefix' => '<div class="inline-input-holder separator"><strong>Hover</strong>',
    ];

    $form['typography']['links']['link_opacity_hover'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#title' => $this->t('Opacity'),
      '#default_value' => $link_opacity_hover_value,
    ];

    $form['typography']['links']['link_color_hover_inverted'] = [
      '#type' => 'select',
      '#title' => $this->t('Inverted color'),
      '#options' => $arr_colors,
      '#default_value' => $link_color_hover_inverted_value,
    ];

    $form['typography']['links']['link_opacity_hover_inverted'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $link_opacity_hover_inverted_value,
    ];

    $form['typography']['links']['link_text_decoration_hover'] = [
      '#type' => 'select',
      '#title' => $this->t('Text decoration'),
      '#options' => [
        'underline' => $this->t('Underline'),
        'underline dotted' => $this->t('Dotted'),
        'underline dashed' => $this->t('Dashed'),
        'none' => $this->t('none'),
      ],
      '#default_value' => $link_text_decoration_hover_value,
      '#suffix' => '</div>',
    ];

    $form['typography']['links']['link_title'] = [
      '#prefix' => '</div><div class="styling-preview">',
      '#suffix' => '</div></div>',
      '#type' => 'item',
      '#markup' => '<p>Links: </p><p><a href="#" class="preview-link-standard">Standard</a></p><p><a href="#" class="preview-link-hover">Hovered</a></p><p>Inline links within text: Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor <a href="#" class="preview-link-inline">invidunt</a> ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd <a href="#" class="preview-link-inline">gubergren, no sea takimata sanctus est</a> Lorem ipsum dolor sit amet</p>',
    ];

    // Section navigation.
    $form['navigation'] = [
      '#type' => 'details',
      '#title' => $this->t('Navigation'),
      '#summary' => $this->t('Navigation settings'),
      '#group' => 'iq_theme',
    ];

    $form['navigation']['navi_main'] = [
      '#type' => 'details',
      '#title' => $this->t('Navigation Main'),
    ];

    $form['navigation']['navi_main']['navi_main_font_family'] = [
      '#prefix' => '<div class="styling-row"><div class="styling-input"><div class="inline-input-holder"><strong>Base styling</strong>',
      '#type' => 'select',
      '#title' => $this->t('Font family'),
      '#options' => $arr_fonts,
      '#default_value' => $formValues['navi_main_font_family'] ?? 'Arial',
    ];

    $form['navigation']['navi_main']['navi_main_font_weight'] = [
      '#type' => 'select',
      '#options' => $arr_fontweights,
      '#title' => $this->t('Font weight'),
      '#default_value' => $formValues['navi_main_font_weight'] ?? 'normal',
    ];

    $form['navigation']['navi_main']['navi_main_font_style'] = [
      '#type' => 'select',
      '#options' => $arr_fontstyles,
      '#title' => $this->t('Font style'),
      '#default_value' => $formValues['navi_main_font_style'] ?? 'normal',
    ];

    $form['navigation']['navi_main']['navi_main_text_transform'] = [
      '#type' => 'select',
      '#title' => $this->t('Text Transform'),
      '#default_value' => $formValues['navi_main_text_transform'] ?? 'none',
      '#options' => $arr_texttransform,
      '#suffix' => '</div>',
    ];

    $form['navigation']['navi_main']['navi_main_font_size_holder'] = [
      '#type' => 'container',
      '#prefix' => '<div class="inline-input-holder">',
      '#attributes' => [
        'class' => ['container-inline', 'unit-combo-field', 'separator'],
      ],
    ];

    $form['navigation']['navi_main']['navi_main_font_size_holder']['navi_main_font_size'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Font size'),
      '#default_value' => $formValues['navi_main_font_size'] ?? 1,
    ];

    $form['navigation']['navi_main']['navi_main_font_size_holder']['navi_main_font_size_unit'] = [
      '#type' => 'select',
      '#options' => $arr_units,
      '#default_value' => $formValues['navi_main_font_size_unit'] ?? 'rem',
    ];

    $form['navigation']['navi_main']['navi_main_line_height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Line height'),
      '#default_value' => $formValues['navi_main_line_height'] ?? 2,
    ];

    $form['navigation']['navi_main']['navi_main_border_width_holder'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'container-inline',
          'unit-combo-field',
          'multi-valule',
          'separator',
        ],
      ],
    ];

    $form['navigation']['navi_main']['navi_main_border_width_holder']['navi_main_border_width_top'] = [
      '#title' => $this->t('Border (top, right, bottom, left)'),
      '#type' => 'textfield',
      '#default_value' => $formValues['navi_main_border_width_top'] ?? '0',
    ];

    $form['navigation']['navi_main']['navi_main_border_width_holder']['navi_main_border_width_right'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['navi_main_border_width_right'] ?? '0',
    ];

    $form['navigation']['navi_main']['navi_main_border_width_holder']['navi_main_border_width_bottom'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['navi_main_border_width_bottom'] ?? '0',
    ];

    $form['navigation']['navi_main']['navi_main_border_width_holder']['navi_main_border_width_left'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['navi_main_border_width_left'] ?? '0',
    ];

    $form['navigation']['navi_main']['navi_main_border_width_holder']['navi_main_border_width_unit'] = [
      '#type' => 'textfield',
      '#type' => 'select',
      '#options' => $arr_units,
      '#default_value' => $formValues['navi_main_border_width_unit'] ?? 'px',
      '#suffix' => '</div>',
    ];

    $form['navigation']['navi_main']['navi_main_margin_holder'] = [
      '#type' => 'container',
      '#prefix' => '<div class="inline-input-holder separator">',
      '#attributes' => [
        'class' => [
          'container-inline',
          'unit-combo-field',
          'multi-valule',
          'separator',
        ],
      ],
    ];

    $form['navigation']['navi_main']['navi_main_margin_holder']['navi_main_margin_top'] = [
      '#title' => $this->t('Margin (top, left/right, bottom)'),
      '#type' => 'textfield',
      '#default_value' => $formValues['navi_main_margin_top'] ?? '0',
    ];

    $form['navigation']['navi_main']['navi_main_margin_holder']['navi_main_margin_left_right'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['navi_main_margin_left_right'] ?? '0',
    ];

    $form['navigation']['navi_main']['navi_main_margin_holder']['navi_main_margin_bottom'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['navi_main_margin_bottom'] ?? '0',
    ];

    $form['navigation']['navi_main']['navi_main_margin_holder']['navi_main_margin_unit'] = [
      '#type' => 'textfield',
      '#type' => 'select',
      '#options' => $arr_units,
      '#default_value' => $formValues['navi_main_margin_unit'] ?? 'px',
    ];

    $form['navigation']['navi_main']['navi_main_padding_holder'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['container-inline', 'unit-combo-field', 'multi-valule'],
      ],
      '#suffix' => '</div>',
    ];

    $form['navigation']['navi_main']['navi_main_padding_holder']['navi_main_padding_top'] = [
      '#title' => $this->t('Padding (top, left/right, bottom)'),
      '#type' => 'textfield',
      '#default_value' => $formValues['navi_main_padding_top'] ?? '0',
    ];

    $form['navigation']['navi_main']['navi_main_padding_holder']['navi_main_padding_left_right'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['navi_main_padding_left_right'] ?? '0',
    ];

    $form['navigation']['navi_main']['navi_main_padding_holder']['navi_main_padding_bottom'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['navi_main_padding_bottom'] ?? '0',
    ];

    $form['navigation']['navi_main']['navi_main_padding_holder']['navi_main_padding_unit'] = [
      '#type' => 'textfield',
      '#type' => 'select',
      '#options' => $arr_units,
      '#default_value' => $formValues['navi_main_padding_unit'] ?? 'px',
    ];

    $form['navigation']['navi_main']['color_page_font_navbar'] = [
      '#prefix' => '<div class="inline-input-holder"><strong>Standard</strong>',
      '#type' => 'select',
      '#title' => $this->t('Font color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_page_font_navbar'] ?? 'white',
    ];

    $form['navigation']['navi_main']['opacity_page_font_navbar'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#default_value' => $formValues['opacity_page_font_navbar'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['navigation']['navi_main']['color_page_background_navbar'] = [
      '#prefix' => '<div class="inline-input-holder">',
      '#type' => 'select',
      '#title' => $this->t('Background color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_page_background_navbar'] ?? 'grey3',
    ];

    $form['navigation']['navi_main']['opacity_page_background_navbar'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_page_background_navbar'] ?? '1',
    ];

    $form['navigation']['navi_main']['color_page_border_navbar'] = [
      '#type' => 'select',
      '#title' => $this->t('Border color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_page_border_navbar'] ?? 'grey3',
    ];

    $form['navigation']['navi_main']['opacity_page_border_navbar'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_page_border_navbar'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['navigation']['navi_main']['color_page_font_navbar_hover'] = [
      '#prefix' => '<div class="inline-input-holder"><strong>Navbar hover</strong>',
      '#type' => 'select',
      '#title' => $this->t('Font color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_page_font_navbar_hover'] ?? 'primary',
    ];

    $form['navigation']['navi_main']['opacity_page_font_navbar_hover'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_page_font_navbar_hover'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['navigation']['navi_main']['color_page_background_navbar_hover'] = [
      '#prefix' => '<div class="inline-input-holder">',
      '#type' => 'select',
      '#title' => $this->t('Background color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_page_background_navbar_hover'] ?? 'white',
    ];

    $form['navigation']['navi_main']['opacity_page_background_navbar_hover'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#default_value' => $formValues['opacity_page_background_navbar_hover'] ?? '1',
    ];

    $form['navigation']['navi_main']['color_page_border_navbar_hover'] = [
      '#type' => 'select',
      '#title' => $this->t('Border color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_page_border_navbar_hover'] ?? 'white',
    ];

    $form['navigation']['navi_main']['opacity_page_border_navbar_hover'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_page_border_navbar_hover'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['navigation']['navi_main']['color_page_font_navbar_active'] = [
      '#prefix' => '<div class="inline-input-holder"><strong>Navbar active</strong>',
      '#type' => 'select',
      '#title' => $this->t('Font color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_page_font_navbar_active'] ?? 'primary',
    ];

    $form['navigation']['navi_main']['opacity_page_font_navbar_active'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#default_value' => $formValues['opacity_page_font_navbar_active'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['navigation']['navi_main']['color_page_background_navbar_active'] = [
      '#prefix' => '<div class="inline-input-holder separator">',
      '#type' => 'select',
      '#title' => $this->t('Background color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_page_background_navbar_active'] ?? 'black',
    ];

    $form['navigation']['navi_main']['opacity_page_background_navbar_active'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_page_background_navbar_active'] ?? '1',
    ];

    $form['navigation']['navi_main']['color_page_border_navbar_active'] = [
      '#type' => 'select',
      '#title' => $this->t('Border color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_page_border_navbar_active'] ?? 'black',
    ];

    $form['navigation']['navi_main']['opacity_page_border_navbar_active'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_page_border_navbar_active'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['navigation']['navi_main']['navi_sub_font_family'] = [
      '#prefix' => '<div class="inline-input-holder"><strong>Dropdown styling</strong>',
      '#type' => 'select',
      '#title' => $this->t('Font family'),
      '#options' => $arr_fonts,
      '#default_value' => $formValues['navi_sub_font_family'] ?? 'Arial',
    ];

    $form['navigation']['navi_main']['navi_sub_font_weight'] = [
      '#type' => 'select',
      '#options' => $arr_fontweights,
      '#title' => $this->t('Font weight'),
      '#default_value' => $formValues['navi_sub_font_weight'] ?? 'normal',
    ];

    $form['navigation']['navi_main']['navi_sub_font_style'] = [
      '#type' => 'select',
      '#options' => $arr_fontstyles,
      '#title' => $this->t('Font style'),
      '#default_value' => $formValues['navi_sub_font_style'] ?? 'normal',
    ];

    $form['navigation']['navi_main']['navi_sub_text_transform'] = [
      '#type' => 'select',
      '#title' => $this->t('Text Transform'),
      '#default_value' => $formValues['navi_sub_text_transform'] ?? 'none',
      '#options' => $arr_texttransform,
      '#suffix' => '</div>',
    ];

    $form['navigation']['navi_main']['navi_sub_font_size_holder'] = [
      '#type' => 'container',
      '#prefix' => '<div class="inline-input-holder">',
      '#attributes' => [
        'class' => ['container-inline', 'unit-combo-field', 'separator'],
      ],
    ];

    $form['navigation']['navi_main']['navi_sub_font_size_holder']['navi_sub_font_size'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Font size'),
      '#default_value' => $formValues['navi_sub_font_size'] ?? 1,
    ];

    $form['navigation']['navi_main']['navi_sub_font_size_holder']['navi_sub_font_size_unit'] = [
      '#type' => 'select',
      '#options' => $arr_units,
      '#default_value' => $formValues['navi_sub_font_size_unit'] ?? 'rem',
    ];

    $form['navigation']['navi_main']['navi_sub_line_height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Line height'),
      '#default_value' => $formValues['navi_sub_line_height'] ?? 2,
    ];

    $form['navigation']['navi_main']['navi_sub_border_width_holder'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'container-inline',
          'unit-combo-field',
          'multi-valule',
          'separator',
        ],
      ],
    ];

    $form['navigation']['navi_main']['navi_sub_border_width_holder']['navi_sub_border_width_top'] = [
      '#title' => $this->t('Border (top, right, bottom, left)'),
      '#type' => 'textfield',
      '#default_value' => $formValues['navi_sub_border_width_top'] ?? '0',
    ];

    $form['navigation']['navi_main']['navi_sub_border_width_holder']['navi_sub_border_width_right'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['navi_sub_border_width_right'] ?? '0',
    ];

    $form['navigation']['navi_main']['navi_sub_border_width_holder']['navi_sub_border_width_bottom'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['navi_sub_border_width_bottom'] ?? '0',
    ];

    $form['navigation']['navi_main']['navi_sub_border_width_holder']['navi_sub_border_width_left'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['navi_sub_border_width_left'] ?? '0',
    ];

    $form['navigation']['navi_main']['navi_sub_border_width_holder']['navi_sub_border_width_unit'] = [
      '#type' => 'textfield',
      '#type' => 'select',
      '#options' => $arr_units,
      '#default_value' => $formValues['navi_sub_border_width_unit'] ?? 'px',
      '#suffix' => '</div>',
    ];

    $form['navigation']['navi_main']['navi_sub_margin_holder'] = [
      '#type' => 'container',
      '#prefix' => '<div class="inline-input-holder separator">',
      '#attributes' => [
        'class' => [
          'container-inline',
          'unit-combo-field',
          'multi-valule',
          'separator',
        ],
      ],
    ];

    $form['navigation']['navi_main']['navi_sub_margin_holder']['navi_sub_margin_top'] = [
      '#title' => $this->t('Margin (top, left/right, bottom)'),
      '#type' => 'textfield',
      '#default_value' => $formValues['navi_sub_margin_top'] ?? '0',
    ];

    $form['navigation']['navi_main']['navi_sub_margin_holder']['navi_sub_margin_left_right'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['navi_sub_margin_left_right'] ?? '0',
    ];

    $form['navigation']['navi_main']['navi_sub_margin_holder']['navi_sub_margin_bottom'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['navi_sub_margin_bottom'] ?? '0',
    ];

    $form['navigation']['navi_main']['navi_sub_margin_holder']['navi_sub_margin_unit'] = [
      '#type' => 'textfield',
      '#type' => 'select',
      '#options' => $arr_units,
      '#default_value' => $formValues['navi_sub_margin_unit'] ?? 'px',
    ];

    $form['navigation']['navi_main']['navi_sub_padding_holder'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['container-inline', 'unit-combo-field', 'multi-valule'],
      ],
      '#suffix' => '</div>',
    ];

    $form['navigation']['navi_main']['navi_sub_padding_holder']['navi_sub_padding_top'] = [
      '#title' => $this->t('Padding (top, left/right, bottom)'),
      '#type' => 'textfield',
      '#default_value' => $formValues['navi_sub_padding_top'] ?? '0',
    ];

    $form['navigation']['navi_main']['navi_sub_padding_holder']['navi_sub_padding_left_right'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['navi_sub_padding_left_right'] ?? '0',
    ];

    $form['navigation']['navi_main']['navi_sub_padding_holder']['navi_sub_padding_bottom'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['navi_sub_padding_bottom'] ?? '0',
    ];

    $form['navigation']['navi_main']['navi_sub_padding_holder']['navi_sub_padding_unit'] = [
      '#type' => 'textfield',
      '#type' => 'select',
      '#options' => $arr_units,
      '#default_value' => $formValues['navi_sub_padding_unit'] ?? 'px',
    ];

    $form['navigation']['navi_main']['color_page_font_nav_dropdown'] = [
      '#prefix' => '<div class="inline-input-holder"><strong>Standard</strong>',
      '#type' => 'select',
      '#title' => $this->t('Font color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_page_font_nav_dropdown'] ?? 'grey3',
    ];

    $form['navigation']['navi_main']['opacity_page_font_nav_dropdown'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#default_value' => $formValues['opacity_page_font_nav_dropdown'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['navigation']['navi_main']['color_page_background_nav_dropdown'] = [
      '#prefix' => '<div class="inline-input-holder">',
      '#type' => 'select',
      '#title' => $this->t('Background'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_page_background_nav_dropdown'] ?? 'primary',
    ];

    $form['navigation']['navi_main']['opacity_page_background_nav_dropdown'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_page_background_nav_dropdown'] ?? '1',
    ];

    $form['navigation']['navi_main']['color_page_border_nav_dropdown'] = [
      '#type' => 'select',
      '#title' => $this->t('Border color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_page_border_nav_dropdown'] ?? 'primary',
    ];

    $form['navigation']['navi_main']['opacity_page_border_nav_dropdown'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_page_border_nav_dropdown'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['navigation']['navi_main']['color_page_font_nav_dropdown_hover'] = [
      '#prefix' => '<div class="inline-input-holder"><strong>Hover</strong>',
      '#type' => 'select',
      '#title' => $this->t('Font color'),
      '#default_value' => $formValues['color_page_font_nav_dropdown_hover'] ?? 'grey3',
      '#options' => $arr_colors,
      '#suffix' => '<div class="line-break"></div>',
    ];

    $form['navigation']['navi_main']['opacity_page_font_nav_dropdown_hover'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_page_font_nav_dropdown_hover'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['navigation']['navi_main']['color_page_background_nav_dropdown_hover'] = [
      '#prefix' => '<div class="inline-input-holder">',
      '#type' => 'select',
      '#options' => $arr_colors,
      '#title' => $this->t('Background color'),
      '#default_value' => $formValues['color_page_background_nav_dropdown_hover'] ?? 'primary',
    ];

    $form['navigation']['navi_main']['opacity_page_background_nav_dropdown_hover'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_page_background_nav_dropdown_hover'] ?? '1',
    ];

    $form['navigation']['navi_main']['color_page_border_nav_dropdown_hover'] = [
      '#type' => 'select',
      '#title' => $this->t('Border color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_page_border_nav_dropdown_hover'] ?? 'grey3',
    ];

    $form['navigation']['navi_main']['opacity_page_border_nav_dropdown_hover'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_page_border_nav_dropdown_hover'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['navigation']['navi_main']['color_page_font_nav_dropdown_active'] = [
      '#prefix' => '<div class="inline-input-holder"><strong>Active</strong>',
      '#type' => 'select',
      '#title' => $this->t('Font color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_page_font_nav_dropdown_active'] ?? 'primary',
      '#suffix' => '<div class="line-break"></div>',
    ];

    $form['navigation']['navi_main']['opacity_page_font_nav_dropdown_active'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_page_font_nav_dropdown_active'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['navigation']['navi_main']['color_page_background_nav_dropdown_active'] = [
      '#prefix' => '<div class="inline-input-holder">',
      '#type' => 'select',
      '#title' => $this->t('Background color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_page_background_nav_dropdown_active'] ?? 'grey3',
    ];

    $form['navigation']['navi_main']['opacity_page_background_nav_dropdown_active'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#default_value' => $formValues['opacity_page_background_nav_dropdown_active'] ?? '1',
    ];

    $form['navigation']['navi_main']['color_page_border_nav_dropdown_active'] = [
      '#type' => 'select',
      '#title' => $this->t('Border color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_page_border_nav_dropdown_active'] ?? 'grey3',
    ];

    $form['navigation']['navi_main']['opacity_page_border_nav_dropdown_active'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_page_border_nav_dropdown_active'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['navigation']['navi_main']['navi_main_title'] = [
      '#prefix' => '</div><div class="styling-preview">',
      '#suffix' => '</div></div>',
      '#type' => 'item',
      '#markup' => '<ul class="clearfix nav navbar-nav">
        <li class="nav-item">
          <a href="#" class="nav-link" >Main Nav 1</a>
        </li>
        <li class="nav-item menu-item--expanded active dropdown">
          <a href="#" class="nav-link active dropdown-toggle" >Main Nav 2 (active)</a>
          <ul class="dropdown-menu">
            <li class="dropdown-item">
              <a href="#" >Nav Dropdown 1</a>
            </li>
            <li class="dropdown-item active">
              <a href="#" class="active is-active" >Nav Dropdown 2 (active)</a>
            </li>
            <li class="dropdown-item">
              <a href="#" >Nav Dropdown 3</a>
            </li>
          </ul>
        </li>
        <li class="nav-item">
          <a href="/de/raster" class="nav-link" >Main Nav 3</a>
        </li>
        <li class="nav-item">
          <a href="/de/raster" class="nav-link" >Main Nav 4</a>
        </li>
            </ul>',
    ];

    $form['navigation']['navi_mobile'] = [
      '#type' => 'details',
      '#title' => $this->t('Navigation Mobile'),
    ];

    $form['navigation']['navi_mobile']['color_page_background_nav_mobile'] = [
      '#prefix' => '<div class="styling-row"><div class="styling-input"><div class="inline-input-holder separator"><strong>Mobile navigation</strong>',
      '#type' => 'select',
      '#title' => $this->t('Background color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_page_background_nav_mobile'] ?? 'white',
    ];

    $form['navigation']['navi_mobile']['opacity_page_background_nav_mobile'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_page_background_nav_mobile'] ?? '1',
    ];

    $form['navigation']['navi_mobile']['color_page_font_nav_mobile'] = [
      '#type' => 'select',
      '#title' => $this->t('Font color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_page_font_nav_mobile'] ?? 'grey3',
    ];

    $form['navigation']['navi_mobile']['opacity_page_font_nav_mobile'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_page_font_nav_mobile'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['navigation']['navi_mobile']['color_page_font_nav_mobile_hover'] = [
      '#prefix' => '<div class="inline-input-holder"><strong>Mobile navigation hover</strong>',
      '#type' => 'select',
      '#title' => $this->t('Font color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_page_font_nav_mobile_hover'] ?? 'grey3',
    ];

    $form['navigation']['navi_mobile']['opacity_page_font_nav_mobile_hover'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_page_font_nav_mobile_hover'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['navigation']['navi_mobile']['navi_mobile_title'] = [
      '#prefix' => '</div><div class="styling-preview">',
      '#suffix' => '</div></div>',
      '#type' => 'item',
      '#markup' => 'Preview',
    ];

    $form['navigation']['navi_sidebar'] = [
      '#type' => 'details',
      '#title' => $this->t('Navigation Sidebar'),
    ];

    $form['navigation']['navi_sidebar']['color_page_font_nav_sidebar'] = [
      '#type' => 'select',
      '#title' => $this->t('Font color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_page_font_nav_sidebar'] ?? 'grey3',
      '#prefix' => '<div class="styling-row"><div class="styling-input"><div class="inline-input-holder separator"><strong>Sidebar standard</strong>',
    ];

    $form['navigation']['navi_sidebar']['opacity_page_font_nav_sidebar'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_page_font_nav_sidebar'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['navigation']['navi_sidebar']['color_page_font_nav_sidebar_hover'] = [
      '#type' => 'select',
      '#title' => $this->t('Font color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_page_font_nav_sidebar_hover'] ?? 'grey3',
      '#prefix' => '<div class="inline-input-holder separator"><strong>Sidebar hover</strong>',
    ];

    $form['navigation']['navi_sidebar']['opacity_page_font_nav_sidebar_hover'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_page_font_nav_sidebar_hover'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['navigation']['navi_sidebar']['navi_sidebar_title'] = [
      '#prefix' => '</div><div class="styling-preview">',
      '#suffix' => '</div></div>',
      '#type' => 'item',
      '#markup' => 'Preview',
    ];

    // Section decorations.
    $form['decorations'] = [
      '#type' => 'details',
      '#title' => $this->t('Styling'),
      '#summary' => $this->t('Borders, shadows etc.'),
      '#group' => 'iq_theme',
    ];

    $form['decorations']['border'] = [
      '#type' => 'details',
      '#title' => $this->t('Border'),
    ];

    $form['decorations']['border']['border_width_holder'] = [
      '#prefix' => '<div class="styling-row"><div class="styling-input"><div class="inline-input-holder ">',
      '#type' => 'container',
      '#attributes' => [
        'class' => ['container-inline', 'unit-combo-field', 'separator'],
      ],
    ];

    $form['decorations']['border']['border_width_holder']['border_width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Width'),
      '#default_value' => $formValues['border_width'] ?? '1',
    ];

    $form['decorations']['border']['border_width_holder']['border_width_unit'] = [
      '#type' => 'textfield',
      '#type' => 'select',
      '#options' => $arr_units,
      '#default_value' => $formValues['border_width_unit'] ?? 'px',
    ];

    $form['decorations']['border']['border_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Style'),
      '#options' => [
        'dotted' => $this->t('Dotted'),
        'dashed' => $this->t('Dashed'),
        'solid' => $this->t('Solid'),
        'double' => $this->t('Double'),
        'groove' => $this->t('Groove'),
        'ridge' => $this->t('Ridge'),
        'inset' => $this->t('Inset'),
        'outset' => $this->t('Outset'),
      ],
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#default_value' => $formValues['border_style'] ?? 'solid',
    ];

    $form['decorations']['border']['border_color'] = [
      '#type' => 'select',
      '#title' => $this->t('Color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['border_color'] ?? 'primary',
    ];

    $form['decorations']['border']['border_opacity'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['border_opacity'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['decorations']['border']['radius_holder'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['container-inline', 'unit-combo-field'],
      ],
    ];

    $form['decorations']['border']['radius_holder']['radius'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Border Radius'),
      '#default_value' => $formValues['radius'] ?? '1',
    ];

    $form['decorations']['border']['radius_holder']['radius_unit'] = [
      '#type' => 'textfield',
      '#type' => 'select',
      '#options' => $arr_units,
      '#default_value' => $formValues['radius_unit'] ?? '1',
      '#suffix' => '</div></div>',
    ];

    $form['decorations']['margins_paddings'] = [
      '#type' => 'details',
      '#title' => $this->t('Margins & Paddings'),
    ];

    $form['decorations']['margins_paddings']['margin'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['container-inline', 'unit-combo-field', 'multi-valule'],
      ],
    ];

    $form['decorations']['margins_paddings']['margin']['margin_top'] = [
      '#title' => $this->t('Margin (Top, right, bottom, left)'),
      '#type' => 'textfield',
      '#default_value' => $formValues['margin_top'] ?? '30',
    ];

    $form['decorations']['margins_paddings']['margin']['margin_right'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['margin_right'] ?? '30',
    ];

    $form['decorations']['margins_paddings']['margin']['margin_bottom'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['margin_bottom'] ?? '30',
    ];

    $form['decorations']['margins_paddings']['margin']['margin_left'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['margin_left'] ?? '30',
    ];

    $form['decorations']['margins_paddings']['margin']['margin_unit'] = [
      '#type' => 'textfield',
      '#type' => 'select',
      '#options' => $arr_units,
      '#default_value' => $formValues['margin_unit'] ?? 'px',
    ];

    $form['decorations']['margins_paddings']['padding'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['container-inline', 'unit-combo-field', 'multi-valule'],
      ],
    ];

    $form['decorations']['margins_paddings']['padding']['padding_top'] = [
      '#title' => $this->t('Padding (Top, right, bottom, left)'),
      '#type' => 'textfield',
      '#default_value' => $formValues['padding_top'] ?? '30',
    ];

    $form['decorations']['margins_paddings']['padding']['padding_right'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['padding_right'] ?? '30',
    ];

    $form['decorations']['margins_paddings']['padding']['padding_bottom'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['padding_bottom'] ?? '30',
    ];

    $form['decorations']['margins_paddings']['padding']['padding_left'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['padding_left'] ?? '30',
    ];

    $form['decorations']['margins_paddings']['padding']['padding_unit'] = [
      '#type' => 'textfield',
      '#type' => 'select',
      '#options' => $arr_units,
      '#default_value' => $formValues['padding_unit'] ?? 'px',
    ];

    // Section buttons.
    $form['buttons'] = [
      '#type' => 'details',
      '#title' => $this->t('Buttons'),
      '#summary' => $this->t('Styling of buttons.'),
      '#group' => 'iq_theme',
    ];

    $form['buttons']['default'] = [
      '#type' => 'details',
      '#title' => $this->t('Default Button'),
    ];

    $form['buttons']['default']['button_font_family'] = [
      '#prefix' => '<div class="styling-row"><div class="styling-input"><div class="inline-input-holder"><strong>Main button styling</strong>',
      '#type' => 'select',
      '#title' => $this->t('Font family'),
      '#options' => $arr_fonts,
      '#default_value' => $formValues['button_font_family'] ?? 'Arial',
    ];

    $form['buttons']['default']['button_font_weight'] = [
      '#type' => 'select',
      '#options' => $arr_fontweights,
      '#title' => $this->t('Font weight'),
      '#default_value' => $formValues['button_font_weight'] ?? 'normal',
    ];

    $form['buttons']['default']['button_font_style'] = [
      '#type' => 'select',
      '#options' => $arr_fontstyles,
      '#title' => $this->t('Font style'),
      '#default_value' => $formValues['button_font_style'] ?? 'normal',
    ];

    $form['buttons']['default']['button_line_height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Line height'),
      '#default_value' => $formValues['button_line_height'] ?? '1.2',
      '#suffix' => '</div>',
    ];

    $form['buttons']['default']['button_font_size_holder'] = [
      '#type' => 'container',
      '#prefix' => '<div class="inline-input-holder">',
      '#attributes' => [
        'class' => ['container-inline', 'unit-combo-field', 'separator'],
      ],
    ];

    $form['buttons']['default']['button_font_size_holder']['button_font_size'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Font size'),
      '#default_value' => $formValues['button_font_size'] ?? '1',
    ];

    $form['buttons']['default']['button_font_size_holder']['button_font_size_unit'] = [
      '#type' => 'select',
      '#options' => $arr_units,
      '#default_value' => $formValues['button_font_size_unit'] ?? 'rem',
    ];

    $form['buttons']['default']['button_border_width_holder'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'container-inline',
          'unit-combo-field',
          'multi-valule',
          'separator',
        ],
      ],
    ];

    $form['buttons']['default']['button_border_width_holder']['button_border_width_top'] = [
      '#title' => $this->t('Border (top, right, bottom, left)'),
      '#type' => 'textfield',
      '#default_value' => $formValues['button_border_width_top'] ?? '1',
    ];

    $form['buttons']['default']['button_border_width_holder']['button_border_width_right'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['button_border_width_right'] ?? '1',
    ];

    $form['buttons']['default']['button_border_width_holder']['button_border_width_bottom'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['button_border_width_bottom'] ?? '1',
    ];

    $form['buttons']['default']['button_border_width_holder']['button_border_width_left'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['button_border_width_left'] ?? '1',
    ];

    $form['buttons']['default']['button_border_width_holder']['button_border_width_unit'] = [
      '#type' => 'textfield',
      '#type' => 'select',
      '#options' => $arr_units,
      '#default_value' => $formValues['button_border_width_unit'] ?? 'px',
    ];

    $form['buttons']['default']['button_border_radius_holder'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['container-inline', 'unit-combo-field', 'separator'],
      ],
    ];

    $form['buttons']['default']['button_border_radius_holder']['button_border_radius'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Border radius'),
      '#default_value' => $formValues['button_border_radius'] ?? '0',
    ];

    $form['buttons']['default']['button_border_radius_holder']['button_border_radius_unit'] = [
      '#type' => 'select',
      '#options' => $arr_units,
      '#default_value' => $formValues['button_border_radius_unit'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['buttons']['default']['button_margin_holder'] = [
      '#type' => 'container',
      '#prefix' => '<div class="inline-input-holder">',
      '#attributes' => [
        'class' => [
          'container-inline',
          'unit-combo-field',
          'multi-valule',
          'separator',
        ],
      ],
    ];

    $form['buttons']['default']['button_margin_holder']['button_margin_top'] = [
      '#title' => $this->t('Margin (top, right, bottom, left)'),
      '#type' => 'textfield',
      '#default_value' => $formValues['button_margin_top'] ?? '0',
    ];

    $form['buttons']['default']['button_margin_holder']['button_margin_right'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['button_margin_right'] ?? '0',
    ];

    $form['buttons']['default']['button_margin_holder']['button_margin_bottom'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['button_margin_bottom'] ?? '0',
    ];

    $form['buttons']['default']['button_margin_holder']['button_margin_left'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['button_margin_left'] ?? '0',
    ];

    $form['buttons']['default']['button_margin_holder']['button_margin_unit'] = [
      '#type' => 'textfield',
      '#type' => 'select',
      '#options' => $arr_units,
      '#default_value' => $formValues['button_margin_unit'] ?? 'px',
    ];

    $form['buttons']['default']['button_padding_holder'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['container-inline', 'unit-combo-field', 'multi-valule'],
      ],
      '#suffix' => '</div>',
    ];

    $form['buttons']['default']['button_padding_holder']['button_padding_top'] = [
      '#title' => $this->t('Padding (top, right, bottom, left)'),
      '#type' => 'textfield',
      '#default_value' => $formValues['button_padding_top'] ?? '5',
    ];

    $form['buttons']['default']['button_padding_holder']['button_padding_right'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['button_padding_right'] ?? '5',
    ];

    $form['buttons']['default']['button_padding_holder']['button_padding_bottom'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['button_padding_bottom'] ?? '5',
    ];

    $form['buttons']['default']['button_padding_holder']['button_padding_left'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['button_padding_left'] ?? '5',
    ];

    $form['buttons']['default']['button_padding_holder']['button_padding_unit'] = [
      '#type' => 'textfield',
      '#type' => 'select',
      '#options' => $arr_units,
      '#default_value' => $formValues['button_padding_unit'] ?? 'px',
    ];

    $form['buttons']['default']['standard'] = [
      '#type' => 'details',
      '#title' => $this->t('Standard color settings'),
    ];

    $form['buttons']['default']['standard']['button_font_color_standard'] = [
      '#type' => 'select',
      '#title' => $this->t('Font color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_font_color_standard'] ?? 'white',
      '#prefix' => '<div class="inline-input-holder"><strong>Standard</strong>',
    ];

    $form['buttons']['default']['standard']['button_font_opacity_standard'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_font_opacity_standard'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['buttons']['default']['standard']['button_background_color_standard'] = [
      '#prefix' => '<div class="inline-input-holder separator">',
      '#type' => 'select',
      '#title' => $this->t('Background color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_background_color_standard'] ?? 'primary',
    ];

    $form['buttons']['default']['standard']['button_background_opacity_standard'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_background_opacity_standard'] ?? '1',
    ];

    $form['buttons']['default']['standard']['button_border_color_standard'] = [
      '#type' => 'select',
      '#title' => $this->t('Border color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_border_color_standard'] ?? 'primary',
    ];

    $form['buttons']['default']['standard']['button_border_opacity_standard'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_border_opacity_standard'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['buttons']['default']['standard']['button_font_color_hover'] = [
      '#type' => 'select',
      '#title' => $this->t('Font color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_font_color_hover'] ?? 'white',
      '#prefix' => '<div class="inline-input-holder"><strong>Hover</strong>',
    ];

    $form['buttons']['default']['standard']['button_font_opacity_hover'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_font_opacity_hover'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['buttons']['default']['standard']['button_background_color_hover'] = [
      '#prefix' => '<div class="inline-input-holder separator">',
      '#type' => 'select',
      '#title' => $this->t('Background color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_background_color_hover'] ?? 'secondary',
    ];

    $form['buttons']['default']['standard']['button_background_opacity_hover'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_background_opacity_hover'] ?? '1',
    ];

    $form['buttons']['default']['standard']['button_border_color_hover'] = [
      '#type' => 'select',
      '#title' => $this->t('Hover border color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_border_color_hover'] ?? 'secondary',
    ];

    $form['buttons']['default']['standard']['button_border_opacity_hover'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_border_opacity_hover'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['buttons']['default']['standard']['button_font_color_active'] = [
      '#type' => 'select',
      '#title' => $this->t('Font color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_font_color_active'] ?? 'white',
      '#prefix' => '<div class="inline-input-holder"><strong>Active</strong>',
    ];

    $form['buttons']['default']['standard']['button_font_opacity_active'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_font_opacity_active'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['buttons']['default']['standard']['button_background_color_active'] = [
      '#type' => 'select',
      '#title' => $this->t('Background color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_background_color_active'] ?? 'primary',
      '#prefix' => '<div class="inline-input-holder separator">',
    ];

    $form['buttons']['default']['standard']['button_background_opacity_active'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_background_opacity_active'] ?? '1',
    ];

    $form['buttons']['default']['standard']['button_border_color_active'] = [
      '#type' => 'select',
      '#title' => $this->t('Border color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_border_color_active'] ?? 'primary',
    ];

    $form['buttons']['default']['standard']['button_border_opacity_active'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_border_opacity_active'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['buttons']['default']['inverted'] = [
      '#type' => 'details',
      '#title' => $this->t('Inverted color settings'),
    ];

    $form['buttons']['default']['inverted']['button_font_color_inverted'] = [
      '#type' => 'select',
      '#title' => $this->t('Font color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_font_color_inverted'] ?? 'white',
      '#prefix' => '<div class="inline-input-holder"><strong>Standard</strong>',
    ];

    $form['buttons']['default']['inverted']['button_font_opacity_inverted'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_font_opacity_inverted'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['buttons']['default']['inverted']['button_background_color_inverted'] = [
      '#prefix' => '<div class="inline-input-holder separator">',
      '#type' => 'select',
      '#title' => $this->t('Background color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_background_color_inverted'] ?? 'secondary',
    ];

    $form['buttons']['default']['inverted']['button_background_opacity_inverted'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_background_opacity_inverted'] ?? '1',
    ];

    $form['buttons']['default']['inverted']['button_border_color_inverted'] = [
      '#type' => 'select',
      '#title' => $this->t('Border color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_border_color_inverted'] ?? 'primary',
    ];

    $form['buttons']['default']['inverted']['button_border_opacity_inverted'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_border_opacity_inverted'] ?? '0',
      '#suffix' => '</div>',
    ];

    $form['buttons']['default']['inverted']['button_font_color_inverted_hover'] = [
      '#type' => 'select',
      '#title' => $this->t('Font color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_font_color_inverted_hover'] ?? 'white',
      '#prefix' => '<div class="inline-input-holder"><strong>Hover</strong>',
    ];

    $form['buttons']['default']['inverted']['button_font_opacity_inverted_hover'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_font_opacity_inverted_hover'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['buttons']['default']['inverted']['button_background_color_inverted_hover'] = [
      '#prefix' => '<div class="inline-input-holder separator">',
      '#type' => 'select',
      '#title' => $this->t('Background color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_background_color_inverted_hover'] ?? 'primary',
    ];

    $form['buttons']['default']['inverted']['button_background_opacity_inverted_hover'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_background_opacity_inverted_hover'] ?? '1',
    ];

    $form['buttons']['default']['inverted']['button_border_color_inverted_hover'] = [
      '#type' => 'select',
      '#title' => $this->t('Hover border color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_border_color_inverted_hover'] ?? 'primary',
    ];

    $form['buttons']['default']['inverted']['button_border_opacity_inverted_hover'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_border_opacity_inverted_hover'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['buttons']['default']['inverted']['button_font_color_inverted_active'] = [
      '#type' => 'select',
      '#title' => $this->t('Font color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_font_color_inverted_active'] ?? 'white',
      '#prefix' => '<div class="inline-input-holder"><strong>Active</strong>',
    ];

    $form['buttons']['default']['inverted']['button_font_opacity_inverted_active'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_font_opacity_inverted_active'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['buttons']['default']['inverted']['button_background_color_inverted_active'] = [
      '#type' => 'select',
      '#title' => $this->t('Background color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_background_color_inverted_active'] ?? 'secondary',
      '#prefix' => '<div class="inline-input-holder separator">',
    ];

    $form['buttons']['default']['inverted']['button_background_opacity_inverted_active'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_background_opacity_inverted_active'] ?? '1',
    ];

    $form['buttons']['default']['inverted']['button_border_color_inverted_active'] = [
      '#type' => 'select',
      '#title' => $this->t('Border color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_border_color_inverted_active'] ?? 'secondary',
    ];

    $form['buttons']['default']['inverted']['button_border_opacity_inverted_active'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_border_opacity_inverted_active'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['buttons']['default']['button_title'] = [
      '#prefix' => '</div><div class="styling-preview">',
      '#suffix' => '</div></div>',
      '#type' => 'item',
      '#markup' => '<div class="preview-button standard">Standard</div><div class="preview-button hover">Hover</div><div class="preview-button active">Active</div><p>Buttons within text: Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor <a href="#" class="preview-button inline">invidunt</a> ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. <br/><a href="#" class="preview-button inline">gubergren, no sea takimata sanctus est</a> <br/>Lorem ipsum dolor sit amet</p>
      <div class="preview-inverted"><div class="preview-button standard">Standard</div><div class="preview-button hover">Hover</div><div class="preview-button active">Active</div><p>Buttons within text: Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor <a href="#" class="preview-button inline">invidunt</a> ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. <br/><a href="#" class="preview-button inline">gubergren, no sea takimata sanctus est</a> <br/>Lorem ipsum dolor sit amet</p></div>
            ',
    ];

    // Section alternate button.
    $form['buttons']['alternate'] = [
      '#type' => 'details',
      '#title' => $this->t('Alterante Button'),
    ];

    $form['buttons']['alternate']['button_alternate_font_family'] = [
      '#prefix' => '<div class="styling-row"><div class="styling-input"><div class="inline-input-holder"><strong>Main button styling</strong>',
      '#type' => 'select',
      '#title' => $this->t('Font family'),
      '#options' => $arr_fonts,
      '#default_value' => $formValues['button_alternate_font_family'] ?? 'Arial',
    ];

    $form['buttons']['alternate']['button_alternate_font_weight'] = [
      '#type' => 'select',
      '#options' => $arr_fontweights,
      '#title' => $this->t('Font weight'),
      '#default_value' => $formValues['button_alternate_font_weight'] ?? 'normal',
    ];

    $form['buttons']['alternate']['button_alternate_font_style'] = [
      '#type' => 'select',
      '#options' => $arr_fontstyles,
      '#title' => $this->t('Font style'),
      '#default_value' => $formValues['button_alternate_font_style'] ?? 'normal',
    ];

    $form['buttons']['alternate']['button_alternate_line_height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Line height'),
      '#default_value' => $formValues['button_alternate_line_height'] ?? '1,2',
      '#suffix' => '</div>',
    ];

    $form['buttons']['alternate']['button_alternate_font_size_holder'] = [
      '#type' => 'container',
      '#prefix' => '<div class="inline-input-holder">',
      '#attributes' => [
        'class' => ['container-inline', 'unit-combo-field', 'separator'],
      ],
    ];

    $form['buttons']['alternate']['button_alternate_font_size_holder']['button_alternate_font_size'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Font size'),
      '#default_value' => $formValues['button_alternate_font_size'] ?? '1',
    ];

    $form['buttons']['alternate']['button_alternate_font_size_holder']['button_alternate_font_size_unit'] = [
      '#type' => 'select',
      '#options' => $arr_units,
      '#default_value' => $formValues['button_alternate_font_size_unit'] ?? 'rem',
    ];

    $form['buttons']['alternate']['button_alternate_border_width_holder'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'container-inline',
          'unit-combo-field',
          'multi-valule',
          'separator',
        ],
      ],
    ];

    $form['buttons']['alternate']['button_alternate_border_width_holder']['button_alternate_border_width_top'] = [
      '#title' => $this->t('Border (top, right, bottom, left)'),
      '#type' => 'textfield',
      '#default_value' => $formValues['button_alternate_border_width_top'] ?? '1',
    ];

    $form['buttons']['alternate']['button_alternate_border_width_holder']['button_alternate_border_width_right'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['button_alternate_border_width_right'] ?? '1',
    ];

    $form['buttons']['alternate']['button_alternate_border_width_holder']['button_alternate_border_width_bottom'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['button_alternate_border_width_bottom'] ?? '1',
    ];

    $form['buttons']['alternate']['button_alternate_border_width_holder']['button_alternate_border_width_left'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['button_alternate_border_width_left'] ?? '1',
    ];

    $form['buttons']['alternate']['button_alternate_border_width_holder']['button_alternate_border_width_unit'] = [
      '#type' => 'select',
      '#options' => $arr_units,
      '#default_value' => $formValues['button_alternate_border_width_unit'] ?? 'px',
    ];

    $form['buttons']['alternate']['button_alternate_border_radius_holder'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['container-inline', 'unit-combo-field', 'separator'],
      ],
    ];

    $form['buttons']['alternate']['button_alternate_border_radius_holder']['button_alternate_border_radius'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Border radius'),
      '#default_value' => $formValues['button_alternate_border_radius'] ?? '0',
    ];

    $form['buttons']['alternate']['button_alternate_border_radius_holder']['button_alternate_border_radius_unit'] = [
      '#type' => 'select',
      '#options' => $arr_units,
      '#default_value' => $formValues['button_alternate_border_radius_unit'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['buttons']['alternate']['button_alternate_margin_holder'] = [
      '#type' => 'container',
      '#prefix' => '<div class="inline-input-holder">',
      '#attributes' => [
        'class' => [
          'container-inline',
          'unit-combo-field',
          'multi-valule',
          'separator',
        ],
      ],
    ];

    $form['buttons']['alternate']['button_alternate_margin_holder']['button_alternate_margin_top'] = [
      '#title' => $this->t('Margin (top, right, bottom, left)'),
      '#type' => 'textfield',
      '#default_value' => $formValues['button_alternate_margin_top'] ?? '0',
    ];

    $form['buttons']['alternate']['button_alternate_margin_holder']['button_alternate_margin_right'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['button_alternate_margin_right'] ?? '0',
    ];

    $form['buttons']['alternate']['button_alternate_margin_holder']['button_alternate_margin_bottom'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['button_alternate_margin_bottom'] ?? '0',
    ];

    $form['buttons']['alternate']['button_alternate_margin_holder']['button_alternate_margin_left'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['button_alternate_margin_left'] ?? '0',
    ];

    $form['buttons']['alternate']['button_alternate_margin_holder']['button_alternate_margin_unit'] = [
      '#type' => 'textfield',
      '#type' => 'select',
      '#options' => $arr_units,
      '#default_value' => $formValues['button_alternate_margin_unit'] ?? 'px',
    ];

    $form['buttons']['alternate']['button_alternate_padding_holder'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['container-inline', 'unit-combo-field', 'multi-valule'],
      ],
      '#suffix' => '</div>',
    ];

    $form['buttons']['alternate']['button_alternate_padding_holder']['button_alternate_padding_top'] = [
      '#title' => $this->t('Padding (top, right, bottom, left)'),
      '#type' => 'textfield',
      '#default_value' => $formValues['button_alternate_padding_top'] ?? '5',
    ];

    $form['buttons']['alternate']['button_alternate_padding_holder']['button_alternate_padding_right'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['button_alternate_padding_right'] ?? '5',
    ];

    $form['buttons']['alternate']['button_alternate_padding_holder']['button_alternate_padding_bottom'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['button_alternate_padding_bottom'] ?? '5',
    ];

    $form['buttons']['alternate']['button_alternate_padding_holder']['button_alternate_padding_left'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['button_alternate_padding_left'] ?? '5',
    ];

    $form['buttons']['alternate']['button_alternate_padding_holder']['button_alternate_padding_unit'] = [
      '#type' => 'textfield',
      '#type' => 'select',
      '#options' => $arr_units,
      '#default_value' => $formValues['button_alternate_padding_unit'] ?? 'px',
    ];

    $form['buttons']['alternate']['standard'] = [
      '#type' => 'details',
      '#title' => $this->t('Standard color settings'),
    ];

    $form['buttons']['alternate']['standard']['button_alternate_font_color_standard'] = [
      '#type' => 'select',
      '#title' => $this->t('Font color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_alternate_font_color_standard'] ?? 'white',
      '#prefix' => '<div class="inline-input-holder"><strong>Standard</strong>',
    ];

    $form['buttons']['alternate']['standard']['button_alternate_font_opacity_standard'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_alternate_font_opacity_standard'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['buttons']['alternate']['standard']['button_alternate_background_color_standard'] = [
      '#prefix' => '<div class="inline-input-holder separator">',
      '#type' => 'select',
      '#title' => $this->t('Background color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_alternate_background_color_standard'] ?? 'primary',
    ];

    $form['buttons']['alternate']['standard']['button_alternate_background_opacity_standard'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_alternate_background_opacity_standard'] ?? '1',
    ];

    $form['buttons']['alternate']['standard']['button_alternate_border_color_standard'] = [
      '#type' => 'select',
      '#title' => $this->t('Border color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_alternate_border_color_standard'] ?? 'primary',
    ];

    $form['buttons']['alternate']['standard']['button_alternate_border_opacity_standard'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_alternate_border_opacity_standard'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['buttons']['alternate']['standard']['button_alternate_font_color_hover'] = [
      '#type' => 'select',
      '#title' => $this->t('Font color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_alternate_font_color_hover'] ?? 'white',
      '#prefix' => '<div class="inline-input-holder"><strong>Hover</strong>',
    ];

    $form['buttons']['alternate']['standard']['button_alternate_font_opacity_hover'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_alternate_font_opacity_hover'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['buttons']['alternate']['standard']['button_alternate_background_color_hover'] = [
      '#prefix' => '<div class="inline-input-holder separator">',
      '#type' => 'select',
      '#title' => $this->t('Background color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_alternate_background_color_hover'] ?? 'secondary',
    ];

    $form['buttons']['alternate']['standard']['button_alternate_background_opacity_hover'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_alternate_background_opacity_hover'] ?? '1',
    ];

    $form['buttons']['alternate']['standard']['button_alternate_border_color_hover'] = [
      '#type' => 'select',
      '#title' => $this->t('Hover border color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_alternate_border_color_hover'] ?? 'secondary',
    ];

    $form['buttons']['alternate']['standard']['button_alternate_border_opacity_hover'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_alternate_border_opacity_hover'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['buttons']['alternate']['standard']['button_alternate_font_color_active'] = [
      '#type' => 'select',
      '#title' => $this->t('Font color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_alternate_font_color_active'] ?? 'white',
      '#prefix' => '<div class="inline-input-holder"><strong>Active</strong>',
    ];

    $form['buttons']['alternate']['standard']['button_alternate_font_opacity_active'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_alternate_font_opacity_active'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['buttons']['alternate']['standard']['button_alternate_background_color_active'] = [
      '#type' => 'select',
      '#title' => $this->t('Background color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_alternate_background_color_active'] ?? 'primary',
      '#prefix' => '<div class="inline-input-holder separator">',
    ];

    $form['buttons']['alternate']['standard']['button_alternate_background_opacity_active'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_alternate_background_opacity_active'] ?? '1',
    ];

    $form['buttons']['alternate']['standard']['button_alternate_border_color_active'] = [
      '#type' => 'select',
      '#title' => $this->t('Border color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_alternate_border_color_active'] ?? 'primary',
    ];

    $form['buttons']['alternate']['standard']['button_alternate_border_opacity_active'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_alternate_border_opacity_active'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['buttons']['alternate']['inverted'] = [
      '#type' => 'details',
      '#title' => $this->t('Inverted color settings'),
    ];

    $form['buttons']['alternate']['inverted']['button_alternate_font_color_inverted'] = [
      '#type' => 'select',
      '#title' => $this->t('Font color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_alternate_font_color_inverted'] ?? 'white',
      '#prefix' => '<div class="inline-input-holder"><strong>inverted</strong>',
    ];

    $form['buttons']['alternate']['inverted']['button_alternate_font_opacity_inverted'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_alternate_font_opacity_inverted'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['buttons']['alternate']['inverted']['button_alternate_background_color_inverted'] = [
      '#prefix' => '<div class="inline-input-holder separator">',
      '#type' => 'select',
      '#title' => $this->t('Background color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_alternate_background_color_inverted'] ?? 'secondary',
    ];

    $form['buttons']['alternate']['inverted']['button_alternate_background_opacity_inverted'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_alternate_background_opacity_inverted'] ?? '1',
    ];

    $form['buttons']['alternate']['inverted']['button_alternate_border_color_inverted'] = [
      '#type' => 'select',
      '#title' => $this->t('Border color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_alternate_border_color_inverted'] ?? 'primary',
    ];

    $form['buttons']['alternate']['inverted']['button_alternate_border_opacity_inverted'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_alternate_border_opacity_inverted'] ?? '0',
      '#suffix' => '</div>',
    ];

    $form['buttons']['alternate']['inverted']['button_alternate_font_color_inverted_hover'] = [
      '#type' => 'select',
      '#title' => $this->t('Font color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_alternate_font_color_inverted_hover'] ?? 'white',
      '#prefix' => '<div class="inline-input-holder"><strong>Hover</strong>',
    ];

    $form['buttons']['alternate']['inverted']['button_alternate_font_opacity_inverted_hover'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_alternate_font_opacity_inverted_hover'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['buttons']['alternate']['inverted']['button_alternate_background_color_inverted_hover'] = [
      '#prefix' => '<div class="inline-input-holder separator">',
      '#type' => 'select',
      '#title' => $this->t('Background color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_alternate_background_color_inverted_hover'] ?? 'primary',
    ];

    $form['buttons']['alternate']['inverted']['button_alternate_background_opacity_inverted_hover'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_alternate_background_opacity_inverted_hover'] ?? '1',
    ];

    $form['buttons']['alternate']['inverted']['button_alternate_border_color_inverted_hover'] = [
      '#type' => 'select',
      '#title' => $this->t('Hover border color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_alternate_border_color_inverted_hover'] ?? 'primary',
    ];

    $form['buttons']['alternate']['inverted']['button_alternate_border_opacity_inverted_hover'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_alternate_border_opacity_inverted_hover'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['buttons']['alternate']['inverted']['button_alternate_font_color_inverted_active'] = [
      '#type' => 'select',
      '#title' => $this->t('Font color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_alternate_font_color_inverted_active'] ?? 'secondary',
      '#prefix' => '<div class="inline-input-holder"><strong>Active</strong>',
    ];

    $form['buttons']['alternate']['inverted']['button_alternate_font_opacity_inverted_active'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_alternate_font_opacity_inverted_active'] ?? 'white',
      '#suffix' => '</div>',
    ];

    $form['buttons']['alternate']['inverted']['button_alternate_background_color_inverted_active'] = [
      '#type' => 'select',
      '#title' => $this->t('Background color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_alternate_background_color_inverted_active'] ?? 'secondary',
      '#prefix' => '<div class="inline-input-holder separator">',
    ];

    $form['buttons']['alternate']['inverted']['button_alternate_background_opacity_inverted_active'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_alternate_background_opacity_inverted_active'] ?? '1',
    ];

    $form['buttons']['alternate']['inverted']['button_alternate_border_color_inverted_active'] = [
      '#type' => 'select',
      '#title' => $this->t('Border color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['button_alternate_border_color_inverted_active'] ?? 'secondary',
    ];

    $form['buttons']['alternate']['inverted']['button_alternate_border_opacity_inverted_active'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['button_alternate_border_opacity_inverted_active'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['buttons']['alternate']['button_alternate_title'] = [
      '#prefix' => '</div><div class="styling-preview button-alternate">',
      '#suffix' => '</div></div>',
      '#type' => 'item',
      '#markup' => '<div class="preview-button  standard">Standard</div><div class="preview-button hover">Hover</div><div class="preview-button active">Active</div><p>Buttons within text: Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor <a href="#" class="preview-button inline">invidunt</a> ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. <br/><a href="#" class="preview-button inline">gubergren, no sea takimata sanctus est</a> <br/>Lorem ipsum dolor sit amet</p>
      <div class="preview-inverted"><div class="preview-button standard">Standard</div><div class="preview-button hover">Hover</div><div class="preview-button active">Active</div><p>Buttons within text: Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor <a href="#" class="preview-button inline">invidunt</a> ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. <br/><a href="#" class="preview-button inline">gubergren, no sea takimata sanctus est</a> <br/>Lorem ipsum dolor sit amet</p></div>
            ',
    ];

    // Section patterns.
    $form['patterns'] = [
      '#type' => 'details',
      '#title' => $this->t('Patterns'),
      '#summary' => $this->t('Styling of patterns.'),
      '#group' => 'iq_theme',
    ];

    $form['patterns']['slider'] = [
      '#type' => 'details',
      '#title' => $this->t('Slider'),
    ];

    $form['patterns']['slider']['slider_arrow_size_holder'] = [
      '#prefix' => '<div class="styling-row"><div class="styling-input full"><div class="inline-input-holder "><strong>Arrows</strong>',
      '#type' => 'container',
      '#attributes' => [
        'class' => ['container-inline', 'unit-combo-field', 'separator'],
      ],
    ];

    $form['patterns']['slider']['slider_arrow_size_holder']['slider_arrow_size'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Size'),
      '#default_value' => $formValues['slider_arrow_size'] ?? '1',
    ];

    $form['patterns']['slider']['slider_arrow_size_holder']['slider_arrow_size_unit'] = [
      '#type' => 'select',
      '#options' => $arr_units,
      '#default_value' => $formValues['slider_arrow_size_unit'] ?? 'rem',
    ];

    $form['patterns']['slider']['slider_arrow_color'] = [
      '#type' => 'select',
      '#title' => $this->t('Color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['slider_arrow_color'] ?? 'grey3',
    ];

    $form['patterns']['slider']['slider_arrow_opacity'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['slider_arrow_opacity'] ?? '1',
    ];

    $form['patterns']['slider']['slider_arrow_backgroundcolor'] = [
      '#type' => 'select',
      '#title' => $this->t('Background color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['slider_arrow_backgroundcolor'] ?? 'white',
    ];

    $form['patterns']['slider']['slider_arrow_background_opacity'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['slider_arrow_background_opacity'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['patterns']['slider']['slider_dot_size_holder'] = [
      '#type' => 'container',
      '#prefix' => '<div class="inline-input-holder "><strong>Dots</strong>',
      '#attributes' => [
        'class' => ['container-inline', 'unit-combo-field', 'separator'],
      ],
    ];

    $form['patterns']['slider']['slider_dot_size_holder']['slider_dot_size'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Size'),
      '#default_value' => $formValues['slider_dot_size'] ?? '1',
    ];

    $form['patterns']['slider']['slider_dot_size_holder']['slider_dot_size_unit'] = [
      '#type' => 'select',
      '#options' => $arr_units,
      '#default_value' => $formValues['slider_dot_size_unit'] ?? 'rem',
    ];

    $form['patterns']['slider']['slider_dot_color'] = [
      '#type' => 'select',
      '#title' => $this->t('Colo standard'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['slider_dot_color'] ?? 'grey3',
    ];

    $form['patterns']['slider']['slider_dot_opacity'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['slider_dot_opacity'] ?? '1',
    ];

    $form['patterns']['slider']['slider_dot_color_active'] = [
      '#type' => 'select',
      '#title' => $this->t('Color active'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['slider_dot_color_active'] ?? 'grey1',
    ];

    $form['patterns']['slider']['slider_dot_opacity_active'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['slider_dot_opacity_active'] ?? '1',
      '#suffix' => '</div></div></div>',
    ];

    $form['patterns']['quote'] = [
      '#type' => 'details',
      '#title' => $this->t('Quote'),
    ];

    $form['patterns']['quote']['quote_highlight_color'] = [
      '#type' => 'select',
      '#title' => $this->t('Color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['quote_highlight_color'] ?? 'grey3',
      '#prefix' => '<div class="styling-row"><div class="styling-input full"><div class="inline-input-holder "><strong>Highlights (quote marks / lines)</strong>',
    ];

    $form['patterns']['quote']['quote_highlight_opacity'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['quote_highlight_opacity'] ?? '1',
      '#suffix' => '</div></div></div>',
    ];

    $form['patterns']['icons'] = [
      '#type' => 'details',
      '#title' => $this->t('Icons'),
    ];

    $form['patterns']['icons']['default'] = [
      '#type' => 'details',
      '#title' => $this->t('Default icon'),
    ];

    $form['patterns']['icons']['default']['color_icons'] = [
      '#type' => 'select',
      '#title' => $this->t('Color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_icons'] ?? 'primary',
      '#prefix' => '<div class="styling-row"><div class="styling-input full"><div class="inline-input-holder "><strong>Standard</strong>',
    ];

    $form['patterns']['icons']['default']['opacity_icons'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_icons'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['patterns']['icons']['default']['color_icons_hover'] = [
      '#type' => 'select',
      '#title' => $this->t('Color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_icons_hover'] ?? 'primary',
      '#prefix' => '<div class="inline-input-holder "><strong>Hover</strong>',
    ];

    $form['patterns']['icons']['default']['opacity_icons_hover'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_icons_hover'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['patterns']['icons']['default']['color_icons_active'] = [
      '#type' => 'select',
      '#title' => $this->t('Color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_icons_active'] ?? 'primary',
      '#prefix' => '<div class="inline-input-holder separator"><strong>Active</strong>',
    ];

    $form['patterns']['icons']['default']['opacity_icons_active'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_icons_active'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['patterns']['icons']['default']['color_icons_inverted'] = [
      '#type' => 'select',
      '#title' => $this->t('Color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_icons_inverted'] ?? 'primary',
      '#prefix' => '<div class="styling-row"><div class="styling-input full"><div class="inline-input-holder "><strong>Inverted</strong>',
    ];

    $form['patterns']['icons']['default']['opacity_icons_inverted'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_icons_inverted'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['patterns']['icons']['default']['color_icons_inverted_hover'] = [
      '#type' => 'select',
      '#title' => $this->t('Color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_icons_inverted_hover'] ?? 'primary',
      '#prefix' => '<div class="inline-input-holder "><strong>Inverted hover</strong>',
    ];

    $form['patterns']['icons']['default']['opacity_icons_inverted_hover'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_icons_inverted_hover'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['patterns']['icons']['default']['color_icons_inverted_active'] = [
      '#type' => 'select',
      '#title' => $this->t('Color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_icons_inverted_active'] ?? 'primary',
      '#prefix' => '<div class="inline-input-holder"><strong>Inverted active</strong>',
    ];

    $form['patterns']['icons']['default']['opacity_icons_inverted_active'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_icons_inverted_active'] ?? '1',
      '#suffix' => '</div></div></div>',
    ];

    $form['patterns']['icons']['alternate'] = [
      '#type' => 'details',
      '#title' => $this->t('Alternate icon'),
    ];

    $form['patterns']['icons']['alternate']['color_icons_alternate'] = [
      '#type' => 'select',
      '#title' => $this->t('Color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_icons_alternate'] ?? 'primary',
      '#prefix' => '<div class="styling-row"><div class="styling-input full"><div class="inline-input-holder "><strong>Standard</strong>',
    ];

    $form['patterns']['icons']['alternate']['opacity_icons_alternate'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_icons_alternate'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['patterns']['icons']['alternate']['color_icons_alternate_hover'] = [
      '#type' => 'select',
      '#title' => $this->t('Color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_icons_alternate_hover'] ?? 'primary',
      '#prefix' => '<div class="inline-input-holder "><strong>Hover</strong>',
    ];

    $form['patterns']['icons']['alternate']['opacity_icons_alternate_hover'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_icons_alternate_hover'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['patterns']['icons']['alternate']['color_icons_alternate_active'] = [
      '#type' => 'select',
      '#title' => $this->t('Color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_icons_alternate_active'] ?? 'primary',
      '#prefix' => '<div class="inline-input-holder separator"><strong>Active</strong>',
    ];

    $form['patterns']['icons']['alternate']['opacity_icons_alternate_active'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_icons_alternate_active'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['patterns']['icons']['alternate']['color_icons_alternate_inverted'] = [
      '#type' => 'select',
      '#title' => $this->t('Color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_icons_alternate_inverted'] ?? 'primary',
      '#prefix' => '<div class="styling-row"><div class="styling-input full"><div class="inline-input-holder "><strong>Inverted</strong>',
    ];

    $form['patterns']['icons']['alternate']['opacity_icons_alternate_inverted'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_icons_alternate_inverted'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['patterns']['icons']['alternate']['color_icons_alternate_inverted_hover'] = [
      '#type' => 'select',
      '#title' => $this->t('Color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_icons_alternate_inverted_hover'] ?? 'primary',
      '#prefix' => '<div class="inline-input-holder "><strong>Inverted hover</strong>',
    ];

    $form['patterns']['icons']['alternate']['opacity_icons_alternate_inverted_hover'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_icons_alternate_inverted_hover'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['patterns']['icons']['alternate']['color_icons_alternate_inverted_active'] = [
      '#type' => 'select',
      '#title' => $this->t('Color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['color_icons_alternate_inverted_active'] ?? 'primary',
      '#prefix' => '<div class="inline-input-holder"><strong>Inverted active</strong>',
    ];

    $form['patterns']['icons']['alternate']['opacity_icons_alternate_inverted_active'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['opacity_icons_alternate_inverted_active'] ?? '1',
      '#suffix' => '</div></div></div>',
    ];

    $form['patterns']['social'] = [
      '#type' => 'details',
      '#title' => $this->t('Social'),
    ];

    $form['patterns']['social']['social_icon_color'] = [
      '#type' => 'select',
      '#title' => $this->t('Color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['social_icon_color'] ?? 'grey3',
      '#prefix' => '<div class="styling-row"><div class="styling-input full"><div class="inline-input-holder "><strong>Social icons</strong>',
    ];

    $form['patterns']['social']['social_icon_opacity'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['social_icon_opacity'] ?? '1',
      '#suffix' => '</div></div></div>',
    ];

    $form['patterns']['tabs'] = [
      '#type' => 'details',
      '#title' => $this->t('Tabbed content'),
    ];

    $form['patterns']['tabs']['tab_color'] = [
      '#type' => 'select',
      '#title' => $this->t('Color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['tab_color'] ?? 'primary',
      '#prefix' => '<div class="styling-row"><div class="styling-input full"><div class="inline-input-holder "><strong>Tab standard</strong>',
    ];

    $form['patterns']['tabs']['tab_opacity'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['tab_opacity'] ?? '1',
    ];

    $form['patterns']['tabs']['tab_font_color'] = [
      '#type' => 'select',
      '#title' => $this->t('Font color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['tab_font_color'] ?? 'grey1',
    ];

    $form['patterns']['tabs']['tab_font_opacity'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['tab_font_opacity'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['patterns']['tabs']['tab_color_active'] = [
      '#type' => 'select',
      '#title' => $this->t('Color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['tab_color_active'] ?? 'grey3',
      '#prefix' => '<div class="inline-input-holder "><strong>Tab Active</strong>',
    ];

    $form['patterns']['tabs']['tab_opacity_active'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['tab_opacity_active'] ?? '1',
    ];

    $form['patterns']['tabs']['tab_font_color_active'] = [
      '#type' => 'select',
      '#title' => $this->t('Tab font color active'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['tab_font_color_active'] ?? 'grey1',
    ];

    $form['patterns']['tabs']['tab_font_opacity_active'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['tab_font_opacity_active'] ?? '1',
      '#suffix' => '</div></div></div>',
    ];

    $form['patterns']['toggler'] = [
      '#type' => 'details',
      '#title' => $this->t('Toggler'),
    ];

    $form['patterns']['toggler']['toggler_color'] = [
      '#type' => 'select',
      '#title' => $this->t('Color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['toggler_color'] ?? 'grey3',
      '#prefix' => '<div class="styling-row"><div class="styling-input full"><div class="inline-input-holder "><strong>Standard</strong>',
    ];

    $form['patterns']['toggler']['toggler_opacity'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['toggler_opacity'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['patterns']['toggler']['toggler_color_hover'] = [
      '#type' => 'select',
      '#title' => $this->t('Color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['toggler_color_hover'] ?? 'grey3',
      '#prefix' => '<div class="styling-row"><div class="styling-input full"><div class="inline-input-holder "><strong>Hover</strong>',
    ];

    $form['patterns']['toggler']['toggler_opacity_hover'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['toggler_opacity_hover'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['patterns']['toggler']['toggler_color_active'] = [
      '#type' => 'select',
      '#title' => $this->t('Color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['toggler_color_active'] ?? 'grey3',
      '#prefix' => '<div class="styling-row"><div class="styling-input full"><div class="inline-input-holder "><strong>Active</strong>',
    ];

    $form['patterns']['toggler']['toggler_opacity_active'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['toggler_opacity_active'] ?? '1',
      '#suffix' => '</div></div></div>',
    ];

    // Section anchornavitation.
    $form['patterns']['anchornavigation'] = [
      '#type' => 'details',
      '#title' => $this->t('Anchor navigation'),
    ];

    $form['patterns']['anchornavigation']['anchornavigation_font_family'] = [
      '#prefix' => '<div class="styling-row"><div class="styling-input"><div class="inline-input-holder"><strong>Main styling</strong>',
      '#type' => 'select',
      '#title' => $this->t('Font family'),
      '#options' => $arr_fonts,
      '#default_value' => $formValues['anchornavigation_font_family'] ?? 'Arial',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_font_weight'] = [
      '#type' => 'select',
      '#options' => $arr_fontweights,
      '#title' => $this->t('Font weight'),
      '#default_value' => $formValues['anchornavigation_font_weight'] ?? 'normal',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_font_style'] = [
      '#type' => 'select',
      '#options' => $arr_fontstyles,
      '#title' => $this->t('Font style'),
      '#default_value' => $formValues['anchornavigation_font_style'] ?? 'normal',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_line_height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Line height'),
      '#default_value' => $formValues['anchornavigation_line_height'] ?? '1.2',
      '#suffix' => '</div>',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_font_size_holder'] = [
      '#type' => 'container',
      '#prefix' => '<div class="inline-input-holder">',
      '#attributes' => [
        'class' => ['container-inline', 'unit-combo-field', 'separator'],
      ],
    ];

    $form['patterns']['anchornavigation']['anchornavigation_font_size_holder']['anchornavigation_font_size'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Font size'),
      '#default_value' => $formValues['anchornavigation_font_size'] ?? '1.2',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_font_size_holder']['anchornavigation_font_size_unit'] = [
      '#type' => 'select',
      '#options' => $arr_units,
      '#default_value' => $formValues['anchornavigation_font_size_unit'] ?? 'rem',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_margin_holder'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'container-inline',
          'unit-combo-field',
          'multi-valule',
          'separator',
        ],
      ],
    ];

    $form['patterns']['anchornavigation']['anchornavigation_margin_holder']['anchornavigation_margin_top'] = [
      '#title' => $this->t('Margin (top, right, bottom, left)'),
      '#type' => 'textfield',
      '#default_value' => $formValues['anchornavigation_margin_top'] ?? '0',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_margin_holder']['anchornavigation_margin_right'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['anchornavigation_margin_right'] ?? '0',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_margin_holder']['anchornavigation_margin_bottom'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['anchornavigation_margin_bottom'] ?? '0',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_margin_holder']['anchornavigation_margin_left'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['anchornavigation_margin_left'] ?? '0',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_margin_holder']['anchornavigation_margin_unit'] = [
      '#type' => 'textfield',
      '#type' => 'select',
      '#options' => $arr_units,
      '#default_value' => $formValues['anchornavigation_margin_unit'] ?? 'px',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_padding_holder'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['container-inline', 'unit-combo-field', 'multi-valule'],
      ],
      '#suffix' => '</div>',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_padding_holder']['anchornavigation_padding_top'] = [
      '#title' => $this->t('Padding (top, right, bottom, left)'),
      '#type' => 'textfield',
      '#default_value' => $formValues['anchornavigation_padding_top'] ?? '5',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_padding_holder']['anchornavigation_padding_right'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['anchornavigation_padding_right'] ?? '5',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_padding_holder']['anchornavigation_padding_bottom'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['anchornavigation_padding_bottom'] ?? '5',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_padding_holder']['anchornavigation_padding_left'] = [
      '#type' => 'textfield',
      '#default_value' => $formValues['anchornavigation_padding_left'] ?? '5',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_padding_holder']['anchornavigation_padding_unit'] = [
      '#type' => 'textfield',
      '#type' => 'select',
      '#options' => $arr_units,
      '#default_value' => $formValues['anchornavigation_padding_unit'] ?? 'px',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_wrapper_background_color'] = [
      '#type' => 'select',
      '#title' => $this->t('Wrapper background color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['anchornavigation_wrapper_background_color'] ?? 'white',
      '#prefix' => '<div class="inline-input-holder separator"><strong>Color settings</strong>',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_wrapper_background_opacity'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['anchornavigation_wrapper_background_opacity'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_font_color_standard'] = [
      '#type' => 'select',
      '#title' => $this->t('Font color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['anchornavigation_font_color_standard'] ?? 'grey1',
      '#prefix' => '<div class="inline-input-holder"><strong>Standard</strong>',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_font_opacity_standard'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['anchornavigation_font_opacity_standard'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_background_color_standard'] = [
      '#prefix' => '<div class="inline-input-holder separator">',
      '#type' => 'select',
      '#title' => $this->t('Background color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['anchornavigation_background_color_standard'] ?? 'white',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_background_opacity_standard'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['anchornavigation_background_opacity_standard'] ?? '1',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_border_color_standard'] = [
      '#type' => 'select',
      '#title' => $this->t('Border color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['anchornavigation_border_color_standard'] ?? 'white',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_border_opacity_standard'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['anchornavigation_border_opacity_standard'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_font_color_hover'] = [
      '#type' => 'select',
      '#title' => $this->t('Font color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['anchornavigation_font_color_hover'] ?? 'white',
      '#prefix' => '<div class="inline-input-holder"><strong>Hover</strong>',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_font_opacity_hover'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['anchornavigation_font_opacity_hover'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_background_color_hover'] = [
      '#prefix' => '<div class="inline-input-holder separator">',
      '#type' => 'select',
      '#title' => $this->t('Background color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['anchornavigation_background_color_hover'] ?? 'primary',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_background_opacity_hover'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['anchornavigation_background_opacity_hover'] ?? '1',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_border_color_hover'] = [
      '#type' => 'select',
      '#title' => $this->t('Hover border color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['anchornavigation_border_color_hover'] ?? 'primary',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_border_opacity_hover'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['anchornavigation_border_opacity_hover'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_font_color_active'] = [
      '#type' => 'select',
      '#title' => $this->t('Font color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['anchornavigation_font_color_active'] ?? 'white',
      '#prefix' => '<div class="inline-input-holder"><strong>Active</strong>',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_font_opacity_active'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['anchornavigation_font_opacity_active'] ?? '1',
      '#suffix' => '</div>',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_background_color_active'] = [
      '#type' => 'select',
      '#title' => $this->t('Background color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['anchornavigation_background_color_active'] ?? 'primary',
      '#prefix' => '<div class="inline-input-holder separator">',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_background_opacity_active'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['separator'],
      ],
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['anchornavigation_background_opacity_active'] ?? '1',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_border_color_active'] = [
      '#type' => 'select',
      '#title' => $this->t('Border color'),
      '#options' => $arr_colors,
      '#default_value' => $formValues['anchornavigation_border_color_active'] ?? 'primary',
    ];

    $form['patterns']['anchornavigation']['anchornavigation_border_opacity_active'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opacity'),
      '#default_value' => $formValues['anchornavigation_border_opacity_active'] ?? '1',
      '#suffix' => '</div>',
    ];

    if (\Drupal::config('system.performance')->get('css')['preprocess']) {
      $form['reset_css'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Reset CSS'),
        '#description' => $this->t('Reset the css by rebuilding the Drupal cache to display changes immediately. This will cause a decrease in performance until the cache is rebuilt.'),
        '#default_value' => 0,
      ];
    }
  }

}
