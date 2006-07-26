<?php /* $Id$ */
//Copyright (C) 2004 Coalescent Systems Inc. (info@coalescentsystems.ca)
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

	require_once('common/db_connect.php'); //PEAR must be installed
	
	//determine if asterisk reload is needed
	$sql = "SELECT value FROM admin WHERE variable = 'need_reload'";
	$need_reload = $db->getRow($sql);
	if(DB::IsError($need_reload)) {
		die($need_reload->getMessage());
	}
//check to see if we are requesting an asterisk reload
if (isset($_REQUEST['clk_reload'])) {
	
	if (isset($amp_conf["POST_RELOAD"]))
	{
		echo "<div id='idWaitBanner' class='clsWait'> Please wait while applyig configuration</div>";
		
		if (!isset($amp_conf["POST_RELOAD_DEBUG"]) || 
		    (($amp_conf["POST_RELOAD_DEBUG"]!="1") && 
		     ($amp_conf["POST_RELOAD_DEBUG"]!="true")) 
		   )
			echo "<div style='display:none'>";
			
		echo "Executing post apply script <b>".$amp_conf["POST_RELOAD"]."</b><pre>";
		system( $amp_conf["POST_RELOAD"] );
		echo "</pre>";
		
		if (!isset($amp_conf["POST_RELOAD_DEBUG"]) || 
		    (($amp_conf["POST_RELOAD_DEBUG"]!="1") && 
		     ($amp_conf["POST_RELOAD_DEBUG"]!="true"))
		    )
			echo "</div><br>";
		
 		echo "
			<script> 
				function hideWaitBanner()
				{
					document.getElementById('idWaitBanner').className = 'clsHidden';
				}

				document.getElementById('idWaitBanner').innerHTML = 'Configuration applied';
				document.getElementById('idWaitBanner').className = 'clsWaitFinishOK';
				setTimeout('hideWaitBanner()',3000);
			</script>
		";
	}
	
	//run retrieve script
	$retrieve = $amp_conf['AMPBIN'].'/retrieve_conf';
	exec($retrieve.'&>'.$asterisk_conf['astlogdir'].'/freepbx-retrieve.log');
	
	require_once('common/php-asmanager.php');
	$astman = new AGI_AsteriskManager();
	if ($res = $astman->connect("127.0.0.1", $amp_conf["AMPMGRUSER"] , $amp_conf["AMPMGRPASS"])) {
		/*	Would be cool to do the following from here 
			(to avoid permission problems when running apache as nobody).
			Unfortunately, I can't make it work :-(
		$astman->send_request('Command', array('Command'=>'!/var/lib/asterisk/bin/retrieve_conf'));
		*/	
		//reload asterisk
		$astman->send_request('Command', array('Command'=>'reload'));	
		$astman->disconnect();
		
		//bounce op_server.pl
		// TODO, should this file be on the web root? whats wrong with /var/lib/asterisk/bin?
		$wOpBounce = rtrim($_SERVER['SCRIPT_FILENAME'],$currentFile).'bounce_op.sh';
		exec($wOpBounce.'&>'.$asterisk_conf['astlogdir'].'/freepbx-bounce_op.log');
		
		//store asterisk reloaded status
		$sql = "UPDATE admin SET value = 'false' WHERE variable = 'need_reload'"; 
		$result = $db->query($sql); 
		if(DB::IsError($result)) {     
			die($result->getMessage()); 
		}
		$need_reload[0] = 'false';
	} else {
		echo _("Cannot connect to Asterisk Manager with ").$amp_conf["AMPMGRUSER"]."/".$amp_conf["AMPMGRPASS"];
	}
}
if (isset($_SESSION["AMP_user"]) && ($_SESSION["AMP_user"]->checkSection(99))) {
	if ($need_reload[0] == 'true') {
		if (isset($_REQUEST['display'])) {
	?>
	<div class="inyourface"><a href="<?php  echo $_SERVER["PHP_SELF"]?>?<? echo (isset($_REQUEST['type']))?'type='.$_REQUEST['type'].'&amp;':''; ?>display=<?php  echo $_REQUEST['display'] ?>&amp;clk_reload=true"><?php echo _("You have made changes - when finished, click here to APPLY them") ?></a></div>
	<?php } else { ?>
	<div class="inyourface"><a href="<?php  echo $_SERVER["PHP_SELF"]?>?clk_reload=true"><?php echo _("You have made changes - when finished, click here to APPLY them") ?></a></div>
	<?php 
		}
	}
}

if (!$quietmode) {
?>
		
    <span class="footer" style="text-align:center;">
		<!--<a target="_blank" href="http://sourceforge.net/donate/index.php?group_id=121515"><img border="0" style="float:left;" alt="Donate to the Asterisk Management Portal project" src="http://images.sourceforge.net/images/project-support.jpg"></a>-->
 	<?php
 	if (isset($amp_conf["AMPFOOTERLOGO"])){
 		if (isset($amp_conf["AMPADMINHREF"])){?>
 	        	<a target="_blank" href="http://<?php echo $amp_conf["AMPADMINHREF"] ?>"><img border="0" src="images/<?php echo $amp_conf["AMPFOOTERLOGO"] ?>"></a>
 		<?php } else{ ?>
 	        	<a target="_blank" href="http://www.freepbx.org"><img border="0" src="images/<?php echo $amp_conf["AMPFOOTERLOGO"] ?>"></a>
 		<?php } ?>
 	<?php } else{ ?>
         	<a target="_blank" href="http://www.freepbx.org"><img border="0" src="images/freepbx_small.png"></a>
 	<?php }  ?>        
 	<a target="_blank" href="http://www.freepbx.org"><img border="0" style="float:left;" src="images/freepbx_small.png"></a>
        <br>
		<br>
<?php
	echo "Version ";
	$ver=getversion(); echo $ver[0][0];
	echo " on <b>".$_SERVER["SERVER_NAME"]."</b>";
?>
		<br>
		<br>
    </span>
<?php 
}
?>
</div>

<br>
<br>
<br>
<br>
</body>

</html>
