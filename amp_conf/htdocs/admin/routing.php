<?php /* $Id$ */
// routing.php Copyright (C) 2004 Greg MacLellan (greg@mtechsolutions.ca)
// routing.php <trunk & roting priority additions> Copyright (C) 2005 Ron Hartmann (rhartmann@vercomsystems.com)
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

$display='8'; 
$extdisplay=$_REQUEST['extdisplay'];
$action = $_REQUEST['action'];

$repotrunkdirection = $_REQUEST['repotrunkdirection'];
$repotrunkkey = $_REQUEST['repotrunkkey'];


$dialpattern = array();
if (isset($_REQUEST["dialpattern"])) {
	//$dialpattern = $_REQUEST["dialpattern"];
	$dialpattern = explode("\n",$_REQUEST["dialpattern"]);

	if (!$dialpattern) {
		$dialpattern = array();
	}
	
	foreach (array_keys($dialpattern) as $key) {
		//trim it
		$dialpattern[$key] = trim($dialpattern[$key]);
		
		// remove blanks
		if ($dialpattern[$key] == "") unset($dialpattern[$key]);
		
		// remove leading underscores (we do that on backend)
		if ($dialpattern[$key][0] == "_") $dialpattern[$key] = substr($dialpattern[$key],1);
	}
	
	// check for duplicates, and re-sequence
	$dialpattern = array_values(array_unique($dialpattern));
}
	
if ( (isset($_REQUEST['reporoutedirection'])) && (isset($_REQUEST['reporoutekey']))) {
	$routepriority = getroutenames();
	$routepriority = setroutepriority($routepriority, $_REQUEST['reporoutedirection'], $_REQUEST['reporoutekey']);
}

$trunkpriority = array();
if (isset($_REQUEST["trunkpriority"])) {
	$trunkpriority = $_REQUEST["trunkpriority"];

	if (!$trunkpriority) {
		$trunkpriority = array();
	}
	
	// delete blank entries and reorder
	foreach (array_keys($trunkpriority) as $key) {
		if (empty($trunkpriority[$key])) {
			// delete this empty
			unset($trunkpriority[$key]);
			
		} else if (($key==($repotrunkkey-1)) && ($repotrunkdirection=="up")) {
			// swap this one with the one before (move up)
			$temptrunk = $trunkpriority[$key];
			$trunkpriority[ $key ] = $trunkpriority[ $key+1 ];
			$trunkpriority[ $key+1 ] = $temptrunk;
			
		} else if (($key==($repotrunkkey)) && ($repotrunkdirection=="down")) {
			// swap this one with the one after (move down)
			$temptrunk = $trunkpriority[ $key+1 ];
			$trunkpriority[ $key+1 ] = $trunkpriority[ $key ];
			$trunkpriority[ $key ] = $temptrunk;
		}
	}
	unset($temptrunk);
	$trunkpriority = array_values($trunkpriority); // resequence our numbers
}

$routename = isset($_REQUEST["routename"]) ? $_REQUEST["routename"] : "";

//if submitting form, update database
switch ($action) {
	case "addroute":
		addRoute($routename, $dialpattern, $trunkpriority);
		exec($extenScript);
		needreload();
	break;
	case "editroute":
		editRoute($routename, $dialpattern, $trunkpriority);
		exec($extenScript);
		needreload();
	break;
	case "delroute":
		deleteRoute($extdisplay);
		exec($extenScript);
		needreload();
		
		$extdisplay = ''; // resets back to main screen
	break;
	case 'renameroute':
		if (renameRoute($routename, $_REQUEST["newroutename"])) {
			exec($extenScript);
			needreload();
		} else {
			echo "<script language=\"javascript\">alert('Error renaming route: duplicate name');</script>";
		}
		$route_prefix=substr($routename,0,4);
		$extdisplay=$route_prefix.$_REQUEST["newroutename"];

	break;
	case 'prioritizeroute':
		exec($extenScript);
		needreload();
	break;
	case 'populatenpanxx':
		if (preg_match("/^([2-9]\d\d)-?([2-9]\d\d)$/", $_REQUEST["npanxx"], $matches)) {
			// first thing we do is grab the exch:
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_URL, "http://members.dandy.net/~czg/prefix.php?npa=".$matches[1]."&nxx=".$matches[2]."&ocn=&pastdays=0&nextdays=0");
			curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Linux; Amportal Local Trunks Configuration)");
			$str = curl_exec($ch);
			curl_close($ch);
			
			if (preg_match("/exch=(\d+)/",$str, $matches)) {
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_URL, "http://members.dandy.net/~czg/lprefix.php?exch=".$matches[1]);
				curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Linux; Amportal Local Trunks Configuration)");
				$str = curl_exec($ch);
				curl_close($ch);
				
				foreach (explode("\n", $str) as $line) {
					if (preg_match("/^(\d{3});(\d{3})/", $line, $matches)) {
						$dialpattern[] = "1".$matches[1].$matches[2]."XXXX";
						//$localprefixes[] = "1".$matches[1].$matches[2];
					}
				}
				
				// check for duplicates, and re-sequence
				$dialpattern = array_values(array_unique($dialpattern));
			} else {
				$errormsg = "Error fetching prefix list for: ". $_REQUEST["npanxx"];
			}
			
		} else {
			// what a horrible error message... :p
			$errormsg = "Invalid format for NPA-NXX code (must be format: NXXNXX)";
		}
		
		if (isset($errormsg)) {
			echo "<script language=\"javascript\">alert('".addslashes($errormsg)."');</script>";
			unset($errormsg);
		}
	break;
}
	

	
//get all rows from globals
$sql = "SELECT * FROM globals";
$globals = $db->getAll($sql);
if(DB::IsError($globals)) {
die($globals->getMessage());
}

