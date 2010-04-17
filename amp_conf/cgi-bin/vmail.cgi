#!/usr/bin/perl
#
# Web based Voicemail for Asterisk
#
# Copyright (C) 2002, Linux Support Services, Inc.
#
# Distributed under the terms of the GNU General Public License
#
# Written by Mark Spencer <markster@linux-support.net>
#
# (icky, I know....  if you know better perl please help!)
#
#
use CGI qw/:standard/;
use Carp::Heavy;
use CGI::Carp qw(fatalsToBrowser);

@validfolders = ( "INBOX", "Old", "Work", "Family", "Friends" );

%formats = (
	"wav" => {
		name => "Best Quality (recommended for fast network connections)",
		mime => "audio/x-wav",
		pref => 1
	},
	"WAV" => {
		name => "Smallest Download (recommended for slower network connections)",
		mime => "audio/x-wav",
		pref => 2
	},
#	"gsm" => {
#		name => "Raw GSM Audio",
#		mime => "audio/x-gsm",
#		pref => 3
#	}
);

$astpath = "/_asterisk";


$stdcontainerstart = "<table align=center width=600><tr><td>\n";
$footer = "<hr>";
$stdcontainerend = "</td></tr><tr><td align=right>$footer</td></tr></table>\n";

sub getcookie()
{
	my ($var) = @_;
	return cookie($var);
}

sub makecookie()
{
	my ($format) = @_;
	cookie(-name => "format", -value =>["$format"], -expires=>"+1y");
}

sub makepasscookie()
{
	my ($vmpass) = @_;
	cookie(-name => "vmpass", -value =>["$vmpass"], -expires=>"+1y");
}

sub login_screen() {
		$box = param('mailbox');
		if($box) {
			local $vmpass = &getcookie('vmpass');   #check for password in cookie
		}
		print header;
		my ($message) = @_;
		print <<_EOH;

<HEAD><LINK HREF="$astpath/vmail.css" REL="stylesheet" TYPE="text/css"><TITLE>Asterisk Web-Voicemail</TITLE></HEAD>
<BODY>
$stdcontainerstart
<FORM METHOD="post">
<input type=hidden name="action" value="login">
<table align=center>
<tr><td valign=top align=center rowspan=6><img align=center src="$astpath/animlogo.gif"></td></tr>
<tr><td align=center colspan=2><span class="headline">Comedian Mail Login</span></td></tr>
<tr><td align=center colspan=2><span class="notice">$message</span></td></tr>
<tr><td>Mailbox:</td><td><input type=text name="mailbox" value="$box"></td></tr>
<tr><td>Password:</td><td><input type=password name="password" value="$vmpass"></td></tr>
<tr><td>&nbsp;</td><td><input type=checkbox name="remember" value="yes"> Remember password</td></tr>
<tr><td align=right colspan=2><input value="Login" type=submit></td></tr>
</table>
</FORM>
$stdcontainerend
</BODY>\n
_EOH

}

sub untaint() 
{
	my($data) = @_;
	
	if ($data =~ /^([-\@\w.]+)$/) {
		$data = $1;
	} else {
		die "Security violation.";
	}

	return $data;
}

sub check_login()
{
	local ($filename, $startcat) = @_;
	local ($mbox, $context) = split(/\@/, param('mailbox'));
	local $vmpass = &getcookie('vmpass');   #check for password in cookie
	local $pass = param('password');
	if (!$pass) {							#use cookie password if not in request
		$pass=$vmpass;
	}

	local $category = $startcat;
	local @fields;
	local $tmp;
	local (*VMAIL);
	if (!$category) {
		$category = "general";
	}
	if (!$context) {
		$context = param('context');
	}
	if (!$context) {
		$context = "default";
	}
	if (!$filename) {
		$filename = "/etc/asterisk/voicemail.conf";
	}

#	print header;
#	print "Including <h2>$filename</h2> while in <h2>$category</h2>...\n";
	open(VMAIL, "<$filename") || die("Bleh, no $filename");
	while(<VMAIL>) {
		chomp;
		if (/include\s\"([^\"]+)\"$/) {
			($tmp, $category) = &check_login("/etc/asterisk/$1", $category);
			if (length($tmp)) {
#				print "Got '$tmp'\n";
				return ($tmp, $category);
			}
		} elsif (/\[(.*)\]/) {
			$category = $1;
		} elsif ($category ne "general") {
			if (/([^\s]+)\s*\=\>?\s*(.*)/) {
				@fields = split(/\,\s*/, $2);
#				print "<p>Mailbox is $1\n";
				if (($mbox eq $1) && ($pass eq $fields[0]) && ($context eq $category)) {
					return ($fields[1] ? $fields[1] : "Extension $mbox in $context", $category);
				}
			}
		}
	}
	close(VMAIL);
	return ("", $category);
}

