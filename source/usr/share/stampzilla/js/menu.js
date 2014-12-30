var menu = {
	curSub:'',
	showPage:function(page){
		$$('.page').removeClass('active');
		if ( $('page_'+page) != undefined ) {
			$('page_'+page).addClass('active');
		}
		location.hash = page;
	},
	sub:function(obj) {
		$$('#submenu a').removeClass("active");
		obj.addClass("active");
		$('submenu').fade('out');

		menu.showPage(obj.id);
	},
	main:function(obj) {
		$$('.menu a').removeClass("active");
		obj.addClass("active");

		if ( menu.curSub != obj.id ) {
			show = false;
			menu.curSub = obj.id;
			$('submenu').innerHTML = '';
			for( node in menu.layout[obj.id] ) {
				if ( node == 0 ) {
					continue;
				}
				show = true;
                if('ontouchstart' in document.documentElement) {
					$('submenu').innerHTML += '<a ontouchstart="menu.sub(this)" id="'+node+'">'+menu.layout[obj.id][node][0]+'</a>';
				} else {
					$('submenu').innerHTML += '<a onclick="menu.sub(this)" id="'+node+'">'+menu.layout[obj.id][node][0]+'</a>';
				}
			}
		}

		if ( show ) {
			//$('submenu').tween('bottom',62);
			$('submenu').fade('in');
		} else {
			menu.showPage(obj.id);
		   // $('submenu').tween('bottom',22);
			$('submenu').fade('out');
		}
	}
}
