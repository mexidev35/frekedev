<?php

    # To modify for irchan2_admin.php, irchan3_admin.php, etc:
    #  Change page title around line 422.
    #  Delete subscriber display section starting around line 571.
    #  Delete subscriber load/modify section starting around line 108.
    #  Search for all 'irc_' and replace with 'irc2_'.

    # String to use for deleted token
    $deletedFromS3 = 'Deleted from S3';

    # No devices or tokens to display yet
    $deviceArray = array();
    $tokenCount = 0;
    
    # No user ID or account yet
    $wpUserId = '';
    $acctCreate = '';

    # Default no on-screen messages
    $topMsg = '';
    $midMsg = '';

    if (array_key_exists('submit', $_POST) and ('Update Options' == $_POST['submit'])) {
        # Request to update common options
        # These options are all saved in WP db, not in S3
        # Trim & escape chars before saving to db

        $partner = esc_sql(trim($_POST['irc_partner']));
        update_option('irc_partner', $partner);  

        $regBkt = esc_sql(trim($_POST['irc_regbkt']));
        update_option('irc_regbkt', $regBkt);  

        $lnkBkt = esc_sql(trim($_POST['irc_lnkbkt']));
        update_option('irc_lnkbkt', $lnkBkt);  

        $actBkt = esc_sql(trim($_POST['irc_actbkt']));
        update_option('irc_actbkt', $actBkt);  

        $aki = esc_sql(trim($_POST['irc_aki']));
        update_option('irc_aki', $aki);  

        $sak = esc_sql(trim($_POST['irc_sak']));
        update_option('irc_sak', $sak);  

        $title = esc_sql(trim($_POST['irc_title']));
        update_option('irc_title', $title);  

        $submit = esc_sql(trim($_POST['irc_submit']));
        update_option('irc_submit', $submit);  

        $success = esc_sql(trim($_POST['irc_success']));
        update_option('irc_success', $success);  

        $failure = esc_sql(trim($_POST['irc_failure']));
        update_option('irc_failure', $failure);

#        $duplicate = esc_sql(trim($_POST['irc_duplicate']));
#        update_option('irc_duplicate', $duplicate);

        $renewMsg = esc_sql(trim($_POST['irc_renewmsg']));
        update_option('irc_renewmsg', $renewMsg);

        $renewMax = esc_sql(trim($_POST['irc_renewmax']));
        update_option('irc_renewmax', $renewMax);

        $devMsg = esc_sql(trim($_POST['irc_devmsg']));
        update_option('irc_devmsg', $devMsg);

        $devMax = esc_sql(trim($_POST['irc_devmax']));
        update_option('irc_devmax', $devMax);

        $expMins = esc_sql(trim($_POST['irc_expmins']));
        update_option('irc_expmins', $expMins);

        $command = esc_sql(trim($_POST['irc_command']));
        update_option('irc_command', $command);

        $debug = esc_sql(trim($_POST['irc_debug']));
        update_option('irc_debug', $debug);

        $topMsg = ' Options saved.';

    } else {
        # Not changing options
        
        # This WHILE loop is used as a container to BREAK out of. It never actually loops.
        while(true) {

            # Load all top section common options
            $partner = get_option('irc_partner');  
            $regBkt = get_option('irc_regbkt');  
            $lnkBkt = get_option('irc_lnkbkt');  
            $actBkt = get_option('irc_actbkt');  
            $aki = get_option('irc_aki');  
            $sak = get_option('irc_sak');
            $title = get_option('irc_title');
            $submit = get_option('irc_submit');
            $success = get_option('irc_success');
            $failure = get_option('irc_failure');
#            $duplicate = get_option('irc_duplicate');
            $renewMsg = get_option('irc_renewmsg');
            $renewMax = get_option('irc_renewmax');
            $devMsg = get_option('irc_devmsg');
            $devMax = get_option('irc_devmax');
            $expMins = get_option('irc_expmins');
            $command = get_option('irc_command');
            $debug = get_option('irc_debug');
    
## DELETE THIS SECTION FOR ADD-ON WIDGET

            if (! array_key_exists('submit', $_POST) or ('' == $_POST['submit'])) {
                # Initial GET
                break;
            }
            # This is either a Load Subscriber or Modify Subscriber POST
    
            # Save selected user id
            $wpUserId = esc_sql(trim($_POST['irc_wpuserid']));
            update_option('irc_wpuserid', $wpUserId);
    
            # Create name of user's account file in S3 account bucket
            $s3 = new S3($aki, $sak);
            if ('' == $partner) {
                # No partner id provided
                $acct_name = $wpUserId;
            } else {
                # Partner id is bucket dir/folder/prefix
                $acct_name = $partner . '/' . $wpUserId;                
            }
        
            # Attempt to read user's account info
            $acct_xml_string = $s3->getObjectBody($actBkt, $acct_name); // Returns False if not found

            if ($acct_xml_string) {
                # Account record read successfully
    
                $acct_xml = simplexml_load_string($acct_xml_string);
                $acctCreate = $acct_xml->creationTime;
                $acctDevMax = $acct_xml->maximumDevices;
    
                # Count number of device tokens in user's account file
                # This should also be the number of token files in the linking bucket
                $tokens = $acct_xml->tokens;
                #$tokenCount = $tokens->count();               // php >= 5.3
                $tokenCount = count($tokens->children());      // php < 5.3

                if ('Load Subscriber' == $_POST['submit']) {            
                    # Request to load subscriber details
    
                    # Load device information from link bucket
                    $devNum = 0;
                    foreach ($tokens->children() as $link_name) {
    
                        # Get model number and firmware version from the account file
                        foreach ($link_name->attributes() as $a => $b) {
                            if (('deviceTypeID' == $a) or ('firmwareVersion' == $a)) {
                                $device[(string) $a] = $b;
                            }
                        }
    
                        $link_xml_string = $s3->getObjectBody($lnkBkt, $link_name); // Returns False if not found
                        if ($link_xml_string) {
    
                            # Parse XML from link file and copy into deviceArray
                            $link_xml = simplexml_load_string($link_xml_string);
                            $device['status'] = $link_xml->status;
                            $device['deviceID'] = $link_xml->deviceID;
                            $device['deviceToken'] = $link_name;
                            $device['customerID'] = $link_xml->customerID;
                            $device['creationTime'] = $link_xml->creationTime;
                            $device['messageTitle'] = $link_xml->messageTitle;
                            $device['message'] = $link_xml->message;
                            $device['expirationMinutes'] = $link_xml->expirationMinutes;
                            $device['command'] = $link_xml->command;

                            # Add new device info to device array
                            $deviceArray[$devNum] = $device;

                        } else {
                            # Could not read link file
                            # Prepare error message but continue
                            $midMsg .= ' Could not read file ' . $link_name . ' for device ' . ($devNum+1) . ' from S3 Linking bucket.';
                            # Display a dummy array
                            $noDevice['deviceToken'] = $link_name;
                            $deviceArray[$devNum] = $noDevice;
                        }

                        # Get next device token
                        $devNum += 1;

                    }
                    # End of per token FOREACH loop

                    # Subscriber details have been loaded
                    break;

                } elseif ('Modify Subscriber' == $_POST['submit']) {
                    # Request to update subscriber details
        
                    # Get device max POST parameter, blank is valid
                    $newAcctDevMax = esc_sql(trim($_POST['irc_acctdevmax']));
    
                    # Update account file if max devices changed
                    if ($newAcctDevMax != $acctDevMax) {
                        $acct_xml->maximumDevices = $newAcctDevMax;
                        $acct_xml_string = $acct_xml->asXML();
                        $acct_update = $s3->putObject($acct_xml_string, $actBkt, $acct_name, S3::ACL_PRIVATE);
                        if (!$acct_update) {
                            # Error, could not write the account file to the account bucket
                            $midMsg .= ' Could not update ' . $acct_name . ' max devices in S3 Account bucket.';
                            break;
                        }
                    }

                    # Flag that account file needs to be rewritten because token was deleted
                    $acctRewrite = false;

                    # Counter contortions because
                    # (a) Just-deleted tokens cause more POSTs than there are valid tokens, and
                    # (b) Just-deleted tokens are saved in deviceArray for display
                    $postCount = $tokenCount;
                    $deviceArrayIndex = 0;
                    # Loop through 1 or more sets of linking file parameters
                    for ($devNum = 0; $devNum < $postCount; $devNum++) {

                        # Get link name from hidden POST value
                        $link_name = esc_sql(trim($_POST['irc_dev' . $devNum . '_token']));

                        # If link name is from a just-deleted token, continue
                        if ($deletedFromS3 == $link_name) {
                            # Adjust loop counter to reflect 1 additional POST parameter
                            #  because tokenCount does not include just-deleted tokens
                            $postCount += 1;
                            # Check next device number
                            continue;
                        }

                        # If link name is not provided in POST because this is a new token
                        if ('' == $link_name) {
                            # tokenCount (postCount) was higher than # of POSTs
                            # This may happen when doing a MODIFY immediately after a new device
                            #  is registered, but before the new token is LOADed.
                            $tokenCount -= 1;
                            continue;
                        }

                        # Read existing file from linking bucket                        
                        $link_xml_string = $s3->getObjectBody($lnkBkt, $link_name); // Returns False if not found
                        if (!$link_xml_string) {
                            # Error, could not read a linking file
                            $midMsg .= ' Could not read file ' . $link_name . ' from S3 Linking bucket.';
                            # Break 2 to exit outer WHILE loop
                            break 2;
                        }
                        $link_xml = simplexml_load_string($link_xml_string);
                        $link_owner = $link_xml->customerID;

                        # Verify that token is owned by the selected user before changing anything
                        if ($link_owner != $wpUserId) {
                            # Error, owner mismatch
                            # This might happen if admin changes User ID on existing data set
                            #  and then clicks MODIFY SUBSCRIBER instead of LOAD SUBSCRIBER.
                            $midMsg .= ' User ID changed or token owner does not match. Please click LOAD SUBSCRIBER.';
                            # Break 2 to exit outer WHILE loop
                            break 2;
                        }

                        # Check for delete request
                        if (array_key_exists('irc_dev' . $devNum . '_delete', $_POST) and ('delete' == esc_sql(trim($_POST['irc_dev' . $devNum . '_delete'])))) {
                            # Delete linking file & delete token from account file

                            # Delete linking file from S3 linking bucket
                            if (!$s3->deleteObject($lnkBkt, $link_name)) {
                                $midMsg .= ' Could not delete file ' . $link_name . ' from S3 Linking bucket.';
                                # Break 2 to exit outer WHILE loop
                                break 2;
                            }
                            # Ok, linking file deleted

                            # Rebuild the account file, leaving out the token to be deleted
                            # $acct_name and $acct_xml are valid
                            $newAcct_xml = new SimpleXMLElement('<account></account>');
                            foreach ($acct_xml->children() as $acct_child) {
                                if ('tokens' != $acct_child->getName()) {
                                    # Add unchanged child into new account xml
                                    $newAcct_xml->addChild($acct_child->getName(), $acct_child);
                                } else {
                                    # Add modified tokens child into new account xml
                                    $newTokens = $newAcct_xml->addChild('tokens');
                                    foreach ($tokens->children() as $token) {
                                        if ($token != $link_name) {
                                            # Put non-deleted token into new tokens parent element
                                            $newToken = $newTokens->addChild('token', $token);
                                            # Add original attributes back to the new token
                                            foreach ($token->attributes() as $a => $b) {
                                                $newToken->addAttribute($a, $b);
                                            }
                                        }
                                    }
                                }                                
                            }
                            $acct_xml = $newAcct_xml;
                            # Update tokens
                            $tokens = $acct_xml->tokens;
                            # Flag account file for rewrite
                            $acctRewrite = true;

                            # Display a dummy array
                            $noDevice['deviceToken'] = $deletedFromS3;
                            $deviceArray[$deviceArrayIndex] = $noDevice;
                            $deviceArrayIndex += 1;

                        } else {
                            # Save linking file

                            # Update all editable linking information, assume all POST parameters are present
                            # Any missing post parameters result in a blank value stored in S3
                            $link_xml->status = esc_sql(trim($_POST['irc_dev' . $devNum . '_status']));
                            $link_xml->messageTitle = esc_sql(trim($_POST['irc_dev' . $devNum . '_msgtitle']));
                            $link_xml->message = esc_sql(trim($_POST['irc_dev' . $devNum . '_msg']));
                            $link_xml->expirationMinutes = esc_sql(trim($_POST['irc_dev' . $devNum . '_expmins']));
                            $link_xml->command = esc_sql(trim($_POST['irc_dev' . $devNum . '_command']));

                            # Write the linking file back to the linking bucket
                            $link_xml_string = $link_xml->asXML();
                            $link_write = $s3->putObject($link_xml_string, $lnkBkt, $link_name, S3::ACL_PRIVATE);
                            if (!$link_write) {
                                # Error, could not write the linking file to the linking bucket
                                $midMsg .= ' Could not write ' . $link_name . ' to S3 Linking bucket.';
                                # Break 2 to exit outer WHILE loop
                                break 2;
                            }
    
                            # Copy device info into device array so it can be redisplayed
                            $device['status'] = $link_xml->status;
                            $device['deviceID'] = $link_xml->deviceID;
                            $device['deviceToken'] = $link_name;
                            $device['customerID'] = $link_xml->customerID;
                            $device['creationTime'] = $link_xml->creationTime;
                            $device['messageTitle'] = $link_xml->messageTitle;
                            $device['message'] = $link_xml->message;
                            $device['expirationMinutes'] = $link_xml->expirationMinutes;
                            $device['command'] = $link_xml->command;

                            # Scan through the account file to get the model & firmware version
                            foreach ($acct_xml->children() as $acct_child) {
                                # Check each element, looking for the tokens element
                                if ('tokens' == $acct_child->getName()) {
                                    # This is the tokens element
                                    foreach ($tokens->children() as $token) {
                                        # Check each tokens element, looking for the correct link name
                                        if ($token == $link_name) {
                                            # This is the token with the matching link name
                                            foreach ($token->attributes() as $a => $b) {
                                                # Check each attribute, looking for model # & firmware version
                                                if (('deviceTypeID' == $a) or ('firmwareVersion' == $a)) {
                                                    $device[(string) $a] = $b;
                                                }
                                            }
                                        }
                                    }
                                }                                
                            }

                            # Add the device to the device array
                            $deviceArray[$deviceArrayIndex] = $device;
                            $deviceArrayIndex += 1;

                        }
                    }
                    # End of per device FOR loop

                    # Sanity check
                    if (count($deviceArray) != $tokenCount) {
                        # This should never happen...
                        $midMsg .= ' Warning: deviceArray size != tokenCount.';
                    }

                    # Need to rewrite account file because of token deletion?
                    if ($acctRewrite) {
                        # A token was deleted, so rewrite account file to the S3 account bucket
                        $acct_xml_string = $acct_xml->asXML();
                        $acct_write = $s3->putObject($acct_xml_string, $actBkt, $acct_name, S3::ACL_PRIVATE);
                        if (!$acct_write) {
                            # Error, could not write the account file to the account bucket
                            $midMsg .= ' Could not write ' . $acct_name . ' to S3 Account bucket after token deletion.';
                            # Break 2 to exit outer WHILE loop
                            break;
                        }
                    }

                    # Subscriber details have been modified & saved
                    $midMsg .= ' Subscriber details saved.';

                    break;
                }
    
            } else {
                # Account record does not exist
                $midMsg .= ' Subscriber ' . $acct_name . ' not found in S3 Account bucket!';
                break;

            }

## END OF SECTION TO DELETE

            break;
        }
        # End of outer WHILE loop
    }

    if ($topMsg != '') {
        # Display a message at the top of the page

        ?>  
        <div class="updated"><p><strong><?php _e( $topMsg ); ?></strong></p></div>  
        <?php

    }
  
