/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * VektoRace implementation : © <Pietro Luigi Porcedda> <pietro.l.porcedda@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

define([
    "dojo","dojo/_base/declare",
    "ebg/core/gamegui",
    "ebg/counter",
    "ebg/scrollmap"],

function(dojo, declare, other) {
    return declare("bgagame.vektorace", ebg.core.gamegui, {

        //++++++++++++++++++++++++//
        // SETUP AND GLOBALS INIT //
        //++++++++++++++++++++++++//
        //#region setup

        constructor: function() {
            console.log('vektorace constructor');
              
            // GLOBAL VARIABLES INIT

            // all octagon sizes: -!! TO BE REPLACED BY SERVER ONES !!-
            // useful measures to rescale octagons and calculate distances between them
            // these measure should be set always using setOctagonSize(size), which given a certain octagon size (length of one side of the square box that contains the octagon), it calculates all deriving measures
            this.octSize; // length of the side of the square box containing the octagon
            this.octSide; // length of each side of the octagon
            this.octRad; // radius of the circle that inscribe the octagon. or distance between octagon center and any of its vertecies
            this.octSeg; // segment measuring half of the remaining length of box size, minus the length of the octagon side. or the cathetus of the right triangle formed on the diagonal sides of the octagon.

            // keeps track of the current scale of the interface
            this.interfaceScale = 3;
        },
        
        // setup: method called each time interface loads. should set up game sistuation according to db.
        //        argument 'gamedatas' cointains data extracted with getAllDatas() game.php method. it is also kept as a global variable as this.gamedatas (function to update it should exist but it should also be unnecessary)
        setup: function(gamedatas) {

            console.log("Starting game setup");
            
            // -- SETUP PLAYER BOARDS --
            for (var player_id in gamedatas.players) { // js foreach extract the keys, not the values
                var player = gamedatas.players[player_id];
                // TODO
            }

            // -- SCROLLMAP INIT --
            // (copied from doc)
            this.scrollmap = new ebg.scrollmap(); // object declaration (can also go in constructor)
   	        // make map scrollable        	
            this.scrollmap.create( $('map_container'),$('map_scrollable'),$('map_surface'),$('map_scrollable_oversurface') );
            this.scrollmap.setupOnScreenArrows( 150 ); // this will hook buttons to onclick functions with 150px scroll step

            this.scaleInterface();

            // -- DIALOG WINDOW INIT --
            // (copied from doc)
            this.gearSelDW = new ebg.popindialog();
            this.gearSelDW.create( 'GearSelectionDialogWindow' );
            this.gearSelDW.setTitle( _("Select a gear vector to declare") );
            this.gearSelDW.setMaxWidth( 600 );

            // -- EXTRACT OCTAGON REFERENCE MEASURES --
            // actually permanent since all rescaling is done with css transform
            this.octSize = parseInt(gamedatas.octagon_ref['size']);
            this.octSide = parseInt(gamedatas.octagon_ref['side']);
            this.octSeg = parseInt(gamedatas.octagon_ref['corner_segment']);
            this.octRad = parseInt(gamedatas.octagon_ref['radius']);

            // -- PLACE TABLE ELEMENTS ACCORDING TO DB --
            // POSIBILITY TO REPLACE ALL ELEMENTS ROTATIONS WITH CSS CLASSES INDICATING THE ROTATION (USEFUL AS IT KEEPS ELEMENT ROTATION DATA)
            for (var i in gamedatas.table_elements) {
                var el = gamedatas.table_elements[i];

                switch (el.entity) {
                    case 'pitwall':
                        
                        dojo.place(
                            this.format_block('jstpl_pitwall'),
                            'track' );
                        this.slideToObjectPos('pitwall', 'track', el.pos_x, -el.pos_y,0).play(); // pos are multiplied with oct size because DB store normalized position
                            
                        // i wanted block formatting to be more symbolic by injecting css transform matrix directly on creation
                        // but it conlficts with positioning and formatting itself (translate won't work, placeOnObject would cancel everythig, sliding makes it weird)
                        // anyway, better to make all transformation later, and in that precise order
                        // css matrix didn't make sense, so let's keep the single transforms
                        // same goes for all the other element
                        dojo.style('pitwall','transform','translate(-50%,-50%) scale('+this.octSize/522+') rotate('+(el.orientation-2)*-45+'deg)');
                        // transforms explained:
                        // - translate centers the element in original size (big) on the origin of the screen (top left of 'track' element)
                        // - scale makes it of the size of current octagon size (522 is the width of the original HQ element)
                        // - rotate apply rotation to the element by taking the k factor of 45 deg rotation from the database, subtracting it from the fixed orientation of the original element and finally multiplies it with -45deg (minus, because css rotate clockwise, while DB store orientation factor counte-clockwise)

                        break;

                    case 'curve':

                        var x = this.octSeg / (this.octSize - this.octSeg); // generic scaling factor to extract measure of the full length of octagon from which the curve is derived
                        var curveOctSize = 465 + x*465;
                        
                        dojo.place(
                            this.format_block('jstpl_curve', {n: el.id}),
                            'track'
                        );
                        this.slideToObjectPos('curve_'+el.id, 'track', el.pos_x, -el.pos_y,0).play();
                        dojo.style('curve_'+el.id,'transform','translate(-50%,-50%) scale('+this.octSize/curveOctSize+') rotate('+(el.orientation-3)*-45+'deg)');

                        break;

                    case 'car':

                        var color = gamedatas.players[el.id].color;

                        dojo.place(
                            this.format_block('jstpl_car', {color: color}),
                            'track'
                        );

                        if (el.pos_x && el.pos_y) { // pos are defined, place element on screen
                            console.log('Positioning car to '+el.pos_x+', '+el.pos_y);
                            this.slideToObjectPos('car_'+color,'track', el.pos_x, -el.pos_y,0).play();
                        } else { // pos are not defined, make element invisible, it hasn't been placed yet
                            dojo.style('car_'+color,'display','none');
                        }
                        dojo.style('car_'+color,'transform','translate(-50%,-50%) scale('+this.octSize/522+') rotate('+(el.orientation-4)*-45+'deg)');

                        break;

                    default: console.log('Unidentified Non-Flying Object');
                        break;
                }
            }

            // -- CONNECT USER INPUT --
            dojo.query('#map_container').connect('mousewheel',this,'wheelZoom'); // zoom wheel
 
            // -- SETUP ALL NOTIFICATION --
            this.setupNotifications();

            console.log( "Ending game setup" );
        },
        
        //#endregion

        //+++++++++++++++++++++++//
        // STATE CHANGE HANDLERS //
        //+++++++++++++++++++++++//
        //#region states

        // [methods that apply changes to the interface (and regulates action buttons) depending on game state]
        
        // onEnteringState: method called each time game enters a new game state.
        //                  used to perform UI changes at beginning of a new game state.
        //                  arguments are symbolic state name (needed for internal mega switch) and state arguments extracted by the corresponding php methods (as stated in states.php)
        onEnteringState: function(stateName,args) {
            console.log( 'Entering state: '+stateName );
            console.log('State args below:');
            console.log(args.args);
            
            switch(stateName) {

                case 'playerPositioning':

                    // avoid displaying additional infos for players who are not active
                    if(!this.isCurrentPlayerActive()) return; // maybe better place out of switch to prevent not current player entering any state handler
    
                    if (Array.isArray(args.args)) {
                        // if returned object is empty (is an empty array), it means that it's the first turn, thus the player can freely position its car
                        // inside a restricted area
                        // SHOULD ALSO CHECK IF IT'S INDEED THE FIRST TURN? CAN IT HAPPEN THAT THE CALLING METHOD RETURNS NO POSITIONS?
                        console.log('displaying positioning area for first player');
                        
                        dojo.place(
                            this.format_block('jstpl_posArea',{}),
                            'pos_highlights'
                        );
                        // slide position to match pitwall
                        // !! sliding cordinates are not robust, they depend on the initial interface zoom
                        var wallsize = $('pitwall').getBoundingClientRect().width;
                        var seg = wallsize/4 / this.octSize * this.octSeg;
                        this.slideToObjectPos('start_positioning_area','map_scrollable_oversurface',wallsize-seg*2-5,-this.octSize/2).play();

                        // THIS OBJECT TRANSLATION IS VALID ONLY FOR STATICALLY POSITIONED PIWALLS IN ORIZONTAL ORIENTATION
                        // THAT'S BECAUSE FINAL GAME PROBABLY WON'T ALLOW CUSTOM TRACK LAYOUT
                        dojo.style('start_positioning_area','transform','translate(0,-100%)')

                        dojo.query('#start_positioning_area').connect('onclick',this,'selectStartCarPos');
                        dojo.query('#start_positioning_area').connect('mousemove',this,'previewStartCarPos');

                    } else {
                        // if returned object has positions (lists indexed by flying start reference car),
                        // display them. how?

                        if (Object.keys(args.args).length > 1 ) {
                            // if possible fs reference cars are more than one,
                            // let player decide what car to display fs positions from

                            // state descriptions changes for when player is deciding reference car
                            var orginalDescription = this.gamedatas.gamestate.descriptionmyturn;
                            var alternativeDescription = _('${you} have to select a reference car to determine all possible "flying-start" positions');

                            this.gamedatas.gamestate.descriptionmyturn = alternativeDescription;
                            this.updatePageTitle();
                            
                            // iterate on all possible reference cars, place selection octagon, connect it to function that displays fs positions, add button to reset ref car
                            Object.keys(args.args).forEach(id => {


                                var col = this.gamedatas.players[id].color;

                                var posX = dojo.style($('car_'+col),'left');
                                var posY = -(dojo.style($('car_'+col),'top'));

                                dojo.place(
                                    this.format_block('jstpl_selOctagon',{x:posX, y:posY}),
                                    'car_highlights'
                                );
                                this.slideToObjectPos('selOct_'+posX+'_'+posY,'touchable_track',posX,-posY,0).play();
                                // usual transformation to adapt new element to interface
                                dojo.style('selOct_'+posX+'_'+posY,'transform','translate(-50%,-50%) scale('+this.octSize/2000+')');
                                
                                // connect selection octagon with temp function that handle this specific case
                                this.connect($('selOct_'+posX+'_'+posY),'onclick', (evt) => {
                                    dojo.stopEvent(evt);

                                    this.gamedatas.gamestate.descriptionmyturn = orginalDescription;
                                    this.updatePageTitle();
                                    
                                    // destroy all highlited positions, if present
                                    dojo.empty('pos_highlights')
                                    // hide all highlighted cars, to focus user attention to new highlighted positions and clean interface
                                    dojo.query('#car_highlights > *').style('display','none');

                                    // finally, display all fs position from this ref car
                                    this.displaySelectionOctagons(Object.values(args.args[id]));
                                    this.connectActionElements('selectCarFSPos','previewCarPos');

                                    // add red actionbutton (persists till end of state), to reset choice of ref car
                                    this.addActionButton('resetFSref_button', _('Reset'), () => {
                                        this.gamedatas.gamestate.descriptionmyturn = alternativeDescription;
                                        this.updatePageTitle();

                                        dojo.style('car_'+this.gamedatas.players[this.getActivePlayerId()].color,'display','none');

                                        // remove all highlighted pos and show again selection of ref cars
                                        dojo.empty('pos_highlights');
                                        dojo.query('#car_highlights > *').style('display','block');
                                    }, null, false, 'red');
                                    
                                    this.disconnect();
                                });
                            })

                        } else {
                            // if object contains positions only for one car
                            // display only those
                            console.log('displaying flying start positions for the only possible reference car');
                            console.log(args.args);

                            this.displaySelectionOctagons(Object.values(args.args)[0]);
                            this.connectActionElements('selectCarFSPos','previewCarPos');
                        }
                    }

                    break;         
                
                case 'greenLight':

                    if(!this.isCurrentPlayerActive()) return; // always prevent interface to change for those whom are not the active player

                    // add putton that displays vector selection in 'green light' mode
                    this.addActionButton('showGearSelDialogButton', _('show selection'), () => {
                        this.displayGearSelDialog('GreenLight');
                    }, null, false, 'blue');
                    
                    break;

                case 'playerMovement':

                    if(!this.isCurrentPlayerActive()) return;

                    // change state description text to specifically describe the first step of the movement phase
                    this.gamedatas.gamestate.descriptionmyturn = _('${you} have to decide how to place your declared gear vector');
                    this.updatePageTitle();

                    // extract coordinates of each possible vector placement position
                    var vecPossiblePos = [];
                    args.args.positions.forEach(pos => {
                        vecPossiblePos.push(pos.coordinates);
                    });

                    this.displaySelectionOctagons(vecPossiblePos); // display vector attachment position in front of the car
                    this.connectActionElements('selectVectorPos','previewVectorPos'); // then connect highlights to activate hover preview and click input event
                
                case 'attackManeuvers':
                    //TODO
                    break;

                case 'futureGearDeclaration':

                    if(!this.isCurrentPlayerActive()) return; // always prevent interface to change for those whom are not the active player

                    // display button to open gear selection dialog window in standard mode.
                    this.addActionButton('showGearSelDialogButton', _('show selection'), () => {
                        this.displayGearSelDialog('');
                    }, null, false, 'blue');
                    
                    break;

                case 'dummmy':
                    break;
            }
        },

        // onLeavingState: equivalent of onEnteringState(...) but needed to perform UI changes before exiting a game state
        onLeavingState: function(stateName) {
            console.log('Leaving state: '+stateName);
            
            switch(stateName) {

                case 'playerPositioning':

                    // clean interface
                    dojo.empty('pos_highlights');
                    dojo.empty('car_highlights');
                    this.removeActionButtons();
                    break;
           
            case 'dummmy':
                break;
            }               
        }, 

        // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
        //                        action status bar (ie: the HTML links in the status bar).
        onUpdateActionButtons: function(stateName,args) {
            console.log( 'onUpdateActionButtons: '+stateName );
                      
            if(this.isCurrentPlayerActive()) {            
                switch(stateName) {
                    case 'playerPositioning':
                        break;

                    case 'playerMovement':
                        break;
                }
            }
        },

        //#endregion

        //+++++++++++++++++//
        // UTILITY METHODS //
        //+++++++++++++++++//
        //#region utility

        // useful method copied from wiki
        ajaxcallwrapper: function(action, args, handler) {
            if (!args) args = []; // this allows to skip args parameter for action which do not require them
                
            args.lock = true; // this allows to avoid rapid action clicking which can cause race condition on server

            if (this.checkAction(action)) { // this does all the proper check that player is active and action is declared
                
                this.ajaxcall("/" + this.game_name + "/" + this.game_name + "/" + action + ".html", args, // this is mandatory fluff 
                    this, (result) => { },  // success result handler is empty - it is never needed
                    handler); // this is real result handler - it called both on success and error, it has optional param  "is_error" - you rarely need it
                }
        },

        // [general purpos methods to scale, move, place, change interface elements]

        // wheelZoom: format input wheel delta and calls method to scale interface accordingly
        wheelZoom: function(evt) {
            dojo.stopEvent(evt);

            scaleDirection = evt.wheelDelta / 120;
            var scalestep = this.interfaceScale - scaleDirection

            if (scalestep >= 0 && scalestep < 7) {
                this.interfaceScale = scalestep;
                this.scaleInterface();
            }
        },

        // scaleInterface: applies scale on the whole game interface with factor calculated as 0.8^interfaceScale step.
        //                 scaling obtained with css transform of parent of all table elments, so to keep distance between them proportional
        scaleInterface: function() {
            dojo.style('track','transform','scale('+Math.pow(0.8,this.interfaceScale)+')');
            dojo.style('touchable_track','transform','scale('+Math.pow(0.8,this.interfaceScale)+')');
        },

        // test function (remove)
        loadTrackPreset: function(){

            dojo.place(
                this.format_block('jstpl_pitwall'),
                'track'
            );
            this.slideToObjectPos('pitwall', 'track', -100, 0).play();

            var x = this.octSide / (this.octSize - this.octSeg)
            var u = x*465
            var c = 465 - u   
            // useless position fine tuning
            /* var z = Math.sqrt(Math.pow(c,2)-(Math.pow(u,2)/4))
            var y = z * 50 / 465      */
            
            dojo.place(
                this.format_block('jstpl_curve', {n: 1, deg: -45, scale: 50/(465 + c)}),
                'track'
            )
            this.slideToObjectPos('curve_1', 'track', -8*50, -4*50).play();

            dojo.place(
                this.format_block('jstpl_curve', {n: 2, deg: 45, scale: 50/(465 + c)}),
                'track'
            )
            this.slideToObjectPos('curve_2', 'track', +8*50, -4*50).play();
        },

        // moveCar: moove player car to a new position. pos are normalized and will be multiplied to match interface
        // doesn't have rotation animation
        moveCar: function(id, posX, posY, rotate = 0) {

            var color = this.gamedatas.players[id].color;

            if (dojo.style("car_"+color,'display') == 'none') { // if car was hidden, thus never placed, make it visible and slide it to player board to make animation prettier
                dojo.style("car_"+color,'display','block');
                this.slideToObject('car_'+color,'overall_player_board_'+id,0).play();
            }

            // FOR SOME UNKNOWN REASON, slideToObjectPos is influenced by interface zoom (global transform scale propriety of '#track' element)
            // so resetting scale and re-apply original scale value after translation solves the problem
            // DUNNO IF THERE'S A MORE ELEGANT WAY TO DO THIS. probably not using slideToObjectPos
            dojo.style('track','transform','scale(1)');
            this.slideToObjectPos("car_"+color,"track",posX-this.octSize/2, -posY-this.octSize/2, 500).play();  // !!TODO!! center car element to center pos should be done at page setup
            this.scaleInterface(0);

            // 'if' not strictly necessary, just avoids to fill transform propriety with 0 deg rotations
            if (rotate != 0) $("car_"+color).style.transform += 'rotate('+rotate*-45+'deg)'; // NOT ANIMATED, FIND WAYS TO DO THAT
        },

        carMovementAnimation: function(id, x, y, rotation, gear, direction) {
            var color = this.gamedatas.players[id].color;
            var carId = 'car_'+color;

            dojo.place(
                this.format_block('jstpl_gearVector', {n:gear}),
                'track'
            );

            dojo.style(carId,'transform','translate(-50%,-50%) scale('+this.octSize/522+') rotate('+(direction-2)*-45+'deg)');

            var offset = (gear-1)*parseInt($('gear_'+gear).getBoundingClientRect().height);

            this.slideToObjectPos('gear_'+gear, 'track', 0-offset, 0, 1000).play();

            this.moveCar(id,x,y,rotation);

            this.slideToObjectAndDestroy('gear_'+gear, 'overall_player_board_'+id, 1000);
        },

        // displaySelectionOctagons: place and displays a list of selection octagons. accepts an array of objects {x:, y: } indicating the center coordinates of each octagon to display.
        displaySelectionOctagons: function(positions) {
            dojo.empty('pos_highlights');

            positions.forEach(pos => {
                dojo.place(
                    this.format_block('jstpl_selOctagon',{ x: pos.x, y: pos.y}),
                    'pos_highlights'
                );

                this.slideToObjectPos('selOct_'+pos.x+'_'+pos.y,'touchable_track',pos.x,-pos.y,0).play();
                dojo.style('selOct_'+pos.x+'_'+pos.y,'transform','translate(-50%,-50%) scale('+this.octSize/2000+')');
            });
        },

        // displays direction arrow to select orientation of F8 after movement. works similarly to method above
        displayDirectionArrows: function(positions, direction) {
            dojo.empty('pos_highlights');

            positions.forEach(pos => {
                dojo.place(
                    this.format_block('jstpl_dirArrow',{ color: pos.color, direction: pos.direction}),
                    'pos_highlights'
                );

                this.slideToObjectPos(pos.direction+'Arrow','touchable_track',pos.coordinates.x,-pos.coordinates.y,0).play();
                
                // extract arrow direction to determine rotation of dom element
                var rotation;
                switch (pos.direction) {
                    case 'right': rotation = -1; break;
                    case 'forward': rotation = 0; break;
                    case 'left': rotation = 1; break;
                }

                var scale = 0.7; // element is scaled to match a standard 100px octagon, then scaled again to reach desired size

                dojo.style(pos.direction+'Arrow','transform','translate(-50%,-50%) scale('+this.octSize/522+') rotate('+(direction-2+rotation)*-45+'deg) scale('+scale+')');
            });
        },

        // connectActionElements: function to connect position highlights elements (#pos_highlights > *) such as selection octagons (but it is also used for direction arrows) to specific handlers for click and mouseEnter events.
        //                        arguments are the names of the handlers method to call.
        //                        method connects also to standard method that wipes any preview on screen on mouse out. kinda stiched solution for previews sticking to position even when mouse is not hovering element
        connectActionElements: function(onclickHandler, onmouseenterHandler) {
            dojo.query('#pos_highlights > *').connect('onclick', this, onclickHandler);
            dojo.query('#pos_highlights > *').connect('onmouseenter', this, onmouseenterHandler);
            dojo.query('#pos_highlights > *').connect('onmouseleave', this, dojo.hitch(this, (evt) => {
                dojo.stopEvent(evt);
                dojo.empty('previews');
            }));
        },

        // validateOrCancelCarPosition: function called when user chooses new car position (car is already moved there). used to clean interface and display confirmation or cancel buttons
        validateOrCancelCarPosition: function(posX,posY) {
            // NOTE: ALL PREVIOUSLY ADDED BUTTONS WILL PERSIST HERE

            // it's wise to hide any red button during this phase, as to no interfere with the current action taking place
            dojo.query('#generalactions > .bgabutton_red').style('display','none');

            // since this is time for validation of the move, we can hide all other option while player chooses to confirm
            dojo.style('pos_highlights','display','none');

            // button to cancel new position. it reverts move by hiding car (MAY BE UNSUITABLE FOR CERTAIN SITUATIONS), removing all added buttons, and displaying any previuously hid red button
            this.addActionButton( 'cancelPos_button', _('Cancel'),
                () => {
                    dojo.destroy($('cancelPos_button'));
                    dojo.destroy($('validatePos_button'));
                    dojo.query('#generalactions > .bgabutton_red').style('display','inline-block');

                    dojo.style('pos_highlights','display','block');
                    dojo.style('car_'+this.gamedatas.players[this.getActivePlayerId()].color,'display','none');
                },
            null, false, 'gray'); 

            // button to validate new position. finally sends position to server to make decision permanent.
            this.addActionButton( 'validatePos_button', _('Validate'), () => this.ajaxcallwrapper('selectPosition', {x: posX, y: posY}) ); 
        },

        // method that sets and displays a dialog window containing all gear vector previews, for gear selecetion (green-light phase/emergency brake event) or declaration (standard end of movement step) method uses a switch to handle all cases.
        // method handles all cases and exception regardin gear selection through a switch on a caseName argument string.
        // displayed vector previews can either be 'active' (meaning they can be freely chose and make a small animation when hovered) or 'inactive' (meaning they are not immediatly usable and are white with 0.5 opacity).
        // Active vectors can also be 'current' (meaning it is the current vector, yellow circle on top)
        // Inactive vector can also be 'purchasable' (meaning they can be unlocked by spending nitro o tire tokens, cost on top) or 'blocked' (meaning their use temporarly blocked by a game mechanic, red x on top)
        // Cases can be:
        // - Default: display all gear vectors, highlighting the current one (this.gamedatas.gamestate.args.currentGear), and marking with the right number of either tire or nitro token the gears that excede the shift by 1 starting from the current vector
        // - GreenLight: its a special phase of the game where the first player chooses the starting gear for all the players. he might choose only the gears between 3 and 5 with no exception
        // - EmergencyBreak: it's a special move permitted only when the player cannot place its declared gear or car anywhere because it would intersect with other table elements. the player might choose to decellerate during vector placement, spending one tire token for every shifted gear. after this move, the player cannot shift gear up for the next turn.
        // - Crash: when a player cannot make valid moves, even with an emergency break, he will skip this movement turn, turn his car by 45deg, if he chooses so and start next turn with gear 1.
        // - Ramming: when a player suffers ramming ('bussata', in italian original translation) by another player, he wont be able to shift gear down for the next turn.
        displayGearSelDialog: function(caseName = '') {

            // Show the dialog
            this.gearSelDW.setContent(this.format_block('jstpl_dialogWindowContainer')); // Must be set before calling show() so that the size of the content is defined before positioning the dialog
            this.gearSelDW.show();
            this.gearSelDW.replaceCloseCallback( () => { this.gearSelDW.hide(); } );

            dojo.place(
                this.format_block('jstpl_gearSelectionWindow'),
                'dialogWindowContainer'
            );

            var curr; 
            var gears;

            switch (caseName) {
                case 'GreenLight':
                    curr = null;
                    gears = [3, 4, 5];
                    break;
            
                default:
                    curr = parseInt(this.gamedatas.gamestate.args.gear);
                    gears = [curr-1, curr, curr+1];
                    break;
            }

            // format blocks and place vectos in DOM
            for (var i=1; i<=5; i++) {

                var type, special;

                if (gears.includes(i)) {
                    type = 'active';

                    if (i == curr)
                        special = 'current';
                } else type = 'inactive';

                dojo.place(
                    this.format_block('jstpl_selWinVectorPreview', {
                        type: type,
                        special: (special)? 'vecPrev_' + special : '',
                        n: i,
                        bottom:(5-i)*522/2}
                    ),
                    'gearSelectionWindow'
                );
            }
            
            // seem useless, but it is needed for element positioning
            this.placeOnObject('gearSelectionWindow', 'dialogWindowContainer');

            // finally, connect all vetors to handler that ajax call server
            // TODO: ACTUALLY CONNECT ONLY ACTIVE OR PURCHASABLE VECTORS
            dojo.query('.selWinVectorPreview').connect('onclick',this,dojo.hitch(this, (evt) => {
                dojo.stopEvent(evt);
                this.gearSelDW.hide();
                this.ajaxcallwrapper(this.gamedatas.gamestate.possibleactions[0], {gearN: evt.target.id.split('_')[1]});
            }));
        },

        // formats a new game element of some type (car, curve, vector, pitwall) and place it inside 'track' node
        createGameElement(type, args) {
            dojo.place(
                this.format_block('jstpl_'+type, args),
                'track'
            );
        },

        // istantaneously move game element to coordinates (x,y), assumed to be relative to track plane ((0,0) is center of pitwall)
        placeOnTrack: function(id, x, y) {
            var el = $(id);

            el.style.left = x;
            el.style.top = -y;
        },

        // as method above, but applies a slide animation to the movement, of default duration 500ms
        slideOnTrack: function(id, x, y, time=500) {

            var el = $(id);

            dojo.animateProperty({
                node:el,
                duration: (this.instantaneousMode)? 0 : time,
                properties: {
                    left: { start: el.style.left, end: x},
                    top: { start: el.style.top, end: -y},
                }
            }).play();
        },
        
        //#endregion

        //++++++++++++++++//
        // PLAYER ACTIONS //
        //++++++++++++++++//
        //#region actions

        // previewStartCarPos: display preview of player car for the first placement (process is different from function below as it costantly follows the user input)
        // TODO: PREVIEW PREVIEW CAR INSTEAD OF PLAYER CAR, MAKE PREVIEW DISAPPEAR WHEN CURSOR NOT ON PLACEMENT AREA
        previewStartCarPos: function(evt) {

            dojo.stopEvent(evt);
            var h = $('start_positioning_area').clientHeight;

            // THIS METHOD IS SENSIBLE TO 'page-content' DIV MEGAPARENT 'zoom' PROPRIETY
            // COULD AJUST VALUES TO RECIPROCAL ZOOM SCALE VALUE

            var posX = parseInt($('start_positioning_area').style.left);

            var baseY = -parseInt($('start_positioning_area').style.top); // offset of positioning area top-down corner from origin of plane
            var offset = -(evt.offsetY-h); // offset of mouse pointer from top-down corner of positioning area

            if (offset<=this.octSize/2) posY = baseY+this.octSize/2; // if mouse offset is less than 50, centered octagon would go out of bounds (floor of positioning area). thus y-coordinate should be assigned to nearest valid position
            else if (offset>=h-this.octSize/2) posY = baseY+h-this.octSize/2; // same goes for the area ceiling
                else posY = baseY + offset; // else position is combination of mouse offset and area offset

            var color = this.gamedatas.players[this.getActivePlayerId()].color;
            dojo.style("car_"+color,'display','block')
            
            dojo.style('track','transform','scale(1)');
            this.slideToObjectPos("car_"+color, "track", posX, -posY-this.octSize/2, 0).play(); // remember to invert Y
            this.scaleInterface(0);
        },

        // selectStartCarPos: specific method to select car position for first player
        selectStartCarPos: function(evt) {

            dojo.stopEvent(evt);

            var color = this.gamedatas.players[this.getActivePlayerId()].color;
            var id = 'car_'+color;

            var posX = parseInt($(id).style.left);
            var posY = -(parseInt($(id).style.top));
            
            console.log('Selected position: '+posX+', '+posY);

            this.validateOrCancelCarPosition(posX,posY);
        },

        // previewCarPos: display preview of players car behind the hovering octagon highlight
        previewCarPos: function(evt) {
            dojo.stopEvent(evt);

            // KNOW THAT THERE'S NO HANDLING OF OVERLAPPING INPUT
            // PLAYER MIGHT MISTAKENLY CHOOSE WRONG POSITION

            var pos = evt.srcElement.id;
            var posX = parseInt(pos.split('_')[1]);
            var posY = parseInt(pos.split('_')[2]);

            dojo.place(
                this.format_block('jstpl_car', {color: 'preview'}),
                'previews'
            );

            dojo.style('car_preview','transform',$('car_'+this.gamedatas.players[this.getActivePlayerId()].color).style.transform);

            dojo.style('track','transform','scale(1)');
            this.slideToObjectPos('car_preview', "track", posX-this.octSize/2, -posY-this.octSize/2, 0).play();
            this.scaleInterface(0);
        },

        // THERE COULD BE ONLY ONE GENERAL PURPOUSE METHOD FOR SELECTING CAR POSITION. PERAPHS ONE THAT DOES THE FORMATTING AND PLACING AND THE OTHER THAT DOES THE ACTION HANDLER PART
        // selectCarFSPos: method to select car position during flying-start initial game phase. position is obtained from the id of the clicked (selection octagon) element
        selectCarFSPos: function(evt) {
            dojo.stopEvent(evt);

            var pos = evt.srcElement.id;
            var posX = pos.split('_')[1];
            var posY = pos.split('_')[2];

            console.log("Selected position: "+posX+", "+posY);

            this.moveCar(this.getActivePlayerId(),posX,posY);
            this.validateOrCancelCarPosition(posX,posY);
        },

        // previewVectorPos: display vector on the highlighted octagon, starting from the bottom of it.
        previewVectorPos: function(evt) {
            dojo.stopEvent(evt);

            var currGear = this.gamedatas.gamestate.args.currentGear;

            dojo.place(
                this.format_block('jstpl_gearVector', {n:currGear}),
                'previews'
            );

            dojo.style('gear_'+currGear,'transform','translate(-50%,-50%) scale('+this.octSize/522+') rotate('+(this.gamedatas.gamestate.args.direction-2)*-45+'deg)');
            
            // offset to make the vector attach from the bottom
            var offset = (currGear-1)*parseInt($('gear_'+currGear).getBoundingClientRect().height);
            
            // BRUTE FORCE SOLUTION TO SLIDE FUNCTION NOT WORKING PROPERLY (like, it slides to the correct position, but stops halfway). DOESN'T EVEN SOLVES THE PROBLEM ENTIRELY, FIND SOLUTION
            for (var i=0; i<20; i++) this.slideToObjectPos('gear_'+currGear,evt.target,-offset,0,0).play(); 
        },

        // handles user click on a selection octagon when placing a vector during movemente phase
        selectVectorPos: function(evt) {
            dojo.stopEvent(evt);

            // update state description to be specific about current movement step
            this.gamedatas.gamestate.descriptionmyturn = _('${you} now have to decide where to place your car');
            this.updatePageTitle();

            // move from preview to track to avoid removal
            dojo.place(
                $('gear_'+this.gamedatas.gamestate.args.currentGear),
                'track'
            );

            // extract chosen vector position from clicked selection octagon
            var vecPos = evt.target.id.split('_');
            vecPos = { x: parseInt(vecPos[1]), y: parseInt(vecPos[2]) };

            // find all car positions related to that vector position and save them to state args holding positions of intrests
            this.gamedatas.gamestate.args.positions.forEach(pos => {
                if (JSON.stringify(vecPos) === JSON.stringify(pos.coordinates)) {
                    this.gamedatas.gamestate.args.positions = pos;

                    if (pos.tireCost) this.gamedatas.gamestate.args.tireCost += 1; // check if selected vector position costs a tire token
                }
            });

            // do as before with vectors, extract coordinates relative to all available car positions
            var carPositions = [];
            this.gamedatas.gamestate.args.positions.carPositions.forEach(pos => {
                carPositions.push(pos.coordinates)
            });

            // finally, display selection octagons for those positions and connect them to proper handlers
            this.displaySelectionOctagons(carPositions);
            this.connectActionElements('selectCarPos','previewCarPos');
           
        },

        // handles user click on a selection octagon when placing a car during movemente phase
        selectCarPos: function(evt) {
            dojo.stopEvent(evt);

            // update state description to be descriptive of currente phase step
            this.gamedatas.gamestate.descriptionmyturn = _('To complete your movement, ${you} have to decide in which direction is the car driving');
            this.updatePageTitle();

            // extract position from clicked element
            var pos = evt.srcElement.id;
            var posX = parseInt(pos.split('_')[1]);
            var posY = parseInt(pos.split('_')[2]);

            // move element from highlights to track to avoid removal
            dojo.place(
                $('car_preview'),
                'track'
            );

            // trasform care preview style so that it matches player car style (most importantly, direction)
            dojo.style('car_preview','transform',$('car_'+this.gamedatas.players[this.getActivePlayerId()].color).style.transform);

            // move preview car to clicked element
            dojo.style('track','transform','scale(1)');
            this.slideToObjectPos('car_preview', "track", posX-this.octSize/2, -posY-this.octSize/2, 0).play();
            this.scaleInterface(0);
            
            // again, as before, extract selected position oordinates and find in agrs object what other positions does that generates 
            var carPos = evt.target.id.split('_');
            carPos = { x: parseInt(carPos[1]), y: parseInt(carPos[2]) };
            
            this.gamedatas.gamestate.args.positions.carPositions.forEach(pos => {
                if (JSON.stringify(carPos) === JSON.stringify(pos.coordinates)) {
                    this.gamedatas.gamestate.args.positions = pos;
                }
            });

            // with the obtained positions, generate and display the direction arrows and connect them to the proper handlers
            this.displayDirectionArrows(this.gamedatas.gamestate.args.positions.directions, this.gamedatas.gamestate.args.direction);
            this.connectActionElements('confirmCarRotation','previewCarRotation');
        },

        // handles user click on a direction arrow when choosing the car orientation at the end of the movement phase
        confirmCarRotation: function(evt) {
            dojo.stopEvent(evt);

            // check wether clicked arrow is black by searching args object and reading color propriety
            this.gamedatas.gamestate.args.positions.directions.forEach(dir => {
                if (dir.direction == evt.target.id.split('A')[0])
                    if (dir.color == 'black') this.gamedatas.gamestate.args.tireCost += 1
            });

            // extract direction from clicked arrow element to calculate rotation relative to current direction
            var rotation;
            // could be done as a method as it appears more than 1 time
            switch (evt.target.id.split('A')[0]) {
                case 'right': rotation = -1; break;
                case 'forward': rotation = 0; break;
                case 'left': rotation = 1; break;
            }

            var position = this.gamedatas.gamestate.args.positions.coordinates;

            // finally ajax to server all data about the player movement
            this.ajaxcallwrapper('completeMovement', {
                x: position.x,
                y: position.y,
                rotation: rotation,
                tireCost: this.gamedatas.gamestate.args.tireCost
            });

            dojo.destroy('car_preview');
            dojo.empty('pos_highlights');
            this.moveCar(this.getActivePlayerId(), position.x, position.y, rotation);
            dojo.destroy('gear_'+this.gamedatas.gamestate.args.currentGear);      
        },

        // rotate preview car in the direction of the hovered direction arrow dom element
        previewCarRotation: function(evt) {
            dojo.stopEvent(evt);

            var rotation;

            switch (evt.target.id.split('A')[0]) {
                case 'right': rotation = -1;
                    break;
                case 'forward': rotation = 0;
                    break;
                case 'left': rotation = 1;
                    break;
            }


            const playerCarTransform = $('car_'+this.gamedatas.players[this.getActivePlayerId()].color).style.transform;

            $('car_preview').style.transform = playerCarTransform + 'rotate('+rotation*-45+'deg)';
        },

        //#endregion

        //+++++++++++++++++++++++++++++++++//
        // NOTIFICATION SETUP AND HANDLERS //
        //+++++++++++++++++++++++++++++++++//
        //#region notifications

        // [methods that setup all notification channels and define the proper notification handler specified in the setup]

        // --- SUBSCRIPTIONS ---
        // setupNotification: setup all notification channel (use this.notifqueue.setSynchronous('chName',delay) to make it asynchronous)
        setupNotifications: function() {
            console.log( 'notifications subscriptions setup' );

            dojo.subscribe('logger', this, 'notif_logger');

            dojo.subscribe('selectPosition', this, 'notif_selectPosition');
            this.notifqueue.setSynchronous( 'selectPosition', 500 );

            dojo.subscribe('chooseStartingGear', this, 'notif_chooseStartingGear');
            this.notifqueue.setSynchronous( 'chooseStartingGear', 500 );

            dojo.subscribe('completeMovement', this, 'notif_completeMovement');
            this.notifqueue.setSynchronous( 'completeMovement', 500 );
        },  

        // --- HANDLERS ---
        
        notif_logger: function(notif) {
            console.log(notif.args);
        },

        notif_selectPosition: function(notif) {
            this.moveCar(notif.args.player_id, notif.args.posX, notif.args.posY);
        },

        notif_chooseStartingGear: function(notif) {
        },

        // once a player completes a movement, update interface for the other players, by animating vector placement, car movement and rotation
        notif_completeMovement: function(notif) {
            this.moveCar(notif.args.player_id, notif.args.posX, notif.args.posY, notif.args.rotation);
            //if(!this.isCurrentPlayerActive())
                //this.carMovementAnimation(notif.args.player_id, notif.args.posX, notif.args.posY, notif.args.rotation, notif.args.gear, notif.args.direction);
            //else dojo.destroy('gear_'+notif.args.gear); 
        },

        //#endregion
   
    });             
});
