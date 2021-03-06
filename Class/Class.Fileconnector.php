<?php
/*
 * @author Anakeen
 * @package FILECONNECTOR
*/

namespace Dcp\Fileconnector;
use \Dcp\AttributeIdentifiers as Attributes;
use \Dcp\AttributeIdentifiers\Fileconnector as MyAttributes;
/**
 * File Connector
 */
Class Fileconnector extends \Dcp\Family\Document
{
    
    public function postStore()
    {
        // compute URI
        switch ($this->getRawValue(MyAttributes::ifc_mode)) {
            case "FTP":
                $uri = strtolower($this->getRawValue(MyAttributes::ifc_mode)) . "://";
                $uri.= ($this->getRawValue(MyAttributes::ifc_login) == "" ? "anonymous" : $this->getRawValue(MyAttributes::ifc_login));
                $uri.= ":*********";
                $uri.= "@";
                $uri.= $this->getRawValue(MyAttributes::ifc_host);
                if ($this->getRawValue(MyAttributes::ifc_port) != "") {
                    $uri.= ":" . $this->getRawValue(MyAttributes::ifc_port);
                }
                $uri.= $this->getRawValue(MyAttributes::ifc_path);
                break;

            case "FS":
                $uri = $this->getRawValue(MyAttributes::ifc_path);
                break;

            default:
                $uri = "-unknown protocol-";
        }
        $this->setValue(MyAttributes::ifc_uris, $uri);
        $valid = 0;
        $dt = opendir($this->uriWithPassword($uri, $this->getRawValue(MyAttributes::ifc_password)));
        if (!$dt) {
            AddWarningMsg(sprintf(_("(ifc) can't access file from %s") , $uri));
        } else {
            AddWarningMsg(sprintf(_("(ifc) access to %s is checked") , $uri));
            $valid = 1;
            closedir($dt);
        }
        
        $this->setValue(MyAttributes::ifc_opened, $valid);
        $this->modify(true, array(
            MyAttributes::ifc_uris,
            MyAttributes::ifc_opened
        ) , true);
        
        $err = $this->designProcessus();
        AddWarningMsg($err);
        
        return;
    }

    protected function uriWithPassword($uri, $password) {
        $newUri = '';
        $prefixHost = '';
        $tokens = parse_url($uri);
        if (isset($tokens['scheme'])) {
            $newUri.= $tokens['scheme'] . '://';
        }
        if (isset($tokens['user'])) {
            $newUri.= $tokens['user'];
            $prefixHost = '@';
            if ($password !== '') {
                $newUri.= ':' . urlencode($password);
            }
        }
        if (isset($tokens['host'])) {
            if ($prefixHost !== '') {
                $newUri.= $prefixHost;
            }
            $newUri.= $tokens['host'];
        }
        if (isset($tokens['port'])) {
            $newUri.= ':' . $tokens['port'];
        }
        if (isset($tokens['path']) && $tokens['path'] !== '') {
            $newUri.= $tokens['path'];
        }
        return $newUri;
    }

    final public function scanSource()
    {
        global $action;
        $dir = $this->getRawValue(MyAttributes::ifc_uris);
        $proto = $this->getRawValue(MyAttributes::ifc_mode);
        $dt = opendir($this->uriWithPassword($dir, $this->getRawValue(MyAttributes::ifc_password)));
        if (!$dt) {
            $action->log->error("[" . $this->title . "]: can't open dir " . $this->getRawValue(MyAttributes::ifc_uris));
            return null;
        }
        $nfn = $nfs = $nfm = $nfx = array();
        clearstatcache();
        $root = $this->getRawValue(MyAttributes::ifc_uris);
        $ke = 0;
        while (false !== ($entry = readdir($dt))) {
            if (is_dir($root . "/" . $entry)) continue;
            $nfn[$ke] = $entry;
            
            switch ($proto) {
                case "FS":
                    $nfs[$ke] = filesize($root . "/" . $entry);
                    $nfm[$ke] = date("Y-m-d H:i:s", filemtime($root . "/" . $entry));
                    break;

                case "FTP":
                    $res = $this->fcFtpFileInfo($entry);
                    $nfs[$ke] = $res["size"];
                    $nfm[$ke] = date("Y-m-d H:i:s", $res["date"]);
                    break;

                default:
                    $nfs[$ke] = " ";
                    $nfm[$ke] = " ";
            }
            $nfx[$ke] = 'N';
            $ke++;
        }
        
        closedir($dt);
        $patterns_n = $this->getMultipleRawValues(MyAttributes::ifc_sl_name);
        $patterns_v = $this->getMultipleRawValues(MyAttributes::ifc_sl_pattern);
        
        $ofp = $this->getMultipleRawValues(MyAttributes::ifc_c_match);
        $ofn = $this->getMultipleRawValues(MyAttributes::ifc_c_name);
        $ofs = $this->getMultipleRawValues(MyAttributes::ifc_c_size);
        $ofm = $this->getMultipleRawValues(MyAttributes::ifc_c_mtime);
        $ofx = $this->getMultipleRawValues(MyAttributes::ifc_c_state);
        
        $cfn = $cfs = $cfm = $cfx = $cfp = array();
        $kk = 0;
        
        foreach ($nfn as $k => $v) {
            $p = array_search($v, $ofn);
            if ($p === false || $ofs[$p] != $nfs[$k]) {
                // new file => added
                foreach ($patterns_v as $kpm => $vpm) {
                    $match = @preg_match('/' . $vpm . '/', $nfn[$k]);
                    if ($match === false) {
                        $matcherror = error_get_last();
                        $err = sprintf("pattern error %s : %s", $vpm, $matcherror["message"]);
                        $action->log->error($err);
                        AddWarningMsg($err);
                        return null;
                    }
                    if ($match) {
                        $cfp[$kk] = $patterns_n[$kpm];
                        $action->log->debug("[" . $nfn[$k] . "] match pattern {" . $cfp[$kk] . "}");
                        $cfn[$kk] = $nfn[$k];
                        $cfs[$kk] = $nfs[$k];
                        $cfm[$kk] = $nfm[$k];
                        $cfx[$kk] = $nfx[$k];
                        $kk++;
                        break;
                    }
                }
            } else {
                // already in list => get the old one
                $cfp[$kk] = $ofp[$p];
                $cfn[$kk] = $ofn[$p];
                $cfs[$kk] = $ofs[$p];
                $cfm[$kk] = $ofm[$p];
                $cfx[$kk] = $ofx[$p];
                $kk++;
            }
        }
        
        $this->clearValue(MyAttributes::ifc_c_match);
        $this->clearValue(MyAttributes::ifc_c_name);
        $this->clearValue(MyAttributes::ifc_c_size);
        $this->clearValue(MyAttributes::ifc_c_mtime);
        $this->clearValue(MyAttributes::ifc_c_state);
        
        $this->setValue(MyAttributes::ifc_c_match, $cfp);
        $this->setValue(MyAttributes::ifc_c_name, $cfn);
        $this->setValue(MyAttributes::ifc_c_size, $cfs);
        $this->setValue(MyAttributes::ifc_c_mtime, $cfm);
        $this->setValue(MyAttributes::ifc_c_state, $cfx);
        
        $this->setValue(MyAttributes::ifc_lastscan, $this->getTimeDate(0, true));
        
        return $this->modify(true, array(
            MyAttributes::ifc_lastscan,
            MyAttributes::ifc_c_match,
            MyAttributes::ifc_c_name,
            MyAttributes::ifc_c_size,
            MyAttributes::ifc_c_mtime,
            MyAttributes::ifc_c_state
        ) , true);
    }
    /**
     * @apiExpose
     * reset (clean) list of file to be processes
     * @return bool
     */
    final public function resetScan()
    {
        $this->clearValue(MyAttributes::ifc_c_match);
        $this->clearValue(MyAttributes::ifc_c_name);
        $this->clearValue(MyAttributes::ifc_c_size);
        $this->clearValue(MyAttributes::ifc_c_mtime);
        $this->clearValue(MyAttributes::ifc_c_state);
        $err = $this->modify(true, array(
            MyAttributes::ifc_c_match,
            MyAttributes::ifc_c_name,
            MyAttributes::ifc_c_size,
            MyAttributes::ifc_c_mtime,
            MyAttributes::ifc_c_state
        ) , true);
        return $err;
    }
    /**
     * @apiExpose
     * @return bool
     */
    final public function verifyNewCxFiles()
    {
        $err = $this->scanSource();
        $st = $this->getMultipleRawValues(MyAttributes::ifc_c_state);
        foreach ($st as $v) {
            if ($v == 'N') return $err;
        }
        return $err;
    }
    
    final public function getNewCxFiles()
    {
        $ret = array();
        $this->scanSource();
        $st = $this->getMultipleRawValues(MyAttributes::ifc_c_state);
        $fn = $this->getMultipleRawValues(MyAttributes::ifc_c_name);
        foreach ($st as $k => $v) {
            if ($v == 'N') {
                $ret[] = $fn[$k];
            }
        }
        return $ret;
    }
    /**
     * @apiExpose
     * @return string
     */
    final public function transfertNewCxFiles()
    {
        $nf = $this->getNewCxFiles();
        $err = '';
        foreach ($nf as $v) {
            $err.= $this->transfertCxFile($v);
        }
        return $err;
    }
    
    public function PreTransfert(&$fileconnector, $filepath, &$doc)
    {
        return "";
    }
    
    public function PostTransfert(&$fileconnector, $filepath, &$doc)
    {
        return "";
    }
    /**
     * @apiExpose
     * @param $file
     * @param int $fromihm
     * @return string
     */
    final public function transfertCxFile($file, $fromihm = 0)
    {
        global $action;
        
        $err = $this->iPreTransfert($file);
        if ($err != '') {
            return sprintf(_("(ifc) file %s transfert(ipre) error=%s") , $file, $err);
        }
        
        switch ($this->getRawValue(MyAttributes::ifc_mode)) {
            case "FTP":
                $fpath = $this->fcFtpLocalFile($file);
                break;

            default:
                $fpath = $this->getRawValue(MyAttributes::ifc_path) . "/" . $file;
        }
        $infos = $this->iGetFileTransf($file);
        
        $doc = createDoc($this->dbaccess, $infos['fam'], false);
        if (!$doc) $err = sprintf(_("(ifc) can't transfert file %s to family %s") , $file, $infos['fam']);
        else {
            if (method_exists($this, "preTransfert")) $err = $this->PreTransfert($this, $fpath, $doc);
            if ($err != "") {
                $err = sprintf(_("(ifc) file %s (fam %s / attr %s) transfert(pre) error=%s") , $file, $infos['fam'], $infos['attr'], $err);
            } else {
                if (method_exists($doc, "connectorExecute")) {
                    /** @noinspection PhpUndefinedMethodInspection */
                    $err = $doc->connectorExecute($this, $fpath);
                } else {
                    $doc->disableEditControl();
                    $attr = $infos['attr'];
                    if ($attr == "") {
                        $oa = $doc->GetFirstFileAttributes();
                        $attr = $oa->id;
                    }
                    if ($attr == "") {
                        $err = sprintf(_("(ifc) file %s : no attribute set and no file/image attribute for family %s") , $file, $infos['fam']);
                    } else {
                        error_log("storeFile($attr, $fpath, $file)");
                        $err = $doc->setFile($attr, $fpath, $file);
                        if ($err != "") $err = sprintf(_("(ifc) file %s (fam %s / attr %s) transfert(store) error=%s") , $file, $infos['fam'], $attr, $err);
                        else {
                            $doc->add();
                            if ($err != "") $err = sprintf(_("(ifc) file %s (fam %s / attr %s) transfert(add) error=%s") , $file, $infos['fam'], $attr, $err);
                            else {
                                $doc->addHistoryEntry(sprintf(_("(ifc) Creation from file connector %s") , $this->getTitle()));
                                $err = $doc->postStore();
                                if ($err != "") $err = sprintf(_("(ifc) file %s (fam %s / attr %s) transfert(post) error=%s") , $file, $infos['fam'], $attr, $err);
                                else {
                                    $err = $doc->refresh();
                                    if ($err != "") $err = sprintf(_("(ifc) file %s (fam %s / attr %s) transfert(refresh) error=%s") , $file, $infos['fam'], $attr, $err);
                                }
                            }
                        }
                    }
                }
            }
        }
        if ($err != "") {
            $action->log->error($err);
            AddWarningMsg($err);
        } else {
            $action->log->debug("file $file was transfered");
            $this->setCxFileStatus($file, "I");
            $this->iPostTransfert($file, $fpath, $doc);
            if (method_exists($this, "postTransfert")) $err = $this->PostTransfert($this, $fpath, $doc);
            if ($fromihm == 1) AddWarningMsg(sprintf(_("doc %s was created for file %s") , $doc->id, $file));
        }
        
        switch ($this->getRawValue(MyAttributes::ifc_mode)) {
            case "FTP":
                @unlink($fpath);
                break;
        }
        
        return $err;
    }
    
    final protected function iPostTransfert($file, $fpath, &$doc)
    {
        $infos = $this->iGetFileTransf($file);
        if ($infos['dir'] > 0) $this->moveCxFile($file, $doc);
        if ($infos['sup'] == 1) $this->removeCxFile($file);
    }
    
    final protected function iPreTransfert($file = '')
    {
        $err = "";
        if (!$this->isValidCxFile($file)) {
            $err = sprintf(_("(ifc) no such file %s") , $file);
        }
        return $err;
    }
    
    final public function getCxFiles()
    {
        return $this->getMultipleRawValues(MyAttributes::ifc_c_name);
    }
    
    final public function getCxFileContent($file = '')
    {
        if (!$this->isValidCxFile($file)) {
            return sprintf(_("(ifc) no such file %s") , $file);
        }
        $uri = $this->getRawValue(MyAttributes::ifc_uris);
        if ($this->getRawValue(MyAttributes::ifc_mode) === 'FTP') {
            $uri = $this->uriWithPassword($uri, $this->getRawValue(MyAttributes::ifc_password));
        }
        $c = file_get_contents($uri . "/" . $file);
        if (!$c) $c = sprintf(_("(ifc) can't retrieve content for file %s") , $this->getRawValue(MyAttributes::ifc_uris) . "/" . $file);
        return $c;
    }
    
    final public function copyCxFile($file = '', $path = '')
    {
        if (!$this->isValidCxFile($file)) {
            return sprintf(_("(ifc) no such file %s") , $file);
        }
        switch ($this->getRawValue(MyAttributes::ifc_mode)) {
            case "FTP":
                $lpath = $this->fcFtpLocalFile($file);
                break;

            default:
                $lpath = $this->getRawValue(MyAttributes::ifc_path) . "/" . $file;
        }
        if (!is_dir($path)) {
            return sprintf(_("(ifc) can't access directory %s") , $path);
        }
        if (!is_writeable($path)) {
            return sprintf(_("(ifc) can't write into directory %s") , $path);
        }
        
        $err = copy($lpath, $path);
        if (!$err) {
            return sprintf(_("(ifc) can't copy file %s to %s") , $file, $path);
        }
        return "";
    }
    
    final protected function isValidCxFile($file = '')
    {
        $ft = $this->getMultipleRawValues(MyAttributes::ifc_c_name);
        if (!in_array($file, $ft)) return false;
        else return true;
    }
    
    final protected function iGetFileTransf($file)
    {
        static $minfo = array();
        
        $fn = $this->getMultipleRawValues(MyAttributes::ifc_c_name);
        $fm = $this->getMultipleRawValues(MyAttributes::ifc_c_match);
        $p = array_search($file, $fn);
        $m = $fm[$p];
        if (!isset($minfo[$m])) {
            $trn = $this->getMultipleRawValues(MyAttributes::ifc_sl_name);
            $trf = $this->getMultipleRawValues(MyAttributes::ifc_sl_familyid);
            $tra = $this->getMultipleRawValues(MyAttributes::ifc_sl_attrid);
            $trd = $this->getMultipleRawValues(MyAttributes::ifc_sl_dirid);
            $trs = $this->getMultipleRawValues(MyAttributes::ifc_sl_suppr);
            $pr = array_search($m, $trn);
            $minfo[$m] = array(
                "match" => $m,
                "fam" => $trf[$pr],
                "attr" => $tra[$pr],
                "dir" => $trd[$pr],
                "sup" => $trs[$pr]
            );
        }
        return $minfo[$m];
    }
    
    final protected function setCxFileStatus($file = '', $st = "U")
    {
        if (!$this->isValidCxFile($file)) return;
        $fn = $this->getMultipleRawValues(MyAttributes::ifc_c_name);
        $fs = $this->getMultipleRawValues(MyAttributes::ifc_c_state);
        $p = array_search($file, $fn);
        $fs[$p] = $st;
        $this->setValue(MyAttributes::ifc_c_state, $fs);
        $this->modify(true, array(
            MyAttributes::ifc_c_state
        ) , true);
    }
    
    final public function getCxFileStatus($file = '')
    {
        if (!$this->isValidCxFile($file)) return false;
        $fn = $this->getMultipleRawValues(MyAttributes::ifc_c_name);
        $fs = $this->getMultipleRawValues(MyAttributes::ifc_c_state);
        $p = array_search($file, $fn);
        return $fs[$p];
    }
    
    final public function moveCxFile($file, &$doc)
    {
        $err = "";
        $infos = $this->iGetFileTransf($file);
        if ($infos["dir"]) {
            /**
             * @var \Dcp\Family\Dir $dir
             */
            $dir = new_Doc($this->dbaccess, $infos["dir"]);
            if ($dir->isAlive() && $dir->doctype == 'D') {
                $dir->insertDocument($doc->initid);
            }
        }
        return $err;
    }
    
    final public function removeCxFile($file = '')
    {
        if (!$this->isValidCxFile($file)) {
            return false;
        }
        switch ($this->getRawValue(MyAttributes::ifc_mode)) {
            case "FTP":
                $ret = $this->fcRemoveFtpFile($file);
                break;

            default:
                $fpath = $this->getRawValue(MyAttributes::ifc_path) . "/" . $file;
                $ret = @unlink($fpath);
        }
        if (!$ret) {
            AddWarningMsg(sprintf(_("(ifc) can't unlink file %s") , $file));
            $this->setCxFileStatus($file, "D");
        } else {
            $ofp = $this->getMultipleRawValues(MyAttributes::ifc_c_match);
            $ofn = $this->getMultipleRawValues(MyAttributes::ifc_c_name);
            $ofs = $this->getMultipleRawValues(MyAttributes::ifc_c_size);
            $ofm = $this->getMultipleRawValues(MyAttributes::ifc_c_mtime);
            $ofx = $this->getMultipleRawValues(MyAttributes::ifc_c_state);
            
            $p = array_search($file, $ofn);
            if ($p !== false) {
                
                $this->clearValue(MyAttributes::ifc_c_match);
                $this->clearValue(MyAttributes::ifc_c_name);
                $this->clearValue(MyAttributes::ifc_c_size);
                $this->clearValue(MyAttributes::ifc_c_mtime);
                $this->clearValue(MyAttributes::ifc_c_state);
                
                unset($ofp[$p]);
                unset($ofn[$p]);
                unset($ofs[$p]);
                unset($ofm[$p]);
                unset($ofx[$p]);
                
                $this->setValue(MyAttributes::ifc_c_match, $ofp);
                $this->setValue(MyAttributes::ifc_c_name, $ofn);
                $this->setValue(MyAttributes::ifc_c_size, $ofs);
                $this->setValue(MyAttributes::ifc_c_mtime, $ofm);
                $this->setValue(MyAttributes::ifc_c_state, $ofx);
                
                $this->modify(true, array(
                    MyAttributes::ifc_c_match,
                    MyAttributes::ifc_c_name,
                    MyAttributes::ifc_c_size,
                    MyAttributes::ifc_c_mtime,
                    MyAttributes::ifc_c_state
                ) , true);
            }
        }
        return $ret;
    }
    
    final protected function checkInput($input)
    {
        include_once "EXTERNALS/fileconnector.php";
        
        $error_message = "";
        $proto = $this->getRawValue(MyAttributes::ifc_mode);
        $lp = getAvailableProtocols();
        $needed = explode("|", strtolower($lp[$proto]["needed"]));
        
        if (in_array(strtolower($input) , $needed) && ($this->getRawValue($input) == "")) {
            $oa = $this->getAttribute($input);
            $error_message.= sprintf(_("(ifc) %s required") , $oa->getLabel());
        }
        return array(
            "err" => $error_message
        );
    }
    
    final protected function checkFamily()
    {
        
        $error_message = "";
        $proposal = array();
        
        $fam = $this->getMultipleRawValues(MyAttributes::ifc_sl_familyid);
        foreach ($fam as $v) {
            if (trim($v) == '') {
                $error_message = sprintf(_("valid families are needed"));
            } else {
                $fd = new_Doc($this->dbaccess, $v);
                if (!$fd->isAlive() || $fd->doctype != 'C') {
                    $error_message = sprintf(_("valid families are needed"));
                } else {
                    if ($fd->GetFirstFileAttributes() === false) {
                        $error_message = sprintf(_("family %s have no file or image attribute") , $fd->getTitle());
                    }
                }
            }
        }
        
        if ($error_message != "") {
            $proposal[] = _("use [...] to select target familie");
        }
        
        return array(
            "err" => $error_message,
            "sug" => $proposal
        );
    }
    
    final protected function showSourceMenu()
    {
        if ($this->getRawValue(MyAttributes::ifc_opened) == 1) {
            return MENU_ACTIVE;
        }
        return MENU_INACTIVE;
    }
    
    final protected function getContext()
    {
        return;
    }
    
    final protected function designProcessus()
    {
        
        $exist = false;
        if ($this->getRawValue(MyAttributes::ifc_p_procid) > 0) {
            $dp = new_Doc($this->dbaccess, $this->getRawValue(MyAttributes::ifc_p_procid) , true);
            if ($dp->isAlive()) {
                $exist = true;
            }
        }
        
        if (!$exist) {
            $dp = createDoc($this->dbaccess, \Dcp\Family\Exec::familyName);
            $dp->setValue(Attributes\Exec::exec_title, sprintf("[FileConnector] %s", $this->getTitle()));
            $dp->setValue(Attributes\Exec::exec_application, "FDL");
            $dp->setValue(Attributes\Exec::exec_action, "FDL_METHOD");
            $dp->setValue(Attributes\Exec::exec_idvar, array(
                "id",
                "method"
            ));
            $dp->setValue(Attributes\Exec::exec_valuevar, array(
                $this->id,
                "transfertNewCxFiles()"
            ));
            $err = $dp->add();
            if ($err == "") {
                $this->setValue(MyAttributes::ifc_p_procid, $dp->id);
                $this->setValue(MyAttributes::ifc_p_proc, $dp->getRawValue(Attributes\Exec::exec_title));
                $this->modify(true, array(
                    MyAttributes::ifc_p_procid,
                    MyAttributes::ifc_p_proc
                ) , true);
            } else {
                return $err;
            }
        }
        
        if ($this->getRawValue("ifc_p_run") == 1) {
            /** @noinspection PhpUndefinedVariableInspection */
            $dp->setValue(Attributes\Exec::exec_handnextdate, $this->getRawValue(MyAttributes::ifc_p_cdateexec));
            $dp->setValue(Attributes\Exec::exec_periodday, $this->getRawValue(MyAttributes::ifc_p_cperday));
            $dp->setValue(Attributes\Exec::exec_periodhour, $this->getRawValue(MyAttributes::ifc_p_cperhour));
            $dp->setValue(Attributes\Exec::exec_periodmin, $this->getRawValue(MyAttributes::ifc_p_cpermin));
        } else {
            /** @noinspection PhpUndefinedVariableInspection */
            $dp->setValue(Attributes\Exec::exec_handnextdate, " ");
            $dp->setValue(Attributes\Exec::exec_periodday, 0);
            $dp->setValue(Attributes\Exec::exec_periodhour, 0);
            $dp->setValue(Attributes\Exec::exec_periodmin, 0);
        }
        
        $err = $dp->modify();
        if ($err != '') {
            return $err;
        }
        $err = $dp->postStore();
        if ($err != '') {
            return $err;
        }
        $err = $dp->refresh();
        return $err;
    }
    // FTP management ------------------------------------------------------------------------------------
    final protected function fcFtpConnexion()
    {
        $server = $this->getRawValue(MyAttributes::ifc_host) . ($this->getRawValue(MyAttributes::ifc_port) == "" ? "" : ":" . $this->getRawValue(MyAttributes::ifc_port));
        $ftpConn = ftp_connect($server);
        error_log("(FTP) connexion sur $server");
        return $ftpConn;
    }
    
    final protected function fcFtpLogin($conn)
    {
        $user = ($this->getRawValue(MyAttributes::ifc_login) == "" ? "anonymous" : $this->getRawValue(MyAttributes::ifc_login));
        $passwd = ($this->getRawValue(MyAttributes::ifc_password) == "" ? "none@nodomain.org" : $this->getRawValue(MyAttributes::ifc_password));
        error_log("(FTP) login $user");
        return ftp_login($conn, $user, $passwd);
    }
    
    final protected function fcFtpFileInfo($file)
    {
        $conn = $this->fcFtpConnexion();
        $this->fcFtpLogin($conn);
        $file = $this->getRawValue(MyAttributes::ifc_path) . (substr($this->getRawValue(MyAttributes::ifc_path) , -1) == '/' ? '' : '/') . $file;
        error_log("(FTP) file info $file");
        $res["size"] = ftp_size($conn, $file);
        $res["date"] = ftp_mdtm($conn, $file);
        ftp_close($conn);
        return $res;
    }
    
    final protected function fcFtpLocalFile($file)
    {
        $conn = $this->fcFtpConnexion();
        $this->fcFtpLogin($conn);
        ftp_pasv($conn, $this->getRawValue(MyAttributes::ifc_passif_mode) === "TRUE");
        $fpath = $this->getRawValue(MyAttributes::ifc_path) . (substr($this->getRawValue(MyAttributes::ifc_path) , -1) == '/' ? '' : '/') . $file;
        $tmpDir = getParam("CORE_TMPDIR", "/tmp/");
        $filename = "$tmpDir/tmp_ftp_import" . $this->id . "-" . $file;
        ftp_get($conn, $filename, $fpath, FTP_BINARY);
        error_log("(FTP) get $fpath (local $filename)");
        ftp_close($conn);
        return $filename;
    }
    
    final protected function fcRemoveFtpFile($file)
    {
        $conn = $this->fcFtpConnexion();
        $this->fcFtpLogin($conn);
        $fpath = $this->getRawValue(MyAttributes::ifc_path) . (substr($this->getRawValue(MyAttributes::ifc_path) , -1) == '/' ? '' : '/') . $file;
        $st = ftp_delete($conn, $fpath);
        error_log("(FTP) delete $fpath (status=" . ($st ? "OK" : "KO"));
        ftp_close($conn);
        return $st;
    }
}

