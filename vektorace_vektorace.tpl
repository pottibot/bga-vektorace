{OVERALL_GAME_HEADER}

<!-- 
--------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- VektoRace implementation : © <Pietro Luigi Porcedda> <pietro.l.porcedda@gmail.com>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-------
-->

<!-- SCROLLABLE MAP DIV -->
<div id="map_container">

    <div id="map_scrollable">
        <div id="track">
            <div id="previews"></div>
            <div id="dirArrows"></div>
            <!-- <div id="bggrid"></div> -->
            <div id="track_img">
                <div id="top_left" class='track_img_slice'></div>
                <div id="top_right" class='track_img_slice'></div>
                <div id="bottom_left" class='track_img_slice'></div>
                <div id="bottom_right" class='track_img_slice'></div>
            </div>
        </div>
    </div>
    <div id="map_surface"></div>
    <div id="map_scrollable_oversurface">
        <div id="touchable_track">
            <div id="pos_highlights"></div>
            <div id="car_highlights"></div>
        </div>
    </div>

    
    
    <!-- arrows -->
    <div class="movetop"></div> 
	<div class="movedown"></div> 
	<div class="moveleft"></div> 
	<div class="moveright"></div> 

    <!-- <div id="centerCross">+</div>  -->
</div>



<div id="preferences">
    <div id="pref_illegalPos">
        <p>Display illegal positions:</p>
        <div>
            <div>
                <input type="radio" id="illegal_none" name="illegalPos" value="none">
                <label for="illegal_none">None</label>
            </div>
            <div>
                <input type="radio" id="illegal_blocked" name="illegalPos" value="" checked>
                <label for="illegal_blocked">Blocked position</label>
            </div>
        </div>
    </div>
</div>


<script type="text/javascript">

// JAVASCRIPT TEMPLATES

// -- game elements --
var jstpl_pitwall = "<div id='pitwall'></div>";
var jstpl_curve = "<div class='curve' id='curve_${n}'></div>";
var jstpl_car = "<div class='car' id='car_${color}'></div>";
var jstpl_gearVector = "<div class='gearVector vector_${n}' id='gear_${n}'></div>";
var jstpl_boostVector = "<div class='boostVector vector_${n}' id='boost_${n}'></div>";

// -- abstract elements (previews and selection area) -- 
var jstpl_posArea = "<div id='start_positioning_area'></div>";
var jstpl_FS_refCarAnchor = "<div id='FS_refCar_${car}' class='refCarAnchor'></div>";
var jstpl_selOctagon = "<div data-pos-index='${i}' class='selectionOctagon standardPos' id='selOct_${x}_${y}'></div>";
var jstpl_dirArrow = "<div class='directionArrow ${color}Arrow' id='${direction}Arrow'></div>";
var jstpl_turnPosInd = "<div id='turnPos_${pos}' class='turnPosIndicator'></div>"

var jstpl_tokenSelWin =   "<div id='tokenSelectionWindow'></div>";
var jstpl_tokenSelDiv =   "<div id='tokenSelectionDiv'> \
                                <div id='tireSelection' class='incrementerDiv'> \
                                    <div class='counterTitle'>Tire Tokens</div> \
                                    <div><div class='token tireToken'></div></div> \
                                    ${tireIncrementer} \
                                </div> \
                                <div id='nitroSelection' class='incrementerDiv'> \
                                    <div class='counterTitle'>Nitro Tokens</div> \
                                    <div><div class='token nitroToken'></div></div> \
                                    ${nitroIncrementer} \
                                </div> \
                            </div>";
var jstpl_tokenIncrementer =    "<div id='${type}TokenIncrementer' class='tokenIncrementer'> \
                                    <button class='minus' type='button'>-</button> \
                                    <input type='number' value='${min}' min='${min}' max='${max}'> \
                                    <button class='plus' type='button'>+</button> \
                                </div>";
var jstpl_cross = '<div class="cross"></div>';
var jstpl_draftingMeter = '<div class="draftingMeter" id="dfMeter_${enemy}"></div>';

// -- tokens
var jstpl_token = '<div class="token ${type}Token"></div>';



// -- gear selection dialog window --
var jstpl_dialogWindowContainer = "<div id='dialogWindowContainer'></div>";
var jstpl_gearSelectionWindow = "<div id='gearSelectionWindow'></div>";
var jstpl_selWinVectorPreview = "<div data-gear-n='${n}' class='gearVector vector_${n}' id='gear_${n}' style='bottom:${bottom}px'></div>";
var jstpl_gearDotHighlight = "<div id='gearDotHighlight'></div>"

// -- player board
var jstpl_player_board = '<div id="itemsBoard_${id}" class="itemsBoard">${standings}${lap}${gear}<br>${tire}${nitro}</div>';
var jstpl_tokens_counter = '<span class="pbItem"><div id="${type}Tokens_p${id}" class="pbIcon token ${type}Token"></div><span id="${type}Tokens_p${id}" class="pbCounter">0</span></span>';
var jstpl_standings_position = '<span class="pbItem"><div id="standings_p${id}" class="pbIcon standingsIcon"></div><span id="turnPos_p${id}" class="pbCounter"></span><span>°</span></span>';
var jstpl_lap_counter = '<span class="pbItem"><div id="lap_p${id}" class="pbIcon lapIcon"></div><span id="lapNum_p${id}" class="pbCounter">0</span><span>°</span></span>';
var jstpl_current_gear = '<span class="pbItem"><div id="gear_p${id}" class="pbIcon gearIndicator gearInd_${n}"></div></span>';

</script>  

{OVERALL_GAME_FOOTER}
