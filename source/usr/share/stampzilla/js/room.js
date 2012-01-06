room = {
	rooms: new Object(),
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
	},
	render:function(uuid) {
		document.title = $('page_'+uuid).getElement('h1');
		if ( $('page_'+uuid).getElement('h1') == undefined ) {
			//$('page_'+uuid).innerHTML += '<div class="title">'+room.rooms[uuid].name+'</div>';
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
					el.innerHTML = '<span class="head"></span>';
					$('page_'+uuid).adopt(el);
				} else {
					el = $('button_'+uuid+'_'+button);
				}

				el.data = room.rooms[uuid].buttons[button];
				el.room = uuid;
				el.uuid = button;
				el.data.position = el.data.position.split(',');
				el.getElement('.head').innerHTML = el.data.title;
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
	ack: function(pkt) {
		buttons = $$('.room .button');
		for( button in buttons ) {
			if ( buttons[button].data == undefined || buttons[button].data.component != pkt.pkt.to || buttons[button].data.cmd != pkt.pkt.cmd ) {
				continue;
			}

			$(buttons[button]).highlight("#00ff00");
		}
	},
	nak: function(pkt) {
		buttons = $$('.room .button');
		for( button in buttons ) {
			if ( buttons[button].data == undefined || buttons[button].data.component != pkt.pkt.to || buttons[button].data.cmd != pkt.pkt.cmd ) {
				continue;
			}

			$(buttons[button]).highlight("#ff0000");
		}

	}
}

