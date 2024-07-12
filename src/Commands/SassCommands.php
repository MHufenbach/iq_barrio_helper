<?php

namespace Drupal\iq_barrio_helper\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Theme\Registry;
use Drupal\iq_barrio_helper\Service\IqBarrioService;
use Drupal\iq_scss_compiler\Commands\SassCommands as IqScssCompilerCommands;
use Drush\Commands\DrushCommands;

/**
 * Sass Drush commands.
 */
class SassCommands extends DrushCommands {

  /**
   * Constructs a new SassCommands object.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   Logger channel factory service.
   * @param \Drupal\iq_scss_compiler\Commands\SassCommands $sassCommands
   *   Iqual sass commands.
   * @param \Drupal\iq_barrio_helper\Service\IqBarrioService $iqBarrioService
   *   IqBarrio Helper service.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cacheTagsInvalidator
   *   Cache tags invalidator service.
   * @param \Drupal\Core\Theme\Registry $themeRegistry
   *   Theme registry service.
   */
  public function __construct(
    protected readonly LoggerChannelFactoryInterface $loggerChannelFactory,
    protected readonly IqScssCompilerCommands $sassCommands,
    protected readonly IqBarrioService $iqBarrioService,
    protected readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    protected readonly Registry $themeRegistry,
  ) {
  }

  /**
   * Import all products and certificates.
   *
   * @options folders Whether or not an extra message should be displayed to the user.
   *
   * @command iq_barrio_helper:sass-watch
   * @aliases iq_barrio_helper-sass-watch
   *
   * @usage drush iq_barrio_helper:sass-watch --folders=themes,modules
   */
  public function watch($options = ['folders' => 'themes', 'ttl' => 60]) {
    $this->sassCommands->watch($options);
  }

  /**
   * Interpolates configuration values in the scss definition file.
   *
   * @options folders Whether or not an extra message should be displayed to the user.
   *
   * @command iq_barrio_helper:sass-interpolate-config
   * @aliases iq_barrio_helper-sass-interpolate-config
   *
   * @usage drush iq_barrio_helper:sass-interpolate-config
   */
  public function interpolateConfig() {
    $this->iqBarrioService->interpolateConfig();
  }

  /**
   * Compile scss.
   *
   * @options folders Whether or not an extra message should be displayed to the user.
   *
   * @command iq_barrio_helper:sass-compile
   * @aliases iq_barrio_helper-sass-compile
   *
   * @usage drush iq_barrio_helper:sass-compile --folders=themes,modules,sites/default/files/styling_profiles --continueOnErrors=false
   */
  public function compile(
    $options = [
      'folders' => 'themes/custom,modules/custom,sites/default/files/styling_profiles',
      'continueOnErrors' => FALSE,
    ],
  ) {
    $this->sassCommands->compile($options);
  }

  /**
   * Run SASS compilations after deploy.
   *
   * @hook post-command deploy:hook
   */
  public function deploy($result, CommandData $commandData) {
    $this->interpolateConfig();
    $this->compile();
    $this->cacheTagsInvalidator->invalidateTags(['rendered']);
    $this->themeRegistry->reset();
  }

}