?>
<div class="wrap">  
    <?php    echo "<h2>" . __( 'Instant TV Channel - Device Registration and Linking', 'irc_trdom' ) . "</h2>"; ?>  
    <form name="irc_form" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">  
        <table>
        <tr>
            <td colspan="3"><?php    echo "<h4>" . __( 'S3 Settings', 'irc_trdom' ) . "</h4>"; ?></td>
        </tr>
        <tr>
            <td><?php _e("Optional Partner ID: " ); ?></td>
            <td><input type="text" name="irc_partner" value="<?php echo $partner; ?>" size="30"></td>
            <td><?php _e(" ex: mycorp" ); ?></td>
        </tr>
        <tr>
            <td><?php _e("Registration Bucket Name: " ); ?></td>
            <td><input type="text" name="irc_regbkt" value="<?php echo $regBkt; ?>" size="30"></td>
            <td><?php _e(" ex: my_reg_bucket" ); ?></td>
        </tr>
        <tr>
            <td><?php _e("Linking Bucket Name: " ); ?></td>
            <td><input type="text" name="irc_lnkbkt" value="<?php echo $lnkBkt; ?>" size="30"></td>
            <td><?php _e(" ex: my_link_bucket" ); ?></td>
        </tr>
        <tr>
            <td><?php _e("Account Bucket Name: " ); ?></td>
            <td><input type="text" name="irc_actbkt" value="<?php echo $actBkt; ?>" size="30"></td>
            <td><?php _e(" ex: my_account_bucket" ); ?></td>
        </tr>
        <tr>
            <td><?php _e("Access Key ID: " ); ?></td>
            <td><input type="text" name="irc_aki" value="<?php echo $aki; ?>" size="30"></td>
            <td><?php _e(" ex: AKIAJ3NAX5K8AB3AXBOG" ); ?></td>
        </tr>
        <tr>
            <td><?php _e("Secret Access Key: " ); ?></td>
            <td><input type="password" name="irc_sak" value="<?php echo $sak; ?>" size="30"></td>
            <td><?php _e(" ex: 2U3a+dERAyraXDfig+pigbwWRxGpabOH/snmXaAf" ); ?></td>
        </tr>
        <tr>
            <td colspan="3"><?php    echo "<h4>" . __( 'WordPress Settings', 'irc_trdom' ) . "</h4>"; ?></td>
        </tr>
        <tr>
            <td><?php _e("Widget Title: " ); ?></td>
            <td><input type="text" name="irc_title" value="<?php echo $title; ?>" size="30"></td>
            <td><?php _e(" ex: Enter Roku Code Here" ); ?></td>
        </tr>
        <tr>
            <td><?php _e("Widget Button Label: " ); ?></td>
            <td><input type="text" name="irc_submit" value="<?php echo $submit; ?>" size="30"></td>
            <td><?php _e(" ex: Register" ); ?></td>
        </tr>
        <tr>
            <td><?php _e("Success Message: " ); ?></td>
            <td><input type="text" name="irc_success" value="<?php echo $success; ?>" size="30"></td>
            <td><?php _e(" ex: Registration Successful" ); ?></td>
        </tr>
        <tr>
            <td><?php _e("Failure Message: " ); ?></td>
            <td><input type="text" name="irc_failure" value="<?php echo $failure; ?>" size="30"></td>
            <td><?php _e(" ex: Registration Failed" ); ?></td>
        </tr>