//create a set of variables that match the items in global[0]
foreach ($globals as $global) {
	${$global[0]} = htmlentities($global[1]);	
}

?>
</div>



<div class="rnav">
    <li><a id="<?php  echo ($extdisplay=='' ? 'current':'') ?>" href="config.php?display=<?php echo $display?>">Add Route</a><br></li>
<?php 
$reporoutedirection = $_REQUEST['reporoutedirection'];
$reporoutekey = $_REQUEST['reporoutekey'];
$key = -1;
$routepriority = getroutenames();
$positions=count($routepriority);
foreach ($routepriority as $tresult) {
$key++;
?>
			<?php   // move up
    			echo "<li><a id=\"".($extdisplay==$tresult[0] ? 'current':'')."\" href=\"config.php?display=".$display."&extdisplay={$tresult[0]}\">$key ". substr($tresult[0],4)."</a>";
			if ($key > 0) {?>
				<img src="images/scrollup.gif" onclick="repositionRoute('<?php echo $key ?>','up')" alt="Move Up" style="float:none; margin-left:0px; margin-bottom:0px;" width="9" height="11">
			<?php  } else { ?>
				<img src="images/blank.gif" style="float:none; margin-left:0px; margin-bottom:0px;" width="9" height="11">
			<?php  }
			
			// move down
			
			if ($key < ($positions-1)) {?>
				<img src="images/scrolldown.gif" onclick="repositionRoute('<?php echo $key ?>','down')" alt="Move Down"  style="float:none; margin-left:0px; margin-bottom:0px;" width="9" height="11">
			<?php  } else { ?>
				<img src="images/blank.gif" style="float:none; margin-left:0px; margin-bottom:0px;" width="9" height="11">
			<?php  } 
			echo "</li>";?>
			
<?php 
} // foreach
?>
</div>

<div class="content">

<?php 
if ($extdisplay) {
	
	// load from db
	
	if (!isset($_REQUEST["dialpattern"])) {
		$dialpattern = getroutepatterns($extdisplay);
	}
	
	if (!isset($_REQUEST["trunkpriority"])) {
		$trunkpriority = getroutetrunks($extdisplay);
	}
	
	echo "<h2>Edit Route</h2>";
} else {	
	echo "<h2>Add Route</h2>";
}

// build trunks associative array
foreach (gettrunks() as $temp) {
	$trunks[$temp[0]] = $temp[1];
}

if ($extdisplay) { // editing
?>
	<p><a href="config.php?display=<?php echo $display ?>&extdisplay=<?php echo $extdisplay ?>&action=delroute">Delete Route <?php  echo substr($extdisplay,4); ?></a></p>
<?php  } ?>

	<form id="routeEdit" name="routeEdit" action="config.php" method="POST">
		<input type="hidden" name="display" value="<?php echo $display?>"/>
		<input type="hidden" name="extdisplay" value="<?php echo $extdisplay ?>"/>
		<input type="hidden" id="action" name="action" value=""/>
		<table>
		<tr>
			<td>
				<a href=# class="info">Route Name<span><br>Name of this route. Should be used to describe what type of calls this route matches (for example, 'local' or 'longdistance').<br><br></span></a>: 
			</td>
