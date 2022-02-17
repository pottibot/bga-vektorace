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
                    1 => array('name' => 'Indianottolis - Oval', 'tmdisplay' => 'Oval', 'description' => totranslate('4 curves arranged in a rectangular shape, forming two long horizontal straightways and four 90deg turns')),
                    2 => array('name' => 'Indianottolis - Tri-Oval 1', 'tmdisplay' => 'Tri-Oval 1', 'description' => totranslate('3 curves arranged in a rectangular triangle, with the first turn at 90deg and the last one at a very tight angle. Two traightways, one along the diagonal of the triangle and one at the base.')),
                    3 => array('name' => 'Indianottolis - Tri-Oval 2', 'tmdisplay' => 'Tri-Oval 2', 'description' => totranslate('3 curves arranged in a rectangular triangle, with the first turn at a very tight angle and the last one at 90deg. Two traightways, one along the diagonal of the triangle and one at the base.')),
                    4 => array('name' => 'Indianottolis - Cross-Oval', 'tmdisplay' => 'Cross-Oval', 'description' => totranslate('3 curves arranged in a flat triangle with two equals short sides, forming one long straightway, two tight turns (at beginning and end of circuit) and a loose one (in the middle).')),
                ),
        'default' => 1
    ),

    /* 102 => array(
        'name' => totranslate('Map boundaries'),    
        'values' => array(
                    1 => array('name' => totranslate('None'), 'tmdisplay' => totranslate('no-bound')),
                    2 => array('name' => totranslate('Center of mass'), 'tmdisplay' => totranslate('bounds')),
                    3 => array('name' => totranslate('Strict'), 'tmdisplay' => totranslate('strict-bounds')),
                    4 => array('name' => totranslate('Far'), 'tmdisplay' => totranslate('far-bounds')),
                ),
        'default' => 2
    ), */
);


$game_preferences = array(
    100 => array(
        'name' => totranslate('Automatically open gear selection window'),
        'needReload' => false,
        'values' => array(
            1 => array( 'name' => totranslate('Yes')),
            2 => array( 'name' => totranslate('No'))
        ),
        'default' => 2
    ),

    101 => array(
        'name' => totranslate('Move confirmation'),
        'needReload' => false,
        'values' => array(
            1 => array( 'name' => totranslate('Yes')),
            2 => array( 'name' => totranslate('No'))
        ),
        'default' => 2
    ),

    102 => array(
        'name' => totranslate('Display track guides'),
        'needReload' => false,
        'values' => array(
            1 => array( 'name' => totranslate('Yes')),
            2 => array( 'name' => totranslate('No'))
        ),
        'default' => 2
    ),

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
