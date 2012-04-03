paramcount = 0;

var editmode = {
    active:false,
    enabled:false,
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

        editmode.enable();
    },
    render: function() {
        if ( editmode.enabled ) {
            editmode.disable();
            editmode.enable();
        }
    },
    enable: function() {
        editmode.enabled = true;
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
                container: buttons[button].parentNode,
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
                container: buttons[button].parentNode,
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
        editmode.disable();

        $(document.body).removeClass('editmodeactive');
        editmode.active = false;
    },
    disable: function() {
        editmode.enabled = false;
        buttons = $$('.button');
        for( button in buttons ) {
            if ( buttons[button].data == undefined ) {
                continue;
            }

            if ( $(buttons[button]).retrieve('dragger') != undefined ) {
                $(buttons[button]).retrieve('dragger').detach();
            }
            if ( $(buttons[button]).retrieve('resizer') != undefined ) {
                $(buttons[button]).retrieve('resizer').detach();
            }
            if ( $(buttons[button]).getElement('.handle') != undefined ) {
                $(buttons[button]).getElement('.handle').dispose();
            }
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
        p.data = el;
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
    copyData: null,
    copy: function(){
        
        p = $('settings_pane').getElement('.parameters');
        editmode.copyData = p.data;
        editmode.addButton($('settings_pane').getElement('.copy'));
        $('settings_pane').fade();
    },
    remove: function() {
        if ( confirm("You are about to remove this button, is this ok?") ) {
            p = $('settings_pane').getElement('.parameters');
            sendJSON('to=logic&cmd=remove&room='+p.data.room+'&element=buttons&uuid='+p.data.uuid);
            $('settings_pane').fade();
        }
    },
    save:function(obj) {
        if ( $(obj) == undefined ) {
            return false;
        }

        $(obj).addClass('saving');
        $(obj).disabled = true;

        sendJSON('to=logic&cmd=update&id='+obj.id+'&room='+obj.room+'&element=buttons&uuid='+obj.uuid+'&field='+obj.field+'&value='+obj.value.replace(/&/g,'%26'));

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
    },
    addButton: function(obj) {
        editmode.activebutton = obj;
        if ( editmode.enabled ) {
            obj.addClass('active');
            editmode.disable();
            $('main').addEvent('mousedown',editmode.addButtonPosition);
            $('main').addEvent('touchstart',editmode.addButtonPosition);
        } else {
            obj.removeClass('active');
            editmode.enable();
            $('main').removeEvent('mousedown',editmode.addButtonPosition);
            $('main').removeEvent('touchstart',editmode.addButtonPosition);
        }
        //window.addEvent('mousedown',editmode.addButtonPosition);
    },
    addButtonPosition: function(event) {
        coord = $(event.target).getCoordinates();
        x = event.client.x - coord.left;
        y = event.client.y - coord.top;

        editmode.activebutton.removeClass('active');

        $('main').removeEvent('mousedown',editmode.addButtonPosition);
        $('main').removeEvent('touchstart',editmode.addButtonPosition);

        editmode.enable();

        pages = $$('.page.active');
        if ( pages[0] != undefined && pages[0].hasClass('room') ) {
            uuid = pages[0].id.substring(5,pages[0].id.length);
            if(editmode.copyData !== null){
                var jsonRequest = new Request.JSON({url: 'send.php?to=logic&cmd=create&room='+uuid+'&element=buttons&x='+x+'&y='+y, onSuccess: function(data){
                    if(data.success != undefined && data.success){
                        $('settings_pane').fade('out');
                        editmode.copyData.uuid=data.ret;
                        editmode.elementClick(editmode.copyData);
                    }
                    editmode.copyData=null;
                }}).send();
            }
            else{
                sendJSON('to=logic&cmd=create&room='+uuid+'&element=buttons&x='+x+'&y='+y);
            }
        }

        return false;
        //aobaj.removeClass('active');    
    }
}
