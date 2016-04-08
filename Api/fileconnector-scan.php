<?php
/*
 * @author Anakeen
 * @package FILECONNECTOR
*/


global $action;
$dbaccess = $action->getParam("FREEDOM_DB");

$action->log->debug("ifile-scan: start");
$search = new SearchDoc($dbaccess, "FILECONNECTOR");
$search->setObjectReturn();
$t = $search->search();

while ($currentDoc = $search->getNextDoc()) {
    /* @var $currentDoc _FILECONNECTOR */
    $action->log->debug("ifile-scan: process " . $currentDoc->title);
    $currentDoc->scanSource();
}
$action->log->debug("ifile-scan: stop");

