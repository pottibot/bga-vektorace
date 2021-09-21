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
    "ebg/scrollmap"
],

function(dojo, declare) {
    return declare("bgagame.vektorace", ebg.core.gamegui, {
        constructor: function() {
            console.log('vektorace constructor');
              
            // GLOBAL VARIABLES INIT

            // all octagon sizes:
            // useful measures to rescale octagons and calculate distances between them
            // these measure should be set always using setOctagonSize(size), which given a certain octagon size (length of one side of the square box that contains the octagon), it calculates all deriving measures
            this.octSize; // length of the side of the square box containing the octagon
            this.octSide; // length of each side of the octagon
            this.octRad; // radius of the circle that inscribe the octagon. or distance between octagon center and any of its vertecies
            this.octSeg; // segment measuring half of the remaining length of box size, minus the length of the octagon side. or the cathetus of the right triangle formed on the diagonal sides of the octagon.

            this.interfaceScale = 3;

            this.currentGear;

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

            this.currentGear = gamedatas.players[player_id]['curr_gear'];

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
            for (var i in gamedatas.table_elements) {
                var el = gamedatas.table_elements[i];

                switch (el.entity) {
                    case 'pitwall':
                        
                        dojo.place(
                            this.format_block('jstpl_pitwall', {}),
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

                        var color
                        
                        if (el.id == '1234') {
                            color = 'test'
                        } else color = gamedatas.players[el.id].color;

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
       
        ///////////////////////////////////////////////////
        //// Game & client states

        //+++++++++++++++++++++++++++++++++++//
        // UI STATE CHANGES & ACTION BUTTONS //
        //+++++++++++++++++++++++++++++++++++//

        // [methods that apply changes to the interface (and regulates action buttons) depending on game state]
        
        // onEnteringState: method called each time game enters a new game state.
        //                  used to perform UI changes at beginning of a new game state.
        //                  arguments are symbolic state name (needed for internal mega switch) and state arguments extracted by the corresponding php methods (as stated in states.php)
        onEnteringState: function(stateName,args) {
            console.log( 'Entering state: '+stateName );
            
            switch(stateName) {

                case 'playerPositioning':

                    // avoid displaying additional infos for players who are not active
                    if(!this.isCurrentPlayerActive()) return;

                    console.log('args.args below');
                    console.log(args.args);
    
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
                            var alternativeDescription = _('Select a reference car to determine possible "flying-start" positions');

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
                                    this.connectHighlightsTo('selectCarPos','previewCarPos');

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

                            this.displaySelectionOctagons(Object.values(Object.values(args.args))[0]);
                            this.connectHighlightsTo('selectCarPos','previewCarPos');
                        }
                    }

                    break;         
                
                case 'greenLight':

                    if(!this.isCurrentPlayerActive()) return; // always prevent interface to change for those whom are not the active player
                    
                    this.displayGearSelDialog(0,3,4,5); // current gear is 0 (doesn't exists) because it's the first gear selection phase
                    
                    break;

                case 'placeVector':

                    if(!this.isCurrentPlayerActive()) return;

                    this.displaySelectionOctagons(Object.values(args.args.attachPositions)) // display vector attachment position in front of the car

                    // create current gear vector element and hide it
                    dojo.place(
                        this.format_block('jstpl_gearVector', {n:args.args.currentGear}),
                        'track'
                    );
                    dojo.style('gear_'+args.args.currentGear,'transform','translate(-50%,-50%) scale('+this.octSize/522+') rotate('+(args.args.direction-2)*-45+'deg)');
                    dojo.style('gear_'+args.args.currentGear,'display','none');

                    // then connect highlights to activate hover preview and click input event
                    this.connectHighlightsTo('selectVectorPos','previewVectorPos');
                
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
                }
            }
        },

        ///////////////////////////////////////////////////
        //// Utility methods

        //+++++++++++++++++//
        // UTILITY METHODS //
        //+++++++++++++++++//

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
                this.format_block('jstpl_pitwall', {deg: -90, scale: 50/524}), // scale is element width / octagon standard size. it scales to a widdth of approx 100px
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
        moveCar: function(id, posX, posY) {

            var color = this.gamedatas.players[id].color;

            if (dojo.style("car_"+color,'display') == 'none') { // if car was hidden, thus never placed, make it visible and slide it to player board to make animation prettier
                dojo.style("car_"+color,'display','block');
                this.slideToObject('car_'+color,'overall_player_board_'+id,0).play();
            }

            // FINALLY COUGHT THE FUCKING BUG
            // slideToObjectPos is influenced by interface zoom (global scale of '#track' element)
            // so resetting scale and putting it back to normal solves the problem
            // DUNNO IF THERE'S A MORE ELEGANT WAY TO DO THIS
            dojo.style('track','transform','scale(1)');
            this.slideToObjectPos("car_"+color,"track",posX-this.octSize/2, -posY-this.octSize/2).play(); 
            this.scaleInterface(0);
        },

        // displaySelectionOctagons: displays a list of selection octagons (white and clickable) and connects them to selectCarPos.
        //                           argument is array of arrays, where these are [x,y] coordinates of the octagon.
        // PROBABLY BEST TO CHANGE DATA FORMAT IN PHP SO THAT EACH POSITION IS AN OBJECT {x: , y: }
        displaySelectionOctagons: function(positions) {
            positions.forEach(pos => {
                dojo.place(
                    this.format_block('jstpl_selOctagon',{ x:pos[0], y:pos[1]}),
                    'pos_highlights'
                );
                this.slideToObjectPos('selOct_'+pos[0]+'_'+pos[1],'touchable_track',pos[0],-pos[1],0).play();
                dojo.style('selOct_'+pos[0]+'_'+pos[1],'transform','translate(-50%,-50%) scale('+this.octSize/2000+')');
            });

            
        },

        // connectHighlightsTo: function to connect higlight elements  (#pos_highlights > *) such as clickable white octagons, to specific handlers. the first is for the onClick event, the second is for the onMouseEnter.
        connectHighlightsTo: function(onclickHandler, onmouseenterHandler) {
            dojo.query('#pos_highlights > *').connect('onclick',this,onclickHandler);
            dojo.query('#pos_highlights > *').connect('onmouseenter',this,onmouseenterHandler);
        },

        // validateOrCancelCarPosition: function called when user chooses new car position (car is already moved there). used to clean interface and display confirmation or cancel buttons
        validateOrCancelCarPosition: function(x,y) {
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
            // MAY BE WISE TO CHECK ACTION AT BEGINNING OF STATE OR EVEN BEFORE THAT (IN THE CALLING FUNCTION) 
            this.addActionButton( 'validatePos_button', _('Validate'), () => {
                if (this.checkAction('selectPosition')) {
                    this.ajaxcall('/vektorace/vektorace/selectPosition.html', {
                        x: Math.round(x),
                        y: Math.round(y),
                        lock: true
                    }, this, () => console.log('call success'));
                }
            }); 
        },

        // curr is current selected gear, gears are the ones to display as available available, given the game situation (actually useless, as i can always pay to scale more than 1)
        // TODO: make method generic to serve both green-light phase gear declaration, normal future gear declaration and all other exceptions (de/acceleration using tokens, emergency brake, ramming)
        // if no exceptions are found, method is going to display all gear vectors, highlighting the current one (this.currentGear), and marking with the right number of either tire or nitro token the gears that excede the shift by 1 starting from the current vector.
        // exception can be:
        // - GreenLight: its a special phase of the game where the first player chooses the starting gear for all the players. he might choose only the gears between 3 and 5 with no exception
        // - EmergencyBreak: it's a special move permitted only when the player cannot place its declared gear or car anywhere because it would intersect with other table elements. the player might choose to decellerate during vector placement, spending one tire token for every shifted gear. after this move, the player cannot shift gear up for the next turn.
        // - Crash: when a player cannot make valid moves, even with an emergency break, he will skip this movement turn, turn his car by 45deg, if he chooses so and start next turn with gear 1.
        // - Ramming: when a player suffers ramming ('bussata', in italian original translation) by another player, he wont be able to shift gear down for the next turn.
        displayGearSelDialog: function(...exceptions) {
            // VERY TEMP JUST TO MAKE METHOD WORK
            var curr = this.currentGear;
            var gears = [curr-1, curr, curr+1];

            var content = dojo.create('div', {
                id: 'dialogContent',
                style: {
                    width: '500px',
                    height: '400px',
                    position: 'relative'
                }
            });

            var gearsPrev = dojo.create('div', {
                id: 'gearsPrev',
                style: {
                    position: 'absolute',
                    left: '0px',
                    top: '0px',
                    transform: 'scale(0.12)',
                    overflowX: 'visible',
                    whiteSpace: 'nowrap'
                }
            });

            var h = 522; // height of the gear vector 1 (base octagon), others are high n times that, where n is the gear number

            // format all blocks
            for (var i=1; i<=5; i++) {
                dojo.place(this.format_block('jstpl_gearVectorPreview', {n:i, bottom:(5-i)*h/2}),gearsPrev);
            }

            dojo.place( gearsPrev , content);
            
            // Show the dialog
            this.gearSelDW.setContent(content.outerHTML); // Must be set before calling show() so that the size of the content is defined before positioning the dialog
            this.gearSelDW.show();
            this.gearSelDW.hideCloseIcon(); // INSTEAD OF PREVENTING CLOSING I COULD ADD A BUTTON TO SHOW SELECTION DIALOG

            this.placeOnObject( 'gearsPrev', 'dialogContent' );

            for (var i=1; i<=5; i++) {
                if (gears.includes(i)) {
                    var id = 'gear_'+i

                    // ACTUALLY QUITE DANGEROUS TO CONNECT SHIT LIKE THIS vvv AS VARIABLES VALUES ARE TAKEN AT EXCECUTION TIME
                    
                    this.connect($(id),'onclick', (evt) => {
                        dojo.stopEvent(evt);
                        this.gearSelDW.destroy();

                        if (curr != 0) {
                            console.log('Player is declaring gear for next turn');
                            if (this.checkAction('declareGear')) {
                                this.ajaxcall('/vektorace/vektorace/declareGear.html', {
                                    n: evt.target.id.split('_')[1],
                                    lock: true
                                }, this, () => {});
                            }
                        } else {
                            console.log('Player is choosing starting Gear');
                            if (this.checkAction('chooseStartingGear')) {
                                this.ajaxcall('/vektorace/vektorace/chooseStartingGear.html', {
                                    n: evt.target.id.split('_')[1],
                                    lock: true
                                }, this, () => {});
                            }
                        }

                        this.disconnect();
                    });

                    this.connect($(id),'onmouseenter', (evt) => {
                        dojo.stopEvent(evt);
                        dojo.addClass(evt.target.id,'hlGearVector');
                        

                        this.disconnect();
                    });
                    this.connect($(id),'onmouseout', (evt) => {
                        dojo.stopEvent(evt);
                        dojo.removeClass(evt.target.id,'hlGearVector');

                        this.disconnect();
                    });

                } else dojo.style('gear_'+i,'opacity','40%');
            }

            if (curr != 0) {
                dojo.place(
                    this.format_block('jstpl_gearDotHighlight', {}),
                    'dialogContent'
                );

                var w = $('gear_'+curr).getBoundingClientRect().width;
                var offX = $('gear_'+curr).getBoundingClientRect().left;
                var offY = $('gear_'+curr).getBoundingClientRect().top;

                this.slideToObjectPos('gearDotHighlight','gear_'+curr,w/2,-w/2,0).play();
                dojo.style('gearDotHighlight','transform','translate(-50%,-50%');

            }
        },

        ///////////////////////////////////////////////////
        //// Player's action

        //++++++++++++++++//
        // PLAYER ACTIONS //
        //++++++++++++++++//

        // [methods that handle player action (as a result of the active player input)]
        // [methods always check if action is permitted (in the sense of current game state, not game rules, that's responsability of game.php) and make AJAX call to server]
        
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

        // previewStartCarPos: display preview of player car for the first placement (process is different from function below as it costantly follows the user input)
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

        // previewCarPos: display preview of players car behind the hovering octagon highlight
        previewCarPos: function(evt) {
            dojo.stopEvent(evt);

            // KNOW THAT THERE'S NO HANDLING OF OVERLAPPING INPUT
            // PLAYER MIGHT MISTAKENLY CHOOSE WRONG POSITION

            var pos = evt.srcElement.id;
            var posX = parseInt(pos.split('_')[1]);
            var posY = parseInt(pos.split('_')[2]);

            var color = this.gamedatas.players[this.getActivePlayerId()].color;
            dojo.style("car_"+color,'display','block')

            dojo.style('track','transform','scale(1)');
            this.slideToObjectPos("car_"+color, "track", posX-this.octSize/2, -posY-this.octSize/2, 0).play();
            this.scaleInterface(0);
        },

        // selectCarPos: general purpose method to select new car position for player. position is obtained from the id of the clicked (selection octagon) element
        selectCarPos: function(evt) {
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

            dojo.style('gear_'+this.currentGear,'display','block');

            var offset = (this.currentGear-1)*parseInt($('gear_'+this.currentGear).getBoundingClientRect().height); // offset to make the vector attach from the bottom

            for (var i=0; i<20; i++) this.slideToObjectPos('gear_'+this.currentGear,evt.target,-offset,0,0).play(); // BRUTE FORCE SOLUTION TO SLIDE FUNCTION NOT WORKING PROPERLY (like, it slides to the correct position, but stops halfway)
        },

        // TODO
        selectVectorPos: function(evt) {
            dojo.stopEvent(evt);

            if (this.checkAction('placeVector')) {
                this.ajaxcall('/vektorace/vektorace/placeVector.html', {
                    x: evt.target.id.split('_')[1],
                    y: evt.target.id.split('_')[2],
                    gear: this.currentGear,
                    lock: true
                }, this, () => {});
            }
        },
        
        ///////////////////////////////////////////////////
        //// Reaction to cometD notifications

        //+++++++++++++++++++++++++++++++++//
        // NOTIFICATION SETUP AND HANDLERS //
        //+++++++++++++++++++++++++++++++++//

        // [methods that setup all notification channels and define the proper notification handler specified in the setup]

        // --- SUBSCRIPTIONS ---
       // setupNotification: setup all notification channel (use this.notifqueue.setSynchronous('chName',delay) to make it asynchronous)
        setupNotifications: function() {
            console.log( 'notifications subscriptions setup' );

            dojo.subscribe('logger',this,'notif_logger');

            dojo.subscribe('selectPosition',this,'notif_selectPosition');
            this.notifqueue.setSynchronous( 'selectPosition', 500 );

            dojo.subscribe('chooseStartingGear',this,'notif_chooseStartingGear');
        },  

        // --- HANDLERS ---
        
        notif_logger: function(notif) {
            console.log(notif.args);
        },

        notif_selectPosition: function(notif) {
            this.moveCar(notif.args.player_id, notif.args.posX, notif.args.posY);
        },

        notif_chooseStartingGear: function(notif) {
            this.currentGear = notif.args.n;
        }
   });             
});
