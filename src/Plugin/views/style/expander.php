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

function expandXML($render, $filter){
  //pure <response> <item> and xml header
    $render = preg_replace(array('/\<response\>|\<\/response\>/'), '', $render);
    $render = preg_replace(array('/\<item key=\"\d+?\"\>|\<\/item\>/'), '', $render);
    $render = preg_replace(array('/\<\?xml version=\"1\.0\"\?\>/'), '', $render);

    //seperate into entity items (both nodes and tax)
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
      //parses untill ids are declared, holds that id untill a new one or eof is found, once new one is found, add everything previous to that as an entry in node map
      if(preg_match('/\<(nid|tid)\>\<value\>(\d+)/', $renderSplits[$c] ) == 1){			//if a nid or tid value exist in split
        if($nodeStartIndex == -1){								//if this is the first match (init)
	  $matches = array();
	  preg_match('/\<(nid|tid)\>\<value\>(\d+)/', $renderSplits[$c], $matches);		//
	  $currNode = $matches[2];//sscanf($renderSplits[$c], "<nid><value>%d");
          $nodeContents[] = $renderSplits[$c];
          $nodeStartIndex = 1; //[1] as [0]m == {
        } else {										//find nid seperators after the first one, adds current node contents to past nid entry on node nap
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
          if($filter != -1){
            if($filter == $expandNid[0])
              $retStr .= "<item key=\"".$expandNid[0]."\">".expand($nodeMap, $expandNid[0])."</item>";
          } else {
            $retStr .= "<item key=\"".$expandNid[0]."\">".expand($nodeMap, $expandNid[0])."</item>";
          }
        }
      }
    }
    return "<response>".$retStr."</response>";
}

function expand(&$nodeMap, $expandId){
    $retStr = "";
    if(!array_key_exists($expandId, $nodeMap)){
      return $expandId;
    }
    $expandCount = 0;
    foreach($nodeMap[$expandId] as &$row){
      if(preg_match('/%!\d+%!$/', $row)){
         $row = substr($row, 2);
         $nid = sscanf($row, "%d");
         $row = "";
         $row .= expand($nodeMap, $nid[0]); //if single expand we need to kill the tail comma of te node
         $expandCount++;
         $retStr .= $row;
      } else {
        $retStr .=$row;
        $expandCount = 0;
      }
    }
    $retStr = str_replace('\n',"",$retStr);
    $retStr = str_replace('\r',"",$retStr);
    $retStr = purgeTags($retStr);
    return $retStr;
}

function purgeTags($str){

    $str = preg_replace(array('/\<\/?p>/'), '', $str);
    $str = preg_replace(array('/\<\/?span>/'), '', $str);
    $str = preg_replace(array('/&#\w{3};/'), '', $str);
    return $str;
}

