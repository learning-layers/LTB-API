#!/bin/bash
echo "Setting now ltb specific settings, getting the libraries (vendor) ready and populating our database"
if [ $# -lt 7 ]; then
    echo "you are sending not enough arguments";
    echo "Usage: sh install.sh <LTB_MYSQL_HOST> <LTB_MYSQL_DB> <LTB_MYSQL_USER> <LTB_MYSQL_PASSWORD> <LTB_API_URI> <LTB_TS_URI> <LTB_HOME_DIR>"
    exit;
fi
LTB_HOME_DIR="$7";
CURRENT_DIR="$8";
LTB_UNIX_LOG="${LTB_HOME_DIR}/data/ltb_io_debug.log";
# Go to the installer directory
cd $LTB_HOME_DIR;
sudo chown :www-data data && \
sudo chmod g+w data && \
cp -f config/autoload/instance.php.dist config/autoload/instance.php && \
sed -i "s#LTB_MYSQL_HOST#$1#g" config/autoload/instance.php && \
sed -i "s#LTB_MYSQL_DB#$2#g" config/autoload/instance.php && \
sed -i "s#LTB_MYSQL_USER#$3#g" config/autoload/instance.php && \
sed -i "s#LTB_MYSQL_PASSWORD#$4#g" config/autoload/instance.php && \
sed -i "s#LTB_API_URI#$5#g" config/autoload/instance.php && \
sed -i "s#LTB_TS_URI#$6#g" config/autoload/instance.php && \
sed -i "s#LTB_HOME_DIR#$7#g" config/autoload/instance.php && \
sed -i "s#LTB_UNIX_LOG#$LTB_UNIX_LOG#g" config/autoload/instance.php && \
# sed -i "s#OIDC_URI#$OIDC_URI#g" config/autoload/instance.php && \
#     sed -i "s#OIDC_CLIENT#$OIDC_CLIENT#g" config/autoload/instance.php && \
#     sed -i "s#OIDC_SECRET#$OIDC_SECRET#g" config/autoload/instance.php && \
sed -i "s#SSS_URI#http://test-ll.know-center.tugraz.at/ltb-v2/#g" config/autoload/instance.php && \
sed -i "s#LTB_HOME_DIR#$7#g" /etc/apache2/apache2.conf && \

# ($LTB_VENDOR_AVAILABLE && mv /tmp/vendor /home/LTB-API) || (echo "Starting download of vendor software" && cd /home/LTB-API && php composer.phar install) && \
# Determine whether in this docker context a vendor directory is made available
 if [[ -d ${LTB_HOME_DIR}/vendor ]] && [[ -f "${LTB_HOME_DIR}/vendor.autoload.php" ]]; then
    echo "Vendor files are already there! Will be copied into container";
    LTB_VENDOR_AVAILABLE=1;
   #  cp -rp ${CURRENT_DIR}/vendor .;
else
    
    echo "Vendor files are not there yet...";

    if [ ! -d ~/.ssh ]; then
        mkdir ~/.ssh;
    fi
    if [ ! -f ~/.ssh/config ]; then
        touch ~/.ssh/config;
    fi
    echo -e "Host github.com\n\tStrictHostKeyChecking no\n" >> ~/.ssh/config;
    echo "Starting download of vendor software";
    php composer.phar install;
    echo "Finished installing libraries";
    LTB_VENDOR_AVAILABLE=0;
fi &&
echo "Installing the tables now of the LTB-API" && \
mysql -u $3 --password=$4 -h $1 $2 < data/ltb-api-db.sql && \
echo "Finished installing the LTB-API";