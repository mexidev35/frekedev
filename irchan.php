<?php
    /*
    Plugin Name: Instant TV Channel
    Plugin URI: http://www.instanttvchannel.com
    Description: Plugin to register and link a Roku device to an Instant TV Channel. After activation, configure using the "Settings - Instant TV Channel" configuration page. Shortcode = [irchan].
    Author: Instant TV Channel
    Version: 2023.01
    Author URI: http://www.instanttvchannel.com
    */

    # To modify for irchan2.php, irchan3.php, etc:
    #  Change the Plugin Name and Description above to
    #   Plugin Name: ITVC Widget 2
    #   Description: Plugin to provide an additional registration and linking widget. Must be used along with the primary Instant TV Channel plugin. After activation, configure using the "Settings - ITVC Widget 2" configuration page. Shortcode = [irchan2].
    #  Change the add_options_page line around line 96 to
    #   add_options_page('ITVC Widget 2', 'ITVC Widget 2', 1, 'Instant_TV_Channel_2', 'irchan2_admin');
    #  Change $widget_ops line around line 120 to
    #    $widget_ops = array( 'description' => __( '2nd widget that provides S3 registration and linking for your Roku channel. Widget options are adjusted under the Settings - ITVC Widget 2 menu.', 'text_domain' ));
    #  Change parent:: line around line 121 to
    #    parent::__construct( false, $name='ITVC Widget 2', $widget_ops);
    #  Change the add_shortcode line around line 161 to
    #   add_shortcode( 'irchan2', 'irchan2_shortcode' );
    #  Search for all 'irc_' and replace with 'irc2_'.
    #  Search for all 'irchan_' and replace with 'irchan2_'.
    #  Search for all 'IRChanWidget' and replace with 'IRChan2Widget'.
    #  Remove the code block starting around line 29.


## DELETE THIS CODE BLOCK FOR ADD-ON WIDGET

    # The S3 function library
    include_once 'irchan_s3.php';

    # ----- Get Serial #s -----
    # $st = Result array of client IDs and request times
    # $sc = Result array of client IDs and occurence counts
    # Roku removed support for retrieving serial numbers around FW 9.0, replaced with Client ID
    function irchanserials($tokens, &$st, &$sc) {
        foreach ($tokens->children() as $token) {
            # Check each token
            $deviceID = 'Unknown';
            $requestSecs = 0;
            foreach ($token->attributes() as $a => $b) {
                # Check each attribute of each token
                if ($a == 'deviceID') {
                    # Serial number
                    $serial = $b;
                } elseif ($a == 'requestTime') {
                    # Request time in epoch seconds
                    $requestSecs = strtotime($b);                 
                }
            }
             if (array_key_exists((string) $serial, $st)) {
                # This serial number was already encountered
                if ($requestSecs > $st[(string) $serial]) {
                    # This requestSecs is newer
                    $st[(string) $serial] = $requestSecs;                    
                }
                # Increment occurrence count for this serial number
                $sc[(string) $serial] += 1;                
             } else {
                # First time this serial number was encountered
                $st[(string) $serial] = $requestSecs;
                $sc[(string) $serial] = 1; 
             }
        }
    }
    # ----- End of Get Serial #s -----

