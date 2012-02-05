schedule = {
    clear: function() {
        $('schedule').innerHTML = '';
    },
    add: function(data) {
        var val = null;
        $$('.scheduleItem').addClass('invalid');
        for(var uuid in data) {
            val = data[uuid];
            
            if($('schedule_'+uuid) == undefined){
                el = new Element('div', {id: 'schedule_'+uuid,class:'scheduleItem'});
                $('scheduleContent').adopt(el);
                this.draw(el,val);
            }
            else{
                if($('schedule_'+uuid) != undefined){
                    $('schedule_'+uuid).removeClass('invalid');
                    this.draw($('schedule_'+uuid),val);
                }

            }
        }
        $$('.invalid').dispose();
    },
    draw: function(el,data){
        var html = '';
        for(var cmduuid in data.commands) {
            html += '<div class="cmds">';
            html += '<a style="float:right;" href="" onclick="schedule.unscheduleCommand(\''+cmduuid+'\');return false;">X</a>';
            for(var fields in data.commands[cmduuid]) {
                html += '<div class="cmd">'+fields+' : '+data.commands[cmduuid][fields]+'</div>';
            }
            html += '</div>';
        }
        html += '<a style="float:right;" href="" onclick="schedule.showFormCmd(\''+data.uuid+'\');return false;">Add command</a>';
        html += '<div class="name">'+data.name+'<a href="" onclick="schedule.unschedule(\''+data.uuid+'\');return false;">X</a></div>';
        html += '<div>Time: '+data.time+'</div>';
        html += '<div>Interval: '+data.interval+'</div>';
        if(data.timestamp != undefined){
        var date = new Date(data.timestamp*1000);
            html += '<div>Next run time: '+("0" + date.getHours()).slice(-2)+':'+("0" + date.getMinutes()).slice(-2)+':'+("0" + date.getSeconds()).slice(-2)+'</div>';
        }
        else{
            html += '<div>Next run time: none</div>';
        }

        html += '<div style="clear:both;"></div>';

        el.innerHTML = html;
    },

    unscheduleCommand: function(uuid){
        var jsonRequest = new Request.JSON({url: 'send.php?to=logic&uuid='+uuid+'&cmd=unscheduleCommand', onSuccess: function(data){}}).send();
    },
    unschedule: function(uuid){
        var jsonRequest = new Request.JSON({url: 'send.php?to=logic&uuid='+uuid+'&cmd=unschedule', onSuccess: function(data){}}).send();
    },
    showFormCmd: function(uuid){
        var form = new Element('form', {'action' : ''});
        var textarea = new Element('textarea', {'name' : 'myTextarea'});
        var button = new Element('input', {'type' : 'button', 'value' : 'Add'});
        var cancel = new Element('input', {'type' : 'button', 'value' : 'Cancel'});
        button.addEvent('click', function(event){
            var self = this;
            var jsonRequest = new Request.JSON({url: 'send.php?to=logic&uuid='+uuid+'&cmd=scheduleCommand&data='+this.parentNode.firstChild.value.replace(/\n/g,','), onSuccess: function(data){
                if(data.success != undefined && data.success){
                    self.parentNode.parentNode.dispose();
                }
            }}).send();
            event.stopPropagation();
            event.preventDefault();
        });
        cancel.addEvent('click', function(event){
            this.parentNode.parentNode.dispose();
            event.stopPropagation();
            event.preventDefault();
        });
        el = new Element('div', {html: 'Add cmd:',class:'FormName',id:'frm_'+uuid});
        el.adopt(form.adopt(textarea,button,cancel));
        el.inject($('scheduleForm'),'top');
    },
    showFormSchedule: function(){
        var form = new Element('form', {'action' : ''});
        var name = new Element('input', {'type':'text','name' : 'name','value' : 'name'});
        var interval = new Element('input', {'type':'text','name' : 'interval','value' : 'interval'});
        var time = new Element('input', {'type':'text','name' : 'time','value' : 'time'});
        var button = new Element('input', {'type' : 'button', 'value' : 'Add'});
        var cancel = new Element('input', {'type' : 'button', 'value' : 'Cancel'});
        button.addEvent('click', function(event){
            var self = this;
            var jsonRequest = new Request.JSON({url: 'send.php?to=logic&cmd=schedule&name='+this.parentNode.elements[0].value+'&interval='+this.parentNode.elements[1].value+'&time='+this.parentNode.elements[2].value, onSuccess: function(data){
                if(data.success != undefined && data.success){
                    self.parentNode.parentNode.dispose();
                }
            }}).send();
            event.stopPropagation();
            event.preventDefault();
        });
        cancel.addEvent('click', function(event){
            this.parentNode.parentNode.dispose();
            event.stopPropagation();
            event.preventDefault();
        });
        el = new Element('div', {html: 'Add New Schedule:',class:'FormName'});
        el.adopt(form.adopt(name,interval,time,button,cancel));
        el.inject($('scheduleForm'),'top');
    }


}
