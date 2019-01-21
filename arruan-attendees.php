<?php
/*
Plugin Name: Arruan Attendee List
Description: Gestion de la liste des participants aux matchs et entraînements
Version: 0.1
Domain Path: /languages
*/


/*
    Mailing management

*/
add_action( 'arruan_attendee_cron_set_event_reminder_scheduledates_hook', 'arruan_attendee_cron_set_event_reminder_scheduledates_exec' );
add_action( 'arruan_attendee_cron_send_first_event_reminder_email_hook', 'arruan_attendee_cron_send_first_event_reminder_email_exec' );
add_action( 'arruan_attendee_cron_send_second_event_reminder_email_hook', 'arruan_attendee_cron_send_second_event_reminder_email_exec' );

if( !wp_next_scheduled( 'arruan_attendee_cron_set_event_reminder_scheduledates_hook' ) ) {
    wp_schedule_event( time(), 'hourly', 'arruan_attendee_cron_set_event_reminder_scheduledates_hook' );
}

if( !wp_next_scheduled( 'arruan_attendee_cron_send_first_event_reminder_email_hook' ) ) {
    wp_schedule_event( time(), 'hourly', 'arruan_attendee_cron_send_first_event_reminder_email_hook' );
}

if( !wp_next_scheduled( 'arruan_attendee_cron_send_second_event_reminder_email_hook' ) ) {
    wp_schedule_event( time(), 'hourly', 'arruan_attendee_cron_send_second_event_reminder_email_hook' );
}

/**
    Applies reminder schedule dates to event posts
*/
function arruan_attendee_cron_set_event_reminder_scheduledates_exec() {
    arruan_attendee_debug("Retrieving 20 posts without reminder schedule dates");
    // Retrievs posts whitout reminder schedule dates
    $postQuery = array (
        'post_type' => 'event',
        'posts_per_page' => 20,
        'meta_query' => array(
            'relation' => 'OR',
            array (
                'key'     => 'arruan_attendee_reminder_first_schedule_date',
                'compare' => 'NOT EXISTS'
            ),
            array (
                'key'     => 'arruan_attendee_reminder_second_schedule_date',
                'compare' => 'NOT EXISTS'
            )
        )
    );    
    
    $posts = get_posts($postQuery);
    arruan_attendee_debug("Found " . sizeof($posts) . " posts");
    foreach ( $posts as $post ) {
        arruan_attendee_debug("Setting schedule dates to post " . $post->ID . "/" . $post->post_title);
        // Setting schedule dates (UTC)
        $eventStartDate = get_post_custom_values('_event_start_date', $post->ID)[0];
        
        update_post_meta($post->ID, 'arruan_attendee_reminder_first_schedule_date', (new DateTime($eventStartDate))->sub(new DateInterval('P6D'))->format('Y-m-d'));
        update_post_meta($post->ID, 'arruan_attendee_reminder_first_status', 'init');
        update_post_meta($post->ID, 'arruan_attendee_reminder_second_schedule_date', (new DateTime($eventStartDate))->sub(new DateInterval('P1D'))->format('Y-m-d'));
        update_post_meta($post->ID, 'arruan_attendee_reminder_second_status', 'init');
    }
}

/** 

    Sends reminder emails
*/
function arruan_attendee_cron_send_first_event_reminder_email_exec() {
    arruan_attendee_cron_send_event_reminder_email_exec('first');
}

function arruan_attendee_cron_send_second_event_reminder_email_exec() {
    arruan_attendee_cron_send_event_reminder_email_exec('second');   
}

