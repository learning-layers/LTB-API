FROM learninglayers/learningtoolbox-web
MAINTAINER Edwin Veenendaal

COPY ./apache_ltb.conf /etc/apache2/apache2.conf
COPY ./node_npm.sh /home/LTB-Tilestore
RUN git clone -b LTB-Tilestore-App https://github.com/learning-layers/LTB-Tilestore.git /home/LTB-Tilestore
# RUN wget "http://nodejs.org/dist/v0.12.2/node-v0.12.2.tar.gz" | 
# Installing node js and npm requires (at Unix): (from http://www.joyent.com/blog/installing-node-and-npm/)
# 
#install node.js. Copied from: https://github.com/joyent/node/wiki/Installing-Node.js-via-package-manager#debian-and-ubuntu-based-linux-distributions
#   GCC 4.2 or newer
#   G++ 4.2 or newer
#   Python 2.6 or 2.7
#   GNU Make 3.81 or newer
# then sh ./configure; make; make install



# RUN curl -sL https://deb.nodesource.com/setup | sudo bash -
# RUN sudo apt-get install -y nodejs
RUN sudo apt-get install -y build-essential python
WORKDIR /home/LTB-Tilestore
RUN sudo sh node_npm.sh
#Run npm install
# RUN npm install
#extract translation string to .pot file
# RUN grunt nggettext-extract
COPY ./install.sh /home/LTB-Tilestore

CMD sh /home/LTB-Tilestore/install.sh $LTB_API_URI $LTB_TS_URI $LTB_TS_HOME_DIR $OIDC_URI && \
    echo " Starting Apache now" && \
    apache2-foreground
EXPOSE 4902