## END OF CODE BLOCK TO DELETE


    # Execute once at activation
    register_activation_hook( __FILE__,  'irchan_install' );
    function irchan_install() {
        # If Submit button has no text, assume not initialized yet
        if ('' == get_option('irc_submit')) {
            update_option('irc_title', 'Enter Roku Code Here');  
            update_option('irc_submit', 'Register');  
            update_option('irc_success', 'Registration Successful');  
            update_option('irc_failure', 'Registration Failed');
#            update_option('irc_duplicate', 'Code Already Registered');
            update_option('irc_renewmsg', 'No subscription renewals are available at this time.');
            update_option('irc_renewmax', '');
            update_option('irc_devmsg', 'You already have the maximum number of devices registered.');
            update_option('irc_devmax', '2');
            update_option('irc_expmins', '');
            update_option('irc_command', '');
            update_option('irc_debug', 'n');
        }
    }


    # Admin Menu
    add_action('admin_menu', 'irchan_admin_actions');
    function irchan_admin_actions() {
        add_options_page('Instant TV Channel', 'Instant TV Channel', 'manage_options', 'Instant_TV_Channel', 'irchan_admin');
    }
    function irchan_admin() {
        include('irchan_admin.php');
    }


    # Need jquery for ajax processing
    add_action('init', 'irchan_init');
    function irchan_init() {
        if (!is_admin()) {
            wp_enqueue_script('jquery');
        }        
    }


    # ----- The widget -----
    add_action( 'widgets_init', 'IRChanWidgetInit' );
    function IRChanWidgetInit() {
            register_widget( 'IRChanWidget' );
    }
    class IRChanWidget extends WP_Widget {

        public function __construct() {
            $widget_ops = array( 'description' => __( 'Widget that provides S3 registration and linking for your Roku channel. Widget options are adjusted under the Settings - Instant TV Channel menu.', 'text_domain' ));
            parent::__construct( false, $name='Instant TV Channel', $widget_ops);
        }

        public function widget( $args, $instance ) {
            
            extract( $args );
            $title = get_option('irc_title');       // Widget title
            $submit = get_option('irc_submit');     // Submit button label
            ?>
        
            <?php
                echo $before_widget;
            ?>
        
            <?php
                if ($title) {
                    echo $before_title . $title . $after_title;
                }
            ?>
        
            <div class='irchan_textbox'>
                <input class='irchan_text' id='irchan_text' type='text' />
                <input class='irchan_button' id='irchan_button' type='button' value='<?php echo $submit; ?>' />
                <div class='irchan_results' id='irchan_results'>&nbsp;</div>
            </div>
        
             <?php
                echo $after_widget;
             ?>
             <?php
        }
      
        public function update( $new_instance, $old_instance ) {
            return $new_instance;
        }
    }
    # ----- End of The Widget -----


    # ----- Shortcode -----
    add_shortcode( 'irchan', 'irchan_shortcode' );
    function irchan_shortcode(){     
        ob_start();
        the_widget('IRChanWidget', null, null);
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }
    # ----- End of Shortcode -----


    # ----- Ajax Processing -----
    # Action that is called by JS Ajax
    # This is where the S3 reg & link & account info is updated
    add_action( 'wp_ajax_irchan_widget_action', 'irchan_widget_action' );
    add_action( 'wp_ajax_nopriv_irchan_widget_action', 'irchan_widget_action' );
    function irchan_widget_action() {

        $current_user = wp_get_current_user();
        $user_login = $current_user->user_login;
        $user_email = $current_user->user_email;

        # Information from the current widget post
        //$code = strtoupper(tag_escape( $_POST['user_text'] ));        // tag_escape may eat digits!
        $code = strtoupper(sanitize_text_field( $_POST['user_text'] )); // Code on S3 is always upper case
        //$nonce = tag_escape( $_POST['nonce'] );                       // tag_escape may eat digits!
        $nonce = sanitize_text_field( $_POST['nonce'] );                // Unique number
        $retVal = '';                                                   // Message to display
        $dRetVal = '(no error)';                                        // Debug message to display

        # Don't want blank codes
        if ($code == '') {
            $code = 'x';        // Assume this does not exist in reg bucket
        }

        # This WHILE loop is used as a container to BREAK out of. It never actually loops.
        while(true) {

            # Widget debug flag
            $debug = get_option('irc_debug');

            # Check nonce
            if (!wp_verify_nonce($nonce, 'irc_nonce')) {
                $dRetVal = '(bad nonce)';            
                break;
            }
            # Ok, nonce is good
        
            # Assume the worst, display fail message
            $retVal = get_option('irc_failure');

            # User must be logged in
            if ('' == $user_login) {
                $dRetVal = '(wordpress user not logged in)';
                break;
            }
            # Ok, user is logged in

            # Get widget admin info
            $partner = get_option('irc_partner');  
            $regBkt = get_option('irc_regbkt');  
            $lnkBkt = get_option('irc_lnkbkt');  
            $actBkt = get_option('irc_actbkt');  
            $aki = get_option('irc_aki');  
            $sak = get_option('irc_sak');
            $renewMax = get_option('irc_renewmax');     // Might be blank
            $devMax = intval(get_option('irc_devmax'));
            $expMins = get_option('irc_expmins');       // Might be blank
            $command = get_option('irc_command');       // Usually blank

            # Check for some required parameters
            if (($regBkt == '') or ($lnkBkt == '') or ($actBkt == '') or ($aki == '') or ($sak == '')) {
                $dRetVal = '(plugin config incomplete)';
                break;                
            }

            # Create some file names
            $s3 = new S3($aki, $sak);
            if ('' == $partner) {
                # No partner id provided
                $reg_name = $code;
                $acct_name = $user_login;
            } else {
                # Partner id is bucket dir/folder/prefix
                $reg_name = $partner . '/' . $code;                
                $acct_name = $partner . '/' . $user_login;                
            }

            # Read registration file from the reg bucket
            $reg_xml_string = $s3->getObjectBody($regBkt, $reg_name); // Returns False if not found

            # Check for reg code present
            if (!$reg_xml_string) {
                $dRetVal = '(no reg code found)';
                break;
            }
            # Ok, reg code file found in reg bucket

            # Parse XML from reg code file
            $reg_xml = simplexml_load_string($reg_xml_string);
            $deviceID = $reg_xml->deviceID;
            $deviceTypeID = $reg_xml->deviceTypeID;
            $firmwareVersion = $reg_xml->firmwareVersion;
            $partnerID = $reg_xml->partnerID;   // Must match $partner
            $channelID = $reg_xml->channelID;
            $deviceToken = $reg_xml->deviceToken;
            $requestTime = $reg_xml->requestTime;
            # Ok, XML has been parsed

            # Check for valid Partner ID
            # Partner ID from XML originates from the device & channel
            # Device/XML Partner ID must match WordPress Partner ID
            if ($partnerID != $partner) {
                $dRetVal = '(non-matching partner id: ' . $partnerID . ')';
                break;
            }
            # Ok, partner ids match

            # Build linking file name, also contents of token element in account record
            if ('' == $partner) {
                # No partner id specified
                $link_name = $deviceToken;
            } else {
                # Partner id is directory/folder/prefix
                $link_name = $partner . '/' . $deviceToken;                    
            }
            # Ok, have the new linking file name and token element contents

#            # Is linking XML file already present in link bucket (duplicate click)?
#            if ($s3->getObjectBody($lnkBkt, $link_name)) {
#                # Linking file for this link_name (device token) already present
#                $retVal = get_option('irc_duplicate');
#                $dRetVal = '(duplicate request)';
#                break;
#            }
#            # Ok, not a duplicate

            # If account record does not exist in account bucket                    
            #  then Create account record in account bucket
            $acct_xml_string = $s3->getObjectBody($actBkt, $acct_name); // Returns False if not found
            if (!$acct_xml_string) {
                # Account record does not exist, make a new one
                
                # Build XML string for new account record
                $acct_xml_string = <<<XML
<?xml version="1.0" ?>
<account>
<tokens/>
<maximumDevices/>
<customerEmail>$user_email</customerEmail>
<creationTime>$requestTime</creationTime>
</account>
XML;
                # Write new account file to S3
                $acct_write = $s3->putObject($acct_xml_string, $actBkt, $acct_name, S3::ACL_PRIVATE);
                if (!$acct_write) {
                    # Error, could not write the account file to the account bucket
                    $dRetVal = '(no account create: ' . $acct_name . ')';
                    break;
                }
            }
            # Ok, either a new account file was written or the existing account file was read
            # acct_xml_string contains current account file contents

            # Parse XML from the account file
            $acct_xml = simplexml_load_string($acct_xml_string);

            # Count existing number of tokens in account record (before adding this new one...)
            # TODO: This should take into account failed/disabled tokens?
            $tokens = $acct_xml->tokens;

            # Count actual number of unique devices by using client IDs
            # Roku removed support for retrieving serial numbers around FW 9.0, replaced with Client ID
            $counts = array();
            $junk = array();
            irchanserials($tokens, $junk, $counts);

            # Check max renewals
            if (('' != $renewMax) and (array_key_exists((string) $deviceID, $counts))) {
                # A renewal count is specified and
                #  at least one subscription token exists for this serial number
                $renewalCount = $counts[(string) $deviceID] - 1;
                if ($renewalCount >= intval($renewMax)) {
                    # User is at the limit, no renewals are allowed
                    $retVal = get_option('irc_renewmsg');
                    $dRetVal = '(already at limit of ' . $renewMax . ' renewals)';
                    break;
                }
            }
            # Ok, user has not exceeded the renewal limit

            # How many unique devices are there currently tokens for?
            $playerCount = count($counts);

            # If account record has a custom devMax then use it
            if ($acct_xml->maximumDevices != '') {
                $devMax = intval($acct_xml->maximumDevices);
            }

            # If counts contains user's serial #, add one to the max device count test
            # This allows devices to be renewed if device count is not currently exceeded
            if (array_key_exists((string) $deviceID, $counts)) {
                # Add one to test
                $existingSN = 1;
            } else {
                $existingSN = 0;
            }

            # Compare existing number of tokens to the maximum number of devices
            # Testing this way prevents users from "renewing" subs after devMax has been lowered
            if ($playerCount >= ($devMax + $existingSN)) {
                # User is at the limit, no new Roku devices can be added
                $retVal = get_option('irc_devmsg');
                $dRetVal = '(already at limit of ' . $devMax . ' tokens)';
                break;
            }
            # Ok, user has not exceeded the device limit

            # Add new token to the account record
            # This serves as a link to a file in the linking bucket
            $newToken = $tokens->addChild('token', $link_name);
            $newToken->addAttribute('deviceID', $deviceID);
            $newToken->addAttribute('deviceTypeID', $deviceTypeID);
            $newToken->addAttribute('firmwareVersion', $firmwareVersion);
            $newToken->addAttribute('requestTime', $requestTime);
            $acct_xml_string = $acct_xml->asXML();

            # Build XML string for link bucket
            $link_xml_string = <<<XML
<?xml version="1.0" ?>
<linkResponse>
<status>success</status>
<deviceID>$deviceID</deviceID>
<customerID>$user_login</customerID>
<customerEmail>$user_email</customerEmail>
<creationTime>$requestTime</creationTime>
<expirationMinutes>$expMins</expirationMinutes>
<command>$command</command>
</linkResponse>
XML;

            # Write linking XML file to S3
            $link_write = $s3->putObject($link_xml_string, $lnkBkt, $link_name, S3::ACL_PRIVATE);
            if (!$link_write) {
                # Error, could not write the linking file to the linking bucket
                $dRetVal = '(no link write: ' . $link_name . ')';
                break;
            }
            # Ok, linking file has been written to the linking bucket
            
            # Write updated account file (after linking file has been successfully written)
            $acct_update = $s3->putObject($acct_xml_string, $actBkt, $acct_name, S3::ACL_PRIVATE);
            if (!$acct_update) {
                # Error, could not write the account file to the account bucket
                $dRetVal = '(no account update: ' . $acct_name . ')';
                break;
            }
            # Ok, account file has been written to the account bucket

            # Delete the registration file
            if (!$s3->deleteObject($regBkt, $reg_name)) {
                # Error, could not delete the registration file from the reg bucket
                $dRetVal = '(no registration delete: ' . $reg_name . ')';
                # Continue, this does not matter
            }

            # Ok, link file written to link bucket, account file written to account bucket, display success message
            $retVal = get_option('irc_success');
            break;

        # End of WHILE loop
        }

        # Exit
        if ('y' == $debug) {
            # Additional error information for debugging
            $retVal = $retVal . ' ' . $dRetVal;
        }
        echo $retVal;
        die(); # Required to return a proper result
    }
    # ----- End of Ajax Processing -----


    # ----- Javascript -----
    # JS that is added to top of page(s) containing widget
    # Posts widget text to the ajax func
    # Updates widget result field with ajax func return value
    add_action( 'wp_head', 'irchan_js_header' );
    function irchan_js_header() {
      $nonce = wp_create_nonce('irc_nonce');
      ?>
        <script type="text/javascript">
        //<![CDATA[
        jQuery(document).ready(function($) {
            jQuery('#irchan_button').click(function(){
                jQuery.ajax({
                    type: 'POST',
                    data: {
                        'action': 'irchan_widget_action',
                        'user_text': jQuery('#irchan_text').val(),
                        'nonce': '<?php echo $nonce; ?>'
                    },
                    dataType: 'html',
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    success: function(results,st) {
                        jQuery('#irchan_results').text(results);
                        if (results == '<?php echo get_option('irc_success'); ?>') {
                            jQuery('#irchan_text').attr('disabled','disabled');                            
                            jQuery('#irchan_button').attr('disabled','disabled');                            
                        }
                    }
                });
            });

        });
        //]]>
        </script>
      <?php
    }
    # ----- End of Javascript -----

?>
