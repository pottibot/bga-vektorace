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

    public function selectPosition() {
        self::setAjaxMode();     
        $posX = self::getArg( "x", AT_int, false );
        $posY = self::getArg( "y", AT_int, false );
        $this->game->selectPosition( $posX, $posY );
        self::ajaxResponse();
    }

    public function chooseStartingGear() {
        self::setAjaxMode();     
        $gearN = self::getArg( "gearN", AT_int, false );
        $this->game->chooseStartingGear($gearN);
        self::ajaxResponse();
    }

    public function declareGear() {
        self::setAjaxMode();     
        $gearN = self::getArg( "gearN", AT_int, false );
        $this->game->declareGear($gearN);
        self::ajaxResponse();
    }
    
    public function placeGearVector() {
        self::setAjaxMode();     
        $pos = self::getArg( "pos", AT_alphanum_dash, true);
        $this->game->placeGearVector($pos);
        self::ajaxResponse();
    }

    public function useBoost() {
        self::setAjaxMode();     
        $use = self::getArg( "use", AT_bool, true);
        $this->game->useBoost($use);
        self::ajaxResponse();
    }


    public function chooseBoost() {
        self::setAjaxMode();     
        $n = self::getArg( "n", AT_int, false );
        $this->game->chooseBoost($n);
        self::ajaxResponse();
    }

    public function placeCar() {
        self::setAjaxMode();     
        $pos = self::getArg( "pos", AT_alphanum_dash, true);
        $dir = self::getArg( "dir", AT_alphanum_dash, true);
        $this->game->placeCar($pos, $dir);
        self::ajaxResponse();
    }
}
