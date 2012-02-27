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
    addCondition: function(uuid) {/*{{{*/
        p = $('settings_pane').getElement('.parameters');
        $('settings_pane').getElement('.remove').style.display = "none";
        p.data = el;
        p.innerHTML = "<h1>New condition</h1>"+
            "<div >State variable <input id=\"value_state\" type=\"text\" value=\"\"></div>"+
            "<div >Type <input id=\"value_type\" type=\"text\" value=\"\"></div>"+
            "<div >Value <input id=\"value_value\" type=\"text\" value=\"\"></div>"+
            "<div ><input type=\"button\" onClick=\"rules.newCondition('"+uuid+"');\" value=\"Add\"></div>";

        $('settings_pane').fade('in');
    },
    newCondition: function(uuid) {
        sendJSON('to=logic&cmd=addCondition&uuid='+uuid+'&state='+$('value_state').value+'&type='+$('value_type').value+'&value='+$('value_value').value);
        $('settings_pane').fade('out');
    },/*}}}*/
    editCondition: function(uuid,key) {/*{{{*/
        p = $('settings_pane').getElement('.parameters');
        $('settings_pane').getElement('.remove').style.display = "block";
        $('settings_pane').getElement('.remove').onclick = function() {rules.removeCondition(uuid,key)};
        p.data = el;
        p.innerHTML = "<h1>New condition</h1>"+
            "<div >State variable <input id=\"value_state\" type=\"text\" value=\""+room.states['logic']['rules'][uuid]['conditions'][key]['state']+"\"></div>"+
            "<div >Type <input id=\"value_type\" type=\"text\" value=\""+room.states['logic']['rules'][uuid]['conditions'][key]['type']+"\"></div>"+
            "<div >Value <input id=\"value_value\" type=\"text\" value=\""+room.states['logic']['rules'][uuid]['conditions'][key]['value']+"\"></div>"+
            "<div ><input type=\"button\" onClick=\"rules.saveCondition('"+uuid+"','"+key+"');\" value=\"Update\"></div>";

        $('settings_pane').fade('in');
    },
    saveCondition: function(uuid,key) {
        sendJSON('to=logic&cmd=updateCondition&uuid='+uuid+'&key='+key+'&state='+$('value_state').value+'&type='+$('value_type').value+'&value='+$('value_value').value);
        $('settings_pane').fade('out');
    },/*}}}*/
    removeCondition: function(uuid,key) {/*{{{*/
        if ( confirm("Are you shure you want to remove this condition?") ) {
            sendJSON('to=logic&cmd=removeCondition&uuid='+uuid+'&key='+key);
            $('settings_pane').fade('out');
        }
    },/*}}}*/

    create: function(uuid) {
        name = prompt("New name for the rule:");

        if( name ) {
            sendJSON('to=logic&cmd=createRule&uuid='+uuid+'&name='+name);
        }
    },
    rename: function(uuid) {
        name = prompt("New name for the rule:");

        if( name ) {
            sendJSON('to=logic&cmd=updateRule&uuid='+uuid+'&name='+name);
        }
    },
    remove: function(uuid) {
        if( confirm('Are you shure you want to remove this rule?') ) {
            sendJSON('to=logic&cmd=removeRule&uuid='+uuid);
        }
    },

    addEnter: function(uuid) {
    },
    addExit: function(uuid) {
    },
    render: function(key) {/*{{{*/
        rule = rules.data[key];

        // Create base
        if ( $('rules').getElement('#rule_'+key) == undefined ) {/*{{{*/
            el = new Element('div', {
                class: 'rule',
                id: 'rule_'+key
            });
            el.innerHTML = 
                '<div class="toolbar"><input type="button" onClick="rules.addCondition(\''+key+'\')" value="Add condition">'+
                '<input type="button" onClick="rules.addEnter(\''+key+'\')" value="Add enter">'+
                '<input type="button" onClick="rules.addExit(\''+key+'\')" value="Add exit">'+
                '<input type="button" onClick="rules.rename(\''+key+'\')" value="Rename" style="margin-left:20px;">'+
                '<input type="button" onClick="rules.remove(\''+key+'\')" value="Remove"></div>'+
                '<h2>'+rule.name+'</h2>'
            $('rules').adopt(el);


            c = new Element('div', {
                class: 'conditions',
            });
            $(el).adopt(c);

            c = new Element('div', {
                class: 'enter',
            });
            $(el).adopt(c);

            c = new Element('div', {
                class: 'exit',
            });
            $(el).adopt(c);

            c = new Element('div', {
                style: 'clear:both;',
            });
            $(el).adopt(c);
        }/*}}}*/

        $('rules').getElement('#rule_'+key).getElement('h2').innerHTML = rule.name;

        if ( rule.active ) {
            $('rule_'+key).addClass('active');
        } else {
            $('rule_'+key).removeClass('active');
        }

        $$('#rule_'+key+' .conditions div').addClass('INVALID');
        $$('#rule_'+key+' .enter div').addClass('INVALID');
        $$('#rule_'+key+' .exit div').addClass('INVALID');

        for( field in rule.conditions ) {/*{{{*/
            if ($('rules').getElement('#condition_'+key+field) == undefined ) {
                el = new Element('div', {
                    class: 'condition',
                    id: 'condition_'+key+field,
                    onClick: "rules.editCondition('"+key+"','"+field+"');"
                });
                el.data = {
                    field: field
                }
                $$('#rule_'+key+' .conditions')[0].adopt(el);
            }

            $('condition_'+key+field).innerHTML = rule.conditions[field].state+" <b>"+rule.conditions[field].type.toUpperCase()+"</b> "+rule.conditions[field].value;
            $('condition_'+key+field).removeClass('INVALID');
            if ( rule.conditions[field].active ) {
                $('condition_'+key+field).addClass('active');
            } else {
                $('condition_'+key+field).removeClass('active');
            }
        }/*}}}*/
        if ( rule.enter ) {/*{{{*/
            for( field in rule.enter ) {
                if ($('rules').getElement('#enter_'+key+field) == undefined ) {
                    el = new Element('div', {
                        class: 'enter',
                        id: 'enter_'+key+field
                    });
                    el.data = {
                        field: field
                    }
                    $$('#rule_'+key+' .enter')[0].adopt(el);
                }

                $('enter_'+key+field).innerHTML = '';

                for( key2 in rule.enter[field] ) {
                    $('enter_'+key+field).innerHTML += key2 + ': '+rule.enter[field][key2]+"<br>";
                }
                $('enter_'+key+field).removeClass('INVALID');
            }
        }/*}}}*/
        if ( rule.exit ) {/*{{{*/
            for( field in rule.exit ) {
                if ($('rules').getElement('#exit_'+key+field) == undefined ) {
                    el = new Element('div', {
                        class: 'exit',
                        id: 'exit_'+key+field
                    });
                    el.data = {
                        field: field
                    }
                    $$('#rule_'+key+' .exit')[0].adopt(el);
                }

                $('exit_'+key+field).innerHTML = '';

                for( key2 in rule.exit[field] ) {
                    $('exit_'+key+field).innerHTML += key2 + ': '+rule.exit[field][key2]+"<br>";
                }
                $('exit_'+key+field).removeClass('INVALID');
            }
        }/*}}}*/

        $$('#rule_'+key+' .INVALID').dispose();
    }/*}}}*/
}
