<?php
namespace Dcp\Family {
	/** File Connector  */
	class Fileconnector extends \Dcp\Fileconnector\Fileconnector { const familyName="FILECONNECTOR";}
}
namespace Dcp\AttributeIdentifiers {
	/** File Connector  */
	class Fileconnector {
		/** [frame] description */
		const ifc_general='ifc_general';
		/** [text] name */
		const ifc_name='ifc_name';
		/** [longtext] description */
		const ifc_descr='ifc_descr';
		/** [frame] access */
		const ifc_access='ifc_access';
		/** [enum] type */
		const ifc_mode='ifc_mode';
		/** [text] host */
		const ifc_host='ifc_host';
		/** [int] port */
		const ifc_port='ifc_port';
		/** [enum] mode passive */
		const ifc_passif_mode='ifc_passif_mode';
		/** [text] login */
		const ifc_login='ifc_login';
		/** [password] password */
		const ifc_password='ifc_password';
		/** [text] path */
		const ifc_path='ifc_path';
		/** [text] URI computed (secure) */
		const ifc_uris='ifc_uris';
		/** [enum] is accessible */
		const ifc_opened='ifc_opened';
		/** [timestamp("%A %d %B %Y %X")] last scan */
		const ifc_lastscan='ifc_lastscan';
		/** [frame] pattern matching */
		const ifc_spec='ifc_spec';
		/** [array] rules */
		const ifc_list='ifc_list';
		/** [text] name */
		const ifc_sl_name='ifc_sl_name';
		/** [text] pattern */
		const ifc_sl_pattern='ifc_sl_pattern';
		/** [docid] family ID */
		const ifc_sl_familyid='ifc_sl_familyid';
		/** [text] family */
		const ifc_sl_family='ifc_sl_family';
		/** [text] attr ID */
		const ifc_sl_attrid='ifc_sl_attrid';
		/** [text] attribute */
		const ifc_sl_attr='ifc_sl_attr';
		/** [docid] dir ID */
		const ifc_sl_dirid='ifc_sl_dirid';
		/** [text] move to */
		const ifc_sl_dir='ifc_sl_dir';
		/** [enum] suppress after import */
		const ifc_sl_suppr='ifc_sl_suppr';
		/** [frame] programmation */
		const ifc_program='ifc_program';
		/** [enum] actif */
		const ifc_p_run='ifc_p_run';
		/** [docid] proc ID */
		const ifc_p_procid='ifc_p_procid';
		/** [text] process */
		const ifc_p_proc='ifc_p_proc';
		/** [timestamp] exec date */
		const ifc_p_cdateexec='ifc_p_cdateexec';
		/** [enum] day period */
		const ifc_p_cperday='ifc_p_cperday';
		/** [enum] hour period */
		const ifc_p_cperhour='ifc_p_cperhour';
		/** [enum] min period */
		const ifc_p_cpermin='ifc_p_cpermin';
		/** [frame] content */
		const ifc_content='ifc_content';
		/** [array] files */
		const ifc_c_list='ifc_c_list';
		/** [text] match */
		const ifc_c_match='ifc_c_match';
		/** [text] name */
		const ifc_c_name='ifc_c_name';
		/** [double] size */
		const ifc_c_size='ifc_c_size';
		/** [timestamp] modification time */
		const ifc_c_mtime='ifc_c_mtime';
		/** [enum] state */
		const ifc_c_state='ifc_c_state';
		/** [menu] import new */
		const ifc_menu_transfert='ifc_menu_transfert';
		/** [menu] check new */
		const ifc_menu_scandir='ifc_menu_scandir';
		/** [menu] clear list */
		const ifc_menu_reset='ifc_menu_reset';
	}
}
