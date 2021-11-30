<?php
 /**
  *------
  * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
  * VektoRace implementation : © <Pietro Luigi Porcedda> <pietro.l.porcedda@gmail.com>
  * 
  * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
  * See http://en.boardgamearena.com/#!doc/Studio for more information.
  * -----
  */

require_once(APP_GAMEMODULE_PATH.'module/table/table.game.php');
require_once('modules/VektoraceOctagon.php');
require_once('modules/VektoraceVector.php');
require_once('modules/VektoracePoint.php');

class VektoRace extends Table {
    
    //+++++++++++++++++++++//
    // SETUP AND DATA INIT //
    //+++++++++++++++++++++//
    #region setup

	function __construct() {
        parent::__construct();
        
        // GAME.PHP GLOBAL VARIABLES HERE
        // they are not simple global variables as those are destroyed with every page load
        // it's a sort of array stored separately (for more info see doc)
        // if not strictly necessary use DB to store every information about the game
        self::initGameStateLabels( array( ));        
	}
	
    // getGameName: basic utility method used for translation and other stuff. do not modify
    protected function getGameName() {
        return "vektorace";
    }	

    // setupNewGame: called once, when a new game is initialized. this sets the initial game state according to the rules
    protected function setupNewGame( $players, $options=array()) {

        self::loadTrackPreset(); // custom function to set predifined track model

        // --- INIT PLAYER DATA ---
         
        // Set the colors of the players with HTML color code
        // The default below is red/green/blue/orange/brown
        // The number of colors defined here must correspond to the maximum number of players allowed for the gams
        $gameinfos = self::getGameinfos();
        $default_colors = $gameinfos['player_colors'];

        $sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ";
        $values = array();

        foreach( $players as $player_id => $player ) {
            $color = array_shift( $default_colors );
            $values[] = "('".$player_id."','$color','".$player['player_canal']."','".addslashes( $player['player_name'] )."','".addslashes( $player['player_avatar'] )."')";

            self::DbQuery("INSERT INTO penalities_and_modifiers (player) VALUES ($player_id)"); // INIT PENALITIES AND MODIFIERS TABLE (just put player id everything else is set to the default value false) 
        }

        $sql .= implode( $values, ',' );
        self::DbQuery( $sql );

        $sql = "UPDATE player
                SET player_turn_position = player_no";
        self::DbQuery($sql);        

        self::reattributeColorsBasedOnPreferences( $players, $gameinfos['player_colors'] );
        self::reloadPlayersBasicInfos();

        // --- INIT GLOBAL VARIABLES ---
        // example: self::setGameStateInitialValue( 'my_first_global_variable', 0 );
        
        // --- INIT GAME STATISTICS ---
        // example: (statistics model should be first defined in stats.inc.php file)
        // self::initStat( 'table', 'table_teststat1', 0 );    // Init a table statistics
        // self::initStat( 'player', 'player_teststat1', 0 );  // Init a player statistics (for all players)

        // --- SETUP INITIAL GAME STATE ---
        $sql = "INSERT INTO game_element (entity, id, orientation)
                VALUES ";
        
        $values = array();
        foreach( $players as $player_id => $player ) {
            $values[] = "('car',".$player_id.",".self::getUniqueValueFromDB("SELECT orientation FROM game_element WHERE entity='pitwall'").")"; // empty brackets to appends at end of array
        }
        
        $sql .= implode( $values, ',' );
        self::DbQuery( $sql );

        // --- ACTIVATE FIRST PLAYER ---
        $this->activeNextPlayer();
    }

    // getAllDatas: method called each time client need to refresh interface and display current game state.
    //              should extract all data currently visible and accessible by the callee client (self::getCurrentPlayerId(). which is very different from active player)
    //              [!!] in vektorace, no information is ever hidder from players, so there's no use in discriminate here.
    protected function getAllDatas() {
        $result = array();
    
        $current_player_id = self::getCurrentPlayerId();

        $sql = "SELECT player_id id, player_score score, player_turn_position turnPos, player_current_gear currGear, player_tire_tokens tireTokens, player_nitro_tokens nitroTokens, player_lap_number lapNum
                FROM player ";
        $result['players'] = self::getCollectionFromDb( $sql );
  
        $result['game_element'] = self::getObjectListFromDb( "SELECT * FROM game_element" );

        $result['octagon_ref'] = VektoraceOctagon::getOctProperties();

        return $result;
    }

    /*
        getGameProgression:
        
        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).
    
        This method is called each time we are in a game state with the "updateGameProgression" property set to true 
        (see states.inc.php)
    */

    // getGameProgression: compute and return game progression (0-100). called by state which are supposed determine an advancement in the game
    function getGameProgression() {
        // TODO
        return 0;
    }

    #endregion

    //+++++++++++++++++++//
    // UTILITY FUNCTIONS //
    //+++++++++++++++++++//
    #region utility

    // [general purpose function that controls the game logic]

    // test: test function to put whatever comes in handy at a given time
    function testComponent() {
        $ret = array();
        
        $args = self::argAttackManeuvers();

        $car = self::getPlayerCarOctagon(self::getActivePlayerId());
        $carVs = $car->getVertices();
        $carVs = array($carVs[0], $carVs[3], $carVs[4], $carVs[7]);
        $pos = $args['maneuvers']['2352473']['rightShunk']['attPos'];
        $posOct = new VektoraceOctagon(new VektoracePoint($pos['x'],$pos['y']));
        $posOctVs = $posOct->getVertices();

        $sat = VektoraceOctagon::findSeparatingAxis($carVs,$posOctVs);
        
        foreach ($carVs as &$v) {
            $v = $v->coordinates();
        } unset($v);

        foreach ($posOctVs as &$v) {
            $v = $v->coordinates();
        } unset($v);


        self::consoleLog(array(
            'vertices' => array('car' => $carVs, 'pos' => $posOctVs),
            'exists separating axis' => $sat)
        );
    }
    
    // consoleLog: debug function that uses notification to log various element to js console (CAUSES BGA FRAMEWORK ERRORS)
    function consoleLog($payload) {
        self::notifyAllPlayers('logger','i have logged',$payload);
    }

    function allVertices() {
        $ret = array();
        
        foreach (self::getObjectListFromDb("SELECT * FROM game_element") as $element) {
            switch ($element['entity']) {
                case 'car':
                    if ($element['pos_x']!=null && $element['pos_y']!=null) {
                        $car = new VektoraceOctagon(new VektoracePoint($element['pos_x'],$element['pos_y']),$element['orientation']);
                        $vertices = $car->getVertices();

                        foreach ($vertices as &$v) {
                            $v = $v->coordinates();
                        } unset($v);

                        $ret['car'.' '.$element['id']] = $vertices;
                    }
                    break;

                case 'curve':
                    $curve = new VektoraceOctagon(new VektoracePoint($element['pos_x'],$element['pos_y']),$element['orientation'],true);
                    $vertices = $curve->getVertices();

                    foreach ($vertices as &$v) {
                        $v = $v->coordinates();
                    } unset($v);

                    $ret['curve'.' '.$element['id']] = $vertices;
                    break;

                case 'pitwall':
                    $pitwall = new VektoraceVector(new VektoracePoint($element['pos_x'],$element['pos_y']),$element['orientation'],4);

                    $vertices = $pitwall->innerRectVertices();

                    foreach ($vertices as &$v) {
                        $v = $v->coordinates();
                    } unset($v);

                    $ret['pitwall'] = $vertices;
                    break;

                default: // vectors and boosts
                    $vector = new VektoraceVector(new VektoracePoint($element['pos_x'],$element['pos_y']),$element['orientation'],$element['id']);

                    $vertices = $vector->innerRectVertices();

                    foreach ($vertices as &$v) {
                        $v = $v->coordinates();
                    } unset($v);

                    $ret[$element['entity'].' '.$element['id']] = $vertices;
                    break;
            }
        }

        self::notifyAllPlayers('allVertices','',$ret);
    }