<?php  if ($extdisplay) { // editing?>
			<td>
				<?php echo substr($extdisplay,4);?>
				<input type="hidden" id="routename" name="routename" value="<?php echo $extdisplay;?>"/>
				<input type="button" onClick="renameRoute();" value="Rename" style="font-size:10px;"  />
				<input type="hidden" id="newroutename" name="newroutename" value=""/>
				<script language="javascript">
				function renameRoute() {
					do {
						var newname = prompt("Rename route " + document.getElementById('routename').value + " to:");
						if (newname == null) return;
					} while (!newname.match('^[a-zA-Z][a-zA-Z0-9]+$') && !alert("Route name cannot start with a number, and can only contain letters and numbers"));
					
					document.getElementById('newroutename').value = newname;
					document.getElementById('routeEdit').action.value = 'renameroute';
					document.getElementById('routeEdit').submit();
				}
				</script>
			</td>
<?php  } else { // new ?>
			<td>
				<input type="text" size="20" name="routename" value="<?php echo $routename;?>"/>
			</td>
<?php  } ?>
		</tr>
		<tr>
			<td colspan="2">
				<br>
				<a href=# class="info">Dial Patterns<span>A Dial Pattern is a unique set of digits that will select this trunk. Enter one dial pattern per line.<br><br><b>Rules:</b><br>
   <strong>X</strong>&nbsp;&nbsp;&nbsp; matches any digit from 0-9<br>
   <strong>Z</strong>&nbsp;&nbsp;&nbsp; matches any digit form 1-9<br>
   <strong>N</strong>&nbsp;&nbsp;&nbsp; matches any digit from 2-9<br>
   <strong>[1237-9]</strong>&nbsp;   matches any digit or letter in the brackets (in this example, 1,2,3,7,8,9)<br>
   <strong>.</strong>&nbsp;&nbsp;&nbsp; wildcard, matches one or more characters <br>
   <strong>|</strong>&nbsp;&nbsp;&nbsp; seperates a dialing prefix from the number (for example, 9|NXXXXXX would match when some dialed "95551234" but would only pass "5551234" to the trunks)
				</span></a><br><br>
			</td>
		</tr>
<?php  /* old code for using textboxes -- replaced by textarea code
$key = -1;
foreach ($dialpattern as $key=>$pattern) {
?>
		<tr>
			<td><?php echo $key ?>
			</td><td>
				<input type="text" size="20" name="dialpattern[<?php echo $key ?>]" value="<?php echo $dialpattern[$key] ?>"/>
			</td>
		</tr>
<?php 
} // foreach

$key += 1; // this will be the next key value
?>
		<tr>
			<td><?php echo $key ?>
			</td><td>
				<input type="text" size="20" name="dialpattern[<?php echo $key ?>]" value="<?php echo $dialpattern[$key] ?>"/>
			</td>
		</tr>
		<tr>
			<td>&nbsp;</td>
			<td>
				<br><input type="submit" value="Add">
			</td>
		</tr>
<?php  */ ?>
		<tr>
			<td>
			</td><td>
				<textarea cols="20" rows="<?php  $rows = count($dialpattern)+1; echo (($rows < 5) ? 5 : (($rows > 20) ? 20 : $rows) ); ?>" id="dialpattern" name="dialpattern"><?php echo  implode("\n",$dialpattern);?></textarea><br>
				
				<input type="submit" style="font-size:10px;" value="Clean & Remove duplicates" />
			</td>
		</tr>
		<tr>
			<td>Insert:</td>
			<input id="npanxx" name="npanxx" type="hidden" />
			<script language="javascript">
			
			function populateLookup() {
<?php 
	if (function_exists("curl_init")) { // curl is installed
?>				
				//var npanxx = prompt("What is your areacode + prefix (NPA-NXX)?", document.getElementById('areacode').value);
				do {
					var npanxx = prompt("What is your areacode + prefix (NPA-NXX)?\n\n(Note: this database contains North American numbers only, and is not guaranteed to be 100% accurate. You will still have the option of modifying results.)\n\nThis may take a few seconds.");
					if (npanxx == null) return;
				} while (!npanxx.match("^[2-9][0-9][0-9][-]?[2-9][0-9][0-9]$") && !alert("Invalid NPA-NXX. Must be of the format 'NXX-NXX'"));
				
				document.getElementById('npanxx').value = npanxx;
				document.getElementById('routeEdit').action.value = "populatenpanxx";
				document.getElementById('routeEdit').submit();
<?php  
	} else { // curl is not installed
?>
				alert("Error: Cannot continue!\n\nPrefix lookup requires cURL support in PHP on the server. Please install or enable cURL support in your PHP installation to use this function. See http://www.php.net/curl for more information.");
<?php 
	}
?>
			}
			
						
			function insertCode() {
				code = document.getElementById('inscode').value;
				insert = '';
				switch(code) {
					case "local":
						insert = 'NXXXXXX\n';
					break;
					case "local10":
						insert = 'NXXXXXX\n'+
							'NXXNXXXXXX\n';
					break;
					case 'tollfree':
						insert = '1800NXXXXXX\n'+
							'1888NXXXXXX\n'+
							'1877NXXXXXX\n'+
							'1866NXXXXXX\n';
					break;
					case "ld":
						insert = '1NXXNXXXXXX\n';
					break;
					case "int":
						insert = '011.\n';
					break;
					case 'info':
						insert = '411\n'+
							'311\n';
					break;
					case 'emerg':
						insert = '911\n';
					break;
					case 'lookup':
						populateLookup();
						insert = '';
					break;
					
				}
				dialPattern=document.getElementById('dialpattern');
				if (dialPattern.value[ dialPattern.value.length - 1 ] == "\n") {
					dialPattern.value = dialPattern.value + insert;
				} else {
					dialPattern.value = dialPattern.value + '\n' + insert;
				}
				
				// reset element
				document.getElementById('inscode').value = '';
			}
			
			--></script>
			<td>
				<select onChange="insertCode();" id="inscode">
					<option value="">Pick pre-defined patterns</option>
					<option value="local">Local 7 digit</option>
					<option value="local10">Local 7/10 digit</ption>
					<option value="tollfree">Toll-free</option>
					<option value="ld">Long-distance</option>
					<option value="int">International</option>
					<option value="info">Information</option>
					<option value="emerg">Emergency</option>
					<option value="lookup">Lookup local prefixes</option>
				</select>
			</td>
		</tr>
		<tr>
			<td colspan="2">
			<br><br>
				<a href=# class="info">Trunk Sequence<span>The Trunk Sequence controls the order of trunks that will be used when the above Dial Patterns are matched. <br><br>For Dial Patterns that match long distance numbers, for example, you'd want to pick the cheapest routes for long distance (ie, VoIP trunks first) followed by more expensive routes (POTS lines).<br></span></a><br><br>
			</td>
		</tr>
		<input type="hidden" id="repotrunkdirection" name="repotrunkdirection" value="">
		<input type="hidden" id="repotrunkkey" name="repotrunkkey" value="">
		<input type="hidden" id="reporoutedirection" name="reporoutedirection" value="">
		<input type="hidden" id="reporoutekey" name="reporoutekey" value="">
