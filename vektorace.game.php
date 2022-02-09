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

require_once('modules/VektoraceGameElement.php');
require_once('modules/VektoracePoint.php');
require_once('modules/VektoraceOctagon.php');
require_once('modules/VektoraceVector.php');
require_once('modules/VektoracePitwall.php');
require_once('modules/VektoraceCurve.php');
require_once('modules/VektoraceCurb.php');

class VektoRace extends Table {
    
    //+++++++++++++++++++++//
    // SETUP AND DATA INIT //
    //+++++++++++++++++++++//
    #region setup

	function __construct() {
        parent::__construct();
        
        // GAME.PHP GLOBAL VARIABLES HERE
        self::initGameStateLabels( array(
            "turn_number" => 10,
            "last_curve" => 11,
            "racing_players_number" => 12,
            "first_avail_position" => 13,
            "number_of_laps" => 100,
            "circuit_layout" => 101,
            "map_boundaries" => 102
        ));        
	}
	
    // getGameName: basic utility method used for translation and other stuff. do not modify
    protected function getGameName() {
        return "vektorace";
    }	

    // setupNewGame: called once, when a new game is initialized. this sets the initial game state according to the rules
    protected function setupNewGame( $players, $options=array()) {
        // -- LOAD TRACK
        self::loadTrackPreset(); // custom function to set predifined track model

        /* $values = ["('pitwall',10,0,0,4)"];

        for ($i=0; $i < 5; $i++) {
            $curbNum = $i+1;
            $curb = new VektoraceCurb($curbNum);
            ['x'=>$x, 'y'=>$y] = $curb->getCenter()->coordinates();
            $dir = $curb->getDirection();

            $values[] = "('curb', $curbNum, $x, $y, $dir)";
        }

        $values = implode($values,',');
        self::DbQuery("INSERT INTO game_element (entity, id, pos_x, pos_y, orientation) VALUES ".$values); */

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
        self::setGameStateInitialValue('turn_number', 0 );
        self::setGameStateInitialValue('last_curve', self::getUniqueValueFromDb("SELECT count(id) FROM game_element WHERE entity='curb'"));
        self::setGameStateInitialValue('racing_players_number', self::getPlayersNumber()); // to know how many players are actually racing (no zombies, no players who already finished race)
        self::setGameStateInitialValue('first_avail_position', 1); // to know which is the next available pos which can be conquered by the next player that crosses the finish line
        self::setGameStateInitialValue('circuit_layout', $this->gamestate->table_globals[101]);
        self::setGameStateInitialValue('map_boundaries', $this->gamestate->table_globals[102]);

        // --- INIT GAME STATISTICS ---
        // table
        self::initStat( 'table', 'turns_number', 0 ); 

        // players
        self::initStat('player', 'turns_number', 0 );
        self::initStat('player', 'pole_turns', 0 );
        self::initStat('player', 'surpasses_number', 0 );
        self::initStat('player', 'pitstop_number', 0 );
        self::initStat('player', 'brake_number', 0 );
        self::initStat('player', 'tire_used', 0 );
        self::initStat('player', 'nitro_used', 0 );
        self::initStat('player', 'attMov_performed', 0 );
        self::initStat('player', 'attMov_suffered', 0 );
        self::initStat('player', 'average_gear', 0 );
        self::initStat('player', 'boost_number', 0 );
        // self::initStat('player', 'curve_quality', 0 );


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

        $result['octagon_ref'] = VektoraceGameElement::getOctagonMeasures();

        $sql = "SELECT player, NoShiftDown push, DeniedSideLeft leftShunt, DeniedSideRight rightShunt, BoxBox boxbox, NoShiftUp brake, CarStop `stop`
                FROM penalities_and_modifiers";
        $result['penalities_and_modifiers'] = self::getCollectionFromDb($sql);
        
        $layouts = [
            1 => 'Oval',
            2 => 'Tri-Oval 1',
            3 => 'Tri-Oval 2',
            4 => 'Cross-Oval'
        ];

        $result['game_info'] = [
            'laps'  => self::getGameStateValue('number_of_laps'),
            'circuit_layout_name'  => $layouts[self::getGameStateValue('circuit_layout')],
            'circuit_layout_num' => self::getGameStateValue('circuit_layout')
        ];
        

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
        
        $firstPlayer = self::getPlayerTurnPosNumber(1);
        $playerLap = self::getPlayerLap($firstPlayer);
        $playerCurve = self::getPlayerCurve($firstPlayer);

        $lapStep = 100/self::getGameStateValue('number_of_laps');
        $curveStep = $lapStep/self::getGameStateValue('last_curve');
        $zoneStep = $curveStep/8;

        $lapPorgress = $playerLap * $lapStep;
        $curveProgress = ($playerCurve['number']-1) * $curveStep;
        $zoneProgress = max(($playerCurve['number']-1) * $zoneStep, 0);

        $progress = round($lapPorgress + $curveProgress + $zoneProgress);
        return min($progress,100);
    }

    #endregion

    //+++++++++++++++++++//
    // UTILITY FUNCTIONS //
    //+++++++++++++++++++//
    #region utility

    // [general purpose function that controls the game logic]

    // debugging functions
    // ---------------------------------
        // test: test function to put whatever comes in handy at a given time
    
        /* function test() {
            self::consoleLog(['last curve'=>self::getGameStateValue('last_curve')]);
        }

        function mapVertices() {
            $siz = VektoraceGameElement::getOctagonMeasures()['size'];
            $off = 29;
            $off2 = 3;

            $tl = new VektoracePoint(-11.5*$siz-$off, 16*$siz+$off);
            $tr = new VektoracePoint(22*$siz+$off2, 16*$siz+$off);
            $bl = new VektoracePoint(-11.5*$siz-$off, -2*$siz-$off);
            $br = new VektoracePoint(22*$siz+$off2, -2*$siz-$off);

            $ret = [
                $tl->coordinates(),
                $tr->coordinates(),
                $bl->coordinates(),
                $br->coordinates(),
            ];
            self::notifyAllPlayers('allVertices','',['points'=>$ret]);
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
                        $car = new VektoraceOctagon(new VektoracePoint($element['pos_x'],$element['pos_y']),$element['orientation']);
                        $ret['car '.$element['id']] = $car->getVertices();
                        break;

                    case 'curve':
                        $curve = new VektoraceCurve(new VektoracePoint($element['pos_x'],$element['pos_y']),$element['orientation']);
                        $ret['curve '.$element['id']] = $curve->getVertices();
                        break;

                    case 'curb':
                        $curb = new VektoraceCurb($element['id']);
                        $ret['curb '.$element['id']] = $curb->getVertices();
                        break;

                    case 'pitwall':
                        $pitwall = new VektoracePitwall(new VektoracePoint($element['pos_x'],$element['pos_y']),$element['orientation']);
                        $ret['pitwall'] = array_merge(...$pitwall->getVertices());
                        break;

                    case 'gearVector':
                    case 'boostVector':
                        $vector = new VektoraceVector(new VektoracePoint($element['pos_x'],$element['pos_y']),$element['orientation'],$element['id']);
                        $ret[$element['entity'].' '.$element['id']] = array_merge(...$vector->getVertices());
                        break;
                }
            }

            foreach ($ret as &$element) {
                foreach ($element as &$vertex) {
                    $vertex = $vertex->coordinates();
                } unset($vertex);
            } unset($element);

            self::notifyAllPlayers('allVertices','',$ret);
            return;
            
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
                        $curve = new VektoraceCurve(new VektoracePoint($element['pos_x'],$element['pos_y']),$element['orientation']);
                        $vertices = $curve->getVertices();

                        foreach ($vertices as &$v) {
                            $v = $v->coordinates();
                        } unset($v);

                        $ret['curve'.' '.$element['id']] = $vertices;
                        break;

                    case 'pitwall':
                        $pitwall = new VektoracePitwall(new VektoracePoint($element['pos_x'],$element['pos_y']),$element['orientation']);

                        $vertices = array_merge(...$pitwall->getVertices());

                        foreach ($vertices as &$v) {
                            $v = $v->coordinates();
                        } unset($v);

                        $ret['pitwall'] = $vertices;
                        break;

                    default: // vectors and boosts
                        $vector = new VektoraceVector(new VektoracePoint($element['pos_x'],$element['pos_y']),$element['orientation'],$element['id']);

                        $vertices = array_merge(...$vector->getVertices());

                        foreach ($vertices as &$v) {
                            $v = $v->coordinates();
                        } unset($v);

                        $ret[$element['entity'].' '.$element['id']] = $vertices;
                        break;
                }
            }

            self::notifyAllPlayers('allVertices','',$ret);
        }

        function testOvertake($p1, $p2) {
            $p1car = self::getPlayerCarOctagon(self::getPlayerTurnPosNumber($p1));
            $p2car = self::getPlayerCarOctagon(self::getPlayerTurnPosNumber($p2));

            self::consoleLog([
                'p1 overtakes p2' => $p1car->overtake($p2car),
                'p2 overtakes p1' => $p2car->overtake($p1car),
            ]);
        }
        
        function switchTurnPos($p1, $p2) {
            $p1id = self::getPlayerTurnPosNumber($p1);
            $p2id = self::getPlayerTurnPosNumber($p2);

            self::DbQuery("UPDATE player SET player_turn_position = $p2 WHERE player_id = $p1id");
            self::DbQuery("UPDATE player SET player_turn_position = $p1 WHERE player_id = $p2id");

            return;
        }

        function moveAllCars() {
            $x = 1000;
            $y = 0;
            $dir = 4;


            $allCars = self::getCollectionFromDb("SELECT id, pos_x x, pos_y y, orientation dir FROM game_element WHERE entity = 'car'");

            foreach ($allCars as $id => $car) {
                $newX = $car['x'] + $x;
                $newY = $car['y'] + $y;

                self::DbQuery("UPDATE game_element SET pos_x = $newX, pos_y = $newY, orientation = $dir WHERE id = $id");
            }
        }

        function assignCurve($n, $zone = 1) {
            
            self::DbQuery("UPDATE player SET player_curve_number = $n, player_curve_zone = $zone");
        }

        // loadBug: in studio, type loadBug(20762) into the table chat to load a bug report from production
        // client side JavaScript will fetch each URL below in sequence, then refresh the page
        public function loadBug($reportId) {
            $db = explode('_', self::getUniqueValueFromDB("SELECT SUBSTRING_INDEX(DATABASE(), '_', -2)"));
            $game = $db[0];
            $tableId = $db[1];
            self::notifyAllPlayers('loadBug', "Trying to load <a href='https://boardgamearena.com/bug?id=$reportId' target='_blank'>bug report $reportId</a>", [
            'urls' => [
                // Emulates "load bug report" in control panel
                "https://studio.boardgamearena.com/admin/studio/getSavedGameStateFromProduction.html?game=$game&report_id=$reportId&table_id=$tableId",
                
                // Emulates "load 1" at this table
                "https://studio.boardgamearena.com/table/table/loadSaveState.html?table=$tableId&state=1",
                
                // Calls the function below to update SQL
                "https://studio.boardgamearena.com/1/$game/$game/loadBugSQL.html?table=$tableId&report_id=$reportId",
                
                // Emulates "clear PHP cache" in control panel
                // Needed at the end because BGA is caching player info
                "https://studio.boardgamearena.com/admin/studio/clearGameserverPhpCache.html?game=$game",
            ]
            ]);
        }
        
        // loadBugSQL: in studio, this is one of the URLs triggered by loadBug() above
        public function loadBugSQL($reportId) {
            $studioPlayer = self::getCurrentPlayerId();
            $players = self::getObjectListFromDb("SELECT player_id FROM player", true);
        
            // Change for your game
            // We are setting the current state to match the start of a player's turn if it's already game over
            $sql = [
            "UPDATE global SET global_value=2 WHERE global_id=1 AND global_value=99"
            ];
            foreach ($players as $pId) {
            // All games can keep this SQL
            $sql[] = "UPDATE player SET player_id=$studioPlayer WHERE player_id=$pId";
            $sql[] = "UPDATE global SET global_value=$studioPlayer WHERE global_value=$pId";
            $sql[] = "UPDATE stats SET stats_player_id=$studioPlayer WHERE stats_player_id=$pId";
        
            // Add game-specific SQL update the tables for your game
            $sql[] = "UPDATE card SET card_location_arg=$studioPlayer WHERE card_location_arg=$pId";
            $sql[] = "UPDATE piece SET player_id=$studioPlayer WHERE player_id=$pId";
            $sql[] = "UPDATE log SET player_id=$studioPlayer WHERE player_id=$pId";
            $sql[] = "UPDATE log SET action_arg=REPLACE(action_arg, $pId, $studioPlayer)";
        
            // This could be improved, it assumes you had sequential studio accounts before loading
            // e.g., quietmint0, quietmint1, quietmint2, etc. are at the table
            $studioPlayer++;
            }
            $msg = "<b>Loaded <a href='https://boardgamearena.com/bug?id=$reportId' target='_blank'>bug report $reportId</a></b><hr><ul><li>" . implode(';</li><li>', $sql) . ';</li></ul>';
            self::warn($msg);
            self::notifyAllPlayers('message', $msg, []);
        
            foreach ($sql as $q) {
            self::DbQuery($q);
            }
            self::reloadPlayersBasicInfos();
        } */
    
