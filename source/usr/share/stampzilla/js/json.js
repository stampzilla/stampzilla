
communicationReady = function(){
	sendJSON("type=hello");
	//Fetch a list of all rooms from logic deamon
	sendJSON("to=logic&cmd=rooms");
	setTimeout(scrollTo, 0, 0, 1);
}

sendJSON = function(url) {
	new Request({
		url: "send.php?"+url
	}).send();
}

incoming = function( json ) {
	//$('page_rooms').innerHTML += "<br><br><br>"+json;
	pkt = eval('('+json+')');

	// Coomands
	if ( pkt.cmd != undefined ) {
		switch( pkt.cmd ) {
			case 'greetings':
				settings.addComponent(pkt.from,pkt.class,pkt.settings);

				for (c in pkt.class) {
					if ( pkt.class[c] == 'video.player' ) {
						video.addPlayer(pkt.from);
					}
				}

				break;
			case 'ack':	
				room.ack( pkt );
				switch( pkt.pkt.cmd ) {
					case 'rooms':
						for(var prop in pkt.ret) {
							room.add(prop,pkt.ret[prop]);
						}
						if ( location.hash > '' ) {
							menu.showPage(location.hash.substring(1,location.hash.length));
						}
						//menu.main($('rooms'));
						//alert(JSON.stringify(menu.layout.rooms));
						break;
					case 'state':
						if ( pkt.ret.paused != undefined ) {
							video.setState(pkt.from,pkt.ret);
						}
						break;
					case 'media':
						video.addMedia(pkt.from,pkt.ret.result.movies);
						break;
					case 'save_setting':
						settings.save_success(
							pkt.from,
							pkt.pkt.key,
							pkt.ret.value
						);
						break;
					default:
						//alert('ACK from '+pkt.from+' - '+pkt.pkt.cmd);
						break;
				}
				break;
			case 'nak':
				room.nak( pkt );
				switch( pkt.pkt.cmd ) {
					case 'save_setting':
						settings.save_failed(
							pkt.from,
							pkt.pkt.key,
							pkt.ret.value,
							pkt.ret.msg
						);
						break;
					default:
						//alert('NAK from '+pkt.from+' - '+pkt.pkt.cmd);
						break;
				}
				break;
			case 'bye':
				settings.removeComponent(pkt.from);
				video.removePlayer(pkt.from);
				break;
		}
	}
	// Types
	if ( pkt.type != undefined ) {
		switch( pkt.type ) {
			case 'event':
				switch(pkt.event) {
					case 'state':
						video.setState(pkt.from,pkt.data);
						break;
					case 'addRoom':
						editmode.exit();

						room.add(pkt.uuid,pkt.data);

						menu.sub($('page_'+pkt.uuid));
						menu.curSub = '';

						//menu.showPage(pkt.uuid);
						break;
					case 'removeRoom':
						editmode.exit();
						room.remove(pkt.uuid);
						break;
					case 'roomUpdate':
						room.rooms[pkt.uuid] = pkt.data;
						if ( !editmode.active ) {
							room.render(pkt.uuid);
						}
						break;
				}
				break;
		}
	}
}
