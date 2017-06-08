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

/**
 * The style plugin for serialized output formats.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "ecat_serializer",
 *   title = @Translation("eCat Serializer"),
 *   help = @Translation("Serializes views row data using the Custom Serializer component."),
 *   display_types = {"data"}
 * )
 */
class EcatSerializer extends Serializer
{
    protected $contextArg;
  
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

// get taxonomy info
    $view = Views::getView('taxonomy_view');
    if (is_object($view)) {
      $view->setDisplay('rest_export_1');
      $view->preExecute();
      $view->execute();
      $taxContent = $view->render();//buildRenderable('rest_export_1');
    }

//expand nodes
    $render = parent::render();
    $expandedXML = $this->expandXML($render.$taxContent['#markup']);

//render xml
    $saxon = new SaxonProcessor(true);
    $xslt = $saxon->newXsltProcessor();
    $xslt->compileFromFile("/var/www/html/sites/default/files/eCat-NCIv3.xsl");
    $xmlStr = $saxon->parseXmlFromString($expandedXML);
    $xslt->setSourceFromXdmValue($xmlStr);
    return $xslt->transformToString();
  }       


  private function expandXML($render){
    $render = preg_replace(array('/\<item key=\"\d+?\"\>|\<\/item\>/'), '', $render);

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
  }
}
