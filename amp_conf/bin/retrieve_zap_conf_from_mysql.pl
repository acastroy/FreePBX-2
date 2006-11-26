#!/usr/bin/perl -w
# Retrieves the sip user/peer entries from the database
# Use these commands to create the appropriate tables in MySQL
#
#CREATE TABLE sip (id INT(11) DEFAULT -1 NOT NULL,keyword VARCHAR(20) NOT NULL,data VARCHAR(50) NOT NULL, flags INT(1) DEFAULT 0 NOT NULL,PRIMARY KEY (id,keyword));
#
# if flags = 1 then the records are not included in the output file

use FindBin;
push @INC, "$FindBin::Bin";

use DBI;
require "retrieve_parse_amportal_conf.pl";

################### BEGIN OF CONFIGURATION ####################

# the name of the extensions table
$table_name = "zap";
# the path to the extensions.conf file
# WARNING: this file will be substituted by the output of this program
$zap_conf = "/etc/asterisk/zapata_additional.conf";

# cool hack by Julien BLACHE <jblache@debian.org>
$ampconf = parse_amportal_conf( "/etc/amportal.conf" );
# username to connect to the database
$username = $ampconf->{"AMPDBUSER"};
# password to connect to the database
$password = $ampconf->{"AMPDBPASS"};
# the name of the database our tables are kept
$database = $ampconf->{"AMPDBNAME"};
# the name of the box the MySQL database is running on
$hostname = $ampconf->{"AMPDBHOST"};

# the engine to be used for the SQL queries,
# if none supplied, backfall to mysql
$db_engine = "mysql";
if (exists($ampconf->{"AMPDBENGINE"})) {
	$db_engine = $ampconf->{"AMPDBENGINE"};
}

################### END OF CONFIGURATION #######################

$warning_banner =
"; do not edit this file, this is an auto-generated file by freepbx
; all modifications must be done from the web gui
";


if ( $db_engine eq "mysql" ) {
	$dbh = DBI->connect("dbi:mysql:dbname=$database;host=$hostname", "$username", "$password");
}
elsif ( $db_engine eq "pgsql" ) {
	$dbh = DBI->connect("dbi:pgsql:dbname=$database;host=$hostname", "$username", "$password");
}
elsif ( $db_engine eq "sqlite" ) {
	if (!exists($ampconf->{"AMPDBFILE"})) {
		print "No AMPDBFILE set in /etc/amportal.conf\n";
		exit;
	}
	
	my $db_file = $ampconf->{"AMPDBFILE"};
	$dbh = DBI->connect("dbi:SQLite2:dbname=$db_file","","");
}

$statement = "SELECT keyword,data from $table_name where id=-1 and keyword <> 'account' and flags <> 1";
my $result = $dbh->selectall_arrayref($statement);
unless ($result) {
	# check for errors after every single database call
	print "dbh->selectall_arrayref($statement) failed!\n";
	print "DBI::err=[$DBI::err]\n";
	print "DBI::errstr=[$DBI::errstr]\n";
	exit;
}

open( EXTEN, ">$zap_conf" ) or die "Cannot create/overwrite extensions file: $zap_conf (!$)\n";
print EXTEN $warning_banner;

$additional = "";
my @resultSet = @{$result};
if ( $#resultSet > -1 ) {
	foreach $row (@{ $result }) {
		my @result = @{ $row };
		$additional .= $result[0]."=".$result[1]."\n";
	}
}

$statement = "SELECT data,id from $table_name where keyword='account' and flags <> 1 group by data";

$result = $dbh->selectall_arrayref($statement);
unless ($result) {
  # check for errors after every single database call
  print "dbh->selectall_arrayref($statement) failed!\n";
  print "DBI::err=[$DBI::err]\n";
  print "DBI::errstr=[$DBI::errstr]\n";
}

@resultSet = @{$result};
if ( $#resultSet == -1 ) {
  print "No zap accounts defined in $table_name\n";
  exit;
}

foreach my $row ( @{ $result } ) {
	my $account = @{ $row }[0];
	my $id = @{ $row }[1];
	print EXTEN ";;;;;;[$account]\n";
	$statement = "SELECT keyword,data from $table_name where id=$id and keyword <> 'account' and flags <> 1 order by keyword DESC";
	my $result = $dbh->selectall_arrayref($statement);
	unless ($result) {
		# check for errors after every single database call
		print "dbh->selectall_arrayref($statement) failed!\n";
		print "DBI::err=[$DBI::err]\n";
		print "DBI::errstr=[$DBI::errstr]\n";
		exit;
	}

	my @resSet = @{$result};
	if ( $#resSet == -1 ) {          
		print "no results\n";
		exit;
	}
	
	$zapchannel="";
	foreach my $row ( @{ $result } ) {
		my @result = @{ $row };
		if ($result[0] eq 'channel') {
			$zapchannel = "$result[1]";
		} else {
			print EXTEN "$result[0]=$result[1]\n";
		}
	}                                         	
	print EXTEN "channel=>$zapchannel\n";
	print EXTEN "$additional\n";
}

exit 0;


