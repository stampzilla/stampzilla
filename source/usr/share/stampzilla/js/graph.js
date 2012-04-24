graph = {


    size :function() {
      var width, height;
      if (navigator.appName.indexOf("Microsoft") != -1) {
        width  = document.body.offsetWidth;
        height = document.body.offsetHeight;
      } else {
        width  = window.outerWidth;
        height = window.outerHeight;
      }

      window.resizeTo(width - 100, height-100);
      window.resizeTo(width, height);
    },

    draw: function(obj,field){

      obj = $(obj);

      obj.setStyle('height',400);
      obj.setStyle('width',700);

      var stock_annotations = [
        {
          series: "Real",
          x: "1929-08-15",
          shortText: "A",
          text: "1929 Stock Market Peak"
        },
        {
          series: "Nominal",
          x: "1987-08-15",
          shortText: "B",
          text: "1987 Crash"
        },
        {
          series: "Nominal",
          x: "1999-12-15",
          shortText: "C",
          text: "1999 (.com) Peak"
        },
        {
          series: "Nominal",
          x: "2007-10-15",
          shortText: "D",
          text: "All-Time Market Peak"
        }
      ];

    // From http://www.econstats.com/eqty/eq_d_mi_3.csv
      stockchart = new Dygraph(
        obj,
        //"dow.txt",
        "graph.php?field="+field,
        {
          showRoller: true,
          customBars: true,
          labelsKMB: true,
          drawCallback: function(g, is_initial) {
            if (!is_initial) return;
            g.setAnnotations( stock_annotations );
          }
        }
      );

      function stockchange(el) {
        stockchart.setVisibility(el.id, el.checked);
      }

      function annotationschange(el) {
        if (el.checked) {
          stockchart.setAnnotations(stock_annotations);
        } else {
          stockchart.setAnnotations([]);
        }
      }
            graph.size();
    
    }



}
