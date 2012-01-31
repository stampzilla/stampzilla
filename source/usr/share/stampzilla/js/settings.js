settings = {
	trees: {},
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

			s = new Element('div', {id: 'component_'+name+'_state'});
			el.adopt(s);

			$('active_nodes').adopt(el);

			this.trees[name] = new MooTreeControl({
				div: 'component_'+name+'_state',
				mode: 'files',
				grid: true,
				theme: 'images/mootree.gif'
			},{
				text: 'Root Node',
				open: true
			});

		}
	},
	removeComponent:function(name) {
		if ( $('component_'+name) != undefined ) {
			$('component_'+name).dispose();
			//delete settings.tree[name];
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
	},
	updateState: function(name,data) {
		if ( this.trees[name] == undefined ) {
			return;
		}

		this.trees[name].disable();
		this.readTree(this.trees[name].root,data);
		this.trees[name].enable();
	},
	readTree: function( p, data ) {
		for( sub in p.nodes ) {
			if ( typeof p.nodes[sub] === 'function' ) {
				continue
			}

			p.nodes[sub].data['valid'] = false;
		}

		for( row in data ) {
			if ( typeof data[row] === 'function' ) {
				continue
			}
 
			node = undefined;
	
			// Searh for node
			for( sub in p.nodes ) {
				if ( typeof p.nodes[sub] === 'function' ) {
					continue
				}

				if ( p.nodes[sub].data['key'] == row ) {
					p.nodes[sub].data['valid'] = true;
					node = p.nodes[sub];
					break;
				}
			}
		
			if ( node == undefined ) {
				node = p.insert({text:row,data:{key:row,valid:true}});
			}

			if ( typeof data[row] === 'object' ) {
				this.readTree(node, data[row]);
			} else {
				node.text = row + ' - ' + data[row];
			}
		}

		// Remove old nodes
		for( sub in p.nodes ) {
			if ( typeof p.nodes[sub] === 'function' ) {
				continue
			}

			if ( p.nodes[sub].data['valid'] == false ) {
				p.nodes[sub].remove();
			}
		}
	}
};
