<?php
/*
Plugin Name: Arruan Attendee List
Description: Gestion de la liste des participants aux matchs et entraînements
Version: 0.1
Domain Path: /languages
*/

add_action( 'wp_enqueue_scripts', 'arruan_attendee_enqueue' );
function arruan_attendee_enqueue() {
    wp_enqueue_script('arruan-attendee-script-ajax', plugin_dir_url( __FILE__ )  . 'public/js/script.js', array('jquery') );
    wp_localize_script('arruan-attendee-script-ajax', 'arruan_attendee_post_url', admin_url('admin-ajax.php'));
    wp_register_style('arruan-attendee-style', plugin_dir_url( __FILE__ )  . 'public/css/style.css');
    wp_enqueue_style('arruan-attendee-style');
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
    $friendCount = $_POST['friends'];

    if (!isset($value) || !isset($postId) || !isset($friendCount)) {
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
        $absentCount = 1;
    }

    if ($friendCount > 0) {
        $playerCount += $friendCount;
        $friends = [];
        for ($i = 0; $i < $friendCount; $i++) {
            $friend = [];
            // TODO: manage eating of friends
            $friend['eating'] = false;
            array_push($friends, $friend);
        }
        $newAttendee['friends'] = $friends;
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
    update_post_meta($postId, 'arruanAttendees', json_encode($newAttendees));

    $ret = array();
    $ret['players'] = $playerCount;
    $ret['eaters'] = $eaterCount;
    $ret['absents'] = $absentCount;
    $ret['attendeeId'] = $newAttendee['userId'];
    $ret['html'] = arruan_get_attendee_table_content($newAttendee);

    wp_send_json_success($ret);
}

function arruan_get_attendee_table_content($attendee) {
    $ret = "<tr id='arruan-attendee-".$attendee['userId']."'><td>".$attendee['userName']."</td><td>".($attendee['playing'] ? 'Oui' : 'Non')."</td><td>".($attendee['eating'] ? 'Oui' : 'Non')."</td></tr>";
    $friends = $attendee['friends'];
    if (isset($friends)) {
        foreach($friends as $idx=>$friend) {
            $ret .= "<tr id='arruan-attendee-".$attendee['userId']."-friend-".$idx."'><td>Pote &laquo;&nbsp;à&nbsp;&raquo; ".$attendee['userName']."</td><td>Oui</td><td>".($friend['eating'] ? 'Oui' : 'Non')."</td></tr>";
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

    $displayForm = $current_user->ID != 0 && !$currentUserIsAnAttendee;
    $displayOpinionChangeLink = $current_user->ID != 0 && $currentUserIsAnAttendee;

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
    <form action="" id="arruan-attendee-opinion-change-form" style="display:none">
        <select id="arruan-attendee-opinion-change-status">
            <option value="player_and_eater">Je viens et je mange</option>
            <option value="player_only">Je viens seulement</option>
            <option value="no_player">J'abandonne les copains</option>
        </select>
        <input type="number" id="arruan-attendee-opinion-change-friends" placeholder="Des amis à amener ?"/>
        <input type="submit" id="arruan-attendee-opinion-change-submit" value="Valider"/>
        <input id="arruan-attendee-opinion-change-post-id" type="hidden" value="<?php echo $postid ?>">
    <form>
    <?php
    // end output buffering, grab the buffer contents, and empty the buffer
    return ob_get_clean();
}
add_shortcode('arruan_attendee_form', 'arruan_display_attendee_content');