    // loadTrackPreset: sets DB to match a preset of element of a test track
    function loadTrackPreset() {
        $sql = "INSERT INTO game_element (entity, id, pos_x, pos_y, orientation)
                VALUES ('pitwall',10,0,0,4),
                       ('curve',1,-550,400,5),
                       ('curve',2,-550,1150,3),
                       ('curve',3,1700,400,7),
                       ('curve',4,1700,1150,1)";
        self::DbQuery($sql);
    }

    // return player turn position given its id
    function getPlayerTurnPos($id) {

        $sql = "SELECT player_turn_position
                FROM player
                WHERE player_id = $id";
        return self::getUniqueValueFromDb( $sql );
    }

    // returns player with turn position number $n (names can be a lil confusing)
    function getPlayerTurnPosNumber($n) {

        $sql = "SELECT player_id
                FROM player
                WHERE player_turn_position = $n";
        return self::getUniqueValueFromDb( $sql );
    }

    // returns turn position number of player after $id in the custon turn order (as in, the one used for the current game round)
    function getPlayerAfterCustom($id) {
        $playerTurnPos = self::getPlayerTurnPos($id);

        $round = [4,1,2,3];
        $next = ($playerTurnPos + 1) % self::getPlayersNumber();

        return self::getPlayerTurnPosNumber($round[$next]);
    }

    // getPlayerCarOctagon: returns VektoraceOctagon object created at center pos, given by the coordinates of the players car, according to DB
    function getPlayerCarOctagon($id) {

        $sql = "SELECT pos_x, pos_y, orientation
                FROM game_element
                WHERE entity = 'car' AND id = $id";

        $ret = self::getObjectFromDB($sql);
        return new VektoraceOctagon(new VektoracePoint($ret['pos_x'],$ret['pos_y']), $ret['orientation']);
    }

    // getPlayerCurrentGear: returns player's current gear
    function getPlayerCurrentGear($id) {

        $sql = "SELECT player_current_gear
                FROM player
                WHERE player_id = $id";

        $ret = self::getUniqueValueFromDB($sql);
        return $ret;
    }
    
    // return vector object of the latest placed vector. can be gear or boost
    function getPlacedVector($type = 'gear') {

        $sql = "SELECT id, pos_x, pos_y, orientation
                FROM game_element
                WHERE entity = '".$type."Vector'";

        $ret = self::getObjectFromDB($sql);
        if (empty($ret)) return null;
        return new VektoraceVector(new VektoracePoint($ret['pos_x'],$ret['pos_y']), $ret['orientation'], $ret['id']);
    }

    // returns player tire and nitro tokens
    function getPlayerTokens($id) {

        $sql = "SELECT player_tire_tokens tire, player_nitro_tokens nitro 
                FROM player
                WHERE player_id = $id";
            
        return self::getObjectFromDB($sql);
    }

    // (called at end of round) calculates new turn order based on current car positions
    function newTurnOrder() {
        
        // get all cars pos from db
        $sql = "SELECT player_id id, pos_x x, pos_y y, orientation dir
                FROM player
                JOIN game_element ON id = player_id
                WHERE entity = 'car'
                ORDER BY player_turn_position DESC";

        $allPlayers = self::getObjectListFromDB($sql);

        // we need to return boolan to indicate if position changed since last turn 
        $isChanged = false;


        // bubble sort cars using overtake conditions
        for ($i=0; $i<count($allPlayers)-1; $i++) { 
            for ($j=0; $j<count($allPlayers)-1-$i; $j++) {
                $thisPlayer = $allPlayers[$j];
                $playerCar = new VektoraceOctagon(new VektoracePoint($thisPlayer['x'], $thisPlayer['y']), $thisPlayer['dir']);

                $nextPlayer = $allPlayers[$j+1];
                $nextCar = new VektoraceOctagon(new VektoracePoint($nextPlayer['x'], $nextPlayer['y']), $nextPlayer['dir']);
                
                if ($playerCar->overtake($nextCar)) {
                    $isChanged = true;

                    $temp = $allPlayers[$j+1];
                    $allPlayers[$j+1] = $allPlayers[$j];
                    $allPlayers[$j] = $temp;
                }
            }
        }

        foreach($allPlayers as $i => $player) {
            $sql = "UPDATE player
                    SET player_turn_position = ".(count($allPlayers)-intval($i))."
                    WHERE player_id = ".$player['id'];
            self::DbQuery($sql);
        }

        return $isChanged;
    }

    // big messy method checks if subj object (can be either octagon or vector) collides with any other element on the map (cars, curves or pitwall)
    function detectCollision($subj, $isVector=false, $ignoreElements = array()) {
        
        self::dump('/// ANALIZING COLLISION OF '.(($isVector)? 'VECTOR':'CAR POSITION'),$subj->getCenter()->coordinates());

        foreach (self::getObjectListFromDb("SELECT * FROM game_element") as $element) {

            if (!is_null($element['pos_x']) && !is_null($element['pos_y']) && !in_array($element['id'],$ignoreElements)) {

                $pos = new VektoracePoint($element['pos_x'],$element['pos_y']);

                self::dump('// WITH '.$element['entity'].' '.$element['id'].' AT ', $pos->coordinates());

                if ($isVector) {

                    // this pitwall case is problematic as it it vector to vector collision
                    if ($element['entity']=='pitwall') {

                        $pitwall = new VektoraceVector($pos, $element['orientation'], 4);

                        // PITWALL COLLIDES WITH EITHER THE TOP OR BOTTOM VECTOR'S OCTAGON AND VICE VERSA

                        // check all four anchors to find collision with respective inner rect too
                        if ($subj->getBottomOct()->collidesWithVector($pitwall) ||
                            $subj->getTopOct()->collidesWithVector($pitwall) ||
                            $pitwall->getBottomOct()->collidesWithVector($subj) ||
                            $pitwall->getTopOct()->collidesWithVector($subj)) {
                                self::trace('// -!- COLLISION DETECTED -!-');
                                return true;
                            }

                        // PITWALL INNER RECTANGLE COLLIDES WITH VECTOR INNER RECTANGLE (RARE)

                        $pitwallInnerRect = $pitwall->innerRectVertices();
                        $vectorInnerRect = $subj->innerRectVertices();

                        //self::consoleLog(array('pitwall' => $pitwallInnerRect, 'vector' => $vectorInnerRect));

                        if (!VektoraceOctagon::findSeparatingAxis($pitwallInnerRect, $vectorInnerRect)) {

                            $omg = M_PI_4;
                            foreach ($pitwallInnerRect as &$vertex) {
                                $vertex->rotate($omg);
                            }
                            unset($vertex);
    
                            foreach ($vectorInnerRect as &$vertex) {
                                $vertex->rotate($omg);
                            }
                            unset($vertex);
    
                            if (!VektoraceOctagon::findSeparatingAxis($pitwallInnerRect, $vectorInnerRect)) {
                                self::trace('// -!- COLLISION DETECTED -!-');
                                return true;
                            }
                        }

                    } else {
                        $obj = new VektoraceOctagon($pos, $element['orientation'], $element['entity']=='curve');
                        if ($obj->collidesWithVector($subj)) return true; 
                    }

                } else {

                    if ($element['entity']=='pitwall') {
                        $obj = new VektoraceVector($pos, $element['orientation'], 4);
                        if ($subj->collidesWithVector($obj)) {
                            self::trace('// -!- COLLISION DETECTED -!-');
                            return true;
                        }
                    }

                    $obj = new VektoraceOctagon($pos, $element['orientation'], $element['entity']=='curve');
                    
                    if ($obj->collidesWith($subj)) {
                        self::trace('// -!- COLLISION DETECTED -!-');
                        return true;
                    }
                }
            }
        }

        return false;
    }

