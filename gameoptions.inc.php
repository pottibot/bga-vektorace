<?php

/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * VektoRace implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * gameoptions.inc.php
 *
 * VektoRace game options description
 * 
 * In this file, you can define your game options (= game variants).
 *   
 * Note: If your game has no variant, you don't have to modify this file.
 *
 * Note²: All options defined in this file should have a corresponding "game state labels"
 *        with the same ID (see "initGameStateLabels" in vektorace.game.php)
 *
 * !! It is not a good idea to modify this file when a game is running !!
 *
 */

$game_options = array(

    100 => array(
        'name' => totranslate('Number of laps'),    
        'values' => array(
                    1 => array('name' => totranslate('1'), 'tmdisplay' => totranslate('1 lap'), 'firstgameonly' => true),
                    2 => array('name' => totranslate('2'), 'tmdisplay' => totranslate('2 laps')),
                    3 => array('name' => totranslate('3'), 'tmdisplay' => totranslate('3 laps')),
                    4 => array('name' => totranslate('4'), 'tmdisplay' => totranslate('4 laps')),
                    5 => array('name' => totranslate('5'), 'tmdisplay' => totranslate('5 laps')),
                ),
        'default' => 3
    ),

    101 => array(
        'name' => totranslate('Circuit layout'),    
        'values' => array(
                    1 => array('name' => totranslate('Indianottolis - Oval'), 'tmdisplay' => totranslate('Oval'), 'description' => totranslate('4 curves arranged in a rectangular shape, forming two long horizontal straightways and four 90deg turns')),
                    2 => array('name' => totranslate('Indianottolis - Tri-Oval 1'), 'tmdisplay' => totranslate('Tri-Oval 1'), 'description' => totranslate('3 curves arranged in a rectangular triangle, with the first turn at 90deg and the last one at a very tight angle. Two traightways, one along the diagonal of the triangle and one at the base.')),
                    3 => array('name' => totranslate('Indianottolis - Tri-Oval 2'), 'tmdisplay' => totranslate('Tri-Oval 2'), 'description' => totranslate('3 curves arranged in a rectangular triangle, with the first turn at a very tight angle and the last one at 90deg. Two traightways, one along the diagonal of the triangle and one at the base.')),
                    4 => array('name' => totranslate('Indianottolis - Cross-Oval'), 'tmdisplay' => totranslate('Cross-Oval'), 'description' => totranslate('3 curves arranged in a flat triangle with two equals short sides, forming one long straightway, two tight turns (at beginning and end of circuit) and a loose one (in the middle).')),
                ),
        'default' => 1
    ),

    /*
    
    // note: game variant ID should start at 100 (ie: 100, 101, 102, ...). The maximum is 199.
    100 => array(
                'name' => totranslate('my game option'),    
                'values' => array(

                            // A simple value for this option:
                            1 => array( 'name' => totranslate('option 1') )

                            // A simple value for this option.
                            // If this value is chosen, the value of "tmdisplay" is displayed in the game lobby
                            2 => array( 'name' => totranslate('option 2'), 'tmdisplay' => totranslate('option 2') ),

                            // Another value, with other options:
                            //  description => this text will be displayed underneath the option when this value is selected to explain what it does
                            //  beta=true => this option is in beta version right now (there will be a warning)
                            //  alpha=true => this option is in alpha version right now (there will be a warning, and starting the game will be allowed only in training mode except for the developer)
                            //  nobeginner=true  =>  this option is not recommended for beginners
                            //  firstgameonly=true  =>  this option is recommended only for the first game (discovery option)
                            3 => array( 'name' => totranslate('option 3'), 'description' => totranslate('this option does X'), 'beta' => true, 'nobeginner' => true )
                        ),
                'default' => 1
            ),

    */

);