function arruan_attendee_cron_send_event_reminder_email_exec($level) {  
    arruan_attendee_debug("Sending reminder emails (". $level .")");

    // Retrieves event to remind (UTC)
    $now = new DateTime('now');
    $postQuery = array (
        'post_type' => 'event',
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key'     => 'arruan_attendee_reminder_'. $level .'_schedule_date',
                'value'   => $now->format('Y-m-d'),
                'compare' => '=',
                'type'    => 'DATE'
            ),
            array(
                'key'     => 'arruan_attendee_reminder_'. $level .'_status',
                'value'   => 'init',
                'compare' => '='
            )
        )
    ); 

    $posts = get_posts($postQuery);
    arruan_attendee_debug("Found " . sizeof($posts) . " posts");

    if (sizeof($posts) > 0) {
        // Retrieves candidate users 
        $userQuery = array (
        'meta_query' => array(
            'relation' => 'AND',
            array (
                'key'     => 'arruan_attendee',
                'value'   => 'true',
                'compare' => '='
            ),
            array(
                'key'     => 'arruan_attendee_reminder',
                'value'   => 'true',
                'compare' => '='
                )
            )
        );

        $users = get_users($userQuery);

        $userMap = array();
        $userIds = array();
        
        arruan_attendee_debug("Found " . sizeof($users) . " candidate users");
        foreach ( $users as $user ) {
            $userMap[$user->ID] = $user;
            $userIds[] = $user->ID;
            arruan_attendee_debug($user->user_nicename);
        }

        foreach ( $posts as $post ) {
            arruan_attendee_debug("Processing reminder for event " . $post->ID . " - " . $post->post_title);

            // Prepares attendee's id list
            $attendeeIds = array();
            $attendeesArray = get_post_custom_values('arruanAttendees', $post->ID);
            if (isset($attendeesArray)) {
                $attendees = json_decode($attendeesArray[0], true);
                foreach ($attendees as $attendee) {
                    $attendeeIds[] = $attendee['userId'];
                }
            }

            arruan_attendee_debug("Post has ". sizeof($attendeeIds) . " attendees");
            $reminderUserIds = array_diff($userIds, $attendeeIds);

            arruan_attendee_debug("Need to send reminder to ". sizeof($reminderUserIds) . " users");

            foreach ( $reminderUserIds as $id ) {
                arruan_attendee_debug("Sending reminder to user " . $id);
                $userToRemind = $userMap[$id];

                arruan_attendee_send_remind_email($userToRemind->user_email, $post->post_title, new DateTime(get_post_custom_values('_event_start_date', $post->ID)[0]), get_permalink($post->ID));
            }

            arruan_attendee_debug("Saving post reminder status");
            $currentStatus = get_post_custom_values('arruan_attendee_reminder_'.$level.'_status', $post->ID)[0];

            update_post_meta($post->ID, 'arruan_attendee_reminder_'.$level.'_status', 'done');

            arruan_attendee_debug("Post reminder status saved");

        }

        arruan_attendee_debug("All reminder are sent");

    }
}

function arruan_attendee_send_remind_email($target, $eventTitle, $eventDate, $eventLink) {
    arruan_attendee_debug("Sending remind to " . $target);

    $options = get_option('arruan_attendee_options');

    // See https://sendgrid.com/docs/ui/sending-email/how-to-send-an-email-with-dynamic-transactional-templates/#additional-resources for json construction
    $data = array();
    $data['template_id'] = $options['arruan_attendee_sendgrid_template_id'];
    $from = array(
        'email' => $options['arruan_attendee_sendgrid_from']
    );
    $data['from'] = $from;

    $personalization = array();
    $personalization['subject'] = $eventTitle;
    $tos = array();
    $to = array(
        'email' => $target
    );
    $tos[] = $to;

    $personalization['to'] = $tos;

    $templateData = array();

    $formattedDate = date_i18n('l j F Y', $eventDate->getTimestamp());

    $event = array(
        'title' => $eventTitle,
        'date' => $formattedDate,
        'link' => $eventLink
    );

    $templateData['event'] = $event;
    $personalization['dynamic_template_data'] = $templateData;

    $personalizations = array();
    array_push($personalizations, $personalization);

    $data['personalizations'] = $personalizations;

    $payload = json_encode($data, JSON_UNESCAPED_SLASHES);

    arruan_attendee_debug("Calling Sendgrid API: ". $payload);

    
    // Prepare new cURL resource
    $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLINFO_HEADER_OUT, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    // Set HTTP Header for POST request 
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload),
        'Authorization: Bearer '. $options['arruan_attendee_sendgrid_api_key'])
    );

    // Submit the POST request
    $result = curl_exec($ch);

    // Vérification si une erreur est survenue
    if(curl_errno($ch)) {
        $info = curl_getinfo($ch);
        arruan_attendee_error("Unable to send email to " . $target . ". Error code: " . $info['http_code']);
    } else {
        $resultArray = json_decode($result, true);

        if (isset($resultArray['errors'])) {
            arruan_attendee_error("Unable to send email to " . $target . ": " . $result);
        } else {
            arruan_attendee_debug("Email sent to ". $target);    
        }
    }

    // Close cURL session handle
    curl_close($ch); 
    
}

