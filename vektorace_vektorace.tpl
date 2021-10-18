{OVERALL_GAME_HEADER}

<!-- 
--------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- VektoRace implementation : © <Pietro Luigi Porcedda> <pietro.l.porcedda>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-------
-->

<!-- SCROLLABLE MAP DIV -->
<div id="map_container">
    <div id="map_scrollable">
        <div id="track">
            <!-- <div id="bggrid"></div> -->
            <div id="previews"></div>
        </div>
    </div>
    <div id="map_surface"></div>
    <div id="map_scrollable_oversurface">
        <div id="touchable_track">
            <div id="pos_highlights"></div>
            <div id="car_highlights"></div>
            <div id="boosts"></div>
        </div>
    </div>
    
    <!-- arrows -->
    <div class="movetop"></div> 
	<div class="movedown"></div> 
	<div class="moveleft"></div> 
	<div class="moveright"></div> 
</div>

<div></div>


<script type="text/javascript">

// JAVASCRIPT TEMPLATES

// -- gear selection dialog window --
var jstpl_dialogWindowContainer = "<div id='dialogWindowContainer'></div>";
var jstpl_gearSelectionWindow = "<div id='gearSelectionWindow'></div>";
var jstpl_selWinVectorPreview = "<div class='gearVector selWinVectorPreview vecPrev_${type} ${special} vector_${n}' id='gear_${n}' style='bottom:${bottom}px'></div>";
var jstpl_gearDotHighlight = "<div id='gearDotHighlight'></div>"

// -- table elements --
var jstpl_pitwall = "<div id='pitwall'></div>";
var jstpl_curve = "<div class='curve' id='curve_${n}'></div>";
var jstpl_car = "<div class='car' id='car_${color}'></div>";
var jstpl_gearVector = "<div class='gearVector vector_${n}' id='gear_${n}'></div>";
var jstpl_boostVector = "<div class='boostVector vector_${n}' id='boost_${n}'></div>";


// -- abstract elements (previews and selection area) -- 
var jstpl_posArea = "<div id='start_positioning_area'></div>";
var jstpl_selOctagon = "<div class='selectionOctagon' id='selOct_${x}_${y}'></div>";
var jstpl_dirArrow = "<div class='directionArrow ${color}Arrow' id='${direction}Arrow'></div>";

</script>  

{OVERALL_GAME_FOOTER}
