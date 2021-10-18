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
        }

        $sql .= implode( $values, ',' );
        self::DbQuery( $sql );

        $sql = "SELECT player_id, player_no
                FROM player";
        $startingOrder = self::getCollectionFromDb($sql,TRUE);
        self::reattributeNewTurnOrder($startingOrder);

        self::reattributeColorsBasedOnPreferences( $players, $gameinfos['player_colors'] );
        self::reloadPlayersBasicInfos();

        // --- INIT GLOBAL VARIABLES ---
        // example: self::setGameStateInitialValue( 'my_first_global_variable', 0 );
        
        // --- INIT GAME STATISTICS ---
        // example: (statistics model should be first defined in stats.inc.php file)
        // self::initStat( 'table', 'table_teststat1', 0 );    // Init a table statistics
        // self::initStat( 'player', 'player_teststat1', 0 );  // Init a player statistics (for all players)

        // --- SETUP INITIAL GAME STATE ---
        $sql = "INSERT INTO table_elements (entity, id, orientation)
                VALUES ";
        
        $values = array();
        foreach( $players as $player_id => $player ) {
            $values[] = "('car',".$player_id.",4)"; // empty brackets to appends at end of array
        }
        
        $sql .= implode( $values, ',' );
        self::DbQuery( $sql );

        self::loadTrackPreset(); // custom function to set predifined track model

        // --- ACTIVATE FIRST PLAYER ---
        $this->activeNextPlayer();
    }

    // getAllDatas: method called each time client need to refresh interface and display current game state.
    //              should extract all data currently visible and accessible by the callee client (self::getCurrentPlayerId(). which is very different from active player)
    //              [!!] in vektorace, no information is ever hidder from players, so there's no use in discriminate here.
    protected function getAllDatas() {
        $result = array();
    
        $current_player_id = self::getCurrentPlayerId();

        $sql = "SELECT player_id id, player_score score, player_turn_position turn_pos, player_current_gear curr_gear
                FROM player ";
        $result['players'] = self::getCollectionFromDb( $sql );
  
        $result['table_elements'] = self::getObjectListFromDb( "SELECT * FROM table_elements" );

        $result['octagon_ref'] = VektoraceOctagon::getOctProprieties();

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
    function test() {
        $oct1 = new VektoraceOctagon(new VektoracePoint(0,0),4);
        $oct2 = new VektoraceOctagon(new VektoracePoint(150,0),4);

        self::consoleLog($oct2->isBehind($oct1));
    }
    
    // consoleLog: debug function that uses notification to log various element to js console (CAUSES BGA FRAMEWORK ERRORS)
    function consoleLog($payload) {
        self::notifyAllPlayers('logger','i have logged',$payload);
    }

    // loadTrackPreset: sets DB to match a preset of element of a test track
    function loadTrackPreset() {
        $sql = "INSERT INTO table_elements (entity, id, pos_x, pos_y, orientation)
                VALUES ('pitwall',10,0,0,4),
                       ('curve',1,-800,500,4),
                       ('curve',2,800,500,0)";
        
        self::DbQuery($sql);
    }

    // getPlayerCarOctagon: returns VektoraceOctagon object created at center pos, given by the coordinates of the players car, according to DB
    function getPlayerCarOctagon($id) {

        $sql = "SELECT pos_x, pos_y, orientation
                FROM table_elements
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

    // reattributeNewTurnOrder: takes associative array where $player_id -> $newTurnPosition and updates db accordingly
    function reattributeNewTurnOrder($neworder) {

        foreach($neworder as $player_id => $newOrderPos) {
            $sql = "UPDATE player
                    SET player_turn_position = $newOrderPos
                    WHERE player_id = $player_id";
            self::DbQuery($sql);
        }

    }

    // detectCollision: returns true if octagon collide with any element on the map
    function detectCollision(VektoraceOctagon $posOct) {

        foreach (self::getObjectListFromDb( "SELECT * FROM table_elements" ) as $i => $element) {
            
            switch ($element['entity']) {
                case 'car': 
                    if ($element['id']!=self::getActivePlayerId() && !is_null($element['pos_x']) && !is_null($element['pos_y'])) {   // check collision only if car is not at the same pos as octagon and if positions are defined  
                        $carOct = new VektoraceOctagon(new VektoracePoint($element['pos_x'],$element['pos_y']));
                        if ($posOct->collidesWith($carOct)) { return true; }
                    }

                    break;
                
                case 'curve':
                    $curveOct = new VektoraceOctagon(new VektoracePoint($element['pos_x'],$element['pos_y']));

                    if ($posOct->collidesWith($curveOct, true, $element['orientation'])) { return true; }

                    break;

                case 'pitwall':
                    if ($posOct->collidesWithPitwall()) { return true; }

                    break;
            }
        }

        return false;
    }

    #endregion

    //++++++++++++++++//
    // PLAYER ACTIONS //
    //++++++++++++++++//
    #region playera ctions

    // [functions responding to ajaxcall formatted and forwarded by action.php script. function names should always match action name]

    // selectPosition: specific function that selects and update db on new position for currently active player car.
    //                 should be repurposed to match all cases of selecting positions and cars moving
    function selectPosition($x,$y) {

        $id = self::getActivePlayerId();

        if ($this->checkAction('selectPosition')) {
            $sql = "UPDATE table_elements
                SET pos_x = $x, pos_y = $y
                WHERE id = $id";
        
            self::DbQuery($sql);

            self::notifyAllPlayers('selectPosition', clienttranslate('${player_name} chose their car starting position'), array(
                'player_id' => $id,
                'player_name' => self::getActivePlayerName(),
                'posX' => $x,
                'posY' => $y
                //'color' => self::getUniqueValueFromDB("SELECT player_color FROM player WHERE player_id=$id")
                ) 
            );
        }

        $this->gamestate->nextState();
    }

    // chooseStartingGear: server function responding to user input when a player chooses the gear vector for all players (green-light phase)
    function chooseStartingGear($n) {
        if ($this->checkAction('chooseStartingGear')) {

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

    // chooseStartingGear: basically same as before, but the gear chosen is a declaration from a single player, about his gear of choise for his next turn. thus DB is updated only for the player's line
    //                     could be merged with method above.
    function declareGear($n) {
        if ($this->checkAction('declareGear')) {
            $id = self::getActivePlayerId();

            $sql = "UPDATE player
                SET player_current_gear = $n
                WHERE player_id = $id";
        
            self::DbQuery($sql);

            self::notifyAllPlayers('declareGear', clienttranslate('${player_name} will use the ${n}th gear on their next turn'), array(
                'player_name' => self::getActivePlayerName(),
                'n' => $n,
                ) 
            );
        }

        $this->gamestate->nextState();
    }

    function completeMovement($x, $y, $vecX, $vecY, $rot, $tireCost) {

        $id = self::getActivePlayerId();

        $orientation = self::getUniqueValueFromDb("SELECT orientation FROM table_elements WHERE id=$id");

        if ($this->checkAction('completeMovement')) {

            $sql = "UPDATE table_elements
                    SET pos_x=$x, pos_y=$y, orientation=orientation+$rot
                    WHERE id = $id";
            self::DbQuery($sql);

            // DO SAME WITH NITRO TOKENS
            $optString = '';
            if ($tireCost > 0) {
                $optString .= ', spending ' . $tireCost . ' Tire Token' . ($tireCost > 1)? 's' : '';

                $sql = "UPDATE player
                        SET player_tire_tokens = player_tire_tokens -1
                        WHERE player_id = $id AND player_tire_tokens > 0";
                self::DbQuery($sql);
            }

            $sql = "SELECT player_tire_tokens
                    FROM player
                    WHERE player_id = $id";

            $tireTokens = self::getUniqueValueFromDb($sql);

            self::notifyAllPlayers('completeMovement', clienttranslate('${player_name} moved his car' . $optString), array(
                'player_name' => self::getActivePlayerName(),
                'player_id' => $id,
                'posX' => $x,
                'posY' => $y,
                'rotation' => $rot,
                'tireTokens' => $tireTokens,
                'direction' => $orientation,
                'gear' => self::getPlayerCurrentGear($id),
                'gearPos' => array('x' => $vecX, 'y' => $vecY)
            ));
        }

        $this->gamestate->nextState();
    }

    #endregion
    
    //++++++++++++++++++++++//
    // STATE ARGS FUNCTIONS //
    //++++++++++++++++++++++//
    #region state args

    // [functions that extract data (somme kind of associative array) for client to read during a certain game state. name should match the one specified on states.inc.php]

    // argPlayerPositioning: return array of positions where possible move should be highlighted. should be vailable for every game state.
    //                       if array is empty (for initial game state where player are positioning their car) it means that it's t he  first player turn, which can place his car wherever he wants, inside a certain area.
    function argPlayerPositioning() {
        
        // get active player
        // if first display area of placement
        // else display possible positioning for each car before

        // first player, may place wherever they want, as long as it's prallel to pitwall
        $activePlayerTurnPosition = self::loadPlayersBasicInfos()[self::getActivePlayerId()]['player_no'];
        
        if ($activePlayerTurnPosition == 1) return array('display' => 'positioningArea');
    
        // else
        // for each player in front, return possible positions using 'flying-start octagon'
        // as long as these are behind the nose of the car in the position before 
        // extract position for every reference car individually
        // then put all in one associative array, idexed by id of reference car

        $playerBefore;
        $allpos = array();

        foreach (self::loadPlayersBasicInfos() as $id => $playerInfos) {
            // take only positions from cars in front
            if ($playerInfos['player_no'] < $activePlayerTurnPosition) {
                if ($activePlayerTurnPosition - $playerInfos['player_no'] == 1) $playerBefore = $playerInfos['player_id'];

                $allpos[$id] = self::getPlayerCarOctagon($id)->flyingStartPositions();
            }
        }

        // -- invalid pos removal --
        // a position should not be displayed (thus not returned in the array) if:
        // - it intersect with the pitlane
        // - it intersect with a curve 
        // - it intersect with an already palced car
        // - it is in front of or parallel to (in respect to the cars nose line) any car ahed in the turn order.

        foreach ($allpos as $refcarid => $positions) { // for each reference car on the board
            foreach ($positions as $i => $pos) { // for each position of the reference car

                $playerCar = self::getPlayerCarOctagon($playerBefore);
                $posOct = new VektoraceOctagon($pos);

                // if it's behind the player in front and doesn't collide with any element on the map, keep it and cast the Point to a an array (to be readable by js script)
                if ($posOct->isBehind($playerCar) && !self::detectCollision($posOct)) $allpos[$refcarid][$i] = $pos->coordinates();
                else unset($allpos[$refcarid][$i]); // otherwise, unset it from the positions array of the reference car
            }

            if (empty($allpos[$refcarid])) unset($allpos[$refcarid]); // if at the end, a reference car has 0 valid position (is empty), unset it from the returned array
            else $allpos[$refcarid] = array_values($allpos[$refcarid]); // otherwise, extract only its values (that is, substitutes associative keys with increasing indices. because otherwise js will read it as an object and not an array)
        }

        return array ('display' => (count($allpos) == 1)? 'fsPositions' : 'chooseRef', 'positions' => $allpos);
    }

    function argPlayerMovement() {

        $playerCar = self::getPlayerCarOctagon(self::getActivePlayerId());
        $currentGear = self::getPlayerCurrentGear(self::getActivePlayerId());

        $allpos = array(); // init returned array
        foreach (array_values($playerCar->getAdjacentOctagons(5)) as $i => $vecPos) { // iter through all possible vector positions

            $vecPottomPositions = $vecPos->coordinates();

            // calc vector top octagon position. translate current pos by some geometric vector
            $vecDirection = $playerCar->getDirection();
            $p = ($currentGear-1) * VektoraceOctagon::getOctProprieties()['size']; // magnitude of translation, module of the translating vector
            $o = $vecDirection * M_PI_4; // direction of translation, angle of the translating vector
            $vecPos->translate($p*cos($o), $p*sin($o)); // apply translation to point

            // create actual octagon object to generate Adjacents from here
            $vecTopOct = new VektoraceOctagon($vecPos, $vecDirection);

            // add position attribute? verbal description of the positon of the vector relative to the player car (front,right,right-side,...)
            $allpos[$i] = array(
                'coordinates' => $vecPottomPositions,
                'tireCost' => $i==0 || $i==4
            );

            foreach (array_values($vecTopOct->getAdjacentOctagons(5)) as $j => $carPos) { // iter through all possible car positions, given the current vector position (remember: getAdjacentsOctagon returns positions from right to left (counter clockwise))

                $carOct = new VektoraceOctagon($carPos, $vecDirection);
                $carAdjacents = array_values($carOct->getAdjacentOctagons(3));
                $carAdjacents = array('R'=>$carAdjacents[0], 'F'=>$carAdjacents[1], 'L'=>$carAdjacents[2]); // map array to letters indicating orientation (Left, Forward, Right)
                // by left/right i mean a 45deg rotation to the left/right, as 90deg rotation are not allowed by the game

                $carCoordinates = $carPos->coordinates();
                
                // THIS COULD BE PROBABLY DONE WITH ONLY ONE ENTRY WITH VARIABLE PROPRIETIES
                switch ($j) {
                    case 0:
                        $allpos[$i]['carPositions'][] = array(
                            'position' => 'right-side',
                            'coordinates' => $carCoordinates,
                            'directions' => array(
                                array(
                                    'coordinates' => $carAdjacents['R']->coordinates(),
                                    'color' => 'black',
                                    'direction' => 'right'
                                ),
                                array(
                                    'coordinates' => $carAdjacents['F']->coordinates(),
                                    'color' => 'black',
                                    'direction' => 'forward'
                                ),
                            ),
                            'tireCost' => true,
                            'blocked' => false
                        );
                        break;
    
                    case 1:
                        $allpos[$i]['carPositions'][] = array(
                            'position' => 'right',
                            'coordinates' => $carCoordinates,
                            'directions' => array(
                                array(
                                    'coordinates' => $carAdjacents['R']->coordinates(),
                                    'color' => 'white',
                                    'direction' => 'right'
                                ),
                                array(
                                    'coordinates' => $carAdjacents['F']->coordinates(),
                                    'color' => 'white',
                                    'direction' => 'forward'
                                ),
                                array(
                                    'coordinates' => $carAdjacents['L']->coordinates(),
                                    'color' => 'black',
                                    'direction' => 'left'
                                ),
                            ),
                            'tireCost' => false,
                            'blocked' => false
                        );
                        break;
    
                    case 2:
                        $allpos[$i]['carPositions'][] = array(
                            'position' => 'front',
                            'coordinates' => $carCoordinates,
                            'directions' => array(
                                array(
                                    'coordinates' => $carAdjacents['R']->coordinates(),
                                    'color' => 'white',
                                    'direction' => 'right'
                                ),
                                array(
                                    'coordinates' => $carAdjacents['F']->coordinates(),
                                    'color' => 'white',
                                    'direction' => 'forward'
                                ),
                                array(
                                    'coordinates' => $carAdjacents['L']->coordinates(),
                                    'color' => 'white',
                                    'direction' => 'left'
                                ),
                            ),
                            'tireCost' => false,
                            'blocked' => false
                        );
                        break;
    
                    case 3:
                        $allpos[$i]['carPositions'][] = array(
                            'position' => 'left',
                            'coordinates' => $carCoordinates,
                            'directions' => array(
                                array(
                                    'coordinates' => $carAdjacents['R']->coordinates(),
                                    'color' => 'black',
                                    'direction' => 'right'
                                ),
                                array(
                                    'coordinates' => $carAdjacents['F']->coordinates(),
                                    'color' => 'white',
                                    'direction' => 'forward'
                                ),
                                array(
                                    'coordinates' => $carAdjacents['L']->coordinates(),
                                    'color' => 'white',
                                    'direction' => 'left'
                                ),
                            ),
                            'tireCost' => false,
                            'blocked' => false
                        );
                        break;
                        
                    case 4:
                        $allpos[$i]['carPositions'][] = array(
                            'position' => 'right',
                            'coordinates' => $carCoordinates,
                            'directions' => array(
                                array(
                                    'coordinates' => $carAdjacents['F']->coordinates(),
                                    'color' => 'black',
                                    'direction' => 'forward'
                                ),
                                array(
                                    'coordinates' => $carAdjacents['L']->coordinates(),
                                    'color' => 'black',
                                    'direction' => 'left'
                                ),
                            ),
                            'tireCost' => true,
                            'blocked' => false
                        );
                        break;
                }
            }
        }

        $boostPositions = array();
        $front_oct = $playerCar;
        for ($i=$currentGear-1; $i>0; $i--) { 
            $boostPositions[$i] = $front_oct->getAdjacentOctagons(1)[0];
            $front_oct = new VektoraceOctagon($boostPositions[$i],$playerCar->getDirection());

            // CHECK IF VECTOR WOULD COLLIDE
        }
        
        return array('positions' => $allpos, 'direction' => $vecDirection, 'currentGear' => $currentGear, 'boostPositions' => $boostPositions); 
    }

    function argAttackManeuvers() {
        return array("opponent" => '');
    }

    function argFutureGearDeclaration() {
        return array('gear' => self::getPlayerCurrentGear(self::getActivePlayerId()));
    }

    #endregion

    //++++++++++++++++++++++++//
    // STATE ACTION FUNCTIONS //
    //++++++++++++++++++++++++//
    #region state actions

    // [function called when entering a state (that specifies it) to perform some kind of action]
    
    function stNextPositioning() {
        $player_id = $this->getActivePlayerId();
        $next_player_id = $this->getPlayerAfter($player_id);

        /* $this->giveExtraTime($next_player_id);
        $this->incStat(1, 'turns_number', $next_player_id);
        $this->incStat(1, 'turns_number'); */

        $this->gamestate->changeActivePlayer($next_player_id);

        $sql = "SELECT player_turn_position
                FROM player
                WHERE player_id = $next_player_id";
        $np_turnpos = self::getUniqueValueFromDb( $sql );

        // if next player is first player
        if ($np_turnpos == 1) {
            $this->gamestate->nextState('greenLight');
        } else {
            // else, keep positioning
            $this->gamestate->nextState('nextPlayer');
        }
    }

    function stAttackManeuvers() {
        $this->gamestate->nextState();
    }

    function stCheckForMovementSpecialEvents() {
        $this->gamestate->nextState();
    }

    function stNextPlayer() {
        $player_id = $this->getActivePlayerId();
        $next_player_id = $this->getPlayerAfter($player_id);

        /* $this->giveExtraTime($next_player_id);
        $this->incStat(1, 'turns_number', $next_player_id);
        $this->incStat(1, 'turns_number'); */

        $this->gamestate->changeActivePlayer($next_player_id);
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
