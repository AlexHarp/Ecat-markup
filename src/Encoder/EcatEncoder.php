<?php

namespace Drupal\ecat_mark_up\Encoder;

use Symfony\Component\Serializer\Encoder\XmlEncoder as SerializationXmlEncoder;
//use Drupal\serialization\Encoder\XmlEncoder as SerializationXmlEncoder;
use Saxon\SaxonProcessor;
/**
 * encodes ecat data in xml as base.
 *
 * Simply respond to eCat_xml format requests using the xml encoder.
 */
class EcatEncoder extends SerializationXmlEncoder {

  /**
   * The formats that this Encoder supports.
   *
   * @var array
   */
  protected static $format = ['eCat_xml'];
}
