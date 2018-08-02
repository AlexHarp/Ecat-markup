<?php

namespace Drupal\ecat_mark_up;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;

/**
 * Adds ecat+xml as known format.
 */
class EcatServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->has('http_middleware.negotiation') && is_a($container->getDefinition('http_middleware.negotiation')->getClass(), '\Drupal\Core\StackMiddleware\NegotiationMiddleware', TRUE)) {
      $container->getDefinition('http_middleware.negotiation')->addMethodCall('registerFormat', ['eCat_xml', ['application/eCat+xml']]);
    }
  }

}
