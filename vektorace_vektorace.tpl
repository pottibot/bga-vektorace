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
            <div id="track_img">
                <div id="top_left" class='track_img_slice'></div>
                <div id="top_right" class='track_img_slice'></div>
                <div id="bottom_left" class='track_img_slice'></div>
                <div id="bottom_right" class='track_img_slice'></div>
            </div>
            <div id='trackLayoutMarker'></div>
            <div id='trackGuide'>
                <svg xmlns="http://www.w3.org/2000/svg" width="8000" height="4394" viewBox="0 0 8858 4866">
                    <path id="limit_right" class="track-guide" d="M8900.34,2738.44L6144.37-37.781"/>
                    <path id="triangle" class="track-guide" d="M1904.82,3204.07l-218.21-219.18L4163.33,1273.91l936.33-1.98L7514.88,2969.09l-233.1,233.99Z"/>
                    <path id="limit_left" class="track-guide" d="M-89.438,3097.37L3211.19-184.97"/>
                    <path id="trapezoid_right" class="track-guide" d="M1684.24,2985.15l2477.7-1715.2,3125.92,0.99,216.41,212.91-10.92,1508.24-209.46,210.93H1900.64Z"/>
                    <path id="trapezoid_left" class="track-guide" d="M7518.12,2963.09L5102.63,1271.02l-3212.7,2.96L1675.85,1486.2l10.95,1503.54,209.98,210.27H7293.62Z"/>
                    <path id="oval" class="track-guide" d="M1685.88,2990.5l-2.98-1520.97,210.63-207.63H7287.46l209.64,208.63-1,1519.97-213.61,211.6-5384.98,1Z"/>
                </svg>
            </div>
            <div id="guide_arrows">
                <div class='guide_arrow'></div>
                <div class='guide_arrow' style="left: 120px;"></div>
            </div>
            <div id="previews"></div>
            <div id="game_elements"></div>
            <div id="dirArrows"></div>
        </div>
    </div>
    <div id="map_surface"></div>
    <div id="map_scrollable_oversurface">
        <div id="touchable_track">
            <div id="car_highlights"></div>
            <div id="pos_highlights"></div>
        </div>
    </div>
    
    <!-- arrows -->
    <div class="map_button movetop"></div> 
	<div class="map_button movedown"></div> 
	<div class="map_button moveleft"></div> 
	<div class="map_button moveright"></div> 

    <div id="map_control">
        <div id="button_zoomIn" class="map_control_button"></div> 
        <div id="button_zoomOut" class="map_control_button"></div> 
        <div id="button_fitMap" class="map_control_button"></div> 
        <div id="button_scrollToCar" class="map_control_button"></div> 
    </div> 

    <!-- <div id="game_details">
        <div id="race_laps">
            <span>{GAME_DET_LAPS}: </span>
        </div>
        <div id="circuit_layout">
            <span>{GAME_DET_LAYOUT}: </span>
        </div>
    </div> -->

</div>


<script type="text/javascript">

// JAVASCRIPT TEMPLATES

// -- game elements --
var jstpl_pitwall = "<div id='pitwall' class='gameElement'></div>";
var jstpl_curve = "<div class='curve gameElement' id='curve_${n}'></div>";
var jstpl_car = "<div class='car gameElement' id='car_${color}'></div>";
var jstpl_gearVector = "<div class='gearVector vector gameElement' id='gear_${n}'></div>";
var jstpl_boostVector = "<div class='boostVector vector gameElement' id='boost_${n}'></div>";

// -- abstract elements (previews and selection area) -- 
var jstpl_posArea = "<div id='start_positioning_area'></div>";
var jstpl_refCarAnchor = "<div id='refCar_${car}' class='refCarAnchor'></div>";
var jstpl_marker = "<div class = 'marker ${type}Marker'></div>";
var jstpl_FS_octagon = "<div class='fsOctagon'></div>";
var jstpl_selOctagon = "<div data-pos-index='${i}' class='selectionOctagon standardPos' id='selOct_${x}_${y}'></div>";
var jstpl_carRect = "<div class='carRect' id='carRect_${id}' style='width:${w}px; height:${h}px;'></div>";
var jstpl_dirArrow = "<div class='directionArrow ${color}Arrow' id='${direction}Arrow'></div>";
var jstpl_turnPosInd = "<div id='turnPos_${pos}' class='turnPosIndicator'></div>"

// token sel window
var jstpl_tokenSelWin =   "<div id='tokenSelectionWindow'>\
                                <div id='tokenSelPreferences'>\
                                    <div>Fill amount: <span>${amt}</span></div>\
                                    <div>Autofill <input type='checkbox' id='tokenAutofill' checked></div>\
                                </div>\
                           </div>"
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

// custom order sel window
var jstpl_orderSelWindow = "<div id='orderSelWindow'>\
                                <div id='orderSelOrderBy'><input type='button' value='Order by ELO'></div>\
                                <div id='orderSelContainer'></div>\
                            </div>";
var jstpl_orderSelPlayer = "<div class='orderSelPlayer' id='orderSelPlayer_${id}'>\
                                <span style='color: #${color}'>${name}</span>\
                                <div class='car gameElement' id='car_${color}'></div>\
                                <input type='number' value='${curr}' min='1' max='${playersNum}'>\
                            </div>";

var jstpl_cross = '<div class="cross"></div>';
var jstpl_draftingMeter = '<div class="draftingMeter"></div>';

// -- tokens
var jstpl_token = '<div class="token ${type}Token"></div>';

//-- log icon
var jstpl_gearInd = '<div class="gearIndicator gearInd_${n}"></div>';

// -- tooltips imgs
var jstpl_attTooltip = '<div class="attTooltip"></div>';

// -- gear selection dialog window --
var jstpl_dialogWindowContainer = "<div id='dialogWindowContainer'></div>";
var jstpl_gearSelectionWindow = "<div id='gearSelectionWindow'></div>";
var jstpl_selWinVectorPreview = "<div data-gear-n='${n}' class='gearVector vector' id='gear_${n}' style='bottom:${bottom}px'></div>";
var jstpl_gearDotHighlight = "<div id='gearDotHighlight'></div>"

// -- player board
var jstpl_player_board = '<div id="itemsBoard_${id}" class="itemsBoard">${standings}${lap}${gear}<br>${tire}${nitro}</div>';
var jstpl_tokens_counter = '<span id="pb_${type}Tokens_p${id}" class="pbItem pb_${type}Tokens"><div class="pbIcon token ${type}Token"></div><span id="${type}Tokens_p${id}" class="pbCounter">0</span></span>';
var jstpl_standings_position = '<span id="pb_turnPos_p${id}" class="pbItem pb_standingPos"><div class="pbIcon standingsIcon"></div><span id="turnPos_p${id}" class="pbCounter"></span></span>';
var jstpl_lap_counter = '<span id="pb_lapNum_p${id}" class="pbItem pb_lapNum"><div class="pbIcon lapIcon"></div><span id="lapNum_p${id}" class="pbCounter">0</span><span>/${tot}</span></span>';
var jstpl_current_gear = '<span id="pb_gearInd_p${id}" class="pbItem pb_gearInd"><div id="gear_p${id}" class="pbIcon gearIndicator gearInd_${n}"></div></span>';

</script>  

{OVERALL_GAME_FOOTER}
