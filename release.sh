#!/bin/sh

#svn status

echo "must be commited or cancel now?"
read

if [ -x $1 ] 
then
    echo "Need a version number"
    exit 1
fi


cd ..
cp -r dtgraph dtgraph-$1
tar cvfz  dtgraph-$1.tar.gz dtgraph-$1 --exclude CVS --exclude .svn --exclude *~ --exclude release.sh --exclude '*.swp'
rm -rf dtgraph-$1

#svn copy svn://srv/svn/dtgraph/trunk svn://srv/svn/dtgraph/tags/release-$1
