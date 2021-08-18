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
require_once('modules/VTRCoctagon.php');

class VektoRace extends Table {
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
        foreach( $players as $player_id => $player )
        {
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

        // --- INIT OCTAGON SIZE REFERENCES ---

        $size = 100;
        $side = $size / (1 + 2/sqrt(2));
        $seg = $side / sqrt(2);
        $rad = sqrt(pow($size/2,2) + pow($side/2,2));

        $sql = "INSERT INTO octagon_sizes (propriety, val)
                VALUES ('size',$size), ('side',$side), ('segment',$seg), ('radius',$rad)";
        self::DbQuery( $sql );

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

        $sql = "SELECT player_id id, player_score score, player_turn_position turn_pos
                FROM player ";
        $result['players'] = self::getCollectionFromDb( $sql );
  
        $result['table_elements'] = self::getObjectListFromDb( "SELECT * FROM table_elements" );

        $result['octagon_ref'] = self::getCollectionFromDb("SELECT propriety, val FROM octagon_sizes", true);

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


//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////

    //+++++++++++++++++++//
    // UTILITY FUNCTIONS //
    //+++++++++++++++++++//

    // [general purpose function that controls the game logic]

    // test: test function to put whatever comes in handy at a given time
    function test() {
        $oct = new VTRCoctagon(0,0);

        //self::consoleLog(VTRCoctagon::getOctProprieties());
        self::consoleLog([$oct->getVertices()]);
    }
    
    // consoleLog: debug function that uses notification to log various element to js console
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

    // getPlayerCarPos: self-explainatory
    function getPlayerCarPos($id) {

        $sql = "SELECT pos_x, pos_y
                FROM table_elements
                WHERE entity = 'car' AND id = $id";

        $ret = self::getObjectFromDB($sql);
        return array($ret['pos_x'],$ret['pos_y']);
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
    
    // CHANGE THIS TO USE SPECIFIC CLASS METHOD AND NOT DB ACCESS
    function getOctagonRefMeasures() {
        return self::getCollectionFromDb("SELECT propriety, val FROM octagon_sizes", true);
    }

    // returns true if position collide with any element on the map
    function detectCollision($carpos) {
        $thisOct = new VTRCoctagon($carpos[0],$carpos[1]);
        $posStr = '('.$carpos[0].', '.$carpos[1].')';

        foreach (self::getObjectListFromDb( "SELECT * FROM table_elements" ) as $i => $element) {
            //self::consoleLog($element);

            switch ($element['entity']) {
                case 'car': 
                    if ($element['id']!=self::getActivePlayerId() && !is_null($element['pos_x']) && !is_null($element['pos_y'])) {    
                        $carOct = new VTRCoctagon($element['pos_x'],$element['pos_y']);
                        if ($thisOct->collidesWith($carOct)) { 
                            //self::consoleLog('collision between element at'.$posStr.' with car '.$element['id']);
                            return true;
                        }
                    }

                    break;
                
                case 'curve':
                    $curveOct = new VTRCoctagon($element['pos_x'],$element['pos_y']);

                    if ($thisOct->collidesWith($curveOct, true, $element['orientation'])) {
                        //self::consoleLog('collision between element at'.$posStr.' with curve '.$element['id']);
                        return true;
                    }

                    break;

                case 'pitwall':
                    if ($thisOct->collidesWithPitwall()) {
                        //self::consoleLog('collision between element at'.$posStr.' with pitwall');
                        return true;
                    }

                    break;
            }
        }

        return false;
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Player actions
////////////

    //++++++++++++++++//
    // PLAYER ACTIONS //
    //++++++++++++++++//

    // [functions responding to ajaxcall formatted and forwarded by action.php script. function names should always match action name]

    // selectPosition: specific function that selects and update db on new position for currently active player car.
    //                 should be repurposed to match all cases of selecting positions and cars moving
    function selectPosition($x,$y) {
        // should check if action is permitted
        

        // debug
        /* if (self::detectCollision(array($x,$y))) self::consoleLog('collision');
        else self::consoleLog('no collision'); */

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
    
//////////////////////////////////////////////////////////////////////////////
//////////// Game state arguments
////////////

    //++++++++++++++++++++++//
    // STATE ARGS FUNCTIONS //
    //++++++++++++++++++++++//

    // [functions that extract data (somme kind of associative array) for client to read during a certain game state. name should match the one specified on states.inc.php]

    // argPlayerPositioning: return array of positions where possible move should be highlighted. should be vailable for every game state.
    //                       if array is empty (for initial game state where player are positioning their car) it means that it's t he  first player turn, which can place his car wherever he wants, inside a certain area.
    function argPlayerPositioning() {
        
        // get active player
        // if first display area of placement
        // else display possible positioning for each car before

        $activeTurn = self::loadPlayersBasicInfos()[self::getActivePlayerId()]['player_no'];

        $playerBefore = '';

        if ($activeTurn == 1) {
            // first player, may place wherever they want, as long as it's prallel to pitwall
            // return empty -> display start positioning area
            return array();   

        } // else
        // for each player in front, return possible positions using 'flying-start octagon'
        // as long as these are behind the nose of the car in the position before 
        // extract position for every reference car individually
        // then put all in one associative array, idexed by id of reference car

        $allpos = array();
        /* $limitX = self::getPlayerCarPos(self::getPlayerBefore())[0]; // taking pos x as positioning limit. not very robust but simple to implement. might change later.
        $limitY = 0; // should get pitwall coordinates and extract some limit as to avoid positions collision with pitwall
        */

        foreach (self::loadPlayersBasicInfos() as $id => $infos) {
            // take only positions from cars in front
            if ($infos['player_no'] < $activeTurn) {
                if ($activeTurn - $infos['player_no']) $playerBefore = $infos['player_id'];

                $carpos = self::getPlayerCarPos($id);

                $oct = new VTRCoctagon($carpos[0],$carpos[1]);

                // return only unique values and without cardinal point indices
                $allpos[$id] = array_unique($oct->flyingStartPositions(2), SORT_REGULAR);
            }
        }

        // TODO, REMOVE INVALID POSITONS (those that collides with other elements or that cross certain limits)
        // a position should not be displayed (thus not returned in the array) if:
        // - it intersect with the pitlane
        // - it intersect with a curve 
        // - it intersect with an already palced car
        // - it is in front of or parallel to (in respect to the cars nose line) any car ahed in the turn order.

        foreach ($allpos as $refcarid => $positions) {
            foreach ($positions as $i => $pos) {

                $playerCar = new VTRCoctagon(...self::getPlayerCarPos($playerBefore));
                $posOct = new VTRCoctagon(...$pos);
                if ($posOct->isBehind($playerCar,4)) {
                    if (self::detectCollision($pos)) {
                        unset($allpos[$refcarid][$i]);
                    }
                } else unset($allpos[$refcarid][$i]);
            }

            if (empty($allpos[$refcarid])) unset($allpos[$refcarid]);
            else $allpos[$refcarid] = array_values($allpos[$refcarid]);
        }

        return $allpos;
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////

    //++++++++++++++++++++++++//
    // STATE ACTION FUNCTIONS //
    //++++++++++++++++++++++++//

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

//////////////////////////////////////////////////////////////////////////////
//////////// Zombie
////////////

    //+++++++++++++++//
    // ZOMBIE SYSTEM //
    //+++++++++++++++//

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
    
///////////////////////////////////////////////////////////////////////////////////:
////////// DB upgrade
//////////

    //+++++++++++++++++++//
    // DB VERSION UPDATE //
    //+++++++++++++++++++//

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
}
