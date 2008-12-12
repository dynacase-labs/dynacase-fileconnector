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

function getFamiliesWithFile($dbaccess, $fam) {
  include_once("EXTERNALS/fdl.php");
  $tr = lfamilies($dbaccess,$fam);
  foreach ($tr as $k=>$v) {
    $fd = new_Doc($dbaccess, $v[1], false, false);
    if (!$fd->GetFirstFileAttributes()) {
      unset($tr[$k]);
    } else {
      $tr[$k][3] = $tr[$k][4] = ' ';
    }
  }
  return $tr;
}
    

?>