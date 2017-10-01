<?php

$updates = array(
   '0.7.2' => array(
       1 => "CREATE TABLE `version` (`db_version` TINYTEXT NOT NULL)",
       2 => "INSERT INTO version VALUE ('0.7.1')",
       3 => "ALTER TABLE `reference`
            CHANGE COLUMN `url` `url` VARCHAR(250) NULL DEFAULT NULL AFTER `ref_type`,
            ADD COLUMN `external_url` VARCHAR(250) NULL DEFAULT NULL AFTER `url`,
            ADD COLUMN `internal_url` VARCHAR(250) NULL DEFAULT NULL AFTER `external_url`",
       4 => "UPDATE reference SET internal_url = url where ref_type = 'file'",
       5 => "UPDATE reference SET external_url = url where ref_type = 'link'",
       6 => "UPDATE version SET db_version = 0.7.2"
    ),
       
   '0.7.3' => array()
    
);

