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
        self::initGameStateLabels( array(
            "turn_number" => 10,
            "last_curve" => 11,
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
        self::setGameStateInitialValue('turn_number', 0 );
        self::setGameStateInitialValue('last_curve', self::getUniqueValueFromDb("SELECT count(id) FROM game_element WHERE entity='curve'"));
        self::setGameStateInitialValue('number_of_laps', $this->gamestate->table_globals[100]);
        self::setGameStateInitialValue('circuit_layout', $this->gamestate->table_globals[101]);
        self::setGameStateInitialValue('map_boundaries', $this->gamestate->table_globals[102]);
        
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

        self::dump('// RACE LAPS NUMBER',$this->gamestate->table_globals[100]);
        self::dump('// RACE CIRCUIT',$this->gamestate->table_globals[101]);

        //self::dump("// CURR STATE DUMP", $this->gamestate->state());

        /* $ret = array();
        
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
        ); */
    }

    function mapVertices() {
        $siz = VektoraceOctagon::getOctProperties()['size'];
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
                        $v->scale(0.75,0.75);
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
    
    function switchTurnPos($p1, $p2) {
        $p1id = self::getPlayerTurnPosNumber($p1);
        $p2id = self::getPlayerTurnPosNumber($p2);

        self::DbQuery("UPDATE player SET player_turn_position = $p2 WHERE player_id = $p1id");
        self::DbQuery("UPDATE player SET player_turn_position = $p1 WHERE player_id = $p2id");

        return;
    }

    function moveAllCars() {
        $x = 1000;
        $y = 1200;
        $dir = 0;


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

    function isBoxBoxPlayerPos($p) {
        $query = self::getUniqueValueFromDb("SELECT BoxBox FROM penalities_and_modifiers WHERE player =".self::getPlayerTurnPosNumber($p));

        self::consoleLog(array(
            'boolval' => boolval($query),
            'val' => $query,
            'isnull' => is_null($query)
        ));
    }

    // loadTrackPreset: sets DB to match a preset of element of a test track
    function loadTrackPreset() {

        switch (self::getGameStateValue('circuit_layout')) {
            case 1:
                self::DbQuery(
                    "INSERT INTO game_element (entity, id, pos_x, pos_y, orientation)
                        VALUES ('pitwall',10,0,0,4),
                               ('curve',1,-505,445,5),
                               ('curve',2,-505,1115,3),
                               ('curve',3,1655,1115,1),
                               ('curve',4,1655,445,7)"
                );
                break;

            case 2:
                self::DbQuery(
                    "INSERT INTO game_element (entity, id, pos_x, pos_y, orientation)
                        VALUES ('pitwall',10,0,0,4),
                               ('curve',1,-505,445,5),
                               ('curve',2,-505,1115,3),
                               ('curve',4,1655,445,7)"
                );
                break;

            case 3:
                self::DbQuery(
                    "INSERT INTO game_element (entity, id, pos_x, pos_y, orientation)
                        VALUES ('pitwall',10,0,0,4),
                               ('curve',1,-505,445,5),
                               ('curve',2,1655,1115,1),
                               ('curve',3,1655,445,7)"
                );
                break;

            case 4:
                self::DbQuery(
                    "INSERT INTO game_element (entity, id, pos_x, pos_y, orientation)
                        VALUES ('pitwall',10,0,0,4),
                               ('curve',1,-505,445,5),
                               ('curve',2,580,900,2),
                               ('curve',3,1655,445,7)"
                );
                break;
        }
    }

    function getPlayerCurve($id) {
        return self::getObjectFromDb("SELECT player_curve_number num, player_curve_zone zon FROM player WHERE player_id = $id");
    }

    function getCurveObject($n) {
        $curve = self::getObjectFromDb("SELECT id, pos_x x, pos_y y, orientation dir FROM game_element WHERE entity = 'curve' AND id = $n");
        return new VektoraceOctagon(new VektoracePoint($curve['x'], $curve['y']), $curve['dir'], true);
    }

    function isPlayerAfterLastCurve($id) {
        $playerCurve = self::getPlayerCurve($id);
        return $playerCurve['num'] == self::getGameStateValue('last_curve') && $playerCurve['zon'] > 4;
    }

    /* function isPlayerBoxBox($id) {

    } */

    function isPlayerRaceFinished($id) {
        return self::getUniqueValueFromDb("SELECT FinishedRace FROM penalities_and_modifiers WHERE player = $id");
    }

    function getLastFixedPos() {
        $i;
        for ($i=self::getPlayersNumber()-2; $i > 0; $i--) {
            if (self::isPlayerRaceFinished(self::getPlayerTurnPosNumber($i)))
                break;
        }

        return $i;
    }

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
        return new VektoraceVector(new VektoracePoint($pw['x'], $pw['y']), $pw['dir'], 4);
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
        $sql = "SELECT player_id id, player_curve_number curve, player_curve_zone curveZone, player_lap_number lap, pos_x x, pos_y y, orientation dir
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
                
                if (/* !self::isPlayerRaceFinished($nextPlayer['id']) &&  */(
                        $thisPlayer['lap'] > $nextPlayer['lap'] ||
                        ($thisPlayer['lap'] == $nextPlayer['lap'] && $thisPlayer['curve'] > $nextPlayer['curve']) ||
                        $playerCar->overtake($nextCar)
                    )
                ) {
                    $isChanged = true;

                    $temp = $allPlayers[$j+1];
                    $allPlayers[$j+1] = $allPlayers[$j];
                    $allPlayers[$j] = $temp;
                } 
            }
        }

        $lastFixedPos = self::getLastFixedPos();

        $ret = array();
        foreach($allPlayers as $i => $player) {
            $turnPos = $lastFixedPos + count($allPlayers) - intval($i);

            $sql = "UPDATE player
                    SET player_turn_position = $turnPos
                    WHERE player_id = ".$player['id'];
            self::DbQuery($sql);

            $ret[$player['id']] = $turnPos;
        }

        return array('list'=>$ret, 'isChanged'=>$isChanged);
    }

    function withinMapBoundaries($oct) {
        $boundariesOpt = self::getGameStateValue('map_boundaries');

        if ($boundariesOpt == 1) return true;

        $siz = VektoraceOctagon::getOctProperties()['size'];

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

        ['x'=>$x, 'y'=>$y] = $oct->getCenter()->coordinates();
        
        return ($x > $mapX[0] && $x < $mapX[1] && $y > $mapY[0] && $y < $mapY[1]);
    }

    // big messy method checks if subj object (can be either octagon or vector) collides with any other element on the map (cars, curves or pitwall)
    function detectCollision($subj, $isVector=false, $ignoreElements = array()) {
        
        self::dump('/// ANALIZING COLLISION OF '.(($isVector)? 'VECTOR':'CAR POSITION'),$subj->getCenter()->coordinates());
        self::dump('/// DUMP SUBJECT',$subj);

        foreach (self::getObjectListFromDb("SELECT * FROM game_element") as $element) {

            if (!is_null($element['pos_x']) && !is_null($element['pos_y']) && !in_array($element['id'],$ignoreElements)) {

                $pos = new VektoracePoint($element['pos_x'],$element['pos_y']);

                self::dump('// WITH '.$element['entity'].' '.$element['id'].' AT ', $pos->coordinates());

                // pitwall is a very special case as it is 0.75 times smaller than a standard gear 4 vector
                if ($element['entity']=='pitwall') {

                    // extract all pitwall vertices
                    $pitwall = new VektoraceVector($pos, $element['orientation'], 4);
                    $pwTop = $pitwall->getTopOct()->getVertices();
                    $pwBottom = $pitwall->getBottomOct()->getVertices();
                    $pwInnerRect = $pitwall->innerRectVertices();

                    // if pitwall is not at origin, before scaling, translate all points so that center matches origin and then translate back to where they were before
                    if ($element['pos_x'] != 0 || $element['pos_y'] != 0) {
                        
                        $pwCenter = $pitwall->getCenter();
                        
                        foreach ($pwTop as &$v) {
                            $v->changeRefPlane($pwCenter);
                            $v->scale(0.75,0.75);
                            $v->translate($pwCenter->x(),$pwCenter->y());
                        } unset($v);

                        foreach ($pwBottom as &$v) {
                            $v->changeRefPlane($pwCenter);
                            $v->scale(0.75,0.75);
                            $v->translate($pwCenter->x(),$pwCenter->y());
                        } unset($v);

                        foreach ($pwInnerRect as &$v) {
                            $v->changeRefPlane($pwCenter);
                            $v->scale(0.75,0.75);
                            $v->translate($pwCenter->x(),$pwCenter->y());
                        } unset($v);

                    } else { // if center is already at origin apply only scaling

                        foreach ($pwTop as &$v) {
                            $v->scale(0.75,0.75);
                        } unset($v);

                        foreach ($pwBottom as &$v) {
                            $v->scale(0.75,0.75);
                        } unset($v);

                        foreach ($pwInnerRect as &$v) {
                            $v->scale(0.75,0.75);
                        } unset($v);
                    }

                    if ($isVector) {

                        $vecTop = $subj->getTopOct()->getVertices();
                        $vecBottom = $subj->getBottomOct()->getVertices();
                        $vecInnerRect = $subj->innerRectVertices();

                        // big fat if checks if both vector's anchors collide with eachother, if anchors collide with other vector's inenr rect and if the respective inner rect collide with eachother
                        if (VektoraceOctagon::SATcollision($pwTop, $vecTop) ||
                            VektoraceOctagon::SATcollision($pwTop, $vecBottom) ||
                            VektoraceOctagon::SATcollision($pwBottom, $vecBottom) ||
                            VektoraceOctagon::SATcollision($pwBottom, $vecTop) ||
                            VektoraceOctagon::SATcollision($pwInnerRect, $vecInnerRect) ||
                            VektoraceOctagon::SATcollision($pwInnerRect, $vecTop) ||
                            VektoraceOctagon::SATcollision($pwInnerRect, $vecBottom) ||
                            VektoraceOctagon::SATcollision($pwTop, $vecInnerRect) ||
                            VektoraceOctagon::SATcollision($pwBottom, $vecInnerRect)
                        ) {
                            self::trace('// -!- COLLISION DETECTED -!-');
                            /* self::dump('// VECTOR TO PITWALL COLLISION', array(
                                'top - top' => VektoraceOctagon::SATcollision($pwTop, $vecTop),
                                'top - bot' => VektoraceOctagon::SATcollision($pwTop, $vecBottom),
                                'bot - bot' => VektoraceOctagon::SATcollision($pwBottom, $vecBottom),
                                'bot - top' => VektoraceOctagon::SATcollision($pwBottom, $vecTop),
                                'inner - inner' => VektoraceOctagon::SATcollision($pwInnerRect, $vecInnerRect),
                                'inner - top' => VektoraceOctagon::SATcollision($pwInnerRect, $vecTop),
                                'inner - bot' => VektoraceOctagon::SATcollision($pwInnerRect, $vecBottom),
                                'top - inner' => VektoraceOctagon::SATcollision($pwTop, $vecInnerRect),
                                'top - inner' => VektoraceOctagon::SATcollision($pwBottom, $vecInnerRect)
                            )); */
                            return true;
                        }

                    } else { // subject is a standard octagon

                        $subjOct = $subj->getVertices();

                        if (
                            VektoraceOctagon::SATcollision($pwTop, $subjOct) ||
                            VektoraceOctagon::SATcollision($pwBottom, $subjOct) ||
                            VektoraceOctagon::SATcollision($pwInnerRect, $subjOct)
                        ) {
                            self::trace('// -!- COLLISION DETECTED -!-');
                            return true;
                        }
                    }

                } else {
                    
                    if ($isVector) {

                        $obj = new VektoraceOctagon($pos, $element['orientation'], $element['entity']=='curve');
                        if ($obj->collidesWithVector($subj)) {
                            self::trace('// -!- COLLISION DETECTED -!-');
                            return true;
                        }

                    } else {

                        $obj = new VektoraceOctagon($pos, $element['orientation'], $element['entity']=='curve');
                        
                        if ($obj->collidesWith($subj)) {
                            self::trace('// -!- COLLISION DETECTED -!-');
                            return true;
                        }
                    }
                }
            }
        }

        $in = false;
        if ($isVector) {
            $in = self::withinMapBoundaries($subj->getTopOct()) && self::withinMapBoundaries($subj->getBottomOct());
        } else $in = self::withinMapBoundaries($subj);

        if ($in) return false;
        else {
            self::trace('// -!- OUT OF BOUNDS -!-');
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

            $pos = $args['positions'];

            //[$refCarId]['positions'][$posIdx]

            foreach ($args['positions'] as $refcar) {

                if ($refcar['carId'] == $refCarId) {

                    if (array_key_exists($posIdx, $refcar['positions'])) {

                        $pos = $refcar['positions'][$posIdx];

                        if (!$pos['valid']) throw new BgaUserException('Illegal car position');

                        ['x'=>$x,'y'=>$y] = $pos['coordinates'];

                        $id = self::getActivePlayerId();
            
                        $sql = "UPDATE game_element
                                SET pos_x = $x, pos_y = $y
                                WHERE id = $id";
                    
                        self::DbQuery($sql);
            
                        self::notifyAllPlayers('placeCarFS', clienttranslate('${player_name} chose their car starting position'), array(
                            'player_id' => $id,
                            'player_name' => self::getActivePlayerName(),
                            'x' => $x,
                            'y' => $y,
                            'refCar' => $refCarId
                        ));
            
                        $this->gamestate->nextState();
                        return;

                    } else throw new BgaUserException('Invalid car position');
                }
            }
            throw new BgaUserException('Invalid reference car id');            
        }
    }

    function chooseTokensAmount($tire,$nitro, $pitStop = false) {
        if ($this->checkAction('chooseTokensAmount')) {

            $args = $this->gamestate->state()['args'];

            if ($tire < $args['tire'] || $nitro < $args['nitro']) throw new BgaUserException('You cannot have a negative transaction of tokens');
            if ($tire > 8 || $nitro > 8) throw new BgaUserException('You cannot have more than 8 tokens for each type');
            if ($tire+$nitro != min($args['tire'] + $args['nitro'] + $args['amount'], 16)) throw new BgaUserException('You have to fill your token reserve with the correct amount');

            $id = self::getActivePlayerId();

            $prevTokens = self::getPlayerTokens($id);

            $sql = "UPDATE player
                    SET player_tire_tokens = $tire, player_nitro_tokens = $nitro
                    WHERE player_id = $id";

            self::DbQuery($sql);

            $action = clienttranslate("chose to start the game with ");

            if ($pitStop) {
                $action = clienttranslate("refilled their token reserve with ");

                $tire = $tire - $prevTokens['tire'];
                $nitro = $nitro - $prevTokens['nitro'];
            }

            self::notifyAllPlayers('chooseTokensAmount', clienttranslate('${player_name} ${action} ${tire} TireTokens and ${nitro} NitroTokens'), array(
                'player_id' => $id,
                'player_name' => self::getActivePlayerName(),
                'action' => $action,
                'tire' => $tire,
                'nitro' => $nitro,
                'prevTokens' => $prevTokens
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

            self::incGameStateValue('turn_number', 1);

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
                    if ($pos['offTrack']) throw new BgaUserException('You cannot pass a curve from behind');
                    if (!$pos['carPosAvail']) throw new BgaUserException("This gear vector doesn't allow any vaild car position");

                    $id = self::getActivePlayerID();

                    $tireTokens = self::getPlayerTokens($id)['tire'];

                    $optString = '';

                    // CHECK TIRE COST
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
                    }/*  else $tireTokens = null; */

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

                    self::notifyAllPlayers('placeGearVector', clienttranslate('${player_name} placed the gear vector'.$optString), array(
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

            throw new BgaUserException('Invalid gear vector position');
        }
    }

    function brakeCar() {
        if ($this->checkAction('brakeCar')) {

            // check if player has indeed no valid positionts, regardless of which state he takes this action from (car or vector placement)
            $arg = self::argGearVectorPlacement();
            if ($arg['hasValid']) throw new BgaUserException('You cannot perform this move if you already have valid positions');

            /* if ($this->gamestate->state()['name'] == 'carPlacement') {
                // if called during this state, a vector has already been places so it has to be removed from db
                $sql = "DELETE FROM game_element
                        WHERE entity = 'gearVector'";
                self::DbQuery($sql);
            } */
            
            // APPLY PENALITY (NO BLACK MOVES, NO ATTACK MANEUVERS, NO SHIFT UP)
            self::DbQuery("UPDATE penalities_and_modifiers SET NoBlackMov = 1, NoAttackMov = 1, NoShiftUp = 1 WHERE player = ".self::getActivePlayerId());

            self::notifyAllPlayers('brakeCar', clienttranslate('${player_name} had to brake to avoid a collision or invalid move'), array(
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

            if (!$arg['canGiveWay']) throw new BgaUserException('You cannot give way if no other player is obstructing your path');

            // APPLY PENALITY (NO ATTACK MANEUVERS)
            self::DbQuery("UPDATE penalities_and_modifiers SET NoAttackMov = 1 WHERE player = $id");

            $playerTurnPos = self::getPlayerTurnPos($id);
            $enemyTurnPos = $playerTurnPos + 1;
            $enemy = self::getPlayerTurnPosNumber($enemyTurnPos);

            self::notifyAllPlayers('giveWay', clienttranslate('${player_name} gave way to ${player_name2} to avoid a collision'), array(
                'player_name' => self::getActivePlayerName(),
                'player_id' => $id,
                'player_name2' => self::getPlayerNameById($enemy),
                'player2_id' => $enemy
            ));

            /* $playerTurnPos = self::getPlayerTurnPos($id);
            $enemyTurnPos = $playerTurnPos + 1;
            $enemy = self::getPlayerTurnPosNumber($enemyTurnPos);
            self::DbQuery("UPDATE player SET turn_pos = $enemyTurnPos WHERE player_id = $id");
            self::DbQuery("UPDATE player SET turn_pos = $playerTurnPos WHERE player_id = $enemy"); */

            $this->gamestate->nextState('setNewTurnOrder');
            return;
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

                self::notifyAllPlayers('rotateAfterBrake', clienttranslate('${player_name} had to stop their car'), array(
                    'player_name' => self::getActivePlayerName(),
                    'player_id' => $id,
                    'rotation' => $dir-1
                ));

                $this->gamestate->nextState('');
                return;

            } else throw new BgaUserException("Invalid direction");
        }
    }

    function useBoost($use) {
        if ($this->checkAction('useBoost')) {

            if($use) {

                $id = self::getActivePlayerId();
                $nitroTokens = self::getPlayerTokens($id)['nitro'];

                if ($nitroTokens == 0) throw new BgaUserException(self::_("You don't have enough Nitro Tokens to use a boost"));

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
                    if (!$pos['carPosAvail']) throw new BgaUserException('No legal car position available with this boost lenght');

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
                        'direction' => $direction
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

            // I SHOULD FILTER HERE INSTEAD OF POINTLESS FOREACH FOR USING ONE ITEM ONLY
            foreach ($allPos as $pos) {
                
                if ($pos['position'] == $position) {

                    if (!$pos['legal']) throw new BgaUserException('Illegal car position');
                    if ($pos['denied']) throw new BgaUserException('Car position denied by the previous shunking you suffered');

                    $allDir = $pos['directions'];

                    foreach ($allDir as $dir) {
                        
                        if ($dir['direction'] == $direction) {

                            $id = self::getActivePlayerId();
                            
                            $previousPos = self::getPlayerCarOctagon($id);
                            
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

                            // remove any vectror used during movement
                            $sql = "DELETE FROM game_element
                                    WHERE entity = 'gearVector' OR entity = 'boostVector'";
                            self::DbQuery($sql);

                            // UPDATE CURVE PROGRESS
                            $curveProgress = $pos['curveProgress'];
                            self::DbQuery("UPDATE player SET player_curve_zone = $curveProgress WHERE player_id = $id");

                            self::notifyAllPlayers('placeCar', clienttranslate('${player_name} placed their car'.$optString), array(
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
                                if ($previousPos->inPitZone($pw, 'EoC') && $pos['byFinishLine'] && self::getUniqueValueFromDb("SELECT BoxBox FROM penalities_and_modifiers WHERE player = $id")) throw new BgaUserException('You cannot pass by the finish line after calling "BoxBox!"');
                                if ($previousPos->inPitZone($pw, 'entrance')) {
                                    if ($pos['byBox'] && !is_null(self::getUniqueValueFromDb("SELECT id FROM game_element WHERE entity = 'boostVector'"))) throw new BgaUserException('You cannot enter the box using a boost vector');
                                    if ($pos['byBox'] && self::getPlayerCurve($id)['num'] == 1) throw new BgaUserException('You cannot go to the Pit-Box at the start of the race');
                                    if ($pos['byBox'] && self::getPlayerLap($id) == self::getGameStateValue('number_of_laps')-1) throw new BgaUserException('You cannot go to the Pit-Box on your last lap');
                                }
                                if ($currPos->inPitZone($pw, 'box', 'any') && $currPos->getDirection() != $pw->getDirection()) throw new BgaUserException(self::_("You are not allowed to rotate the car while inside the Pit-Box"));

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

                                    self::notifyAllPlayers('boxEntranceOvershoot', clienttranslate('${player_name} wasn\'t able to stop by the box. They won\'t be refilling their tokens next turn'), array(
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

                                        self::notifyAllPlayers('boxEntrance', clienttranslate('${player_name} entered the pit box'), array(
                                            'player_name' => self::getActivePlayerName(),
                                            'player_id' => $id,
                                        ));
    
                                        $this->gamestate->nextState('boxEntrance'); // refill tokens
                                    } else $this->gamestate->nextState('endMovement'); // else car should be in exit, skips attack

                                    return;
                                }
                            }

                            $this->gamestate->nextState('attack');
                            return;
                        }
                    }

                    throw new BgaVisibleSystemException('Invalid car direction');

                }
            }

            throw new BgaUserException('Invalid car position');
        }
    }

    function engageManeuver($enemy, $action, $posIndex) {
        if ($this->checkAction('engageManeuver')) {

            $args = self::argAttackManeuvers();
            $id = self::getActivePlayerId();

            $penalities = self::getObjectFromDb("SELECT NoDrafting, NoAttackMov, BoxBox FROM penalities_and_modifiers WHERE player = $id");
            if ($penalities['NoAttackMov'] || $penalities['BoxBox']) throw new BgaUserException('You are currently restricted from performing any action maneuver');
            if (($action == 'drafting' || $action == 'push' || $action == 'slingshot') && $penalities['NoDrafting']) throw new BgaUserException('You cannot perform drafting maneuvers after speding tire tokens during your movement phase');

            $mov = null;
            foreach ($args['attEnemies'] as $en) {
                if ($en['id'] == $enemy) {
                    $mov = $en['maneuvers'][$action];
                }
            }
            if (is_null($mov)) throw new BgaUserException('Invalid attack move');


            if (!$mov['active']) throw new BgaUserException('You do not pass the requirements to be able to perform this maneuver');
            if (!$mov['legal']) throw new BgaUserException('Illegal attack position');

            $attPos = $mov['attPos'];
            if ($action == 'slingshot') {
                if (!$attPos[$posIndex]['valid']) throw new BgaUserException('Illegal attack position');
                $attPos = $attPos[$posIndex]['pos'];
            }

            ['x'=>$x, 'y'=>$y] = $attPos;

            $posOct = new VektoraceOctagon(new VektoracePoint($x,$y),self::getPlayerCarOctagon($id)->getDirection());
            if ($posOct->inPitZone(self::getPitwall(),'box')) throw new BgaUserException('You cannot enter the box with an attack maneuver');

            self::DbQuery("UPDATE game_element SET pos_x = $x, pos_y = $y WHERE id = $id"); // don't worry about db update being before checking nitroTokens, any thrown exception discards the transaction and reset db top previous state

            $nitroTokens = null; // needed for slingshot

            switch ($action) {
                case 'drafting':
                    $desc = clienttranslate('${player_name} took the slipstream of ${player_name2}');                
                    break;

                case 'push':
                    $desc = clienttranslate('${player_name} pushed ${player_name2} form behind');
                    self::DbQuery("UPDATE penalities_and_modifiers SET NoShiftDown = 1 WHERE player = $enemy");
                    break;

                case 'slingshot':

                    $nitroTokens = self::getPlayerTokens($id)['nitro'] - 1;
                    if ($nitroTokens < 0) throw new BgaUserException("You don't have enough Nitro Tokens to perform this action");
                    self::DbQuery("UPDATE player SET player_nitro_tokens = $nitroTokens WHERE player_id = $id");
                    
                    $desc = clienttranslate('${player_name} overtook ${player_name2} with a Slingshot maneuver (-1 Nitro Token)');
                    break;

                case 'leftShunk':
                    $desc = clienttranslate('${player_name} shunked ${player_name2} from the left');
                    self::DbQuery("UPDATE penalities_and_modifiers SET DeniedSideLeft = 1 WHERE player = $enemy");
                    break;

                case 'rightShunk':
                    $desc = clienttranslate('${player_name} shunked ${player_name2} from the right');
                    self::DbQuery("UPDATE penalities_and_modifiers SET DeniedSideRight = 1 WHERE player = $enemy");
                    break;
            }

            self::notifyAllPlayers('engageManeuver',$desc,array(
                'player_name' => self::getActivePlayerName(),
                'player_id' => $id,
                'player_name2' => self::getPlayerNameById($enemy),
                'attackPos' => $attPos,
                'nitroTokens' => $nitroTokens
            ));

            $this->gamestate->nextState('completeManeuver');
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

    function boxBox($skip) {
        if ($this->checkAction('boxBox')) {
            $id = self::getActivePlayerId();

            if ($skip) {
                self::DbQuery("UPDATE penalities_and_modifiers SET BoxBox = 0 WHERE player = $id");

                $this->gamestate->nextState('');
            }
            else {
                self::DbQuery("UPDATE penalities_and_modifiers SET BoxBox = 1 WHERE player = $id");

                self::notifyAllPlayers('boxBox',clienttranslate('${player_name} called "BoxBox!"'),array(
                    'player_name' => self::getActivePlayerName(),
                    'player_id' => self::getActivePlayerId()
                ));

                $this->gamestate->nextState('');
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
        
        $sql = "SELECT pos_x x, pos_y y, orientation dir
                FROM game_element
                WHERE entity = 'pitwall'";
        $pitwall = self::getObjectFromDb($sql);

        $pitwall = new VektoraceVector (new VektoracePoint($pitwall['x'], $pitwall['y']), $pitwall['dir'], 4);
        $anchorVertex = $pitwall->getBottomOct()->getVertices()[1];

        $anchorVertex->changeRefPlane($pitwall->getCenter());
        $anchorVertex->scale(0.75,0.75);
        $anchorVertex->translate($pitwall->getCenter()->x(),$pitwall->getCenter()->y());
        
        $windowCenter = clone $anchorVertex;

        $placementWindowSize = array('width' => VektoraceOctagon::getOctProperties()['size'], 'height' => VektoraceOctagon::getOctProperties()['size']*5);
        
        $ro = $placementWindowSize['width']/2;
        $the = ($pitwall->getDirection()-4) * M_PI_4;
        $windowCenter->translate($ro*cos($the), $ro*sin($the));

        $ro = $placementWindowSize['height']/2;
        $the = ($pitwall->getDirection()-2) * M_PI_4;
        $windowCenter->translate($ro*cos($the), $ro*sin($the));

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
                    $posOct = new VektoraceOctagon($pos); // construct octagon of current position

                    /* $vertices = $posOct->getVertices();
                    foreach ($vertices as &$v) {
                        $v = $v->coordinates();
                    } unset($v); */

                    $valid = $posOct->isBehind($playerBeforeCar) && !self::detectCollision($posOct);
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

        ['num'=>$curveNum, 'zon'=>$curveZone] = self::getPlayerCurve($id);
        $playerCurve = self::getCurveObject($curveNum);

        $playerTurnPos = self::getPlayerTurnPos($id);
        $ignorePlayer = ($playerTurnPos == self::getPlayersNumber() || self::getPlayerTurnPos($id) == 1)? [] : [self::getPlayerTurnPosNumber($playerTurnPos+1)];

        // iter through all 5 adjacent octagon
        foreach ($playerCar->getAdjacentOctagons(5) as $i => $anchorPos) {

            if (!($currentGear==1 && ($i==0 || $i==4))) {

                // construct vector from that anchor position
                $vector = new VektoraceVector($anchorPos, $direction, $currentGear, 'bottom');

                // return vector center to make client easly display it, along with anchor pos for selection octagon, and special properties flag
                $positions[] = array(
                    'position' => $posNames[$i],
                    'anchorCoordinates' => $anchorPos->coordinates(),
                    'vectorCoordinates' => $vector->getCenter()->coordinates(),
                    'tireCost' => ($i == 0 || $i == 4), // pos 0 and 4 are right-side and left-side respectevly, as AdjacentOctagons() returns position in counter clockwise fashion
                    'legal' => !self::detectCollision($vector,true),
                    'denied' => ($i < 2 && $deniedSide['R']) || ($i > 2 && $deniedSide['L']),
                    'obstructed' => !self::detectCollision($vector,true, $ignorePlayer),
                    'offTrack' =>  ($playerCurve->curveProgress($vector->getTopOct()) - $curveZone) > 3,
                    'curveProgress'=> $playerCurve->curveProgress($vector->getTopOct()),
                    'carPosAvail' => self::argCarPlacement($vector)['hasValid']
                );
            }
        }

        $hasValid = false;
        foreach ($positions as $pos) {
            if ($pos['carPosAvail'] && $pos['legal'] && !$pos['denied'] && !$pos['offTrack'] && !($pos['tireCost'] && self::getPlayerTokens(self::getActivePlayerId())['tire']<1)) {

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

        return array('positions' => $positions, 'direction' => $direction, 'gear' => $currentGear, 'hasValid' => $hasValid, 'canGiveWay' => $canGiveWay);
    }

    function argEmergencyBrake() {

        $playerCar = self::getObjectFromDb("SELECT pos_x x, pos_y y, orientation dir FROM game_element WHERE id = ".$id = self::getActivePlayerId());

        $carOct = new VektoraceOctagon(new VektoracePoint($playerCar['x'],$playerCar['y']), $playerCar['dir']);

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

        return array('directionArrows' => $directions, 'direction' => $playerCar['dir']);
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
        $playerCurve = self::getObjectFromDb("SELECT pos_x x, pos_y y, orientation dir, player_curve_zone zoneNum FROM game_element JOIN player ON id = player_curve_number WHERE entity='curve' AND player_id = $id");
        $previousZone = $playerCurve['zoneNum'];
        $playerCurve = new VektoraceOctagon(new VektoracePoint($playerCurve['x'], $playerCurve['y']), $playerCurve['dir'], true);

        $pw = self::getPitwall();

        foreach ($topAnchor->getAdjacentOctagons(5) as $i => $carPos) {

            $carOct = new VektoraceOctagon($carPos, $dir);
            $directions = array();
            $dirNames = array('right', 'straight', 'left');

            self::dump("// CAR POS", $carOct);

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
                'tireCost' => ($i==0 || $i==4) && !(($i < 2 && $deniedSide['R']) || ($i > 2 && $deniedSide['L'])),
                // messy stuff to passively tell why a position is denied. there are few special case to be aware of:
                //  position is both denied by shunk AND is a tireCost position -> position is displayed simply as DENIED (turn off tireCost, you'll see why this is necessary in client)
                //  position is tireCost AND player is restricted from selecting tireCost positions -> position is set also to denied and displayed as DENIED (but being both tireCost and denied true, client can guess why without additional info)
                //  position is only denied by shunk -> position is set and displayed as DENIED
                //  position is only tireCost -> position is set and displayed as TIRECOST
                //  position is both tireCost, NoBlackMov AND denied by shunk -> position is simply displayed as denied by shunk (no need to display additional info)
                'legal' => !self::detectCollision($carOct),
                'denied' => ($i < 2 && $deniedSide['R']) || ($i > 2 && $deniedSide['L']) || (($i==0 || $i==4) && self::getUniqueValueFromDb("SELECT NoBlackMov FROM penalities_and_modifiers WHERE player = $id")),
                'byFinishLine' => $carOct->inPitZone($pw, 'SoC') || $carOct->inPitZone($pw, 'grid'),
                'byBox' => $carOct->inPitZone($pw, 'box') || $carOct->inPitZone($pw, 'exit'),
                'offTrack' =>  $playerCurve->curveProgress($carOct) - $previousZone > 3,
                'curveProgress'=> $playerCurve->curveProgress($carOct) // used by server only
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

            // enter only if:
            if ($pos['legal'] && // pos is legal
                !$pos['denied'] && // pos is not denied
                !$pos['offTrack'] && // pos is not off track
                // if pos is costs a tire, the player has at least one tire token and it's not prevented from using it
                !($pos['tireCost'] && (self::getPlayerTokens($id)['tire']<1 || self::getUniqueValueFromDb("SELECT NoBlackMov FROM penalities_and_modifiers WHERE player = $id"))) &&
                !(self::isPlayerAfterLastCurve($id) && (
                    ($pos['byFinishLine'] && self::getUniqueValueFromDb("SELECT BoxBox FROM penalities_and_modifiers WHERE player = $id")) ||
                    ($pos['byBox'] && ($isBoost || self::getPlayerCurve($id)['num'] == 1 || self::getPlayerLap($id) == self::getGameStateValue('number_of_laps')-1))
                ))
            )
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
    function argAttackManeuvers() {

        $sql = "SELECT id, pos_x x, pos_y y, orientation dir
                FROM game_element
                WHERE entity = 'car'";
        $cars = self::getObjectListFromDb($sql);

        $playerId = self::getActivePlayerId();
        $playerCar = self::getPlayerCarOctagon($playerId);

        $attEnemies = array();
        $canAttack = false;
        $movsAvail = false;

        // PLAYER CAN ATTACK CHECK
        $penalities = self::getObjectFromDb("SELECT NoDrafting, NoAttackMov, BoxBox FROM penalities_and_modifiers WHERE player = $playerId");
        if (self::getPlayerTurnPos($playerId) != 1 || !$penalities['NoAttackMov'] || !$penalities['BoxBox'] || !($playerCar->inPitZone(self::getPitwall(),'box') && !$playerCar->inPitZone(self::getPitwall(),'exit','whole'))) {
            $canAttack = true;
            
            foreach ($cars as $i => $car) {
                
                $enemyId = $car['id'];
                $enemyCar = new VektoraceOctagon(new VektoracePoint($car['x'], $car['y']), $car['dir']);

                $pw = self::getPitwall();

                // ENEMY CAN BE ATTACKED CHECK
                if ($enemyId != $playerId && // enemy is not player
                    !self::getUniqueValueFromDb("SELECT BoxBox FROM penalities_and_modifiers WHERE player = $enemyId") && // enemy is not shielded by boxbox
                    !($enemyCar->inPitZone(self::getPitwall(),'box') && !$enemyCar->inPitZone(self::getPitwall(),'exit','whole')) && // enemy is not in pitbox
                    $enemyCar->overtake($playerCar) && // enemy is in front of player
                    $playerCar->getDirection() == $enemyCar->getDirection() && // enemy has same direction of player
                    VektoracePoint::distance($playerCar->getCenter(),$enemyCar->getCenter()) <= 3*VektoraceOctagon::getOctProperties()['size'] // enemy is within an acceptable range to be able to be attacked
                    ) {

                    // init maneuvers arr
                    $maneuvers = array();
        
                    // get player and enemy gears (for maneuver validity check)
                    $currGear = self::getCollectionFromDb("SELECT player_id id, player_current_gear gear FROM player WHERE player_id = $playerId OR player_id = $enemyId", true);

                    // create drafting manevuers detectors
                    $range2detectorVec = new VektoraceVector($enemyCar->getAdjacentOctagons(1,true), $enemyCar->getDirection(), 2, 'top');
                    $range1detectorOct = new VektoraceOctagon($enemyCar->getAdjacentOctagons(1,true), $enemyCar->getDirection());

                    $range1Collision = false;
                    $range2Collision = false;
                    
                    // DRAFTING MANEUVERS CONDITION CHECKS
                    if (!$penalities['NoDrafting'] &&
                        $currGear[$playerId] >= 3 &&
                        $currGear[$enemyId] >= 3
                        ) {

                        if ($playerCar->collidesWithVector($range2detectorVec, 'car', 0)) {
                            $range2Collision = true;

                            if ($playerCar->collidesWith($range1detectorOct, 'car', 0))
                                $range1Collision= true;
                        }
                    }

                    // CALC SLINGSHOT POSITIONS
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
                        'legal'=> !self::detectCollision($range2detectorOct, false, array($playerId))
                    );
                    $maneuvers['push'] = array(
                        'name' => clienttranslate('Push'),
                        'attPos' => $range1detectorOct->getCenter()->coordinates(),
                        'active' => $range1Collision,
                        'legal'=> !self::detectCollision($range1detectorOct, false, array($playerId))
                    );
                    $maneuvers['slingshot'] = array(
                        'name' => clienttranslate('Slingshot'),
                        'attPos' => $slingshotPos,
                        'active' => $range1Collision,
                        'legal' => $hasValid && !self::detectCollision($range1detectorOct, false, array($playerId))
                    );

                    // create shunking manevuers detectors
                    $sidesCenters = $enemyCar->getAdjacentOctagons(3,true);
                    $leftsideDetectorOct = new VektoraceOctagon($sidesCenters[0], $enemyCar->getDirection());
                    $rightsideDetectorOct = new VektoraceOctagon($sidesCenters[2], $enemyCar->getDirection());
                    $leftCollision = false;
                    $rightCollision = false;

                    // SHUNKING MANEUVERS CONDITION CHECK
                    if ($currGear[$playerId] >= 2 && $currGear[$enemyId] >= 2) {
                        if ($playerCar->collidesWith($leftsideDetectorOct, 'nose', 0)) $leftCollision = $hasValidMovs = true;
                        if ($playerCar->collidesWith($rightsideDetectorOct, 'nose', 0)) $rightCollision = $hasValidMovs = true;
                    }

                    // ADD SHUNKING MANEUVER DATA TO ENEMY MANEUVERS ARRAY
                    $maneuvers['leftShunk'] = array(
                        'name' => clienttranslate('Left Shunk'),
                        'attPos' => $leftsideDetectorOct->getCenter()->coordinates(),
                        'active' => $leftCollision,
                        'legal'=> !self::detectCollision($leftsideDetectorOct, false, array($playerId))
                    );
                    $maneuvers['rightShunk'] = array(
                        'name' => clienttranslate('Right Shunk'),
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
                    "width" => VektoraceOctagon::getOctProperties()["size"],
                    "height" => VektoraceOctagon::getOctProperties()["side"]
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

        // if player in box, he might only choose the 2nd gear
        if (self::getPlayerCarOctagon(self::getActivePlayerId())->inPitZone(self::getPitwall(),'box')) return array('gears' => array('unavail','curr','unavail','unavail','unavail'));

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

    function stBoostVectorPlacement() {
        if (!self::argBoostVectorPlacement()['hasValid']) {

            self::notifyAllPlayers('noBoostAvail', clienttranslate('${player_name} could not place any boost vector'), array(
                'player_name' => self::getActivePlayerName(),
                'player_id' => self::getActivePlayerId(),
            ));

            $this->gamestate->nextState();
        }
    }

    function stEmergencySlowdownOrBrake() {

        $id = self::getActivePlayerId();

        $shiftedGear = self::getPlayerCurrentGear($id) -1;
        $tireExpense = 1;
        $insuffTokens = false;

        while ($shiftedGear > 1) {
            $gearPlacement = self::argGearVectorPlacement($shiftedGear);
            if ($gearPlacement['hasValid']) {

                $carPlacement = self::argGearVectorPlacement($shiftedGear);

                // CHECK FOR AVAILABLE TOKENS AND UPDATE AMOUNT
                $tireTokens = self::getPlayerTokens($id)['tire'] - $tireExpense;

                // if tokens insufficent break loop, car will simply stop. mem bool val to notify player reason
                if ($tireTokens < 0) {
                    $insuffTokens = true;
                    break;
                }

                self::DbQuery("UPDATE player SET player_tire_tokens = $tireTokens WHERE player_id = $id");

                // UPDATE NEW GEAR
                $sql = "UPDATE player
                        SET player_current_gear = $shiftedGear
                        WHERE player_id = $id";
                self::DbQuery($sql);

                // NOTIFY PLAYERS
                self::notifyAllPlayers('useNewVector', clienttranslate('${player_name} slowed down to the ${shiftedGear}th gear, spending ${tireExpense} TireTokens'), array(
                    'player_name' => self::getActivePlayerName(),
                    'player_id' => $id,
                    'shiftedGear' => $shiftedGear,
                    'tireExpense' => $tireExpense
                ));

                // JUMP BACK TO VECTOR PLACEMENT PHASE
                $this->gamestate->nextState('slowdown');
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

        $this->gamestate->nextState('brake');
        return;

        // a rotation is still allowed, so state does not jump (args contain rotation arrows data)
    }

    function stGiveWay() {
        $id = self::getActivePlayerId();
        $playerTurnPos = self::getPlayerTurnPos($id);
        $enemyTurnPos = $playerTurnPos + 1;
        $enemy = self::getPlayerTurnPosNumber($enemyTurnPos);

        self::DbQuery("UPDATE player SET player_turn_position = $enemyTurnPos WHERE player_id = $id");
        self::DbQuery("UPDATE player SET player_turn_position = $playerTurnPos WHERE player_id = $enemy");

        $this->gamestate->changeActivePlayer($enemy);
        $this->gamestate->nextState();
    }

    function stAttackManeuvers() {

        $args = self::argAttackManeuvers();

        if (count($args['attEnemies']) == 0) {
            $this->gamestate->nextState('noManeuver');
            return;
        }

        if (!$args['canAttack']) {
            if (self::getPlayerTurnPos(self::getActivePlayerId()) != 1)
                self::notifyAllPlayers('noAttMov', clienttranslate('${player_name} is restricted from performing any attack move this turn.'), (array(
                    'player_name' => self::getActivePlayerName(),
                    'player_id' => self::getActivePlayerId(),
                    'enemies' => 0
                )));
            $this->gamestate->nextState('noManeuver');
        } else if (!$args['attMovsAvail']) {
            if (self::getPlayerTurnPos(self::getActivePlayerId()) != 1)
                self::notifyAllPlayers('noAttMov', clienttranslate('${player_name} could not perform any valid attack move this turn.'), array(
                    'player_name' => self::getActivePlayerName(),
                    'player_id' => self::getActivePlayerId(),
                    'enemies' => count($args['attEnemies'])
                ));

            $this->gamestate->nextState('noManeuver');
        }
    }

    function stPitStop() {

        $id = self::getActivePlayerId();
        $currGear = self::getPlayerCurrentGear($id);
        $speedSurplus = $currGear - 2;

        if ($currGear > 2) {
            self::notifyAllPlayers('boxSpeedPenality', clienttranslate('${player_name} exceeded pit box entrance speed limit by ${speedSurplus} (-${penality} refilled tokens)'), array(
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

        $lastCurve = self::getGameStateValue('last_curve');

        $playerCurveNumber = intval(self::getUniqueValueFromDb("SELECT player_curve_number FROM player WHERE player_id = $id"));
        $nextCurveNumber = max(($playerCurveNumber + 1) % ($lastCurve+1), 1); // not exactly a clock but it should work

        $currAndNextCurves = self::getCollectionFromDb("SELECT id, pos_x x, pos_y y, orientation dir FROM game_element WHERE entity = 'curve' AND (id = $playerCurveNumber OR id = $nextCurveNumber)");

        $playerCurve = $currAndNextCurves[$playerCurveNumber];
        $playerCurveOct = new VektoraceOctagon(new VektoracePoint($playerCurve['x'], $playerCurve['y']), $playerCurve['dir'], true);

        $nextCurve = $currAndNextCurves[$nextCurveNumber];
        $nextCurveOct = new VektoraceOctagon(new VektoracePoint($nextCurve['x'], $nextCurve['y']), $nextCurve['dir'], true);

        $curveZone = intval(self::getUniqueValueFromDb("SELECT player_curve_zone FROM player WHERE player_id = $id"));

        // CHECK IF CURRENT CURVE IS NOT LAST
        if ($playerCurveNumber < $lastCurve) {
            // if so, check if player reached reached and passed next curve

            // curve passed check (COULD BE BETTER?)
            // if car has left zone 4 of a curve, then it is considered to have passed and assigned the next curve as current one, indipendently of distance to that curve
            // if car is closer to next curve (but still hasn't passed 4th zone), assign car to next curve anyway (this is for when curves don't from a convex track shape)
            if ($curveZone > 4 || 
                VektoracePoint::distance($playerCar->getCenter(), $nextCurveOct->getCenter()) < VektoracePoint::distance($playerCar->getCenter(), $playerCurveOct->getCenter())) {

                // set new curve db
                self::DbQuery("UPDATE player SET player_curve_number = $nextCurveNumber WHERE player_id = $id");
                $playerCurveNumber = $nextCurveNumber;

                // calc new curve zone
                $curveZone = $nextCurveOct->curveProgress($playerCar);
                if ($curveZone > 3) $curveZone = 0; // if curve zone is higher than 3 (likely 7, meaning behind curve, in rare situation where curves are far from each other and pointing in different directions)

                // set new curve zone
                self::DbQuery("UPDATE player SET player_curve_zone = $curveZone WHERE player_id = $id");
            }
        } else if ($curveZone > 4) {

            // -- check finish line crossing
            // dot prod of vector pointing from pitwall top to its direction and vector pointing to nose of player car
            $pitwall = self::getObjectFromDb("SELECT pos_x x, pos_y y, orientation dir FROM game_element WHERE entity='pitwall'");
            $pitwallObj = new VektoraceVector(new VektoracePoint($pitwall['x'], $pitwall['y']), $pitwall['dir'], 4);
            $pwCenter = $pitwallObj->getCenter();
            $pwTopCenter = $pitwallObj->getTopOct()->getCenter();
            $pwTopCenter->translateVec(VektoraceOctagon::getOctProperties()['side']/2, $pitwall['dir']*M_PI_4); // translate to match finish line
            $pwTopCenter->changeRefPlane($pwCenter);
            $pwTopCenter->scale(0.75,0.75); // REMBER PITWALL SI F*ING SMALLER THAN OTHER VECTORS
            $pwTopCenter->translate($pwCenter->x(),$pwCenter->y());

            $dirVec = clone $pwTopCenter;
            $dirVec->translateVec(1,$pitwall['dir'] * M_PI_4);
            $dirVec = VektoracePoint::displacementVector($pwTopCenter, $dirVec);

            ['origin' => $carNose, 'norm' => $carNorm] = $playerCar->getDirectionNorm();
            $pcVec = VektoracePoint::displacementVector($pwTopCenter, $carNose);

            // car crosses line if is parallel to piwall AND if it's nose crosses the line
            // NO EASY WAY TO DETECT IF CAR IS PARALLEL TO PITWALL WITHOUT PREVENTING CAR TO ROTATE AFTER CROSSING FINISHLINE
            if (/* $playerCar->getDirection() == $pitwallObj->getDirection() &&  */VektoracePoint::dot($dirVec,$pcVec) > 0) {
                self::DbQuery("UPDATE player SET player_lap_number = player_lap_number+1 WHERE player_id = $id");
                
                // update playter lap number
                $playerLapNum = self::getUniqueValueFromDb("SELECT player_lap_number FROM player WHERE player_id = $id");
                // check if player lap number is same provided by game options 
                if ($playerLapNum == self::getGameStateValue("number_of_laps")) {
                    // if so race should end for this player
                    $score = self::getPlayersNumber() - self::getPlayerTurnPos($id);
                    self::DbQuery("UPDATE player SET player_score = $score WHERE player_id = $id");
                    self::DbQuery("UPDATE penalities_and_modifiers SET FinishedRace = 1 WHERE player = $id");
                    self::DbQuery("DELETE FROM game_element WHERE id = $id");
                    self::notifyAllPlayers('finishedRace',clienttranslate('${player_name} crossed the finish line in ${pos} position'), array(
                        'player_name' => self::getActivePlayerName(),
                        'player_id' => $id,
                        'pos' => self::getPlayerTurnPos($id),
                        'lapNum' => $playerLapNum
                    ));

                    if (self::getPlayerTurnPos($id) == self::getPlayersNumber()-1) $this->gamestate->nextState('raceEnd');
                    else $this->gamestate->nextState('skipGearDeclaration');

                    return;
                        
                } else {

                    // send notif about player completing a lap
                    self::notifyAllPlayers('lapFinish',clienttranslate('${player_name} completed their ${n} lap'), array(
                        'player_name' => self::getActivePlayerName(),
                        'player_id' => $id,
                        'n' => $playerLapNum
                    ));

                    self::DbQuery("UPDATE penalities_and_modifiers SET BoxBox = NULL WHERE player = $id");

                    // set new curve db
                    self::DbQuery("UPDATE player SET player_curve_number = $nextCurveNumber WHERE player_id = $id"); // next curve number should be 1

                    // calc new curve zone
                    $curveZone = $nextCurveOct->curveProgress($playerCar);
                    if ($curveZone > 3) $curveZone = 0;

                    // set new curve zone
                    self::DbQuery("UPDATE player SET player_curve_zone = $curveZone WHERE player_id = $id");
                }
            } else { // else, if finish line has not been crossed

                // and if player hasn't decided on calling boxbox
                if (is_null(self::getUniqueValueFromDb("SELECT BoxBox FROM penalities_and_modifiers WHERE player = $id")) && self::getPlayerLap($id) < self::getGameStateValue('number_of_laps')-1) {

                    // go to boxbox promt state
                    $this->gamestate->nextState('boxBox');
                    return;
                }
            }
        }

        $this->gamestate->nextState('gearDeclaration');
    }

    // gives turn to next player for car movement or recalculates turn order if all player have moved their car
    function stNextPlayer() {
        $player_id = self::getActivePlayerId();

        self::DbQuery(
            "UPDATE penalities_and_modifiers 
            SET NoBlackMov = 0,
                NoShiftDown = 0,
                NoShiftUp = 0,
                NoAttackMov = 0,
                NoDrafting = 0,
                DeniedSideLeft = 0,
                DeniedSideRight = 0
            WHERE player = $player_id");

        $np_id = self::getPlayerAfterCustom($player_id);
        
        if (self::getPlayerTurnPos($np_id) == 1) {

            $order = self::newTurnOrder();
            
            $optString = '';
            if ($order['isChanged']) $optString = ' The turn order has changed.';

            self::notifyAllPlayers('nextRoundTurnOrder', clienttranslate('A new game round begins.'.$optString), array(
                'order' => $order['list'],
                'missingPlayers' => self::getPlayersNumber() - count($order['list'])
            ));

            self::incGameStateValue('turn_number', 1);

            $np_id = self::getPlayerTurnPosNumber(1);

        }

        while (self::isPlayerRaceFinished($np_id)) {
            $np_id = self::getPlayerAfterCustom($np_id);
        }

        /* $this->giveExtraTime($next_player_id);
        $this->incStat(1, 'turns_number', $next_player_id);
        $this->incStat(1, 'turns_number'); */
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
