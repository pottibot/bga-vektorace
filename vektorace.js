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

            // -- EXTRACT OCTAGON REFERENCE MEASURES --
            // actually permanent since all rescaling is done with css transform
            this.octSize = parseInt(gamedatas.octagon_ref['size']);
            this.octSide = parseInt(gamedatas.octagon_ref['side']);
            this.octSeg = parseInt(gamedatas.octagon_ref['corner_segment']);
            this.octRad = parseInt(gamedatas.octagon_ref['radius']);
            
            // -- SETUP PLAYER BOARDS --
            for (var player_id in gamedatas.players) { // js foreach extract the keys, not the values
                var player = gamedatas.players[player_id];
                
                var player_board_div = $('player_board_'+player_id);
                dojo.place( this.format_block('jstpl_player_board', {
                    gear: this.format_block('jstpl_current_gear', { id: player_id, n: player['currGear'] }),
                    lap: this.format_block('jstpl_lap_counter', { id: player_id, lap: player['lapNum'] }),
                    standings: this.format_block('jstpl_standings_position', { id: player_id, pos: player['turnPos'] }),
                    tire: this.format_block('jstpl_tokens_counter', { id: player_id, type: 'tire', count: player['tireTokens'] }),
                    nitro: this.format_block('jstpl_tokens_counter', { id: player_id, type: 'nitro', count: player['nitroTokens'] })
                } ), player_board_div );
            }
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

                console.log(el);
                
                switch (el.entity) {

                    case 'pitwall':
                        this.createGameElement('pitwall');
                        this.placeOnTrack('pitwall', el.pos_x, el.pos_y, el.orientation);

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
            console.log('Entering state: '+stateName);
            console.log('State args: ',args.args);
            
            switch(stateName) {

                case 'firstPlayerPositioning':
                    if(!this.isCurrentPlayerActive()) return;

                    this.displayPoints([args.args.center])

                    dojo.place( this.format_block('jstpl_posArea'), 'pos_highlights' );
                    this.placeOnTrack('start_positioning_area',args.args.anchorPos.x,args.args.anchorPos.y,0);
                    
                    $('start_positioning_area').style.transformOrigin = 'bottom left'
                    $('start_positioning_area').style.transform = `translate(0,-100%) rotate(${args.args.rotation*45}deg)`;

                    dojo.query('#start_positioning_area').connect('onclick',this,'selectStartCarPos');
                    dojo.query('#start_positioning_area').connect('mousemove',this,'previewStartCarPos');
                    dojo.query('#start_positioning_area').connect('onmouseleave', this, (evt) => {
                        dojo.stopEvent(evt);
                        dojo.empty('previews');
                    });

                    break;

                case 'tokenAmountChoice':
                    
                    if(!this.isCurrentPlayerActive()) return;
                    
                    var baseTire = parseInt(args.args.tire);
                    var baseNitro = parseInt(args.args.nitro);
                    this.displayTokenSelection(baseTire,baseNitro, args.args.amount);
                    this.addActionButton('confirmTokenAmount', _('Confirm'), () => {

                        args.args.tire = this.gamedatas.gamestate.args.tire;
                        args.args.nitro = this.gamedatas.gamestate.args.nitro;
                        if (args.args.tire + args.args.nitro == Math.min(baseTire + baseNitro + args.args.amount, 16)) {
                            console.log(args.args);
                            this.ajaxcallwrapper('chooseTokensAmount',{ tire: args.args.tire, nitro: args.args.nitro});
                        } else this.showMessage('You must add some tokens to your pile');
                        
                    }, null, false, 'blue');


                    break;

                case 'playerPositioning':

                    // avoid displaying additional infos for players who are not active
                    if(!this.isCurrentPlayerActive()) return; // maybe better place out of switch to prevent not current player entering any state handler

                    switch (args.args.display) {

                        case 'positioningArea':
                            
                            dojo.place( this.format_block('jstpl_posArea'), 'pos_highlights' );

                            // slide position to match pitwall
                            // !! sliding cordinates are not robust, they depend on the initial interface zoom
                            var wallsize = $('pitwall').getBoundingClientRect().width;
                            var seg = wallsize/4 / this.octSize * this.octSeg;
                            this.placeOnTrack('start_positioning_area',wallsize-seg*2-5,this.octSize/2);

                            // THIS OBJECT TRANSLATION IS VALID ONLY FOR STATICALLY POSITIONED PITWALLS IN ORIZONTAL ORIENTATION
                            // THAT'S BECAUSE FINAL GAME PROBABLY WON'T ALLOW CUSTOM TRACK LAYOUT
                            dojo.style('start_positioning_area','transform','translate(0,-100%)')

                            dojo.query('#start_positioning_area').connect('onclick',this,'selectStartCarPos');
                            dojo.query('#start_positioning_area').connect('mousemove',this,'previewStartCarPos');
                            dojo.query('#start_positioning_area').connect('onmouseleave', this, (evt) => {
                                dojo.stopEvent(evt);
                                dojo.empty('previews');
                            });
                                
                            break;
                    
                        case 'chooseRef':

                            // if possible fs reference cars are more than one,
                            // let player decide what car to display fs positions from

                            // state descriptions changes for when player is deciding reference car
                            var orginalDescription = args.descriptionmyturn;
                            var alternativeDescription = _('${you} have to select a reference car to determine all possible "flying-start" positions');

                            this.gamedatas.gamestate.descriptionmyturn = alternativeDescription;
                            this.updatePageTitle();

                            // iterate on all possible reference cars, place selection octagon, connect it to function that displays fs positions, add button to reset ref car
                            Object.keys(args.args.positions).forEach(id => {
                                
                                var col = this.gamedatas.players[id].color;

                                var carPos = {
                                    x: dojo.style($('car_'+col),'left'),
                                    y: -(dojo.style($('car_'+col),'top'))
                                }

                                dojo.place(
                                    this.format_block('jstpl_selOctagon',{
                                        i: id,
                                        x: carPos.x,
                                        y: carPos.y
                                    }),
                                    'car_highlights'
                                );

                                var selOctId = `selOct_${carPos.x}_${carPos.y}`;

                                this.placeOnTrack(selOctId, carPos.x, carPos.y);
                                // usual transformation to adapt new element to interface
                                dojo.style(selOctId,'transform','translate(-50%,-50%) scale('+this.octSize/500+')');

                                this.connect($(selOctId), 'onclick', (evt) => {
                                    
                                });
                                
                                // connect selection octagon with temp function that handle this specific case
                                this.connect($(selOctId), 'onclick', (evt) => {
                                    dojo.stopEvent(evt);

                                    this.gamedatas.gamestate.descriptionmyturn = orginalDescription;
                                    this.updatePageTitle();
                                    
                                    // destroy all highlited positions, if present
                                    dojo.empty('pos_highlights')
                                    // hide all highlighted cars, to focus user attention to new highlighted positions and clean interface
                                    dojo.query('#car_highlights > *').style('display','none');

                                    // finally, display all fs position from this ref car
                                    this.displaySelectionOctagons(Object.values(args.args.positions[id]));
                                    this.connectPosHighlights('selectCarFSPos','previewCarPos');

                                    // add red actionbutton (persists till end of state), to reset choice of ref car
                                    this.addActionButton('resetFSref_button', _('Reset'), () => {
                                            this.gamedatas.gamestate.descriptionmyturn = alternativeDescription;
                                            this.updatePageTitle();

                                            dojo.style('car_'+this.gamedatas.players[this.getActivePlayerId()].color,'display','none');

                                            // remove all highlighted pos and show again selection of ref cars
                                            dojo.empty('pos_highlights');
                                            dojo.query('#car_highlights > *').style('display','');
                                    }, null, false, 'red');
                                    
                                    this.disconnect();
                                });
                            })

                        break;

                        case 'fsPositions':
                            // if object contains positions only for one car
                            // display only those
                            this.displaySelectionOctagons(Object.values(args.args.positions)[0]);
                            this.connectPosHighlights('selectCarFSPos','previewCarPos');
                    }
                            
                    break;         
                
                case 'greenLight':

                    if(!this.isCurrentPlayerActive()) return; // always prevent interface to change for those whom are not the active player

                    // add putton that displays vector selection in 'green light' mode
                    this.addActionButton('showGearSelDialogButton', _('show selection'), () => {
                        this.displayGearSelDialog('GreenLight');
                    }, null, false, 'blue');
                    
                    break;
                
                case 'placeGearVector':

                    if(!this.isCurrentPlayerActive()) return;

                    var vecAllPos = [];
                    args.args.positions.forEach(pos => {
                        vecAllPos.push(pos.anchorCoordinates);
                    })

                    this.displaySelectionOctagons(vecAllPos); // display vector attachment position in front of the car
                    this.connectPosHighlights('selectGearVecPos','previewGearVecPos'); // then connect highlights to activate hover preview and click input event

                    document.querySelectorAll('.selectionOctagon').forEach((selOct) => {
                        var i = selOct.dataset.posIndex;
                        var pos = args.args.positions[i];

                        /* if (!pos.legal) {
                            dojo.place(this.format_block('jstpl_illegalCross'), 'pos_highlights');
                            $('pos_highlights').lastChild.style.cssText = `left: ${selOct.style.left}; top: ${selOct.style.top}; pointer-events: none`;
                        }
                        else if (pos.tireCost) {
                            dojo.place(this.format_block('jstpl_token',{type:'tire', optClass: 'selOctToken'}), 'pos_highlights');
                            $('pos_highlights').lastChild.style.cssText = `left: ${selOct.style.left}; top: ${selOct.style.top}; pointer-events: none`;
                            selOct.style.filter = 'brightness(0.5)';
                        }; */

                        if (!pos.legal) {
                            dojo.removeClass(selOct.id,'standardPos');
                            dojo.addClass(selOct.id,'illegalPos');
                            //dojo.removeClass(selOct.id,'selectionOctagon');
                        }
                        else if (pos.tireCost) {
                            dojo.removeClass(selOct.id,'standardPos');
                            dojo.addClass(selOct.id,'tirePos');
                            //dojo.removeClass(selOct.id,'selectionOctagon');
                        };
                    });

                    break;

                case 'boostPrompt':

                    this.addActionButton(
                        'useBoost_button',
                        _('Use Boost')+' -1 '+this.format_block('jstpl_token',{type:'nitro'}),
                        () => {this.ajaxcallwrapper('useBoost', {use: true})},
                        null, false, 'red'
                    ); 
        
                    $('useBoost_button').style.cssText = `color: #eb6b0c;
                                                          background: #fed20c;
                                                          borderColor: #f7aa16`;
        
                    this.iconize(document.querySelector('#useBoost_button > .token'),20);
                    
                    this.addActionButton(
                        'skipBoost_button',
                        _("Skip"),
                        () => {this.ajaxcallwrapper('useBoost', {use: false})},
                        null, false, 'gray');

                    break;
                
                case 'boostChoice':

                    if(!this.isCurrentPlayerActive()) return;

                    var boostAllPos = [];
                    args.args.positions.forEach(pos => {
                        boostAllPos.push(pos.vecTopCoordinates);
                    })

                    this.displaySelectionOctagons(boostAllPos);
                    this.connectPosHighlights('selectBoostVecPos','previewBoostVecPos');

                    break;

                case 'placeCar':

                    if(!this.isCurrentPlayerActive()) return;

                    var carAllPos = [];
                    args.args.positions.forEach(pos => {
                        carAllPos.push(pos.coordinates);
                    })

                    this.displaySelectionOctagons(carAllPos);
                    this.connectPosHighlights('selectCarPos','previewCarPos');

                    break;              
                
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

        displayPoints: function(points) {
            points.forEach((p,i) => {
                dojo.place(
                    `<div id='${p.x}_${p.y}' class='point'>${i}</div>`,
                    'track'
                );

                this.placeOnTrack(`${p.x}_${p.y}`,p.x,p.y);
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

            if (scalestep >= 0 && scalestep < 7 || true) {
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

        iconize: function(el, size, offset=null) {

            // scale to size 100px, then scale to wanted size
            var scale = this.octSize / ((offset)? offset : el.offsetWidth) * size / this.octSize;
            console.log(scale);

            el.style.transform = `scale(${scale})`;

            // calc margin to remove white space around scaled element
            // ! assuming element is square
            el.style.margin = `-${el.offsetWidth * (1 - scale) / 2}px`;

            // wrap in icon div and set size. necessary to hold element in place
            el.outerHTML = `<div class='icon' style=' width: ${size}px; height: ${size}px;'>` + el.outerHTML + "</div>";
        },

        updatePlayerTokens: function(id, tire=0, nitro=0) {

            $('tireTokensCount_p'+id).innerHTML = 'x'+tire;
            $('nitroTokensCount_p'+id).innerHTML = 'x'+nitro;

        },

        // displaySelectionOctagons: place and displays a list of selection octagons. accepts an array of objects {x:, y: } indicating the center coordinates of each octagon to display.
        displaySelectionOctagons: function(positions) {

            positions.forEach((pos, i) => {
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
            });
        },

        // displays direction arrow to select orientation of F8 after movement. works similarly to method above
        displayDirectionArrows: function(positions, direction) {

            var allDirPos = [];

            positions.forEach(pos => {
                console.log(pos);

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
                dojo.empty('tokens');
            });
        },

        // validateOrCancelCarPosition: function called when user chooses new car position (car is already moved there). used to clean interface and display confirmation or cancel buttons
        validateOrCancelCarPosition: function(posX,posY) {
            // NOTE: ALL PREVIOUSLY ADDED BUTTONS WILL PERSIST HERE

            // it's wise to hide any red button during this phase, as to no interfere with the current action taking place
            dojo.query('#generalactions > .bgabutton_red').style('display','none');

            // since this is time for validation of the move, we can hide all other option while player chooses to confirm
            dojo.query('#pos_highlights > *').style('display','none');

            // button to cancel new position. it reverts move by hiding car (MAY BE UNSUITABLE FOR CERTAIN SITUATIONS), removing all added buttons, and displaying any previuously hid red button
            this.addActionButton( 'cancelPos_button', _('Cancel'),
                () => {
                    dojo.destroy($('cancelPos_button'));
                    dojo.destroy($('validatePos_button'));
                    dojo.query('#generalactions > .bgabutton_red').style('display','');

                    dojo.query('#pos_highlights > *').style('display','');
                    dojo.style('car_'+this.gamedatas.players[this.getActivePlayerId()].color,'display','none');
                },
                null, false, 'gray'); 

            // button to validate new position. finally sends position to server to make decision permanent.
            this.addActionButton( 'validatePos_button', _('Validate'), () => this.ajaxcallwrapper('selectStartingPosition', {x: posX, y: posY}) ); 
        },

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

            document.querySelectorAll('.incrementerDiv .token').forEach( el => {
                this.iconize(el,50);
            });

            var window = $('tokenSelectionWindow');

            var h = window.offsetHeight;
            window.style.height = '0px';
            window.offsetHeight;
            window.style.height = h+'px';
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
            dojo.query('.selWinVectorPreview').connect('onclick',this, (evt) => {
                dojo.stopEvent(evt);
                this.gearSelDW.hide();
                this.ajaxcallwrapper(this.gamedatas.gamestate.possibleactions[0], {gearN: evt.target.id.split('_')[1]});
            });
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

        // COULD ALSO BE DONE WITH SELECTION OCTAGONS THAT DISPLAY BOOST PREVIEW WHEN HOVERED (YES)
        // EASIER HANDLING OF USER INTERACTION, SERVER GIVES POSITIONS AND ALSO CHECKS IF THEY ARE (OR PRODUCE) ILLEGAL MOVES
        displayBoostPreviews: function() {

            this.gamedatas.gamestate.descriptionmyturn = _('${you} now have to choose what boost vector to use');
            this.updatePageTitle();

            $('pos_highlights').style.display = 'none';

            var n = parseInt(this.gamedatas.gamestate.args.currentGear);
            var direction = this.gamedatas.gamestate.args.direction;

            // center pos of placed vector
            var gearPos = {
                x: parseInt($('gear_'+n).style.left),
                y: -parseInt($('gear_'+n).style.top)
            }

            // offset length, vector magnitude
            var ro = (n+i) * this.octSize/2;
            // cruise direction, vector angle
            var omg = direction*Math.PI/4;
        
            for (var i=n-1; i>0; i--) {
                this.createGameElement('boostVector',{n:i},'boosts');

                // offset length, vector magnitude
                var ro = (n+i) * this.octSize/2;

                var offsetX = ro * Math.cos(omg);
                var offsetY = ro * Math.sin(omg);

                this.placeOnTrack('boost_'+i, gearPos.x+offsetX, gearPos.y+offsetY, direction);
            }

            dojo.query('.boostVector').addClass('boostPreview')

            dojo.query('.boostPreview').connect('onclick', this, 'selectBoost')
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

            $('car_preview').style.display = 'none';
            $('start_positioning_area').style.display = 'none';

            this.ajaxcallwrapper('placeFirstCar',{x: posX, y: posY}, (is_error) => {

                console.log(is_error);

                if (is_error) {
                    $('car_preview').style.display = '';
                    $('start_positioning_area').style.display = '';
                }
            });
        },

        // previewCarPos: display preview of players car behind the hovering octagon highlight
        previewCarPos: function(evt) {
            dojo.stopEvent(evt);

            // KNOW THAT THERE'S NO HANDLING OF OVERLAPPING INPUT
            // PLAYER MIGHT MISTAKENLY CHOOSE WRONG POSITION

            var pos = this.selOctagonPos(evt.target);

            this.createPreviewCar();

            this.placeOnTrack('car_preview', pos.x, pos.y);
        },

        // THERE COULD BE ONLY ONE GENERAL PURPOUSE METHOD FOR SELECTING CAR POSITION. PERAPHS ONE THAT DOES THE FORMATTING AND PLACING AND THE OTHER THAT DOES THE ACTION HANDLER PART
        // selectCarFSPos: method to select car position during flying-start initial game phase. position is obtained from the id of the clicked (selection octagon) element
        selectCarFSPos: function(evt) {
            dojo.stopEvent(evt);

            var pos = this.selOctagonPos(evt.target);

            this.carFirstPlacement(this.getActivePlayerId(), pos.x, pos.y);
            this.validateOrCancelCarPosition(pos.x, pos.y);
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
            if (tireCost) {
                dojo.place( this.format_block('jstpl_token', {type: 'tire'}), 'tokens');

                $('tokens').lastChild.className += ' selOctToken';
                
                //debugger
                $('tokens').lastChild.style.cssText = `
                    left: ${anchorPos.x}px;
                    top: ${-anchorPos.y - this.octSize/2}px;`;
            }
        },

        // handles user click on a selection octagon when placing a vector during movemente phase
        selectGearVecPos: function(evt) {
            dojo.stopEvent(evt);

            // move from preview to track to avoid removal
            dojo.place(
                'gear_'+this.gamedatas.gamestate.args.gear,
                'track'
            );

            dojo.empty('pos_highlights');
            dojo.empty('previews');

            var pos = this.gamedatas.gamestate.args.positions[parseInt(evt.target.dataset.posIndex)]['position'];
            
            this.ajaxcallwrapper('placeGearVector', {pos: pos});                
        },

        previewBoostVecPos: function(evt) {
            dojo.stopEvent(evt);

            var n = this.gamedatas.gamestate.args.positions[parseInt(evt.target.dataset.posIndex)]['length'];
            var pos = this.gamedatas.gamestate.args.positions[parseInt(evt.target.dataset.posIndex)]['vecCenterCoordinates'];

            this.createGameElement('boostVector', {n: n}, 'previews');
            this.placeOnTrack('boost_'+n, pos.x, pos.y, this.gamedatas.gamestate.args.direction);
        },

        selectBoostVecPos: function(evt) {

            dojo.stopEvent(evt);

            var n = this.gamedatas.gamestate.args.positions[parseInt(evt.target.dataset.posIndex)]['length'];

            dojo.place(
                'boost_'+n,
                'track'
            );

            $('pos_highlights').innerHTML = '';
            $('previews').innerHTML = '';
            
            this.ajaxcallwrapper('chooseBoost', {n: n});
        },

        selectCarPos: function(evt) {

            dojo.stopEvent(evt);

            this.gamedatas.gamestate.descriptionmyturn = _('To complete your movement, ${you} have to decide where your car should be pointing');
            this.updatePageTitle();

            this.gamedatas.gamestate.args.positions = this.gamedatas.gamestate.args.positions[parseInt(evt.target.dataset.posIndex)]

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
            
            var black = rotation['black'];
            var pos = rotation['coordinates'];
            var rotation = rotation['rotation'];

            if (black) {
                dojo.place( this.format_block('jstpl_token', {type: 'tire'}), 'tokens');

                $('tokens').lastChild.className += ' selOctToken';
                
                //debugger
                $('tokens').lastChild.style.cssText = `
                    left: ${pos.x}px;
                    top: ${-pos.y - this.octSize/2}px;`;
            }

            const playerCarTransform = $('car_'+this.gamedatas.players[this.getActivePlayerId()].color).style.transform;

            $('car_preview').style.transform = playerCarTransform + 'rotate('+rotation*-45+'deg)';
        },

        // handles user click on a direction arrow when choosing the car orientation at the end of the movement phase
        confirmCarRotation: function(evt) {
            dojo.stopEvent(evt);

            var dir = this.gamedatas.gamestate.args.positions['directions'][parseInt(evt.target.dataset.posIndex)]['direction'];
            var pos =  this.gamedatas.gamestate.args.positions['position'];

            $('pos_highlights').innerHTML = '';
            $('previews').innerHTML = '';
            $('dirArrows').innerHTML = '';
            $('car_preview').remove();

            this.ajaxcallwrapper('placeCar',{ pos: pos, dir: dir});
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
        },  

        // --- HANDLERS ---
        
        notif_logger: function(notif) {
            console.log(notif.args);
        },

        notif_placeFirstCar: function(notif) {
            this.carFirstPlacement(notif.args.player_id, notif.args.x, notif.args.y);
        },

        notif_chooseTokensAmount: function(notif) {
            this.updatePlayerTokens(notif.args.player_id, notif.args.tire, notif.args.nitro);
            if(this.isCurrentPlayerActive) $('tokenSelectionWindow').style.height = '0px'
        },

        notif_selectPosition: function(notif) {
            if (!this.isCurrentPlayerActive()) this.carFirstPlacement(notif.args.player_id, notif.args.posX, notif.args.posY);
        },

        notif_placeGearVector: function(notif) {

            if (!this.isCurrentPlayerActive()) {

                this.createGameElement('gearVector',{ n: notif.args.gear });

                var pb = this.getPlayerBoardCoordinates(notif.args.player_id);
                this.placeOnTrack('gear_'+notif.args.gear, pb.x, pb.y, notif.args.direction);
                this.slideOnTrack('gear_'+notif.args.gear, notif.args.x, notif.args.y);

            }

            this.updatePlayerTokens(notif.args.player_id, notif.args.tireTokens, 0);



            if (notif.args.tireTokens < 0) {

                /* $('log_'+notif.move_id).innerHTML = $('log_'+notif.move_id).innerHTML.replace('TT', this.format_block('jstpl_token',{type:'tire'}));
                

                this.iconize(document.querySelector('.log .token'),20,250); */

                /* var el = document.querySelector('.pbIcon.tireToken').parentElement;
                console.log(el);
                console.log('HELLOOOO???');
                
                $('log_'+notif.move_id).innerHTML = $('log_'+notif.move_id).innerHTML.replace('TT', el.outerHTML); */
            
            }
        },

        notif_useBoost: function(notif) {

            this.updatePlayerTokens(notif.args.player_id, 0, notif.args.nitroTokens);
        },

        notif_chooseBoost: function(notif) {
            
            if (!this.isCurrentPlayerActive()) {

                this.createGameElement('boostVector',{ n: notif.args.n });

                var pb = this.getPlayerBoardCoordinates(notif.args.player_id);
                this.placeOnTrack('boost_'+notif.args.n, pb.x, pb.y, notif.args.direction);
                this.slideOnTrack('boost_'+notif.args.n, notif.args.vecX, notif.args.vecY);
            }
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

            this.updatePlayerTokens(notif.args.player_id, notif.args.tireTokens, 0);
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

        //#endregion
   
    });             
});
