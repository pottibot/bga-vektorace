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

        $this->tpl['PREF_ILLPOS_TITLE'] = self::_("Display illegal positions");
        $this->tpl['PREF_ILLPOS_LABLE_N'] = self::_("None");
        $this->tpl['PREF_ILLPOS_LABLE_DISP'] = self::_("Blocked position");

        $this->tpl['PREF_AUTOGEARWIN_TITLE'] = self::_("Automatically open gear selection window");
        $this->tpl['PREF_AUTOGEARWIN_LABLE_Y'] = self::_("Yes");
        $this->tpl['PREF_AUTOGEARWIN_LABLE_N'] = self::_("No");
  	}
  }
  

