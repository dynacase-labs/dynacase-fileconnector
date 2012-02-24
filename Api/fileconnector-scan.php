<?php
/*
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
 * @package FILECONNECTOR
*/

include_once ("FDL/Class.SearchDoc.php");

global $action;
$dbaccess = $action->getParam("FREEDOM_DB");

$action->log->debug("ifile-scan: start");
$s = new SearchDoc($dbaccess, "FILECONNECTOR");
$s->setObjectReturn();
$t = $s->search();

while ($v = $s->nextDoc()) {
    $action->log->debug("ifile-scan: process " . $v->title);
    $v->scanSource();
}
$action->log->debug("ifile-scan: stop");
?>
