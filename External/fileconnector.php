<?php

function getFilesAttr($dbaccess,$famid,$name) { 

  if ( ! $famid) return (_("family must be selected before"));
  $doc = createDoc($dbaccess, $famid, false);
  // internal attributes
  $ti = array("title" => _("doctitle"),
              "revdate" => _("revdate"),
              "revision" => _("revision"),
              "owner" => _("owner"),
              "state" => _("state"));
  
  $tr = array();
  
  $tinter = $doc->GetFileAttributes();
  while(list($k,$v) = each($tinter)) {
    if (($name == "") ||    (eregi("$name", $v->labelText , $reg)))
      $tr[] = array($v->labelText ,
                    $v->id,
		    $v->labelText);
    
  }
  return $tr;  
}



?>