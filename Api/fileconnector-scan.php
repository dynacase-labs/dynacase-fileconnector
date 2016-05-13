<?php
/*
 * @author Anakeen
 * @package FILECONNECTOR
*/

global $action;

$action->log->debug("ifile-scan: start");
$search = new SearchDoc($action->dbaccess, "FILECONNECTOR");
$search->setObjectReturn();
$t = $search->search();

while ($currentDoc = $search->getNextDoc()) {
    /* @var \Dcp\Fileconnector\Fileconnector $currentDoc */
    $action->log->debug("ifile-scan: process " . $currentDoc->title);
    $currentDoc->scanSource();
}
$action->log->debug("ifile-scan: stop");
