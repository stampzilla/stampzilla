
communicationReady = function(){
    sendJSON("type=hello");
    //Fetch a list of all rooms from logic deamon
    //sendJSON("to=logic&cmd=state");
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
                settings.updateState(pkt.from,pkt.state);

                for (c in pkt.class) {
                    if ( pkt.class[c] == 'video.player' ) {
                        video.addPlayer(pkt.from);
                    }

                    if ( pkt.class[c] == 'logic' ) {
                        sendJSON("to="+pkt.from+"&cmd=state");
                    }
                }

                break;
            case 'ack':    
                room.highlight( pkt );
                switch( pkt.pkt.cmd ) {
                    case 'state':
                        switch( pkt.from ) {
                            case "logic":
                                if ( !editmode.active ) {
                                    for(var prop in pkt.ret.rooms) {
                                        room.add(prop,pkt.ret.rooms[prop]);
                                    }
                                    if ( location.hash > '' ) {
                                        menu.showPage(location.hash.substring(1,location.hash.length));
                                    }
                                }
                                for(var prop in pkt.ret.rules) {
                                    rules.add(prop,pkt.ret.rules[prop]);
                                }
                                for(var prop in pkt.ret.schedule) {
                                    schedule.add(prop,pkt.ret.schedule[prop]);
                                }
                            case "xbmc":
                                if ( pkt.ret.paused != undefined ) {
                                    video.setState(pkt.from,pkt.ret);
                                }
                                break;
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
                    case 'update':
                        editmode.save_success(
                            pkt.pkt.id,
                            pkt.ret.value
                        );
                        break;
                    default:
                        //alert('ACK from '+pkt.from+' - '+pkt.pkt.cmd);
                        break;
                }
                break;
            case 'nak':
                room.highlight( pkt );
                switch( pkt.pkt.cmd ) {
                    case 'save_setting':
                        settings.save_failed(
                            pkt.from,
                            pkt.pkt.key,
                            pkt.ret.value,
                            pkt.ret.msg
                        );
                        break;
                    case 'update':
                        editmode.save_failed(
                            pkt.pkt.id,
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
                if ( pkt.from == 'logic' ) {
                    room.clear();
                    rules.clear();
                    schedule.clear();
                }
                break;
        }
    }
    // Types
    if ( pkt.type != undefined ) {
        switch( pkt.type ) {
            case 'state':
                settings.updateState(pkt.from,pkt.data);
                room.updateState(pkt.from,pkt.data);
                break;
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
                        room.render(pkt.uuid);
                        break;
                }
                break;
        }
    }
}
