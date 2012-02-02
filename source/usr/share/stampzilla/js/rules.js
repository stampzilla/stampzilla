rules = {
	data: new Object(),
	clear: function() {
		rules.data = new Object();
		$('rules').innerHTML = '';
	},
	add: function(key,data) {
		rules.data[key] = data;
		rules.render(key);
	},
	render: function(key) {
		rule = rules.data[key];

		if ( $('rules').getElement('#component_'+rule.trigger.component) == undefined ) {
			el = new Element('div', {
				class: 'component',
				id: 'component_'+rule.trigger.component
			});
			el.innerHTML = '<h2>'+rule.trigger.component+'</h2>';
			$('rules').adopt(el);
		}

		if ( $('rules').getElement('#rule_'+key) == undefined ) {
			el = new Element('div', {
				class: 'rule',
				id: 'rule_'+key
			});
			el.innerHTML = '<div class="trigger"></div><div class="conditions"></div>';
			$('component_'+rule.trigger.component).adopt(el);
		}

		$('rules').getElement('#rule_'+key+' .trigger').innerHTML = '';
		for( field in rule.trigger ) {
			if ( field == 'component' ) {
				continue;
			}

			$('rules').getElement('#rule_'+key+' .trigger').innerHTML = field+': '+rule.trigger[field];
		}

		$('rules').getElement('#rule_'+key+' .conditions').innerHTML = '';
		for( uuid in rule.conditions ) {
			if ( typeof rule.conditions[uuid] === 'function' ) {
				continue;
			}
			$('rules').getElement('#rule_'+key+' .conditions').innerHTML = uuid+': '+rule.conditions[uuid];
		}
	}
}