sub validmailbox()
{
	local ($context, $mbox, $filename, $startcat) = @_;
	local $category = $startcat;
	local @fields;
	local (*VMAIL);
	if (!$context) {
		$context = param('context');
	}
	if (!$context) {
		$context = "default";
	}
	if (!$filename) {
		$filename = "/etc/asterisk/voicemail.conf";
	}
	if (!$category) {
		$category = "general";
	}
	open(VMAIL, "<$filename") || die("Bleh, no $filename");
	while(<VMAIL>) {
		chomp;
		if (/include\s\"([^\"]+)\"$/) {
			($tmp, $category) = &validmailbox($mbox, $context, "/etc/asterisk/$1");
			if ($tmp) {
				return ($tmp, $category);
			}
		} elsif (/\[(.*)\]/) {
			$category = $1;
		} elsif (($category ne "general") && ($category eq $context)) {
			if (/([^\s]+)\s*\=\>?\s*(.*)/) {
				@fields = split(/\,\s*/, $2);
				if (($mbox eq $1) && ($context eq $category)) {
					return ($fields[2] ? $fields[2] : "unknown", $category);
				}
			}
		}
	}
	return ("", $category);
}

sub mailbox_options()
{
	local($context, $current, $filename, $category) = @_;
	local (*VMAIL);
	local $tmp2;
	local $tmp;
	if (!$filename) {
		$filename = "/etc/asterisk/voicemail.conf";
	}
	if (!$category) {
		$category = "general";
	}
#	print header;
#	print "Including <h2>$filename</h2> while in <h2>$category</h2>...\n";
	open(VMAIL, "<$filename") || die("Bleh, no voicemail.conf");
	while(<VMAIL>) {
		chomp;
		s/\;.*$//;
		if (/include\s\"([^\"]+)\"$/) {
			($tmp2, $category) = &mailbox_options($context, $current, "/etc/asterisk/$1", $category);
#			print "Got '$tmp2'...\n";
			$tmp .= $tmp2;
		} elsif (/\[(.*)\]/) {
			$category = $1;
		} elsif ($category ne "general") {
			if (/([^\s]+)\s*\=\>?\s*(.*)/) {
				@fields = split(/\,\s*/, $2);
				$text = "$1";
				if ($fields[2]) {
					$text .= " ($fields[1])";
				}
				if ($1 eq $current) {
					$tmp .= "<OPTION SELECTED>$text</OPTION>\n";
				} else {
					$tmp .= "<OPTION>$text</OPTION>\n";
				}
				
			}
		}
	}
	close(VMAIL);
	return ($tmp, $category);
}

sub mailbox_list()
{
	local ($name, $context, $current) = @_;
	local $tmp;
	local $text;
	local $tmp;
	local $opts;
	if (!$context) {
		$context = "default";
	}
	$tmp = "<SELECT name=\"$name\">\n";
	($opts) = &mailbox_options($context, $current);
	$tmp .= $opts;
	$tmp .= "</SELECT>\n";
	
}

sub msgcount() 
{
	my ($context, $mailbox, $folder) = @_;
	my $path = "/var/spool/asterisk/voicemail/$context/$mailbox/$folder";
	if (opendir(DIR, $path)) {
		my @msgs = grep(/^msg....\.txt$/, readdir(DIR));
		closedir(DIR);
		return sprintf "%d", $#msgs + 1;
	}
	return "0";
}