/**

    Attendee form management

*/
add_action( 'wp_enqueue_scripts', 'arruan_attendee_enqueue' );
function arruan_attendee_enqueue() {
    wp_enqueue_script('arruan-attendee-script', plugin_dir_url( __FILE__ )  . 'public/js/script.js', array('jquery') );
    wp_localize_script('arruan-attendee-script', 'arruan_attendee_post_url', admin_url('admin-ajax.php'));
    wp_enqueue_script('arruan-attendee-script-tagify', plugin_dir_url( __FILE__ )  . 'public/js/tagify.min.js');
    
    wp_register_style('arruan-attendee-style', plugin_dir_url( __FILE__ )  . 'public/css/style.css');
    wp_enqueue_style('arruan-attendee-style');
    wp_register_style('arruan-attendee-style-tagify', plugin_dir_url( __FILE__ )  . 'public/css/tagify.css');
    wp_enqueue_style('arruan-attendee-style-tagify');
}

add_action( 'wp_ajax_arruan-attendee-post', 'arruan_attendee_post_action');
//add_action( 'wp_ajax_nopriv_arruan-attendee-post', 'arruan_attendee_post_action');


function arruan_attendee_post_action() {

    $currentUser = wp_get_current_user();
    // Operation not permitted if user is not logged in nor allowed to participate
    if ($currentUser->ID == 0 || 'true' != get_user_meta($currentUser->ID, 'arruan_attendee', true)){
        wp_send_json_error('Operation not permitted');
    }

    $value = $_POST['value'];
    $postId = $_POST['postId'];

    arruan_attendee_debug("Posting attendee details on post ". $postId .": " . $value . "[eating: " . $_POST['eatingFriends'] . ", playingOnly:" .$_POST['eatingFriends']."]");
    

    if (!isset($value) || !isset($postId)) {
        wp_send_json_error('Invalid params');
    }

    $playingAndEatingFriends = [];
    if (!empty($_POST['eatingFriends'])) {
        $playingAndEatingFriends = explode("|", $_POST['eatingFriends']);
    }
    $playingOnlyFriends = [];
    if (!empty($_POST['playingOnlyFriends'])) {
        $playingOnlyFriends = explode("|", $_POST['playingOnlyFriends']);
    }

    $friendCount = sizeof($playingAndEatingFriends) + sizeof($playingOnlyFriends);

    $playerCount = 0;
    $eaterCount = 0;
    $absentCount = 0;

    $newAttendee = [];
    $newAttendee['userId'] = $currentUser->ID;
    $newAttendee['userName'] = $currentUser->display_name;
    if ($value === 'player_and_eater') {
        $newAttendee['playing'] = true;
        $newAttendee['eating'] = true;
        $playerCount = 1;
        $eaterCount = 1;
    } else if ($value === 'player_only') {
        $newAttendee['playing'] = true;
        $newAttendee['eating'] = false;
        $playerCount = 1;
    } else {
        $newAttendee['playing'] = false;
        $newAttendee['eating'] = false;
        $absentCount = 1;
    }

    if ($friendCount > 0) {
        $playerCount += $friendCount;
        $friendLists = [];

        foreach ($playingAndEatingFriends as $friendName) {
            $friend = [];
            $friend['name'] = htmlspecialchars($friendName);
            $friend['eating'] = true;
            array_push($friendLists, $friend);
        }
        foreach ($playingOnlyFriends as $friendName) {
            $friend = [];
            $friend['name'] = htmlspecialchars($friendName);
            $friend['eating'] = false;
            array_push($friendLists, $friend);
        }
        $newAttendee['friends'] = $friendLists;
    }


    $newAttendees = [$newAttendee];
    $attendeesArray = get_post_custom_values('arruanAttendees', $postId);

    if (isset($attendeesArray)) {
        $attendees = json_decode($attendeesArray[0], true);
        foreach ($attendees as $attendee) {
            if ($attendee['userId'] != $currentUser->ID) {
                array_push($newAttendees, $attendee);
                if ($attendee['playing']) {
                    $playerCount++;
                } else {
                    $absentCount++;
                }
                if ($attendee['playing']) {
                    $eaterCount++;
                }
                $attendeeFriends = $attendee['friends'];
                if (isset($attendeeFriends)) {
                    $playerCount += count($attendeeFriends);
                    // TODO: manage eating of friends
                }
            }
        }
    }
    update_post_meta($postId, 'arruanAttendees', json_encode($newAttendees, JSON_UNESCAPED_UNICODE));

    $ret = array();
    $ret['players'] = $playerCount;
    $ret['eaters'] = $eaterCount;
    $ret['absents'] = $absentCount;
    $ret['attendeeId'] = $newAttendee['userId'];
    $ret['html'] = arruan_get_attendee_table_content($newAttendee);

    wp_send_json_success($ret);
}