    #endregion

    //++++++++++++++++//
    // PLAYER ACTIONS //
    //++++++++++++++++//
    #region player actions

    // [functions responding to ajaxcall formatted and forwarded by action.php script. function names should always match action name]

    // selectPosition: specific function that selects and update db on new position for currently active player car.
    //                 should be repurposed to match all cases of selecting positions and cars moving
    function placeFirstCar($x,$y) {

        if ($this->checkAction('placeFirstCar')) {

            // check if sent pos is valid (and player didn't cheat) by doing dot product of positioning window norm and pos vector to window center (result should be close to 0 as vectors should be orthogonal)
            $args = self::argFirstPlayerPositioning();
            
            $dir = -$args['rotation']+4;

            $center = new VektoracePoint($args['center']['x'],$args['center']['y']);
            $norm = new VektoracePoint(0,0);
            $norm->translateVec(1,($dir)*M_PI_4);

            $pos = VektoracePoint::displacementVector($center, new VektoracePoint($x,$y));
            $pos->normalize();

            if (abs(VektoracePoint::dot($norm, $pos)) > 0.1) throw new BgaUserException('Invalid car position');

            $id = self::getActivePlayerId();

            $sql = "UPDATE game_element
                SET pos_x = $x, pos_y = $y
                WHERE id = $id";
        
            self::DbQuery($sql);

            self::notifyAllPlayers('placeFirstCar', clienttranslate('${player_name} chose their car starting position'), array(
                'player_id' => $id,
                'player_name' => self::getActivePlayerName(),
                'x' => $x,
                'y' => $y
                ) 
            );

            $this->gamestate->nextState();
        }
    }

    function placeCarFS($refCarId,$posIdx) {

        if ($this->checkAction('placeCarFS')) {

            $args = self::argFlyingStartPositioning();

            $pos = $args['positions'][$refCarId]['positions'][$posIdx];

            if (!$pos['valid']) throw new BgaUserException('Invalid car position');

            ['x'=>$x,'y'=>$y] = $pos['coordinates'];

            $id = self::getActivePlayerId();

            $sql = "UPDATE game_element
                    SET pos_x = $x, pos_y = $y
                    WHERE id = $id";
        
            self::DbQuery($sql);

            self::notifyAllPlayers('placeFirstCar', clienttranslate('${player_name} chose their car starting position'), array(
                'player_id' => $id,
                'player_name' => self::getActivePlayerName(),
                'x' => $x,
                'y' => $y
            ));

            $this->gamestate->nextState();
        }
    }

    function chooseTokensAmount($tire,$nitro) {
        if ($this->checkAction('chooseTokensAmount')) {

            $args = self::argTokenAmountChoice();
            if ($tire > 8 || $nitro > 8 || ($tire+$nitro) != min($args['tire'] + $args['nitro'] + $args['amount'], 16)) throw new BgaUserException('Invalid tokens amount');

            $id = self::getActivePlayerId();

            $sql = "UPDATE player
                    SET player_tire_tokens = $tire, player_nitro_tokens = $nitro
                    WHERE player_id = $id";

            self::DbQuery($sql);

            self::notifyAllPlayers('chooseTokensAmount', clienttranslate('${player_name} chose to start the game with ${tire} TireTokens and ${nitro} NitroTokens'), array(
                'player_id' => $id,
                'player_name' => self::getActivePlayerName(),
                'tire' => $tire,
                'nitro' => $nitro
                )
            );

            $this->gamestate->nextState();
        }
    }

    // chooseStartingGear: server function responding to user input when a player chooses the gear vector for all players (green-light phase)
    function chooseStartingGear($n) {
        if ($this->checkAction('chooseStartingGear')) {

            if ($n<3 && $n>0) throw new BgaUserException('You may only choose between the 3rd to the 5th gear for the start of the game');
            if ($n<1 || $n>5) throw new BgaUserException('Invalid gear number');

            $sql = "UPDATE player
                    SET player_current_gear = $n";
        
            self::DbQuery($sql);

            self::notifyAllPlayers('chooseStartingGear', clienttranslate('${player_name} chose the ${n}th gear as the starting gear for every player'), array(
                'player_name' => self::getActivePlayerName(),
                'n' => $n,
                ) 
            );
        }

        $this->gamestate->nextState();
    }

    // declareGear: same as before, but applies only to active player, about his gear of choise for his next turn. thus DB is updated only for the player's line
    function declareGear($n) {
        if ($this->checkAction('declareGear')) {

            if ($n<1 || $n>5) throw new BgaUserException('Invalid gear number');

            $id = self::getActivePlayerId();

            $args = self::argFutureGearDeclaration()['gears'];
            $gearProp = $args[$n-1];

            $curr = self::getPlayerCurrentGear($id);

            if ($gearProp == 'unavail') throw new BgaUserException('You are not allowed to choose this gear right now');
            if ($gearProp == 'denied') {
                if ($n > $curr) throw new BgaUserException('You cannot shift upwards after an Emergency Break');
                if ($n < $curr) throw new BgaUserException('You cannot shift downwards after suffering a push from an enemy car');
            }

            if ($gearProp == 'tireCost' || $gearProp == 'nitroCost')  {

                $type = str_replace('Cost','',$gearProp);

                $tokens = self::getPlayerTokens($id)[$type];

                $cost = abs($curr - $n)-1;
                $tokenExpense = $tokens - $cost;

                if ($tokenExpense < 0) throw new BgaUserException('You don\'t have enough '.$type.' tokens to do this action');

                $sql = "UPDATE player
                        SET player_".$type."_tokens = $tokenExpense
                        WHERE player_id = $id";

                self::DbQuery($sql);

                self::notifyAllPlayers('gearShift', clienttranslate('${player_name} performed ${shiftType} of step ${step} by spending ${cost} ${tokenType} tokens'), array(
                    'player_name' => self::getActivePlayerName(),
                    'player_id' => self::getActivePlayerId(),
                    'shiftType' => (($type == 'tire')? 'a downshift' : 'an upshift'),
                    'step' => $cost + 1,
                    'cost' => $cost,
                    'tokenType' => $type,
                    'tokensAmt' => $tokenExpense
                ));
            }

            $sql = "UPDATE player
                    SET player_current_gear = $n
                    WHERE player_id = $id";
        
            self::DbQuery($sql);

            self::notifyAllPlayers('declareGear', clienttranslate('${player_name} will use the ${n}th gear on their next turn'), array(
                'player_name' => self::getActivePlayerName(),
                'player_id' => self::getActivePlayerId(),
                'n' => $n,
                ) 
            );
        }

        $this->gamestate->nextState();
    }

    function placeGearVector($position) {

        if ($this->checkAction('placeGearVector')) {

            foreach (self::argGearVectorPlacement()['positions'] as $pos) {

                if ($pos['position'] == $position) {

                    if (!$pos['legal']) throw new BgaUserException('Illegal gear vector position');
                    if ($pos['denied']) throw new BgaUserException('Gear vector position denied by the previous shunking you suffered');

                    $id = self::getActivePlayerID();

                    $orientation = self::getUniqueValueFromDb("SELECT orientation FROM game_element WHERE id=$id");
                    $gear = self::getPlayerCurrentGear($id);
                    ['x'=>$x, 'y'=>$y] = $pos['vectorCoordinates'];

                    $sql = "INSERT INTO game_element (entity, id, pos_x, pos_y, orientation)
                            VALUES ('gearVector', $gear, $x, $y, $orientation)";
                    self::DbQuery($sql);

                    $tireTokens = self::getPlayerTokens($id)['tire'];

                    $optString = '';

                    if ($pos['tireCost']) {

                        if ($tireTokens == 0) throw new BgaUserException(self::_("You don't have enough Tire Tokens to do this move"));
                        
                        $sql = "UPDATE player
                                SET player_tire_tokens = player_tire_tokens -1
                                WHERE player_id = $id";
                        self::DbQuery($sql);

                        // APPLY PENALITY (NO DRAFTING ATTACK MOVES ALLOWED)
                        self::DbQuery("UPDATE penalities_and_modifiers SET NoDrafting = 1 WHERE player = $id");

                        $tireTokens -= 1;
                        $optString = ' performing a "side shift" (-1 TireToken)'; // in italian: 'scarto laterale'
                    } else $tireTokens = 0;

                    self::notifyAllPlayers('placeGearVector', clienttranslate('${player_name} placed the gear vector'.$optString), array(
                        'player_name' => self::getActivePlayerName(),
                        'player_id' => $id,
                        'x' => $x,
                        'y' => $y,
                        'direction' => $orientation,
                        'tireTokens' => $tireTokens,
                        'gear' => $gear
                    ));

                    $this->gamestate->nextState('endVectorPlacement');
                    return;
                }
            }

            throw new BgaUserException('Invalid gear vector position');
        }
    }

