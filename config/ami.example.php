<?php
/**
 * AMI Configuration Example
 *
 * Copy this file to ami.php and update with your AMI credentials.
 *
 * To create an AMI user, add to /etc/asterisk/manager.conf:
 *
 * [areports]
 * secret = YOUR_AMI_SECRET
 * deny = 0.0.0.0/0.0.0.0
 * permit = 127.0.0.1/255.255.255.255
 * read = system,call,agent,user,config,command,reporting
 * write = system,call,agent,user,config,command,reporting
 *
 * Then reload: asterisk -rx "manager reload"
 */

return [
    'host' => '127.0.0.1',
    'port' => 5038,
    'username' => 'areports',
    'secret' => 'YOUR_AMI_SECRET',
    'timeout' => 30,
    'connect_timeout' => 5,
];
