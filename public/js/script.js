jQuery(document).on( 'click', '#arruan-attendee-player-and-eater', function(e) {
    e.preventDefault();
    sendArruanAttendeeStatus('player_and_eater', jQuery('#arruan-attendee-post-id').val());
});

jQuery(document).on( 'click', '#arruan-attendee-player-only', function(e) {
    e.preventDefault();
    sendArruanAttendeeStatus('player_only', jQuery('#arruan-attendee-post-id').val());
});

jQuery(document).on( 'click', '#arruan-attendee-no-player', function(e) {
    e.preventDefault();
    sendArruanAttendeeStatus('no_player', jQuery('#arruan-attendee-post-id').val());
});

jQuery(document).on('click', '#arruan-attendee-opinion-change-link', function(e) {
    e.preventDefault();
    jQuery('#arruan-attendee-opinion-change-form').toggle();
});

jQuery(document).on('submit', '#arruan-attendee-opinion-change-form', function(e) {
    e.preventDefault();
    sendArruanAttendeeStatus(jQuery('#arruan-attendee-opinion-change-status').val(), jQuery('#arruan-attendee-opinion-change-post-id').val(), jQuery('#arruan-attendee-opinion-change-friends').val());
});

function sendArruanAttendeeStatus(status, postId, friends = 0) {
    jQuery.ajax({
        url : arruan_attendee_post_url,
        method : 'POST',
        data : {
            'action' : 'arruan-attendee-post',
            'value' : status,
            'postId' : postId,
            'friends' : friends
        },
	success : function(resp) {
            if ( resp.success ) {
                jQuery('#arruan-attendee-player-count').text(resp.data.players);
                jQuery('#arruan-attendee-eater-count').text(resp.data.eaters);
                jQuery('#arruan-attendee-absent-count').text(resp.data.absents);

                jQuery('#arruan-attendee-'+ resp.data.attendeeId).remove();
                jQuery('[id^="arruan-attendee-'+ resp.data.attendeeId + '-friend"]').remove();

                jQuery('#arruan-attendee-table > tbody:last-child').append(jQuery.parseHTML(resp.data.html));
                jQuery('#arruan-attendee-form').fadeOut(300, function() { jQuery(this).remove(); });
                jQuery('#arruan-attendee-opinion-change-container').show();
                jQuery('#arruan-attendee-opinion-change-form').hide();
            }
	},
	error : function(resp) {
            jQuery( '#content' ).html("Erreur");
        }
    });
}

