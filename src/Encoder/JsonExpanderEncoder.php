<?php


/**
Author: Alexharp
Date: 2/7/18
Notes: copy from a badly coded module "ecatEncoder" to provide a quick encoder that can take in xml and output json

*/
namespace Drupal\ecat_mark_up\Encoder;

use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder as SerializationJsonEncoder;
use Saxon\SaxonProcessor;
/**
 * encodes ecat data in xml as base.
 *
 * Simply respond to eCat_xml format requests using the xml encoder.
 */
class JsonExpanderEncoder extends SerializationJsonEncoder implements EncoderInterface{

  /**
   * The formats that this Encoder supports.
   *
   * @var array
   */
  protected static $format = ['eCat_json'];
}
