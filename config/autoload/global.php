<?php
$api_version = 'ltb-v2';//Not used at the moment
$api_scripts_version = '0.7.2';//The version of the software (should change on every release)
//$api_db_version = '0.7.2.0';//first version numbers linked to major of software version. 
//Every update gets its own revision number. So we will have 0.7.2.0 to 0.7.2.12 
//for the 12 updates that are performed to get from software version 0.7.1 to 0.7.2
include "instance.php";
include('derived.settings.php');
return ($derived_settings);
