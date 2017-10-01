#!/bin/bash
# Trying this first. Since composer is that slow. Also possible to tgz all the vendor files
if [[ -d "$1/vendor" && ! -d "learningtoolbox/api/vendor" ]]; then
    echo "Vendor files are already there! Will be copied into container later on. So we have to copy them to cwd first.";
    LTB_VENDOR_AVAILABLE=1;
    cp -rpf ${1}/vendor ./learningtoolbox/api;
else 
    echo "Vendor files not present or... already copied to docker location.";
fi