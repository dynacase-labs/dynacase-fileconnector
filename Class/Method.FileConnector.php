<?php
/*
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
 * @package FILECONNECTOR
*/
/**
 * File Connector
 */
/**
 * @begin-method-ignore
 * this part will be deleted when construct document class until end-method-ignore
 */
Class _FILECONNECTOR extends Doc
{
    /*
     * @end-method-ignore
    */
    
    function postStore()
    {
        // compute URI
        switch ($this->getRawValue("ifc_mode")) {
            case "FTP":
                $uri = strtolower($this->getRawValue("ifc_mode")) . "://";
                $uri.= ($this->getRawValue("ifc_login") == "" ? "anonymous" : $this->getRawValue("ifc_login"));
                $uri.= ":*********";
                $uri.= "@";
                $uri.= $this->getRawValue("ifc_host");
                if ($this->getRawValue("ifc_port") != "") {
                    $uri.= ":" . $this->getRawValue("ifc_port");
                }
                $uri.= $this->getRawValue("ifc_path");
                break;

            case "FS":
                $uri = $this->getRawValue("ifc_path");
                break;

            default:
                $uri = "-unknown protocol-";
        }
        $this->setValue("ifc_uris", $uri);
        $valid = 0;
        $dt = opendir($uri);
        if (!$dt) {
            AddWarningMsg(sprintf(_("(ifc) can't access file from %s") , $uri));
        } else {
            AddWarningMsg(sprintf(_("(ifc) access to %s is checked") , $uri));
            $valid = 1;
            closedir($dt);
        }
        
        $this->setValue("ifc_opened", $valid);
        $this->modify(true, array(
            "ifc_uris",
            "ifc_opened"
        ) , true);
        
        $err = $this->designProcessus();
        AddWarningMsg($err);
        
        return;
    }
    
    final public function scanSource()
    {
        global $action;
        $dir = $this->getRawValue("ifc_uris");
        $proto = $this->getRawValue("ifc_mode");
        $dt = opendir($dir);
        if (!$dt) {
            $action->log->error("[" . $this->title . "]: can't open dir " . $this->getRawValue("ifc_uris"));
            return null;
        }
        $nfn = $nfs = $nfm = $nfx = array();
        clearstatcache();
        $root = $this->getRawValue("ifc_uris");
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
        $patterns_n = $this->getMultipleRawValues('ifc_sl_name');
        $patterns_v = $this->getMultipleRawValues('ifc_sl_pattern');
        
        $ofp = $this->getMultipleRawValues("ifc_c_match");
        $ofn = $this->getMultipleRawValues("ifc_c_name");
        $ofs = $this->getMultipleRawValues("ifc_c_size");
        $ofm = $this->getMultipleRawValues("ifc_c_mtime");
        $ofx = $this->getMultipleRawValues("ifc_c_state");
        
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
        
        $this->clearValue('ifc_c_match');
        $this->clearValue('ifc_c_name');
        $this->clearValue('ifc_c_size');
        $this->clearValue('ifc_c_mtime');
        $this->clearValue('ifc_c_state');
        
        $this->setValue('ifc_c_match', $cfp);
        $this->setValue('ifc_c_name', $cfn);
        $this->setValue('ifc_c_size', $cfs);
        $this->setValue('ifc_c_mtime', $cfm);
        $this->setValue('ifc_c_state', $cfx);
        
        $this->setValue("ifc_lastscan", $this->getTimeDate(0, true));
        
        return $this->modify(true, array(
            "ifc_lastscan",
            'ifc_c_match',
            'ifc_c_name',
            'ifc_c_size',
            'ifc_c_mtime',
            'ifc_c_state'
        ) , true);
    }
    /**
     * @apiExpose
     * reset (clean) list of file to be processes
     * @return bool
     */
    final public function resetScan()
    {
        $this->clearValue('ifc_c_match');
        $this->clearValue('ifc_c_name');
        $this->clearValue('ifc_c_size');
        $this->clearValue('ifc_c_mtime');
        $this->clearValue('ifc_c_state');
        $err = $this->modify(true, array(
            'ifc_c_match',
            'ifc_c_name',
            'ifc_c_size',
            'ifc_c_mtime',
            'ifc_c_state'
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
        $st = $this->getMultipleRawValues("ifc_c_state");
        foreach ($st as $v) {
            if ($v == 'N') return $err;
        }
        return $err;
    }
    
    final public function getNewCxFiles()
    {
        $ret = array();
        $this->scanSource();
        $st = $this->getMultipleRawValues("ifc_c_state");
        $fn = $this->getMultipleRawValues("ifc_c_name");
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
        
        switch ($this->getRawValue("ifc_mode")) {
            case "FTP":
                $fpath = $this->fcFtpLocalFile($file);
                break;
            default:
                $fpath = $this->getRawValue('ifc_path') . "/" . $file;
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
                }
                else {
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
                                $doc->addHistoryEntry(sprintf(_("Creation from file connector %s") , $this->getTitle()));
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
        
        switch ($this->getRawValue("ifc_mode")) {
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
        return $this->getMultipleRawValues("ifc_c_name");
    }
    
    final public function getCxFileContent($file = '')
    {
        if (!$this->isValidCxFile($file)) {
            return sprintf(_("(ifc) no such file %s") , $file);
        }
        $c = file_get_contents($this->getRawValue("ifc_uris") . "/" . $file);
        if (!$c) $c = sprintf(_("(ifc) can't retrieve content for file %s") , $this->getRawValue("ifc_uris") . "/" . $file);
        return $c;
    }
    
    final public function copyCxFile($file = '', $path = '')
    {
        if (!$this->isValidCxFile($file)) {
            return sprintf(_("(ifc) no such file %s") , $file);
        }
        switch ($this->getRawValue("ifc_mode")) {
            case "FTP":
                $lpath = $this->fcFtpLocalFile($file);
                break;

            default:
                $lpath = $this->getRawValue('ifc_path') . "/" . $file;
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
        $ft = $this->getMultipleRawValues("ifc_c_name");
        if (!in_array($file, $ft)) return false;
        else return true;
    }
    
    final protected function iGetFileTransf($file)
    {
        static $minfo = array();
        
        $fn = $this->getMultipleRawValues("ifc_c_name");
        $fm = $this->getMultipleRawValues("ifc_c_match");
        $p = array_search($file, $fn);
        $m = $fm[$p];
        if (!isset($minfo[$m])) {
            $trn = $this->getMultipleRawValues("ifc_sl_name");
            $trf = $this->getMultipleRawValues("ifc_sl_familyid");
            $tra = $this->getMultipleRawValues("ifc_sl_attrid");
            $trd = $this->getMultipleRawValues("ifc_sl_dirid");
            $trs = $this->getMultipleRawValues("ifc_sl_suppr");
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
        $fn = $this->getMultipleRawValues('ifc_c_name');
        $fs = $this->getMultipleRawValues('ifc_c_state');
        $p = array_search($file, $fn);
        $fs[$p] = $st;
        $this->setValue('ifc_c_state', $fs);
        $this->modify(true, array(
            'ifc_c_state'
        ) , true);
    }
    
    final public function getCxFileStatus($file = '')
    {
        if (!$this->isValidCxFile($file)) return false;
        $fn = $this->getMultipleRawValues('ifc_c_name');
        $fs = $this->getMultipleRawValues('ifc_c_state');
        $p = array_search($file, $fn);
        return $fs[$p];
    }
    
    final public function moveCxFile($file, &$doc)
    {
        $err = "";
        $infos = $this->iGetFileTransf($file);
        if ($infos["dir"]) {
            /**
             * @var Dir $dir
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
        switch ($this->getRawValue("ifc_mode")) {
            case "FTP":
                $ret = $this->fcRemoveFtpFile($file);
                break;

            default:
                $fpath = $this->getRawValue('ifc_path') . "/" . $file;
                $ret = @unlink($fpath);
        }
        if (!$ret) {
            AddWarningMsg(sprintf(_("(ifc) can't unlink file %s") , $file));
            $this->setCxFileStatus($file, "D");
        } else {
            $ofp = $this->getMultipleRawValues("ifc_c_match");
            $ofn = $this->getMultipleRawValues("ifc_c_name");
            $ofs = $this->getMultipleRawValues("ifc_c_size");
            $ofm = $this->getMultipleRawValues("ifc_c_mtime");
            $ofx = $this->getMultipleRawValues("ifc_c_state");
            
            $p = array_search($file, $ofn);
            if ($p !== false) {
                
                $this->clearValue('ifc_c_match');
                $this->clearValue('ifc_c_name');
                $this->clearValue('ifc_c_size');
                $this->clearValue('ifc_c_mtime');
                $this->clearValue('ifc_c_state');
                
                unset($ofp[$p]);
                unset($ofn[$p]);
                unset($ofs[$p]);
                unset($ofm[$p]);
                unset($ofx[$p]);
                
                $this->setValue('ifc_c_match', $ofp);
                $this->setValue('ifc_c_name', $ofn);
                $this->setValue('ifc_c_size', $ofs);
                $this->setValue('ifc_c_mtime', $ofm);
                $this->setValue('ifc_c_state', $ofx);
                
                $this->modify(true, array(
                    'ifc_c_match',
                    'ifc_c_name',
                    'ifc_c_size',
                    'ifc_c_mtime',
                    'ifc_c_state'
                ) , true);
            }
        }
        return $ret;
    }
    
    final protected function checkInput($input)
    {
        include_once "EXTERNALS/fileconnector.php";
        
        $error_message = "";
        $proto = $this->getRawValue("ifc_mode");
        $lp = getAvailableProtocols();
        $needed = explode("|", strtolower($lp[$proto]["needed"]));
        
        if (in_array(strtolower($input) , $needed) && ($this->getRawValue($input) == "")) {
            $oa = $this->getAttribute($input);
            $error_message.= sprintf(_("%s required") , $oa->getLabel());
        }
        return array(
            "err" => $error_message
        );
    }
    
    final protected function checkFamily()
    {
        
        $error_message = "";
        $proposal = array();
        
        $fam = $this->getMultipleRawValues("ifc_sl_familyid");
        foreach ($fam as $v) {
            if (trim($v) == '') {
                $error_message = sprintf(_("valid families are needed"));
            }
            else {
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
        if ($this->getRawValue("ifc_opened") == 1) {
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
        if ($this->getRawValue("ifc_p_procid") > 0) {
            $dp = new_Doc($this->dbaccess, $this->getRawValue("ifc_p_procid"), true);
            if ($dp->isAlive()) {
                $exist = true;
            }
        }
        
        if (!$exist) {
            $dp = createDoc($this->dbaccess, "EXEC");
            $dp->setValue("exec_title", sprintf("[FileConnector] %s", $this->getTitle()));
            $dp->setValue("exec_application", "FDL");
            $dp->setValue("exec_action", "FDL_METHOD");
            $dp->setValue("exec_idvar", array(
                "id",
                "method"
            ));
            $dp->setValue("exec_valuevar", array(
                $this->id,
                "transfertNewCxFiles()"
            ));
            $err = $dp->add();
            if ($err == "") {
                $this->setValue("ifc_p_procid", $dp->id);
                $this->setValue("ifc_p_proc", $dp->getRawValue("exec_title"));
                $this->modify(true, array(
                    "ifc_p_procid",
                    "ifc_p_proc"
                ) , true);
            } else {
                return $err;
            }
        }
        
        if ($this->getRawValue("ifc_p_run") == 1) {
            /** @noinspection PhpUndefinedVariableInspection */
            $dp->setValue("exec_handnextdate", $this->getRawValue("ifc_p_cdateexec"));
            $dp->setValue("exec_periodday", $this->getRawValue("ifc_p_cperday"));
            $dp->setValue("exec_periodhour", $this->getRawValue("ifc_p_cperhour"));
            $dp->setValue("exec_periodmin", $this->getRawValue("ifc_p_cpermin"));
        } else {
            /** @noinspection PhpUndefinedVariableInspection */
            $dp->setValue("exec_handnextdate", " ");
            $dp->setValue("exec_periodday", 0);
            $dp->setValue("exec_periodhour", 0);
            $dp->setValue("exec_periodmin", 0);
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
        $server = $this->getRawValue("ifc_host") . ($this->getRawValue("ifc_port") == "" ? "" : ":" . $this->getRawValue("ifc_port"));
        $ftpConn = ftp_connect($server);
        error_log("(FTP) connexion sur $server");
        return $ftpConn;
    }
    
    final protected function fcFtpLogin($conn)
    {
        $user = ($this->getRawValue("ifc_login") == "" ? "anonymous" : $this->getRawValue("ifc_login"));
        $passwd = ($this->getRawValue("ifc_password") == "" ? "none@nodomain.org" : $this->getRawValue("ifc_password"));
        error_log("(FTP) login $user/$passwd");
        return ftp_login($conn, $user, $passwd);
    }
    
    final protected function fcFtpFileInfo($file)
    {
        $conn = $this->fcFtpConnexion();
        $this->fcFtpLogin($conn);
        $file = $this->getRawValue("ifc_path") . (substr($this->getRawValue("ifc_path") , -1) == '/' ? '' : '/') . $file;
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
        ftp_pasv($conn, $this->getRawValue("IFC_PASSIF_MODE") === "TRUE");
        $fpath = $this->getRawValue("ifc_path") . (substr($this->getRawValue("ifc_path") , -1) == '/' ? '' : '/') . $file;
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
        $fpath = $this->getRawValue("ifc_path") . (substr($this->getRawValue("ifc_path") , -1) == '/' ? '' : '/') . $file;
        $st = ftp_delete($conn, $fpath);
        error_log("(FTP) delete $fpath (status=" . ($st ? "OK" : "KO"));
        ftp_close($conn);
        return $st;
    }
    /**
     * @begin-method-ignore
     * this part will be deleted when construct document class until end-method-ignore
     */
}
/*
 * @end-method-ignore
*/
