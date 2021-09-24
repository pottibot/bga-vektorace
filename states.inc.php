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
 
$machinestates = array(

    // INITIAL STATE (DO NOT MODIFY)
    1 => array(
        "name" => "gameSetup",
        "description" => "",
        "type" => "manager",
        "action" => "stGameSetup",
        "transitions" => array( "" => 2 )
    ),

    // PLAYER POSITIONING (should be called SELECT START POSITION)
    // "flying-start" initial positioning phase.
    // each player place it's F8 racing car behind the starting line, using a special octagonal tile to separates the car in front from the ones behind (see rules)
    2 => array(
        "name" => "playerPositioning",
        "type" => "activeplayer",
        "description" => clienttranslate('${actplayer} must choose a starting position'),
        "descriptionmyturn" => clienttranslate('${you} must choose a starting position'),
        "possibleactions" => array( "selectPosition" ), // should be called 'selectStartingPosition', but it might just produce redundant code
        "args" => "argPlayerPositioning", // method that returns all possible positions for the positioning player (see game.php)
        "transitions" => array( "" => 3) // always jump to 'nextPositioning' state to check if all players have positioned their car
    ),

    // NEXT POSITIONING (maybe change it to NEXT START POSITION)
    // control state to check if all player have placed their car
    3 => array(
        "name" => "nextPositioning",
        "type" => "game",
        "description" => "",
        "action" => "stNextPositioning", // condition-checking method
        "transitions" => array( "nextPlayer" => 2, "greenLight" => 4) // if positioning phase completed, go to 'green light' phase, otherwise repeat 'playerPositioning' state
    ),

    // GREEN LIGHT
    // first player chooses the starting gear vector for the first turn of all the players 
    4 => array(
        "name" => "greenLight",
        "type" => "activeplayer",
        "description" => clienttranslate('${actplayer} must choose the starting gear vector for all players'),
        "descriptionmyturn" => clienttranslate('${you} must choose the starting gear vector for all players'),
        "possibleactions" => array( "chooseStartingGear" ),
        "transitions" => array( "" => 5) // finally, game can start. first player begins placing its vector and moving his car.
    ),

    // PLAYER MOEVEMENT
    // 
    5 => array(
        "name" => "playerMovement",
        "type" => "activeplayer",
        "description" => clienttranslate('${actplayer} must move their F8 using their current gear vector'),
        "descriptionmyturn" => clienttranslate('${you} must move your F8 using your current gear vector'),
        "args" => "argPlayerMovement",
        "possibleactions" => array("completeMovement"),
        "transitions" => array( "attackManeuvers" => 8)
    ),

    // ATTACK MANEUVERS
    // [after having moved the car for this turn] the player can choose, if possible, to make an attack manouver on an opponent's car
    8 => array(
        "name" => "attackManeuvers",
        "type" => "activeplayer",
        "description" => clienttranslate('${actplayer} can choose to attack ${opponent}'), // ACTUALLY TEXT SHOULD DEPEND FROM THE TYPE OF ATTACK
        "descriptionmyturn" => clienttranslate('${you} can choose to attack ${opponent}'),
        "args" => "", // args should return what type of maneuvers are available, and what results might those give (care moving to which position)
        "possibleactions" => array("swapPaint"), // tradurre: sportellata, bussata, sorpasso in scia,
        "transitions" => array( "endMovement" => 9, "pitStop" => 13, "victory" => 15) // AS FOR MOVE CAR STATE, IT'S PROBABLY BEST TO MOVE THESE TRANSITIONS TO NEXT PLAYER GAME STATE (or something equivalent)
    ),

    // FUTURE GEAR DECLARATION (brobably best to call it declareGear)
    // [at the end of his turn] the active player must finally declare what gear he whishes to use for the next turn (minding that he might only shift the current gear by one. PLUS SOME MORE SPECIFIC CASE RULES)
    9 => array(
        "name" => "futureGearDeclaration",
        "type" => "activeplayer",
        "description" => clienttranslate('${actplayer} must state what gear vector they will use in the next turn'),
        "descriptionmyturn" => clienttranslate('${you} must state what gear vector you will use in the next turn'),
        "args" => "argCurrentGear",
        "possibleactions" => array("declareGear"),
        "transitions" => array("" => 10) // only transition possible it the one that gives control back to the game, which in turn gives it to the next player in the turn order
    ),

    // NEXT PLAYER TURN
    // control state in which the game checks if all player have moved their car for this round, if so it produces a new turn order based on the current car standings position.
    // before jumping to the next player, it also checks if the would-be-next player is obstructed by a player in front and asks him if it wishes to yield his turn to play the turn after so to have a clean pathway in front
    10 => array(
        "name" => "nextPlayer",
        "type" => "game",
        "action" => "nextPlayerOrNextRound",
        "transitions" => array( "" => 5)
    ),

    // YIELD TURN
    // a player with an obstructing car in front (but whom's turn order is behind) might decide to yield his turn to that player so to have more space to moove in during his next movement (happens rarely, during sharp turns)
    11 => array(
        "name" => "yieldTurn",
        "type" => "activeplayer",
        "description" => clienttranslate('${actplayer} must choose whether to yield their turn to ${frontPlayer} as it might obstruct their maneuvers (they will be playing just after)'),
        "descriptionmyturn" => clienttranslate('${you} must choose whether to yield your turn to ${frontPlayer} as it might obstruct your maneuvers (you will be playing just after)'),
        "args" => "",
        "possibleactions" => array( "yieldTurn" ),
        "transitions" => array( "" => 10) // should calculates new play order and finally start next turn
    ),

    // PIT STOP
    // a player which enters the pit stop area can collect tire and nitro tokens (minus eventual penalities)
    13 => array(
        "name" => "pitStop",
        "type" => "activeplayer",
        "description" => clienttranslate('${actplayer} must select ${x} tokens'),
        "descriptionmyturn" => clienttranslate('${you} must select ${x} tokens'),
        "args" => "",
        "possibleactions" => array( "takeTokens" ),
        "transitions" => array( "" => 10) // after passing trough the pitbox, player will always start with the 2nd gear
    ),

    // STATE STRUCTURE:
    /*  n => array(
     *      "name" => "",
     *      "type" => "",
     *      "description" => clienttranslate('${actplayer} ...'),
     *      "descriptionmyturn" => clienttranslate('${you} ...'),
     *      "action" => "",
     *      "args" => "",
     *      "possibleactions" => array( ""),
     *      "transitions" => array( "" => )
     *      "updateGameProgression" => true/false,
     *  ),
     */    
   
    
    // FINAL STATE (DO NOT MODIFY)
    99 => array(
        "name" => "gameEnd",
        "description" => clienttranslate("End of game"),
        "type" => "manager",
        "action" => "stGameEnd",
        "args" => "argGameEnd"
    )

);