sub msgcountstr()
{
	my ($context, $mailbox, $folder) = @_;
	my $count = &msgcount($context, $mailbox, $folder);
	if ($count > 1) {
		"$count messages";
	} elsif ($count > 0) {
		"$count message";
	} else {
		"no messages";
	}
}
sub messages()
{
	my ($context, $mailbox, $folder) = @_;
	my $path = "/var/spool/asterisk/voicemail/$context/$mailbox/$folder";
	if (opendir(DIR, $path)) {
		my @msgs = sort grep(/^msg....\.txt$/, readdir(DIR));
		closedir(DIR);
		return map { s/^msg(....)\.txt$/$1/; $_ } @msgs;
	}
	return ();
}

sub getfields()
{
	my ($context, $mailbox, $folder, $msg) = @_;
	my $fields;
	if (open(MSG, "</var/spool/asterisk/voicemail/$context/$mailbox/$folder/msg${msg}.txt")) {
		while(<MSG>) {
			s/\#.*$//g;
			if (/^(\w+)\s*\=\s*(.*)$/) {
				$fields->{$1} = $2;
			}
		}
		close(MSG);
		$fields->{'msgid'} = $msg;
	} else { print "<BR>The message you have requested has been moved or deleted.<br><br><b><a href=vmail.cgi?action=login&mailbox=$mailbox>Click to view voicemail inbox.</a></b>"; }
	$fields;
}

sub message_prefs()
{
	my ($nextaction, $msgid) = @_;
	my $folder = param('folder');
	my $mbox = param('mailbox');
	my $context = param('context');
	my $passwd = param('password');
	my $format = param('format');
	if (!$format) {
		$format = &getcookie('format');
	}
	print header;
	print <<_EOH;

<HEAD><LINK HREF="$astpath/vmail.css" REL="stylesheet" TYPE="text/css"><TITLE>Asterisk Web-Voicemail: Preferences</TITLE></HEAD>
<BODY>
$stdcontainerstart
<FORM METHOD="post">
<table width=100% align=center>
<tr><td align=right colspan=3><span class="headline">Web Voicemail Preferences</span></td></tr>
<tr><td align=left><span class="subheadline">Preferred&nbsp;Audio&nbsp;Format:</span></td><td colspan=2></td></tr>
_EOH

foreach $fmt (sort { $formats{$a}->{'pref'} <=> $formats{$b}->{'pref'} } keys %formats) {
	my $clicked = "checked" if $fmt eq $format;
	print "<tr><td></td><td align=left><input type=radio name=\"format\" $clicked value=\"$fmt\"></td><td width=100%>&nbsp;$formats{$fmt}->{name}</td></tr>\n";
}

print <<_EOH;
<tr><td align=right colspan=3><input type=submit value="save settings..."></td></tr>
</table>
<input type=hidden name="action" value="$nextaction">
<input type=hidden name="folder" value="$folder">
<input type=hidden name="mailbox" value="$mbox">
<input type=hidden name="context" value="$context">
<input type=hidden name="password" value="$passwd">
<input type=hidden name="msgid" value="$msgid">
$stdcontainerend
</BODY>\n
_EOH

}

