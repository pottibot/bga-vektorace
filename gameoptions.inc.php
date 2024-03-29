<?php

/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Vektorace implementation : © <Pietro Luigi Porcedda> <pietro.l.porcedda@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
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

    105 => array(
        'name' => totranslate('Map'),    
        'values' => array(
                    1 => array('name' => totranslate('Default'), 'tmdisplay' => totranslate('default'), 'description' => totranslate('Default map with randomized tracks')),
                    2 => array('name' => 'Indianottolis', 'tmdisplay' => totranslate('indianottolis'), 'description' => totranslate('Tournament map with predefined tracks')),
                ),
        'default' => 1
    ),

    /* 102 => array(
        'name' => totranslate('Insert track generator seed'),
        'values' => array(
                    1 => array('name' => totranslate('No'), 'tmdisplay' => totranslate('Random')),
                    2 => array('name' => totranslate('Yes'), 'tmdisplay' => totranslate('From seed'))
                ),
        'default' => 1
    ),

    103 => array(
        'name' => totranslate('Seed digit 1'),    
        'values' => array(
                    1 => array('name' => '1', 'tmdisplay' => '1'),
                    1 => array('name' => '1', 'tmdisplay' => '1'),
                    1 => array('name' => '1', 'tmdisplay' => '1'),
                    1 => array('name' => '1', 'tmdisplay' => '1'),
                    1 => array('name' => '1', 'tmdisplay' => '1'),
                    1 => array('name' => '1', 'tmdisplay' => '1'),
                    1 => array('name' => '1', 'tmdisplay' => '1'),
                    1 => array('name' => '1', 'tmdisplay' => '1'),
                    1 => array('name' => '1', 'tmdisplay' => '1'),
                    1 => array('name' => '1', 'tmdisplay' => '1'),
                ),
        'default' => 1
    ), */

    101 => array(
        'name' => totranslate('Track layout'),    
        'values' => array(
                    1 => array('name' => 'Oval', 'tmdisplay' => 'Oval', 'description' => totranslate('4 curves arranged in a rectangular shape')),
                    2 => array('name' => 'Inside 1', 'tmdisplay' => 'Inside 1', 'description' => totranslate('4 curves arranged in a right trapezoid with the diagonal on the right')),
                    3 => array('name' => 'Inside 2', 'tmdisplay' => 'Inside 2', 'description' => totranslate('4 curves arranged in a right trapezoid with the diagonal on the left')),
                    4 => array('name' => 'Tri', 'tmdisplay' => 'Tri', 'description' => totranslate('3 curves arranged in a flat triangle with two equals short sides and one long base')),
                ),
        'default' => 1,
        'displaycondition' => array( 
            // Note: do not display this option unless these conditions are met
            array(
                'type' => 'otheroption',
                'id' => 105, // Game specific option defined in the same array above
                'value' => array(2)
            ),
        ),
    ),

    110 => array(
        'name' => totranslate('Initial positining order'),    
        'values' => array(
                    1 => array('name' => totranslate('Random'), 'tmdisplay' => totranslate('Random start')),
                    2 => array('name' => totranslate('Custom'), 'tmdisplay' => totranslate('Custom start'), 'description' => totranslate('Table creator will decide the initial positioning order at the start of the game (useful to implement tournament positioning rules or based on ELO)'))
                ),
        'default' => 1
    ),
);


$game_preferences = array(
    100 => array(
        'name' => totranslate('Automatically open gear selection window'),
        'needReload' => false,
        'values' => array(
            1 => array( 'name' => totranslate('Yes')),
            2 => array( 'name' => totranslate('No'))
        ),
        'default' => 1
    ),

    101 => array(
        'name' => totranslate('Move confirmation'),
        'needReload' => false,
        'values' => array(
            1 => array( 'name' => totranslate('Yes')),
            2 => array( 'name' => totranslate('No'))
        ),
        'default' => 1
    ),

        102 => array(
        'name' => totranslate('Display curves delimiters'),
        'needReload' => false,
        'values' => array(
            1 => array( 'name' => totranslate('Yes')),
            2 => array( 'name' => totranslate('No'))
        ),
        'default' => 2
    ),

/*     102 => array(
        'name' => totranslate('Display track boundaries'),
        'needReload' => false,
        'values' => array(
            1 => array( 'name' => totranslate('Yes')),
            2 => array( 'name' => totranslate('No'))
        ),
        'default' => 1
    ), */

    103 => array(
        'name' => totranslate('Display elements shadows'),
        'needReload' => false,
        'values' => array(
            1 => array( 'name' => totranslate('Yes')),
            2 => array( 'name' => totranslate('No'))
        ),
        'default' => 1
    ),

    104 => array(
        'name' => totranslate('Display illegal positions'),
        'needReload' => false,
        'values' => array(
            1 => array( 'name' => totranslate('Yes')),
            2 => array( 'name' => totranslate('No'))
        ),
        'default' => 1
    ),
);


/* 
name - mandatory. The name of the preference. Value will be automatically wrapped in totranslate if you don't.
needReload - If set to true, the game interface will auto-reload after a change of the preference.
values - mandatory. The array (map) of values with additional parameters per value.
    name - mandatory. String representation of the numeric value. Value will be automatically wrapped in totranslate if you don't.
    cssPref - CSS class to add to the <html> tag. Currently it is added or removed only after a reload (see needReload).
default - Indicates the default value to use for this preference (optional, if not present the first value listed is the default).
 */