    function breakCar() {
        if ($this->checkAction('breakCar')) {

            // check if player has indeed no valid positionts, regardless of which state he takes this action from (car or vector placement)
            $arg = call_user_func('self::arg'.$this->gamestate->state()['name']);
            if ($arg['hasValid']) throw new BgaUserException('You cannot perform this move if you already have valid positions');

            if ($this->gamestate->state()['name'] == 'carPlacement') {
                // if called during this state, a vector has already been places so it has to be removed from db
                $sql = "DELETE FROM game_element
                        WHERE entity = 'gearVector'";
                self::DbQuery($sql);
            }            

            self::notifyAllPlayers('breakCar', clienttranslate('${player_name} had to break to avoid a collision'), array(
                'player_name' => self::getActivePlayerName()
            ));

            $this->gamestate->nextState('tryNewGearVector');
            return;
        }
    }

    function useBoost($use) {
        if ($this->checkAction('useBoost')) {

            if($use) {

                $id = self::getActivePlayerId();
                $nitroTokens = self::getPlayerTokens($id)['nitro'];

                if ($nitroTokens == 0) throw new BgaUserException(self::_("You don't have enough Nitro Tokens to do use a boost"));

                $sql = "UPDATE player
                        SET player_nitro_tokens = player_nitro_tokens -1
                        WHERE player_id = $id AND player_nitro_tokens > 0";
                self::DbQuery($sql);

                $nitroTokens += -1;

                self::notifyAllPlayers('useBoost', clienttranslate('${player_name} chose to use a boost vector to extend their car movement (-1 NitroToken)'), array(
                    'player_name' => self::getActivePlayerName(),
                    'player_id' => $id,
                    'nitroTokens' => $nitroTokens
                ));
            }

            $this->gamestate->nextState(($use)? 'use' : 'skip');
        }
    }

    function placeBoostVector($n) {

        if ($this->checkAction('placeBoostVector')) {

            ['positions'=>$boostAllPos, 'direction'=>$direction] = self::argBoostVectorPlacement();

            foreach ($boostAllPos as $pos) {

                if ($pos['length'] == $n) {

                    if (!$pos['legal']) throw new BgaUserException('Illegal boost vector lenght');

                    ['x'=>$x, 'y'=>$y] = $pos['vecCenterCoordinates'];
                    
                    $sql = "INSERT INTO game_element (entity, id, pos_x, pos_y, orientation)
                            VALUES ('boostVector', $n, $x, $y, $direction)";

                    self::DbQuery($sql);

                    self::notifyAllPlayers('chooseBoost', clienttranslate('${player_name} placed the ${n}th boost vector'), array(
                        'player_name' => self::getActivePlayerName(),
                        'player_id' => self::getActivePlayerID(),
                        'n' => $n,
                        'vecX' => $x,
                        'vecY' => $y,
                        'direction' => $direction,
                    ));

                    $this->gamestate->nextState();
                    return;
                }
            }

            throw new BgaVisibleSystemException('Invalid boost length');
        }
    }

    function placeCar($position, $direction) {

        if ($this->checkAction('placeCar')) {

            $allPos = self::argCarPlacement()['positions'];

            foreach ($allPos as $pos) {
                
                if ($pos['position'] == $position) {

                    if (!$pos['legal']) throw new BgaUserException('Illegal car position');
                    if ($pos['denied']) throw new BgaUserException('Car position denied by the previous shunking you suffered');

                    $allDir = $pos['directions'];

                    foreach ($allDir as $dir) {
                        
                        if ($dir['direction'] == $direction) {

                            $id = self::getActivePlayerId();
                            
                            $tireTokens = self::getPlayerTokens($id)['tire'];

                            $optString = '';

                            if ($dir['black']) {

                                if (self::getUniqueValueFromDb("SELECT NoBlackMov FROM penalities_and_modifiers WHERE player = $id"))
                                    throw new BgaUserException(self::_('You cannot select "black moves" after an Emergency Break'));

                                if ($tireTokens == 0)
                                    throw new BgaUserException(self::_("You don't have enough Tire Tokens to do this move"));
                                
                                $sql = "UPDATE player
                                        SET player_tire_tokens = player_tire_tokens -1
                                        WHERE player_id = $id";
                                self::DbQuery($sql);

                                // APPLY PENALITY (NO DRAFTING ATTACK MOVES ALLOWED)
                                self::DbQuery("UPDATE penalities_and_modifiers SET NoDrafting = 1 WHERE player = $id");

                                $tireTokens--;
                                $optString = ' performing a "black" move (-1 TireToken)';
                            }

                            ['x'=>$x, 'y'=>$y] = $pos['coordinates'];
                            $rotation = $dir['rotation'];
                            $orientation = self::getUniqueValueFromDb("SELECT orientation FROM game_element WHERE id = $id");
                            $orientation = ($orientation + $rotation + 8) % 8;

                            $sql = "UPDATE game_element
                                    SET pos_x = $x, pos_y = $y, orientation = $orientation
                                    WHERE id = $id";
                            self::DbQuery($sql);

                            $sql = "DELETE FROM game_element
                                    WHERE entity = 'gearVector' OR entity = 'boostVector'";
                            self::DbQuery($sql);

                            self::notifyAllPlayers('placeCar', clienttranslate('${player_name} placed their car'.$optString), array(
                                'player_name' => self::getActivePlayerName(),
                                'player_id' => $id,
                                'x' => $x,
                                'y' => $y,
                                'rotation' => $rotation,
                                'tireTokens' => $tireTokens,
                            ));

                            $this->gamestate->nextState('endMovement'); /* (self::availableAttackManeuvers())? 'attack' : */
                            return;
                        }
                    }

                    throw new BgaVisibleSystemException('Invalid car direction');

                }
            }

            throw new BgaUserException('Invalid car position');
        }
    }