sub message_play()
{
	my ($message, $msgid) = @_;
	my $folder = param('folder');
	if (!$folder) {						#default to INBOX folder if not specified
		$folder="INBOX";
	}
	my ($mbox, $context) = split(/\@/, param('mailbox'));
	my $passwd = param('password');
	my $format = param('format');
	
	my $fields;
	if (!$context) {
		$context = param('context');
	}
	if (!$context) {
		$context = "default";
	}
	
	my $folders = &folder_list('newfolder', $context, $mbox, $folder);
	my $mailboxes = &mailbox_list('forwardto', $context, $mbox);
	if (!$format) {
		$format = &getcookie('format');
	}
	if (!$format) {
		&message_prefs("play", $msgid);
	} else {
		print header(-cookie => &makecookie($format));
		$fields = &getfields($context, $mbox, $folder, $msgid);
		if (!$fields) {
			print "\n";
			return;
		}
		my $duration = $fields->{'duration'};
		if ($duration) {
			$duration = sprintf "%d:%02d", $duration/60, $duration % 60; 
		} else {
			$duration = "<i>Unknown</i>";
		}
		print <<_EOH;
	
<HEAD><LINK HREF="$astpath/vmail.css" REL="stylesheet" TYPE="text/css"><TITLE>Asterisk Web-Voicemail: $folder Message $msgid</TITLE></HEAD>
<BODY>
$stdcontainerstart
<FORM METHOD="post">
<table width=100% align=center>
<tr><td align=right colspan=3><span style="headline">$folder Message $msgid</span></td></tr>
_EOH

		print <<_EOH;
<tr><td align=center colspan=3>
<table>
	<tr><td colspan=2 align=center><span style="subheadline">$folder <b>$msgid</b></span></td></tr>
	<tr><td class="name">Message:</td><td class="value">$msgid</td></tr>\n
	<tr><td class="name">Mailbox:</td><td class="value">$mbox</td></tr>\n
	<tr><td class="name">Folder:</td><td class="value">$folder</td></tr>\n
	<tr><td class="name">From:</td><td class="value">$fields->{callerid}</td></tr>\n
	<tr><td class="name">Duration:</td><td class="value">$duration</td></tr>\n
	<tr><td class="name">Original Date:</td><td class="value">$fields->{origdate}</td></tr>\n
	<tr><td class="name">Original Mailbox:</td><td class="value">$fields->{origmailbox}</td></tr>\n
	<tr><td class="name">Caller Channel:</td><td class="value">$fields->{callerchan}</td></tr>\n
	<tr><td align=center colspan=2>
	<input name="action" type=submit value="index">&nbsp;
	<input name="action" type=submit value="delete ">&nbsp;
	<input name="action" type=submit value="forward to -> ">&nbsp;
	$mailboxes&nbsp;
	<input name="action" type=submit value="save to ->">
	$folders&nbsp;
	<input name="action" type=submit value="play ">
	<input name="action" type=submit value="download">
</td></tr>
<tr><td colspan=2 align=center>
<embed width=400 height=40 src="vmail.cgi?action=audio&folder=$folder&mailbox=$mbox&context=$context&password=$passwd&msgid=$msgid&format=$format&dontcasheme=$$.$format" autostart=yes loop=false></embed>
</td></tr></table>
</td></tr>
</table>
<input type=hidden name="folder" value="$folder">
<input type=hidden name="mailbox" value="$mbox">
<input type=hidden name="context" value="$context">
<input type=hidden name="password" value="$passwd">
<input type=hidden name="msgid" value="$msgid">
$stdcontainerend
</BODY>\n
_EOH
	}
}

sub message_audio()
{
	my ($forcedownload) = @_;
	my $folder = &untaint(param('folder'));
	my $msgid = &untaint(param('msgid'));
	my $mailbox = &untaint(param('mailbox'));
	my $context = &untaint(param('context'));
	my $format = param('format');
	if (!$format) {
		$format = &getcookie('format');
	}
	&untaint($format);
	my $path = "/var/spool/asterisk/voicemail/$context/$mailbox/$folder/msg${msgid}.$format";

	$msgid =~ /^\d\d\d\d$/ || die("Msgid Liar ($msgid)!");
	grep(/^${format}$/, keys %formats) || die("Format Liar ($format)!");

	# Mailbox and folder are already verified
	if (open(AUDIO, "<$path")) {
		$size = -s $path;
		$|=1;
		if ($forcedownload) {
			print header(-type=>$formats{$format}->{'mime'}, -Content_length => $size, -attachment => "msg${msgid}.$format");
		} else {		
			print header(-type=>$formats{$format}->{'mime'}, -Content_length => $size);
		}
		
		while(($amt = sysread(AUDIO, $data, 4096)) > 0) {
			syswrite(STDOUT, $data, $amt);
		}
		close(AUDIO);
	} else {
		die("Hrm, can't seem to open $path\n");
	}
}

