<?php

include_once("FDL/Class.SearchDoc.php");

global $action;
$dbaccess = $action->getParam("FREEDOM_DB");

$action->log->debug("ifile-scan: start");
$s = new SearchDoc($dbaccess, "FILECONNECTOR");
$s->setObjectReturn();
$t = $s->search();

while ($v = $s->nextDoc()) { 
  $action->log->debug("ifile-scan: process ".$v->title);
  $v->scanSource();
}
$action->log->debug("ifile-scan: stop");

?>