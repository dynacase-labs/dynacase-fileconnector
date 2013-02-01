<?php
/*
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
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