sub message_index() 
{
	my ($folder, $message) = @_;
	my ($mbox, $context) = split(/\@/, param('mailbox'));
	my $passwd = param('password');
	my $message2;
	my $msgcount;	
	my $hasmsg;
	my $newmessages, $oldmessages;
	my $format = param('format');
	if (!$format) {
		$format = &getcookie('format');
	}
	if (!$context) {
		$context = param('context');
	}
	if (!$context) {
		$context = "default";
	}
	if ($folder) {
		$msgcount = &msgcountstr($context, $mbox, $folder);
		$message2 = "&nbsp;&nbsp;&nbsp;Folder '$folder' has " . &msgcountstr($context, $mbox, $folder);
	} else {
		$newmessages = &msgcount($context, $mbox, "INBOX");
		$oldmessages = &msgcount($context, $mbox, "Old");
		if (($newmessages > 0) || ($oldmessages < 1)) {
			$folder = "INBOX";
		} else {
			$folder = "Old";
		}
		$message2 = "You have";
		if ($newmessages > 0) {
			$message2 .= " <b>$newmessages</b> NEW ";
			if ($oldmessages > 0) {
				$message2 .= "and <b>$oldmessages</b> OLD ";
				if ($oldmessages != 1) {
					$message2 .= " messages.";
				} else {
					$message2 .= "message.";
				}
			} else {
				if ($newmessages != 1) {
					$message2 .= " messages.";
				} else {
					$message2 .= " message.";
				}
			}
		} else {
			if ($oldmessages > 0) {
				$message2 .= " <b>$oldmessages</b> OLD";
				if ($oldmessages != 1) {
					$message2 .= " messages.";
				} else {
					$message2 .= " message.";
				}
			} else {
				$message2 .= " <b>no</b> messages.";
			}
		}
	}
	
	my $folders = &folder_list('newfolder', $context, $mbox, $folder);
	my $cfolders = &folder_list('changefolder', $context, $mbox, $folder);
	my $mailboxes = &mailbox_list('forwardto', $context, $mbox);

	#if we selected to store the password, then write a cookie
	local $remember = param('remember');
	if ($remember) {
		local $pass = param('password');
		print header(-cookie => &makepasscookie($pass));
	} else {
		print header(-cookie => &makecookie($format));;
	}
	print <<_EOH;

<HEAD><LINK HREF="$astpath/vmail.css" REL="stylesheet" TYPE="text/css"><TITLE>Asterisk Web-Voicemail: $mbox $folder</TITLE></HEAD>
<BODY>
$stdcontainerstart
<FORM METHOD="post">
<table width=100% align=center>
<tr><td align=center colspan=2><span class="notice">$message</span></td></tr>
<tr><td align=right colspan=2><span class="subheadline"><b>$folder</b> Messages</span> <input type=submit name="action" value="change to ->">$cfolders</td></tr>
<tr><td align=left colspan=2><span class="subheadline">$message2</span></td></tr>
</table>
<table width=100% align=center cellpadding=0 cellspacing=0>
_EOH

print "<tr><td class=\"colTitle\">&nbsp;Msg</td><td class=\"colTitle\">&nbsp;From</td><td class=\"colTitle\">Duration</td><td class=\"colTitle\">Date</td><td class=\"colTitle\">&nbsp;</td></tr>\n";
print "<tr><td><hr></td><td><hr></td><td><hr></td><td><hr></td><td></td></tr>\n";
foreach $msg (&messages($context, $mbox, $folder)) {

	$fields = &getfields($context, $mbox, $folder, $msg);
	$duration = $fields->{'duration'};
	if ($duration) {
		$duration = sprintf "%d:%02d", $duration / 60, $duration % 60;
	} else {
		$duration = "<i>Unknown</i>";
	}
	$hasmsg++;
	print "<tr><td><input type=checkbox name=\"msgselect\" value=\"$msg\">&nbsp;<b>$msg</b></td><td>$fields->{'callerid'}</td><td>$duration</td><td>$fields->{'origdate'}</td><td><input name='play$msg' alt=\"Play message $msg\" border=0 type=image align=left src=\"$astpath/play.gif\"></td></tr>\n";

}
if (!$hasmsg) {
	print "<tr><td colspan=4 align=center><P><span class=\"notice\">No messages</span><P></td></tr>";
}

print <<_EOH;
</table>
<table width=100% align=center>
<tr><td align=right colspan=2>
	<input type="submit" name="action" value="refresh">&nbsp;
_EOH

if ($hasmsg) {
print <<_EOH;
	<input type="submit" name="action" value="delete">&nbsp;
	<input type="submit" name="action" value="save to ->">
	$folders&nbsp;
	<input type="submit" name="action" value="forward to ->">
	$mailboxes
_EOH
}

print <<_EOH;
</td></tr>
<tr><td align=right colspan=2>
	<input type="submit" name="action" value="preferences">
	<input type="submit" name="action" value="logout">
</td></tr>
</table>
<input type=hidden name="folder" value="$folder">
<input type=hidden name="mailbox" value="$mbox">
<input type=hidden name="context" value="$context">
<input type=hidden name="password" value="$passwd">
</FORM>
$stdcontainerend
</BODY>\n
_EOH
}