    function engageManeuver($enemy, $action) {
        if ($this->checkAction('engageManeuver')) {
            
            $args = self::argAttackManeuvers();
            $id = self::getActivePlayerId();

            if (is_null($args['maneuvers'][$enemy][$action])) throw new BgaUserException('Invalid selected action');

            $penalities = self::getObjectFromDb("SELECT NoDrafting, NoAttackMov FROM penalities_and_modifiers WHERE player = $id");
            if ($penalities['NoAttackMov']) throw new BgaUserException('You are currently restricted from performing any action maneuver');
            if (($action == 'drafting' || $action == 'push' || $action == 'slingshot') && $penalities['NoDrafting']) throw new BgaUserException('You cannot perform drafting maneuvers after speding tire tokens during your movement phase');

            ['x'=>$x, 'y'=>$y] = $args['maneuvers'][$enemy][$action]['attPos'];
            self::dbQuery("UPDATE game_element SET pos_x = $x, pos_y = $y WHERE id = $id");

            $nitroTokens = null; // needed for slingshot

            switch ($action) {
                case 'drafting':
                    $desc = clienttranslate('${player_name} took the slipstream of ${player_name2}');                
                    break;

                case 'push':
                    $desc = clienttranslate('${player_name} pushed ${player_name2} form behind');
                    self::dbQuery("UPDATE penalities_and_modifiers SET NoShiftDown = 1 WHERE player = $enemy");
                    break;

                case 'slingshot':
                    // SHOULD ALSO CHECK IF SLINGSHOT MOVE HAS VALID POSITIONS
                    // > NOPE, PLAYER SHOULD AVOID TO CLICK THAT ACTION IF HE THINKS THERE WILL NOT BE ANY AVAILABLE POSITION

                    $nitroTokens = self::getPlayerTokens($id)['nitro'] - 1;
                    if ($nitroTokens < 0) throw new BgaUserException("You don't have enough Nitro Tokens to perform this action");
                    self::dbQuery("UPDATE player SET player_nitro_tokens = $nitroTokens WHERE player_id = $id");

                    self::dbQUery("UPDATE player SET player_slingshot_target = $enemy");
                    
                    $desc = clienttranslate('${player_name} chose to perform a Slingshot maneuver (-1 Nitro Token) while drafting behind ${player_name2}');
                    break;

                case 'leftShunk':
                    $desc = clienttranslate('${player_name} shunked ${player_name2} from the left');
                    self::dbQuery("UPDATE penalities_and_modifiers SET DeniedSideLeft = 1 WHERE player = $enemy");
                    break;

                case 'rightShunk':
                    $desc = clienttranslate('${player_name} shunked ${player_name2} from the right');
                    self::dbQuery("UPDATE penalities_and_modifiers SET DeniedSideRight = 1 WHERE player = $enemy");
                    break;
            }

            self::notifyAllPlayers('engageManeuver',$desc,array(
                'player_name' => self::getActivePlayerName(),
                'player_id' => $id,
                'player_name2' => self::getPlayerNameById($enemy),
                'attackPos' => $args['maneuvers'][$enemy][$action]['attPos'],
                'nitroTokens' => $nitroTokens
            ));

            $this->gamestate->nextState(($action == 'slingshot')? 'slingshot' : 'completeManeuver');
        }
    }

    function chooseSlingshotPosition($pos) {
        if ($this->checkAction('chooseSlingshotPosition')) {

            $id = self::getActivePlayerId();
            $args = self::argSlingshotMovement();

            if (!$args['slingshotPos'][$pos]['valid']) throw new BgaUserException('Invalid Slingshot position');

            ['x'=>$x,'y'=>$y] = $args['slingshotPos'][$pos]['pos'];
            self::dbQuery("UPDATE game_element SET pos_x = $x, pos_y = $y WHERE id = $id");

            self::notifyAllPlayers('chooseSlingshotPosition',clienttranslate('${player_name} completed the slingshot maneuver, overtaking ${player_name2}'),array(
                'player_name' => self::getActivePlayerName(),
                'player_id' => self::getActivePlayerId(),
                'player_name2' => self::getPlayerNameById(self::getUniqueValueFromDb("SELECT player_slingshot_target FROM player WHERE player_id = $id")),
                'slingshotPos' => $args['slingshotPos'][$pos]['pos']
            ));

            $this->gamestate->nextState();
        }
    }

    function skipAttack() {
        if ($this->checkAction('skipAttack')) {
            self::notifyAllPlayers('skipAttack',clienttranslate('${player_name} did not perform any attack maneuver'),array(
                'player_name' => self::getActivePlayerName(),
                'player_id' => self::getActivePlayerId()
            ));

            $this->gamestate->nextState('noManeuver');
        }
    }

    #endregion
    
    //++++++++++++++++++++++//
    // STATE ARGS FUNCTIONS //
    //++++++++++++++++++++++//
    #region state args

    // [functions that extract data (somme kind of associative array) for client to read during a certain game state. name should match the one specified on states.inc.php]

    // returns coordinates and useful data to position starting placement area. rotation independent
    function argFirstPlayerPositioning() {
        
        $sql = "SELECT pos_x x, pos_y y, orientation dir
                FROM game_element
                WHERE entity = 'pitwall'";
        $pitwall = self::getObjectFromDb($sql);

        $pitwall = new VektoraceVector (new VektoracePoint($pitwall['x'], $pitwall['y']), $pitwall['dir'], 4);
        $anchorVertex = $pitwall->getBottomOct()->getVertices()[1];
        
        $windowCenter = clone $anchorVertex;

        $placementWindowSize = array('width' => VektoraceOctagon::getOctProperties()['size'], 'height' => VektoraceOctagon::getOctProperties()['size']*5);
        
        $ro = $placementWindowSize['width']/2;
        $omg = ($pitwall->getDirection()-4) * M_PI_4;
        $windowCenter->translate($ro*cos($omg), $ro*sin($omg));

        $ro = $placementWindowSize['height']/2;
        $omg = ($pitwall->getDirection()-2) * M_PI_4;
        $windowCenter->translate($ro*cos($omg), $ro*sin($omg));

        ///
        /* $allv = $pitwall->getBottomOct()->getVertices();
        foreach ($allv as $i => $v) {
            $allv[$i] = $v->coordinates();
        } */
        ///

        return array("anchorPos" => $anchorVertex->coordinates(), "rotation" => 4 - $pitwall->getDirection(), 'center' => $windowCenter->coordinates()/* , 'debug' => array('windowSize' => $placementWindowSize) */);
    }

    // returns coordinates and useful data for all available (valid and not) flying start positition for each possible reference car
    function argFlyingStartPositioning() {

        $activePlayerTurnPosition = self::getPlayerTurnPos(self::getActivePlayerId());
        
        $allpos = array();
        foreach (self::loadPlayersBasicInfos() as $id => $playerInfos) {
            
            if ($playerInfos['player_no'] < $activePlayerTurnPosition) // take only positions from cars in front
                $allpos[$id] = array(
                    'coordinates' => self::getPlayerCarOctagon($id)->getCenter()->coordinates(),
                    'positions' => self::getPlayerCarOctagon($id)->flyingStartPositions(),
                    'hasValid' => false
                );
        }

        // -- invalid pos check --
        // a position is invalid if:
        // - it intersect with the pitlane
        // - it intersect with a curve 
        // - it intersect with an already palced car
        // - it is not behind the car ahead in the turn order (in respect to the car's nose line).

        $playerBefore = self::getPlayerTurnPosNumber($activePlayerTurnPosition-1); 

        foreach ($allpos as &$refpos) { // for each reference car on the board

            $positions = array();
            foreach (array_values($refpos['positions']) as $pos) { // for each position of the reference car

                $playerCar = self::getPlayerCarOctagon($playerBefore); // construct octagon from ahead player's position
                $posOct = new VektoraceOctagon($pos); // construct octagon of current position

                $vertices = $posOct->getVertices();
                foreach ($vertices as &$v) {
                    $v = $v->coordinates();
                } unset($v);

                // if pos is not behind or a collision is detected, report it as invalid
                $positions[] = array(
                    'coordinates' => $pos->coordinates(),
                    'vertices' => $vertices,
                    'valid' => ($posOct->isBehind($playerCar) && !self::detectCollision($posOct))
                );
            }

            $refpos['positions'] = $positions;

            foreach ($positions as $pos) {
                if ($pos['valid']) {
                    $refpos['hasValid'] = true;
                    break;
                }
            }

        } unset($refpos);

        return array ('positions' => $allpos);
    }

    // returns current token amount for active player
    function argTokenAmountChoice() {

        $sql = "SELECT player_tire_tokens tire, player_nitro_tokens nitro
                FROM player
                WHERE player_id = ".self::getActivePlayerId();

        $tokens = self::getObjectFromDB($sql);

        return array('tire' => $tokens['tire'], 'nitro' => $tokens['nitro'], 'amount'=> 8);
    }