    // ---------------------------------

    function boxBoxCalled($id) {
        return self::getUniqueValueFromDb("SELECT BoxBox FROM penalities_and_modifiers WHERE player = $id");
    }

    // loadTrackPreset: sets DB to match a preset of element of a test track
    function loadTrackPreset() {

        $curve_elements = [];
        
        for ($i=0; $i < 4; $i++) {
            $curbNum = $i+1;
            $curb = new VektoraceCurb($curbNum);
            ['x'=>$x, 'y'=>$y] = $curb->getCenter()->coordinates();
            $dir = $curb->getDirection();

            $curve_elements[] = "('curb', $curbNum, $x, $y, $dir)";
        }

        $curb = new VektoraceCurb(5);
        ['x'=>$x, 'y'=>$y] = $curb->getCenter()->coordinates();
        $dir = $curb->getDirection();

        switch (self::getGameStateValue('circuit_layout')) {

            case 2:
                $curve_elements[2] = "('curb', 5, $x, $y, $dir)";
                break;

            case 3:
                $curve_elements[1] = "('curb', 5, $x, $y, $dir)";
                break;

            case 4:
                $curve_elements[1] = "('curb', 5, $x, $y, $dir)";
                unset($curve_elements[2]);
                break;
        }

        $curve_elements[] = "('pitwall',10,0,0,4)";

        $curve_elements = implode($curve_elements,',');
        self::DbQuery("INSERT INTO game_element (entity, id, pos_x, pos_y, orientation) VALUES ".$curve_elements);
    }

    function getTrackCurveOrder($trackNum) {
        switch ($trackNum) {
            case 1:
                return [1,2,3,4];
                break;
            case 2:
                return [1,2,5,4];
                break;

            case 3:
                return [1,5,3,4];
                break;

            case 4:
                return [1,5,4];
                break;
        }
    }

    function getLiteralOrdinal($n, $shortForm = false) {

        $positions = [clienttranslate('first'), clienttranslate('second'), clienttranslate('third'), clienttranslate('fourth'), clienttranslate('fifth'), clienttranslate('sixth')];
        if ($shortForm) $positions = [clienttranslate('1st'), clienttranslate('2nd'), clienttranslate('3rd'), clienttranslate('4th'), clienttranslate('5th'), clienttranslate('6th')];

        return $positions[$n-1];
    }

    // imported from wiki, seems overly complicated
    function isPlayerZombie($player_id) {
        $players = self::loadPlayersBasicInfos();
        if (! isset($players[$player_id]))
            throw new feException("Player $player_id is not playing here");
        
        return ($players[$player_id]['player_zombie'] == 1);
    }

    // checks if player is zombie and if so removes his car from the table
    function removeZombieCar($id) {

        if (!self::isPlayerZombie($id)) return;

        self::DbQuery("DELETE FROM game_element WHERE id = $id"); // HOW DOES THIS INFLUENCE PLAYER ORDER ALGO
        self::notifyAllPlayers('removeZombieCar',clienttranslate('${player_name}\'s car is removed from the game'), array(
            'player_name' => self::getPlayerNameById($id),
            'player_id' => $id
        ));

        $turnPos = self::getGameStateValue('first_avail_position')-1 + self::getGameStateValue('racing_players_number');
        self::DbQuery("UPDATE player SET player_turn_position = $turnPos WHERE player_id = $id");
        self::notifyAllPlayers('setZombieTurnPos','', array(
            'player_id' => $id,
            'pos' => $turnPos
        ));

        self::incGameStateValue('racing_players_number',-1);
    }

    function getPlayerCurve($id) {
        $curve = self::getObjectFromDb("SELECT player_curve_number num, player_curve_zone zon FROM player WHERE player_id = $id");        
        $curveNum = intval($curve['num']);
        
        $curveId = self::getTrackCurveOrder(self::getGameStateValue('circuit_layout'))[$curveNum-1];
        
        $nextNum = $curveNum+1;
        if ($nextNum > self::getGameStateValue('last_curve')) $nextNum = 1;
        $nextCurve = self::getTrackCurveOrder(self::getGameStateValue('circuit_layout'))[$nextNum-1];

        return [
            'object' => new VektoraceCurb($curveId),
            'number' => $curveNum,
            'zone' => $curve['zon'],
            'next' => $nextCurve
        ];
    }

    /* function getCurveId($curveNumber) {
        self::getTrackCurveOrder(self::getGameStateValue('circuit_layout'))[$curveNumber-1];
    } */

    function isPlayerAfterLastCurve($id) {
        $playerCurve = self::getPlayerCurve($id);
        return $playerCurve['number'] == self::getGameStateValue('last_curve') && $playerCurve['zone'] > 4;
    }

    /* function isPlayerBoxBox($id) {

    } */

    function isPlayerRaceFinished($id) {
        return self::getUniqueValueFromDb("SELECT FinishedRace FROM penalities_and_modifiers WHERE player = $id");
    }

    /* function getLastFixedPos() {
        $i;
        for ($i=self::getPlayersNumber()-2; $i > 0; $i--) {
            if (self::isPlayerRaceFinished(self::getPlayerTurnPosNumber($i)))
                break;
        }

        return $i;
    } */

