<?php
/*
	FusionPBX
	Version: MPL 1.1

	The contents of this file are subject to the Mozilla Public License Version
	1.1 (the "License"); you may not use this file except in compliance with
	the License. You may obtain a copy of the License at
	http://www.mozilla.org/MPL/

	Software distributed under the License is distributed on an "AS IS" basis,
	WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
	for the specific language governing rights and limitations under the
	License.

	The Original Code is FusionPBX

	The Initial Developer of the Original Code is
	Mark J Crane <markjcrane@fusionpbx.com>
	Portions created by the Initial Developer are Copyright (C) 2008-2012
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/
include "root.php";
require_once "resources/require.php";
require_once "resources/check_auth.php";

if (permission_exists('operator_panel_view')) {
	//access granted
}
else {
	echo "access denied";
	exit;
}

//add multi-lingual support
	include "app_languages.php";
	foreach($text as $key => $value) {
		$text[$key] = $value[$_SESSION['domain']['language']['code']];
	}

//set user status
	if (isset($_REQUEST['status']) && $_REQUEST['status'] != '') {
		$user_status = check_str($_REQUEST['status']);
	//sql update
		$sql  = "update v_users set ";
		$sql .= "user_status = '".$user_status."' ";
		$sql .= "where domain_uuid = '".$_SESSION['domain_uuid']."' ";
		$sql .= "and user_uuid = '".$_SESSION['user']['user_uuid']."' ";
		if (permission_exists("user_account_setting_edit")) {
			$count = $db->exec(check_sql($sql));
		}

	//if call center app is installed then update the user_status
		if (is_dir($_SERVER["DOCUMENT_ROOT"].PROJECT_PATH.'/app/call_center')) {
			//update the user_status
				$fp = event_socket_create($_SESSION['event_socket_ip_address'], $_SESSION['event_socket_port'], $_SESSION['event_socket_password']);
				$switch_cmd .= "callcenter_config agent set status ".$_SESSION['user']['username']."@".$_SESSION['domain_name']." '".$user_status."'";
				$switch_result = event_socket_request($fp, 'api '.$switch_cmd);

			//update the user state
				$cmd = "api callcenter_config agent set state ".$_SESSION['user']['username']."@".$_SESSION['domain_name']." Waiting";
				$response = event_socket_request($fp, $cmd);
		}

		exit;
	}

require_once "resources/header.php";
?>

<!-- virtual_drag function holding elements -->
<input type='hidden' class='formfld' id='vd_call_id' value=''>
<input type='hidden' class='formfld' id='vd_ext_from' value=''>
<input type='hidden' class='formfld' id='vd_ext_to' value=''>

<script type="text/javascript">
//ajax refresh
	var refresh = 1950;
	var source_url = 'index_inc.php?' <?php if (isset($_GET['debug'])) { echo " + '&debug'"; } ?>;
	var interval_timer_id;

	function loadXmlHttp(url, id) {
		var f = this;
		f.xmlHttp = null;
		/*@cc_on @*/ // used here and below, limits try/catch to those IE browsers that both benefit from and support it
		/*@if(@_jscript_version >= 5) // prevents errors in old browsers that barf on try/catch & problems in IE if Active X disabled
		try {f.ie = window.ActiveXObject}catch(e){f.ie = false;}
		@end @*/
		if (window.XMLHttpRequest&&!f.ie||/^http/.test(window.location.href))
			f.xmlHttp = new XMLHttpRequest(); // Firefox, Opera 8.0+, Safari, others, IE 7+ when live - this is the standard method
		else if (/(object)|(function)/.test(typeof createRequest))
			f.xmlHttp = createRequest(); // ICEBrowser, perhaps others
		else {
			f.xmlHttp = null;
			 // Internet Explorer 5 to 6, includes IE 7+ when local //
			/*@cc_on @*/
			/*@if(@_jscript_version >= 5)
			try{f.xmlHttp=new ActiveXObject("Msxml2.XMLHTTP");}
			catch (e){try{f.xmlHttp=new ActiveXObject("Microsoft.XMLHTTP");}catch(e){f.xmlHttp=null;}}
			@end @*/
		}
		if(f.xmlHttp != null){
			f.el = document.getElementById(id);
			f.xmlHttp.open("GET",url,true);
			f.xmlHttp.onreadystatechange = function(){f.stateChanged();};
			f.xmlHttp.send(null);
		}
	}

	loadXmlHttp.prototype.stateChanged=function () {
	if (this.xmlHttp.readyState == 4 && (this.xmlHttp.status == 200 || !/^http/.test(window.location.href)))
		//this.el.innerHTML = this.xmlHttp.responseText;
		document.getElementById('ajax_reponse').innerHTML = this.xmlHttp.responseText;
	}

	var requestTime = function() {
		var url = source_url;
		url += '&vd_ext_from=' + document.getElementById('vd_ext_from').value;
		url += '&vd_ext_to=' + document.getElementById('vd_ext_to').value;
		url += '&group=' + ((document.getElementById('group')) ? document.getElementById('group').value : '');
		<?php
		if (isset($_GET['debug'])) {
			echo "url += '&debug';";
		}
		?>
		new loadXmlHttp(url, 'ajax_reponse');
		refresh_start();
	}

	if (window.addEventListener) {
		window.addEventListener('load', requestTime, false);
	}
	else if (window.attachEvent) {
		window.attachEvent('onload', requestTime);
	}