    function argGreenLight() {
        return array('gears' => array('unavail','unavail','avail','avail','avail'));
    }

    // returns coordinates and useful data to position vector adjacent to the player car
    function argGearVectorPlacement($predictFromGear=null) {

        $id = self::getActivePlayerId();

        $playerCar = self::getPlayerCarOctagon($id);

        $currentGear = self::getPlayerCurrentGear($id);
        if (!is_null($predictFromGear)) $currentGear = $predictFromGear;

        $direction = $playerCar->getDirection();
        
        $positions = array();
        $posNames = array('right-side','right','front','left','left-side');

        $deniedSide = self::getObjectFromDb("SELECT DeniedSideLeft L, DeniedSideRight R FROM penalities_and_modifiers WHERE player = $id");

        // iter through all 5 adjacent octagon
        foreach ($playerCar->getAdjacentOctagons(5) as $i => $anchorPos) {

            // construct vector from that anchor position
            $vector = new VektoraceVector($anchorPos, $direction, $currentGear, 'bottom');

            // return vector center to make client easly display it, along with anchor pos for selection octagon, and special properties flag
            $positions[] = array(
                'position' => $posNames[$i],
                'anchorCoordinates' => $anchorPos->coordinates(),
                'vectorCoordinates' => $vector->getCenter()->coordinates(),
                'tireCost' => ($i == 0 || $i == 4), // pos 0 and 4 are right-side and left-side respectevly, as AdjacentOctagons() returns position in counter clockwise fashion
                'legal' => !self::detectCollision($vector,true),
                'denied' => ($i < 2 && $deniedSide['R']) || ($i > 2 && $deniedSide['L'])
            );
        }

        $hasValid = false;

        foreach ($positions as $pos) {
            if ($pos['legal'] && !$pos['denied'] && !($pos['tireCost'] && self::getPlayerTokens(self::getActivePlayerId())['tire']<1)) {
                $hasValid = true;
                break;
            }
        }

        return array('positions' => $positions, 'direction' => $direction, 'gear' => $currentGear, 'hasValid' => $hasValid);
    }

    function argEmergencyBreak() {

        $playerCar = self::getObjectFromDb("SELECT pos_x x, pos_y y, orientation dir FROM game_element WHERE id = ".$id = self::getActivePlayerId());

        $carOct = new VektoraceOctagon(new VektoracePoint($playerCar['x'],$playerCar['y']), $playerCar['dir']);
        return array('directionArrows' => $carOct->getAdjacentOctagons(3));
    }

    // works similarly to method above, but returns adjacent octagons in a chain to get a number of octagon straight in front of each others
    function argBoostVectorPlacement() {

        $gearVec = self::getPlacedVector('gear');
        $gear = $gearVec->getLength();

        $next = $gearVec->getTopOct();
        $direction = $gearVec->getDirection();

        $positions = array();
        for ($i=0; $i<$gear-1; $i++) {

            $vecTop = $next->getAdjacentOctagons(1);
            $vector = new VektoraceVector($vecTop, $direction, $i+1, 'top');
            $next = new VektoraceOctagon($vecTop, $direction);

            $positions[] = array(
                'vecTopCoordinates' => $vecTop->coordinates(),
                'vecCenterCoordinates' => $vector->getCenter()->coordinates(),
                'length' => $i+1,
                'legal' => !self::detectCollision($vector,true) && self::argCarPlacement($vector, true)['hasValid']  // leagl/valid, as it also checks if this particular boost lenght produces at least one vaild position
            );
        }

        $hasValid = false;

        foreach ($positions as $pos) {
            if ($pos['legal']) {
                $hasValid = true;
                break;
            }
        }

        return array('positions' => $positions, 'direction' => $direction, 'hasValid' => $hasValid);
    }

    // works as every positioning arguments method, but also adds information about rotation arrows placements (treated simply as adjacent octagons) and handles restriction on car possible directions and positioning
    function argCarPlacement($predictFromVector=null,$isPredBoost=false) {

        $gear = self::getPlacedVector();
        $boost = self::getPlacedVector('boost');

        $topAnchor;
        $n;
        $isBoost;

        if (!is_null($predictFromVector)) {
            $topAnchor = $predictFromVector->getTopOct();
            $n = $predictFromVector->getLength();
            $isBoost = $isPredBoost;
        } else {

            if (is_null($boost)) {
                $topAnchor = $gear->getTopOct();
                $n = $gear->getLength();
                $isBoost = false;  
            } else {
                $topAnchor = $boost->getTopOct();
                $n = $boost->getLength();
                $isBoost = true;
            }
        }

        $dir = $topAnchor->getDirection();

        $positions = array();
        $posNames = array('right-side','right','front','left','left-side');

        $id = self::getActivePlayerId();
        $deniedSide = self::getObjectFromDb("SELECT DeniedSideLeft L, DeniedSideRight R FROM penalities_and_modifiers WHERE player = $id");

        foreach ($topAnchor->getAdjacentOctagons(5) as $i => $carPos) {

            $carOct = new VektoraceOctagon($carPos, $dir);
            $directions = array();
            $dirNames = array('right', 'straight', 'left');

            foreach ($carOct->getAdjacentOctagons(3) as $j => $arrowPos) {
                
                if (!($i==0 && $j==2) && !($i==4 && $j==0))
                    $directions[] = array(
                        'direction' => $dirNames[$j],
                        'coordinates' => $arrowPos->coordinates(),
                        'rotation' => $j-1,
                        'black' => $i==0 || $i==4 || ($i==1 && $j==2) || ($i==3 && $j==0)
                    );
            }

            $positions[] = array(
                'position' => $posNames[$i],
                'coordinates' => $carPos->coordinates(),
                'directions' => $directions,
                'tireCost' => $i==0 || $i==4,
                'legal' => !self::detectCollision($carOct),
                'denied' => ($i < 2 && $deniedSide['R']) || ($i > 2 && $deniedSide['L'])
            );
        }

        // hello, mess
        if ($n == 1 || $isBoost) {
            unset($positions[0]);
            unset($positions[4]);
        }

        if ($isBoost) {
            unset($positions[1]['directions'][2]);
            unset($positions[3]['directions'][0]);

            if($n>1) {
                unset($positions[1]['directions'][0]);
                unset($positions[3]['directions'][2]);
            }

            if($n>2) {
                unset($positions[1]);
                unset($positions[3]);
            }

            if($n>3) {
                unset($positions[2]['directions'][0]);
                unset($positions[2]['directions'][2]);
            }
        }

        $hasValid = false;

        // always easier to return non associative arrays for lists of positions, so that js can easly iterate through them
        $positions = array_values($positions);
        foreach ($positions as $i => $pos) {
            $positions[$i]['directions'] = array_values($positions[$i]['directions']);

            if ($pos['legal'] && !$pos['denied'] && !($pos['tireCost'] && (self::getPlayerTokens($id)['tire']<1 || self::getUniqueValueFromDb("SELECT NoBlackMov FROM penalities_and_modifiers WHERE player = $id"))))
            // if pos is legal and, in the case that it costs a tire token, it must not be that the player either doesn't have a token or aren't allowed to spend it 
                $hasValid = true;
        }

        return array('positions' => $positions, 'direction' => $dir, 'hasValid' => $hasValid);
    }

