jQuery(document ).on( 'click', '#arruan-attendee-player-and-eater', function(e) {
    e.preventDefault();
    sendArruanAttendeeStatus('player_and_eater', jQuery('#arruan-attendee-post-id').val());
});

jQuery(document ).on( 'click', '#arruan-attendee-player-only', function(e) {
    e.preventDefault();
    sendArruanAttendeeStatus('player_only', jQuery('#arruan-attendee-post-id').val());
});

jQuery(document ).on( 'click', '#arruan-attendee-no-player', function(e) {
    e.preventDefault();
    sendArruanAttendeeStatus('no_player', jQuery('#arruan-attendee-post-id').val());
});

function sendArruanAttendeeStatus(status, postId) {
    jQuery.ajax({
        url : arruan_attendee_post_url,
        method : 'POST',
        data : {
            action : 'arruan-attendee-post',
            value : status,
            postId : postId
        },
	success : function(resp) {
            if ( resp.success ) {
                jQuery('#arruan-attendee-player-count').text(resp.data.players);
                jQuery('#arruan-attendee-eater-count').text(resp.data.eaters);
                jQuery('#arruan-attendee-absent-count').text(resp.data.absents);
                var tableRow = '<tr>';
                tableRow += '<td>' + resp.data.user.name + '</td>';
                if (resp.data.user.playing === true) {
                    tableRow += '<td>Oui</td>';
                } else {
                    tableRow += '<td>Non</td>';
                }
                if (resp.data.user.eating === true) {
                    tableRow += '<td>Oui</td>';
                } else {
                    tableRow += '<td>Non</td>';
                }
                tableRow += '</tr>';

                jQuery('#arruan-attendee-table > tbody:last-child').append(tableRow);
                jQuery('#arruan-attendee-form').fadeOut(300, function() { jQuery(this).remove(); });
            }
	},
	error : function(resp) {
            jQuery( '#content' ).html("Erreur");
        }
    });
}

