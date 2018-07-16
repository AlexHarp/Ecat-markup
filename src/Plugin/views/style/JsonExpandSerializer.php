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
      $this->view->query->where = [];//remove the contextual filter so we can still grab all for recursion
    }

    public function preRender($result) {
      if (!empty($this->view->rowPlugin)) {
        $this->view->rowPlugin->preRender($result);
      }
    }

    /**
     * {@inheritdoc}
     * many attempts where made to intercept the json request and redirect it to an xml request to no avail.
     * Thus a dirty fix was made below
     */
    public function render() {
        if(!strcmp($this->view->getRequest()->getRequestFormat(),"json")){
            $httpStr = "http://"
                .$this->view->getRequest()->headers->get("host")
                .$this->view->getRequest()->getPathInfo()."?_format=xml";    
            $jsonOut = file_get_contents($httpStr);
        } else {
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
                //changes request format
                $requestFormat = $view->getRequest();
                $requestFormat->setRequestFormat('xml');
                $view->setRequest($requestFormat);
                //cont.
                $view->preExecute();
                $view->execute();
                $taxContent = $view->render();//buildRenderable('rest_export_1');
                //file_put_contents("/var/www/html/sites/default/files/debug/Taxrender.xml", $taxContent);
            }


            //expand nodes
            $render = parent::render();
            //file_put_contents("/var/www/html/sites/default/files/debug/render.xml", $render);
            //convert json to xml to use the same usecase as xml expanding
            //file_put_contents("/var/www/html/sites/default/files/debug/json-to-xmlRender.xml", $render);
            $xmlJoin = $render.$taxContent['#markup'];
            $xmlJoin = str_replace('&nbsp;', '&#160;', $xmlJoin);
            //file_put_contents("/var/www/html/sites/default/files/debug/xmlJoin.xml", $xmlJoin);
            include 'expander.php';
            $expandedXML = expandXML($xmlJoin, $this->filter);
            $xmlLoad = simplexml_load_string($expandedXML);
            $jsonOut = json_encode($xmlLoad);
        }
        return $jsonOut;
    }       
}
