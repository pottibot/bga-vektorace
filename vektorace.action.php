<?php
/*
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * VektoRace implementation : © <Pietro Luigi Porcedda> <pietro.l.porcedda@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on https://boardgamearena.com.
 * See http://en.doc.boardgamearena.com/Studio for more information.
 * -----
 */
  
class action_vektorace extends APP_GameAction { 
    // Constructor: please do not modify
 	public function __default() {
	    if (self::isArg('notifwindow')) {
            $this->view = "common_notifwindow";
	        $this->viewArgs['table'] = self::getArg( "table", AT_posint, true );
	    } else {
            $this->view = "vektorace_vektorace";
            self::trace( "Complete reinitialization of board game" );
        }
	} 

    // debugging func
    public function loadBugSQL() {
        self::setAjaxMode();
        $reportId = (int) self::getArg('report_id', AT_int, true);
        $this->game->loadBugSQL($reportId);
        self::ajaxResponse();
    }
    // debug 
    /* public function testCollision() {
        self::setAjaxMode();     
        $x = self::getArg( "x", AT_int, true );
        $y = self::getArg( "y", AT_int, true );
        $this->game->testCollision( $x, $y );
        self::ajaxResponse();
    } */
	
	// ALL ACTION HANDLERS BELOW

    public function placeFirstCar() {
        self::setAjaxMode();     
        $x = self::getArg( "x", AT_int, true );
        $y = self::getArg( "y", AT_int, true );
        $this->game->placeFirstCar( $x, $y );
        self::ajaxResponse();
    }

    public function placeCarFS() {
        self::setAjaxMode();     
        $ref = self::getArg( "ref", AT_alphanum, true);
        $pos = self::getArg( "pos", AT_int, true);
        $this->game->placeCarFS($ref, $pos);
        self::ajaxResponse();
    }

    public function chooseTokensAmount() {
        self::setAjaxMode();     
        $tire = self::getArg( "tire", AT_int, true );
        $nitro = self::getArg( "nitro", AT_int, true );
        $pitStop = self::getArg( "pitStop", AT_bool, false, false);
        $this->game->chooseTokensAmount( $tire, $nitro, $pitStop);
        self::ajaxResponse();
    }

    public function chooseStartingGear() {
        self::setAjaxMode();     
        $gearN = self::getArg( "gearN", AT_int, true );
        $this->game->chooseStartingGear($gearN);
        self::ajaxResponse();
    }

    public function declareGear() {
        self::setAjaxMode();     
        $gearN = self::getArg( "gearN", AT_int, true );
        $this->game->declareGear($gearN);
        self::ajaxResponse();
    }
    
    public function placeGearVector() {
        self::setAjaxMode();     
        $pos = self::getArg( "pos", AT_alphanum_dash, true);
        $this->game->placeGearVector($pos);
        self::ajaxResponse();
    }

    public function brakeCar() {
        self::setAjaxMode();
        $this->game->brakeCar();
        self::ajaxResponse();
    }

    public function giveWay() {
        self::setAjaxMode();
        $this->game->giveWay();
        self::ajaxResponse();
    }

    public function rotateAfterBrake() {
        self::setAjaxMode();
        $dir = self::getArg( "dir", AT_int, true);
        $this->game->rotateAfterBrake($dir);
        self::ajaxResponse();
    }

    public function useBoost() {
        self::setAjaxMode();     
        $use = self::getArg( "use", AT_bool, true);
        $this->game->useBoost($use);
        self::ajaxResponse();
    }

    public function placeBoostVector() {
        self::setAjaxMode();     
        $n = self::getArg( "n", AT_int, false );
        $this->game->placeBoostVector($n);
        self::ajaxResponse();
    }

    public function placeCar() {
        self::setAjaxMode();     
        $pos = self::getArg( "pos", AT_alphanum_dash, true);
        $dir = self::getArg( "dir", AT_alphanum_dash, true);
        $this->game->placeCar($pos, $dir);
        self::ajaxResponse();
    }

    public function engageManeuver() {
        self::setAjaxMode();     
        $action = self::getArg( "maneuver", AT_alphanum_dash, true);
        $enemy = self::getArg( "enemy", AT_int, true);
        $posIndex = self::getArg( "posIndex", AT_int, false, 0);
        $this->game->engageManeuver($enemy, $action, $posIndex);
        self::ajaxResponse();
    }

    public function skipAttack() {
        self::setAjaxMode();
        $this->game->skipAttack();
        self::ajaxResponse();
    }

    public function boxBox() {
        self::setAjaxMode();     
        $skip = self::getArg( "skip", AT_bool, false, false);
        $this->game->boxBox($skip);
        self::ajaxResponse();
    }
}
