window.sape = {
	url:null,
	init: function(url) {
		this.requests = [];
		this.requestFailObserver = [];
		this.url = url;
		//parent.window.sape.hello('!!!asd');
		this.request('?test');
	},
	request: function( query ) {
		var request = document.createElement('script');
		request.src = this.url + query;
		document.head.appendChild(request);
		this.requests.push(request);

		//Detect timeout
		//this.requestFailObserver.push(this.ape.requestFail.delay(this.ape.options.pollTime + 10000, this.ape, [-1, request]));

		/*if (Browser.Engine.gecko) {
				//Firefox hack to avoid status bar always show a loading message
				//Ok this hack is little bit weird but it works!
				(function() {
						var tmp = document.createElement('iframe');
						document.body.appendChild(tmp);
						document.body.removeChild(tmp);
				}).delay(200);
		}*/
	},
	recive:function(data) {
		parent.window.sape.recive(data);
		this.request('?test');
	}
};