sub validfolder()
{
	my ($folder) = @_;
	return grep(/^$folder$/, @validfolders);
}

sub folder_list()
{
	my ($name, $context, $mbox, $selected) = @_;
	my $f;
	my $count;
	my $tmp = "<SELECT name=\"$name\">\n";
	foreach $f (@validfolders) {
		$count =  &msgcount($context, $mbox, $f);
		if ($f eq $selected) {
			$tmp .= "<OPTION SELECTED>$f ($count)</OPTION>\n";
		} else {
			$tmp .= "<OPTION>$f ($count)</OPTION>\n";
		}
	}
	$tmp .= "</SELECT>";
}

sub message_rename()
{
	my ($context, $mbox, $oldfolder, $old, $newfolder, $new) = @_;
	my $oldfile, $newfile;
	return if ($old eq $new) && ($oldfolder eq $newfolder);

        if ($context =~ /^(\w+)$/) {
                $context = $1;
        } else {
                die("Invalid Context<BR>\n");
        }
	
	if ($mbox =~ /^(\w+)$/) {
		$mbox = $1;
	} else {
		die ("Invalid mailbox<BR>\n");
	}
	
	if ($oldfolder =~ /^(\w+)$/) {
		$oldfolder = $1;
	} else {
		die("Invalid old folder<BR>\n");
	}
	
	if ($newfolder =~ /^(\w+)$/) {
		$newfolder = $1;
	} else {
		die("Invalid new folder ($newfolder)<BR>\n");
	}
	
	if ($old =~ /^(\d\d\d\d)$/) {
		$old = $1;
	} else {
		die("Invalid old Message<BR>\n");
	}
	
	if ($new =~ /^(\d\d\d\d)$/) {
		$new = $1;
	} else {
		die("Invalid old Message<BR>\n");
	}
	
	my $path = "/var/spool/asterisk/voicemail/$context/$mbox/$newfolder";
	if (! -d $path) { 
		mkdir $path, 0755;
	}
	my $path = "/var/spool/asterisk/voicemail/$context/$mbox/$oldfolder";
	opendir(DIR, $path) || die("Unable to open directory\n");
	my @files = grep /^msg${old}\.\w+$/, readdir(DIR);
	closedir(DIR);
	foreach $oldfile (@files) {
		my $tmp = $oldfile;
		if ($tmp =~ /^(msg${old}.\w+)$/) {
			$tmp = $1;
			$oldfile = $path . "/$tmp";
			$tmp =~ s/msg${old}/msg${new}/;
			$newfile = "/var/spool/asterisk/voicemail/$context/$mbox/$newfolder/$tmp";
#			print "Renaming $oldfile to $newfile<BR>\n";
			rename($oldfile, $newfile);
		}
	}
}

sub file_copy()
{
	my ($orig, $new) = @_;
	my $res;
	my $data;
	open(IN, "<$orig") || die("Unable to open '$orig'\n");
	open(OUT, ">$new") || die("Unable to open '$new'\n");
	while(($res = sysread(IN, $data, 4096)) > 0) {
		syswrite(OUT, $data, $res);
	}
	close(OUT);
	close(IN);
}

