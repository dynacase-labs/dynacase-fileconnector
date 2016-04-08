<?php
/*
 * @author Anakeen
 * @package FILECONNECTOR
*/

function getFilesAttr($dbaccess, $famid, $name)
{
    
    if (!$famid) return (_("(ifc) family must be selected before"));
    $doc = createDoc($dbaccess, $famid, false);
    
    $tr = array();
    $pattern = preg_quote($name);
    $tinter = $doc->GetFileAttributes();
    while (list($k, $v) = each($tinter)) {
        if (($name == "") || (preg_match("/$pattern/i", $v->labelText, $reg))) {
            $tr[] = array(
                $v->labelText,
                $v->id,
                $v->labelText
            );
        }
    }
    return $tr;
}

function getFamiliesWithFile($dbaccess, $fam)
{
    include_once "EXTERNALS/fdl.php";
    $tr = lfamilies($dbaccess, $fam);
    foreach ($tr as $k => $v) {
        $fd = new_Doc($dbaccess, $v[1], false, false);
        if (!$fd->GetFirstFileAttributes()) {
            unset($tr[$k]);
        } else {
            $tr[$k][3] = $tr[$k][4] = ' ';
        }
    }
    return $tr;
}

function getAvailableProtocols()
{
    return array(
        "FS" => array(
            "label" => _("(ifc) file system") ,
            "needed" => "ifc_path"
        ) ,
        "FTP" => array(
            "label" => _("(ifc) file transfert protocol") ,
            "needed" => "ifc_host|ifc_path"
        )
    );
}

function listProtocols()
{
    $p = array();
    foreach (getAvailableProtocols() as $k => $v) {
        $p[] = "$k|" . $v["label"];
    }
    return implode($p, ",");
}
