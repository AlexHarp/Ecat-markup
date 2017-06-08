<?php

namespace Drupal\ecat_mark_up\Normalizer;

use Drupal\serialization\Normalizer\NormalizerBase as SerializationNormalizerBase;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Base class for Normalizers.
 */
abstract class NormalizerBase extends SerializationNormalizerBase implements DenormalizerInterface {

  /**
   * The formats that the Normalizer can handle.
   *
   * @var array
   */
  protected $formats = ['eCat_XML'];
}
