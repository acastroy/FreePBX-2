<?php

function frameworkPasswordCheck() {
	global $amp_conf;

	$freepbx_conf =& freepbx_conf::create();
  $amp_conf_defaults =& $freepbx_conf->conf_defaults;

	$nt = notifications::create($db);
	if ($amp_conf['AMPMGRPASS'] == $amp_conf_defaults['AMPMGRPASS'][1]) {
		$nt->add_warning('core', 'AMPMGRPASS', _("Default Asterisk Manager Password Used"), _("You are using the default Asterisk Manager password that is widely known, you should set a secure password"));
	} else {
		$nt->delete('core', 'AMPMGRPASS');
	}
	
	if ($amp_conf['ARI_ADMIN_PASSWORD'] == $amp_conf_defaults['ARI_ADMIN_PASSWORD'][1]) {
		$nt->add_warning('ari', 'ARI_ADMIN_PASSWORD', _("Default ARI Admin password Used"), _("You are using the default ARI Admin password that is widely known, you should change to a new password. Do this in Advanced Settings"));
	} else {
		$nt->delete('ari', 'ARI_ADMIN_PASSWORD');
	}
	
	if ($amp_conf['AMPDBPASS'] == $amp_conf_defaults['AMPDBPASS'][1]) {
		$nt->add_warning('core', 'AMPDBPASS', _("Default SQL Password Used"), _("You are using the default SQL password that is widely known, you should set a secure password"));
	} else {
		$nt->delete('core', 'AMPDBPASS');
	}
	
	// Check and increase php memory_limit if needed and if allowed on the system
	//
	$current_memory_limit = rtrim(ini_get('memory_limit'),'M');
	$proper_memory_limit = '100';
	if ($current_memory_limit < $proper_memory_limit) {
		if (ini_set('memory_limit',$proper_memory_limit.'M') !== false) {
			$nt->add_notice('core', 'MEMLIMIT', _("Memory Limit Changed"), sprintf(_("Your memory_limit, %sM, is set too low and has been increased to %sM. You may want to change this in you php.ini config file"),$current_memory_limit,$proper_memory_limit));
		} else {
			$nt->add_warning('core', 'MEMERR', _("Low Memory Limit"), sprintf(_("Your memory_limit, %sM, is set too low and may cause problems. FreePBX is not able to change this on your system. You should increase this to %sM in you php.ini config file"),$current_memory_limit,$proper_memory_limit));
		}
	} else {
		$nt->delete('core', 'MEMLIMIT');
	}

	// send error if magic_quotes_gpc is enabled on this system as much of the code base assumes not
	//
	if(get_magic_quotes_gpc()) {
		$nt->add_error('core', 'MQGPC', _("Magic Quotes GPC"), _("You have magic_quotes_gpc enabled in your php.ini, http or .htaccess file which will cause errors in some modules. FreePBX expects this to be off and runs under that assumption"));
	} else {
		$nt->delete('core', 'MQGPC');
	}
}

/** Loads a view (from the views/ directory) with a number of named parameters created as local variables.
 * @param  string   The name of the view.
 * @param  array    The parameters to pass. Note that the key will be turned into a variable name for use by the view.
 *                  For example, passing array('foo'=>'bar'); will create a variable $foo that can be used by
 *                  the code in the view.
 */
function loadview($viewname, $parameters = false) {
	ob_start();
	showview($viewname, $parameters);
	$contents = ob_get_contents();
	ob_end_clean();
	return $contents;
}

/** Outputs the contents of a view.
 * @param  string   The name of the view.
 * @param  array    The parameters to pass. Note that the key will be turned into a variable name for use by the view.
 *                  For example, passing array('foo'=>'bar'); will create a variable $foo that can be used by
 *                  the code in the view.
 */
function showview($viewname, $parameters = false) {
	global $amp_conf, $db;
	if (is_array($parameters)) {
		extract($parameters);
	}

	$viewname = str_replace('..','.',$viewname); // protect against going to subdirectories
	if (file_exists('views/'.$viewname.'.php')) {
		include('views/'.$viewname.'.php');
	}
}

