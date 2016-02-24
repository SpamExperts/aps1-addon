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
require_once("SpamFilter/System.php");
require_once("SpamFilter/Domains.php");
require_once("SpamFilter/Serviceusers.php");

if(count($argv) >= 2)
{
    	print "Usage: report-resources\n";
	exit(1);
}

$domain = new SpamFilter_Domains();
$user = new SpamFilter_Serviceusers();

$resource_data['num_domains'] 		= (count($domain->getAmount()>0)) 	? $domain->getAmount() 	: 0;
$resource_data['num_serviceusers'] 	= (count($user->getAmount()>0)) 	? $user->getAmount() 	: 0;

$system = new SpamFilter_System();
$system->show_resources( $resource_data );
exit(0);
