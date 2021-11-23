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
        $this->game->chooseTokensAmount( $tire, $nitro );
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

    public function breakCar() {
        self::setAjaxMode();
        $this->game->breakCar();
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
        $action = self::getArg( "action", AT_alphanum_dash, true);
        $this->game->placeCar($pos, $dir);
        self::ajaxResponse();
    }
}
