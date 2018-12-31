<?php
/*
Plugin Name: Arruan Attendee List
Description: Gestion de la liste des participants aux matchs et entraînements
Version: 0.1
Domain Path: /languages
*/

add_action( 'wp_enqueue_scripts', 'arruan_attendee_enqueue' );
function arruan_attendee_enqueue() {
    wp_enqueue_script( 'arruan-attendee-script-ajax', plugin_dir_url( __FILE__ )  . 'public/js/script.js', array('jquery') );
    wp_localize_script( 'arruan-attendee-script-ajax', 'arruan_attendee_post_url', admin_url('admin-ajax.php'));
}

add_action( 'wp_ajax_arruan-attendee-post', 'arruan_attendee_post_action');
//add_action( 'wp_ajax_nopriv_arruan-attendee-post', 'arruan_attendee_post_action');


function arruan_attendee_post_action() {

    $currentUser = wp_get_current_user();
    if ($currentUser->ID == 0){
        wp_send_json_error('Operation not permitted');
    }

    $value = $_POST['value'];
    $postId = $_POST['postId'];

    if (!isset($value) || !isset($postId)) {
        wp_send_json_error('Invalid params');
    }

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
            }
        }
    }
    update_post_meta($postId, 'arruanAttendees', json_encode($newAttendees));

    $ret = array();
    $ret['players'] = $playerCount;
    $ret['eaters'] = $eaterCount;
    $ret['absents'] = $absentCount; 

    $user = array();

    $user['name'] = $newAttendee['userName'];
    $user['playing'] = $newAttendee['playing'];
    $user['eating'] = $newAttendee['eating'];

    $ret['user'] = $user;

    wp_send_json_success($ret);
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
    $userArray = [];
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
            $user = [];
            $user['name'] = $attendee['userName'];
            $user['playing'] = $attendee['playing'];
            $user['eating'] = $attendee['eating'];
            array_push($userArray, $user);

            if ($current_user->ID === $attendee['userId']) {
                $currentUserIsAnAttendee = true;
            }
        }
    }

    $displayForm = $current_user->ID != 0 && !$currentUserIsAnAttendee;

    // begin output buffering
    ob_start();
    if ($displayForm === true) {
        ?>
        <p>
        <form action="" id="arruan-attendee-form">
            <input type="submit" id="arruan-attendee-player-and-eater" value="Je viens et je mange"/>
            <input type="submit" id="arruan-attendee-player-only" value="Je viens seulement"/>
            <input type="submit" id="arruan-attendee-no-player" value="J'abandonne les copains"/>
            <input id="arruan-attendee-post-id" type="hidden" value="<?php echo $postid ?>">
        </form>
        </p>
        <?php
    }
    ?>
    <p>
    <strong><span id="arruan-attendee-player-count"><?php echo $playerCount ?></span> Joueur(s) | <span id="arruan-attendee-eater-count"><?php echo $eaterCount ?></span> Glouton(s) | <span id="arruan-attendee-absent-count"><?php echo $absentCount ?></span> Absent(s)</strong>
    </p>
    <p>
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
        foreach ($userArray as $user) {
            ?>
            <tr><td><?php echo $user['name']; ?></td><td><?php echo $user['playing'] ? 'Oui' : 'Non' ?></td><td><?php echo $user['eating'] ? 'Oui' : 'Non' ?></td></tr>
            <?php
        }
        ?>
        </tbody>
    </table>
    </p>
    <?php
    // end output buffering, grab the buffer contents, and empty the buffer
    return ob_get_clean();
}
add_shortcode('arruan_attendee_form', 'arruan_display_attendee_content');

