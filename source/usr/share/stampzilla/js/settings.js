settings = {
	addComponent:function(name,classes,settings) {
		if ( $('component_'+name) == undefined ) {
			s = '';

			for( key in settings ) {
				row = settings[key];
				switch( row['type'] ) {
					case 'text':
						value = '';
						if ( row['value'] != undefined ) {
							value = row['value'];
						}
						s += '<div><label for="'+name+'_'+key+'">'+row['name']+'</label><input type="text" id="setting_'+name+'_'+key+'" name="'+name+'_'+key+'" onChange="settings.save(this,\''+name+'\',\''+key+'\');" value="'+value+'"></div>';
						break;
				}
			}
			el = new Element('div', {id: 'component_'+name});
			el.innerHTML = '<h2>'+name+" <span>("+classes+") <a href=\"javascript:sendJSON('to="+name+"&cmd=kill');\">[Kill]</a></span></h2>"+s;
			$('active_nodes').adopt(el);
		}
	},
	removeComponent:function(name) {
		if ( $('component_'+name) != undefined ) {
			$('component_'+name).dispose();
		}
	},
	save:function(obj,name,key) {
		$(obj).addClass('saving');
		$(obj).disabled = true;
		sendJSON('to='+name+'&cmd=save_setting&key='+key+'&value='+obj.value);
		return true;
	},
	save_success: function(name,key,value) {
		$('setting_'+name+'_'+key).removeClass('saving');
		$('setting_'+name+'_'+key).value = value;
		$('setting_'+name+'_'+key).disabled = false;
	},
	save_failed: function(name,key,value,msg) {
		$('setting_'+name+'_'+key).removeClass('saving');
		$('setting_'+name+'_'+key).value = value;
		$('setting_'+name+'_'+key).disabled = false;
		
		alert('Failed to save: '+msg);
	}

};
