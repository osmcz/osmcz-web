git push --force-with-lease "server" master:master

pause
exit /B

----------------------
.git/hooks/post-recieve


#!/bin/bash
WORK_TREE="/home/www/zbycz/openstreetmap.cz/www"
while read oldrev newrev ref
do
  branch=`echo $ref | cut -d/ -f3`

  if [ "master" == "$branch" ]; then
    git --work-tree=$WORK_TREE checkout -f $branch
    echo 'Changes deployed from master.'
  fi
done
cd $WORK_TREE
rm -r "$WORK_TREE/app/temp/cache"
# mysql -u dbuser --password=pass dbname < database.sql
