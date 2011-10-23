<?php 

if ( !isset($_GET['m']) )
	$_GET['m'] = '';

if ( !isset($_GET['s']) )
	$_GET['s'] = '';


if( $_GET['m'] == 'rooms' ) { ?>
<h1>Rum</h1>

<h2>Bumblebeeo</h2>
<div>
<div class="radio" onclick="radio(this,'to=plc&cmd=set&unit=Yoda|Roof&value=32767');">Roof off</div>
<div class="radio" onclick="radio(this,'to=plc&cmd=set&unit=Yoda|Roof&value=32767');">Roof full</div>
<div class="radio" onclick="radio(this,'to=plc&cmd=set&unit=Yoda|Roof&value=32767');">Roof circle</div>
</div>
<div class="toggle" onclick="toggle(this,'to=yamaha&cmd=Zone2Power|1','to=yamaha&cmd=Zone2Power|0');">Main Power</div>
<h2>Yoda</h2>
<div class="toggle" onclick="toggle(this,'to=plc&cmd=set&unit=Yoda|Roof&value=32767','to=plc&cmd=reset&unit=Yoda|Roof');">Roof</div>
<div class="toggle" onclick="toggle(this,'to=yamaha&cmd=Zone2Power|1','to=yamaha&cmd=Zone2Power|0');">Zone 2</div>

<?php } else { ?>

yeey

<?php } ?>
