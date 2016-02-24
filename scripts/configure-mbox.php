<?php
/*
*************************************************************************
*                                                                       *
* ProSpamFilter2                                                        *
* Bridge between Webhosting panels & SpamExperts filtering		*
*                                                                       *
* Copyright (c) 2010-2011 SpamExperts B.V. All Rights Reserved,         *
*                                                                       *
*************************************************************************
*                                                                       *
* Email: support@spamexperts.com                                        *
* Website: htttp://www.spamexperts.com                                  *
*                                                                       *
*************************************************************************
*                                                                       *
* This software is furnished under a license and may be used and copied *
* only in accordance with the  terms of such license and with the       *
* inclusion of the above copyright notice. No title to and ownership    *
* of the software is  hereby  transferred.                              *
*                                                                       *
* You may not reverse engineer, decompile or disassemble this software  * 
* product or software product license.                                  *
*                                                                       *
* SpamExperts may terminate this license if you don't comply with any   *
* of the terms and conditions set forth in our end user                 *
* license agreement (EULA). In such event, licensee agrees to return    *
* licensor  or destroy  all copies of software upon termination of the  *
* license.                                                              *
*                                                                       *
* Please see the EULA file for the full End User License Agreement.     *
*                                                                       *
*************************************************************************
*/
require_once('SpamFilter/System.php');
require_once('SpamFilter/Serviceusers.php');
if(count($argv) < 2)
{
    	print "Usage: configure-mbox (install | enable | upgrade <version> | configure | disable | remove)\n";
	exit(1);
}

$user_handler = new SpamFilter_Serviceusers();
$command = $argv[1];

$exitcode = 0;	
switch ($command)
{
	case "enable":
		// Dummy command, we do not do anything with it.
		exit( 0 );
		break;			
	case "install": 
		$exitcode = $user_handler->install();
		break;
	case "upgrade":
		// Dummy command, we do not do anything with it.
		exit( 0 );
		break;
	case "disable":			
		// Dummy command, we do not do anything with it.
		exit( 0 );
		break;
	case "remove":
		$exitcode = $user_handler->remove();
		break;
	case "configure":
		$exitcode = $user_handler->configure();
		break;
	default:
		fwrite(STDERR, "Unknown command '{$command}'" . PHP_EOL);
		exit(1);
}

if (! $exitcode ) 
{
	fwrite(STDERR, "Exiting with exit code 1." . PHP_EOL);
	exit (1);
}
fwrite(STDERR, "Exiting with exit code 0" . PHP_EOL);
exit( 0 );