sub message_copy()
{
	my ($mbox, $newmbox, $oldfolder, $old, $new) = @_;
	my $oldfile, $newfile;
	
	my $context = param('context');
	
	return if ($mbox eq $newmbox);
	
	if ($mbox =~ /^(\w+)$/) {
		$mbox = $1;
	} else {
		die ("Invalid mailbox<BR>\n");
	}

	if ($newmbox =~ /^(\w+)$/) {
		$newmbox = $1;
	} else {
		die ("Invalid new mailbox<BR>\n");
	}
	
	if ($oldfolder =~ /^(\w+)$/) {
		$oldfolder = $1;
	} else {
		die("Invalid old folder<BR>\n");
	}
	
	if ($old =~ /^(\d\d\d\d)$/) {
		$old = $1;
	} else {
		die("Invalid old Message<BR>\n");
	}
	
	if ($new =~ /^(\d\d\d\d)$/) {
		$new = $1;
	} else {
		die("Invalid old Message<BR>\n");
	}
	
	my $path = "/var/spool/asterisk/voicemail/$context/$newmbox";
	if (! -d $path) { 
	    mkdir $path, 0755;
	}
	my $path = "/var/spool/asterisk/voicemail/$context/$newmbox/INBOX";
	if (! -d $path) { 
	    mkdir $path, 0755;
	}
	my $path = "/var/spool/asterisk/voicemail/$context/$mbox/$oldfolder";
	opendir(DIR, $path) || die("Unable to open directory: $path\n");
	my @files = grep /^msg${old}\.\w+$/, readdir(DIR);
	closedir(DIR);
	foreach $oldfile (@files) {
		my $tmp = $oldfile;
		if ($tmp =~ /^(msg${old}.\w+)$/) {
			$tmp = $1;
			$oldfile = $path . "/$tmp";
			$tmp =~ s/msg${old}/msg${new}/;
			$context =~ /(\w+)/;
			$context = $1;
			$newmbox =~ /(\w+)/;
			$newmbox = $1;
			$newfile = "/var/spool/asterisk/voicemail/$context/$newmbox/INBOX/$tmp";
#			print "Copying $oldfile to $newfile<BR>\n";
			&file_copy($oldfile, $newfile);
		}
	}
}

sub message_delete()
{
	my ($context, $mbox, $folder, $msg) = @_;
	if ($mbox =~ /^(\w+)$/) {
		$mbox = $1;
	} else {
		die ("Invalid mailbox<BR>\n");
	}
	if ($context =~ /^(\w+)$/) {
		$context = $1;
	} else {
		die ("Invalid context<BR>\n");
	}
	if ($folder =~ /^(\w+)$/) {
		$folder = $1;
	} else {
		die("Invalid folder<BR>\n");
	}
	if ($msg =~ /^(\d\d\d\d)$/) {
		$msg = $1;
	} else {
		die("Invalid Message<BR>\n");
	}
	my $path = "/var/spool/asterisk/voicemail/$context/$mbox/$folder";
	opendir(DIR, $path) || die("Unable to open directory\n");
	my @files = grep /^msg${msg}\.\w+$/, readdir(DIR);
	closedir(DIR);
	foreach $oldfile (@files) {
		if ($oldfile =~ /^(msg${msg}.\w+)$/) {
			$oldfile = $path . "/$1";
#			print "Deleting $oldfile<BR>\n";
			unlink($oldfile);
		}
	}
}

sub message_forward()
{
	my ($toindex, @msgs) = @_;
	my $folder = param('folder');
	my ($mbox, $context) = split(/\@/, param('mailbox'));
	my $newmbox = param('forwardto');
	my $msg;
	my $msgcount;
	if (!$context) {
		$context = param('context');
	}
	if (!$context) {
		$context = "default";
	}
	$newmbox =~ s/(\w+)(\s+.*)?$/$1/;
	if (!&validmailbox($context, $newmbox)) {
		die("Bah! Not a valid mailbox '$newmbox'\n");
		return "";
	}
	$msgcount = &msgcount($context, $newmbox, "INBOX");
	my $txt;
	$context = &untaint($context);
	$newmbox = &untaint($newmbox);
	if ($newmbox ne $mbox) {
#		print header;
		foreach $msg (@msgs) {
#			print "Forwarding $msg from $mbox to $newmbox<BR>\n";
			&message_copy($mbox, $newmbox, $folder, $msg, sprintf "%04d", $msgcount);
			$msgcount++;
		}
		$txt = "Forwarded messages " . join(', ', @msgs) . "to $newmbox";
	} else {
		$txt = "Can't forward messages to yourself!\n";
	} 
	if ($toindex) {
		&message_index($folder, $txt);
	} else {
		&message_play($txt, $msgs[0]);
	}
}

