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

$stats_type = array(

    // Statistics global to table
    "table" => array(

        "turns_number" => array("id"=> 10,
                    "name" => totranslate("Number of turns"),
                    "type" => "int" ),
    ),
    
    // Statistics existing for each player
    "player" => array(

        "turns_number" => array("id"=> 10,
                    "name" => totranslate("Number of turns"),
                    "type" => "int" ),

        "pole_turns" => array("id"=> 11,
                    "name" => totranslate("Turns in pole position"),
                    "type" => "int" ),

        "surpasses_number" => array("id"=> 12,
                    "name" => totranslate("Surpasses"),
                    "type" => "float" ),

        "pitstop_number" => array("id"=> 13,
                    "name" => totranslate("Number of Pit-Stops made"),
                    "type" => "int" ),

        "brake_number" => array("id"=> 14,
                    "name" => totranslate("Emergency brakes performed"),
                    "type" => "int" ),

        "tire_used" => array("id"=> 15,
                    "name" => totranslate("Tire tokens used"),
                    "type" => "int" ),

        "nitro_used" => array("id"=> 16,
                    "name" => totranslate("Nitro tokens used"),
                    "type" => "int" ),
        
        "attMov_performed" => array("id"=> 17,
                    "name" => totranslate("Attack moves performed"),
                    "type" => "int" ),

        "attMov_suffered" => array("id"=> 18,
                    "name" => totranslate("Attack moves suffered"),
                    "type" => "int" ),

        "average_gear" => array("id"=> 19,
                    "name" => totranslate("Average gear used"),
                    "type" => "float" ),

        "boost_number" => array("id"=> 20,
                    "name" => totranslate("Boost used"),
                    "type" => "int" ),
        
        /* "curve_quality" => array("id"=> 21,
                    "name" => totranslate("Average turns per curve"),
                    "type" => "float" ), */
    )

);
