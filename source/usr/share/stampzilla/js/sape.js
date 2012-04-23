sape = new Class({
	transport:null,
    spinner:null,
	scripts:[
		'js/sape.transport.js'
	],
	connect:function(url) {
		window.sape = this;

        window.sape.spinner = new Spinner(
            'container',
            {
                message:"Connecting...",
            }
        );
        window.sape.spinner.show(true);


		iframe = new Element('iframe', {
			id: 'sape',
			style: 'display:none'
		});
		document.body.insertBefore(iframe,document.body.childNodes[0]);
	
		this.transport = iframe.contentWindow;

        var initFn = function() {
			window.sape.transport.sape.init(url);
        }

        if (iframe.addEventListener) {
                iframe.addEventListener('load', initFn, false);
        } else if (iframe.attachEvent) {
                iframe.attachEvent('onload', initFn);
        }
	
		var doc = iframe.contentDocument;
		if (!doc) doc = iframe.contentWindow.document;//For IE

		doc.open();
		var theHtml = '<html><head>';
		for (var i = 0; i < this.scripts.length; i++) {
				theHtml += '<script type="text/JavaScript" src="' + this.scripts[i] + '"></script>';
		}
		theHtml += '</head><body></body></html>';
		doc.write(theHtml);
		doc.close();

	},
    ready:function(){
        window.sape.spinner.hide();
		communicationReady();
    },
	recive:function(data) {
        window.sape.spinner.hide();
		incoming(data);
	},
    fail:function(cnt){
        window.sape.spinner.show();
        window.sape.spinner.msg.innerHTML = 'Reconnecting ('+cnt+')...';
    },
})

