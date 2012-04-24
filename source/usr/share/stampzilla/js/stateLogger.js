stateLogger = {
    setState: function(data) {
        for( key in data.graphs ) {
            link = new Element('a', {
                onclick: "graph.draw('charts',"+data.graphs[key]+");"
            });
            link.innerHTML = key;
            $('stateLoggerCharts').adopt(link);
        }
    }
}