    /* 
    . drafting (no tire token used, min 3rd gear for both cars, same dir as enemy car, max 2 octagon distance from enemy car bottom)
    . slingshot pass (same as above, but only 1 oct max distance)
    . pushing (same as above)
    . shunting (min 2nd gear for player car only, same dir as enemy car, max 1 oct distance from enemey car bottom sides) 
    */
    // COLLISION SHOULD CHECK CAR NOSE NOT WHOLE OCTAGON BASE
    function argAttackManeuvers() {

        $sql = "SELECT id, pos_x x, pos_y y, orientation dir
                FROM game_element
                WHERE entity = 'car'";
        $cars = self::getObjectListFromDb($sql);

        $playerId = self::getActivePlayerId();
        $playerCar = self::getPlayerCarOctagon($playerId);

        $maneuvers = array();
        $hasAttMovs = false;
        $reason = '';

        $penalities = self::getObjectFromDb("SELECT NoDrafting, NoAttackMov FROM penalities_and_modifiers WHERE player = $playerId");
        if ($penalities['NoAttackMov']) $reason = clienttranslate('You are currently restricted from performing attack maneuvers');
        else {
            foreach ($cars as $i => $car) {
                
                $enemyId = $car['id'];
                $enemyCar = new VektoraceOctagon(new VektoracePoint($car['x'], $car['y']), $car['dir']);

                $currGear = self::getCollectionFromDb("SELECT player_id id, player_current_gear gear FROM player WHERE player_id = $playerId OR player_id = $enemyId", true);

                if ($enemyId != $playerId && $enemyCar->overtake($playerCar)) {

                    $maneuvers[$enemyId] = array();
                    
                    // CHECK FOR DRAFTING MANEUVERS
                    if ($penalities['NoDrafting']) $reason = clienttranslate('You cannot engage in drafting maneuvers after spending tire tokens during the movement phase');
                    else {

                        if ($currGear[$playerId] >= 3 && $currGear[$enemyId] >= 3 && $playerCar->getDirection() == $enemyCar->getDirection()) {

                            $range2detectorVec = new VektoraceVector($enemyCar->getAdjacentOctagons(1,true), $enemyCar->getDirection(), 2, 'top');
                            if ($playerCar->collidesWithVector($range2detectorVec, true)) {

                                $posOct = $range2detectorVec->getTopOct();
                                if (!self::detectCollision($posOct, false, array($playerId)))
                                    $maneuvers[$enemyId]['drafting'] = array('name' => clienttranslate('Drafting'), 'attPos' => $posOct->getCenter()->coordinates(), 'vecPos' => $range2detectorVec->getCenter()->coordinates());
                                
                                $range1detectorOct = new VektoraceOctagon($enemyCar->getAdjacentOctagons(1,true), $enemyCar->getDirection());
                                if ($playerCar->collidesWith($range1detectorOct, true)) {

                                    if (!self::detectCollision($range1detectorOct, false, array($playerId))) {
                                        $maneuvers[$enemyId]['push'] = array('name' => clienttranslate('Push'), 'attPos' => $range1detectorOct->getCenter()->coordinates());
                                        $maneuvers[$enemyId]['slingshot'] = array('name' => clienttranslate('Slingshot'), 'attPos' => $range1detectorOct->getCenter()->coordinates());
                                    }
                                }
                            }
                        }
                    }

                    // CHECK FOR SHUNKING MANEUVER
                    if ($currGear[$playerId] >= 2 && $currGear[$enemyId] >= 2 && $playerCar->getDirection() == $enemyCar->getDirection()) {
                        
                        $sidesCenters = $enemyCar->getAdjacentOctagons(3,true);
                        $leftsideDetectorOct = new VektoraceOctagon($sidesCenters[0], $enemyCar->getDirection());
                        $rightsideDetectorOct = new VektoraceOctagon($sidesCenters[2], $enemyCar->getDirection());

                        if ($playerCar->collidesWith($leftsideDetectorOct, true)) {
                            if (!self::detectCollision($leftsideDetectorOct, false, array($playerId)))
                                $maneuvers[$enemyId]['leftShunk'] = array('name' => clienttranslate('Left Shunk'), 'attPos' => $leftsideDetectorOct->getCenter()->coordinates());
                        }

                        if ($playerCar->collidesWith($rightsideDetectorOct, true)) {
                            if (!self::detectCollision($rightsideDetectorOct, false, array($playerId))) {
                                $maneuvers[$enemyId]['rightShunk'] = array('name' => clienttranslate('Right Shunk'), 'attPos' => $rightsideDetectorOct->getCenter()->coordinates());

                                /* self::dump("// DUMP PLAYER ID", $playerId);
                                self::dump("// DUMP PLAYER CAR CENTER", $playerCar->getCenter());
                                self::dump("// DUMP PLAYER CAR VS", $playerCar->getVertices());
                                self::dump("// DUMP ID OF ENEMY", $enemyId);
                                self::dump("// DUMP DETECTOR CENTER", $rightsideDetectorOct->getCenter());
                                self::dump("// DUMP DETECTOR VS", $rightsideDetectorOct->getVertices()); */

                            }
                        }
                    } 

                    if (count($maneuvers[$enemyId])>0) $hasAttMovs = true;
                    else unset($maneuvers[$enemyId]);
                }
            }

            if (!$hasAttMovs && $reason == '') $reason = clienttranslate('No players in range');
        }

        return array("maneuvers" => $maneuvers, 'noAttReason' => $reason/* , 'otherplayer' => '', 'otherplayer_id' => '' */);
    }

    function argSlingshotMovement() {

        $slingshotPos = array();
        $enemyCar = self::getPlayerCarOctagon(self::getUniqueValueFromDb("SELECT player_slingshot_target FROM player WHERE player_id = ".self::getActivePlayerId()));

        // slingshot pos are the 3 adjacent position in front of enemy car
        $hasValid = false;
        foreach ($enemyCar->getAdjacentOctagons(3) as $pos) {
            $posOct = new VektoraceOctagon($pos);
            $valid = !self::detectCollision($posOct);
            if ($valid) $hasValid = true;
            $slingshotPos[] = array(
                'pos' => $pos->coordinates(),
                'valid' => $valid
            );
        }

        // if none is valid it could be that another car is already in front of it
        // player can then position his car on either side of the car already in front
        if (!$hasValid) {
            ['x'=>$x, 'y'=>$y] = $enemyCar->getAdjacentOctagons(1)->coordinates();
            $frontCar = self::getObjectFromDb("SELECT * FROM game_element WHERE entity = 'car' AND pos_x = $x AND pos_y = $y");
            if (!is_null($frontCar)) {
                $frontCar = new VektoraceOctagon(new VektoracePoint($x,$y), $enemyCar->getDirection());
                $sidePos = $frontCar->getAdjacentOctagons(5);
                $left = $sidePos[0];
                $right = $sidePos[4];
                $leftOct = new VektoraceOctagon($left);
                $valid = $hasValid = !self::detectCollision($leftOct);
                
                $slingshotPos[] = array(
                    'pos' => $left->coordinates(),
                    'valid' => $valid
                );
                $rightOct = new VektoraceOctagon($right);
                $valid = $hasValid = !self::detectCollision($rightOct);
                
                $slingshotPos[] = array(
                    'pos' => $right->coordinates(),
                    'valid' => $valid
                );
            }
        }

        return array('slingshotPos' => $slingshotPos, 'hasValid' => $hasValid);
    }

    // return current gear. TODO: handle special cases and restrictions
    function argFutureGearDeclaration() {

        $curr = self::getPlayerCurrentGear(self::getActivePlayerId());
        $noShift = self::getObjectFromDb("SELECT NoShiftUp up, NoShiftDown down FROM penalities_and_modifiers WHERE player = ".self::getActivePlayerId());

        $gears = array();
        for ($i=0; $i<5; $i++) { 
            switch ($i+1 <=> $curr) {

                case -1: 
                    if ($noShift['down']) $gears[] = 'denied';
                    else $gears[] = ($i+1 - $curr == -1)? 'avail' : 'tireCost'; // if downshift is greater than 1, gear selection costs +1 tire token (for each step down)
                    break;

                case 0: $gears[] = 'curr';
                    break;

                case 1: 
                    if ($noShift['up']) $gears[] = 'denied';
                    else $gears[] = ($i+1 - $curr == 1)? 'avail' : 'nitroCost'; // if upshift is greater than 1, gear selection costs +1 nitro token (for each step up)
                    break;
            }
        }

        return array('gears' => $gears);
    }