function arruan_get_attendee_table_content($attendee) {
    $userNameHtml = $attendee['playing'] || $attendee['eating'] ? stripslashes($attendee['userName']) : "<s>".stripslashes($attendee['userName'])."</s>";
    $ret = "<tr id='arruan-attendee-".$attendee['userId']."'><td>".$userNameHtml."</td><td>".($attendee['playing'] ? 'Oui' : 'Non')."</td><td>".($attendee['eating'] ? 'Oui' : 'Non')."</td></tr>";
    $friends = $attendee['friends'];
    if (isset($friends)) {
        foreach($friends as $idx=>$friend) {
            $ret .= "<tr id='arruan-attendee-".$attendee['userId']."-friend-".$idx."'><td>".stripslashes($friend['name'])."</td><td>Oui</td><td>".($friend['eating'] ? 'Oui' : 'Non')."</td></tr>";
        }
    } 
    return $ret;
}

function arruan_display_attendee_content($atts) {
    global $post;
    extract(shortcode_atts(
        array(
            'postid' => $post->ID,
	), $atts, 'arruan_attendee_form'));
       
    $current_user = wp_get_current_user();

    $playerCount = 0;
    $eaterCount = 0;
    $absentCount = 0;
    $attendees = [];
    $currentUserIsAnAttendee = false;

    $attendeesArray = get_post_custom_values('arruanAttendees', $postid);
    if (isset($attendeesArray)) {
        $attendees = json_decode($attendeesArray[0], true);
        foreach ($attendees as $attendee) {
            if ($attendee['playing']) {
                $playerCount++;
            } else {
                $absentCount++;
            }
            if ($attendee['eating']) {
                $eaterCount++;
            }
            if ($current_user->ID === $attendee['userId']) {
                $currentUserIsAnAttendee = true;
            }
            $friends = $attendee['friends'];
            if (isset($friends)) {
                foreach($friends as $friend) {
                    $playerCount++;
                    $eaterCount += ($friend['eating'] ? 1 : 0);
                }
            }
        }
    }
    $isAllowedToBeAnAttendee = 'true' == get_user_meta($current_user->ID, 'arruan_attendee', true);
    $displayForm = $current_user->ID != 0 && !$currentUserIsAnAttendee && $isAllowedToBeAnAttendee;
    $displayOpinionChangeLink = $current_user->ID != 0 && $currentUserIsAnAttendee && $isAllowedToBeAnAttendee;

    // begin output buffering
    ob_start();
    if ($displayForm === true) {
        ?>
        <form action="" id="arruan-attendee-form">
            <input type="submit" id="arruan-attendee-player-and-eater" value="Je viens et je mange"/>
            <input type="submit" id="arruan-attendee-player-only" value="Je viens seulement"/>
            <input type="submit" id="arruan-attendee-no-player" value="J'abandonne les copains"/>
            <input id="arruan-attendee-post-id" type="hidden" value="<?php echo $postid ?>">
        </form>
        <br style="clear:both">
        <?php
    }
    ?>
    <p>
    <strong><span id="arruan-attendee-player-count"><?php echo $playerCount ?></span> Joueur(s) | <span id="arruan-attendee-eater-count"><?php echo $eaterCount ?></span> Glouton(s) | <span id="arruan-attendee-absent-count"><?php echo $absentCount ?></span> Absent(s)</strong>
    </p>
    <table id="arruan-attendee-table" >
        <thead>
	<tr>
            <th>Joueur</th>
            <th>Présent</th>
            <th>Repas</th>
        <tr>
        </thead>
        <tbody>
        <?php
        foreach ($attendees as $attendee) {
            echo arruan_get_attendee_table_content($attendee);
        }
        ?>
        </tbody>
    </table>
    <br style="clear:both">
    <p id="arruan-attendee-opinion-change-container" style="<?php echo ($displayOpinionChangeLink ? '': 'display:none'); ?>">
        <a id="arruan-attendee-opinion-change-link" href="#">Je change d'avis</a>
    </p>
    <!-- Not a form to avoid comment subscription conflict -->
    <p id="arruan-attendee-opinion-change-form" style="display:none">
        <select id="arruan-attendee-opinion-change-status">
            <option value="player_and_eater">Je viens et je mange</option>
            <option value="player_only">Je viens seulement</option>
            <option value="no_player">J'abandonne les copains</option>
        </select>
        <textarea id="arruan-attendee-opinion-change-friends-eating" placeholder="Des amis qui jouent et restent manger ?"></textarea>
        <textarea id="arruan-attendee-opinion-change-friends-playing-only" placeholder="Des amis qui jouent mais partent avant la 3eme ?"></textarea>
        <input type="button" id="arruan-attendee-opinion-change-submit" value="Valider"/>
        <input id="arruan-attendee-opinion-change-post-id" type="hidden" value="<?php echo $postid ?>">
    <p/>
    <?php
    // end output buffering, grab the buffer contents, and empty the buffer
    return ob_get_clean();
}
add_shortcode('arruan_attendee_form', 'arruan_display_attendee_content');

