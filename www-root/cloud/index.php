<?php
define('IPS_Token', $_SERVER['REMOTE_ADDR']."#".date("Y.m.d.H.i.s")."#". 
		sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',mt_rand(0, 0xffff), mt_rand(0, 0xffff),mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    ));
include __DIR__ . '/config.php';
include $work_dir . '/bin/main.php';