<!--        <tr>
            <td><?php _e("Duplicate Code Message: " ); ?></td>
            <td><input type="text" name="irc_duplicate" value="<?php #echo $duplicate; ?>" size="30"></td>
            <td><?php _e(" ex: Code Already Registered" ); ?></td>
        </tr> -->
        <tr>
            <td><?php _e("Device Limit Message: " ); ?></td>
            <td><input type="text" name="irc_devmsg" value="<?php echo $devMsg; ?>" size="30"></td>
            <td><?php _e(" ex: You already have the maximum number of devices registered." ); ?></td>
        </tr>
        <tr>
            <td><?php _e("Renewal Limit Message: " ); ?></td>
            <td><input type="text" name="irc_renewmsg" value="<?php echo $renewMsg; ?>" size="30"></td>
            <td><?php _e(" ex: No subscription renewals are available at this time." ); ?></td>
        </tr>
        <tr>
            <td><?php _e("Debug Widget: " ); ?></td>
            <td>
                <select name="irc_debug">
                <?php
                    if ('y' == $debug) {
                ?>
                    <option value="n">No</option>
                    <option selected="selected" value="y">Yes</option>                                                    
                <?php
                    } else {
                ?>
                    <option selected="selected" value="n">No</option>
                    <option value="y">Yes</option>
                <?php
                    }
                ?>
                </select>
            </td>
            <td>Should always be NO unless testing. YES adds diagnostic messages to widget.</td>
        </tr>
        <tr>
            <td colspan="3"><?php    echo "<h4>" . __( 'Subscriber Settings', 'irc_trdom' ) . "</h4>"; ?></td>
        </tr>
        <tr>
            <td><?php _e("Maximum Number of Renewals: " ); ?></td>
            <td><input type="text" name="irc_renewmax" value="<?php echo $renewMax; ?>" size="30"></td>
            <td><?php _e(" ex: 0 (0 allows no renewals. Blank allows unlimited renewals. Typically should be blank. If limiting renewals do not delete files from the S3 Linking bucket.)" ); ?></td>
        </tr>
        <tr>
            <td><?php _e("Default Maximum Number of Devices: " ); ?></td>
            <td><input type="text" name="irc_devmax" value="<?php echo $devMax; ?>" size="30"></td>
            <td><?php _e(" ex: 2" ); ?></td>
        </tr>
        <tr>
            <td><?php _e("Default Registration Lifetime (minutes): " ); ?></td>
            <td><input type="text" name="irc_expmins" value="<?php echo $expMins; ?>" size="30"></td>
            <td><?php _e(" ex: 1440 (0 or blank will never expire)" ); ?></td>
        </tr>
        <tr>
            <td><?php _e("Default Command: " ); ?></td>
            <td>
                <select name="irc_command">
                <?php

                    if ('deleteToken' == $command) {
                        # Delete is selected

                ?>
                    <option value="">No Command</option>
                    <option selected="selected" value="deleteToken">Delete Token</option>
                <?php

                    } else {
                        # None is selected

                ?>
                    <option selected="selected" value="">No Command</option>
                    <option value="deleteToken">Delete Token</option>
                <?php

                    }

                ?>
                </select>
            </td>
            <td>Default command. Typically should be &quot;No Command&quot;.</td>
        </tr>
        </table>
        <p class="submit">  
            <input type="submit" name="submit" value="Update Options" />
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <a href="http://www.instanttvchannel.com/help/roku_registration_and_linking_files">Information about Registration and Linking S3 Buckets and Files</a>
        </p>  
    </form>
    
