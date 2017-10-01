#!/bin/bash
echo "Starting the Learning Layers Toolbox from Windows env";
dos2unix ./box_start.sh;
dos2unix ./ltb_api_start.sh;
dos2unix ./ltb_ts_start.sh;
dos2unix ./terminal_open.sh;
dos2unix ./learningtoolbox/api/*;
dos2unix ./learningtoolbox/tilestore/*;
dos2unix ./learningtoolbox/web/*;

echo "Conversion to Unix file format completed for Unix scripts. Starting up docker process...";
sleep 6;
#sh box_start.sh;

# SEE also https://github.com/learning-layers/ldetherpad-Dockerfiles.git