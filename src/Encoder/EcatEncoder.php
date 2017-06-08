<?php

namespace Drupal\ecat_mark_up\Encoder;

use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Encoder\SerializerAwareEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder as BaseXmlEncoder;
//use Drupal\serialization\Encoder\XmlEncoder as SerializationXmlEncoder;
use Saxon\SaxonProcessor;
/**
 * encodes ecat data in xml as base.
 *
 * Simply respond to eCat_xml format requests using the xml encoder.
 */
class EcatEncoder extends SerializerAwareEncoder implements EncoderInterface{

  /**
   * The formats that this Encoder supports.
   *
   * @var array
   */
  protected static $format = ['eCat_xml'];

/**
   * An instance of the Symfony XmlEncoder to perform the actual encoding.
   *
   * @var \Symfony\Component\Serializer\Encoder\XmlEncoder
   */
  protected $baseEncoder;

  /**
   * Gets the base encoder instance.
   *
   * @return \Symfony\Component\Serializer\Encoder\XmlEncoder
   *   The base encoder.
   */
  public function getBaseEncoder() {
    if (!isset($this->baseEncoder)) {
      $this->baseEncoder = new BaseXmlEncoder();
      $this->baseEncoder->setSerializer($this->serializer);
    }

    return $this->baseEncoder;
  }

  public function encode($data, $format, array $context = array()) {
    $baseXML =  $this->getBaseEncoder()->encode($data, $format, $context);
    /*$expandedXML = $this->expandXML($baseXML);
    file_put_contents("/var/www/html/sites/default/files/ecatbuffer.xml", $expandedXML);

    $saxon = new SaxonProcessor(true);
    $xslt = $saxon->newXsltProcessor();
    //dpm($baseXML, "basexml");
    $xslt->compileFromFile("/var/www/html/sites/default/files/eCat-NCIv3.xsl");  
    $xmlStr = $saxon->parseXmlFromString($expandedXML);
    //dpm($xmlStr, "xmlstr");
    $xslt->setSourceFromXdmValue($xmlStr);
    return $xslt->transformToString();
    //return $xslt->transformFileToString("/var/www/html/sites/default/files/ecatbuffer.xml","/var/www/html/sites/default/files/eCat-NCIv3.xsl");
//dpm($baseXML, "base xml");*/
    return $baseXML;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsEncoding($format) {
    return in_array($format, static::$format);
  }

  private function expandXML($render){
    
    $render = preg_replace(array('/\<item key=\"\d+?\"\>|\<\/item\>/'), '', $render);
     
    $renderSplits = preg_split('/(\<target_id\>\d+?\<\/target_id\>\<target_type>node\<\/target_type>.*?\<\/url>|\<nid\>\<value\>\d+\<\/value\>\<\/nid\>)/', $render, -1, PREG_SPLIT_DELIM_CAPTURE);
    $retStr = "";
//Build node map
    $nodeMap = array();
    $nodeStartIndex = -1;
    $currNode = "";
dpm("running");
    $nodeContents = array();
    for($c = 1; $c < sizeof($renderSplits); $c++){
      dpm($renderSplits[$c], "split");
      if(!strncmp($renderSplits[$c], "<target_id>", 11)){
        //dpm("in loop");
        //dpm($split, "loop split");
        $nid = sscanf($renderSplits[$c], "<target_id>%d");
        $nid[0] = "%!" . $nid[0] . "%!";
        //dpm($nid, "nid");
        $renderSplits[$c] = $nid[0];
      }
      if(!strncmp($renderSplits[$c], "<nid><value>", 12)){
        if($nodeStartIndex == -1){
          $currNode = sscanf($renderSplits[$c], "<nid><value>%d");
          $nodeContents[] = $renderSplits[$c];
          $nodeStartIndex = 1; //[1] as [0]m == {
        } else {
          $nodeMap[$currNode[0]] = $nodeContents;
          $currNode = sscanf($renderSplits[$c], "<nid><value>%d");
          $nodeContents = array();
          $nodeContents[] = $renderSplits[$c];
//wont find last node ?? is this still a thing
        }
      } else {
        $nodeContents[] = $renderSplits[$c];
      }
      $retStr .= "\n\n\n\n".$renderSplits[$c];
    }
//expand nodes
    $retStr = "";
    foreach($nodeMap as &$node){
      if(preg_match('/\<type\>\<target_id\>(\w+)/', $node[1], $matches)){
        if(!strcmp($matches[1],"product")){
          $expandNid = sscanf($node[0], "<nid><value>%d");
          dpm($this->view->args[0], "arg");
          if($this->view->args[0]){
            if($this->view->args[0] == $expandNid[0])
              $retStr .= "<item key=\"".$expandNid[0]."\">".$this->expand($nodeMap, $expandNid[0])."</item>";
          } else {
            $retStr .= "<item key=\"".$expandNid[0]."\">".$this->expand($nodeMap, $expandNid[0])."</item>";
          }
        }   
      } 
    }
    return "<response>".$retStr."</response>";
  }

  private function expand(&$nodeMap, $expandId){
    $retStr = "";

    $expandCount = 0;
    foreach($nodeMap[$expandId] as &$row){
      if(preg_match('/%!\d+%!$/', $row)){
         $row = substr($row, 2);
         $nid = sscanf($row, "%d");
         $row = "";
         $row .= $this->expand($nodeMap, $nid[0]); //if single expand we need to kill the tail comma of te node
         $expandCount++;
         $retStr .= $row;
      } else {
        $retStr .=$row;
        $expandCount = 0;
      }
    }
    $retStr = str_replace('\n',"",$retStr);
    $retStr = str_replace('\r',"",$retStr);
    return $retStr;
    return $retStr;
  }
}
