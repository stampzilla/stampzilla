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

        if($('page_'+uuid) == undefined){
            el = new Element('div', {id: 'page_'+uuid,class: 'page room'});
            $('main').adopt(el);
        }

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
        try{
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
                    root = buttons[button].data.state;
                    path = root.split('.');
                }

                node = path[0];

                if ( this.states[node] == undefined ) {
                    buttons[button].getElement('.state').innerHTML = 'UNKNOWN';
                    continue;
                }

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

            sliders = $$('.room .slider');
            for( slider in sliders ) {
                if ( sliders[slider].data == undefined || sliders[slider].data.state == undefined) {
                    continue;
                }


				root = sliders[slider].data.state;
				path = root.split('.');
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
                value = eval("this.states[node]"+p+";");
				value -= sliders[slider].data.min;
				value /= sliders[slider].data.max;
				value *= sliders[slider].factor*(sliders[slider].data.max/sliders[slider].data.step);

				if ( -value != NaN && sliders[slider].scrollvalue != value && sliders[slider].data.active != true ) {
					sliders[slider].scroller.scrollTo(0,-value,200);
					sliders[slider].scrollvalue = value;
				}
			}
        } catch (er) {
            //alert(er.message);
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

        list = new Array();

        if ( room.rooms[uuid].sliders != undefined ) {
            for( slider in room.rooms[uuid].sliders ) {
                if ( room.rooms[uuid].sliders[slider].title == undefined )
                    continue;

				//room.rooms[uuid].sliders[slider].step = 10;
                
                list.push(slider);

                if ( $('slider_'+uuid+'_'+slider) == undefined ) {
                    el = new Element('div', {
                        id: 'slider_'+uuid+'_'+slider,
                        class: 'slider',
                        style: 'position:absolute;'
                    });
                    el.innerHTML = '<span class="head"></span><div class="scrollwrapper"><div class="scrollcontent"></div></div>';
                    $('page_'+uuid).adopt(el);

					el.scroller = new iScroll(el.getElement('.scrollwrapper'),{
						momentum: false
					});

					el.scroller.options.onScrollMove=room.liveUpdate;
					el.scroller.options.onScrollEnd=room.finalUpdate;
                } else {
                    el = $('slider_'+uuid+'_'+slider);
                }

                el.data = room.rooms[uuid].sliders[slider];
                el.room = uuid;
                el.uuid = button;
                el.data.position = el.data.position.split(',');
                el.getElement('.head').innerHTML = el.data.title;

				content = '';
				for (var i=room.rooms[uuid].sliders[slider].min;i<=room.rooms[uuid].sliders[slider].max;i+=room.rooms[uuid].sliders[slider].step) {
					content += '<div class="scrollvalue">'+i+'</div>';
				}
				el.getElement('.scrollcontent').innerHTML = content;
            }
        }

        /*sliders = $$('#page_'+uuid+' .slider');
        for( slider in sliders ) {
            if ( sliders[slider].data == undefined ) {
                continue;
            }

            if ( list.indexOf(sliders[slider].uuid) == -1 ) {
				$(sliders[slider]).scroller.destroy();
				$(sliders[slider]).scroller = null;

                $(sliders[slider]).dispose();
            }
        }*/


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

        buttons = $$('.room .slider');
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

			buttons[button].factor = buttons[button].getStyle('height').toInt()/2;
			$(buttons[button]).scroller.maxScrollY = -(buttons[button].factor*((buttons[button].data.max-buttons[button].data.min)/buttons[button].data.step));

			$(buttons[button]).getElements('.scrollvalue').setStyle('font-size',buttons[button].factor/2);
			$(buttons[button]).getElements('.scrollvalue').setStyle('height',buttons[button].factor);
			$(buttons[button]).getElement('.scrollcontent').setStyle('padding-top',buttons[button].factor/2);
			$(buttons[button]).getElement('.scrollcontent').setStyle('padding-bottom',buttons[button].factor/2);

			//buttons[button].scroller.refresh();
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
    },
	liveUpdate: function() {
        clearTimeout(pressTimer);
		if ( this.scroller != undefined && !isNaN(this.y) ) {
			value = (-this.y/this.wrapper.parentNode.factor/(this.wrapper.parentNode.data.max/this.wrapper.parentNode.data.step)) * this.wrapper.parentNode.data.max + this.wrapper.parentNode.data.min;

			if ( value > this.wrapper.parentNode.data.max ) value = this.wrapper.parentNode.data.max;
			if ( value < this.wrapper.parentNode.data.min ) value = this.wrapper.parentNode.data.min;

			if ( !this.wrapper.parentNode.data.running ) {
				this.wrapper.parentNode.data.running = true;
				this.wrapper.parentNode.data.active = true;
				room.startLiveUpdater(this);
			}
			this.wrapper.parentNode.data.value = Math.round(value);
		}
	},
	finalUpdate: function() {
		this.wrapper.parentNode.data.last = true;
		this.wrapper.parentNode.data.active = true;
		document.title = "end";
		room.liveUpdate(this);
		this.wrapper.parentNode.data.running = false;
	},
	startLiveUpdater: function(obj) {
		if ( obj.wrapper != undefined && obj.wrapper.parentNode.data.active ) {
			if ( !isNaN(obj.wrapper.parentNode.data.value) && obj.wrapper.parentNode.data.value != undefined && (obj.wrapper.parentNode.data.prev != obj.wrapper.parentNode.data.value||obj.wrapper.parentNode.data.last)) {

				obj.wrapper.parentNode.data.prev = obj.wrapper.parentNode.data.value;
				obj.wrapper.parentNode.data.last = false;

				cmd = obj.wrapper.parentNode.data.cmd.replace('%VALUE%',obj.wrapper.parentNode.data.value);
				new Request({
					url: "send.php?to="+obj.wrapper.parentNode.data.component+"&cmd="+cmd,
					onComplete: function() {
						room.startLiveUpdater(obj);
					}
				}).send();
			} else {
				obj.wrapper.parentNode.data.running = false;
			}
		}
	}
}