<?php 
$key = -1;
$positions=count($trunkpriority);
foreach ($trunkpriority as $key=>$trunk) {
?>
		<tr>
			<td align="right"><?php echo $key; ?>&nbsp;&nbsp;
			</td>
			<td>
				<select id='trunkpri<?php echo $key ?>' name="trunkpriority[<?php echo $key ?>]">
				<option value=""></option>
				<?php 
				foreach ($trunks as $name=>$display) {
					echo "<option id=\"trunk".$key."\" value=\"".$name."\" ".($name == $trunk ? "selected" : "").">".$display."</option>";
				}
				?>
				</select>
				
				<img src="images/delete.gif" style="float:none; margin-left:0px; margin-bottom:0px;" width="9" height="11" onclick="deleteTrunk(<?php echo $key ?>)">
			<?php   // move up
			if ($key > 0) {?>
				<img src="images/scrollup.gif" onclick="repositionTrunk(repotrunkdirection,repotrunkkey, '<?php echo $key ?>','up')" alt="Move Up" style="float:none; margin-left:0px; margin-bottom:0px;" width="9" height="11">
			<?php  } else { ?>
				<img src="images/blank.gif" style="float:none; margin-left:0px; margin-bottom:0px;" width="9" height="11">
			<?php  }
			
			// move down
			
			if ($key < ($positions-1)) {?>
				<img src="images/scrolldown.gif" onclick="repositionTrunk(repotrunkdirection,repotrunkkey, '<?php echo $key ?>','down')" alt="Move Down"  style="float:none; margin-left:0px; margin-bottom:0px;" width="9" height="11">
			<?php  } else { ?>
				<img src="images/blank.gif" style="float:none; margin-left:0px; margin-bottom:0px;" width="9" height="11">
			<?php  } ?>
			</td>
		</tr>
<?php 
} // foreach

$key += 1; // this will be the next key value
$name = "";
?>
		<tr>
			<td> &nbsp </td>
			<td>
				<select id='trunkpri<?php echo $key ?>' name="trunkpriority[<?php echo $key ?>]">
				<option value="" SELECTED></option>
				<?php 
				foreach ($trunks as $name=>$display) {
					echo "<option value=\"".$name."\">".$display."</option>";
				}
				?>
				</select>
			</td>
		</tr>
		<tr>
			<td></td>
			<td>
				<input type="submit" value="Add">
			</td>
		</tr>
		<tr>
			<td colspan="2">
			<br>
				<h6><input name="Submit" type="button" value="Submit Changes" onclick="checkRoute(routeEdit, '<?php echo ($extdisplay ? "editroute" : "addroute") ?>')"></h6>
			</td>
		</tr>
		</table>
	</form>
	
<?php  //Make sure the bottom border is low enuf
foreach ($routepriority as $tresult) {
    echo "<br><br><br>";
}