//drag/drop functionality
	var ie_workaround = false;

	function drag(ev, from_ext) {
		refresh_stop();
		try {
			ev.dataTransfer.setData("Call", ev.target.id);
			ev.dataTransfer.setData("From", from_ext);
			virtual_drag_reset();
		}
		catch (err) {
			// likely internet explorer being used, do workaround
			virtual_drag(ev.target.id, from_ext);
			ie_workaround = true;
		}
	}

	function allowDrop(ev, target_id) {
		ev.preventDefault();
	}

	function discardDrop(ev, target_id) {
		ev.preventDefault();
	}

	function drop(ev, to_ext) {
		ev.preventDefault();
		if (ie_workaround) { // potentially set on drag() function above
			var call_id = document.getElementById('vd_call_id').value;
			var from_ext = document.getElementById('vd_ext_from').value;
			virtual_drag_reset();
		}
		else {
			var call_id = ev.dataTransfer.getData("Call");
			var from_ext = ev.dataTransfer.getData("From");
		}
		var to_ext = to_ext;
		var cmd;

		if (call_id != '') {
			cmd = get_transfer_cmd(call_id, to_ext); //transfer a call
		}
		else {
			if (from_ext != to_ext) { // prevent user from dragging extention onto self
				cmd = get_originate_cmd(from_ext+'@<?=$_SESSION["domain_name"]?>', to_ext); //make a call
			}
		}

		if (cmd != '') { send_cmd('exec.php?cmd='+escape(cmd)); }

		refresh_start();
	}

//refresh controls
	function refresh_stop() {
		clearInterval(interval_timer_id);
	}

	function refresh_start() {
		interval_timer_id = setInterval( function() {
			url = source_url;
			url += '&vd_ext_from=' + document.getElementById('vd_ext_from').value;
			url += '&vd_ext_to=' + document.getElementById('vd_ext_to').value;
			url += '&group=' + ((document.getElementById('group')) ? document.getElementById('group').value : '');
			<?php
			if (isset($_GET['debug'])) {
				echo "url += '&debug';";
			}
			?>
			new loadXmlHttp(url, 'ajax_reponse');
		}, refresh);
	}

//call destination
	function call_destination(from_ext, destination) {
		if (destination != '') {
			cmd = get_originate_cmd(from_ext+'@<?=$_SESSION["domain_name"]?>', destination); //make a call
		}
		if (cmd != '') {
			send_cmd('exec.php?cmd='+escape(cmd));
		}
		refresh_start();
	}

//kill call
	function kill_call(call_id) {
		if (call_id != '') {
			cmd = 'uuid_kill ' + call_id;
			send_cmd('exec.php?cmd='+escape(cmd));
		}
	}

//eavesdrop call
	function eavesdrop_call(ext, chan_uuid) {
		if (ext != '' && chan_uuid != '') {
			cmd = get_eavesdrop_cmd(ext, chan_uuid);
			if (cmd != '') {
				send_cmd('exec.php?cmd='+escape(cmd));
			}
		}
	}

//record call
	function record_call(chan_uuid) {
		if (chan_uuid != '') {
			cmd = get_record_cmd(chan_uuid);
			if (cmd != '') {
				send_cmd('exec.php?cmd='+escape(cmd));
			}
		}
	}