<!-- DELETE THIS SECTION FOR ADD-ON WIDGET PLUGINS -->

    <hr>
    <?php
    
    if ($midMsg != '') {
        # Display a message at the middle of the page

        ?>  
        <div class="updated"><p><strong><?php _e( $midMsg ); ?></strong></p></div>  
        <?php

    }
    
    # Magic client ID array used for various display functions
    # Roku removed support for retrieving serial numbers around FW 9.0, replaced with Client ID
    if ($tokenCount > 0) {
        $sn = array();
        $junk = array();
        irchanserials($tokens, $sn, $junk);
    }

    ?>
    <form name="irc_subscriber_form" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">  
        <table>
        <tr>
            <td colspan="3"><?php    echo "<h4>" . __( 'Subscriber Details', 'irc_trdom' ) . "</h4>"; ?></td>
        </tr>
        <tr>
            <td><?php _e("Subscriber&#39;s WordPress User ID: " ); ?></td>
            <td><input type="text" name="irc_wpuserid" value="<?php echo $wpUserId; ?>" size="30"></td>
            <td>&nbsp;</td>
        </tr>
        <?php

            if ('' != $acctCreate) {
                # Display account create date & max devs only if valid user id is provided

        ?>
        <tr>
            <td><?php _e("Subscriber Creation Date: " ); ?></td>
            <td>&nbsp;&nbsp;<?php echo $acctCreate; ?></td>
            <td>&nbsp;</td>
        </tr>
        <tr>
            <td><?php _e("Maximum Number of Devices: " ); ?></td>
            <td><input type="text" name="irc_acctdevmax" value="<?php echo $acctDevMax; ?>" size="30"></td>
            <td>ex: 5 (blank will use default of <?php echo $devMax; ?>)</td>
        </tr>
        <tr>
            <td><?php _e("Actual Number of Devices: " ); ?></td>
            <td>&nbsp;&nbsp;<?php echo count($sn); ?></td>
            <td>&nbsp;</td>
        </tr>
        <?php         

            }
   
            for ($devNum = 0; $devNum < $tokenCount; $devNum++) {

                # Is this a deleted device token?
                if (array_key_exists($devNum, $deviceArray) and array_key_exists('deviceID', $deviceArray[$devNum])) {
                    # Ok to display this record

                    # Is this the active (most recent) record for this device?
                    if ($sn[(string) $deviceArray[$devNum]['deviceID']] == strtotime($deviceArray[$devNum]['creationTime'])) {
                        # Yes, this is the active (most recent) record
                        $active= true;
                    } else {
                        # No, this is not the active record
                        $active = false;                    
                    }

        ?>
        <tr>
            <td colspan="3">
                <fieldset style="width:100%; border:1px solid black;">
                    <legend><b><?php _e("Linking File "); echo $devNum + 1; ?>
                    <?php

                    if ($active) {
                        # Add "most recent" text to legend
                        ?>
                        - This is the most recent linking file for this device
                        <?php
                    }

                    # Hidden input contains name of this token file:
                    ?>
                    </b></legend>
                    <input type="hidden" name="irc_dev<?php echo $devNum; ?>_token" value="<?php echo $deviceArray[$devNum]['deviceToken']; ?>">

                    <table>
                        <tr>
                            <td><?php _e("Device Client ID: " ); ?></td>
                            <td><?php echo $deviceArray[$devNum]['deviceID']; ?></td>
                            <?php

                # If inactive (not most recent) record, display "Delete" checkbox
                if ($active) {
                    # Most recent linking file, display nothing
                            ?>
                            <td>&nbsp;</td>
                            <?php
                } else {
                    # Not deleted, not active, display delete checkbox
                            ?>
                            <td><input type="checkbox" name="irc_dev<?php echo $devNum; ?>_delete" value="delete">&nbsp;&nbsp;Delete this file from S3 Linking bucket.</td>
                            <?php
                }

                        ?>
                        </tr>
                        <tr>
                            <td><?php _e("Device Model &amp; Firmware: " ); ?></td>
                            <td><?php echo $deviceArray[$devNum]['deviceTypeID'] . '&nbsp;&nbsp;&nbsp;&nbsp;' . $deviceArray[$devNum]['firmwareVersion']; ?></td>
                            <td>&nbsp;</td>
                        </tr>
                        <tr>
                            <td><?php _e("Registration Date: " ); ?></td>
                            <td><?php echo $deviceArray[$devNum]['creationTime']; ?></td>
                            <td>&nbsp;</td>
                        </tr>
                        <tr>
                            <td><?php _e("Linking File: " ); ?></td>
                            <td><?php echo $deviceArray[$devNum]['deviceToken']; ?></td>
                            <td>Name of this file in the S3 Linking bucket.</td>
                        </tr>
                        <tr>
                            <td><?php _e("Status: " ); ?></td>
                            <td>
                                <select name="irc_dev<?php echo $devNum; ?>_status">
                                <?php

                    # Either "success" or anything else
                    if ('success' == $deviceArray[$devNum]['status']) {

                                    ?>
                                    <option selected="selected" value="success">Success</option>                                                    
                                    <option value="failure">Fail</option>
                                    <?php
                    } else {
                                    ?>
                                    <option value="success">Success</option>
                                    <option selected="selected" value="failure">Fail</option>
                                    <?php
                    }

                                ?>
                                </select>
                            </td>
                            <td>Select Fail to temporarily deny access.</td>
                        </tr>
                        <tr>
                            <td><?php _e("Message Title: " ); ?></td>
                            <td><input type="text" name="irc_dev<?php echo $devNum; ?>_msgtitle" value="<?php echo $deviceArray[$devNum]['messageTitle']; ?>" size="30"></td>
                            <td><?php _e(" ex: Important Message" ); ?></td>
                        </tr>
                        <tr>
                            <td><?php _e("Message: " ); ?></td>
                            <td><input type="text" name="irc_dev<?php echo $devNum; ?>_msg" value="<?php echo $deviceArray[$devNum]['message']; ?>" size="30"></td>
                            <td><?php _e(" ex: Your subscription payment is overdue!" ); ?></td>
                        </tr>
                        <tr>
                            <td><?php _e("Registration Lifetime (minutes): " ); ?></td>
                            <td><input type="text" name="irc_dev<?php echo $devNum; ?>_expmins" value="<?php echo $deviceArray[$devNum]['expirationMinutes']; ?>" size="30"></td>
                            <?php

                    # If no expiration, or if (nowTime - registrationTime) < expirationTime, then not expired
                    if ( (intval($deviceArray[$devNum]['expirationMinutes']) == 0) or ((time() - strtotime($deviceArray[$devNum]['creationTime'])) < (60*intval($deviceArray[$devNum]['expirationMinutes']))) ) {
                        # Not expired

                            ?>
                            <td><?php _e(" ex: 1440 (0 or blank will never expire)" ); ?></td>
                            <?php

                    } else {
                        # Expired    

                            ?>
                            <td style="color:red;"><?php _e(" EXPIRED" ); ?></td>
                            <?php

                    }

                        ?>
                        </tr>

                        <tr>
                            <td><?php _e("Command: " ); ?></td>
                            <td>
                                <select name="irc_dev<?php echo $devNum; ?>_command">
                                <?php

                    # 1 command implemented
                    if ('deleteToken' == $deviceArray[$devNum]['command']) {

                                    ?>
                                    <option value="">No Command</option>
                                    <option selected="selected" value="deleteToken">Delete Token</option>
                                    <?php

                    } else {
                                    ?>
                                    <option selected="selected" value="">No Command</option>
                                    <option value="deleteToken">Delete Token</option>
                                    <?php

                    }
                                ?>
                                </select>
                            </td>
                            <td>Deleting token from device forces re-registration.</td>
                        </tr>
                    </table>
                </fieldset>
            </td>
        </tr>        
        <?php

                }
                # End of displayable (non-deleted) record for this device

            }
            # End of per-device FOR loop

        ?>
        </table>
        <p class="submit">  
            <input type="submit" name="submit" value="Load Subscriber" />
        <?php

            # Do not display MODIFY button if nothing to modify
            if (($wpUserId != "") or (count($deviceArray) > 0) ) {
                # Display MODIFY button

        ?>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <input type="submit" name="submit" value="Modify Subscriber" />
        <?php

            }

        ?>
        </p>  
    </form>

<!-- END OF SECTION TO DELETE -->

</div>  
