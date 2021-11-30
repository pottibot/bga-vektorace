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

            this.counters = {};
        },
        
        // setup: method called each time interface loads. should set up game sistuation according to db.
        //        argument 'gamedatas' cointains data extracted with getAllDatas() game.php method. it is also kept as a global variable as this.gamedatas (function to update it should exist but it should also be unnecessary)
        setup: function(gamedatas) {

            console.log("Starting game setup");

            // -- EXTRACT OCTAGON REFERENCE MEASURES --
            // actually permanent since all rescaling is done with css transform
            this.octSize = parseInt(gamedatas.octagon_ref['size']);
            this.octSide = parseInt(gamedatas.octagon_ref['side']);
            this.octSeg = parseInt(gamedatas.octagon_ref['corner_segment']);
            this.octRad = parseInt(gamedatas.octagon_ref['radius']);
            
            // -- SETUP PLAYER BOARDS --
            this.counters.playerBoard = {};

            for (var player_id in gamedatas.players) {
                var player = gamedatas.players[player_id];
                this.counters.playerBoard[player_id] = {};
                
                // create all icon elements
                var player_board_div = $('player_board_'+player_id);
                dojo.place( this.format_block('jstpl_player_board', {
                    id: player_id,
                    gear: this.format_block('jstpl_current_gear', { id: player_id, n: player['currGear']}),
                    lap: this.format_block('jstpl_lap_counter', { id: player_id,}),
                    standings: this.format_block('jstpl_standings_position', { id: player_id}),
                    tire: this.format_block('jstpl_tokens_counter', { id: player_id, type: 'tire'}),
                    nitro: this.format_block('jstpl_tokens_counter', { id: player_id, type: 'nitro'})
                } ), player_board_div );

                // set counter for each counting icon
                document.querySelectorAll(`#itemsBoard_${player_id} .pbCounter`).forEach( el => {
                    var counter = new ebg.counter();
                    counter.create(el);

                    var propertyName = el.id.substr(0,el.id.indexOf('_'));
                    counter.setValue(player[propertyName]);

                    this.counters.playerBoard[player_id][propertyName] = counter;
                });
            }

            // to properly render icon on screen, iconize it 
            document.querySelectorAll('.pbIcon').forEach( (el) => { this.iconize(el, 30) });

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

            // -- PLACE TABLE ELEMENTS ACCORDING TO DB --
            // POSIBILITY TO REPLACE ALL ELEMENTS ROTATIONS WITH CSS CLASSES INDICATING THE ROTATION (USEFUL AS IT KEEPS ELEMENT ROTATION DATA)
            for (var i in gamedatas.game_element) {
                var el = gamedatas.game_element[i];
                
                switch (el.entity) {

                    case 'pitwall':
                        this.createGameElement('pitwall');
                        this.placeOnTrack('pitwall', el.pos_x, el.pos_y, el.orientation);
                        $('pitwall').style.transform += 'scale(0.75)';
                        break;

                    case 'curve':
                        this.createGameElement('curve', {n: el.id});
                        this.placeOnTrack('curve_'+el.id, el.pos_x, el.pos_y, el.orientation);

                        break;

                    case 'car':
                        var col = gamedatas.players[el.id].color

                        this.createGameElement('car', {color: col});

                        if (el.pos_x && el.pos_y) this.placeOnTrack('car_'+col, el.pos_x, el.pos_y, el.orientation);
                        else {
                            this.placeOnTrack('car_'+col, 0, 0, el.orientation);
                            $('car_'+col).style.display = 'none';
                        }

                        break;

                    case 'gearVector':
                        this.createGameElement('gearVector', {n: el.id});
                        this.placeOnTrack('gear_'+el.id, el.pos_x, el.pos_y, el.orientation);

                        break;

                    case 'boostVector':
                        this.createGameElement('boostVector', {n: el.id});
                        this.placeOnTrack('boost_'+el.id, el.pos_x, el.pos_y, el.orientation);

                        break;
                    
                    default:
                        console.log('Unidentified Non-Flying Object');
                        break;
                }
            }

            // -- CONNECT USER INPUT --
            dojo.query('#map_container').connect('mousewheel',this,'wheelZoom'); // zoom wheel
            dojo.query('#map_container').connect('click',this,'trackCoordsFromMapEvt');
 
            // -- SETUP ALL NOTIFICATION --
            this.setupNotifications();

            // -- SETUP PREFERENCES HANDLERS --
            document.querySelectorAll('#pref_illegalPos input').forEach((el) => {
                el.addEventListener('change', (evt) => {
                    document.documentElement.style.setProperty('--display-illegal', evt.target.value);
                })
            })

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
            console.log('Entering state: '+stateName);
            console.log('State args: ',args.args);
            
            switch(stateName) {

                case 'firstPlayerPositioning':
                    if(!this.isCurrentPlayerActive()) return;

                    // place positioning area as continuation of pitlane line
                    dojo.place( this.format_block('jstpl_posArea'), 'pos_highlights' );
                    this.placeOnTrack('start_positioning_area',args.args.anchorPos.x,args.args.anchorPos.y,0);
                    
                    $('start_positioning_area').style.transformOrigin = 'bottom left'
                    $('start_positioning_area').style.transform = `translate(0,-100%) rotate(${args.args.rotation*45}deg)`;

                    // connect it to input handlers
                    dojo.query('#start_positioning_area').connect('onclick',this,'selectStartCarPos');
                    dojo.query('#start_positioning_area').connect('mousemove',this,'previewStartCarPos');
                    dojo.query('#start_positioning_area').connect('onmouseleave', this, (evt) => {
                        dojo.stopEvent(evt);
                        dojo.empty('previews');
                    });

                    break;

                case 'flyingStartPositioning':

                    if(!this.isCurrentPlayerActive()) return;

                    // var askForReference = args.descriptionmyturn; //  original descritipion asks to click on reference car
                    var askForPos = _('${you} must choose a starting position');

                    // iterate on all possible reference cars, place selection octagon on it and connect it to function that displays fs positions
                    Object.keys(args.args.positions).forEach(id => {

                        var refcar = args.args.positions[id]
                        var pos = refcar.coordinates;
                            
                        dojo.place(
                            this.format_block('jstpl_selOctagon',{
                                i: id,
                                x: pos.x,
                                y: pos.y
                            }),
                            'car_highlights'
                        );

                        var selOctId = `selOct_${pos.x}_${pos.y}`;

                        this.placeOnTrack(selOctId, pos.x, pos.y);
                        // usual transformation to adapt new element to interface
                        dojo.style(selOctId,'transform','translate(-50%,-50%) scale('+this.octSize/$(selOctId).offsetWidth+')');

                        if (refcar.hasValid) {

                            this.connect($(selOctId), 'onclick', (evt) => {

                                $('car_highlights').childNodes.forEach( el => el.style.display = '');
                                $('pos_highlights').innerHTML = '';

                                var positions = [];
                                refcar.positions.forEach(element => {
                                    positions.push(element.coordinates);
                                });

                                this.gamedatas.gamestate.args.refCar = evt.target.dataset.posIndex;

                                this.displaySelectionOctagons(positions);
                                this.connectPosHighlights('selectCarFSPos','previewCarPos');

                                document.querySelectorAll('#pos_highlights > .selectionOctagon').forEach( el => {
                                    if (!refcar.positions[el.dataset.posIndex].valid) {
                                        el.className = el.className.replace('standardPos','illegalPos');
                                        // el.style.pointerEvents = 'none';
                                    }
                                })

                                /* refcar.positions.forEach(element => {
                                    this.displayPoints(element.vertices);
                                }); */



                                args.descriptionmyturn = askForPos;
                                this.updatePageTitle();

                                $(selOctId).style.display = 'none';
                            });

                        } else $(selOctId).remove();
                    });

                    /* refCarSelOcts = document.querySelectorAll('#car_highlights > .selectionOctagon');
                    if (refCarSelOcts.length == 1) refCarSelOcts[0].click(); // if ref car is only one simulate click on it */
                            
                    break;         
                
                case 'tokenAmountChoice':
                
                    if(!this.isCurrentPlayerActive()) return;
                    
                    var baseTire = parseInt(args.args.tire);
                    var baseNitro = parseInt(args.args.nitro);

                    // func that creates and displays window to select token amount
                    this.displayTokenSelection(baseTire,baseNitro, args.args.amount);
                    this.addActionButton('confirmTokenAmount', _('Confirm'), () => {

                        args.args.tire = this.gamedatas.gamestate.args.tire;
                        args.args.nitro = this.gamedatas.gamestate.args.nitro;
                        if (args.args.tire + args.args.nitro == Math.min(baseTire + baseNitro + args.args.amount, 16)) { // check that player actually set some value for each type (server gonna check anyway). COULD SET STANDARD TO 4 FOR EACH
                            console.log(args.args);
                            this.ajaxcallwrapper('chooseTokensAmount',{ tire: args.args.tire, nitro: args.args.nitro});
                        } else this.showMessage('You must add some tokens to your pile');
                        
                    }, null, false, 'blue');

                    if (baseTire == 0 && baseNitro == 0) {
                        document.querySelectorAll('.tokenIncrementer > input').forEach( el => el.value = 4);
                        this.gamedatas.gamestate.args.tire = 4;
                        this.gamedatas.gamestate.args.nitro = 4;
                    }
                    break;

                case 'greenLight':

                    if(!this.isCurrentPlayerActive()) return; // always prevent interface to change for those whom are not the active player

                    // add putton that displays vector selection in 'green light' mode
                    this.addActionButton('showGearSelDialogButton', _('show selection'), () => {
                        this.displayGearSelDialog(args.args.gears);
                    }, null, false, 'blue');
                    
                    break;
                
                case 'gearVectorPlacement':

                    if(!this.isCurrentPlayerActive()) return;

                    // push all positions coordinates to array and pass it to method to display selection octagons for each pos
                    var vecAllPos = [];
                    args.args.positions.forEach(pos => {
                        vecAllPos.push(pos.anchorCoordinates);
                    })

                    this.displaySelectionOctagons(vecAllPos); // display vector attachment position in front of the car
                    this.connectPosHighlights('selectGearVecPos','previewGearVecPos'); // then connect highlights to activate hover preview and click input event

                    // add special properties to selection octagons
                    document.querySelectorAll('#pos_highlights > .selectionOctagon').forEach((selOct) => {
                        var i = selOct.dataset.posIndex;
                        var pos = args.args.positions[i];

                        if (!pos.legal) {
                            selOct.className = selOct.className.replace('standardPos','illegalPos');
                            //selOct.style.pointerEvents = 'none';
                        } else {
                            if (pos.denied) {
                                selOct.className = selOct.className.replace('standardPos','deniedPos');
                                //selOct.style.pointerEvents = 'none';
                            } else if (pos.tireCost) {
                                selOct.className = selOct.className.replace('standardPos','tirePos');
                            };
                        }
                    });

                    if (!args.args.hasValid) {
                        this.addActionButton(
                            'emergencyBreak_button', _('Emergency Break'), () => { this.ajaxcallwrapper('breakCar') },
                            null, false, 'red'
                        ); 
                    }

                    break;

                case 'emergencyBrake':

                    if(!this.isCurrentPlayerActive()) return;
                    this.displayDirectionArrows(args.args.directionArrows);
                    this.connectPosHighlights('confirmCarRotation','previewCarRotation'); // use same action handler or make new? CONSIDER SEPARATING CAR PLACEMENT AND ROTATION AGAIN (NOPE, I'M LAZY)

                    dojo.query('#pos_highlights > *').connect('onclick', this, (evt)  => {
                        dojo.stopEvent(evt);
                        this.ajaxcallwrapper('rotateAfterBrake',{rotIdx:evt.target.dataset.posIndex}, null, '.selectionOctagon');
                    });
                    dojo.query('#pos_highlights > *').connect('onmouseenter', this, (evt) => {
                        dojo.stopEvent(evt);
                        $('car_'+this.gamedatas.players[this.getActivePlayerId()].color).style.transform += 'rotate('+evt.target.dataset.posIndex*-45+'deg)';
                    });
                    dojo.query('#pos_highlights > *').connect('onmouseleave', this, (evt) => {
                        dojo.stopEvent(evt);
                        $('car_'+this.gamedatas.players[this.getActivePlayerId()].color).style.transform += 'rotate('+evt.target.dataset.posIndex*45+'deg)';
                    });

                    break;

                case 'boostPrompt':

                    if(!this.isCurrentPlayerActive()) return;

                    // use button
                    this.addActionButton(
                        'useBoost_button',
                        _('Use Boost')+' -1 '+this.format_block('jstpl_token',{type:'nitro'}),
                        () => {
                            // prevent call if player doesn't have tokens. server gonna check anyway
                            if (this.counters.playerBoard[this.getActivePlayerId()].nitroTokens.getValue() < 1) {
                                this.showMessage("You don't have enough Nitro Tokens to use a Boost","error");
                                return;
                            }
                            this.ajaxcallwrapper('useBoost', {use: true})
                        },
                        null, false, 'red'
                    ); 
        
                    // style button in a cool way
                    $('useBoost_button').style.cssText = `color: #eb6b0c;
                                                          background: #fed20c;
                                                          borderColor: #f7aa16`;
        
                    // iconize nitro token element to properly display it
                    this.iconize(document.querySelector('#useBoost_button > .token'),20);
                    
                    // skip button
                    this.addActionButton(
                        'skipBoost_button',
                        _("Skip"),
                        () => {this.ajaxcallwrapper('useBoost', {use: false})},
                        null, false, 'gray');

                    break;
                
                case 'boostVectorPlacement':

                    if(!this.isCurrentPlayerActive()) return;

                    // same as for gearVectorPlacement
                    var boostAllPos = [];
                    args.args.positions.forEach(pos => {
                        boostAllPos.push(pos.vecTopCoordinates);
                    })

                    this.displaySelectionOctagons(boostAllPos);
                    this.connectPosHighlights('selectBoostVecPos','previewBoostVecPos');

                    document.querySelectorAll('#pos_highlights > .selectionOctagon').forEach((selOct) => {
                        var i = selOct.dataset.posIndex;
                        var pos = args.args.positions[i];

                        if (!pos.legal) {
                            selOct.className = selOct.className.replace('standardPos','illegalPos');
                            //selOct.style.pointerEvents = 'none';
                        }
                    });

                    break;

                case 'carPlacement':

                    if(!this.isCurrentPlayerActive()) return;

                    // same as vector placement phases, just different given data structure
                    var carAllPos = [];
                    args.args.positions.forEach(pos => {
                        carAllPos.push(pos.coordinates);
                    })

                    this.displaySelectionOctagons(carAllPos);
                    this.connectPosHighlights('selectCarPos','previewCarPos');

                    document.querySelectorAll('#pos_highlights > .selectionOctagon').forEach((selOct) => {
                        var i = selOct.dataset.posIndex;
                        var pos = args.args.positions[i];

                        if (!pos.legal) {
                            selOct.className = selOct.className.replace('standardPos','illegalPos');
                            //selOct.style.pointerEvents = 'none';
                        } else {
                            if (pos.denied) {
                                selOct.className = selOct.className.replace('standardPos','deniedPos');
                                //selOct.style.pointerEvents = 'none';
                            } else if (pos.tireCost) {
                                selOct.className = selOct.className.replace('standardPos','tirePos');
                            };
                        }
                    });

                    if (!args.args.hasValid) {
                        this.addActionButton(
                            'emergencyBreak_button', _('Emergency Break'), () => { this.ajaxcallwrapper('breakCar') },
                            null, false, 'red'
                        ); 
                    }

                    break;              
                
                case 'attackManeuvers':
                    
                    if(!this.isCurrentPlayerActive()) return;

                    // save original state title
                    var title = $('pagemaintitletext').innerHTML;

                    // iter through each player that can suffer an attack maneuver from active player
                    for (const playerId in args.args.maneuvers) {
                        // format new title describing action maneuver against some player
                        this.gamedatas.gamestate.args.otherplayer = this.gamedatas.players[playerId].name;
                        this.gamedatas.gamestate.args.otherplayer_id = playerId;

                        this.gamedatas.gamestate.descriptionmyturn = '<br>On ${otherplayer}: '; // ugly, i know
                        this.updatePageTitle();

                        // create containers to put the newly formatted title in (and restore original at the end)
                        var newText = document.createElement('span');
                        var newButtons = document.createElement('div');
                        newButtons.style.display  = 'inline';

                        newText.className = newButtons.className = 'extraTitleLine'

                        var positions = []; // array that will contain attack condition meters position for displaySelOct()

                        // iter through each available maneuver
                        for (const movName in args.args.maneuvers[playerId]) {

                            // format action button to execute this specific maneuver
                            mov = args.args.maneuvers[playerId][movName]; // movName is short name, mov.name is full, translated name
                            this.addActionButton('attMov_'+playerId+'_'+movName, mov.name, () => this.ajaxcallwrapper('engageManeuver',{maneuver: movName, enemy: playerId}));
                            dojo.place('attMov_'+playerId+'_'+movName, newButtons);

                            // extract all useful positions coordinates to display meters
                            for (const property in mov) {
                                if (property == 'attPos') positions.push(mov.attPos);
                                else if (property == 'vecPos') { // specific case: display a vector, do it here  (otherwise use generic function to highlight positions MIGHT CHANGE IN THE FUTURE WITH PROPER 1OCT METER)
                                    dojo.place(this.format_block('jstpl_draftingMeter',{enemy: playerId}),'track');
                                    
                                    var playerCar = $('car_'+this.gamedatas.players[playerId].color);
                                    $('dfMeter_'+playerId).style.transform = playerCar.style.transform + 'rotate(-90deg)';

                                    this.placeOnTrack('dfMeter_'+playerId,mov[property].x,mov[property].y);
                                }
                            }
                        }

                        // save title text content in new container
                        newText.innerHTML = $('pagemaintitletext').innerHTML;
                        // place container on document
                        $('gotonexttable_wrap').before(newText);
                        $('gotonexttable_wrap').before(newButtons);

                        // diplay extracted positions
                        this.displaySelectionOctagons(positions);
                        dojo.query('.selectionOctagon').style('pointer-events','none'); // block mouse interaction (meters are just visual indicator, not links to start actions)
                    }

                    // restore original title
                    $('pagemaintitletext').innerHTML = title;
                    // add skip phase button
                    this.addActionButton('attMov_skip', _('Skip'), () => this.ajaxcallwrapper('skipAttack'), null, false, 'gray');

                    break;

                case 'slingshotMovement':

                    if(!this.isCurrentPlayerActive()) return;

                    var positions = [];
                    args.args.slingshotPos.forEach(pos => {
                        positions.push(pos.pos);
                    });

                    this.displaySelectionOctagons(positions);
                    this.connectPosHighlights(evt => {
                        dojo.stopEvent(evt);
                        this.ajaxcallwrapper('chooseSlingshotPosition',{pos: parseInt(evt.target.dataset.posIndex)});
                    }, 'previewCarPos');

                    console.log(document.querySelectorAll('#pos_highlights > .selectionOctagon'));

                    document.querySelectorAll('#pos_highlights > .selectionOctagon').forEach((selOct) => {
                        var i = selOct.dataset.posIndex;
                        var pos = args.args.slingshotPos[i];

                        if (!pos.valid) {
                            selOct.className = selOct.className.replace('standardPos','illegalPos');
                            //selOct.style.pointerEvents = 'none';
                        }
                    });

                    break;

                case 'futureGearDeclaration':

                    if(!this.isCurrentPlayerActive()) return;

                    // display button to open gear selection dialog window in standard mode.
                    this.addActionButton('showGearSelDialogButton', _('show selection'), () => {
                        this.displayGearSelDialog(args.args.gears);
                    }, null, false, 'blue');
                    
                    break;

                case 'dummmy':
                    break;
            }
        },

        // onLeavingState: equivalent of onEnteringState(...) but needed to perform UI changes before exiting a game state
        onLeavingState: function(stateName) {
            console.log('Leaving state: '+stateName);

            // overkill, could only erease some for specific states
            $('pos_highlights').innerHTML = '';
            $('car_highlights').innerHTML = '';
            $('previews').innerHTML = '';
            $('dirArrows').innerHTML = '';

            switch(stateName) {

                case 'nextPlayer': 
                    document.querySelectorAll('.turnPosIndicator').forEach( el => el.remove());
                    break;

                case 'carPlacement': 
                    if(!this.isCurrentPlayerActive()) return;
                    if ($('car_preview')) $('car_preview').remove();
                    break;
           
                case 'attackManeuvers':
                    document.querySelectorAll('.extraTitleLine').forEach(el => el.remove());
                    document.querySelectorAll('.draftingMeter').forEach(el => el.remove());
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
            }
        },

        //#endregion

        //+++++++++++++++++//
        // UTILITY METHODS //
        //+++++++++++++++++//
        //#region utility

        // debug func that displays small green circle to identify points on screen
        displayPoints: function(points) {

            points.forEach((p,i) => {
                if (!$(`${p.x}_${p.y}`)) {

                    dojo.place(
                        `<div id='${p.x}_${p.y}' class='point'>${i}</div>`,
                        'track'
                    );

                    this.placeOnTrack(`${p.x}_${p.y}`,p.x,p.y);
                }
            });
        },

        // finds player board coordinates using temp div and bga framework function
        getPlayerBoardCoordinates: function(playerID) {

            dojo.place(
                "<div id='findPlayerBoard' style='position: absolute' ></div>",
                'track');

            this.placeOnTrack('findPlayerBoard',0,0)

            this.placeOnObject('findPlayerBoard', 'overall_player_board_'+playerID);

            var ret = {
                x: parseInt($('findPlayerBoard').style.left) / Math.pow(0.8,this.interfaceScale),
                y: -parseInt($('findPlayerBoard').style.top) / Math.pow(0.8,this.interfaceScale)
            }

            dojo.destroy($('findPlayerBoard'));

            return ret;
        },

        // useful method copied from wiki + some modification
        ajaxcallwrapper: function(action, args, handler, lockElementsSelector = null) { // lockElementsSelector allows to block pointer events of the selected elements while ajaxcall is sent (so that previews won't show)
            if (!args) args = []; // this allows to skip args parameter for action which do not require them

            if (lockElementsSelector) {
                document.querySelectorAll(lockElementsSelector).forEach( el => el.style.pointerEvents = 'none');

                handler = (is_error) => {
                    if (is_error) document.querySelectorAll(lockElementsSelector).forEach( el => el.style.pointerEvents = '');
                }
            }
                
            args.lock = true; // this allows to avoid rapid action clicking which can cause race condition on server

            if (this.checkAction(action)) { // this does all the proper check that player is active and action is declared
                
                this.ajaxcall("/" + this.game_name + "/" + this.game_name + "/" + action + ".html", args, // this is mandatory fluff 
                    this, (result) => { },  // success result handler is empty - it is never needed
                    handler); // this is real result handler - it called both on success and error, it has optional param  "is_error" - you rarely need it
                }
        },

        trackCoordsFromMapEvt: function(evt) {
            var offW = evt.target.offsetWidth;
            var offH = evt.target.offsetHeight;

            var offX = evt.offsetX - offW/2;
            var offY = -(evt.offsetY - offH/2);

            var trackL = -(parseInt($('map_scrollable').style.left) - offW/2);
            var trackT = parseInt($('map_scrollable').style.top) - offH/2;

            var scrollX = Math.round((offX + trackL));
            var scrollY = Math.round((offY + trackT));
            
            var mapX = Math.round(scrollX / Math.pow(0.8,this.interfaceScale)); // honestly dunno why dividing for interface scale instad of multiplying but it works that way
            var mapY = Math.round(scrollY / Math.pow(0.8,this.interfaceScale));

            return {x: mapX, y: mapY}
        },

        // [general purpos methods to scale, move, place, change interface elements]

        // wheelZoom: format input wheel delta and calls method to scale interface accordingly
        wheelZoom: function(evt) {
            dojo.stopEvent(evt);

            var coordsBeforeScale = this.trackCoordsFromMapEvt(evt);

            scaleDirection = evt.wheelDelta / 120;
            var scalestep = this.interfaceScale - scaleDirection;

            if (scalestep >= 0 && scalestep < 7) {
                this.interfaceScale = scalestep;
                this.scaleInterface();
            }

            var coordsAfterScale = this.trackCoordsFromMapEvt(evt);

            var scrollDelta = {
                x: (coordsBeforeScale.x - coordsAfterScale.x)*Math.pow(0.8,this.interfaceScale),
                y: (coordsBeforeScale.y - coordsAfterScale.y)*Math.pow(0.8,this.interfaceScale)
            }

            /* console.log('coords before scale',coordsBeforeScale);
            console.log('coords after scale',coordsAfterScale);
            console.log('coords scale delta',scrollDelta); */
            
            this.scrollmap.scroll(-scrollDelta.x, scrollDelta.y,0,0);
        },

        // scaleInterface: applies scale on the whole game interface with factor calculated as 0.8^interfaceScale step.
        //                 scaling obtained with css transform of parent of all table elments, so to keep distance between them proportional
        scaleInterface: function() {
            dojo.style('track','transform','scale('+Math.pow(0.8,this.interfaceScale)+')');
            dojo.style('touchable_track','transform','scale('+Math.pow(0.8,this.interfaceScale)+')');
        },

        // scale element to size and cuts margin to fix scaling white space, then wraps element in .icon element
        // useful to do this in js as it can dinamically transform any element into an icon
        // note that this func won't work if element is not yet rendered on the page (ie. notification in game log)
        iconize: function(el, size) {

            // scale to size 100px, then scale to wanted size
            var scale = this.octSize / el.offsetWidth * size / this.octSize;

            el.style.transform = `scale(${scale})`;

            // calc margin to remove white space around scaled element
            // ! assuming element is square
            el.style.margin = `-${el.offsetWidth * (1 - scale) / 2}px`;

            // wrap in icon div and set size. necessary to hold element in place
            el.outerHTML = `<div class='icon' style=' width: ${size}px; height: ${size}px;'>` + el.outerHTML + "</div>";
        },

        // sets token counters to new value (not increments, full new value should be passed)
        updatePlayerTokens: function(id, tire=null, nitro=null) {

            if (tire) this.counters.playerBoard[id].tireTokens.toValue(tire);
            if (nitro) this.counters.playerBoard[id].nitroTokens.toValue(nitro);
        },

        // displaySelectionOctagons: place and displays a list of selection octagons. accepts an array of objects {x:, y: } indicating the center coordinates of each octagon to display.
        displaySelectionOctagons: function(positions) {

            positions.forEach((pos, i) => {
                if (!$('selOct_'+pos.x+'_'+pos.y)) { // prevent sel octagon of same position to be created and mess the interface (should not happen anyway, server shoud handle doubles)
                    dojo.place(
                        this.format_block('jstpl_selOctagon',{
                            i: i,
                            x: pos.x,
                            y: pos.y
                        }),
                        'pos_highlights'
                    );

                    this.placeOnTrack('selOct_'+pos.x+'_'+pos.y,pos.x,pos.y);
                    dojo.style('selOct_'+pos.x+'_'+pos.y,'transform','translate(-50%,-50%) scale('+this.octSize/500+')');
                }
            });
        },

        // displays direction arrow to select orientation of F8 after movement. works similarly to method above
        displayDirectionArrows: function(positions, direction) {

            var allDirPos = [];

            positions.forEach(pos => {

                allDirPos.push(pos.coordinates);

                this.createGameElement('dirArrow', { color: (pos.black)? 'black' : 'white', direction: pos.direction}, 'dirArrows')

                this.placeOnTrack(pos.direction+'Arrow', pos.coordinates.x, pos.coordinates.y, direction+pos.rotation);
            });

            this.displaySelectionOctagons(allDirPos);

            document.querySelectorAll('#pos_highlights > .selectionOctagon').forEach( el => {
                el.style.filter = 'opacity(0)';
            })
        },

        // connectPosHighlights: function to connect position highlights elements (#pos_highlights > *) such as selection octagons (but it is also used for direction arrows) to specific handlers for click and mouseEnter events.
        //                        arguments are the names of the handlers method to call.
        //                        method connects also to standard method that wipes any preview on screen on mouse out. kinda stiched solution for previews sticking to position even when mouse is not hovering element
        connectPosHighlights: function(onclickHandler, onmouseenterHandler) {
            dojo.query('#pos_highlights > *').connect('onclick', this, onclickHandler);
            dojo.query('#pos_highlights > *').connect('onmouseenter', this, onmouseenterHandler);
            dojo.query('#pos_highlights > *').connect('onmouseleave', this, (evt) => {
                dojo.stopEvent(evt);
                dojo.empty('previews');
            });
        },

        // creates and displays window to select token amount for each type.
        // attributes amount automatically to each tipe given the already owned (base) amount for each type, and the total amount of new token to withdraw
        displayTokenSelection: function(baseTire,baseNitro,amount) {

            dojo.place(
                this.format_block('jstpl_tokenSelWin'),
                'game_play_area',
                'first'
            );

            dojo.place(
                this.format_block('jstpl_tokenSelDiv',{
                    tireIncrementer: this.format_block('jstpl_tokenIncrementer', {type: 'tire', min: baseTire, max: Math.min(baseTire + amount, 8)}),
                    nitroIncrementer: this.format_block('jstpl_tokenIncrementer', {type: 'nitro', min: baseNitro, max: Math.min(baseNitro + amount, 8)})
                }),
                'tokenSelectionWindow'
            );

            var base = {
                tire: baseTire,
                nitro: baseNitro
            };

            this.gamedatas.gamestate.args.tire = baseTire;
            this.gamedatas.gamestate.args.nitro = baseNitro;
            
            // func that handles automatic token distribution and updates html elements
            var updateCounter = (type, value) => {

                if (value == NaN) value = 0;

                /* console.log('global args, actual tire, nitro',this.gamedatas.gamestate.args);
                console.log('args baseT, baseN, amt',arguments);
                console.log('type,val: ',[type, value]);
                console.log('base: ',base); */
            
                if (value < Math.max(base[type], 0) || value > amount || value > Math.min(base[type] + amount, 8)) {

                    document.querySelector('#tireTokenIncrementer > input').value = this.gamedatas.gamestate.args.tire;
                    document.querySelector('#nitroTokenIncrementer > input').value = this.gamedatas.gamestate.args.nitro;

                    console.error([
                        {exp: 'value < Math.max(base[type], 0)', res: value < Math.max(base[type], 0), vals: {value: value, base: base}},
                        {exp: 'value > amount', res: value > amount, vals: {value: value, amount: amount}},
                        {exp: 'value > Math.min(base[type] + amount, 8)', res: value > Math.min(base[type] + amount, 8), vals: {value: value, base: base}}
                    ]);
                    this.showMessage('You must add exactly '+amount+' tokens to your pile. One type cannot be more than 8. You cannot sell already owned tokens', 'info');
                } else {

                    var tire = (type=='nitro')? (amount - value) : value;
                    var nitro = (type=='tire')? (amount - value) : value;

                    this.gamedatas.gamestate.args.tire = tire
                    this.gamedatas.gamestate.args.nitro = nitro
                    document.querySelector('#tireTokenIncrementer > input').value = tire;
                    document.querySelector('#nitroTokenIncrementer > input').value = nitro;
                }
            }

            // handler for inputting numbers into field directly
            document.querySelectorAll('.tokenIncrementer > input').forEach( el => {
                el.addEventListener('input', (evt)=>{
                    var value = evt.target.value;
                    var type = evt.target.parentElement.id.replace('TokenIncrementer','');
                    updateCounter(type, value);
                });

                el.addEventListener('click', (evt)=>{
                    evt.target.value = '';
                });
            });

            // handler for incrementerbuttons
            document.querySelectorAll('.tokenIncrementer > button').forEach( el => {
                el.addEventListener('click',(evt) => {

                    var value = parseInt(evt.target.parentElement.children[1].value);
                    var type = evt.target.parentElement.id.replace('TokenIncrementer','');

                    switch (evt.target.className) {
                        case 'plus': value++ 
                            break;
                    
                        case 'minus': value--
                            break;
                    }

                    updateCounter(type, value);
                });
            })

            // iconize token type element
            document.querySelectorAll('.incrementerDiv .token').forEach( el => {
                this.iconize(el,50);
            });

            // modify properties to animate transition that displays window
            var window = $('tokenSelectionWindow');

            var h = window.offsetHeight;
            window.style.height = '0px'; // first set to zero
            window.offsetHeight; // refresh element painter with access to some property that requires page render (magic)
            window.style.height = h+'px'; // finally set window to desired height
        },

        // creates and displays window to select token amount for each type.
        // attributes amount automatically to each tipe given the already owned (base) amount for each type, and the total amount of new token to withdraw
        displayTokenSelection: function(baseTire,baseNitro,amount) {

            dojo.place(
                this.format_block('jstpl_tokenSelWin'),
                'game_play_area',
                'first'
            );

            dojo.place(
                this.format_block('jstpl_tokenSelDiv',{
                    tireIncrementer: this.format_block('jstpl_tokenIncrementer', {type: 'tire', min: baseTire, max: Math.min(baseTire + amount, 8)}),
                    nitroIncrementer: this.format_block('jstpl_tokenIncrementer', {type: 'nitro', min: baseNitro, max: Math.min(baseNitro + amount, 8)})
                }),
                'tokenSelectionWindow'
            );

            var base = {
                tire: baseTire,
                nitro: baseNitro
            };

            this.gamedatas.gamestate.args.tire = baseTire;
            this.gamedatas.gamestate.args.nitro = baseNitro;
            
            // func that handles automatic token distribution and updates html elements
            var updateCounter = (type, value) => {

                if (value == NaN) value = 0;

                /* console.log('global args, actual tire, nitro',this.gamedatas.gamestate.args);
                console.log('args baseT, baseN, amt',arguments);
                console.log('type,val: ',[type, value]);
                console.log('base: ',base); */
            
                if (value < Math.max(base[type], 0) || value > amount || value > Math.min(base[type] + amount, 8)) {

                    document.querySelector('#tireTokenIncrementer > input').value = this.gamedatas.gamestate.args.tire;
                    document.querySelector('#nitroTokenIncrementer > input').value = this.gamedatas.gamestate.args.nitro;

                    console.error([
                        {exp: 'value < Math.max(base[type], 0)', res: value < Math.max(base[type], 0), vals: {value: value, base: base}},
                        {exp: 'value > amount', res: value > amount, vals: {value: value, amount: amount}},
                        {exp: 'value > Math.min(base[type] + amount, 8)', res: value > Math.min(base[type] + amount, 8), vals: {value: value, base: base}}
                    ]);
                    this.showMessage('You must add exactly '+amount+' tokens to your pile. One type cannot be more than 8. You cannot sell already owned tokens', 'info');
                } else {

                    var tire = (type=='nitro')? (amount - value) : value;
                    var nitro = (type=='tire')? (amount - value) : value;

                    this.gamedatas.gamestate.args.tire = tire
                    this.gamedatas.gamestate.args.nitro = nitro
                    document.querySelector('#tireTokenIncrementer > input').value = tire;
                    document.querySelector('#nitroTokenIncrementer > input').value = nitro;
                }
            }

            // handler for inputting numbers into field directly
            document.querySelectorAll('.tokenIncrementer > input').forEach( el => {
                el.addEventListener('input', (evt)=>{
                    var value = evt.target.value;
                    var type = evt.target.parentElement.id.replace('TokenIncrementer','');
                    updateCounter(type, value);
                });

                el.addEventListener('click', (evt)=>{
                    evt.target.value = '';
                });
            });

            // handler for incrementerbuttons
            document.querySelectorAll('.tokenIncrementer > button').forEach( el => {
                el.addEventListener('click',(evt) => {

                    var value = parseInt(evt.target.parentElement.children[1].value);
                    var type = evt.target.parentElement.id.replace('TokenIncrementer','');

                    switch (evt.target.className) {
                        case 'plus': value++ 
                            break;
                    
                        case 'minus': value--
                            break;
                    }

                    updateCounter(type, value);
                });
            })

            // iconize token type element
            document.querySelectorAll('.incrementerDiv .token').forEach( el => {
                this.iconize(el,50);
            });

            // modify properties to animate transition that displays window
            var window = $('tokenSelectionWindow');

            var h = window.offsetHeight;
            window.style.height = '0px'; // first set to zero
            window.offsetHeight; // refresh element painter with access to some property that requires page render (magic)
            window.style.height = h+'px'; // finally set window to desired height
        },

        // displays dialog window to select gear for greenlight phase and future gear declaration
        // argument should be array where cell index+1 indicates gear number, and cell value indicates properties of gear
        // possible properties are:
        //      * avail     - the gear is available for selection at no costs
        //      * unavail   - the gear is not available during this game phase
        //      * tireCost - the gear can be purchased using tire tokens
        //      * nitroCost   - the gear can be purchased using nitro tokens
        //      * denied    - the gear cannot be selected due to some penality to the player
        // ex: on greenLight phase [unavail, unavail, avail, avail, avail]
        //     on a standard futureGearDeclaration with previous selectect gear 4 [shiftCost, shiftCost, avail, current, avail]
        //     on recovering from an emergency break that forced a downshift from 4 to 3 [shiftCost, avail, current, denied, denied
        displayGearSelDialog: function(gears) {

            /* ['denied','denied','curr','avail','nitroCost'] */

            console.log(gears);

            // Show the dialog
            this.gearSelDW.setContent(this.format_block('jstpl_gearSelectionWindow')); // Must be set before calling show() so that the size of the content is defined before positioning the dialog
            this.gearSelDW.show();
            this.gearSelDW.replaceCloseCallback( () => { this.gearSelDW.hide(); } );

            var size = 80;
            
            gears.forEach( (g,i) => {

                dojo.place(
                    this.format_block('jstpl_selWinVectorPreview', {
                        n: i+1,
                        bottom: 0}
                    ),
                    'gearSelectionWindow'
                );
                    
                // same techniche to remove white space after scaling used in iconize()
                var gear = $('gear_'+(i+1));
                var scale = this.octSize / gear.offsetWidth * size / this.octSize;
                gear.style.transform = `scale(${scale})`;

                gear.style.marginLeft = gear.style.marginRight = `-${gear.offsetWidth * (1 - scale) / 2}px`;
                gear.style.marginTop = gear.style.marginBottom = `-${gear.offsetHeight * (1 - scale) / 2}px`;


                var optToken = '';
                if (g.indexOf('Cost') != -1) optToken = '<span>-' + (Math.abs(gears.indexOf('curr')-i)-1) + ' ' +  this.format_block('jstpl_token',{type: g.replace('Cost','')}) + '</span>';
                if (g == 'denied') optToken = '<span>' + this.format_block('jstpl_cross') + '</span>';

                gear.outerHTML = `<div data-gear-n='${i+1}' class='gearSelectionPreview gearSel_${g} ${(g=='curr')? 'gearSel_avail' : ''}' style='transform: translate(0,-${(4-i)*size/2}px)'>` + gear.outerHTML + optToken + "</div>";

            });

            document.querySelectorAll('.gearSelectionPreview').forEach( el => {
                el.addEventListener('click', evt => {

                    this.ajaxcallwrapper(this.gamedatas.gamestate.possibleactions[0],{gearN: evt.target.dataset.gearN}, (is_error => {if (!is_error) this.gearSelDW.hide()}));
                });
            });

            document.querySelectorAll('.gearSelectionPreview .token').forEach( el => {
                this.iconize(el,30);
            })

            document.querySelectorAll('.gearSelectionPreview .cross').forEach( el => {
                this.iconize(el,50);
            })
        },

        // formats a new game element of some type (car, curve, gearVector, boostVector, pitwall) and place it inside 'track' node
        createGameElement: function(type, args={}, refnode='track') {

            dojo.place(
                this.format_block('jstpl_'+type, args),
                refnode
            );

            // counter original rotation
            var rotation;
            switch (type) {
                case 'car': rotation = -4; break;
                case 'curve': rotation = -3; break;
                default: rotation = -2; break; // (for gear and boost vectors, pitwall, dirArrows, ..)
            }

            // center, adapt to interface scale, rotate element
            var transform = `translate(-50%,-50%) scale(${this.octSize/500}) rotate(${rotation*-45}deg)`;
            var element = $(refnode).lastChild;

            element.style.transform = transform;
        },

        // handles case where it's the car first placement, thus it is invisible and should be placed on on respective player boards before being slid to the track
        carFirstPlacement: function(id,x,y) {
            var carid = 'car_'+this.gamedatas.players[id].color;
            $(carid).style.display = '';

            var pb = this.getPlayerBoardCoordinates(id);
            this.placeOnTrack(carid, pb.x, pb.y);

            this.slideOnTrack(carid, x, y);
        },

        // formats a car preview element and transforms it to match active player car
        createPreviewCar: function() {
            dojo.place(
                this.format_block('jstpl_car', {color: 'preview'}),
                'previews'
            );

            dojo.style('car_preview','transform',$('car_'+this.gamedatas.players[this.getActivePlayerId()].color).style.transform);
        },

        // istantaneously move game element to coordinates (x,y), assumed to be relative to track plane ((0,0) is center of pitwall). also rotate element k times 45deg (counter clockwise)
        placeOnTrack: function(id, x, y, k=null) {

            var el = $(id);

            el.style.position = "absolute"; // redundant, but safe

            el.style.left = x +'px';
            el.style.top = -y +'px';

            if (k) el.style.transform += `rotate(${k * -45}deg)`;
        },

        // as method above, but applies css transition to the movement
        slideOnTrack: function(id, x, y, k=null, duration=500, delay=0, onEnd=()=>{}) {

            var el = $(id);
            
            el.offsetWidth; // MAGIC that sets all changed css properties before, so that it doesn't influence transition

            el.style.transitionDuration = duration+'ms';
            el.style.transitionDelay = delay+'ms';

            el.style.transitionProperty = 'left, top, transform';
            
            this.placeOnTrack(id, x, y, k)

            var transitionPropCounter = 2; // should be 3 but apparently left-top properties, by transitioning together, they fire transitionend once? doesn't make sense
            if (!k) transitionPropCounter--; // if no rotation, there will be one less transition.

            el.ontransitionend = () => {
                transitionPropCounter--;
                
                if (transitionPropCounter == 0) {

                    // reset transition properties
                    el.style.transitionProperty = '';
                    el.style.transitionDuration = '';
                    el.style.transitionDelay = '';

                    onEnd.call();
                }
            }
        },

        // useful method to extract coordinates of a selectionOctagon from its ID
        selOctagonPos: function(selOctElement) {
            return {
                x: parseInt(selOctElement.id.split('_')[1]),
                y: parseInt(selOctElement.id.split('_')[2])
            }
        },
        
        //#endregion

        //++++++++++++++++//
        // PLAYER ACTIONS //
        //++++++++++++++++//
        //#region actions

        // previewStartCarPos: display preview of player car for the first placement (process is different from function below as it costantly follows the user input)
        previewStartCarPos: function(evt) {
            // cool, now it also accounts for pitlane orientation

            dojo.stopEvent(evt);

            var h = $('start_positioning_area').clientHeight;
            var rot = this.gamedatas.gamestate.args.rotation;

            var xp = this.gamedatas.gamestate.args.anchorPos.x;
            var yp = this.gamedatas.gamestate.args.anchorPos.y;

            var offx = evt.offsetX; // offset from left (NOT NEEDED)
            var offy = evt.offsetY; // offset from top

            var x = xp+this.octSize/2
            var y = yp+h-offy;

            if (offy > h-this.octSize/2) y = yp+this.octSize/2;
            if (offy < this.octSize/2) y = yp+h-this.octSize/2;

            omg = -rot * Math.PI/4;
            var c = Math.cos(omg);
            var s = Math.sin(omg);

            var xr = ((x-xp)*c - (y-yp)*s) +xp;
            var yr = ((x-xp)*s + (y-yp)*c) +yp;

            if (!$('car_preview')) this.createPreviewCar();
            this.placeOnTrack('car_preview', xr, yr);
        },

        // selectStartCarPos: specific method to select car position for first player
        selectStartCarPos: function(evt) {

            dojo.stopEvent(evt);

            var posX = parseInt($('car_preview').style.left);
            var posY = -(parseInt($('car_preview').style.top));

            this.ajaxcallwrapper('placeFirstCar',{x: posX, y: posY}, null, '#start_positioning_area');
        },

        // previewCarPos: display preview of players car behind the hovering octagon highlight
        previewCarPos: function(evt) {
            dojo.stopEvent(evt);

            var pos = this.selOctagonPos(evt.target);

            this.createPreviewCar();

            this.placeOnTrack('car_preview', pos.x, pos.y);
        },

        // selectCarFSPos: method to select car position during flying-start initial game phase. position is obtained from the id of the clicked (selection octagon) element
        selectCarFSPos: function(evt) {
            dojo.stopEvent(evt);

            var pos = evt.target.dataset.posIndex;
            this.ajaxcallwrapper('placeCarFS', {ref: this.gamedatas.gamestate.args.refCar, pos: pos}, null, '.selectionOctagon');
        },

        // previewGearVecPos: display vector on the highlighted octagon, starting from the bottom of it.
        previewGearVecPos: function(evt) {
            dojo.stopEvent(evt);

            var currGear = this.gamedatas.gamestate.args.gear;
            this.createGameElement('gearVector', {n: currGear}, 'previews');

            var pos = this.gamedatas.gamestate.args.positions[parseInt(evt.target.dataset.posIndex)];
            
            var tireCost = pos['tireCost'];
            var anchorPos = pos['anchorCoordinates'];
            var pos = pos['vectorCoordinates'];
            //naggia

            this.placeOnTrack('gear_'+currGear, pos.x, pos.y, this.gamedatas.gamestate.args.direction);
            
            /* if (tireCost) {
                dojo.place( this.format_block('jstpl_token', {type: 'tire'}), 'tokens');

                $('tokens').lastChild.className += ' selOctToken';
                
                //debugger
                $('tokens').lastChild.style.cssText = `
                    left: ${anchorPos.x}px;
                    top: ${-anchorPos.y - this.octSize/2}px;`;
            } */
        },

        // handles user click on a selection octagon when placing a vector during movemente phase
        selectGearVecPos: function(evt) {
            dojo.stopEvent(evt);

            //document.querySelectorAll('.selectionOctagon').forEach( el => el.style.pointerEvents = 'none');

            var pos = this.gamedatas.gamestate.args.positions[parseInt(evt.target.dataset.posIndex)]['position'];
            
            this.ajaxcallwrapper('placeGearVector', {pos: pos}, null, '.selectionOctagon');                
        },

        // displays gear vector as positionend on hovered selection octagon
        previewBoostVecPos: function(evt) {
            dojo.stopEvent(evt);

            var n = this.gamedatas.gamestate.args.positions[parseInt(evt.target.dataset.posIndex)]['length'];
            var pos = this.gamedatas.gamestate.args.positions[parseInt(evt.target.dataset.posIndex)]['vecCenterCoordinates'];

            this.createGameElement('boostVector', {n: n}, 'previews');
            this.placeOnTrack('boost_'+n, pos.x, pos.y, this.gamedatas.gamestate.args.direction);
        },

        // sends ajaxcall to confirm gear vector position
        selectBoostVecPos: function(evt) {

            dojo.stopEvent(evt);

            var n = this.gamedatas.gamestate.args.positions[parseInt(evt.target.dataset.posIndex)]['length'];
            
            this.ajaxcallwrapper('placeBoostVector', {n: n}, null, '.selectionOctagon');
        },

        // displays orientation arrow to let user decide car direction before confirming position and endiong movement phase
        selectCarPos: function(evt) {

            dojo.stopEvent(evt);

            var pos = this.gamedatas.gamestate.args.positions[parseInt(evt.target.dataset.posIndex)];

            if (!pos.legal) {
                this.showMessage(_("Illegal car position"),"error");
                return;
            }

            if (pos.denied) {
                this.showMessage(_("Car position denied by the previous shunking you suffered"),"error");
                return;
            }

            if (pos.tireCost && this.counters.playerBoard[this.getActivePlayerId()].tireTokens.getValue() < 1) {
                this.showMessage(_("You don't have enough Tire Tokens to place your car here"),"error");
                return;
            }

            this.gamedatas.gamestate.descriptionmyturn = _('${you} must choose where the car should be pointing');
            this.updatePageTitle();

            this.gamedatas.gamestate.args.positions = pos;

            // move element from highlights to track to avoid removal
            dojo.place(
                'car_preview',
                'track'
            );
            
            $('pos_highlights').innerHTML = '';
            $('previews').innerHTML = '';

            var directions = this.gamedatas.gamestate.args.positions['directions'];

            // with the obtained positions, generate and display the direction arrows and connect them to the proper handlers
            this.displayDirectionArrows(directions, this.gamedatas.gamestate.args.direction);
            this.connectPosHighlights('confirmCarRotation','previewCarRotation');
        },

        // rotate preview car in the direction of the hovered direction arrow dom element
        previewCarRotation: function(evt) {
            dojo.stopEvent(evt);

            var rotation = this.gamedatas.gamestate.args.positions['directions'][parseInt(evt.target.dataset.posIndex)];
            
            // var black = rotation['black'];
            // var pos = rotation['coordinates'];
            var rotation = rotation['rotation'];

            /* if (black) {
                dojo.place( this.format_block('jstpl_token', {type: 'tire'}), 'tokens');

                $('tokens').lastChild.className += ' selOctToken';
                
                //debugger
                $('tokens').lastChild.style.cssText = `
                    left: ${pos.x}px;
                    top: ${-pos.y - this.octSize/2}px;`;
            } */

            const playerCarTransform = $('car_'+this.gamedatas.players[this.getActivePlayerId()].color).style.transform;

            $('car_preview').style.transform = playerCarTransform + 'rotate('+rotation*-45+'deg)';
        },

        // handles user click on a direction arrow when choosing the car orientation at the end of the movement phase
        confirmCarRotation: function(evt) {
            dojo.stopEvent(evt);

            var dir = this.gamedatas.gamestate.args.positions['directions'][parseInt(evt.target.dataset.posIndex)]['direction'];
            var pos =  this.gamedatas.gamestate.args.positions['position'];

            this.ajaxcallwrapper('placeCar',{ pos: pos, dir: dir}, null, '.selectionOctagon');
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
            dojo.subscribe('allVertices', this, 'notif_allVertices');

            dojo.subscribe('placeFirstCar', this, 'notif_placeFirstCar');
            this.notifqueue.setSynchronous( 'placeFirstCar', 500 );

            dojo.subscribe('selectPosition', this, 'notif_selectPosition');
            this.notifqueue.setSynchronous( 'selectPosition', 500 );

            dojo.subscribe('chooseTokensAmount', this, 'notif_chooseTokensAmount');
            this.notifqueue.setSynchronous( 'chooseTokensAmount', 500 );

            dojo.subscribe('chooseStartingGear', this, 'notif_chooseStartingGear');
            this.notifqueue.setSynchronous( 'chooseStartingGear', 500 );

            dojo.subscribe('placeGearVector', this, 'notif_placeGearVector');
            this.notifqueue.setSynchronous( 'placeGearVector', 500 );

            dojo.subscribe('useBoost', this, 'notif_useBoost');
            this.notifqueue.setSynchronous( 'useBoost', 500 );

            dojo.subscribe('chooseBoost', this, 'notif_chooseBoost');
            this.notifqueue.setSynchronous( 'chooseBoost', 500 );

            dojo.subscribe('placeCar', this, 'notif_placeCar');
            this.notifqueue.setSynchronous( 'placeCar', 500 );

            dojo.subscribe('declareGear', this, 'notif_declareGear');
            this.notifqueue.setSynchronous( 'declareGear', 500 );

            dojo.subscribe('engageManeuver', this, 'notif_engageManeuver');
            this.notifqueue.setSynchronous( 'engageManeuver', 500 );

            dojo.subscribe('chooseSlingshotPosition', this, 'notif_chooseSlingshotPosition');
            this.notifqueue.setSynchronous( 'chooseSlingshotPosition', 500 );

            dojo.subscribe('gearShift', this, 'notif_gearShift');
            this.notifqueue.setSynchronous( 'gearShift', 500 );

            dojo.subscribe('nextRoundTurnOrder', this, 'notif_nextRoundTurnOrder');
            this.notifqueue.setSynchronous( 'nextRoundTurnOrder', 4000 );
            
        },  

        // --- HANDLERS ---
        
        notif_logger: function(notif) {
            console.log(notif.args);

            Object.values(notif.args.vertices).forEach( el => {
                console.log(el);
                this.displayPoints(el);
            });


        },
        
        notif_allVertices: function(notif) {
            console.log(notif.args);

            Object.values(notif.args).forEach( el => {
                this.displayPoints(el);
            });
        },

        notif_placeFirstCar: function(notif) {
            this.carFirstPlacement(notif.args.player_id, notif.args.x, notif.args.y);
        },

        notif_placeCarFS: function(notif) {
            this.carFirstPlacement(notif.args.player_id, notif.args.x, notif.args.y);
        },

        notif_chooseTokensAmount: function(notif) {
            this.updatePlayerTokens(notif.args.player_id, notif.args.tire, notif.args.nitro);
            if (this.isCurrentPlayerActive()) {
                $('tokenSelectionWindow').style.height = '0px';
                $('tokenSelectionWindow').ontransitionEnd = () => {$('tokenSelectionWindow').remove()}
            }
        },

        notif_selectPosition: function(notif) {
            if (!this.isCurrentPlayerActive()) this.carFirstPlacement(notif.args.player_id, notif.args.posX, notif.args.posY);
        },

        notif_placeGearVector: function(notif) {

            var vecPreview = (document.querySelector('.gearVector'));
            if (vecPreview) vecPreview.remove();

            this.createGameElement('gearVector',{ n: notif.args.gear });            
            var pb = this.getPlayerBoardCoordinates(notif.args.player_id);
            this.placeOnTrack('gear_'+notif.args.gear, pb.x, pb.y, notif.args.direction);
            this.slideOnTrack('gear_'+notif.args.gear, notif.args.x, notif.args.y);         

            this.updatePlayerTokens(notif.args.player_id, notif.args.tireTokens, null);
        },

        notif_useBoost: function(notif) {

            this.updatePlayerTokens(notif.args.player_id, null, notif.args.nitroTokens);
        },

        notif_chooseBoost: function(notif) {

            var boostPreview = (document.querySelector('.boostVector'));
            if (boostPreview) boostPreview.remove();

            this.createGameElement('boostVector',{ n: notif.args.n }); 

            var pb = this.getPlayerBoardCoordinates(notif.args.player_id);
            this.placeOnTrack('boost_'+notif.args.n, pb.x, pb.y, notif.args.direction);
            this.slideOnTrack('boost_'+notif.args.n, notif.args.vecX, notif.args.vecY);
        },

        notif_placeCar: function(notif) {

            this.slideOnTrack('car_'+this.gamedatas.players[notif.args.player_id].color, notif.args.x, notif.args.y, notif.args.rotation, 500, 0, () => {

                var pb = this.getPlayerBoardCoordinates(notif.args.player_id);
                
                this.slideOnTrack(document.querySelector('.gearVector').id, pb.x, pb.y, 0, 500, 0, () => {

                    document.querySelector('.gearVector').remove();

                    var boost = document.querySelector('.boostVector');

                    if (boost) this.slideOnTrack(boost.id, pb.x, pb.y, 0, 500, 0, () => boost.remove());
                });
            });

            this.updatePlayerTokens(notif.args.player_id, notif.args.tireTokens, null);
        },

        notif_engageManeuver: function(notif) {
            this.slideOnTrack('car_'+this.gamedatas.players[notif.args.player_id].color, notif.args.attackPos.x, notif.args.attackPos.y);

            this.updatePlayerTokens(notif.args.player_id, null, notif.args.nitroTokens);
        },

        notif_chooseSlingshotPosition: function(notif) {
            this.slideOnTrack('car_'+this.gamedatas.players[notif.args.player_id].color, notif.args.slingshotPos.x, notif.args.slingshotPos.y);
        },

        notif_chooseStartingGear: function(notif) {

            Object.values(this.gamedatas.players).forEach(player => {
                var gear = $('gear_p'+player.id);
                var i = gear.className.indexOf('gearInd_')
                gear.className = gear.className.slice(0,i).concat('gearInd_'+notif.args.n);
            });
        },

        notif_declareGear: function(notif) {

            var gear = $('gear_p'+notif.args.player_id);
            var i = gear.className.indexOf('gearInd_')
            gear.className = gear.className.slice(0,i).concat('gearInd_'+notif.args.n);
        },

        notif_gearShift: function(notif) {

            this.updatePlayerTokens(
                notif.args.player_id,
                (notif.args.tokenType == 'tire')? notif.args.tokensAmt : null,
                (notif.args.tokenType == 'nitro')? notif.args.tokensAmt : null);
        },

        notif_nextRoundTurnOrder: function(notif) {

            for (const key in notif.args) {

                var pos = notif.args[key];
                
                dojo.place(
                    this.format_block('jstpl_turnPosInd',{pos:pos}),
                    'touchable_track'
                );

                var playerCar = $('car_'+this.gamedatas.players[key].color);
                var indicator = $('turnPos_'+pos);

                indicator.style.transform = 'translate(-50%,-50%) scale('+this.octSize/250+')';
                indicator.style.left = playerCar.style.left;
                indicator.style.top = playerCar.style.top;
                indicator.style.animationDelay = (pos-1)+'s'; 
                // element then removed when leaving state bacause it gets buggy otherwise

                this.counters.playerBoard[key].turnPos.toValue(pos);
            }
        },

        //#endregion
   
    });             
});