    function getPlayerLap($id) {
        return self::getUniqueValueFromDb("SELECT player_lap_number FROM player WHERE player_id = $id");
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
        $next = $playerTurnPos + 1;
        if ($next > self::getPlayersNumber()) $next = 1;

        return self::getPlayerTurnPosNumber($next);
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

    function getPitwall() {
        $pw = self::getObjectFromDb("SELECT pos_x x, pos_y y, orientation dir FROM game_element WHERE entity = 'pitwall'");
        return new VektoracePitwall(new VektoracePoint($pw['x'], $pw['y']), $pw['dir']);
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
        $sql = "SELECT player_id id, player_turn_position turnPos
                FROM player
                JOIN game_element ON player_id = id
                ORDER BY turnPos ASC";

        $allPlayers = $oldOrder = self::getObjectListFromDb($sql);

        //self::consoleLog($allPlayers);

        usort($allPlayers, function ($p1, $p2) {

            $p1turnPos = $p1['turnPos'];
            $p2turnPos = $p2['turnPos'];

            $p1 = $p1['id'];
            $p2 = $p2['id'];

            $car1 = self::getPlayerCarOctagon($p1);
            $car2 = self::getPlayerCarOctagon($p2);

            $player1 = self::getPlayerNameById($p1);
            $player2 = self::getPlayerNameById($p2);
            /* self::trace("// COMPARING $player1 AND $player2");
            self::dump("// $player1 CAR", $car1);
            self::dump("// $player2 CAR", $car2); */
            
            $p1curve = self::getPlayerCurve($p1)['number'];
            $p2curve = self::getPlayerCurve($p2)['number'];

            $p1lap = self::getPlayerLap($p1);
            $p2lap = self::getPlayerLap($p2);

            // check lap
            $lapComp = $p1lap <=> $p2lap; // if lap less or greater then, return result
            if ($lapComp != 0) return $lapComp;
            else {
                //self::trace("// $p1turnPos equal lap $p2turnPos");
                
                // if equal lap, check curves
                $curveComp = $p1curve <=> $p2curve; // if lap less or greater then, return result
                if ($curveComp != 0) return $curveComp;
                else {
                    //self::trace("// $p1turnPos equal curve $p2turnPos");

                    // if equal curves, check for actual overtaking
                    if ($car1->overtake($car2)) return 1; // if overtaking happens, car is greater than, otherwise is less
                    else {
                        //self::trace("// $p1turnPos doesn't surpass $p2turnPos");
                        if ($p1turnPos < $p2turnPos && !$car2->overtake($car1)) {
                            //self::trace("// $p1turnPos is higher pos than $p2turnPos and it doesn't get surpassed");
                            return 1; // else, if car is not surpassed buy other AND turn position is higher (thus lower), this car is still greater
                        }
                        else return -1;
                    }
                }
            }
        });

        $allPlayers = array_reverse($allPlayers); // reverse array because higher position is actually lower number

        //self::consoleLog($allPlayers);

        $isChanged = ($oldOrder === $allPlayers)? false : true;

        $firstAvailPos = self::getGameStateValue('first_avail_position');
        $playersNum = self::getGameStateValue('racing_players_number');

        //format array and write db if order changed
        $ret = array();
        foreach ($allPlayers as $i => $player) {
            $ret[] = $player['id'];

            $pos = $firstAvailPos + $i;

            if ($isChanged) {
                $sql = "UPDATE player
                    SET player_turn_position = $pos
                    WHERE player_id = ".$player['id'];
                self::DbQuery($sql);
            }

            $oldPos = $player['turnPos'];
            $surpasses = $oldPos - $pos;
            if ($surpasses>0) self::incStat($surpasses,'surpasses_number',$player['id']);
        }

        return array('list'=>$ret, 'isChanged'=>$isChanged);
    }

    function withinMapBoundaries($p) {
        $boundariesOpt = self::getGameStateValue('map_boundaries');

        if ($boundariesOpt == 1) return true;

        $siz = VektoraceGameElement::getOctagonMeasures()['size'];

        $off = 29;
        $off2 = 3;
         
        $mapX = [-11.5*$siz-$off, 22*$siz+$off2];
        $mapY = [-2*$siz-$off, 16*$siz+$off];

        if ($boundariesOpt == 3) {
            $mapX[0] += 0.5;
            $mapX[1] -= 0.5;
        } else if ($boundariesOpt == 4) {
            $mapY[0] -= 5.5;
            $mapY[1] += 5.5;
        }

        /* $mapX[0] *= $siz;
        $mapX[1] *= $siz;
        $mapY[0] *= $siz;
        $mapY[1] *= $siz; */

        ['x'=>$x, 'y'=>$y] = $p->coordinates();
        
        return ($x > $mapX[0] && $x < $mapX[1] && $y > $mapY[0] && $y < $mapY[1]);
    }

    // big messy method checks if subj object (can be either octagon or vector) collides with any other element on the map (cars, curves or pitwall)
    function detectCollision($subj, $isVector=false, $ignoreElements = array()) {
        
        /* self::dump('/// ANALIZING COLLISION OF '.(($isVector)? 'VECTOR':'CAR POSITION'),$subj->getCenter()->coordinates());
        self::dump('/// DUMP SUBJECT',$subj); */

        foreach (self::getObjectListFromDb("SELECT * FROM game_element") as $element) {

            if (!is_null($element['pos_x']) && !is_null($element['pos_y']) &&
                !in_array($element['id'],$ignoreElements) &&
                $element['entity'] != 'gearVector' && $element['entity'] != 'boostVector') {

                $pos = new VektoracePoint($element['pos_x'],$element['pos_y']);

                /* self::dump('// WITH '.$element['entity'].' '.$element['id'].' AT ', $pos->coordinates()); */

                $obj;
                switch ($element['entity']) {
                    case 'car': $obj = new VektoraceOctagon($pos, $element['orientation']);
                        break;
                    case 'curb': $obj = new VektoraceCurb($element['id']);
                        break;
                    case 'pitwall': $obj = new VektoracePitwall($pos, $element['orientation']);
                        break;
                    /* default:
                        throw new BgaVisibleSystemException(self::_('Cannot detect collision with invalid or unidentified object')); */
                }

                /* self::dump('/// DUMP SUBJECT',$subj);
                self::dump('/// DUMP SUBJECT VERTICES',$subj->getVertices());
                self::dump('/// DUMP OBJECT',$obj);
                self::dump('/// DUMP OBJECT VERTICES',$obj->getVertices()); */
                if ($subj->collidesWith($obj)) return true;
            }
        }

        $in = false;
        if ($isVector) $in = self::withinMapBoundaries($subj->getCenter());
        else $in = self::withinMapBoundaries($subj->getCenter());

        if ($in) return false;
        else {
            //self::trace('// -!- OUT OF BOUNDS -!-');
            return true;
        }
    }

    #endregion

    //++++++++++++++++//
    // PLAYER ACTIONS //
    //++++++++++++++++//
    #region player actions

    // [functions responding to ajaxcall formatted and forwarded by action.php script. function names should always match action name]

    // selectPosition: specific function that selects and update db on new position for currently active player car.
    //                 should be repurposed to match all cases of selecting positions and cars moving
    //                 default used when player becomes zombie, so that players after can still position car according to the rules
    function placeFirstCar($x,$y,$default=false) {

        if ($this->checkAction('placeFirstCar')) {

            // check if sent pos is valid (and player didn't cheat) by doing dot product of positioning window norm and pos vector to window center (result should be close to 0 as vectors should be orthogonal)
            $args = self::argFirstPlayerPositioning();
            
            $dir = -$args['rotation']+4;

            $center = new VektoracePoint($args['center']['x'],$args['center']['y']);
            $norm = VektoracePoint::createPolarVector(1,($dir)*M_PI_4);

            if ($default) {
                self::notifyAllPlayers('allVertices','',['p'=>[$center->coordinates()]]);
                ['x'=>$x,'y'=>$y] = $center->coordinates();
            } else if ($x != $center->x() && $y != $center->y()) {
                $pos = VektoracePoint::displacementVector($center, new VektoracePoint($x,$y));
                $pos->normalize();

                if (abs(VektoracePoint::dot($norm, $pos)) > 0.1) throw new BgaUserException(self::_('Invalid car position'));
            }

            $id = self::getActivePlayerId();

            $sql = "UPDATE game_element
                SET pos_x = $x, pos_y = $y
                WHERE id = $id";
        
            self::DbQuery($sql);

            self::notifyAllPlayers('placeFirstCar', clienttranslate('${player_name} chooses his/her starting position'), array(
                'player_id' => $id,
                'player_name' => self::getActivePlayerName(),
                'x' => $x,
                'y' => $y
                ) 
            );

            $this->gamestate->nextState('chooseTokens');
        }
    }

    function placeCarFS($refCarId,$posIdx) {

        if ($this->checkAction('placeCarFS')) {

            $args = self::argFlyingStartPositioning();

            $pos = $args['positions'];

            //[$refCarId]['positions'][$posIdx]

            foreach ($args['positions'] as $refcar) {

                if ($refcar['carId'] == $refCarId) {

                    if (array_key_exists($posIdx, $refcar['positions'])) {

                        $pos = $refcar['positions'][$posIdx];

                        if (!$pos['valid']) throw new BgaUserException(self::_('Illegal car position'));

                        ['x'=>$x,'y'=>$y] = $pos['coordinates'];

                        $id = self::getActivePlayerId();
            
                        $sql = "UPDATE game_element
                                SET pos_x = $x, pos_y = $y
                                WHERE id = $id";
                    
                        self::DbQuery($sql);
            
                        self::notifyAllPlayers('placeCarFS', clienttranslate('${player_name} chooses his/her starting position'), array(
                            'player_id' => $id,
                            'player_name' => self::getActivePlayerName(),
                            'x' => $x,
                            'y' => $y,
                            'refCar' => $refCarId
                        ));
            
                        $this->gamestate->nextState('chooseTokens');
                        return;

                    } else throw new BgaVisibleSystemException(self::_('Invalid car position'));
                }
            }
            throw new BgaVisibleSystemException(self::_('Invalid reference car id'));            
        }
    }

    function chooseTokensAmount($tire,$nitro, $pitStop = false) {
        if ($this->checkAction('chooseTokensAmount')) {

            $args = $this->gamestate->state()['args'];

            if ($tire < $args['tire'] || $nitro < $args['nitro']) throw new BgaUserException(self::_('You cannot have a negative transaction of tokens'));
            if ($tire > 8 || $nitro > 8) throw new BgaUserException(self::_('You cannot have more than 8 tokens for each type'));
            if ($tire+$nitro != min($args['tire'] + $args['nitro'] + $args['amount'], 16)) throw new BgaUserException(self::_('You have to fill your token reserve with the correct amount'));

            $id = self::getActivePlayerId();

            $prevTokens = self::getPlayerTokens($id);

            $sql = "UPDATE player
                    SET player_tire_tokens = $tire, player_nitro_tokens = $nitro
                    WHERE player_id = $id";

            self::DbQuery($sql);

            if ($pitStop) {
                $tire = $tire - $prevTokens['tire'];
                $nitro = $nitro - $prevTokens['nitro'];
            }

            self::notifyAllPlayers('chooseTokensAmount', ($pitStop)? clienttranslate('${player_name} refills his/her token reserve with ${tire} ${tire_token} and ${nitro} ${nitro_token}'):clienttranslate('${player_name} chooses to start the game with ${tire} ${tire_token} and ${nitro} ${nitro_token}'), array(
                'player_id' => $id,
                'player_name' => self::getActivePlayerName(),
                'tire' => $tire,
                'nitro' => $nitro,
                'prevTokens' => $prevTokens,
                'tire_token' => clienttranslate('tire token(s)'),
                'nitro_token' => clienttranslate('nitro token(s)'),
                'i18n' => array('tire_token','nitro_token'),
                )
            );

            $this->gamestate->nextState(($pitStop)? 'endPitStopRefill' : 'endInitialTokenAmt');
        }
    }

    // chooseStartingGear: server function responding to user input when a player chooses the gear vector for all players (green-light phase)
    function chooseStartingGear($n) {
        if ($this->checkAction('chooseStartingGear')) {

            if ($n<3 && $n>0) throw new BgaUserException(self::_('You may only choose between the 3rd to the 5th gear for the start of the game'));
            if ($n<1 || $n>5) throw new BgaVisibleSystemException(self::_('Invalid gear number'));

            $sql = "UPDATE player
                    SET player_current_gear = $n";
        
            self::DbQuery($sql);

            self::incGameStateValue('turn_number', 1);
            foreach (self::getCollectionFromDb("SELECT player_id FROM player") as $id => $player)
                self::setStat($n,'average_gear',$id);

            $log = clienttranslate('${player_name} chooses the ${gearNum} gear as the starting gear for every player');
            $log = str_replace('gearNum','gear_'.$n,$log); 
    
            self::notifyAllPlayers('chooseStartingGear', $log, array(
                'player_name' => self::getActivePlayerName(),
                'n' => $n,
                'gear_'.$n => self::getLiteralOrdinal($n,true),
                'i18n' => array('gear_'.$n)
                ) 
            );
        }

        $this->gamestate->nextState('placeVector');
    }

    // declareGear: same as before, but applies only to active player, about his gear of choise for his next turn. thus DB is updated only for the player's line
    function declareGear($n) {
        if ($this->checkAction('declareGear')) {

            if ($n<1 || $n>5) throw new BgaVisibleSystemException(self::_('Invalid gear number'));

            $id = self::getActivePlayerId();

            $args = self::argFutureGearDeclaration()['gears'];
            $gearProp = $args[$n-1];

            $curr = self::getPlayerCurrentGear($id);

            if ($gearProp == 'unavail') throw new BgaUserException(self::_('You are not allowed to choose this gear right now'));
            if ($gearProp == 'denied') {
                if ($n > $curr) throw new BgaUserException(self::_('You cannot shift upwards after an emergency brake'));
                if ($n < $curr) throw new BgaUserException(self::_('You cannot shift downwards after suffering a push from an enemy car'));
            }

            if ($gearProp == 'tireCost' || $gearProp == 'nitroCost')  {

                $type = str_replace('Cost','',$gearProp);

                $tokens = self::getPlayerTokens($id)[$type];

                $cost = abs($curr - $n)-1;
                $tokenExpense = $tokens - $cost;
 
                // translation in exception apparently cannot include variable content
                if ($tokenExpense < 0) {
                    if ($type == 'tire') throw new BgaUserException(self::_("You don't have enough tire tokens to do this action"));
                    if ($type == 'nitro') throw new BgaUserException(self::_("You don't have enough nitro tokens to do this action"));
                }

                self::incStat($cost,$type.'_used',$id);

                $sql = "UPDATE player
                        SET player_".$type."_tokens = $tokenExpense
                        WHERE player_id = $id";

                self::DbQuery($sql);

                $log = clienttranslate('${player_name} spends ${cost} ${tire_token}${nitro_token} to performs ${shiftType}');
                $log = str_replace((($type == 'tire')? '${nitro_token}' : '${tire_token}'),'',$log);

                self::notifyAllPlayers('gearShift', $log, array(
                    'i18n' => array('shiftType','tire_token','nitro_token'),
                    'player_name' => self::getActivePlayerName(),
                    'player_id' => self::getActivePlayerId(),
                    'shiftType' => (($type == 'tire')? clienttranslate('a downshift') : clienttranslate('an upshift')),
                    'step' => $cost + 1,
                    'cost' => $cost,
                    'tokenType' => $type,
                    'updatedTokensAmt' => $tokenExpense,
                    'tire_token' => clienttranslate('tire token(s)'),
                    'nitro_token' => clienttranslate('nitro token(s)')
                ));
            }

            $sql = "UPDATE player
                    SET player_current_gear = $n
                    WHERE player_id = $id";
        
            self::DbQuery($sql);

            $log = clienttranslate('${player_name} declares ${gearInd} for his/her next turn');
            $log = str_replace('gearInd','gear_'.$n,$log); 

            //self::notifyAllPlayers('declareGear', clienttranslate('${player_name} declares the ${gearNum} gear for his/her next turn'), array(
            self::notifyAllPlayers('declareGear', $log, array(
                'player_name' => self::getActivePlayerName(),
                'player_id' => self::getActivePlayerId(),
                'n' => $n,
                'gear_'.$n => self::getLiteralOrdinal($n,true),
                'i18n' => array('i18n' => array('gear_'.$n))
                ) 
            );

            $this->gamestate->nextState('nextPlayer');
        }
    }

    function placeGearVector($position) {

        if ($this->checkAction('placeGearVector')) {

            foreach (self::argGearVectorPlacement()['positions'] as $pos) {

                if ($pos['position'] == $position) {

                    if (!$pos['legal']) throw new BgaUserException(self::_('Illegal gear vector position'));
                    if ($pos['denied']) throw new BgaUserException(self::_('Gear vector position denied for the shunting you previously suffered'));
                    if ($pos['offTrack']) throw new BgaUserException(self::_('You cannot pass a curve from behind'));
                    if (!$pos['carPosAvail']) throw new BgaUserException(self::_("This gear vector position doesn't allow any vaild car positioning"));

                    $id = self::getActivePlayerID();

                    $tireTokens = self::getPlayerTokens($id)['tire'];

                    // CHECK TIRE COST
                    if ($pos['tireCost']) {

                        if ($tireTokens == 0) throw new BgaUserException(self::_("You don't have enough tire tokens to do this move"));
                        
                        $sql = "UPDATE player
                                SET player_tire_tokens = player_tire_tokens -1
                                WHERE player_id = $id";
                        self::DbQuery($sql);

                        // APPLY PENALITY (NO DRAFTING ATTACK MOVES ALLOWED)
                        self::DbQuery("UPDATE penalities_and_modifiers SET NoDrafting = 1 WHERE player = $id");

                        $tireTokens -= 1;
                        self::incStat(1,'tire_used',$id);
                        self::notifyAllPlayers('sideShift', clienttranslate('${player_name} spends 1 ${tire_token} to perform a side shift'), array(
                            'player_name' => self::getActivePlayerName(),
                            'player_id' => $id,
                            'tire_token' => clienttranslate('tire token'),
                            'i18n' => array('tire_token'),
                        ));
                    }

                    $orientation = self::getUniqueValueFromDb("SELECT orientation FROM game_element WHERE id=$id");
                    $gear = self::getPlayerCurrentGear($id);
                    ['x'=>$x, 'y'=>$y] = $pos['vectorCoordinates'];

                    // INSERT VECTOR ON TABLE
                    $sql = "INSERT INTO game_element (entity, id, pos_x, pos_y, orientation)
                            VALUES ('gearVector', $gear, $x, $y, $orientation)";
                    self::DbQuery($sql);

                    // UPDTE CURVE PROGRESS BASED ON VECTOR TOP
                    $curveProgress = $pos['curveProgress'];
                    self::DbQuery("UPDATE player SET player_curve_zone = $curveProgress WHERE player_id = $id");

                    self::notifyAllPlayers('placeGearVector', clienttranslate('${player_name} places his/her gear vector'), array(
                        'player_name' => self::getActivePlayerName(),
                        'player_id' => $id,
                        'x' => $x,
                        'y' => $y,
                        'direction' => $orientation,
                        'tireTokens' => $tireTokens,
                        'gear' => $gear
                    ));

                    if (self::getPlayerTokens($id)['nitro'] == 0 ||
                        self::getUniqueValueFromDb("SELECT BoxBox FROM penalities_and_modifiers WHERE player = $id") ||
                        $gear == 1 ||
                        !self::argBoostVectorPlacement()['hasValid']) {
                        $this->gamestate->nextState('skipBoost');
                        return;
                    }

                    $this->gamestate->nextState('boostPromt');
                    return;
                }
            }

            throw new BgaVisibleSystemException(self::_('Invalid gear vector position'));
        }
    }

    function brakeCar() {
        if ($this->checkAction('brakeCar')) {

            // check if player has indeed no valid positionts, regardless of which state he takes this action from (car or vector placement)
            $arg = self::argGearVectorPlacement();
            if ($arg['hasValid']) throw new BgaUserException(self::_('You cannot perform this move if you have valid gear vector positions'));

            /* if ($this->gamestate->state()['name'] == 'carPlacement') {
                // if called during this state, a vector has already been places so it has to be removed from db
                $sql = "DELETE FROM game_element
                        WHERE entity = 'gearVector'";
                self::DbQuery($sql);
            } */
            
            // APPLY PENALITY (NO BLACK MOVES, NO ATTACK MANEUVERS, NO SHIFT UP)
            self::DbQuery("UPDATE penalities_and_modifiers SET NoBlackMov = 1, NoAttackMov = 1, NoShiftUp = 1 WHERE player = ".self::getActivePlayerId());

            self::notifyAllPlayers('brakeCar', clienttranslate('${player_name} performs an emergency brake to avoid making an illegal move'), array(
                'player_name' => self::getActivePlayerName()
            ));

            $this->gamestate->nextState('slowdownOrBrake');
            return;
        }
    }

    function giveWay() {
        if ($this->checkAction('giveWay')) {

            $arg = self::argGearVectorPlacement();
            $id = self::getActivePlayerId();

            if (!$arg['canGiveWay']) throw new BgaUserException(self::_('You cannot give way if no player is obstructing your path'));            

            $this->gamestate->nextState('setNewTurnOrder');
        }
    }

    function rotateAfterBrake($dir) {
        if ($this->checkAction('rotateAfterBrake')) {

            $id = self::getActivePlayerId();
            $arg = self::argEmergencyBrake()['directionArrows'];

            if (array_key_exists($dir,$arg)) {

                $direction = $arg[$dir];

                $orientation = self::getUniqueValueFromDb("SELECT orientation FROM game_element WHERE id = $id");
                $orientation = ($orientation + $direction['rotation'] + 8) % 8;

                $sql = "UPDATE game_element
                        SET orientation = $orientation
                        WHERE id = $id";
                self::DbQuery($sql);

                self::notifyAllPlayers('rotateAfterBrake', clienttranslate('${player_name} stopped his/her car. No gear vector could allow a valid move'), array(
                    'player_name' => self::getActivePlayerName(),
                    'player_id' => $id,
                    'rotation' => $dir-1
                ));

                $this->gamestate->nextState('endOfTurn');
                return;

            } else throw new BgaVisibleSystemException(self::_("Invalid direction"));
        }
    }

    function useBoost($use) {
        if ($this->checkAction('useBoost')) {

            if($use) {

                $id = self::getActivePlayerId();
                $nitroTokens = self::getPlayerTokens($id)['nitro'];

                if ($nitroTokens == 0) throw new BgaUserException(self::_("You don't have enough nitro tokens to use a boost"));

                $sql = "UPDATE player
                        SET player_nitro_tokens = player_nitro_tokens -1
                        WHERE player_id = $id AND player_nitro_tokens > 0";
                self::DbQuery($sql);

                $nitroTokens += -1;
                self::incStat(1,'nitro_used',$id);
                self::incStat(1,'boost_number',$id);
                self::notifyAllPlayers('useBoost', clienttranslate('${player_name} spends 1 ${nitro_token} to extend his/her movement with a boost vector'), array(
                    'player_name' => self::getActivePlayerName(),
                    'player_id' => $id,
                    'nitroTokens' => $nitroTokens,
                    'nitro_token' => clienttranslate('nitro token'),
                    'i18n' => array('nitro_token'),
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

                    if (!$pos['legal']) throw new BgaUserException(self::_('Illegal boost vector lenght'));
                    if (!$pos['carPosAvail']) throw new BgaUserException(self::_("This boost lenght doesn't allow any vaild car positioning"));

                    ['x'=>$x, 'y'=>$y] = $pos['vecCenterCoordinates'];
                    
                    $sql = "INSERT INTO game_element (entity, id, pos_x, pos_y, orientation)
                            VALUES ('boostVector', $n, $x, $y, $direction)";

                    self::DbQuery($sql);

                    self::notifyAllPlayers('chooseBoost', clienttranslate('${player_name} places the ${boostNum} boost vector'), array(
                        'player_name' => self::getActivePlayerName(),
                        'player_id' => self::getActivePlayerID(),
                        'n' => $n,
                        'boostNum' => self::getLiteralOrdinal($n,true),
                        'i18n' => array('boostNum'),
                        'vecX' => $x,
                        'vecY' => $y,
                        'direction' => $direction
                    ));

                    $this->gamestate->nextState("placeCar");
                    return;
                }
            }

            throw new BgaVisibleSystemException(self::_('Invalid boost length'));
        }
    }

    function placeCar($position, $direction) {

        if ($this->checkAction('placeCar')) {

            $allPos = self::argCarPlacement()['positions'];

            // I SHOULD FILTER HERE INSTEAD OF POINTLESS FOREACH FOR USING ONE ITEM ONLY
            foreach ($allPos as $pos) {
                
                if ($pos['position'] == $position) {

                    if (!$pos['legal']) throw new BgaUserException(self::_('Illegal car position'));
                    if ($pos['denied']) throw new BgaUserException(self::_('Car position denied for the shunting you previously suffered'));

                    $allDir = $pos['directions'];

                    foreach ($allDir as $dir) {
                        
                        if ($dir['direction'] == $direction) {

                            $id = self::getActivePlayerId();
                            
                            $previousPos = self::getPlayerCarOctagon($id);
                            
                            $tireTokens = self::getPlayerTokens($id)['tire'];

                            if ($dir['black']) {

                                if (self::getUniqueValueFromDb("SELECT NoBlackMov FROM penalities_and_modifiers WHERE player = $id"))
                                    throw new BgaUserException(self::_('You cannot perform "black moves" after an emergency break'));

                                if ($tireTokens == 0)
                                    throw new BgaUserException(self::_("You don't have enough tire tokens to perform this move"));
                                
                                $sql = "UPDATE player
                                        SET player_tire_tokens = player_tire_tokens -1
                                        WHERE player_id = $id";
                                self::DbQuery($sql);

                                // APPLY PENALITY (NO DRAFTING ATTACK MOVES ALLOWED)
                                self::DbQuery("UPDATE penalities_and_modifiers SET NoDrafting = 1 WHERE player = $id");

                                $tireTokens--;
                                self::incStat(1,'tire_used',$id);
                                self::notifyAllPlayers('blackMove', clienttranslate('${player_name} spends 1 ${tire_token} to perform a "black move"'), array(
                                    'player_name' => self::getActivePlayerName(),
                                    'player_id' => $id,
                                    'tire_token' => clienttranslate('tire token'),
                                    'i18n' => array('tire_token'),
                                ));
                            }

                            ['x'=>$x, 'y'=>$y] = $pos['coordinates'];
                            $rotation = $dir['rotation'];
                            $orientation = self::getUniqueValueFromDb("SELECT orientation FROM game_element WHERE id = $id");
                            $orientation = ($orientation + $rotation + 8) % 8;

                            $sql = "UPDATE game_element
                                    SET pos_x = $x, pos_y = $y, orientation = $orientation
                                    WHERE id = $id";
                            self::DbQuery($sql);

                            // remove any vectror used during movement
                            $sql = "DELETE FROM game_element
                                    WHERE entity = 'gearVector' OR entity = 'boostVector'";
                            self::DbQuery($sql);

                            // UPDATE CURVE PROGRESS
                            $curveProgress = $pos['curveProgress'];
                            self::DbQuery("UPDATE player SET player_curve_zone = $curveProgress WHERE player_id = $id");

                            self::notifyAllPlayers('placeCar', clienttranslate('${player_name} places his/her car'), array(
                                'player_name' => self::getActivePlayerName(),
                                'player_id' => $id,
                                'x' => $x,
                                'y' => $y,
                                'rotation' => $rotation,
                                'tireTokens' => $tireTokens
                            ));

                            $currPos = self::getPlayerCarOctagon($id);
                            $pw = self::getPitwall();

                            if (self::isPlayerAfterLastCurve($id)) {
                                if ($previousPos->inPitZone($pw, 'EoC') && $pos['byFinishLine'] && self::getUniqueValueFromDb("SELECT BoxBox FROM penalities_and_modifiers WHERE player = $id")) throw new BgaUserException(self::_('You cannot avoid going to the box after after calling "BoxBox!"'));
                                if ($pos['leftBoxEntrance']) throw new BgaUserException(self::_('You cannot leave the box entrance lane after calling "BoxBox!"'));
                                if ($previousPos->inPitZone($pw, 'entrance')) {
                                    if ($pos['byBox'] && !is_null(self::getUniqueValueFromDb("SELECT id FROM game_element WHERE entity = 'boostVector'"))) throw new BgaUserException(self::_('You cannot enter the box using a boost vector'));
                                    if ($pos['byBox'] && self::getPlayerCurve($id)['number'] == 1) throw new BgaUserException(self::_('You cannot go to the Pit-Box at the start of the race'));
                                    if ($pos['byBox'] && self::getPlayerLap($id) == self::getGameStateValue('number_of_laps')-1) throw new BgaUserException(self::_('You cannot go to the Pit-Box on your last lap'));
                                }
                                if ($currPos->inPitZone($pw, 'box', 'any') && $currPos->getDirection() != $pw->getDirection()) throw new BgaUserException(self::_("You are not allowed to rotate the car while inside the Pit Box"));

                                // -- CAR OVERSHOOTS BOX ENTRANCE (was in entrance, now is in exit)
                                if ($previousPos->inPitZone($pw,'entrance') && $currPos->inPitZone($pw,'exit','any')) {
                                    // overshot pitbox entrance -> penality

                                    $newPosPoint = $currPos->boxOvershootPenality($pw);
                                    $newPosPoint = new VektoraceOctagon($newPosPoint,$currPos->getDirection());

                                    if (self::detectCollision($newPosPoint)) {
                                        $newPosPoint = $currPos->boxOvershootPenality($pw, true);
                                        $newPosPoint = new VektoraceOctagon($newPosPoint,$currPos->getDirection());                              
                                    }

                                    ['x'=>$x,'y'=>$y] = $newPosPoint->getCenter()->coordinates();
                                    $orientation = $pw->getDirection();

                                    $sql = "UPDATE game_element
                                            SET pos_x = $x, pos_y = $y, orientation = $orientation
                                            WHERE id = $id";
                                    self::DbQuery($sql);

                                    $rotation = $orientation - $currPos->getDirection();

                                    self::notifyAllPlayers('boxEntranceOvershoot', clienttranslate('${player_name} doesn\'t stop by the Pit Box area and thus does not refill his/her tokens'), array(
                                        'player_name' => self::getActivePlayerName(),
                                        'player_id' => $id,
                                        'x' => $x,
                                        'y' => $y,
                                        'rotation' => $rotation,
                                    ));

                                    $this->gamestate->nextState('endMovement');
                                    return;
                                }

                                // -- ELSE CHECK IF CAR IS INSIDE BOX
                                if ($currPos->inPitZone($pw, 'box', 'any')) {
                                    // if nose is in box (and previous was not. should not be possible but avoids double refill)
                                    if ($currPos->inPitZone($pw,'box') && !$previousPos->inPitZone($pw,'box')) {

                                        self::notifyAllPlayers('boxEntrance', clienttranslate('${player_name} enters the Pit Box'), array(
                                            'player_name' => self::getActivePlayerName(),
                                            'player_id' => $id,
                                        ));

                                        self::incStat(1,'pitstop_number',$id);
    
                                        $this->gamestate->nextState('boxEntrance'); // refill tokens
                                    } else $this->gamestate->nextState('endMovement'); // else car should be in exit, skips attack

                                    return;
                                }
                            }

                            $this->gamestate->nextState('attack');
                            return;
                        }
                    }

                    throw new BgaVisibleSystemException(self::_('Invalid car direction'));

                }
            }

            throw new BgaVisibleSystemException(self::_('Invalid car position'));
        }
    }

    function engageManeuver($enemy, $action, $posIndex) {
        if ($this->checkAction('engageManeuver')) {

            $args = self::argAttackManeuvers();
            $id = self::getActivePlayerId();

            $penalities = self::getObjectFromDb("SELECT NoDrafting, NoAttackMov, BoxBox FROM penalities_and_modifiers WHERE player = $id");
            if ($penalities['NoAttackMov'] || $penalities['BoxBox']) throw new BgaUserException(self::_('You are currently restricted from performing any action maneuver'));
            if (($action == 'drafting' || $action == 'push' || $action == 'slingshot') && $penalities['NoDrafting']) throw new BgaUserException(self::_('You cannot perform drafting maneuvers after spending tire tokens during your movement phase'));

            $mov = null;
            foreach ($args['attEnemies'] as $en) {
                if ($en['id'] == $enemy) {
                    $mov = $en['maneuvers'][$action];
                }
            }
            if (is_null($mov)) throw new BgaVisibleSystemException(self::_('Invalid attack maneuver'));


            if (!$mov['active']) throw new BgaUserException(self::_('You do not pass the requirements to be able to perform this maneuver'));
            if (!$mov['legal']) throw new BgaUserException(self::_('Illegal attack position'));

            $attPos = $mov['attPos'];
            if ($action == 'slingshot') {
                if (!$attPos[$posIndex]['valid']) throw new BgaUserException(self::_('Illegal attack position'));
                $attPos = $attPos[$posIndex]['pos'];
            }

            ['x'=>$x, 'y'=>$y] = $attPos;

            $posOct = new VektoraceOctagon(new VektoracePoint($x,$y),self::getPlayerCarOctagon($id)->getDirection());
            if ($posOct->inPitZone(self::getPitwall(),'box')) throw new BgaUserException(self::_('You cannot enter the box with an attack maneuver'));

            $playerCurve = self::getPlayerCurve($id)['object'];
            $curveProgress =  $posOct->getCurveZone($playerCurve);
            self::DbQuery("UPDATE player SET player_curve_zone = $curveProgress WHERE player_id = $id");

            self::DbQuery("UPDATE game_element SET pos_x = $x, pos_y = $y WHERE id = $id"); // don't worry about db update being before checking nitroTokens, any thrown exception discards the transaction and reset db top previous state

            $nitroTokens = null; // needed for slingshot

            switch ($action) {
                case 'drafting':
                    $desc = clienttranslate('${player_name} takes the slipstream of ${player_name2}');                
                    break;

                case 'push':
                    $desc = clienttranslate('${player_name} pushes ${player_name2} form behind');
                    self::DbQuery("UPDATE penalities_and_modifiers SET NoShiftDown = 1 WHERE player = $enemy");
                    break;

                case 'slingshot':

                    $nitroTokens = self::getPlayerTokens($id)['nitro'] - 1;
                    if ($nitroTokens < 0) throw new BgaUserException(self::_("You don't have enough nitro tokens to perform this action"));
                    self::DbQuery("UPDATE player SET player_nitro_tokens = $nitroTokens WHERE player_id = $id");
                    self::incStat(1,'nitro_used',$id);
                    
                    $desc = clienttranslate('${player_name} spends 1 ${nitro_token} to overtake ${player_name2} with a slingshot pass');
                    break;

                case 'leftShunt':
                    $desc = clienttranslate('${player_name} shunts ${player_name2} from the left');
                    self::DbQuery("UPDATE penalities_and_modifiers SET DeniedSideLeft = 1 WHERE player = $enemy");
                    break;

                case 'rightShunt':
                    $desc = clienttranslate('${player_name} shunts ${player_name2} from the right');
                    self::DbQuery("UPDATE penalities_and_modifiers SET DeniedSideRight = 1 WHERE player = $enemy");
                    break;
            }

            self::incStat(1,'attMov_performed',$id);
            self::incStat(1,'attMov_suffered',$enemy); // counting simple drafting too

            self::notifyAllPlayers('engageManeuver',$desc,array(
                'player_name' => self::getActivePlayerName(),
                'player_id' => $id,
                'player_name2' => self::getPlayerNameById($enemy),
                'enemy' => $enemy,
                'attackPos' => $attPos,
                'nitroTokens' => $nitroTokens,
                'nitro_token' => clienttranslate('nitro token'),
                'i18n' => array('nitro_token'),
                'action' => $action
            ));

            $this->gamestate->nextState('endOfMovement');
        }
    }

    function skipAttack() {
        if ($this->checkAction('skipAttack')) {
            self::notifyAllPlayers('skipAttack',clienttranslate('${player_name} does not perform any attack maneuver'),array(
                'player_name' => self::getActivePlayerName(),
                'player_id' => self::getActivePlayerId()
            ));

            $this->gamestate->nextState('endOfMovement');
        }
    }

    function boxBox($skip) {
        if ($this->checkAction('boxBox')) {
            $id = self::getActivePlayerId();

            if ($skip) {
                self::DbQuery("UPDATE penalities_and_modifiers SET BoxBox = 0 WHERE player = $id");

                $this->gamestate->nextState('endTurn');
            }
            else {
                self::DbQuery("UPDATE penalities_and_modifiers SET BoxBox = 1 WHERE player = $id");

                self::notifyAllPlayers('boxBox',clienttranslate('${player_name} calls "BoxBox!"'),array(
                    'player_name' => self::getActivePlayerName(),
                    'player_id' => self::getActivePlayerId()
                ));

                $this->gamestate->nextState('endTurn');
            }
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
        
        $pw = self::getPitwall();
        $anchorVertex = $pw->getVertices()[2][1];

        $placementWindowSize = array('width' => VektoraceGameElement::getOctagonMeasures()['size'], 'height' => VektoraceGameElement::getOctagonMeasures()['size']*3.5);
        
        $ro = $placementWindowSize['width']/2;
        $the = ($pw->getDirection()-4) * M_PI_4;
        $windowCenter = $anchorVertex->translatePolar($ro, $the);

        $ro = $placementWindowSize['height']/2;
        $the = ($pw->getDirection()-2) * M_PI_4;
        $windowCenter = $windowCenter->translatePolar($ro, $the);

        return array("anchorPos" => $anchorVertex->coordinates(), "rotation" => 4 - $pw->getDirection(), 'center' => $windowCenter->coordinates());
    }

    // returns coordinates and useful data for all available (valid and not) flying start positition for each possible reference car
    function argFlyingStartPositioning() {

        $activePlayerTurnPosition = self::getPlayerTurnPos(self::getActivePlayerId());
        $playerBefore = self::getPlayerTurnPosNumber($activePlayerTurnPosition-1); 
        $allpos = array();

        foreach (self::loadPlayersBasicInfos() as $id => $playerInfos) { // for each reference car on the board

            if ($playerInfos['player_no'] < $activePlayerTurnPosition) {

                $hasValid = false;

                $playerCar = self::getPlayerCarOctagon($id);
                $fsOctagons = $playerCar->getAdjacentOctagons(3,true);

                $right_3 = new VektoraceOctagon($fsOctagons[2], ($playerCar->getDirection()+1 +8)%8);
                $center_3 = new VektoraceOctagon($fsOctagons[1], $playerCar->getDirection());
                $left_3 = new VektoraceOctagon($fsOctagons[0], ($playerCar->getDirection()-1 +8)%8);

                $fsPositions = array(...$right_3->getAdjacentOctagons(3,true), ...$center_3->getAdjacentOctagons(3,true), ...$left_3->getAdjacentOctagons(3,true));
                $fsPositions = array_unique($fsPositions, SORT_REGULAR);

                $positions = array();
                foreach ($fsPositions as $pos) { // for each position of the reference car

                    $playerBeforeCar = self::getPlayerCarOctagon($playerBefore); // construct octagon from ahead player's position
                    $posOct = new VektoraceOctagon($pos, $playerBeforeCar->getDirection()); // construct octagon of current position

                    /* $vertices = $posOct->getVertices();
                    foreach ($vertices as &$v) {
                        $v = $v->coordinates();
                    } unset($v); */

                    $valid = $posOct->isBehind($playerBeforeCar,false) && !self::detectCollision($posOct);
                    if ($valid) $hasValid = true;

                    // if pos is not behind or a collision is detected, report it as invalid
                    $positions[] = array(
                        'coordinates' => $pos->coordinates(),
                        /* 'vertices' => $vertices, */
                        // 'debug' => $posOct->isBehind($playerBeforeCar),
                        'valid' => $valid
                    );
                }

                foreach ($fsOctagons as &$oct) {
                    $oct = $oct->coordinates();
                } unset($oct);

                $allpos[] = array(
                    'carId' => $id,
                    'coordinates' => $playerCar->getCenter()->coordinates(),
                    'FS_octagons' => $fsOctagons,
                    'positions' => $positions,
                    'hasValid' => $hasValid
                );
            }
        }

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

        ['number'=>$curveNum, 'zone'=>$curveZone, 'object'=>$playerCurve] = self::getPlayerCurve($id);

        $playerTurnPos = self::getPlayerTurnPos($id);
        $ignorePlayer = ($playerTurnPos == self::getPlayersNumber() || self::getPlayerTurnPos($id) == 1)? [] : [self::getPlayerTurnPosNumber($playerTurnPos+1)];

        // iter through all 5 adjacent octagon
        foreach ($playerCar->getAdjacentOctagons(5) as $i => $anchorPos) {

            if (!($currentGear==1 && ($i==0 || $i==4))) {

                // construct vector from that anchor position
                $vector = new VektoraceVector($anchorPos, $direction, $currentGear, 'bottom');
                
                // calc difference between current curve zone and hypotetical vector top curve zone
                $curveZoneStep = $vector->getTopOct()->getCurveZone($playerCurve) - $curveZone;

                $predArgCarPos = self::argCarPlacement($vector);

                // return vector center to make client easly display it, along with anchor pos for selection octagon, and special properties flag
                $positions[] = array(
                    'position' => $posNames[$i],
                    'anchorCoordinates' => $anchorPos->coordinates(),
                    'vectorCoordinates' => $vector->getCenter()->coordinates(),
                    'tireCost' => ($i == 0 || $i == 4), // pos 0 and 4 are right-side and left-side respectevly, as AdjacentOctagons() returns position in counter clockwise fashion
                    'legal' => !self::detectCollision($vector,true),
                    'denied' => ($i < 2 && $deniedSide['R']) || ($i > 2 && $deniedSide['L']),
                    'obstructed' => !self::detectCollision($vector,true, $ignorePlayer),
                    'offTrack' =>  $curveZoneStep > 3 || $curveZoneStep < -1 || ($curveZoneStep < 0 && $vector->getTopOct()->getCurveZone($playerCurve) == 0), // if curve zone step is too high or backwards, assuming player is going off track
                    'curveProgress'=> $vector->getTopOct()->getCurveZone($playerCurve),
                    'carPosAvail' => $predArgCarPos['hasValid'],
                    'carPosNoneWhite' => $predArgCarPos['noneWhite']
                );
            }
        }

        $hasValid = false;
        $noneWhite = true;
        foreach ($positions as $pos) {
            if ($pos['carPosAvail'] && $pos['legal'] && !$pos['denied'] && !$pos['offTrack'] && !($pos['tireCost'] && self::getPlayerTokens(self::getActivePlayerId())['tire']<1)) {

                if (!$pos['tireCost'] && !$pos['carPosNoneWhite']) $noneWhite = false;
                $hasValid = true;
                break;
            }
        }

        // DETECT GIVE WAY POSSIBILITY
        // retrieve player with turn position after
        // if i remove him from the elements, does detectCollission now return false for any of the available positions?
        // if yes, then player before is obstructing, grant possibility to giveWay
        $canGiveWay = false;
        if (!$hasValid) {
            foreach ($positions as $pos) {

                // if valid is found no need to check for canGiveWay
                if (!$pos['denied'] && !$pos['legal'] && $pos['obstructed']) {
                    $canGiveWay = true;
                    break; 
                }
            }
        }

        return array('positions' => $positions, 'direction' => $direction, 'gear' => $currentGear, 'hasValid' => $hasValid, 'noneWhite' => $noneWhite, 'canGiveWay' => $canGiveWay);
    }

    function argEmergencyBrake() {

        $carOct = self::getPlayerCarOctagon(self::getActivePlayerId());

        $dirNames = array('right', 'straight', 'left');

        $directions = array();
        foreach ($carOct->getAdjacentOctagons(3) as $i => &$dir) {
            $directions[] = array(
                'direction' => $dirNames[$i],
                'coordinates' => $dir->coordinates(),
                'rotation' => $i-1,
                'black' => false
            );
        } unset($dir);

        return array('directionArrows' => $directions, 'direction' => $carOct->getDirection());
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
                'legal' => !self::detectCollision($vector,true),
                'carPosAvail' => self::argCarPlacement($vector, true)['hasValid']  // leagl/valid, as it also checks if this particular boost lenght produces at least one vaild position
            );
        }

        $hasValid = false;

        foreach ($positions as $pos) {
            if ($pos['legal'] && $pos['carPosAvail']) {
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
        
        $playerCurve = self::getPlayerCurve($id);
        $playerCurveObj = $playerCurve['object'];

        $pw = self::getPitwall();

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

            $curveZoneStep = $carOct->getCurveZone($playerCurveObj) - $playerCurve['zone'];

            $positions[] = array(
                'position' => $posNames[$i],
                'coordinates' => $carPos->coordinates(),
                'directions' => $directions,
                'tireCost' => ($i==0 || $i==4) && !(($i < 2 && $deniedSide['R']) || ($i > 2 && $deniedSide['L'])),
                // messy stuff to passively tell why a position is denied. there are few special case to be aware of:
                //  position is both denied by shunt AND is a tireCost position -> position is displayed simply as DENIED (turn off tireCost, you'll see why this is necessary in client)
                //  position is tireCost AND player is restricted from selecting tireCost positions -> position is set also to denied and displayed as DENIED (but being both tireCost and denied true, client can guess why without additional info)
                //  position is only denied by shunt -> position is set and displayed as DENIED
                //  position is only tireCost -> position is set and displayed as TIRECOST
                //  position is both tireCost, NoBlackMov AND denied by shunt -> position is simply displayed as denied by shunt (no need to display additional info)
                'legal' => !self::detectCollision($carOct),
                'denied' => ($i < 2 && $deniedSide['R']) || ($i > 2 && $deniedSide['L']) || (($i==0 || $i==4) && self::getUniqueValueFromDb("SELECT NoBlackMov FROM penalities_and_modifiers WHERE player = $id")),
                'byFinishLine' => $carOct->inPitZone($pw, 'SoC') || $carOct->inPitZone($pw, 'grid'),
                'byBox' => $carOct->inPitZone($pw, 'box') || $carOct->inPitZone($pw, 'exit'),
                'leftBoxEntrance' => self::boxBoxCalled($id) && $carOct->inPitZone($pw, 'EoC'),
                'offTrack' =>  $curveZoneStep > 3 || $curveZoneStep < -1 || ($curveZoneStep < 0 && $carOct->getCurveZone($playerCurveObj) == 0),
                'curveProgress'=> $carOct->getCurveZone($playerCurveObj) // used by server only
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
        $noneWhite = true;
        // always easier to return non associative arrays for lists of positions, so that js can easly iterate through them
        $positions = array_values($positions);
        foreach ($positions as $i => $pos) {
            $positions[$i]['directions'] = array_values($positions[$i]['directions']);

            // enter only if:
            if ($pos['legal'] && // pos is legal
                !$pos['denied'] && // pos is not denied
                !$pos['offTrack'] && // pos is not off track
                // if pos is costs a tire, the player has at least one tire token and it's not prevented from using it
                !($pos['tireCost'] && (self::getPlayerTokens($id)['tire']<1 || self::getUniqueValueFromDb("SELECT NoBlackMov FROM penalities_and_modifiers WHERE player = $id"))) &&
                !(self::isPlayerAfterLastCurve($id) && (
                    $pos['leftBoxEntrance'] ||
                    ($pos['byFinishLine'] && self::getUniqueValueFromDb("SELECT BoxBox FROM penalities_and_modifiers WHERE player = $id")) ||
                    ($pos['byBox'] && ($isBoost || self::getPlayerCurve($id)['number'] == 1 || self::getPlayerLap($id) == self::getGameStateValue('number_of_laps')-1))
                ))
            ) {

                if (!$pos['tireCost']) $noneWhite = false;
                $hasValid = true;
            }
        }

        return array('positions' => $positions, 'direction' => $dir, 'hasValid' => $hasValid, 'noneWhite' => $noneWhite);
    }

    /* 
    . drafting (no tire token used, min 3rd gear for both cars, same dir as enemy car, max 2 octagon distance from enemy car bottom)
    . slingshot pass (same as above, but only 1 oct max distance)
    . pushing (same as above)
    . shunting (min 2nd gear for player car only, same dir as enemy car, max 1 oct distance from enemey car bottom sides) 
    */
    function argAttackManeuvers() {

        $playerId = self::getActivePlayerId();
        $playerCar = self::getPlayerCarOctagon($playerId);

        $sql = "SELECT id
                FROM game_element
                WHERE entity = 'car' AND id != $playerId";
        $enemies = self::getObjectListFromDb($sql, true);

        $attEnemies = array();
        $canAttack = false;
        $movsAvail = false;

        // -- PLAYER CAN ATTACK CHECK
        $penalities = self::getObjectFromDb("SELECT NoDrafting, NoAttackMov, BoxBox FROM penalities_and_modifiers WHERE player = $playerId");
        if (self::getPlayerTurnPos($playerId) != 1 &&
            !$penalities['NoAttackMov'] && !$penalities['BoxBox'] &&
            !($playerCar->inPitZone(self::getPitwall(),'box','any'))
            ){
            $canAttack = true;
            
            foreach ($enemies as $enemyId) {
                
                $enemyCar = self::getPlayerCarOctagon($enemyId);

                $pw = self::getPitwall();

                // -- ENEMY CAN BE ATTACKED CHECK
                if (!self::getUniqueValueFromDb("SELECT BoxBox FROM penalities_and_modifiers WHERE player = $enemyId") && // enemy is not shielded by boxbox
                    !($enemyCar->inPitZone(self::getPitwall(),'box','any')) && // enemy is not in pitbox
                    $enemyCar->overtake($playerCar) && // enemy is in front of player
                    $playerCar->getDirection() == $enemyCar->getDirection() && // enemy has same direction of player
                    VektoracePoint::distance($playerCar->getCenter(),$enemyCar->getCenter()) <= 3*VektoraceGameElement::getOctagonMeasures()['size'] // enemy is within an acceptable range to be able to be attacked
                    ){

                    // init maneuvers arr
                    $maneuvers = array();

                    // create drafting manevuers detectors
                    $range2detectorVec = new VektoraceVector($enemyCar->getAdjacentOctagons(1,true), $enemyCar->getDirection(), 2, 'top');
                    $range1detectorOct = new VektoraceOctagon($enemyCar->getAdjacentOctagons(1,true), $enemyCar->getDirection());
                    /* //solution for car nose landing between range 2 detector vec octagons (empty triangle). good?
                    $midrange2detectorOct = new VektoraceOctagon($range2detectorVec->getCenter(), $enemyCar->getDirection()); // needed to detect car nose when is inside triangle between two octs of range 2 detector*/
                    $range1Collision = false;
                    $range2Collision = false;
                    
                    // -- DRAFTING MANEUVERS CONDITION CHECKS
                    if (!$penalities['NoDrafting'] &&
                        self::getPlayerCurrentGear($playerId) >= 3 &&
                        self::getPlayerCurrentGear($enemyId) >= 3
                        ){

                        if ($playerCar->collidesWith($range2detectorVec, 'car') && $playerCar->isBehind($range1detectorOct)) {
                            $range2Collision = true;

                            if ($playerCar->collidesWith($range1detectorOct, 'car'))
                                $range1Collision= true;
                        }/*  else if ($playerCar->collidesWith($midrange2detectorOct, 'car'))
                            $range2Collision = true; */
                    }

                    // -- CALC SLINGSHOT POSITIONS
                    $slingshotPos = array();

                    // slingshot pos are the 3 adjacent position in front of enemy car
                    $hasValid = false;
                    foreach ($enemyCar->getAdjacentOctagons(3) as $pos) {
                        $posOct = new VektoraceOctagon($pos);
                        $valid = !self::detectCollision($posOct) && !$posOct->inPitZone($pw,'box');
                        if ($valid) $hasValid = true;
                        $slingshotPos[] = array(
                            'pos' => $pos->coordinates(),
                            'valid' => $valid
                        );
                    }

                    // if none is valid it could be that another car is already in front of it
                    // player can then position his car on either side of the car already in front
                    if (!$hasValid) {
                        // search db for car in front of enemy car
                        ['x'=>$x, 'y'=>$y] = $enemyCar->getAdjacentOctagons(1)->coordinates();
                        $frontCar = self::getObjectFromDb("SELECT * FROM game_element WHERE entity = 'car' AND pos_x = $x AND pos_y = $y");

                        // if found, calc new singshot positions
                        if (!is_null($frontCar)) {
                            $frontCar = new VektoraceOctagon(new VektoracePoint($x,$y), $enemyCar->getDirection());
                            $sidePos = $frontCar->getAdjacentOctagons(5);
                            $left = $sidePos[0];
                            $right = $sidePos[4];
                            $leftOct = new VektoraceOctagon($left);
                            $valid = !self::detectCollision($leftOct) && !$posOct->inPitZone($pw,'box');
                            if ($valid) $hasValid = true;
                            
                            $slingshotPos[] = array(
                                'pos' => $left->coordinates(),
                                'valid' => $valid
                            );
                            $rightOct = new VektoraceOctagon($right);
                            $valid = !self::detectCollision($rightOct) && !$posOct->inPitZone($pw,'box');
                            if ($valid) $hasValid = true;
                            
                            $slingshotPos[] = array(
                                'pos' => $right->coordinates(),
                                'valid' => $valid
                            );

                            if ($hasValid) {
                                $slingshotPos = array_slice($slingshotPos,3,2);
                            }
                        } // otherwise, leave it be. no slingshot position is valid (all positions collide with other game elements)
                    }

                    // ADD DRAFTING MANEUVER DATA TO ENEMY MANEUVERS ARRAY
                    $maneuvers['drafting'] = array(
                        'name' => clienttranslate('Drafting'),
                        'attPos' => $range1detectorOct->getCenter()->coordinates(),
                        'catchPos' => $range2detectorVec->getBottomOct()->getCenter()->coordinates(),
                        'vecPos' => $range2detectorVec->getCenter()->coordinates(),
                        'vecDir' => $enemyCar->getDirection(),
                        'active' => $range2Collision,
                        'legal'=> !self::detectCollision($range2detectorVec, false, array($playerId))
                    );
                    $maneuvers['push'] = array(
                        'name' => clienttranslate('Push'),
                        'attPos' => $range1detectorOct->getCenter()->coordinates(),
                        'active' => $range1Collision,
                        'legal'=> !self::detectCollision($range1detectorOct, false, array($playerId))
                    );
                    $maneuvers['slingshot'] = array(
                        'name' => clienttranslate('Slingshot pass'),
                        'attPos' => $slingshotPos,
                        'active' => $range1Collision,
                        'legal' => $hasValid && !self::detectCollision($range1detectorOct, false, array($playerId))
                    );

                    // create shunting manevuers detectors
                    $sidesCenters = $enemyCar->getAdjacentOctagons(3,true);
                    $leftsideDetectorOct = new VektoraceOctagon($sidesCenters[0], $enemyCar->getDirection());
                    $rightsideDetectorOct = new VektoraceOctagon($sidesCenters[2], $enemyCar->getDirection());
                    $leftCollision = false;
                    $rightCollision = false;

                    // SHUNTING MANEUVERS CONDITION CHECK
                    if (self::getPlayerCurrentGear($playerId) >= 2 && self::getPlayerCurrentGear($enemyId) >= 2) {
                        
                        if ($playerCar->collidesWith($leftsideDetectorOct, 'car') && $playerCar->isBehind($leftsideDetectorOct)) $leftCollision = $hasValidMovs = true;
                        if ($playerCar->collidesWith($rightsideDetectorOct, 'car') && $playerCar->isBehind($rightsideDetectorOct)) $rightCollision = $hasValidMovs = true;

                    }

                    // ADD SHUNTING MANEUVER DATA TO ENEMY MANEUVERS ARRAY
                    $maneuvers['leftShunt'] = array(
                        'name' => clienttranslate('Left Shunt'),
                        'attPos' => $leftsideDetectorOct->getCenter()->coordinates(),
                        'active' => $leftCollision,
                        'legal'=> !self::detectCollision($leftsideDetectorOct, false, array($playerId))
                    );
                    $maneuvers['rightShunt'] = array(
                        'name' => clienttranslate('Right Shunt'),
                        'attPos' => $rightsideDetectorOct->getCenter()->coordinates(),
                        'active' => $rightCollision,
                        'legal'=> !self::detectCollision($rightsideDetectorOct, false, array($playerId))
                    );

                    $hasValidMovs = false;

                    foreach ($maneuvers as $mov) {
                        if ($mov['active'] && $mov['legal']) {
                            $hasValidMovs = $movsAvail = true;
                        }
                    }

                    // ADD EVERYTHING TO ENEMY ARRAY
                    $attEnemies[] = array(
                        'id' => $enemyId,
                        'coordinates' => $enemyCar->getCenter()->coordinates(),
                        'maneuvers' => $maneuvers,
                        'hasValidMovs' => $hasValidMovs
                    );
                }
            }
        }

        return array(
            "attEnemies" => $attEnemies,
            "attMovsAvail" => $movsAvail,
            "canAttack" => $canAttack,
            "playerCar" => array(
                "pos" => $playerCar->getCenter()->coordinates(),
                "dir" => $playerCar->getDirection(),
                "size" => array(
                    "width" => VektoraceGameElement::getOctagonMeasures()["size"],
                    "height" => VektoraceGameElement::getOctagonMeasures()["side"]
                )
            )
        );
    }

    function argPitStop() {

        $id = self::getActivePlayerId();
        $currGear = self::getPlayerCurrentGear($id);
        $speedSurplus = $currGear - 2;
        $amount = 8 - max($speedSurplus*2, 0);

        $tokens = self::getPlayerTokens($id);

        return array('tire' => $tokens['tire'], 'nitro' => $tokens['nitro'], 'amount' => $amount);
    }

    // return current gear.
    function argFutureGearDeclaration() {

        $id = self::getActivePlayerId();

        // if player in box, he might only choose the 2nd gear
        if (self::getPlayerCarOctagon($id)->inPitZone(self::getPitwall(),'box')) return array('gears' => array('unavail','curr','unavail','unavail','unavail'));
        if (self::getUniqueValueFromDb("SELECT CarStop FROM penalities_and_modifiers WHERE player = $id")) return array('gears' => array('avail','unavail','unavail','unavail','unavail'));

        $curr = self::getPlayerCurrentGear($id);
        $noShift = self::getObjectFromDb("SELECT NoShiftUp up, NoShiftDown down FROM penalities_and_modifiers WHERE player = $id");

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
        $next_player_id = self::getPlayerAfter($player_id);

        $np_turnpos = self::getPlayerTurnPos($next_player_id);

        $this->gamestate->changeActivePlayer($next_player_id);

        // if next player is first player
        if ($np_turnpos == 1) {
            $this->gamestate->nextState('gameStart');      
        } else {
            // else, keep positioning
            $this->gamestate->nextState('nextPositioningPlayer');
        }
    }

    function stGearVectorPlacement() {

        $id = self::getActivePlayerId();

        self::DbQuery("UPDATE penalities_and_modifiers SET CarStop = 0 WHERE player = $id");

        self::incStat(1,'turns_number');
        self::incStat(1,'turns_number',$id);

        $avgGear = self::getStat('average_gear',$id);
        $turns = self::getStat('turns_number',$id);

        $avgGear = (($avgGear*$turns) + self::getPlayerCurrentGear($id))/($turns+1);
        self::setStat($avgGear,'average_gear',$id);

        if (self::getPlayerTurnPos($id) == 1) self::incStat(1,'pole_turns',$id);

        $this->giveExtraTime($id);
    }

    function stBoostVectorPlacement() {
        if (!self::argBoostVectorPlacement()['hasValid']) {

            // this should not happen any longer but might be subject to change

            self::notifyAllPlayers('noBoostAvail', clienttranslate('${player_name} cannot legally palce a boost vector of any lenght'), array(
                'player_name' => self::getActivePlayerName(),
                'player_id' => self::getActivePlayerId(),
            ));

            $this->gamestate->nextState();
        }
    }

    function stEmergencyBrake() {

        $id = self::getActivePlayerId();
        self::incStat(1,'brake_number',$id);

        $shiftedGear = self::getPlayerCurrentGear($id) -1;
        $tireExpense = 1;
        $insuffTokens = false;

        while ($shiftedGear > 0) {
            //self::dump('// TRYING VECTOR ',$shiftedGear);

            $gearPlacement = self::argGearVectorPlacement($shiftedGear);
            //self::dump('// PLACE VECTOR ARGS',$gearPlacement);
            if ($gearPlacement['hasValid']) {

                // CHECK FOR AVAILABLE TOKENS AND UPDATE AMOUNT
                $tireTokens = self::getPlayerTokens($id)['tire'] - $tireExpense;

                // if tokens insufficent break loop, car will simply stop. mem bool val to notify player reason
                if ($tireTokens < 0 || ($tireTokens == 0 && $gearPlacement['noneWhite'])) {
                    //self::trace('// INSUFF TOKENS');
                    $insuffTokens = true;
                    self::notifyAllPlayers('brakeInsuffTokens', clienttranslate('${player_name} doesn\'t have enough tire tokens to downshift to a valid gear'), array(
                        'player_name' => self::getActivePlayerName(),
                        'player_id' => $id
                    ));
                    break;
                }

                self::DbQuery("UPDATE player SET player_tire_tokens = $tireTokens WHERE player_id = $id");
                self::incStat($tireExpense,'tire_used',$id);

                // UPDATE NEW GEAR
                $sql = "UPDATE player
                        SET player_current_gear = $shiftedGear
                        WHERE player_id = $id";
                self::DbQuery($sql);

                $log = clienttranslate('${player_name} spends ${tireExpense} ${tire_token} to downshift to ${gearNum}');
                $log = str_replace('gearNum','gear_'.$shiftedGear,$log); 

                // NOTIFY PLAYERS
                self::notifyAllPlayers('useNewVector', $log, array(
                    'player_name' => self::getActivePlayerName(),
                    'player_id' => $id,
                    'shiftedGear' => $shiftedGear,
                    'tireExpense' => $tireExpense,
                    'updatedTokensAmt' => $tireTokens,
                    'tire_token' => clienttranslate('tire token'),
                    'gear_'.$shiftedGear => self::getLiteralOrdinal($shiftedGear,true),
                    'i18n' => array('gear_'.$shiftedGear, 'tire_token'),
                ));

                // JUMP BACK TO VECTOR PLACEMENT PHASE
                $this->gamestate->nextState('slowdown');
                return;
            }

            $tireExpense ++;
            $shiftedGear --;
        } // if reaches 0 then car will completly stot (not move for one turn)

        self::DbQuery("UPDATE penalities_and_modifiers SET CarStop = 1 WHERE player = ".self::getActivePlayerId());

        /* // car will start next turn on gear 1
        $sql = "UPDATE player
                SET player_current_gear = 1
                WHERE player_id = ".self::getActivePlayerId();
        
        self::DbQuery($sql); */

        $this->gamestate->nextState('brake');
        return;

        // a rotation is still allowed, so state does not jump (args contain rotation arrows data)
    }

    function stGiveWay() {
        $id = self::getActivePlayerId();
        $playerTurnPos = self::getPlayerTurnPos($id);
        $enemyTurnPos = $playerTurnPos + 1;
        $enemy = self::getPlayerTurnPosNumber($enemyTurnPos);
        
        // APPLY PENALITY (NO ATTACK MANEUVERS)
        self::DbQuery("UPDATE penalities_and_modifiers SET NoAttackMov = 1 WHERE player = $id");

        // SWITCH PLAYERS TURN ORDER
        self::DbQuery("UPDATE player SET player_turn_position = $enemyTurnPos WHERE player_id = $id");
        self::DbQuery("UPDATE player SET player_turn_position = $playerTurnPos WHERE player_id = $enemy");

        self::notifyAllPlayers('giveWay', clienttranslate('${player_name} gives way to ${player_name2} which is obstructing his/her path'), array(
            'player_name' => self::getActivePlayerName(),
            'player_id' => $id,
            'player_name2' => self::getPlayerNameById($enemy),
            'player2_id' => $enemy
        ));

        $this->gamestate->changeActivePlayer($enemy);
        $this->gamestate->nextState();
    }

    function stAttackManeuvers() {

        $args = self::argAttackManeuvers();

        if (count($args['attEnemies']) == 0) {
            $this->gamestate->nextState('endOfMovement');
            return;
        }

        if (!$args['canAttack']) {
            if (self::getPlayerTurnPos(self::getActivePlayerId()) != 1)
                self::notifyAllPlayers('noAttMov', clienttranslate('${player_name} is restricted from performing any attack move this turn'), (array(
                    'player_name' => self::getActivePlayerName(),
                    'player_id' => self::getActivePlayerId(),
                    'enemies' => 0
                )));
            $this->gamestate->nextState('endOfMovement');
        } else if (!$args['attMovsAvail']) {
            if (self::getPlayerTurnPos(self::getActivePlayerId()) != 1)
                self::notifyAllPlayers('noAttMov', clienttranslate('${player_name} cannot perform any valid attack move this turn'), array(
                    'player_name' => self::getActivePlayerName(),
                    'player_id' => self::getActivePlayerId(),
                    'enemies' => count($args['attEnemies'])
                ));

            $this->gamestate->nextState('endOfMovement');
        }
    }

    function stPitStop() {

        $id = self::getActivePlayerId();
        $currGear = self::getPlayerCurrentGear($id);
        $speedSurplus = $currGear - 2;

        if ($currGear > 2) {
            self::notifyAllPlayers('boxSpeedPenality', clienttranslate('${player_name} exceeds Pit Box entrance speed limit by ${speedSurplus}: -${penality} refilled tokens'), array(
                'player_name' => self::getActivePlayerName(),
                'player_id' => self::getActivePlayerId(),
                'speedSurplus' => $speedSurplus,
                'penality' =>  $speedSurplus*2
            ));
        }
    }

    function stEndOfMovementSpecialEvents() {

        // store some useful vars
        $id = self::getActivePlayerId();
        $playerCar = self::getPlayerCarOctagon($id);
        $playerCurve = self::getPlayerCurve($id);

        $playerCurveNumber = $playerCurve['number'];
        $nextCurveNumber = $playerCurveNumber+1;
        if ($nextCurveNumber > self::getGameStateValue('last_curve')) $nextCurveNumber = 1;

        $curveZone = $playerCurve['zone'];

        /* self::dump("// PLAYER CURVE DUMP", [
            'curveNum' => $playerCurveNumber,
            'curveZone' => $curveZone,
            'nextCurve' => $nextCurveNumber
        ]); */

        $nextCurve = new VektoraceCurb($playerCurve['next']);
        $playerCurve = $playerCurve['object'];

        // CHECK IF CURRENT CURVE IS NOT LAST
        if ($nextCurveNumber != 1) {
            // if so, check if player reached reached and passed next curve

            // curve passed check (COULD BE BETTER?)
            // if car has left zone 4 of a curve, then it is considered to have passed and assigned the next curve as current one, indipendently of distance to that curve
            // if car is closer to next curve (but still hasn't passed 4th zone), assign car to next curve anyway (this is for when curves don't from a convex track shape)
            if ($curveZone > 4 || 
                VektoracePoint::distance($playerCar->getCenter(), $nextCurve->getCenter()) < VektoracePoint::distance($playerCar->getCenter(), $playerCurve->getCenter())) {

                // set new curve db
                self::DbQuery("UPDATE player SET player_curve_number = $nextCurveNumber WHERE player_id = $id");
                $playerCurveNumber = $nextCurveNumber;

                // calc new curve zone
                $curveZone = $playerCar->getCurveZone($nextCurve);
                if ($curveZone > 4) $curveZone = 0; // if curve zone is higher than 3 (likely 7, meaning behind curve, in rare situation where curves are far from each other and pointing in different directions)

                // set new curve zone
                self::DbQuery("UPDATE player SET player_curve_zone = $curveZone WHERE player_id = $id");
            }
        } else if ($curveZone > 4) {

            //self::trace("// PASSED LAST CURVE");

            // -- check finish line crossing
            // dot prod of vector pointing from pitwall top to its direction and vector pointing to nose of player car
            $pw = self::getPitwall();
            $pwProp = $pw->getProperties();
            $pwVec = $pwProp['c'];
            $pwFinishPoint = $pwProp['Q'];
            $carNose = $playerCar->getDirectionNorm()['origin'];
            $carVec = VektoracePoint::displacementVector($pwFinishPoint, $carNose);

            // car crosses line if is parallel to piwall AND if it's nose crosses the line
            if (VektoracePoint::dot($pwVec,$carVec) > 0) {
                self::DbQuery("UPDATE player SET player_lap_number = player_lap_number+1 WHERE player_id = $id");
                
                // update playter lap number
                $playerLapNum = self::getUniqueValueFromDb("SELECT player_lap_number FROM player WHERE player_id = $id");
                // check if player lap number is same provided by game options 
                if ($playerLapNum == self::getGameStateValue("number_of_laps")) {
                    // if so race should end for this player

                    $pos = self::getGameStateValue('first_avail_position');
                    
                    $score = self::getPlayersNumber() - $pos;
                    self::DbQuery("UPDATE player SET player_score = $score WHERE player_id = $id");
                    self::DbQuery("UPDATE penalities_and_modifiers SET FinishedRace = 1 WHERE player = $id");
                    self::DbQuery("DELETE FROM game_element WHERE id = $id");
                    
                    self::notifyAllPlayers('finishedRace',clienttranslate('${player_name} crosses the finish line in ${pos} position'), array(
                        'player_name' => self::getActivePlayerName(),
                        'player_id' => $id,
                        'pos' => self::getLiteralOrdinal($pos),
                        'posInt' => $pos,
                        'i18n' => array('pos'),
                        'lapNum' => $playerLapNum
                    ));

                    self::incGameStateValue('racing_players_number',-1);
                    self::incGameStateValue('first_avail_position',1);

                    if (self::getGameStateValue('racing_players_number') == 1) {

                        $lastPlayerId = self::getUniqueValueFromDb("SELECT id FROM game_element WHERE entity = 'car'");
                        $pos = self::getGameStateValue('first_avail_position');

                        self::notifyAllPlayers('finishedRace',clienttranslate('${player_name} finishes the race in ${pos} position'), array(
                            'player_name' => self::getPlayerNameById($lastPlayerId),
                            'player_id' => $lastPlayerId,
                            'pos' => self::getLiteralOrdinal($pos),
                            'posInt' => $pos,
                            'i18n' => array('pos'),
                            'lapNum' => $playerLapNum-1
                        ));

                        $raceLeaderboard = self::getCollectionFromDb("SELECT player_id, player_score FROM player", true);
                        foreach ($raceLeaderboard as $player => $score) {
                            $finalPos = self::getPlayersNumber() - $score;
                            self::DbQuery("UPDATE player SET player_turn_position = $finalPos WHERE player_id = $player");
                        }

                        $this->gamestate->nextState('raceEnd');
                    }
                    else $this->gamestate->nextState('skipGearDeclaration');

                    return;
                        
                } else {

                    // send notif about player completing a lap
                    self::notifyAllPlayers('lapFinish',clienttranslate('${player_name} completes his/her ${lapNum} lap'), array(
                        'player_name' => self::getActivePlayerName(),
                        'player_id' => $id,
                        'n' => $playerLapNum,
                        'lapNum' => self::getLiteralOrdinal($playerLapNum, true),
                        'i18n' => array('lapNum'),
                    ));

                    // reset boxbox
                    self::DbQuery("UPDATE penalities_and_modifiers SET BoxBox = NULL WHERE player = $id");

                    // set new curve db
                    self::DbQuery("UPDATE player SET player_curve_number = $nextCurveNumber WHERE player_id = $id"); // next curve number should be 1

                    // calc new curve zone
                    $curveZone = $playerCar->getCurveZone($nextCurve);
                    if ($curveZone > 3) $curveZone = 0; // i curve zone greater than 3 it's likely player curve is far and facing a weird direction. set zone to 0 to avoid misdetection of offroad

                    // set new curve zone
                    self::DbQuery("UPDATE player SET player_curve_zone = $curveZone WHERE player_id = $id");
                }
            } else { // else, if finish line has not been crossed

                // and if player hasn't decided on calling boxbox
                if (is_null(self::getUniqueValueFromDb("SELECT BoxBox FROM penalities_and_modifiers WHERE player = $id")) &&
                    $playerCar->inPitZone(self::getPitwall(),'entrance') &&
                    self::getPlayerLap($id) < self::getGameStateValue('number_of_laps')-1) {

                    // go to boxbox promt state
                    $this->gamestate->nextState('boxBox');
                    return;
                }
            }
        }

        $this->gamestate->nextState('gearDeclaration');
    }

    // gives turn to next player for car movement or recalculates turn order if all players have moved their car
    function stNextPlayer() {

        $player_id = self::getActivePlayerId();
        $np_id = self::getPlayerAfterCustom($player_id);
        
        self::DbQuery("UPDATE penalities_and_modifiers 
                       SET NoBlackMov = 0,
                           NoShiftDown = 0,
                           NoShiftUp = 0,
                           NoAttackMov = 0,
                           NoDrafting = 0,
                           DeniedSideLeft = 0,
                           DeniedSideRight = 0
                       WHERE player = $player_id");

        if (self::isPlayerZombie($player_id)) self::removeZombieCar($player_id);
        
        if (self::getPlayerTurnPos($np_id) == 1) {

            $order = self::newTurnOrder();

            if ($order['isChanged']) self::notifyAllPlayers('turnOrderChanged', clienttranslate('The turn order has changed'), array());

            self::notifyAllPlayers('nextRoundTurnOrder', clienttranslate('A new game round begins'), array(
                'order' => $order['list'],
                'firstPos' => self::getGameStateValue('first_avail_position')
            ));

            self::incGameStateValue('turn_number', 1);

            $np_id = self::getPlayerTurnPosNumber(1);

        }

        while (self::isPlayerRaceFinished($np_id)) {
            $np_id = self::getPlayerAfterCustom($np_id);
        }

        $this->gamestate->changeActivePlayer($np_id);

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
                case 'firstPlayerPositioning':
                    self::placeFirstCar(0,0,true);
                    break;

                case 'greenLight':
                    self::chooseStartingGear(4);
                    break;
                    
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
