function checkForm(theForm) {
	$tech = theForm.tech.value;
	$account = theForm.account.value;
	$vmpwd = theForm.vmpwd.value;
	$email = theForm.email.value;
	$pager = theForm.pager.value;
	$context = theForm.context.value;
	
	if ($tech != "zap") {
		$secret = theForm.secret.value;
		$host = theForm.host.value;
		$type = theForm.type.value;
		$username = theForm.username.value;
		if ($username == "") {
			theForm.username.value = $account;
			$username = $account;
		}
	}

	$mailbox = theForm.mailbox.value;
	$fullname = theForm.name.value;
	$vm = theForm.vm.value;
	
	if ($tech == "zap") {
		$channel=theForm.channel.value;
	}

	
	if (($tech != "zap") && ($account == "" || $secret == "" || $context == "" || $host == "" || $type == ""  || $username == "")) {
		alert('Please fill in all required fields.');
	} else if (($tech == "zap") && ( $account == "" || $context == "" || $channel=="")) {
		alert('Please fill in all required fields.');
	} else if (($account.indexOf('0') == 0) && ($account.length > 1)) {
		alert('Extensions numbers with more than one digit cannot begin with 0');
	} else if ($account != parseInt($account)) {
		alert('There is something wrong with your extension number - it must be in integer');
	} else if ($vm == "enabled" && $fullname == "" && $vmpwd == "" && $email == "" && $pager == "") {
		alert('You have enabled Voicemail & Directory for this extension, but have not specified any options.  Please specify options, or disable Voicemail & Directory.');	
	} else {
	theForm.submit();
	}
}

function checkGeneral(theForm) {
	$RINGTIMER = theForm.RINGTIMER.value;
	$FAX_RX = theForm.FAX_RX.value;

	if ($RINGTIMER == "" || $FAX_RX == "") {
		alert('Please fill out all forms.');
	} else {
	theForm.submit();
	}
}

function checkIncoming(theForm) {
	$INCOMING = theForm.INCOMING.value;

	if ($INCOMING == "") {
		alert('Please select where you would like to send incoming calls to.');
	} else {
	theForm.submit();
	}
}

function checkGRP(theForm,action) {
	var bad = "false";

	var whichitem = 0;
	while (whichitem < theForm.goto_indicate.length) {
		if (theForm.goto_indicate[whichitem].checked) {
			theForm.goto0.value=theForm.goto_indicate[whichitem].value;
		}
		whichitem++;
	}

	var gotoType = theForm.elements[ "goto0" ].value;
	if (gotoType == 'custom') {
		var gotoVal = theForm.elements[ "custom_args"].value;
		if (gotoVal.indexOf('custom') == -1) {
			bad = "true";
			alert('Custom Goto contexts must contain the string "custom".  ie: custom-app,s,1');
		}
	}
	
	$grplist = theForm.grplist.value;
	if ($grplist == "") {
		alert('Please enter an extension list.');
		bad="true";
	} 
	
	$account = theForm.account.value;
	if (($account.indexOf('0') == 0) && ($account.length > 1)) {
		alert('Group numbers with more than one digit cannot begin with 0');
		bad="true";
	}
	
	if (bad == "false") {
		theForm.submit();
	}
}

function checkDID(theForm) {
	$account = theForm.account.value;

	var whichitem = 0;
	while (whichitem < theForm.goto_indicate.length) {
		if (theForm.goto_indicate[whichitem].checked) {
			theForm.goto0.value=theForm.goto_indicate[whichitem].value;
		}
		whichitem++;
	}
	
	if ($account == "") {
		alert('Please enter a DID number list.');
	} else {
	theForm.submit();
	}
}

function checkTrunk(theForm, action) {
	$channelid = theForm.channelid.value;
	$usercontext = theForm.usercontext.value;

	if ($channelid == "") {
		alert('Missing required field: trunk name');
	} else if ($channelid == $usercontext) {
		alert('Trunk Name and User Context cannot be set to the same value');
	} else {
		theForm.action.value = action;
		theForm.submit();
	}
}

function checkRoute(theForm, action) {
	$routename = theForm.routename.value;
	$dialpattern = theForm.dialpattern.value;
	$trunkpriority = document.getElementById('trunkpri0').value;

	if ($routename == "") {
		alert('Route name must not be blank');
	} else if (!$routename.match('^[a-zA-Z][a-zA-Z0-9]+$')) {
		alert('Route name cannot start with a number, and can only contain letters and numbers');
	} else if (!$dialpattern.match('[A-Z0-9a-z]+')) {
		alert('Dial pattern cannot be blank');
	} else if ($trunkpriority.value == '') {
		//TODO this doesn't account for other items besides the first being filled in
		alert('At least one trunk must be picked');
	} else {
		theForm.action.value = action;
		theForm.submit();
	}
}

function openWindow(url,width,height) { 
	popupWin = window.open(url, '', 'width='+width + ',height='+height)
}

function checkIVR(theForm,ivr_num_options) {
	var bad = "false";
	for (var formNum = 0; formNum < ivr_num_options; formNum++) {
		var gotoType = theForm.elements[ "goto"+formNum ].value;
		if (gotoType == 'custom') {
			var gotoVal = theForm.elements[ "custom"+formNum ].value;
			if (gotoVal.indexOf('custom') == -1) {
				bad = "true";
				item = formNum + 1;
				alert('There is a problem with option number '+item+'.\n\nCustom Goto contexts must contain the string "custom".  ie: custom-app,s,1');
			}
		}
	}
	if (bad == "false") {
		theForm.submit();
	}
}

function checkVoicemail(theForm) {
	$vm = theForm.elements["vm"].value;
	if ($vm == 'disabled') {
		document.getElementById('voicemail').style.display='none';
		theForm.vmpwd.value = '';
		theForm.name.value = '';
		theForm.email.value = '';
		theForm.pager.value = '';
	} else {
		document.getElementById('voicemail').style.display='block';
	}
}

function hideExtenFields(theForm) {
	if(theForm.tech.value == 'iax2') {
		document.getElementById('dtmfmode').style.display = 'none';
		document.getElementById('secret').style.display = 'inline';
		document.getElementById('channel').style.display = 'none';
	} else if (theForm.tech.value == 'sip') {
		document.getElementById('dtmfmode').style.display = 'inline';
		document.getElementById('secret').style.display = 'inline';
		document.getElementById('channel').style.display = 'none';
	} else if (theForm.tech.value == 'zap') {
		document.getElementById('dtmfmode').style.display = 'none';
		document.getElementById('secret').style.display = 'none';
		document.getElementById('channel').style.display = 'block';
	}
}