// setup locale
function set_language() {
	if (extension_loaded('gettext')) {
		if (isset($_COOKIE['lang'])) {
			setlocale(LC_ALL,  $_COOKIE['lang']);
			putenv("LANGUAGE=".$_COOKIE['lang']);
		} else {
			setlocale(LC_ALL,  'en_US');
		}
		bindtextdomain('amp','./i18n');
		bind_textdomain_codeset('amp', 'utf8');
		textdomain('amp');
	}
}

//
function fileRequestHandler($handler, $module = false, $file = false){
	global $amp_conf;
	
	switch ($handler) {
		case 'cdr':
			include('cdr/cdr.php');
			break;
		case 'cdr_export_csv':
			include('cdr/export_csv.php');
			break;
		case 'cdr_export_pdf':
			include('cdr/export_pdf.php');
			break;
		case 'reload':
			// AJAX handler for reload event
			$response = do_reload();
			header("Content-type: application/json");
			echo json_encode($response);
		break;
		case 'file':
			/** Handler to pass-through file requests 
			 * Looks for "module" and "file" variables, strips .. and only allows normal filename characters.
			 * Accepts only files of the type listed in $allowed_exts below, and sends the corresponding mime-type, 
			 * and always interprets files through the PHP interpreter. (Most of?) the freepbx environment is available,
			 * including $db and $astman, and the user is authenticated.
			 */
			if (!$module || !$file) {
				die_freepbx("unknown");
			}
			//TODO: this could probably be more efficient
			$module = str_replace('..','.', preg_replace('/[^a-zA-Z0-9-\_\.]/','',$module));
			$file = str_replace('..','.', preg_replace('/[^a-zA-Z0-9-\_\.]/','',$file));
			
			$allowed_exts = array(
				'.js' => 'text/javascript',
				'.js.php' => 'text/javascript',
				'.css' => 'text/css',
				'.css.php' => 'text/css',
				'.html.php' => 'text/html',
				'.jpg.php' => 'image/jpeg',
				'.jpeg.php' => 'image/jpeg',
				'.png.php' => 'image/png',
				'.gif.php' => 'image/gif',
			);
			foreach ($allowed_exts as $ext=>$mimetype) {
				if (substr($file, -1*strlen($ext)) == $ext) {
					$fullpath = 'modules/'.$module.'/'.$file;
					if (file_exists($fullpath)) {
						// file exists, and is allowed extension

						// image, css, js types - set Expires to 24hrs in advance so the client does
						// not keep checking for them. Replace from header.php
						if (!$amp_conf['DEVEL']) {
							@header('Expires: '.gmdate('D, d M Y H:i:s', time() + 86400).' GMT', true);
							@header('Cache-Control: max-age=86400, public, must-revalidate',true); 
							@header('Pragma: ', true); 
						}
						@header("Content-type: ".$mimetype);
						include($fullpath);
						exit();
					}
					break;
				}
			}
			die_freepbx("../view/not allowed");
		break;
	}
	exit();
}

/**
 * Load View
 *
 * This function is used to load a "view" file. It has two parameters:
 *
 * 1. The name of the "view" file to be included.
 * 2. An associative array of data to be extracted for use in the view.
 *
 * NOTE: you cannot use the variable $view_filename_protected in your views!
 *
 * @param	string
 * @param	array
 * @return	string
 * 
 */

function load_view($view_filename_protected, array $vars = array()) {
	
	//return false if we cant find the file or if we cant open it
	if ( ! $view_filename_protected OR ! file_exists($view_filename_protected) OR ! is_readable($view_filename_protected) ) {
		return false;
	}

	// Import the view variables to local namespace
	extract($vars, EXTR_SKIP);
	
	// Capture the view output
	ob_start();
	
	// Load the view within the current scope
	include($view_filename_protected);
	
	// Get the captured output
	$buffer = ob_get_contents();
	
	//Flush & close the buffer
	@ob_end_clean();
	
	//Return captured output
	return $buffer;
	
}