function display_arruan_attendee_fields( $user ) { 
    $isAttendee = 'true' == get_user_meta($user->ID, 'arruan_attendee', true);
    $needAttendeeReminder = $isAttendee && 'true' == get_user_meta($user->ID, 'arruan_attendee_reminder', true);
   
    if ($isAttendee || current_user_can( 'edit_users', $user->ID )) {
        ?>
        <h3>Club des Arruanais</h3>
        <?php
    }
    ?>
    <table class="form-table">
    <?php
    if (current_user_can( 'edit_users', $user->ID )) {
        ?>
        <tr >
            <th scope="row">Participe aux évènements</th>
            <td>
                <fieldset>
                    <legend class="screen-reader-text"><span>Participe aux évènements</span></legend>
                    <label for="arruan_attendee">
                        <input name="arruan_attendee" type="checkbox" id="arruan_attendee" <?php echo $isAttendee ? 'checked' : '' ?>>
                        Affiche les boutons de participations aux évènements (entraînements, matchs)
                    </label>
                    <br>
                </fieldset>
            </td>
        </tr>   
        <?php
    }
    if ( wp_get_current_user()->ID == $user->ID && $isAttendee) {
        ?>
        <tr >
            <th scope="row">Rappels d'évènements</th>
            <td>
                <fieldset>
                    <legend class="screen-reader-text"><span>Recevoir les rappels de participation</span></legend>
                    <label for="arruan_attendee_reminder">
                        <input name="arruan_attendee_reminder" type="checkbox" id="arruan_attendee_reminder" <?php echo $needAttendeeReminder ? 'checked' : '' ?>>
                        Vous recevrez des emails vous rappelant de vous inscrire aux évènements du club (entraînements, matchs)
                    </label>
                    <br>
                </fieldset>
            </td>
        </tr>
        <?php
    }
    ?>
    </table> 
    <?php
}

add_action( 'edit_user_profile', 'display_arruan_attendee_fields' );
add_action( 'show_user_profile', 'display_arruan_attendee_fields' );

function save_arruan_attendee_admin_fields( $user_id ) {
    if (!current_user_can( 'edit_users', $user_id )) { 
        return false; 
    }
    update_user_meta( $user_id, 'arruan_attendee', isset($_POST["arruan_attendee"]) && 'on' == $_POST['arruan_attendee'] ? 'true' : 'false');
    // If reminder status is not set. Default to true
    if (empty(get_user_meta($user_id, 'arruan_attendee_reminder'))) {
        update_user_meta( $user_id, 'arruan_attendee_reminder', 'true');
    }
}

add_action( 'edit_user_profile_update', 'save_arruan_attendee_admin_fields' );

function save_arruan_attendee_profile_fields( $user_id ) {
    if (current_user_can( 'edit_users', $user_id )) { 
        update_user_meta( $user_id, 'arruan_attendee', isset($_POST["arruan_attendee"]) && 'on' == $_POST['arruan_attendee'] ? 'true' : 'false');
    }
    
    update_user_meta( $user_id, 'arruan_attendee_reminder', isset($_POST["arruan_attendee_reminder"]) && 'on' == $_POST['arruan_attendee_reminder'] ? 'true' : 'false');
}

