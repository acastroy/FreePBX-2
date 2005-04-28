<?php /* $Id$ */
// routing.php Copyright (C) 2004 Greg MacLellan (greg@mtechsolutions.ca)
// Asterisk Management Portal Copyright (C) 2004 Coalescent Systems Inc. (info@coalescentsystems.ca)
//
//This program is free software; you can redistribute it and/or
//modify it under the terms of the GNU General Public License
//as published by the Free Software Foundation; either version 2
//of the License, or (at your option) any later version.
//
//This program is distributed in the hope that it will be useful,
//but WITHOUT ANY WARRANTY; without even the implied warranty of
//MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//GNU General Public License for more details.

//script to write conf file from mysql
$extenScript = rtrim($_SERVER['SCRIPT_FILENAME'],$currentFile).'retrieve_extensions_from_mysql.pl';

//script to write sip conf file from mysql
$sipScript = rtrim($_SERVER['SCRIPT_FILENAME'],$currentFile).'retrieve_sip_conf_from_mysql.pl';

//script to write iax conf file from mysql
$iaxScript = rtrim($_SERVER['SCRIPT_FILENAME'],$currentFile).'retrieve_iax_conf_from_mysql.pl';

//script to write op_server.cfg file from mysql 
$wOpScript = rtrim($_SERVER['SCRIPT_FILENAME'],$currentFile).'retrieve_op_conf_from_mysql.pl';


$userdisplay = $_REQUEST['userdisplay'];
$action = $_REQUEST['action'];

// populate some global variables from the request string
$set_globals = array("username","password","extension_high","extension_low","deptname");
foreach ($set_globals as $var) {
	if (isset($_REQUEST[$var])) {
		$$var = stripslashes( $_REQUEST[$var] );
	}
}

$sections = array();
if (isset($_REQUEST["sections"])) {
	if (is_array($_REQUEST["sections"])) {
		$sections = $_REQUEST["sections"];
	} else {
		//TODO do we even need this??
		$sections = explode(";",$_REQUEST["sections"]);
	}
}

//if submitting form, update database
switch ($action) {
	case "addampuser":
		addAmpUser($username, $password, $extension_low, $extension_high, $deptname, $sections);
	break;
	case "editampuser":
		deleteAmpUser($userdisplay);
		addAmpUser($username, $password, $extension_low, $extension_high, $deptname, $sections);
	break;
	case "delampuser":
		deleteAmpUser($userdisplay);
		$userdisplay = ""; // go "add" screen
	break;
}

?>
</div>

<div class="rnav">
    <li><a id="<?php  echo ($userdisplay=='' ? 'current':'') ?>" href="config.php?display=<?php echo $display?>"><?php echo _("Add User")?></a><br></li>

<?php 
//get existing trunk info
$tresults = getAmpUsers();

foreach ($tresults as $tresult) {
    echo "<li><a id=\"".($userdisplay==$tresult[0] ? 'current':'')."\" href=\"config.php?display=".$display."&userdisplay=".$tresult[0]."\">".$tresult[0]."</a></li>";
}

?>
</div>

<div class="content">

<?php 

	if ($userdisplay) {
		echo "<h2>"._("Edit AMP User")."</h2>";
		
		$user = getAmpUser($userdisplay);
		
		$username = $user["username"];
		$password = $user["password"];
		$extension_high = $user["extension_high"];
		$extension_low = $user["extension_low"];
		$deptname = $user["deptname"];
		$sections = $user["sections"];
		
?>
		<p><a href="config.php?display=<?php echo $display ?>&userdisplay=<?php echo $userdisplay ?>&action=delampuser"><?php echo _("Delete User")?> <?php  echo $userdisplay; ?></a></p>
<?php 

	} else {
		// set defaults
		$username = "";
		$password = "";
		$deptname = "";
		
		$extension_low = "";
		$extension_high = "";
		
		$sections = array("*");
		
	
		echo "<h2>"._("Add AMP User")."</h2>";
	} 
?>
	
		<form name="ampuserEdit" action="config.php" method="get">
			<input type="hidden" name="display" value="<?php echo $display?>"/>
			<input type="hidden" name="userdisplay" value="<?php echo $userdisplay ?>"/>
			<input type="hidden" name="action" value=""/>
			<input type="hidden" name="tech" value="<?php echo $tech?>"/>
			<table>
			<tr>
				<td colspan="2">
					<h4><?php echo _("General Settings")?></h4>
				</td>
			</tr>
			<tr>
				<td>
					<a href=# class="info"><?php echo _("Username<span>Create a unique username for this new user</span>")?></a>: 
				</td><td>
					<input type="text" size="20" name="username" value="<?php echo $username;?>"/>
				</td>
			</tr>
<?php  if ($amp_conf["AUTHTYPE"] == "database") { ?>			
			<tr>
				<td>
					<a href=# class="info"><?php echo _("Password<span>Create a password for this new user</span>")?></a>: 
				</td><td>
					<input type="password" size="20" name="password" value="<?php echo $password;?>"/>
				</td>
			</tr>
<?php  } ?>
			<tr>
				<td colspan="2">
					<br>
					<h4><?php echo _("Access Restrictions")?></h4>
				</td>
			</tr>
			<tr>
				<td>
					<a href=# class="info"><?php echo _("Department Name<span>Restrict this user's view of Digital Receptionist menus and System Recordings to only those for this department.</span>")?></a>: 
				</td><td>
					<input type="text" size="20" name="deptname" value="<?php echo $deptname;?>"/>
				</td>
			</tr>
			<tr>
				<td>
					<a href=# class="info"><?php echo _("Extension Range<span>Restrict this user's view to only Extensions, Ring Groups, and Queues within this range.</span>")?></a>: 
				</td><td>
					<input type="text" size="5" name="extension_low" value="<?php echo $extension_low;?>"/>
					&nbsp;to
					<input type="text" size="5" name="extension_high" value="<?php echo $extension_high;?>"/>
				</td>
			</tr>
			<tr>
				<td valign="top">
					<a href=# class="info"><?php echo _("Admin Access<span>Select the Admin Sections this user should have access to.</span>")?></a>: 
				</td><td>&nbsp;
					<select multiple name="sections[]">
					<option>
<?php 
				foreach ($amp_sections as $key=>$value) {
					echo "<option value=\"".$key."\"";
					if (in_array($key, $sections)) echo " SELECTED";
					echo ">".$value."</option>";
				}
				echo "<option value=\"*\"";
				if (in_array("*", $sections)) echo " SELECTED";
				echo ">ALL SECTIONS</option>";
?>					
					</select>
				</td>
			</tr>
			
			<tr>
				<td colspan="2">
					<h6><input name="Submit" type="button" value="Submit Changes" onclick="checkAmpUser(ampuserEdit, '<?php echo ($userdisplay ? "editampuser" : "addampuser") ?>')"></h6>
				</td>
			</tr>
			</table>
		</form>
	
<?php  //Make sure the bottom border is low enuf
foreach ($tresults as $tresult) {
    echo "<br><br><br>";
}
