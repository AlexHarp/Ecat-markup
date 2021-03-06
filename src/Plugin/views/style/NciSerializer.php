<?php
/**
 * copied from EcatSerializer.
 * User: Alexis
 * Date: 13/11/17
 * Time: 1:02 PM
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
 *   id = "nci_serializer",
 *   title = @Translation("Nci Serializer"),
 *   help = @Translation("Serializes views row data using the Custom Serializer component."),
 *   display_types = {"data"}
 * )
 */
class NciSerializer extends Serializer
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
file_put_contents("/var/www/html/sites/default/files/working.xml", "yes");
    if($this->view->args[0]){
     //check if nid or url alias
      $this->filter = $this->view->args[0];
dpm($this->filter, "filter");
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
file_put_contents("/var/www/html/sites/default/files/xmlnciJoin.xml", $xmlJoin);
file_put_contents("/var/www/html/sites/default/files/ncicontent.xml", $render);
    $expandedXML = $this->expandXML($xmlJoin);



file_put_contents("/var/www/html/sites/default/files/xmlnciexpand.xml", $expandedXML);
//render xml
    $saxon = new SaxonProcessor(true);
    $xslt = $saxon->newXsltProcessor();
    $xslt->compileFromFile("/var/www/html/sites/default/files/NCIv1.xsl");
    $xmlStr = $saxon->parseXmlFromString($expandedXML);
    $xslt->setSourceFromXdmValue($xmlStr);
file_put_contents("/var/www/html/sites/default/files/xsltnciexpand.xml", $xslt);
    return $xslt->transformToString();
  }       


  private function expandXML($render){
  //purge <response> <item> and xml header
    $render = preg_replace(array('/\<response\>|\<\/response\>/'), '', $render);
    $render = preg_replace(array('/\<item key=\"\d+?\"\>|\<\/item\>/'), '', $render);
    $render = preg_replace(array('/\<\?xml version=\"1\.0\"\?\>/'), '', $render);

    $renderSplits = preg_split('/(\<target_id\>\d+?\<\/target_id\>\<target_type>(?:node|taxonomy_term)\<\/target_type>.*?\<\/url>|\<(?:nid|tid)\>\<value\>\d+\<\/value\>\<\/(?:nid|tid)\>)/', $render, -1, PREG_SPLIT_DELIM_CAPTURE);
    $retStr = "";
//Build node map
    $nodeMap = array();
    $nodeStartIndex = -1;
    $currNode = "";
    $nodeContents = array();
    for($c = 1; $c < sizeof($renderSplits); $c++){	//if a refrence to a node
      if(!strncmp($renderSplits[$c], "<target_id>", 11)){
        $nid = sscanf($renderSplits[$c], "<target_id>%d");
        $nid[0] = "%!" . $nid[0] . "%!";
        $renderSplits[$c] = $nid[0];
      } 
      if(preg_match('/\<(nid|tid)\>\<value\>(\d+)/', $renderSplits[$c] ) == 1){
        if($nodeStartIndex == -1){
	  $matches = array();
	  preg_match('/\<(nid|tid)\>\<value\>(\d+)/', $renderSplits[$c], $matches);
	  $currNode = $matches[2];//sscanf($renderSplits[$c], "<nid><value>%d");
          $nodeContents[] = $renderSplits[$c];
          $nodeStartIndex = 1; //[1] as [0]m == {
        } else {
          $nodeMap[$currNode] = $nodeContents;
	  $matches = array();
	  preg_match('/\<(nid|tid)\>\<value\>(\d+)/', $renderSplits[$c], $matches);
          $currNode = $matches[2];//sscanf($renderSplits[$c], "<nid><value>%d");
          $nodeContents = array();
          $nodeContents[] = $renderSplits[$c];
        }
      } else {
        $nodeContents[] = $renderSplits[$c];
      }
      $retStr .= "\n\n\n\n".$renderSplits[$c];
    }
    //add final node
    $nodeContents[sizeof($nodeContents) - 1] = str_replace('</response>',"",$nodeContents[sizeof($nodeContents) - 1]);
    $nodeMap[$currNode] = $nodeContents;
//expand nodes
    $retStr = "";
    foreach($nodeMap as &$node){
      if(preg_match('/\<type\>\<target_id\>(\w+)/', $node[1], $matches)){
        if(!strcmp($matches[1],"product")){
          $expandNid = sscanf($node[0], "<nid><value>%d");
          if($this->filter != -1){
            if($this->filter == $expandNid[0])
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
         $row .= $this->expand($nodeMap, $nid[0]); //if single expand we need to kill the tail comma of the node
         $expandCount++;
         $retStr .= $row;
      } else {
        $retStr .=$row;
        $expandCount = 0;
      }
    }
    $retStr = str_replace('\n',"",$retStr);
    $retStr = str_replace('\r',"",$retStr);
    $retStr = $this->purgeTags($retStr);
    return $retStr;
  }

  private function purgeTags($str){

    $str = preg_replace(array('/\<\/?p>/'), '', $str);
    $str = preg_replace(array('/\<\/?span>/'), '', $str);
    $str = preg_replace(array('/&#\w{3};/'), '', $str);
    return $str;
  }
}
