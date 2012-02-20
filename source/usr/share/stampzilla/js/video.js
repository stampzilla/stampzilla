video = {
	players:new Array(),
	addPlayer:function(name) {
		video.removePlayer(name);
		video.players[video.players.length] = name;

		$('videoplayers').innerHTML += 
			'<div id="videoplayer_'+name+'">'+
			'<div class="radio play" onclick="sendJSON(\'to='+name+'&cmd=play\');">Play</div>'+
			'<div class="radio pause" onclick="sendJSON(\'to='+name+'&cmd=pause\');">Pause</div>'+
			'<div class="radio stop" onclick="sendJSON(\'to='+name+'&cmd=stop\');">Stop</div>'+
			'</div>';
		
		
		sendJSON("to="+name+"&cmd=media");
	},
	removePlayer:function(name) {
		if ( $('videoplayer_'+name) != undefined ) {
			$('videoplayer_'+name).dispose();
			$$('.videoplayer_'+name).dispose();
		}
	},
	setState:function(name,state) {
		$$('#videoplayer_'+name+' .radio').removeClass('true');

		if ( state.playing ) {
			if ( state.paused ) {
				$$('#videoplayer_'+name+' .pause').addClass('true');
			} else {
				$$('#videoplayer_'+name+' .play').addClass('true');
			}
		} else {
			$$('#videoplayer_'+name+' .stop').addClass('true');
		}
	},
	addMedia:function(name,movies){
		list = '';
		len = movies.length;
		$('movie_files').innerHTML = '';

		for(var a=0;a<len;a++) {
			list += '<a onClick="sendJSON(\'to='+name+'&cmd=PlayMovie&file='+movies[a].movieid+'\');"';
			
			if ( movies[a].thumbnail != undefined ) {
				list += ' style="background-image:url(resize.php?url='+movies[a].thumbnail+');"';
			}

			list += ' class="movie videoplayer_'+name;

			if ( movies[a].lastplayed != undefined ) {
				list += ' played';
			}						

			list += '"><div class="new">New</div>';
			for( field in movies[a] ) {
				value = eval('movies[a].'+field);

				if ( field == 'rating' )
					value = Math.round(value);

				list += '<div class="'+field+'">'+value+'</div>';
			}

			list += '</a>';
		}
		$('movie_files').innerHTML = list;
	}
}
