paramcount = 0;

var editmode = {
	active:false,
	longpress: function() {
		// Only enter editmode on room pages
		pages = $$('.page.active');
		if ( pages[0] != undefined && pages[0].hasClass('room') ) {
			//if ( !$(document.body).hasClass('editmodeactive') && confirm("You are about to enter edit mode, is this ok?") ) {
			if ( !$(document.body).hasClass('editmodeactive') ) {
				editmode.activate();
			}
		}
	},
	activate:function() {
		$(document.body).addClass('editmodeactive');
		editmode.active = true;
		$('submenu').fade('out');

		buttons = $$('.button');
		for( button in buttons ) {
			if ( buttons[button].data == undefined ) {
				continue;
			}
			el = new Element('div', {
				class: 'handle',
			});

			$(buttons[button]).adopt(el);

			$(buttons[button]).makeResizable({
				handle: el,
				grid: 10,
				limit: {x: [50,null],y: [50,null]},
				onBeforeStart:function() {
				},
				onStart:function()
				{
					this.element.setOpacity(.5);
					this.zindex = 100;
					this.element.retrieve('dragger').cancel();
				},
				onDrag:function() {
					this.element.dragging = true;
				},
				onComplete:function()
				{
					this.element.setOpacity(1);
					this.zindex = 0;
					sendJSON("to=logic&cmd=update&room="+(this.element.id.split('_')[1])+"&element=buttons&uuid="+(this.element.id.split('_')[2])+
						"&field=position&value="+this.element.style.left+","+this.element.style.top+","+this.element.style.width+","+this.element.style.height);
				},
				onCancel:function() {
					this.element.dragging = false;
				}
			});


			$(buttons[button]).makeDraggable({
				stopPropagation:true,
				grid: 10,
				onStart:function()
				{
					this.element.setOpacity(.5);
					this.zindex = 100;
				},
				onDrag:function() {
					this.element.dragging = true;
				},
				onComplete:function()
				{
					this.element.setOpacity(1);
					this.zindex = 0;
					sendJSON("to=logic&cmd=update&room="+(this.element.id.split('_')[1])+"&element=buttons&uuid="+(this.element.id.split('_')[2])+
						"&field=position&value="+this.element.style.left+","+this.element.style.top+","+this.element.style.width+","+this.element.style.height);
				},
				onCancel:function() {
					if ( !this.element.dragging ) {
						editmode.elementClick(this.element);
					}
					this.element.dragging = false;
				}
			});
		}
	},
	exit: function() {
		$(document.body).removeClass('editmodeactive');
		editmode.active = false;

		buttons = $$('.button');
		for( button in buttons ) {
			if ( buttons[button].data == undefined ) {
				continue;
			}
			$(buttons[button]).retrieve('dragger').detach();
			$(buttons[button]).retrieve('resizer').detach();
			$(buttons[button]).getElement('.handle').dispose();
		}
	},
	addRoom: function() {
		name = prompt("What is the name of the new room?");

		if ( name != null && name != "" && name != "null" ) {
			sendJSON("to=logic&cmd=room&name="+name);
		}
	},
	removeRoom: function() {
		pages = $$('.page.active');

		if ( pages[0] != undefined && pages[0].hasClass('room') ) {
			id = pages[0].id.substring(5,pages[0].id.length);
			if ( confirm("You are about to remove the room named '"+id+"', is this ok?") ) {
				sendJSON("to=logic&cmd=deroom&uuid="+id);
			}
		} else {
			alert("Unknown room, exiting edit mode");
			editmode.exit();
		}
	},
	elementClick:function( el ) {
		p = $('settings_pane').getElement('.parameters');
		p.innerHTML = "<h1>"+el.data.title+"</h1>";

		for( param in el.data ) {
			row = new Element('div', {
				class: 'param_'+param,
			});

			row.innerHTML = param;
			paramcount++;
			if ( param != 'position' ) {
				form = new Element('input', {
					id: 'param'+paramcount,
					type: 'text',
					value: el.data[param],
					onChange: "editmode.save(this)"
				});
		
				form.data = el.data;
				form.room = el.room;
				form.uuid = el.uuid;
				form.field = param;
			} else {
				form = new Element('div', {
					id: 'param'+paramcount,
				});
				form.innerHTML = el.data[param];
			}

			row.adopt(form);

			p.adopt(row);
		}

		$('settings_pane').fade('in');
	},
	save:function(obj) {
		if ( $(obj) == undefined ) {
			return false;
		}

		$(obj).addClass('saving');
		$(obj).disabled = true;

		sendJSON('to=logic&cmd=update&id='+obj.id+'&room='+obj.room+'&element=buttons&uuid='+obj.uuid+'&field='+obj.field+'&value='+obj.value);

		return true;
	},
	save_success: function(id,value) {
		if ( $(id) != undefined ) {
			$(id).removeClass('saving');
			$(id).value = value;
			$(id).disabled = false;
		}
	},
	save_failed: function(id,value,msg) {
		if ( $(id) != undefined ) {
			$(id).removeClass('saving');
			$(id).value = value;
			$(id).disabled = false;
		}
		alert('Failed to save: '+msg);
	}
}
