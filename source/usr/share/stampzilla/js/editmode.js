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

		$$('.button').makeDraggable({
			onStart:function()
			{
				this.element.setOpacity(.5);
				this.zindex = 100;
			},
			onComplete:function()
			{
				this.element.setOpacity(1);
				this.zindex = 0;
				sendJSON("to=logic&cmd=update&room="+(this.element.id.split('_')[1])+"&element=buttons&uuid="+(this.element.id.split('_')[2])+
					"&position="+this.element.style.left+","+this.element.style.top+","+this.element.style.width+","+this.element.style.height);
			}
		});
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
	}
}
