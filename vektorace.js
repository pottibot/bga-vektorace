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

            // useful measures to rescale octagons and calculate distances between them. actually they should be never used, all geometric calculation should be done by server.
            // these measure should be set always using setOctagonSize(size), which given a certain octagon size (length of one side of the square box that contains the octagon), it calculates all deriving measures
            this.octSize; // length of the side of the square box containing the octagon
            this.octSide; // length of each side of the octagon
            this.octRad; // radius of the circle that inscribe the octagon. or distance between octagon center and any of its vertecies
            this.octSeg; // segment measuring half of the remaining length of box size, minus the length of the octagon side. or the cathetus of the right triangle formed on the diagonal sides of the octagon.

            // keeps track of the current scale of the interface
            this.interfaceScale;
            this.zoomLimit;

            // init counters object
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

            for (let player_id in gamedatas.players) {
                let player = gamedatas.players[player_id];
                this.counters.playerBoard[player_id] = {};
                
                // create all icon elements
                let player_board_div = $('player_board_'+player_id);
                dojo.place( this.format_block('jstpl_player_board', {
                    id: player_id,
                    gear: this.format_block('jstpl_current_gear', { id: player_id, n: player['currGear']}),
                    lap: this.format_block('jstpl_lap_counter', { id: player_id,}),
                    standings: this.format_block('jstpl_standings_position', { id: player_id}),
                    tire: this.format_block('jstpl_tokens_counter', { id: player_id, type: 'tire'}),
                    nitro: this.format_block('jstpl_tokens_counter', { id: player_id, type: 'nitro'})
                } ), player_board_div );

                // create and initiate counter for each counting icon
                document.querySelectorAll(`#itemsBoard_${player_id} .pbCounter`).forEach( el => {
                    let counter = new ebg.counter();
                    counter.create(el);

                    let propertyName = el.id.substring(0,el.id.indexOf('_'));
                    counter.setValue(player[propertyName]);

                    this.counters.playerBoard[player_id][propertyName] = counter; // store counter in global object
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

            // -- SET INITIAL INTERFACE SCALE --
            this.interfaceScale = 3
            this.zoomLimit = false;
            this.scaleInterface();

            // -- DIALOG WINDOW INIT --
            // (copied from doc)
            this.gearSelDW = new ebg.popindialog();
            this.gearSelDW.create( 'GearSelectionDialogWindow' );
            this.gearSelDW.setTitle( _("Select a gear vector to declare") );
            this.gearSelDW.setMaxWidth( 600 );

            // -- PLACE TABLE ELEMENTS ACCORDING TO DB --
            for (let i in gamedatas.game_element) {
                let el = gamedatas.game_element[i];
                
                switch (el.entity) {

                    case 'pitwall':
                        let pw = this.createGameElement('pitwall');
                        this.placeOnTrack(pw, el.pos_x, el.pos_y, el.orientation);
                        pw.style.transform += 'scale(0.75)';
                        break;

                    case 'curve':
                        let cur = this.createGameElement('curve', {n: el.id});
                        this.placeOnTrack(cur, el.pos_x, el.pos_y, el.orientation);

                        break;

                    case 'car':
                        let car = this.createGameElement('car', {color: gamedatas.players[el.id].color});

                        if (el.pos_x && el.pos_y) this.placeOnTrack(car, el.pos_x, el.pos_y, el.orientation);
                        else {
                            this.placeOnTrack(car, 0, 0, el.orientation);
                            car.style.display = 'none';
                        }

                        break;

                    case 'gearVector':
                        let gv = this.createGameElement('gearVector', {n: el.id});
                        this.placeOnTrack(gv, el.pos_x, el.pos_y, el.orientation);

                        break;

                    case 'boostVector':
                        let bv = this.createGameElement('boostVector', {n: el.id});
                        this.placeOnTrack(bv, el.pos_x, el.pos_y, el.orientation);

                        break;
                    
                    default:
                        console.log('Unidentified Non-Flying Object');
                        break;
                }
            }

            // -- CONNECT USER INPUT --
            document.querySelector('#map_container').addEventListener('mousewheel',(evt) => {
                // format input wheel delta and calls method to scale interface accordingly
                // ! MAY VARY ON LAPTOPS AND TOUCH DEVICES !
                dojo.stopEvent(evt);

                // get coordinates before scaling
                let coordsBeforeScale = this.trackCoordsFromPointerEvt(evt);

                // scale interface
                scaleDirection = evt.wheelDelta / 120;
                let scalestep = this.interfaceScale - scaleDirection;

                // if scalestep within certain interval
                if ((scalestep >= 0 && scalestep < 7) || !this.zoomLimit) {
                    this.interfaceScale = scalestep;
                    this.scaleInterface();

                    // get coordinates of pointer after scale
                    let coordsAfterScale = this.trackCoordsFromPointerEvt(evt);

                    // calc delta and scale it by interface scale
                    let scrollDelta = {
                        x: (coordsBeforeScale.x - coordsAfterScale.x)*Math.pow(0.8,this.interfaceScale),
                        y: (coordsBeforeScale.y - coordsAfterScale.y)*Math.pow(0.8,this.interfaceScale)
                    }
            
                    /* console.log('coords before scale',coordsBeforeScale);
                    console.log('coords after scale',coordsAfterScale);
                    console.log('coords scale delta',scrollDelta); */
                    
                    // scroll map
                    this.scrollmap.scroll(-scrollDelta.x, scrollDelta.y,0,0);
                }
            }); // zoom wheel
            document.querySelector('#map_container').addEventListener('click',evt => this.trackCoordsFromPointerEvt(evt));
 
            // -- SETUP ALL NOTIFICATION --
            this.setupNotifications();

            // -- SETUP PREFERENCES HANDLERS --
            document.querySelectorAll('#pref_illegalPos input').forEach((el) => {
                el.addEventListener('change', (evt) => {
                    document.documentElement.style.setProperty('--display-illegal', evt.target.value);
                })
            });

            document.querySelectorAll('#pref_mapGrid input').forEach((el) => {
                el.addEventListener('change', (evt) => {
                    console.log(evt.target.value);
                    document.documentElement.style.setProperty('--track-source-top-left', "url(img/track_"+evt.target.value+"/top-left.jpg)");
                    document.documentElement.style.setProperty('--track-source-top-right', "url(img/track_"+evt.target.value+"/top-right.jpg)");
                    document.documentElement.style.setProperty('--track-source-bottom-left', "url(img/track_"+evt.target.value+"/bottom-left.jpg)");
                    document.documentElement.style.setProperty('--track-source-bottom-right', "url(img/track_"+evt.target.value+"/bottom-right.jpg)");
                })
            });

            document.querySelectorAll('#pref_mapOpacity input').forEach((el) => {
                el.addEventListener('change', (evt) => {
                    document.documentElement.style.setProperty('--track-opacity', evt.target.value / 100);
                })
            });

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

            $('previews').style.display = (this.isCurrentPlayerActive())? '' : 'none';
            
            switch(stateName) {

                case 'firstPlayerPositioning':

                    // place positioning area as continuation of pitlane line
                    dojo.place( this.format_block('jstpl_posArea'), 'pos_highlights' );
                    this.placeOnTrack('start_positioning_area',args.args.anchorPos.x,args.args.anchorPos.y,0);
                    
                    $('start_positioning_area').style.transformOrigin = 'bottom left'
                    $('start_positioning_area').style.transform = `translate(0,-100%) rotate(${args.args.rotation*45}deg)`;

                    // connect it to input handlers
                    if(!this.isCurrentPlayerActive()) return;

                    $('start_positioning_area').addEventListener('click', evt => {
                        dojo.stopEvent(evt);

                        this.ajaxcallwrapper('placeFirstCar', {
                            x: parseInt($('car_preview').style.left),
                            y: -(parseInt($('car_preview').style.top))
                        }, null, '#start_positioning_area');
                    })

                    dojo.query('#start_positioning_area').connect('mousemove',this,'previewStartCarPos');

                    $('start_positioning_area').addEventListener('mouseleave', evt => {
                        dojo.stopEvent(evt);
                        dojo.empty('previews')
                    });

                    break;

                case 'flyingStartPositioning':

                    let askForRef = args.descriptionmyturn; //  original descritipion asks to click on reference car
                    let askForPos = _('${you} must choose a starting position');

                    // set anchor elements on reference cars
                    args.args.positions.forEach(refcar => {

                        if (refcar.hasValid) {

                            let player = refcar.carId;
                            let pos = refcar.coordinates;

                            dojo.place(
                                this.format_block('jstpl_refCarAnchor',{ car: player }),
                                'car_highlights'
                            );

                            this.placeOnTrack('refCar_'+player, pos.x, pos.y);
                        }
                    });

                    this.gamedatas.gamestate.args.refCar = '';

                    // connect each anchor to handler
                    document.querySelectorAll('.refCarAnchor').forEach(el => el.addEventListener('click', evt => {

                        dojo.stopEvent(evt);

                        let refId = evt.target.id.split('_').pop(); // extract car id from anchor id
                        
                        // clean interface from previously elements added by this handler
                        $('pos_highlights').innerHTML = '';
                        document.querySelectorAll('.fsOctagon').forEach( el => el.remove());

                        // if clicked ref is same as before, clear stored refCar id and return
                        if (refId == this.gamedatas.gamestate.args.refCar) this.gamedatas.gamestate.args.refCar = '';
                        else { // display all fs pos

                            this.gamedatas.gamestate.args.refCar = refId; // set new current reference

                            // find refCar object inside args
                            let refCar = args.args.positions.filter(ref => ref.carId == refId).pop();
                                    
                            // make array with all selOct pos and call method to display them
                            let positions = [];
                            refCar.positions.forEach(element => {
                                positions.push(element.coordinates);
                            });

                            this.displaySelectionOctagons(positions);
                            document.querySelectorAll(`.selectionOctagon`).forEach( el => {
                                if (!refCar.positions[el.dataset.posIndex].valid) {
                                    el.className = el.className.replace('standardPos','illegalPos');
                                }
                            });

                            // display fs octagon too
                            refCar.FS_octagons.forEach((oct,i) => {
                                dojo.place(this.format_block('jstpl_FS_octagon'),'track');

                                el = $('track').lastElementChild;

                                el.style.left = oct.x +'px';
                                el.style.top = -oct.y +'px';

                                el.style.transform = $('car_'+this.gamedatas.players[this.getActivePlayerId()].color).style.transform;
                                el.style.transform += `rotate(${(i+1)*-45}deg)`;
                            });

                            // finally connect all pos to handlers
                            this.connectPosHighlights(
                                evt => this.ajaxcallwrapper('placeCarFS', {
                                    ref: this.gamedatas.gamestate.args.refCar,
                                    pos: evt.target.dataset.posIndex},
                                null, '.selectionOctagon'),
                                'previewCarPos'
                            );
                        }

                        // update page title depending on action (choose ref car or choose car pos  )
                        if (this.gamedatas.gamestate.args.refCar == '') {
                            this.gamedatas.gamestate.descriptionmyturn = askForRef;
                            this.updatePageTitle();
                        } else {
                            this.gamedatas.gamestate.descriptionmyturn = askForPos;
                            this.updatePageTitle();
                        }
                    }));

                    // if there's only one reference car, pre-click on it
                    if (document.querySelectorAll('.refCarAnchor').length == 1) document.querySelector('.refCarAnchor').click();
                            
                    break;         
                
                case 'tokenAmountChoice':
                
                    if(!this.isCurrentPlayerActive()) return;
                    
                    let baseTire = parseInt(args.args.tire);
                    let baseNitro = parseInt(args.args.nitro);

                    // func that creates and displays window to select token amount
                    this.displayTokenSelection(baseTire,baseNitro, args.args.amount);
                    this.addActionButton('confirmTokenAmount', _('Confirm'), () => {

                        this.ajaxcallwrapper('chooseTokensAmount',{ tire: this.gamedatas.gamestate.args.tire, nitro: this.gamedatas.gamestate.args.nitro});
                        
                    }, null, false, 'blue');

                    if (baseTire == 0 && baseNitro == 0) {
                        document.querySelectorAll('.tokenIncrementer > input').forEach( el => el.value = 4);
                        this.gamedatas.gamestate.args.tire = 4;
                        this.gamedatas.gamestate.args.nitro = 4;
                    }
                    break;

                case 'greenLight':

                    if(!this.isCurrentPlayerActive()) return;

                    // add putton that displays vector selection in 'green light' mode
                    this.addActionButton('showGearSelDialogButton', _('show selection'), () => {
                        this.displayGearSelDialog(args.args.gears);
                    }, null, false, 'blue');

                    $('showGearSelDialogButton').click();
                    
                    break;
                
                case 'gearVectorPlacement':

                    // push all positions coordinates to array and pass it to method to display selection octagons for each pos
                    let vecAllPos = [];
                    args.args.positions.forEach(pos => {
                        vecAllPos.push(pos.anchorCoordinates);
                    })

                    this.displaySelectionOctagons(vecAllPos); // display vector attachment position in front of the car
                    this.connectPosHighlights(
                        // click handler
                        evt => {
                            dojo.stopEvent(evt);
                            this.ajaxcallwrapper('placeGearVector', {
                                pos: args.args.positions[parseInt(evt.target.dataset.posIndex)]['position']
                            }, null, '.selectionOctagon');
                        },

                        // mouseover handler
                        evt => {
                            dojo.stopEvent(evt);

                            let currGear = args.args.gear;
                            let gv = this.createGameElement('gearVector', {n: currGear}, 'previews');

                            let pos = args.args.positions[parseInt(evt.target.dataset.posIndex)]['vectorCoordinates'];
                            this.placeOnTrack(gv, pos.x, pos.y, args.args.direction);
                        }
                    );

                    // add special properties to selection octagons
                    document.querySelectorAll('#pos_highlights > .selectionOctagon').forEach((selOct) => {
                        let i = selOct.dataset.posIndex;
                        let pos = args.args.positions[i];

                        if (!pos.legal || !pos.carPosAvail || pos.offTrack) {
                            selOct.className = selOct.className.replace('standardPos','illegalPos');
                        } else {
                            if (pos.denied) {
                                selOct.className = selOct.className.replace('standardPos','deniedPos');
                            } else if (pos.tireCost) {
                                selOct.className = selOct.className.replace('standardPos','tirePos');
                            };
                        }
                    });

                    // if no pos is available, show brake button
                    if (!args.args.hasValid && this.isCurrentPlayerActive()) {
                        this.addActionButton(
                            'emergencyBrake_button', _('Emergency Brake'), () => { this.ajaxcallwrapper('brakeCar') },
                            null, false, 'red'
                        );
                        this.addTooltip(
                            'emergencyBrake_button',
                            _("This action is available when you cannot position your gear vector in any legal way"),
                            _("By performing an emergency brake, you downshift gear until its vector can be placed in a legal position, spending 1 Tire Token for each shifted gear.\
                               If no gear can fit in the space available, you will be forced to stop your car, ending your current turn. You may choose to rotate your car by 45 degrees after this action. Next turn, will restart the car using the 1st gear")
                        );

                        if (args.args.canGiveWay) {
                            this.addActionButton(
                                'giveWay_button', _('Give way'), () => { this.ajaxcallwrapper('giveWay') },
                                null, false, 'blue'
                            );
                            this.addTooltip(
                                'giveWay_button',
                                _("This action is available when an opponent that has not fully overtook you is blocking your path, preventing any legal gear vector position"),
                                _("By giving way, you will temporarily pause your turn and let your opponent move first. You won't be able to perform any attack maneuver after resuming your turn.")
                            );

                            /* When cannot position you gear vector in any legal way because an opponent is obstructing the passage and hasn\'t moved yet */
                            
                            /* an opponent has not overtaken you in the turn order but it is somehow in front of you and obstructing your passage */
                        }
                    }

                    break;

                case 'emergencyBrake':

                    if(!this.isCurrentPlayerActive()) return;
                    
                    this.displayDirectionArrows(args.args.directionArrows, args.args.direction);

                    document.querySelectorAll('#pos_highlights > *').forEach (el => {
                        el.addEventListener('click', evt => {
                            dojo.stopEvent(evt);
                            this.ajaxcallwrapper('rotateAfterBrake',{dir:evt.target.dataset.posIndex}, null, '.selectionOctagon')
                        });
                        el.addEventListener('mouseenter', evt => {
                            dojo.stopEvent(evt);
                            let car = $('car_'+this.gamedatas.players[this.getActivePlayerId()].color);
                            let rot = evt.target.dataset.posIndex-1
                            car.style.transform += `rotate(${rot*-45}deg)`;
                        });
                        el.addEventListener('mouseleave', evt => {
                            dojo.stopEvent(evt);
                            let car = $('car_'+this.gamedatas.players[this.getActivePlayerId()].color);
                            let rot = evt.target.dataset.posIndex-1
                            car.style.transform += `rotate(${rot*45}deg)`;
                        });
                    });

                    break;

                case 'boostPrompt':

                    if (!this.isCurrentPlayerActive()) return;

                    // use button
                    this.addActionButton(
                        'useBoost_button',
                        _('Use Boost')+' -1 '+this.format_block('jstpl_token',{type:'nitro'}),
                        () => this.ajaxcallwrapper('useBoost', {use: true}),
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
                        () => this.ajaxcallwrapper('useBoost', {use: false}),
                        null, false, 'gray');

                    break;
                
                case 'boostVectorPlacement':

                    // works similarly to gearVectorPlacement

                    let boostAllPos = [];
                    args.args.positions.forEach(pos => {
                        boostAllPos.push(pos.vecTopCoordinates);
                    })

                    this.displaySelectionOctagons(boostAllPos);
                    this.connectPosHighlights(
                        evt => {
                            dojo.stopEvent(evt);

                            let n = args.args.positions[parseInt(evt.target.dataset.posIndex)]['length'];
                            this.ajaxcallwrapper('placeBoostVector', {n: n}, null, '.selectionOctagon');
                        },
                        evt => {
                            dojo.stopEvent(evt);

                            let n = args.args.positions[parseInt(evt.target.dataset.posIndex)]['length'];
                            let pos = args.args.positions[parseInt(evt.target.dataset.posIndex)]['vecCenterCoordinates'];

                            let bv = this.createGameElement('boostVector', {n: n}, 'previews');
                            this.placeOnTrack(bv, pos.x, pos.y, args.args.direction);
                        });

                    document.querySelectorAll('#pos_highlights > .selectionOctagon').forEach((selOct) => {
                        let i = selOct.dataset.posIndex;
                        let pos = args.args.positions[i];

                        if (!pos.legal || !pos.carPosAvail) {
                            selOct.className = selOct.className.replace('standardPos','illegalPos');
                        }
                    });

                    break;

                case 'carPlacement':

                    // also works similarly to gearVectorPlacement, but has different data structure and is divided in two interface-only mini phases
                    // -> THIS MEANS THAT A PAGE RELOAD DURING SECOND MINI-PHASE WILL RESET WHOLE PHASE. DOESN'T HAVE IMPLICATION ON GAME AS ONLY DIRECTION ARROWS ARE REVEALED IN SECOND MINI PHASE
                    // MAYBE ADD POSSIBILITY OF CHANGING CAR POS? IT IS POSSIBLE TO RESET IT ANYWAY BY RELOADING PAGE

                    let carAllPos = [];
                    args.args.positions.forEach(pos => {
                        carAllPos.push(pos.coordinates);
                    })

                    this.displaySelectionOctagons(carAllPos);
                    this.connectPosHighlights('selectCarPos','previewCarPos');

                    document.querySelectorAll('#pos_highlights > .selectionOctagon').forEach((selOct) => {
                        let i = selOct.dataset.posIndex;
                        let pos = args.args.positions[i];

                        if (!pos.legal || pos.offTrack) {
                            selOct.className = selOct.className.replace('standardPos','illegalPos');
                        } else {
                            if (pos.tireCost) {
                                selOct.className = selOct.className.replace('standardPos','tirePos');
                            } else if (pos.denied) {
                                selOct.className = selOct.className.replace('standardPos','deniedPos');
                            }
                        }
                    });

                    break;              
                
                case 'attackManeuvers':

                    // works somewhat similarly to fs start phase, with two steps selection: first of attacked enemy, then of attack maneuver
    
                    // if no att mov avail, display temp page title, before animation end and game jumps state
                    if (!args.args.canAttack || !args.args.attMovsAvail) {
                        $('pagemaintitletext').childNodes[1].textContent = _(' cannot perform any vaild attack maneuver this turn');
                        if (args.args.attEnemies.length == 0) return;
                    } else {
                        if (this.isCurrentPlayerActive()) this.addActionButton('attMov_skip', _('Skip'), () => this.ajaxcallwrapper('skipAttack'), null, false, 'gray');
                    }

                    // init temp property to trace what enemy attack maneuver are currently displayed
                    args.args.currEnemy = '';

                    let askForEnemy = args.descriptionmyturn;
                    let askForMov = _('${you} may now choose which attack maneuver to perform');

                    /* // place car rectangle to better visualize if car check conditions
                    dojo.place(
                        this.format_block('jstpl_carRect',{
                            id: this.getActivePlayerId(),
                            w: args.args.playerCar.size.width,
                            h: args.args.playerCar.size.height
                        }),
                        'car_highlights'
                    );
                    $('carRect_'+this.getActivePlayerId()).style.transform = 'translate(-50%,-50%)';
                    this.placeOnTrack('carRect_'+this.getActivePlayerId(),args.args.playerCar.pos.x,args.args.playerCar.pos.y,args.args.playerCar.dir); */

                    let validTargets = 0;
                    let lastValid;
                    // place an anchor for each close enemy in front (even if no attack mov avail. coz player would want to visualize attackable areas anyway?)
                    args.args.attEnemies.forEach(enemy => {
                        if (args.args.canAttack) {
                            if (enemy.hasValidMovs) {
                                validTargets++;
                                lastValid = enemy.id;
                            }

                            dojo.place(
                                this.format_block('jstpl_refCarAnchor',{ car: enemy.id }),
                                'car_highlights'
                            );

                            this.placeOnTrack('refCar_'+enemy.id, enemy.coordinates.x, enemy.coordinates.y);
                        }
                    });

                    // for each anchor, add click handler
                    document.querySelectorAll('.refCarAnchor').forEach(ref => ref.addEventListener('click', evt => {

                        dojo.stopEvent(evt);

                        $('pos_highlights').innerHTML = '';
                        if (document.querySelector('.draftingMeter')) document.querySelector('.draftingMeter').remove();

                        const enemy = args.args.attEnemies.filter(enemy => enemy.id == evt.target.id.split('_').pop()).pop();

                        // change page title depending on wether att movs are displayed
                        if (args.args.canAttack && args.args.attMovsAvail) {
                            if (args.args.currEnemy == enemy.id) {
                                args.args.currEnemy = '';

                                this.gamedatas.gamestate.descriptionmyturn = askForEnemy;
                                this.updatePageTitle();

                                if (this.isCurrentPlayerActive()) this.addActionButton('attMov_skip', _('Skip'), () => this.ajaxcallwrapper('skipAttack'), null, false, 'gray');
                                return;
                            } else {
                                args.args.currEnemy = enemy.id;

                                this.gamedatas.gamestate.descriptionmyturn = askForMov;
                                this.updatePageTitle();

                                if (this.isCurrentPlayerActive()) this.addActionButton('attMov_skip', _('Skip'), () => this.ajaxcallwrapper('skipAttack'), null, false, 'gray');
                            }
                        }
                        
                        for (const movName in enemy.maneuvers) {
                            
                            let mov = enemy.maneuvers[movName];
                            let el;

                            switch (movName) {
                                case 'drafting':
                                    let df = this.createGameElement('draftingMeter',{},'track');
                                    this.placeOnTrack(df, mov.vecPos.x, mov.vecPos.y, mov.vecDir);

                                    el = this.createGameElement('selOctagon',{i: 0, x: mov.catchPos.x, y: mov.catchPos.y},'pos_highlights');
                                    this.placeOnTrack(el, mov.catchPos.x, mov.catchPos.y);

                                    this.addTooltipHtml(el.id,
                                        `
                                        <h3>${_('Drafting')}</h3>
                                        <p>${_("Take the slipstream and position behind your opponent's car")}</p>
                                        `
                                    );

                                    el.style.opacity = 0;

                                    if (!mov.active || !mov.legal) df.style.filter = 'brightness(0.2) opacity(0.25)';

                                    el.addEventListener('click', evt => {
                                        dojo.stopEvent(evt);

                                        this.ajaxcallwrapper('engageManeuver',{
                                            enemy: enemy.id,
                                            maneuver: movName
                                        }, null, '#pos_highlights > *')
                                    });

                                    if (mov.active && mov.legal) {
                                        el.addEventListener('mouseenter', evt => {
                                            dojo.stopEvent(evt);
                                            this.placeOnTrack(this.createPreviewCar(), mov.attPos.x, mov.attPos.y);
                                            if (mov.active && mov.legal) df.style.filter = 'drop-shadow(0px 0px 10px red)';
                                        });
                                        el.addEventListener('mouseleave', evt => {
                                            dojo.stopEvent(evt);
                                            $('car_preview').remove();
                                            if (mov.active && mov.legal) df.style.filter = '';
                                        });
                                    }
                                    break;

                                case 'slingshot':
                                    if (mov.active && mov.legal) {
                                        mov.attPos.forEach((pos,i) => {

                                            el = this.createGameElement('selOctagon',{i: i, x: pos.pos.x, y: pos.pos.y},'pos_highlights');
                                            this.placeOnTrack(el, pos.pos.x, pos.pos.y);

                                            this.addTooltipHtml(el.id,
                                                `
                                                <h3>${_('Slingshot')}</h3>
                                                <p>${_("Overtake your opponent by utilizing drafting momentum. Costs 1 Nitro Token")}</p>
                                                `
                                            );

                                            el.className = el.className.replace('standardPos', (pos.valid)? 'nitroPos' : 'illegalPos');
                                            
                                            el.addEventListener('click', evt => {
                                                dojo.stopEvent(evt);

                                                this.ajaxcallwrapper('engageManeuver',{
                                                    enemy: enemy.id,
                                                    maneuver: movName,
                                                    posIndex: evt.target.dataset.posIndex
                                                }, null, '#pos_highlights > *');
                                            });
                                            el.addEventListener('mouseenter', evt => {
                                                dojo.stopEvent(evt);

                                                let pos = (movName == 'slingshot')? this.selOctagonPos(evt.target) : mov.attPos;
                                                this.placeOnTrack(this.createPreviewCar(), pos.x, pos.y);
                                            });
                                            el.addEventListener('mouseleave', evt => {
                                                dojo.stopEvent(evt);

                                                $('car_preview').remove();
                                            });
                                        });
                                    }
                                    break;

                                default:
                                    el = this.createGameElement('selOctagon',{i: 0, x: mov.attPos.x, y: mov.attPos.y},'pos_highlights');
                                    this.placeOnTrack(el, mov.attPos.x, mov.attPos.y);

                                    if (!mov.active) el.className = el.className.replace('standardPos', 'unactivePos');
                                    else if (!mov.legal) el.className = el.className.replace('standardPos', 'illegalPos');

                                    el.addEventListener('click', evt => {
                                        dojo.stopEvent(evt);
                                        
                                        this.ajaxcallwrapper('engageManeuver',{
                                            enemy: enemy.id,
                                            maneuver: movName
                                        }, null, '#pos_highlights > *')
                                    });

                                    if (mov.active) {
                                        el.addEventListener('mouseenter', evt => {
                                            dojo.stopEvent(evt);
                                            this.placeOnTrack(this.createPreviewCar(), mov.attPos.x, mov.attPos.y);
                                        });
                                        el.addEventListener('mouseleave', evt => {
                                            dojo.stopEvent(evt);
                                            $('car_preview').remove();
                                        });
                                    }

                                    switch (movName) {
                                        case 'push':
                                            this.addTooltipHtml(el.id,
                                                `
                                                <h3>${_('Push')}</h3>
                                                <p>${_("Push your opponent from behind. They won't be able to downshift gear next turn")}</p>
                                                `
                                            );
                                            break;
                                    
                                        case 'leftShunk':
                                            this.addTooltipHtml(el.id,
                                                `
                                                <h3>${_('Left Shunk')}</h3>
                                                <p>${_("Shunk your opponent from the left side. They won't be able to choose positions on the left for their vector or their car")}</p>
                                                `
                                            );
                                            break;

                                        case 'rightShunk':
                                            this.addTooltipHtml(el.id,
                                                `
                                                <h3>${_('Right Shunk')}</h3>
                                                <p>${_("Shunk your opponent from the right side. They won't be able to choose positions on the right for their vector or their car")}</p>
                                                `
                                            );
                                            break;
                                    }
                                    break;
                            }
                        }
                    }));

                    if (document.querySelectorAll('.refCarAnchor').length == 1) document.querySelector('.refCarAnchor').click();
                    else if (validTargets == 1) document.querySelector('#refCar_'+lastValid).click();

                    break;

                case 'boxBoxPromt':
                    if (!this.isCurrentPlayerActive()) return;

                    // use button
                    this.addActionButton(
                        'boxbox_button',
                        _('BoxBox!'),
                        () => this.ajaxcallwrapper('boxBox'),
                        null, false, 'red'
                    );

                    this.addTooltip(
                        'boxbox_button',
                        _("This action is available when you pass the last curve and are parallel to the pitwall"),
                        _("By calling \"BoxBox!\", you declare your intention to stop by the pit box to refill your tokens.\
                         When doing so, you gain immunity from enemy attacks but you cannot perform any attack maneuvers either. You are also restricted from using boost vectors.\
                         After calling \"BoxBox!\" you must stop by the pit-box.")
                    );
        
                    /* // style button in a cool way
                    $('useBoost_button').style.cssText = `color: #eb6b0c;
                                                          background: #fed20c;
                                                          borderColor: #f7aa16`; */
                    
                    // skip button
                    this.addActionButton(
                        'skipBoost_button',
                        _("Skip"),
                        () => this.ajaxcallwrapper('boxBox', {skip: true}),
                        null, false, 'gray'
                    );

                    break;
                    
                case 'pitStop': {

                    if(!this.isCurrentPlayerActive()) return;
                    
                    let baseTire = parseInt(args.args.tire);
                    let baseNitro = parseInt(args.args.nitro);

                    // func that creates and displays window to select token amount
                    this.displayTokenSelection(baseTire,baseNitro, args.args.amount);
                    this.addActionButton('confirmTokenAmount', _('Confirm'), () => {

                        this.ajaxcallwrapper('chooseTokensAmount',{ tire: this.gamedatas.gamestate.args.tire, nitro: this.gamedatas.gamestate.args.nitro, pitStop: true});
                        
                        
                    }, null, false, 'blue');

                    break;

                }
                
                case 'futureGearDeclaration':

                    if(!this.isCurrentPlayerActive()) return;

                    // display button to open gear selection dialog window in standard mode.
                    this.addActionButton('showGearSelDialogButton', _('show selection'), () => {
                        this.displayGearSelDialog(args.args.gears);
                    }, null, false, 'blue');

                    $('showGearSelDialogButton').click();
                    
                    break;

                case 'dummmy':
                    break;
            }
        },

        // onLeavingState: equivalent of onEnteringState(...) but needed to perform UI changes before exiting a game state
        onLeavingState: function(stateName) {
            console.log('Leaving state: '+stateName);

            switch(stateName) {

                case 'firstPlayerPositioning':
                    $('pos_highlights').innerHTML = '';
                    break;

                case 'flyingStartPositioning': 
                    document.querySelectorAll('.fsOctagon').forEach( el => el.remove());

                    $('pos_highlights').innerHTML = '';
                    $('car_highlights').innerHTML = '';
                    $('previews').innerHTML = '';
                    break;

                case 'tokenAmountChoice':
                    if (this.isCurrentPlayerActive()) {
                        $('tokenSelectionWindow').style.height = '0px';
                        $('tokenSelectionWindow').ontransitionEnd = () => {$('tokenSelectionWindow').remove()}
                    }
                    break;

                case 'greenLight':
                    break;

                case 'nextPlayer': 
                    document.querySelectorAll('.turnPosIndicator').forEach( el => el.remove());
                    break;

                case 'gearVectorPlacement':
                    $('pos_highlights').innerHTML = '';
                    $('car_highlights').innerHTML = '';
                    $('previews').innerHTML = '';
                    break;

                case 'emergencyBrake':
                    $('pos_highlights').innerHTML = '';
                    $('car_highlights').innerHTML = '';
                    $('previews').innerHTML = '';
                    $('dirArrows').innerHTML = '';
                    break;

                case 'boostPrompt':
                    break;

                case 'boostVectorPlacement':
                    $('pos_highlights').innerHTML = '';
                    $('car_highlights').innerHTML = '';
                    $('previews').innerHTML = '';
                    break;

                case 'carPlacement':
                    $('pos_highlights').innerHTML = '';
                    $('car_highlights').innerHTML = '';
                    $('previews').innerHTML = '';
                    $('dirArrows').innerHTML = '';

                    if ($('car_preview')) $('car_preview').remove();

                    break;
           
                case 'attackManeuvers':
                    $('pos_highlights').innerHTML = '';
                    $('car_highlights').innerHTML = '';
                    $('previews').innerHTML = '';
                    if (document.querySelector('.draftingMeter')) document.querySelector('.draftingMeter').remove();
                    break;

                case 'futureGearDeclaration':
                    break;

                case 'pitStop': 
                    if (this.isCurrentPlayerActive()) {
                        $('tokenSelectionWindow').style.height = '0px';
                        $('tokenSelectionWindow').ontransitionEnd = () => {$('tokenSelectionWindow').remove()}
                    }
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

            let ret = {
                x: parseInt($('findPlayerBoard').style.left) / Math.pow(0.8,this.interfaceScale),
                y: -parseInt($('findPlayerBoard').style.top) / Math.pow(0.8,this.interfaceScale)
            }

            dojo.destroy($('findPlayerBoard'));

            return ret;
        },

        // useful method copied from wiki + some modification
        ajaxcallwrapper: function(action, args, handler = null, lockElementsSelector = null) { // lockElementsSelector allows to block pointer events of the selected elements while ajaxcall is sent (so that previews won't show)
            if (!args) args = []; // this allows to skip args parameter for action which do not require them

            /* if (!handler && lockElementsSelector) {
                if (this.isCurrentPlayerActive()) document.querySelectorAll(lockElementsSelector).forEach( el => el.style.pointerEvents = 'none');

                handler = (is_error) => {
                    if (is_error) document.querySelectorAll(lockElementsSelector).forEach( el => el.style.pointerEvents = '');
                }
            } else if (!handler) handler = (is_error) => {}; */
            if (!handler) handler = (is_error) => {};

            let lockFunc = () => {};
            if (lockElementsSelector) {
                if (this.isCurrentPlayerActive()) document.querySelectorAll(lockElementsSelector).forEach( el => el.style.pointerEvents = 'none');

                lockFunc = (is_error) => {
                    if (is_error) document.querySelectorAll(lockElementsSelector).forEach( el => el.style.pointerEvents = '');
                }
            }
                
            args.lock = true; // this allows to avoid rapid action clicking which can cause race condition on server

            if (this.checkAction(action)) { // this does all the proper check that player is active and action is declared
                
                this.ajaxcall("/" + this.game_name + "/" + this.game_name + "/" + action + ".html", args, // this is mandatory fluff 
                    this, (result) => { },  // success result handler is empty - it is never needed
                    (is_error) => { handler(is_error); lockFunc(is_error); }); // this is real result handler - it called both on success and error, it has optional param  "is_error" - you rarely need it
                }
        },

        // takes pointer event and return coordinates relative to main track
        trackCoordsFromPointerEvt: function(evt) {
            dojo.stopEvent(evt);

            //get sizes of map element
            let offW = evt.target.offsetWidth;
            let offH = evt.target.offsetHeight;

            /* console.log(offW);
            console.log(offH); */

            // get pointer coordinates relative to centered map (subtract sizes)
            let offX = evt.offsetX - offW/2;
            let offY = -(evt.offsetY - offH/2);

            // get scrollable container offset relative to map element and center it 
            let trackL = -(parseInt($('map_scrollable').style.left) - offW/2);
            let trackT = parseInt($('map_scrollable').style.top) - offH/2;

            // sum pointer offset with map offset and get absolute coordiantes
            let absX = Math.round((offX + trackL));
            let absY = Math.round((offY + trackT));
            
            // scale coordinates depending  on interface scale to get relative map coordinates
            let mapX = Math.round(absX / Math.pow(0.8,this.interfaceScale)); // honestly dunno why dividing for interface scale instad of multiplying but it works that way
            let mapY = Math.round(absY / Math.pow(0.8,this.interfaceScale));

            console.log(mapX, mapY);

            return {x: mapX, y: mapY}
        },

        // applies scale on the whole game interface with factor calculated as 0.8^interfaceScale step. power function needed to make zoom feel smooth
        // scaling obtained with css transform of parent of all table elments, so to keep distance between them proportional
        scaleInterface: function() {
            dojo.style('track','transform','scale('+Math.pow(0.8,this.interfaceScale)+')');
            dojo.style('touchable_track','transform','scale('+Math.pow(0.8,this.interfaceScale)+')');
        },

        // scale element to size and cuts margin to fix scaling white space, then wraps element in .icon element
        // useful to do this in js as it can dinamically transform any element into an icon
        // note that this func won't work if element is not yet rendered on the page (ie. notification in game log)
        iconize: function(el, size) {

            // scale to size 100px, then scale to wanted size
            let scale = this.octSize / el.offsetWidth * size / this.octSize;

            el.style.transform = `scale(${scale})`;

            // calc margin to remove white space around scaled element
            // ! assuming element is square
            el.style.margin = `-${el.offsetWidth * (1 - scale) / 2}px`;

            // wrap in icon div and set size. necessary to hold element in place
            el.outerHTML = `<div class='icon' style=' width: ${size}px; height: ${size}px;'>` + el.outerHTML + "</div>";
        },

        // sets token counters to new value (not increments, full new value should be passed)
        updatePlayerTokens: function(id, tire=null, nitro=null) {

            if (parseInt(tire) >= 0 ) this.counters.playerBoard[id].tireTokens.toValue(tire);
            if (parseInt(nitro) >= 0 ) this.counters.playerBoard[id].nitroTokens.toValue(nitro);
        },

        // displaySelectionOctagons: place and displays a list of selection octagons. accepts an array of objects {x:, y: } indicating the center coordinates of each octagon to display.
        displaySelectionOctagons: function(positions, subcont=null) {

            positions.forEach((pos, i) => {
                if (!$('selOct_'+pos.x+'_'+pos.y)) { // prevent sel octagon of same position to be created and mess the interface (should not happen anyway, server shoud handle doubles)
                    dojo.place(
                        this.format_block('jstpl_selOctagon',{
                            i: i,
                            x: pos.x,
                            y: pos.y
                        }),
                        (subcont)? subcont : 'pos_highlights'
                    );

                    this.placeOnTrack('selOct_'+pos.x+'_'+pos.y,pos.x,pos.y);
                    dojo.style('selOct_'+pos.x+'_'+pos.y,'transform','translate(-50%,-50%) scale('+this.octSize/500+')');
                }
            });
        },

        // displays direction arrow to select orientation of F8 after movement. works similarly to method above
        // positions has to be an array of object with properties coordinates, direction and black
        displayDirectionArrows: function(positions, direction) {

            // extract every arrow position and create and place arrow element
            let allDirPos = [];
            positions.forEach(pos => {
                allDirPos.push(pos.coordinates);

                let arr = this.createGameElement('dirArrow', { color: (pos.black)? 'black' : 'white', direction: pos.direction}, 'dirArrows')                
                this.placeOnTrack(arr, pos.coordinates.x, pos.coordinates.y, +direction+pos.rotation);
            });

            // place selection octagons on top of arrows to catch user input on larger area
            this.displaySelectionOctagons(allDirPos);
            document.querySelectorAll('#pos_highlights > .selectionOctagon').forEach( el => {
                el.style.filter = 'opacity(0)';
            })
        },

        // function to connect position highlights elements (#pos_highlights > *) such as selection octagons (but it is also used for direction arrows) to specific handlers for click and mouseEnter events.
        // arguments are the names of the handlers method to call.
        // method connects also to standard method that wipes any preview on screen on mouse out. kinda stiched solution for previews sticking to position even when mouse is not hovering element
        connectPosHighlights: function(onclickHandler, onmouseenterHandler) {

            document.querySelectorAll('#pos_highlights > *').forEach( el => {
                dojo.connect(el,'onclick', this, onclickHandler);
                dojo.connect(el,'onmouseenter', this, onmouseenterHandler);
                dojo.connect(el,'onmouseleave', this, (evt) => $('previews').innerHTML = ''); // mouse leave generally means removing the displayed preview
            });
        },

        // creates and displays window to select token amount for each type.
        // attributes amount automatically to each tipe given the already owned (base) amount for each type, and the total amount of new token to withdraw
        displayTokenSelection: function(baseTire,baseNitro,amount) {

            dojo.place(
                this.format_block('jstpl_tokenSelWin', {amt: amount}),
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

            amount = Math.min(amount + baseTire + baseNitro, 16);

            /* let base = {
                tire: baseTire,
                nitro: baseNitro
            }; */

            this.gamedatas.gamestate.args.tire = baseTire;
            this.gamedatas.gamestate.args.nitro = baseNitro;
            
            // func that handles automatic token distribution and updates html elements
            let updateCounter = (type, value) => {

                if (value == NaN) value = 0;

                let tireText = document.querySelector('#tireTokenIncrementer > input');
                let nitroText = document.querySelector('#nitroTokenIncrementer > input');

                let tire;
                let nitro;

                if (document.querySelector('#tokenAutofill').checked) {
                    tire = (type=='nitro')? (amount - value) : value;
                    nitro = (type=='tire')? (amount - value) : value;
                } else {
                    tire = (type=='tire')? value : tireText.value;
                    nitro = (type=='nitro')? value : nitroText.value;
                }

                if (tire < 0 || nitro < 0) return
            
                /* if (value < Math.max(base[type], 0) || value > amount || value > Math.min(base[type] + amount, 8)) { */
                if (value < 0 || value > 16) { // this avoid going negative programmatically and user gets stuck buttons

                    tireText.value = this.gamedatas.gamestate.args.tire;
                    nitroText.value = this.gamedatas.gamestate.args.nitro;

                } else {

                    let tireVal = tireText.value = this.gamedatas.gamestate.args.tire = tire;
                    let nitroVal = nitroText.value = this.gamedatas.gamestate.args.nitro = nitro;

                    if (tireVal < Math.max(baseTire, 0) || tireVal > Math.min(baseTire + amount, 8)) {
                        tireText.style.color = 'red';
                    } else tireText.style.color = 'black';

                    if (nitroVal < Math.max(baseNitro, 0) || nitroVal > Math.min(baseNitro + amount, 8)) {
                        nitroText.style.color = 'red';
                    } else nitroText.style.color = 'black';
                }
            }

            // handler for inputting numbers into field directly
            document.querySelectorAll('.tokenIncrementer > input').forEach( el => {
                el.addEventListener('input', (evt)=>{
                    let value = evt.target.value;
                    let type = evt.target.parentElement.id.replace('TokenIncrementer','');
                    updateCounter(type, value);
                });

                el.addEventListener('click', (evt)=>{
                    evt.target.value = '';
                });
            });

            // handler for incrementerbuttons
            document.querySelectorAll('.tokenIncrementer > button').forEach( el => {
                el.addEventListener('click',(evt) => {

                    let value = parseInt(evt.target.parentElement.children[1].value);
                    let type = evt.target.parentElement.id.replace('TokenIncrementer','');

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
            let window = $('tokenSelectionWindow');

            let h = window.offsetHeight;
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

            // Show the dialog
            this.gearSelDW.setContent(this.format_block('jstpl_gearSelectionWindow')); // Must be set before calling show() so that the size of the content is defined before positioning the dialog
            this.gearSelDW.show();
            this.gearSelDW.replaceCloseCallback( () => {
                this.gearSelDW.hide();
            });

            let size = 80;
            
            gears.forEach( (g,i) => {

                dojo.place(
                    this.format_block('jstpl_selWinVectorPreview', {
                        n: i+1,
                        bottom: 0}
                    ),
                    'gearSelectionWindow'
                );
                    
                // same techniche to remove white space after scaling used in iconize()
                let gear = $('gear_'+(i+1));
                let scale = this.octSize / gear.offsetWidth * size / this.octSize;
                gear.style.transform = `scale(${scale})`;

                gear.style.marginLeft = gear.style.marginRight = `-${gear.offsetWidth * (1 - scale) / 2}px`;
                gear.style.marginTop = gear.style.marginBottom = `-${gear.offsetHeight * (1 - scale) / 2}px`;


                let optToken = '';
                if (g.indexOf('Cost') != -1) optToken = '<span>-' + (Math.abs(gears.indexOf('curr')-i)-1) + ' ' +  this.format_block('jstpl_token',{type: g.replace('Cost','')}) + '</span>';
                if (g == 'denied') optToken = '<span>' + this.format_block('jstpl_cross') + '</span>';

                gear.outerHTML = `<div data-gear-n='${i+1}' class='gearSelectionPreview gearSel_${g} ${(g=='curr')? 'gearSel_avail' : ''}' style='transform: translate(0,-${(4-i)*size/2}px)'>` + gear.outerHTML + optToken + "</div>";

            });

            document.querySelectorAll('.gearSelectionPreview').forEach( el => {
                el.addEventListener('click', evt => {
                    dojo.stopEvent(evt);
                    this.ajaxcallwrapper(this.gamedatas.gamestate.possibleactions[0],{gearN: evt.target.dataset.gearN}, is_error => {if (!is_error) this.gearSelDW.hide()});
                });
            });

            document.querySelectorAll('.gearSelectionPreview .token').forEach( el => {
                this.iconize(el,30);
            })

            document.querySelectorAll('.gearSelectionPreview .cross').forEach( el => {
                this.iconize(el,50);
            })
        },

        // formats a new game element of some type from template and place it inside refnode
        // also applies general transform to adapt element to interface (center, scale and rotate sprite)
        // returns created element
        createGameElement: function(type, args={}, refnode='game_elements') {

            dojo.place(
                this.format_block('jstpl_'+type, args),
                refnode
            );
            
            let element = $(refnode).lastChild;
            

            // counter original rotation on sprite
            let rotation;
            switch (type) {
                case 'selOctagon': 
                    rotation = 0;
                    break;
                case 'car':

                    rotation = -4;
                    break;
                case 'curve':

                    rotation = -3;
                    break;
                default:

                    rotation = -2;
                    break; // (for gear and boost vectors, pitwall, dirArrows, ..)
            }
            // center, adapt to interface scale, rotate element
            element.style.transform = `translate(-50%,-50%) scale(${this.octSize/500}) rotate(${rotation*-45}deg)`;

            return element;
        },

        // handles case where it's the car first placement, thus it is invisible and should be placed on on respective player boards before being slid to the track
        carFirstPlacement: function(id,x,y) {
            let carid = 'car_'+this.gamedatas.players[id].color;
            $(carid).style.display = '';

            let pb = this.getPlayerBoardCoordinates(id);
            this.placeOnTrack(carid, pb.x, pb.y);

            this.slideOnTrack(carid, x, y);
        },

        // formats a car preview element and transforms it to match active player car
        createPreviewCar: function() {
            dojo.place(
                this.format_block('jstpl_car', {color: 'preview'}),
                'previews'
            );

            $('car_preview').className = $('car_preview').className.replace('gameElement','');

            $('car_preview').style.transform = $('car_'+this.gamedatas.players[this.getActivePlayerId()].color).style.transform;
            return $('car_preview');
        },

        // istantaneously move game element to coordinates (x,y) relative to track coordinates system. also rotate element k times 45deg (counter clockwise)
        placeOnTrack: function(el, x, y, k=null) {

            if (!(el instanceof Element)) el = $(el);

            el.style.position = "absolute"; // redundant, but safe

            el.style.left = x +'px';
            el.style.top = -y +'px';

            if (k) el.style.transform += `rotate(${k * -45}deg)`;
        },

        // as method above, but applies css transition to the movement
        slideOnTrack: function(id, x, y, k=null, duration=500, delay=0, onEnd=()=>{}) {

            let el = $(id);
            
            el.offsetWidth; // MAGIC that sets all changed css properties before, so that it doesn't influence transition

            el.style.zIndex = 5; // make slid element be above everything else while being slid

            // set transition properties
            el.style.transitionDuration = duration+'ms';
            el.style.transitionDelay = delay+'ms';

            el.style.transitionProperty = 'left,top,transform';
            // if (el.className.includes('gameElement')) el.style.transitionProperty += ',filter';
            
            // place element to new coordinates (it will now be animated)
            this.placeOnTrack(id, x, y, k)

            // count transitions and when all of them end, fire onEnd handler
            let transitionPropCounter = 2; // should be 3 but apparently left-top properties, by transitioning together, they fire transitionend once? doesn't make sense
            if (!k) transitionPropCounter--; // if no rotation, there will be one less transition.

            el.ontransitionend = () => {
                transitionPropCounter--;
                
                if (transitionPropCounter == 0) {

                    el.style.zIndex = '';

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

        updateGearIndicator: function(player,newGear) {
            let gear = $('gear_p'+player);
            let i = gear.className.indexOf('gearInd_')
            gear.className = gear.className.slice(0,i).concat('gearInd_'+newGear);
        },
        
        //#endregion

        //++++++++++++++++//
        // PLAYER ACTIONS //
        //++++++++++++++++//
        //#region actions

        // TO BE MOVED TO DIRECT HANDLER ON ENTERING STATE BLOCK
        previewStartCarPos: function(evt) {
            // cool, now it also accounts for pitlane orientation

            dojo.stopEvent(evt);

            let h = $('start_positioning_area').clientHeight;
            let rot = this.gamedatas.gamestate.args.rotation;

            let xp = this.gamedatas.gamestate.args.anchorPos.x;
            let yp = this.gamedatas.gamestate.args.anchorPos.y;

            let offx = evt.offsetX; // offset from left (NOT NEEDED)
            let offy = evt.offsetY; // offset from top

            let x = xp+this.octSize/2
            let y = yp+h-offy;

            if (offy > h-this.octSize/2) y = yp+this.octSize/2;
            if (offy < this.octSize/2) y = yp+h-this.octSize/2;

            the = -rot * Math.PI/4;
            let c = Math.cos(the);
            let s = Math.sin(the);

            let xr = ((x-xp)*c - (y-yp)*s) +xp;
            let yr = ((x-xp)*s + (y-yp)*c) +yp;

            if (!$('car_preview')) this.createPreviewCar();
            this.placeOnTrack('car_preview', xr, yr);
        },

        // display preview of players car behind the hovering octagon highlight
        // the only handler function shared between multiple events
        previewCarPos: function(evt) {
            dojo.stopEvent(evt);

            let pos = this.selOctagonPos(evt.target);
            this.placeOnTrack(this.createPreviewCar(), pos.x, pos.y);
        },

        // displays orientation arrow to let user decide car direction before confirming position and endiong movement phase
        // could be moved to onEnter where this funct is attached to event
        selectCarPos: function(evt) {

            dojo.stopEvent(evt);

            let pos = this.gamedatas.gamestate.args.positions[parseInt(evt.target.dataset.posIndex)];

            // make all the checks for move validity
            // being split in two steps, car placement phase needs to check user action intead of letting server do that
            if (!this.isCurrentPlayerActive()) {
                /* this.showMessage(_('It is not your turn'),'error'); */
                return;
            }

            /* if (!pos.legal) {
                this.showMessage(_("Illegal car position"),"error");
                return;
            }

            if (pos.denied && pos.tireCost) {
                this.showMessage(_('You cannot select "black moves" after an Emergency Brake'),"error");
                return;
            }

            if (pos.denied) {
                this.showMessage(_("Car position denied by the previous shunking you suffered"),"error");
                return;
            }

            if (pos.offTrack) {
                this.showMessage(_("You cannot pass a curve from behind"),"error");
                return;
            }

            if (pos.tireCost && this.counters.playerBoard[this.getActivePlayerId()].tireTokens.getValue() < 1) {
                this.showMessage(_("You don't have enough Tire Tokens to place your car here"),"error");
                return;
            } */

            const prevArgs = JSON.parse(JSON.stringify(this.gamedatas.gamestate));

            this.gamedatas.gamestate.descriptionmyturn = _('${you} must choose where the car should be pointing');
            this.updatePageTitle();
            
            this.addActionButton('resetCarPos',_('reset'),()=>{
                this.onLeavingState('carPlacement');
                this.onEnteringState('carPlacement',prevArgs);
                this.gamedatas.gamestate = prevArgs;

                this.updatePageTitle();
            })

            this.gamedatas.gamestate.args.positions = pos;

            // move element from highlights to track to avoid removal
            dojo.place(
                'car_preview',
                'track'
            );
            
            $('pos_highlights').innerHTML = '';
            $('previews').innerHTML = '';

            let directions = this.gamedatas.gamestate.args.positions['directions'];

            // with the obtained positions, generate and display the direction arrows and connect them to the proper handlers
            this.displayDirectionArrows(directions, this.gamedatas.gamestate.args.direction);
            this.connectPosHighlights(
                evt => {
                    this.ajaxcallwrapper('placeCar', {
                        pos: this.gamedatas.gamestate.args.positions['position'],
                        dir: this.gamedatas.gamestate.args.positions['directions'][parseInt(evt.target.dataset.posIndex)]['direction']
                    }, (is_error) => { if (is_error) $('resetCarPos').click();}, '.selectionOctagon');
                },
                evt => {
                    const rotation = this.gamedatas.gamestate.args.positions['directions'][parseInt(evt.target.dataset.posIndex)]['rotation'];
                    const playerCarTransform = $('car_'+this.gamedatas.players[this.getActivePlayerId()].color).style.transform;

                    $('car_preview').style.transform = playerCarTransform + 'rotate('+rotation*-45+'deg)';
                }
            );
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

            dojo.subscribe('placeCarFS', this, 'notif_placeCarFS');
            this.notifqueue.setSynchronous( 'placeCarFS', 500 );

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
            this.notifqueue.setSynchronous( 'placeCar');

            dojo.subscribe('useNewVector', this, 'notif_useNewVector');
            this.notifqueue.setSynchronous( 'useNewVector', 500 );

            dojo.subscribe('rotateAfterBrake', this, 'notif_rotateAfterBrake');
            this.notifqueue.setSynchronous( 'rotateAfterBrake', 500 );

            dojo.subscribe('giveWay', this, 'notif_giveWay');
            this.notifqueue.setSynchronous( 'giveWay', 500 );

            dojo.subscribe('declareGear', this, 'notif_declareGear');
            this.notifqueue.setSynchronous( 'declareGear', 500 );

            dojo.subscribe('engageManeuver', this, 'notif_engageManeuver');
            this.notifqueue.setSynchronous( 'engageManeuver', 500 );

            dojo.subscribe('noAttMov', this, 'notif_noAttMov');
            this.notifqueue.setSynchronous( 'noAttMov');

            dojo.subscribe('gearShift', this, 'notif_gearShift');
            this.notifqueue.setSynchronous( 'gearShift', 500 );

            dojo.subscribe('nextRoundTurnOrder', this, 'notif_nextRoundTurnOrder');
            this.notifqueue.setSynchronous( 'nextRoundTurnOrder');

            dojo.subscribe('boxEntranceOvershoot', this, 'notif_boxEntranceOvershoot');
            this.notifqueue.setSynchronous( 'boxEntranceOvershoot', 500 );

            dojo.subscribe('lapFinish', this, 'notif_lapFinish');
            this.notifqueue.setSynchronous( 'lapFinish', 500 );

            dojo.subscribe('finishedRace', this, 'notif_finishedRace');
            this.notifqueue.setSynchronous( 'finishedRace', 1500 );
            
        },  

        // --- HANDLERS ---
        
        notif_logger: function(notif) {
            console.log(notif.args);

            /* Object.values(notif.args.vertices).forEach( el => {
                console.log(el);
                this.displayPoints(el);
            }); */


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
            $('refCar_'+notif.args.refCar).click();
            this.carFirstPlacement(notif.args.player_id, notif.args.x, notif.args.y);
        },

        notif_chooseTokensAmount: function(notif) {
            this.updatePlayerTokens(notif.args.player_id, parseInt(notif.args.tire) + parseInt(notif.args.prevTokens.tire), parseInt(notif.args.nitro) + parseInt(notif.args.prevTokens.nitro));
        },

        notif_placeGearVector: function(notif) {

            let vecPreview = (document.querySelector('.gearVector'));
            if (vecPreview) vecPreview.remove();

            let gv = this.createGameElement('gearVector',{ n: notif.args.gear });            
            let pb = this.getPlayerBoardCoordinates(notif.args.player_id);
            this.placeOnTrack(gv, pb.x, pb.y, notif.args.direction);

            this.slideOnTrack(gv, notif.args.x, notif.args.y);         

            this.updatePlayerTokens(notif.args.player_id, notif.args.tireTokens, null);
        },

        notif_useBoost: function(notif) {

            this.updatePlayerTokens(notif.args.player_id, null, notif.args.nitroTokens);
        },

        notif_chooseBoost: function(notif) {

            let boostPreview = (document.querySelector('.boostVector'));
            if (boostPreview) boostPreview.remove();

            let bv = this.createGameElement('boostVector',{ n: notif.args.n });
            let pb = this.getPlayerBoardCoordinates(notif.args.player_id);
            this.placeOnTrack(bv, pb.x, pb.y, notif.args.direction);
            this.slideOnTrack(bv, notif.args.vecX, notif.args.vecY);
        },

        notif_noBoostAvail: function(notif) {

            if(!this.isCurrentPlayerActive()) this.showMessage(_("No boost length can fit here or no car can be positioned on top of it, try to estimate better the space available next time"));
        },

        notif_placeCar: function(notif) {
            let boost = document.querySelector('.boostVector');
            this.notifqueue.setSynchronousDuration((boost)? 1500 : 1000);

            this.slideOnTrack('car_'+this.gamedatas.players[notif.args.player_id].color, notif.args.x, notif.args.y, notif.args.rotation, 500, 0, () => {

                let pb = this.getPlayerBoardCoordinates(notif.args.player_id);
                
                this.slideOnTrack(document.querySelector('.gearVector').id, pb.x, pb.y, 0, 500, 0, () => {

                    document.querySelector('.gearVector').remove();

                    /* boost = document.querySelector('.boostVector'); */
                    if (boost) this.slideOnTrack(boost.id, pb.x, pb.y, 0, 500, 0, () => boost.remove());
                });
            });

            this.updatePlayerTokens(notif.args.player_id, notif.args.tireTokens, null);
        },

        notif_useNewVector: function(notif) {

            this.updatePlayerTokens(notif.args.player_id, notif.args.tireTokens, null);
        },

        notif_rotateAfterBrake: function(notif) {

            this.updateGearIndicator(notif.args.player_id, 1);

            let car = $('car_'+this.gamedatas.players[notif.args.player_id].color);
            car.style.transition = 'transform 500ms'
            car.style.transform += `rotate(${notif.args.rotation * -45}deg)`;
        },

        notif_giveWay: function(notif) {
            let playerTurnPos = this.counters.playerBoard[notif.args.player_id].turnPos.getValue();
            let enemyTurnPos = this.counters.playerBoard[notif.args.player2_id].turnPos.getValue();
            this.counters.playerBoard[notif.args.player_id].turnPos.toValue(enemyTurnPos);
            this.counters.playerBoard[notif.args.player2_id].turnPos.toValue(playerTurnPos);
        },

        notif_engageManeuver: function(notif) {

            /* document.querySelector('.carRect').remove(); */

            this.slideOnTrack('car_'+this.gamedatas.players[notif.args.player_id].color, notif.args.attackPos.x, notif.args.attackPos.y);

            this.updatePlayerTokens(notif.args.player_id, null, notif.args.nitroTokens);
        },

        notif_noAttMov: function(notif) {
            this.notifqueue.setSynchronousDuration(2000 * notif.args.enemies);

            let refcars = document.querySelectorAll('.refCarAnchor');
            if (refcars.length > 1) {
                refcars.forEach((el,i) => {
                    setTimeout(() => el.click(), 2000*(i));
                })
            }
        },

        notif_chooseStartingGear: function(notif) {

            Object.values(this.gamedatas.players).forEach(player => {
                this.updateGearIndicator(player.id, notif.args.n);
            });
        },

        notif_declareGear: function(notif) {

            this.updateGearIndicator(notif.args.player_id, notif.args.n);
        },

        notif_gearShift: function(notif) {

            this.updatePlayerTokens(
                notif.args.player_id,
                (notif.args.tokenType == 'tire')? notif.args.tokensAmt : null,
                (notif.args.tokenType == 'nitro')? notif.args.tokensAmt : null
            );
        },

        notif_nextRoundTurnOrder: function(notif) {

            this.notifqueue.setSynchronousDuration(1000*Object.keys(notif.args.order).length);

            for (const pId in notif.args.order) {

                let pos = notif.args.order[pId];
                /* console.log(Object.keys(notif.args).lenght);
                console.log(pos);
                console.log(Object.keys(notif.args).lenght-pos); */
                
                dojo.place(
                    this.format_block('jstpl_turnPosInd',{pos:pos}),
                    'touchable_track'
                );

                let playerCar = $('car_'+this.gamedatas.players[pId].color);
                let indicator = $('turnPos_'+pos);

                indicator.style.transform = 'translate(-50%,-50%) scale('+this.octSize/250+')';
                indicator.style.left = playerCar.style.left;
                indicator.style.top = playerCar.style.top;
                indicator.style.animationDelay = (pos-notif.args.missingPlayers-1)+'s';
                // element then removed when leaving state bacause it gets buggy otherwise

                this.counters.playerBoard[pId].turnPos.toValue(pos);
            }
        },

        notif_boxEntranceOvershoot: function(notif) {
            this.slideOnTrack('car_'+this.gamedatas.players[notif.args.player_id].color, notif.args.x, notif.args.y, notif.args.rotation, 500, 0);
        },

        notif_lapFinish: function(notif) {

            this.counters.playerBoard[notif.args.player_id].lapNum.toValue(notif.args.n);
        },

        notif_finishedRace: function(notif) {

            this.counters.playerBoard[notif.args.player_id].lapNum.toValue(notif.args.lapNum);

            let car = $('car_'+this.gamedatas.players[notif.args.player_id].color);

            car.style.transition = 'opacity 1.5s';
            car.style.opacity = 0;
            car.ontransitionEnd = () => car.remove();


        },

        //#endregion

    });
});