//used by call control and ajax refresh functions
	function send_cmd(url) {
		if (window.XMLHttpRequest) {// code for IE7+, Firefox, Chrome, Opera, Safari
			xmlhttp=new XMLHttpRequest();
		}
		else {// code for IE6, IE5
			xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
		}
		xmlhttp.open("GET",url,false);
		xmlhttp.send(null);
		document.getElementById('cmd_reponse').innerHTML=xmlhttp.responseText;
	}

//hide/show destination input field
	function toggle_destination(ext) {
		refresh_stop();
		$('#destination_'+ext).fadeToggle(200, function(){
			if ($('#destination_'+ext).is(':visible')) {
				$('#destination_'+ext).focus();
			}
			else {
				$('#destination_'+ext).val('');
				refresh_start();
			}
		});
	}

	function get_transfer_cmd(uuid, destination) {
		cmd = "uuid_transfer " + uuid + " " + destination + " XML <?=trim($_SESSION['user_context'])?>";
		return cmd;
	}

	function get_originate_cmd(source, destination) {
		cmd = "bgapi originate {sip_auto_answer=true,origination_caller_id_number=" + destination + ",sip_h_Call-Info=_undef_}user/" + source + " " + destination + " XML <?=trim($_SESSION['user_context'])?>";
		return cmd;
	}

	function get_eavesdrop_cmd(ext, chan_uuid) {
		cmd = "bgapi originate {origination_caller_id_name=<?=$text['label-eavesdrop']?>,origination_caller_id_number=" + ext + "}user/<?=$_SESSION['user']['extensions'][0]?>@<?=$_SESSION['domain_name']?> &eavesdrop(" + chan_uuid + ")";
		return cmd;
	}

	function get_record_cmd(uuid) {
		cmd = "uuid_record " + uuid + " start <?=$_SESSION['switch']['recordings']['dir']?>/archive/<?=date('Y')?>/<?=date('M')?>/<?=date('d')?>/" + uuid + ".wav";
		return cmd;
	}

//virtual functions
	function virtual_drag(call_id, ext) {
		if (document.getElementById('vd_ext_from').value != '' && document.getElementById('vd_ext_to').value != '') {
			virtual_drag_reset();
		}

		if (call_id != '') {
			document.getElementById('vd_call_id').value = call_id;
		}

		if (ext != '') {
			if (document.getElementById('vd_ext_from').value == '') {
				document.getElementById('vd_ext_from').value = ext;
				document.getElementById(ext).style.borderStyle = 'dotted';
				if (document.getElementById('vd_ext_to').value != '') {
					document.getElementById(document.getElementById('vd_ext_to').value).style.borderStyle = '';
					document.getElementById('vd_ext_to').value = '';
				}
			}
			else {
				document.getElementById('vd_ext_to').value = ext;
				if (document.getElementById('vd_ext_from').value != document.getElementById('vd_ext_to').value) {
					if (document.getElementById('vd_call_id').value != '') {
						cmd = get_transfer_cmd(document.getElementById('vd_call_id').value, document.getElementById('vd_ext_to').value); //transfer a call
					}
					else {
						cmd = get_originate_cmd(document.getElementById('vd_ext_from').value + '@<?=$_SESSION["domain_name"]?>', document.getElementById('vd_ext_to').value); //originate a call
					}
					if (cmd != '') {
						//alert(cmd);
						send_cmd('exec.php?cmd='+escape(cmd));
					}
				}
				virtual_drag_reset();
			}
		}
	}

	function virtual_drag_reset(vd_var) {
		if (!(vd_var === undefined)) {
			document.getElementById(vd_var).value = '';
		}
		else {
			document.getElementById('vd_call_id').value = '';
			if (document.getElementById('vd_ext_from').value != '') {
				document.getElementById(document.getElementById('vd_ext_from').value).style.borderStyle = '';
				document.getElementById('vd_ext_from').value = '';
			}
			if (document.getElementById('vd_ext_to').value != '') {
				document.getElementById(document.getElementById('vd_ext_to').value).style.borderStyle = '';
				document.getElementById('vd_ext_to').value = '';
			}
		}
	}

</script>

<style type="text/css">
	TABLE {
		border-spacing: 0px;
		border-collapse: collapse;
		border: none;
		}
</style>

<?php
//create simple array of users own extensions
unset($_SESSION['user']['extensions']);
foreach ($_SESSION['user']['extension'] as $assigned_extensions) {
	$_SESSION['user']['extensions'][] = $assigned_extensions['user'];
}
?>

<div id='ajax_reponse'></div>
<div id='cmd_reponse' style='display: none;'></div>
<br><br>

<?php
require_once "resources/footer.php";
?>