add_action( 'personal_options_update', 'save_arruan_attendee_profile_fields' );


/**

    Settings page

*/

if (is_admin()) {
    add_action('admin_init', 'arruan_attendee_admin_init' );
    add_action('admin_menu', 'arruan_attendee_admin_menu');    
}


// Register our settings. Add the settings section, and settings fields
function arruan_attendee_admin_init(){
    register_setting('arruan_attendee_options', 'arruan_attendee_options', 'arruan_attendee_admin_validate');
    add_settings_section('arruan_attendee_main_section', 'Envoi du rappel d\'inscription par email', 'arruan_attendee_main_section_callback', __FILE__);
    add_settings_field('arruan_attendee_sendgrid_from', 'Email expéditeur*', 'arruan_attendee_admin_sendgrid_from_callback', __FILE__, 'arruan_attendee_main_section');
    add_settings_field('arruan_attendee_sendgrid_api_key', 'Clef API Sendgrid*', 'arruan_attendee_admin_sendgrid_api_key_callback', __FILE__, 'arruan_attendee_main_section');
    add_settings_field('arruan_attendee_sendgrid_template_id', 'ID template de mail Sendgrid*', 'arruan_attendee_admin_sendgrid_template_id_callback', __FILE__, 'arruan_attendee_main_section');
}

function arruan_attendee_main_section_callback() {

}

function arruan_attendee_admin_sendgrid_from_callback() {
    $options = get_option('arruan_attendee_options');
    echo "<input required id='arruan_attendee_sendgrid_from' name='arruan_attendee_options[arruan_attendee_sendgrid_from]' size='40' type='text' value='".$options['arruan_attendee_sendgrid_from']."' />";
}

function arruan_attendee_admin_sendgrid_api_key_callback() {
    $options = get_option('arruan_attendee_options');
    echo "<input required id='arruan_attendee_sendgrid_api_key' name='arruan_attendee_options[arruan_attendee_sendgrid_api_key]' size='40' type='text' value='".$options['arruan_attendee_sendgrid_api_key']."' />";
}

function arruan_attendee_admin_sendgrid_template_id_callback() {
    $options = get_option('arruan_attendee_options');
    echo "<input required id='arruan_attendee_sendgrid_template_id' name='arruan_attendee_options[arruan_attendee_sendgrid_template_id]' size='40' type='text' value='".$options['arruan_attendee_sendgrid_template_id']."' />";
}

// Add sub page to the Settings Menu
function arruan_attendee_admin_menu() {
    // add optiont to main settings panel
    add_options_page('Inscription des Arruanais', 'Inscription des Arruanais', 'administrator', __FILE__, 'arruan_attendee_admin_menu_callback');
}

// Display the admin options page
function arruan_attendee_admin_menu_callback() {
    ?>
        <div class="wrap">
            <div class="icon32" id="icon-options-general"><br></div>
            <h2>Inscriptions des Arruanais</h2>
            Cette page permet de paramétrer la gestion du module d'inscription des Arruanais.
            <form action="options.php" method="post">
                        
            <?php settings_fields('arruan_attendee_options'); ?>
            <?php do_settings_sections(__FILE__); ?>
            <p class="submit">
                <input name="Submit" type="submit" class="button-primary" value="Enregistrer les modifications" />
            </p>
            </form>
        </div>
    <?php
}

// Validate user data for some/all of your input fields
function arruan_attendee_admin_validate($input) {
    // Check our textboxes option field contains no HTML tags - if so strip them out
    $input['arruan_attendee_sendgrid_from'] =  wp_filter_nohtml_kses($input['arruan_attendee_sendgrid_from']);  
    $input['arruan_attendee_sendgrid_api_key'] =  wp_filter_nohtml_kses($input['arruan_attendee_sendgrid_api_key']);  
    $input['arruan_attendee_sendgrid_template_id'] =  wp_filter_nohtml_kses($input['arruan_attendee_sendgrid_template_id']);  
    return $input; // return validated input
}



function arruan_attendee_debug($message) {
    if (WP_DEBUG === true) {
        if (is_array($message) || is_object($message)) {
            error_log(print_r($message, true));
        } else {
            error_log($message);
        }
    }
}

function arruan_attendee_error($message) {
    if (is_array($message) || is_object($message)) {
        error_log(print_r($message, true));
    } else {
        error_log($message);
    }
}