    #endregion

    //++++++++++++++++++++++++//
    // STATE ACTION FUNCTIONS //
    //++++++++++++++++++++++++//
    #region state actions

    // [function called when entering a state (that specifies it) to perform some kind of action]
    
    // gives turn to next player for car positioning or jumps to green light phase
    function stNextPositioning() {
        $player_id = self::getActivePlayerId();
        $next_player_id = self::getPlayerAfter($player_id); // error?

        /* $this->giveExtraTime($next_player_id);
        $this->incStat(1, 'turns_number', $next_player_id);
        $this->incStat(1, 'turns_number'); */

        $this->gamestate->changeActivePlayer($next_player_id);

        $np_turnpos = self::getPlayerTurnPos($next_player_id);

        // if next player is first player
        if ($np_turnpos == 1) {
            $this->gamestate->nextState('gameStart');
        } else {
            // else, keep positioning
            $this->gamestate->nextState('nextPositioningPlayer');
        }
    }

    function stEmergencyBreak() {

        $id = self::getActivePlayerId();

        $shiftedGear = self::getPlayerCurrentGear($id) -1;
        $tireExpense = 1;
        $insuffTokens = false;

        while ($shiftedGear > 1) {
            $args = self::argGearVectorPlacement($shiftedGear);
            if ($args['hasValid']) {

                // CHECK FOR AVAILABLE TOKENS AND UPDATE AMOUNT
                $tireTokens = self::getPlayerTokens($id)['tire'] - $tireExpense;
                $tireTokens -= $tireExpense;

                // if tokens insufficent break loop, car will simply stop. mem bool val to notify player reason
                if ($tireTokens < 0) {
                    $insuffTokens = true;
                    break;
                }

                self::DbQuery("UPDATE player SET player_tire_tokens = player_tire_tokens-$tireExpense WHERE player = $id");

                // UPDATE NEW GEAR
                $sql = "UPDATE player
                        SET player_current_gear = $shiftedGear
                        WHERE player_id = $id";
                self::DbQuery();

                // APPLY PENALITY (NO BLACK MOVES, NO ATTACK MANEUVERS, NO SHIFT UP)
                self::DbQuery("UPDATE penalities_and_modifiers SET NoBlackMov = 1, NoAttackMov = 1, NoShiftUp = 1 WHERE player = $id");

                // JUMP BACK TO VECTOR PLACEMENT PHASE
                $this->gamestate->nextState('gearVectorPlacement');
                return;
            }
            $tireExpense ++;
            $shiftedGear --;
        } // if reaches 0 then car will completly stot (not move for one turn)

        // car will start next turn on gear 1
        $sql = "UPDATE player
                SET player_current_gear = 1
                WHERE player_id = ".self::getActivePlayerId();
        
        self::DbQuery($sql);

        // a rotation is still allowed, so state does not jump (args contain rotation arrows data)
    }

    function stAttackManeuvers() {
        $args = self::argAttackManeuvers();
        if (count($args['maneuvers'])<1) {
            self::notifyPlayer(self::getActivePlayerId(),'cannotAttack',clienttranslate('You cannot perform any attack move this turn. ${reason}'), array('reason' => $args['noAttReason']));
            $this->gamestate->nextState('noManeuver');
        }
    }

    function stSlingshotMovement() {
        $args = self::argSlingshotMovement();

        if (!$args['hasValid']) {
            self::notifyPlayer(self::getActivePlayerId(),'cannotSlingshot',clienttranslate('There are no available Slingshot posititons'), array());
            $this->gamestate->nextState();
        }
    }

    // TODO
    function stEndOfMovementSpecialEvents() {
        $this->gamestate->nextState();
    }

    // gives turn to next player for car movement or recalculates turn order if all player have moved their car
    function stNextPlayer() {
        $player_id = self::getActivePlayerId();

        // this will reset everything. BoxBox probably needs to remain
        self::dbQuery("DELETE FROM penalities_and_modifiers WHERE player = $player_id");
        self::DbQuery("INSERT INTO penalities_and_modifiers (player) VALUES ($player_id)");

        $np_id = self::getPlayerAfterCustom($player_id);

        /* $this->giveExtraTime($next_player_id);
        $this->incStat(1, 'turns_number', $next_player_id);
        $this->incStat(1, 'turns_number'); */

        if (self::getPlayerTurnPos($np_id) == 1) {

            $isChanged = self::newTurnOrder();
            
            $optString = '';
            if ($isChanged) $optString = ' The turn order has changed.';

            $sql = "SELECT player_id, player_turn_position FROM player";
            $turnOrder = self::getCollectionFromDb($sql, true);

            $this->gamestate->changeActivePlayer(self::getPlayerTurnPosNumber(1));

            self::notifyAllPlayers('nextRoundTurnOrder', clienttranslate('A new game round begins.'.$optString), $turnOrder);

        } else {
            $this->gamestate->changeActivePlayer($np_id);
        }

        $this->gamestate->nextState();
    }

    #endregion

    //+++++++++++++++//
    // ZOMBIE SYSTEM //
    //+++++++++++++++//
    #region zombie

    // [advance stuff for when a player quit]

    /* zombieTurn:
     *
     * This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
     * You can do whatever you want in order to make sure the turn of this player ends appropriately
     * (ex: pass).
     * 
     * Important: your zombie code will be called when the player leaves the game. This action is triggered
     * from the main site and propagated to the gameserver from a server, not from a browser.
     * As a consequence, there is no current player associated to this action. In your zombieTurn function,
     * you must _never_ use getCurrentPlayerId() or getCurrentPlayerName(), otherwise it will fail with a "Not logged" error message.
     */
    function zombieTurn($state, $active_player) {
    	$statename = $state['name'];
    	
        if ($state['type'] === "activeplayer") {
            switch ($statename) {
                default:
                    $this->gamestate->nextState( "zombiePass" );
                	break;
            }

            return;
        }

        if ($state['type'] === "multipleactiveplayer") {
            // Make sure player is in a non blocking status for role turn
            $this->gamestate->setPlayerNonMultiactive( $active_player, '' );
            
            return;
        }

        throw new feException( "Zombie mode not supported at this game state: ".$statename );
    }

    #endregion
    
    //+++++++++++++++++++//
    // DB VERSION UPDATE //
    //+++++++++++++++++++//
    #region db update

    /* upgradeTableDb:
     * 
     * You don't have to care about this until your game has been published on BGA.
     * Once your game is on BGA, this method is called everytime the system detects a game running with your old
     * Database scheme.
     * In this case, if you change your Database scheme, you just have to apply the needed changes in order to
     * update the game database and allow the game to continue to run with your new version.
     */   
    function upgradeTableDb($from_version) {
        // $from_version is the current version of this game database, in numerical form.
        // For example, if the game was running with a release of your game named "140430-1345",
        // $from_version is equal to 1404301345
        
    /* Example:
     *  if( $from_version <= 1404301345 ) {
     *      // ! important ! Use DBPREFIX_<table_name> for all tables
     *
     *      $sql = "ALTER TABLE DBPREFIX_xxxxxxx ....";
     *      self::applyDbUpgradeToAllDB( $sql );
     *  }
     *  
     * if( $from_version <= 1405061421 ) {
     *      // ! important ! Use DBPREFIX_<table_name> for all tables
     *
     *      $sql = "CREATE TABLE DBPREFIX_xxxxxxx ....";
     *      self::applyDbUpgradeToAllDB( $sql );
     *  }
     *  // Please add your future database scheme changes here
     */

    }  
    
    #endregion
}
