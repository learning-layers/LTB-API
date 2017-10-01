FROM learninglayers/httpd
MAINTAINER Edwin Veenendaal

COPY ./apache_ltb.conf /etc/apache2/apache2.conf

RUN apt-get update
RUN apt-get install -y git php5-mysql
RUN a2enmod rewrite
# RUN apt-get update && apt-get install -y git php5-mysql

# Clone the sources here

RUN git clone -b b_messages https://github.com/learning-layers/LTB-API.git /home/LTB-API
# Does not work COPY ./../../../../LTB-API $LTB_HOME_DIR # This copies the local mounted windows dir to the image

COPY ./install.sh /home/LTB-API/install.sh
COPY /home/ltb/config/autoload/instance.php /home/LTB-API/config/autoload/instance.php
# This is a tryout
# COPY ./vendor/ /home/LTB-API/vendor

# TODO: GET the openid details and create a client etc...RUN docker inspect openidconnect

# Create here the instance.php file dynamic part
CMD sh /home/LTB-API/install.sh $LTB_MYSQL_HOST $LTB_MYSQL_DB $LTB_MYSQL_USER $LTB_MYSQL_PASSWORD $LTB_API_URI $LTB_TS_URI $LTB_HOME_DIR $CURRENT_DIR && \
    apache2-foreground

EXPOSE 4901