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

// Do not use submit event due to comment subscription button conflict
jQuery(document).on('click', '#arruan-attendee-opinion-change-submit', function(e) {
    e.preventDefault();

    var playingAndEatingFriends = new Array();
    var playingOnlyFriends = new Array();

    var json = jQuery('#arruan-attendee-opinion-change-friends-eating').val();
    if ("" != json) {
        var valueItems = JSON.parse(json);
        for (i = 0; i < valueItems.length; i++) {
            playingAndEatingFriends.push(valueItems[i]['value']);
        }    
    }
    
    json = jQuery('#arruan-attendee-opinion-change-friends-playing-only').val();
    if ("" != json) {
        valueItems = JSON.parse(json);    
        for (i = 0; i < valueItems.length; i++) {
            playingOnlyFriends.push(valueItems[i]['value']);
        }
    }

    sendArruanAttendeeStatus(
        jQuery('#arruan-attendee-opinion-change-status').val(), 
        jQuery('#arruan-attendee-opinion-change-post-id').val(), 
        playingAndEatingFriends,
        playingOnlyFriends
    );
});

jQuery(document).ready(function() {
    jQuery('#arruan-attendee-opinion-change-friends-eating').tagify({
        delimiters          : ","
    }).on('add', function(e, tag){
        jQuery(tag.tag.parentElement).children('div').addClass('empty_placeholder');
    }).on('remove', function(e, tag){
        if (JSON.parse(this.value).length == 0) {
            jQuery(tag.tag.parentElement).children('div').removeClass('empty_placeholder');
        }
    });
});

jQuery(document).ready(function() {
    jQuery('#arruan-attendee-opinion-change-friends-playing-only').tagify({
        delimiters          : ","
    }).on('add', function(e, tag){
        jQuery(tag.tag.parentElement).children('div').addClass('empty_placeholder');
    }).on('remove', function(e, tag){
        if (JSON.parse(this.value).length == 0) {
            jQuery(tag.tag.parentElement).children('div').removeClass('empty_placeholder');
        }
    });
});

function sendArruanAttendeeStatus(status, postId, playingAndEatingFriends = [], playingOnlyFriends = []) {
    jQuery.ajax({
        url : arruan_attendee_post_url,
        method : 'POST',
        data : {
            'action' : 'arruan-attendee-post',
            'value' : status,
            'postId' : postId,
            'eatingFriends' : playingAndEatingFriends.join("|"),
            'playingOnlyFriends' : playingOnlyFriends.join("|")
        },
	success : function(resp) {
            if ( resp.success ) {
                jQuery('#arruan-attendee-player-count').text(resp.data.players);
                jQuery('#arruan-attendee-eater-count').text(resp.data.eaters);
                jQuery('#arruan-attendee-absent-count').text(resp.data.absents);

                jQuery('#arruan-attendee-'+ resp.data.attendeeId).remove();
                jQuery('[id^="arruan-attendee-'+ resp.data.attendeeId + '-friend"]').remove();

                jQuery('#arruan-attendee-table > tbody').prepend(jQuery.parseHTML(resp.data.html));
                jQuery('#arruan-attendee-form').fadeOut(300, function() { jQuery(this).remove(); });
                jQuery('#arruan-attendee-opinion-change-container').show();
                jQuery('#arruan-attendee-opinion-change-form').hide();
                jQuery('#arruan-attendee-opinion-change-friends-eating').data('tagify').removeAllTags();
                jQuery('#arruan-attendee-opinion-change-friends-playing-only').data('tagify').removeAllTags();
            }
	},
	error : function(resp) {
            jQuery( '#content' ).html("Erreur");
        }
    });
}

