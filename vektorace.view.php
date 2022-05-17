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
  
  require_once( APP_BASE_PATH."view/common/game.view.php" );
  
  class view_vektorace_vektorace extends game_view
  {
    function getGameName() {
        return "vektorace";
    }    
  	function build_page( $viewArgs )
  	{		
  	    // Get players & players number
        $players = $this->game->loadPlayersBasicInfos();
        $players_nbr = count( $players );

        $this->tpl['GAME_DET_LAPS'] = self::_("Race laps");
        $this->tpl['GAME_DET_LAYOUT'] = self::_("Circuit shape");
  	}
  }
  

