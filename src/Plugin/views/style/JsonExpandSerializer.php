<?php
/**
 * Created by PhpStorm.
 * User: Alexis
 * Date: 28/03/17
 * Time: 2:15 PM
 */

namespace Drupal\ecat_mark_up\Plugin\views\style;

use Drupal\rest\Plugin\views\style\Serializer;
use Drupal\views\Views;
use Saxon\SaxonProcessor;
use Drupal\Core\url;
/**
 * The style plugin for serialized output formats.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "json_Expand_serializer",
 *   title = @Translation("json Expander Serializer"),
 *   help = @Translation("Serializes views row data using the Custom Serializer component."),
 *   display_types = {"data"}
 * )
 */
class JsonExpandSerializer extends Serializer
{
    protected $contextArg;
    protected $filter;
  
    public function query() {
      parent::query();
      //$requestFormat = $this->view->getRequest()->setRequestFormat('xml'); 
     // $requestFormat = $this->view->getRequest()->query->replace(array('_format' => 'xml')); 
      $this->view->query->where = [];//remove the contextual filter so we can still grab all for recursion
//      dpm($this->view->query, "query");
    }

    public function preRender($result) {
      if (!empty($this->view->rowPlugin)) {
        $this->view->rowPlugin->preRender($result);
      }
    }

   /* public function json_to_xml($json) {
      include("/usr/share/pear/XML/Serializer.php");
      $serializer = new XML_Serializer();
      $obj = json_decode($json);

      if ($serializer->serialize($obj)) {
        return $serializer->getSerializedData();
      }
      else {
        return null;
      }
    }*/ 
 
    /**
     * {@inheritdoc}
     * many attempts where made to intercept the json request and redirect it to an xml request to no avail.
     * Thus a dirty fix was made below
     */
    public function render() {
//kint($this->view->getRequest(), "requests");
      //  kint(NULL,"here");
    //    kint($this->view->getRequest()->getRequestFormat(),"req fmt");
        if(!strcmp($this->view->getRequest()->getRequestFormat(),"json")){
            $httpStr = "http://"
                .$this->view->getRequest()->headers->get("host")
                .$this->view->getRequest()->getPathInfo()."?_format=xml";    
            $jsonOut = file_get_contents($httpStr);
  //          kint($httpStr,"httpStr");
//kint($xmlLoad, "redirect");
        } else {

            kint(NULL,"here2");
        
//kint($this->view->args, "args");
//kint($this->view->args, "args");
//kint($this->view->build_info, "build info");
kint($this->view->element, "element");
//kint($this->view->exposed_data, "exposed_data");
//kint($this->view->exposed_widgets, "exposed_widgets");
//kint($this->view->header, "header");
kint($this->view->getRequest(), "requests");
//kint($this->view->getResponse(), "response");
//kint($this->view->query, "query");
//kint($this->view, "view");
    if($this->view->args[0]){
      //check if nid or url alias
      $this->filter = $this->view->args[0];
      //if it is not a number
      $filterTrim = trim($this->filter, "\\");
      if(!(is_numeric($filterTrim) && $filterTrim > 0 && $filterTrim == round($filterTrim, 0))){
        $alias = \Drupal::service('path.alias_manager')->getPathByAlias('/'.$this->filter);
        $params = Url::fromUri("internal:" . $alias)->getRouteParameters();
        $entity_type = key($params);
        $node = \Drupal::entityTypeManager()->getStorage($entity_type)->load($params[$entity_type]);
        $this->filter = $node->nid->value;
      } 
    } else {
      $this->filter = -1;
    }

// get taxonomy info
    //kpm($view, "view");
    $view = Views::getView('taxonomy_view');
    if (is_object($view)) {
      $view->setDisplay('rest_export_1');
//changes request format
      $requestFormat = $view->getRequest();
      $requestFormat->setRequestFormat('xml');
      $view->setRequest($requestFormat);
//cont.
      $view->preExecute();
      $view->execute();
      $taxContent = $view->render();//buildRenderable('rest_export_1');
      file_put_contents("/var/www/html/sites/default/files/debug/Taxrender.xml", $taxContent);
    }

//file_put_contents("/var/www/html/sites/default/files/tax.xml", $taxContent['#markup']);

//expand nodes
//changes request format
   // $requestFormat = $this->view->getRequest()->setRequestFormat('xml'); 
   // $requestFormat = $this->view->getRequest()->query->replace(array('_format' => 'xml')); 
   // kint($this->view->getRequest()->query, "query trawl");//->replace(array('_format' => 'xml')); 
  //  $requestFormat->setRequestFormat('xml');
//    $this->view->setRequest($requestFormat);
kint($this->view->getRequest(), "requests");
    $render = parent::render();
    file_put_contents("/var/www/html/sites/default/files/debug/render.xml", $render);
//convert json to xml to use the same usecase as xml expanding
  //  $render = $this->json_to_xml($render);
    file_put_contents("/var/www/html/sites/default/files/debug/json-to-xmlRender.xml", $render);
    $xmlJoin = $render.$taxContent['#markup'];
    $xmlJoin = str_replace('&nbsp;', '&#160;', $xmlJoin);
    file_put_contents("/var/www/html/sites/default/files/debug/xmlJoin.xml", $xmlJoin);
    include 'expander.php';
    $expandedXML = expandXML($xmlJoin, $this->filter);
    $xmlLoad = simplexml_load_string($expandedXML);
    $jsonOut = json_encode($xmlLoad);
        }
    file_put_contents("/var/www/html/sites/default/files/debug/xmlraw.xml", $expandedXML);
    file_put_contents("/var/www/html/sites/default/files/debug/xmlWellFormed.xml", $xmlLoad);
    file_put_contents("/var/www/html/sites/default/files/debug/jsonOut.json", $jsonOut);
    file_put_contents("/var/www/html/sites/default/files/debug/jsonerr.json", json_last_error() );
    return $jsonOut;





//return $expandedXML;
//$here = "here";
//file_put_contents("/var/www/html/sites/default/files/xmlecatexpand.xml", $expandedXML);
//render xml
/*    $saxon = new SaxonProcessor(true);
    $xmlStr = $saxon->parseXmlFromString($expandedXML);
    return $xmlStr;*/
    //$jsonOut = $expandedXML;
   // $jsonOut = json_encode($expandedXML);
    file_put_contents("/var/www/html/sites/default/files/jsonOut.json", $jsonOut);
   // $xml = simplexml_load_string($);
    /*if($jsonOut)
      file_put_contents("/var/www/html/sites/default/files/jsonOut.json", $jsonOut);
    else
      file_put_contents("/var/www/html/sites/default/files/jsonOut.json", "failed");*/
    file_put_contents("/var/www/html/sites/default/files/jsonerr.json", json_last_error() );
    //return $jsonOut;
//$expandedXML;
//
    //
    }       
}
