room = {
    rooms: new Object(),
    states: new Object(),
    clear: function() {
        menu.layout.rooms = {
            0: 'Rooms'
        };
        $$('.page.room').dispose();

        if ( $('rooms').hasClass('active') ) {
            $$('#submenu a').dispose();
            $('submenu').fade('out');
            menu.showPage('rooms');
        }
    },
    add: function(uuid,data) {
        var temp = new Object();
        temp["0"] = data.name;
        menu.layout.rooms[uuid] = temp;

        el = new Element('div', {id: 'page_'+uuid,class: 'page room'});
        $('main').adopt(el);

        room.rooms[uuid] = data;
        room.render(uuid);
    },
    remove:function(uuid) {
        $('page_'+uuid).dispose();
        delete menu.layout.rooms[uuid];
        if ( $(uuid) != undefined ) {
            $(uuid).dispose();
        }
        $$('#submenu #'+uuid).dispose();
    },
    updateState:function(node,data) {
        if ( data == undefined )
            return;

        this.states[node] = data;
        this.renderStates();
    },
    renderStates:function() {
        buttons = $$('.room .button');
        for( button in buttons ) {
            if ( buttons[button].data == undefined || buttons[button].data.state == undefined) {
                continue;
            }

			mode = '';
			if ( buttons[button].data.state.indexOf('=') != -1 ) {
				mode = '=';
            	root = buttons[button].data.state.split('=');
				path = root[0].split('.');
			} else if ( buttons[button].data.state.indexOf('>') != -1 ) {
				mode = '>';
            	root = buttons[button].data.state.split('>');
				path = root[0].split('.');
			} else if ( buttons[button].data.state.indexOf('<') != -1 ) {
				mode = '<';
            	root = buttons[button].data.state.split('<');
				path = root[0].split('.');
			} else {
				path = root.split('.');
			}

			node = path[0];
            delete path[0];
            p = '';
            for( key in path ) {
                if ( typeof path[key] == 'function' ) {
                    continue;
                }

                n = path[key];

                if ( n-0 == n && n.length>0 ) {
                    p += '['+n+']';
                } else {
                    p += '.'+n;
                }

                eval("if ( this.states[node]"+p+" == undefined ) {this.states[node]"+p+" = {};}");
            }


            if ( mode != '' ) {
				switch (mode) {
					case '=':
		                eval("if (this.states[node]"+p+"==root[1]) {buttons[button].addClass('active');} else {buttons[button].removeClass('active');};");
    		            buttons[button].getElement('.state').innerHTML = '';
						break;
					case '>':
		                eval("if (this.states[node]"+p+">root[1]) {buttons[button].addClass('active');} else {buttons[button].removeClass('active');};");
    		            buttons[button].getElement('.state').innerHTML = '';
						break;
					case '<':
		                eval("if (this.states[node]"+p+"<root[1]) {buttons[button].addClass('active');} else {buttons[button].removeClass('active');};");
    		            buttons[button].getElement('.state').innerHTML = '';
						break;
				}
            } else {
                eval("buttons[button].getElement('.state').innerHTML = this.states[node]"+p+";");
            }
        }
    },
    render:function(uuid) {
        if ( $('page_'+uuid).getElement('h1') == undefined ) {
            el = new Element('h1', {
                class: 'title'
            });
            el.innerHTML = room.rooms[uuid].name;
            $('page_'+uuid).adopt(el);
        } else {
            $('page_'+uuid).getElement('h1').innerHTML = room.rooms[uuid].name;
        }
    
        list = new Array();

        if ( room.rooms[uuid].buttons != undefined ) {
            for( button in room.rooms[uuid].buttons ) {
                if ( room.rooms[uuid].buttons[button].title == undefined )
                    continue;
                
                list.push(button);

                if ( $('button_'+uuid+'_'+button) == undefined ) {
                    el = new Element('div', {
                        id: 'button_'+uuid+'_'+button,
                        class: 'button',
                        style: 'position:absolute;'
                    });
                    el.innerHTML = '<span class="head"></span><span class="state"></span>';
                    $('page_'+uuid).adopt(el);
                } else {
                    el = $('button_'+uuid+'_'+button);
                }

                el.data = room.rooms[uuid].buttons[button];
                el.room = uuid;
                el.uuid = button;
                el.data.position = el.data.position.split(',');
                el.getElement('.head').innerHTML = el.data.title;
				if ( el.data.state != undefined ) {
	                el.getElement('.state').innerHTML = 'UNKNOWN';
				}
                el.onclick = function() {room.button(this)};
            }
        }

        buttons = $$('#page_'+uuid+' .button');
        for( button in buttons ) {
            if ( buttons[button].data == undefined ) {
                continue;
            }

            if ( list.indexOf(buttons[button].uuid) == -1 ) {
                $(buttons[button]).dispose();
            }
        }

        this.renderStates();

        editmode.render();

        room.orient();
    },
    orient: function() {
        orient = 90;
        if ( window.orientation != undefined ) {
            orient = window.orientation;
        }

        buttons = $$('.room .button');
        for( button in buttons ) {
            if ( buttons[button].data == undefined ) {
                continue;
            }
            if ( orient == 0 || orient == 180 ) {
                buttons[button].style.left = buttons[button].data.position[1]+'px';
                buttons[button].style.bottom = buttons[button].data.position[0]+'px';
                buttons[button].style.top = '';
                buttons[button].style.width = buttons[button].data.position[3]+'px';
                buttons[button].style.height = buttons[button].data.position[2]+'px';
            } else {
                buttons[button].style.left = buttons[button].data.position[0]+'px';
                buttons[button].style.top = buttons[button].data.position[1]+'px';
                buttons[button].style.bottom = '';
                buttons[button].style.width = buttons[button].data.position[2]+'px';
                buttons[button].style.height = buttons[button].data.position[3]+'px';
            }
        }

    },
    button:function(obj) {
        if ( !editmode.active ) {
            sendJSON("to="+obj.data.component+"&cmd="+obj.data.cmd);
        }
    },
    highlight: function(pkt) {
        if ( pkt.pkt == undefined ) {
            return;
        }

        buttons = $$('.room .button');

        outerloop:
        for( button in buttons ) {
            if ( buttons[button].data == undefined || buttons[button].data.component != pkt.pkt.to ) {
                continue;
            }

            args = buttons[button].data.cmd.split('&');
            for ( arg in args ) {
                if ( typeof args[arg] == 'function' ){
                    continue;
                }
                if ( arg == 0 ) {
                    cmd = args[arg];
                } else {
                    args[arg] = args[arg].split('=');
                    args[args[arg][0]] = args[arg][1];
                }
            }

            if ( pkt.pkt.cmd != cmd ) {
                continue;
            }

            for(key in pkt.pkt) {
                if ( key == 'to' || key == 'from' || key == 'cmd' ) {
                    continue;
                }
                if ( args[key] != pkt.pkt[key] ) {
                    continue outerloop;
                }

                //alert(key+" - "+pkt.pkt[key]);
            }

            if ( pkt.cmd == 'ack' ) {
                $(buttons[button]).highlight("#00ff00");
            } else {
                $(buttons[button]).highlight("#ff0000");
            }
        }
    }
}