sub message_delete_or_move()
{
	my ($toindex, $del, @msgs) = @_;
	my $txt;
	my $path;
	my $y, $x;
	my $folder = param('folder');
	my $newfolder = param('newfolder') unless $del;
	$newfolder =~ s/^(\w+)\s+.*$/$1/;
	my ($mbox, $context) = split(/\@/, param('mailbox'));
	if (!$context) {
		$context = param('context');
	}
	if (!$context) {
		$context = "default";
	}
	my $passwd = param('password');
	$context = &untaint($context);
	$mbox = &untaint($mbox);
	$folder = &untaint($folder);
	my $msgcount = &msgcount($context, $mbox, $folder);
	my $omsgcount = &msgcount($context, $mbox, $newfolder) if $newfolder;
#	print header;
	if ($newfolder ne $folder) {
		$y = 0;
		for ($x=0;$x<$msgcount;$x++) {
			my $msg = sprintf "%04d", $x;
			my $newmsg = sprintf "%04d", $y;
			if (grep(/^$msg$/, @msgs)) {
				if ($newfolder) {
					&message_rename($context, $mbox, $folder, $msg, $newfolder, sprintf "%04d", $omsgcount);
					$omsgcount++;
				} else {
					&message_delete($context, $mbox, $folder, $msg);
				}
			} else {
				&message_rename($context, $mbox, $folder, $msg, $folder, $newmsg);
				$y++;
			}
		}
		if ($del) {
			$txt = "Deleted messages "  . join (', ', @msgs);
		} else {
			$txt = "Moved messages "  . join (', ', @msgs) . " to $newfolder";
		}
	} else {
		$txt = "Can't move a message to the same folder they're in already";
	}
	# Not as many messages now
	$msgcount--;
	if ($toindex || ($msgs[0] >= $msgcount)) {
		&message_index($folder, $txt);	
	} else {
		&message_play($txt, $msgs[0]);
	}
}

if (param()) {
	my $folder = param('folder');
	my $changefolder = param('changefolder');
	$changefolder =~ s/(\w+)\s+.*$/$1/;
	
	my $newfolder = param('newfolder');
	$newfolder =~ s/^(\w+)\s+.*$/$1/;
	if ($newfolder && !&validfolder($newfolder)) {
		print header;
		die("Bah! new folder '$newfolder' isn't a folder.");
	}
	$action = param('action');
	$msgid = param('msgid');
	if (length($msgid) != 4) {					#if we are passing msgid via url, it will be less than 4 digits
		$msgid = 10000 + $msgid - 1;			#the count will also be 1 too high
		$msgid = substr($msgid,1,4);
	}
	if (!$action) {
		my ($tmp) = grep /^play\d\d\d\d\.x$/, param;
		if ($tmp =~ /^play(\d\d\d\d)/) {
			$msgid = $1;
			$action = "play";
		} else {
			print header;
			print "No message?<BR>\n";
			return;
		}
	}
	@msgs = param('msgselect');
	@msgs = ($msgid) unless @msgs;
	{
		($mailbox) = &check_login();
		if (length($mailbox)) {
			if ($action eq 'login') {
				&message_index($folder, "Welcome, $mailbox");
			} elsif (($action eq 'refresh') || ($action eq 'index')) {
				&message_index($folder, "Welcome, $mailbox");
			} elsif ($action eq 'change to ->') {
				if (&validfolder($changefolder)) {
					$folder = $changefolder;
					&message_index($folder, "Welcome, $mailbox");
				} else {
					die("Bah!  Not a valid change to folder '$changefolder'\n");
				}
			} elsif ($action eq 'play') {
				&message_play("$mailbox $folder $msgid", $msgid);
			} elsif ($action eq 'preferences') {
				&message_prefs("refresh", $msgid);
			} elsif ($action eq 'download') {
				&message_audio(1);
			} elsif ($action eq 'play ') {
				&message_audio(0);
			} elsif ($action eq 'audio') {
				&message_audio(0);
			} elsif ($action eq 'delete') {
				&message_delete_or_move(1, 1, @msgs);
			} elsif ($action eq 'delete ') {
				&message_delete_or_move(0, 1, @msgs);
			} elsif ($action eq 'forward to ->') {
				&message_forward(1, @msgs);
			} elsif ($action eq 'forward to -> ') {
				&message_forward(0, @msgs);
			} elsif ($action eq 'save to ->') {
				&message_delete_or_move(1, 0, @msgs);
			} elsif ($action eq 'save to -> ') {
				&message_delete_or_move(0, 0, @msgs);
			} elsif ($action eq 'logout') {
				&login_screen("Logged out!\n");
			}
		} else {
			sleep(1);
			&login_screen("Login Incorrect!\n");
		}
	}
} else {
	&login_screen("\&nbsp;");
}