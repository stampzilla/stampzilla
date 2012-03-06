window.sape = {
    url:null,
    init: function(url) {
        this.requests = [];
        this.requestFailObserver = [];
        this.url = url;

        this.count = 0;
        //parent.window.sape.hello('!!!asd');
        window.parent.onkeyup = function(ev) {
                if (ev.keyCode == 27) {
                    window.sape.recive(null);
                }
        };

        this.request('?start');

        document.body.addEventListener('unload',function(){alert('abort')})
    },
    request: function( query ) {
        if(this.requests.length > 0 ){
            this.clearRequest(this.requests.shift());
        }
        var request = document.createElement('script');
        request.src = this.url + query + "&" + (++this.count);
        document.head.appendChild(request);
        this.requests.push(request);

        //Detect timeout
        clearTimeout(this.requestFailObserver);
        this.requestFailObserver = setTimeout(this.requestFail,5000 + 200);

       /* if (true) {
                //Firefox hack to avoid status bar always show a loading message
                //Ok this hack is little bit weird but it works!
                setTimeout(function() {
                        var tmp = document.createElement('iframe');
                        document.body.appendChild(tmp);
                        document.body.removeChild(tmp);
                },200);
        }*/
    },
    recive:function(data) {

        clearTimeout(this.requestFailObserver);
        this.clearRequest(this.requests.shift());
        this.failCounter = 0;

        this.request('?');
        if(data != null){
            parent.window.sape.recive(data);
        }
    },
    onInit:function(){
        parent.window.sape.ready();
        this.request('?');
    },
    clearRequest:function(request) {

        clearTimeout(this.requestFailObserver);
        request.parentNode.removeChild(request);

        //Avoid memory leaks
        if (request.clearAttributes) {
            request.clearAttributes();
        } else {
            for (var prop in request) delete request[prop];
        }
    },
    requestFail: function() {

        window.sape.clearRequest(window.sape.requests.shift());

        if (this.failCounter < 6) this.failCounter++;

        //this.cancelRequest();

        var delay = (this.failCounter*Math.random(300,1000));

        setTimeout(function() {window.sape.request('?check');},delay );
    },
};
