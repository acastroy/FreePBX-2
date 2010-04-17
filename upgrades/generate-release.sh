#!/bin/bash 
# use this script like:
# md5-amp_conf.sh 1.10.00X

case "$1" in
	?*)

ver=$1
cd ..
svn update
cd amp_conf
find agi-bin  astetc  bin htdocs  htdocs_panel  mohmp3  sbin sounds -type f \! -name 'vm_email.inc' \! -name 'defines.php' \! -name 'op_server.cfg' \! -name 'dialparties.agi' \! -name 'manager.conf' \! -name '*.pl' \! -name 'cdr_mysql.conf' \! -name 'voicemail.conf' | xargs md5sum | grep -v .svn > ../upgrades/$ver.md5

	;;
	*)

echo "usage: md5-amp_conf <version>";
exit

	;;
esac
cd ../upgrades
svn add $ver.md5
svn ps svn:mime-type text/plain $ver.md5
svn ps svn:eol-style native $ver.md5
svn ci -m "Creating release $ver"
cd ..
cur=`svn info | grep URL | awk ' { print $2 }'`
svn cp -m "Automatic tag of $ver" $cur https://amportal.svn.sourceforge.net/svnroot/amportal/freepbx/tags/$ver
mkdir -p /usr/src/freepbx-release
rm -rf /usr/src/freepbx-release/freepbx-$ver
svn export $cur /usr/src/freepbx-release/freepbx-$ver
cd /usr/src/freepbx-release
tar zcvf freepbx-$ver.tar.gz freepbx-$ver
cd freepbx-$ver/amp_conf/htdocs/admin/modules/
. ./import.sh
find . -name .svn -exec rm -rf {} \;
cd /usr/src/freepbx-release
tar zcvf freepbx-$ver-withmodules.tar.gz freepbx-$ver

