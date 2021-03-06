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
 *   id = "Xml_Expand_serializer",
 *   title = @Translation("Xml Expander Serializer"),
 *   help = @Translation("Serializes views row data using the Custom Serializer component."),
 *   display_types = {"data"}
 * )
 */
class XmlExpandSerializer extends Serializer
{
    protected $contextArg;
    protected $filter;
  
    public function query() {
      parent::query();
      $this->view->query->where = [];//remove the contextual filter so we can still grab all for recursion
//      dpm($this->view->query, "query");
    }

    public function preRender($result) {
      if (!empty($this->view->rowPlugin)) {
        $this->view->rowPlugin->preRender($result);
      }
    }
  
    /**
     * {@inheritdoc}
     */
  public function render() {
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
    $view = Views::getView('taxonomy_view');
    if (is_object($view)) {
      $view->setDisplay('rest_export_1');
      $view->preExecute();
      $view->execute();
      $taxContent = $view->render();//buildRenderable('rest_export_1');
    }

//file_put_contents("/var/www/html/sites/default/files/tax.xml", $taxContent['#markup']);

//expand nodes
    $render = parent::render();
    $xmlJoin = $render.$taxContent['#markup'];
    $xmlJoin = str_replace('&nbsp;', '&#160;', $xmlJoin);
//file_put_contents("/var/www/html/sites/default/files/xmlJoin.xml", $xmlJoin);
    include 'expander.php';
    $expandedXML = expandXML($xmlJoin, $this->filter);

//return $expandedXML;
//$here = "here";
//file_put_contents("/var/www/html/sites/default/files/xmlecatexpand.xml", $expandedXML);
//render xml
 /*   $saxon = new SaxonProcessor(true);
    $xmlStr = $saxon->parseXmlFromString($expandedXML);
    return $xmlStr;*/
    return $expandedXML;
  }       
}
